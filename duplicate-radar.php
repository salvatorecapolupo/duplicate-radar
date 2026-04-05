<?php
/**
 * Plugin Name:       Duplicate Radar
 * Plugin URI:        https://github.com/salvatorecapolupo/duplicate-radar
 * Description:       Batch scan of duplicate posts with selectable criteria: title, permalink, and text similarity.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Salvatore Capolupo
 * License:           MIT License
 * Text Domain:       duplicate-radar-main
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Duplicate_Radar {

	const AJAX_SCAN  = 'dr_scan';
	const AJAX_TRASH = 'dr_trash_post';
	const NONCE_KEY  = 'dr_radar_nonce';
	const MAX_TEXT   = 50000;

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		
		add_action( 'wp_ajax_' . self::AJAX_SCAN,  [ $this, 'handle_scan' ] );
		add_action( 'wp_ajax_' . self::AJAX_TRASH, [ $this, 'handle_trash' ] );
	}

	public function register_menu() {
		add_management_page(
			esc_html__( 'Duplicate Radar', 'duplicate-radar-main' ),
			esc_html__( 'Duplicate Radar', 'duplicate-radar-main' ),
			'manage_options',
			'duplicate-radar',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'tools_page_duplicate-radar' ) return;

		wp_enqueue_script( 'dr-script', plugin_dir_url(__FILE__) . 'js/radar.js', ['jquery'], '1.1.0', true );

		wp_localize_script( 'dr-script', 'drData', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_KEY ),
			'editUrl' => admin_url( 'post.php?action=edit&post=' ),
			'labels'  => [
				'scanning' => esc_html__( 'Analisi in corso...', 'duplicate-radar-main' ),
				'done'     => esc_html__( 'Scansione completata.', 'duplicate-radar-main' ),
				'error'    => esc_html__( 'Errore durante l\'operazione.', 'duplicate-radar-main' )
			]
		]);
	}

	public function render_page() {
		?>
		<div class="wrap" id="dr-wrap">
			<h1>📡 Duplicate Radar</h1>
			<div class="card" style="max-width:800px;">
				<h3><?php esc_html_e( 'Criteri di rilevamento', 'duplicate-radar-main' ); ?></h3>
				<label><input type="checkbox" id="dr-check-title" checked> <?php esc_html_e( 'Titolo identico', 'duplicate-radar-main' ); ?></label><br>
				<label><input type="checkbox" id="dr-check-slug" checked> <?php esc_html_e( 'Permalink simile', 'duplicate-radar-main' ); ?></label><br>
				<label>
					<input type="checkbox" id="dr-check-content"> <?php esc_html_e( 'Somiglianza testo ≥', 'duplicate-radar-main' ); ?>
					<input type="number" id="dr-threshold" value="80" min="10" max="100" style="width:50px;"> %
				</label>
				<hr>
				<button id="dr-start" class="button button-primary"><?php esc_html_e( 'Avvia scansione', 'duplicate-radar-main' ); ?></button>
			</div>

			<div id="dr-progress-wrap" style="display:none; margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<div style="background:#f0f0f1; width:100%; height:20px; border-radius: 3px; overflow: hidden;">
                    <div id="dr-bar" style="background:#2271b1; width:0; height:100%; transition: width 0.3s ease;"></div>
                </div>
				<p id="dr-status" style="margin: 10px 0 0 0; font-weight: 600;"></p>
			</div>

			<table class="wp-list-table widefat fixed striped" id="dr-table" style="display:none;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post Originale', 'duplicate-radar-main' ); ?></th>
						<th><?php esc_html_e( 'Sospetto Duplicato', 'duplicate-radar-main' ); ?></th>
						<th><?php esc_html_e( 'Motivo', 'duplicate-radar-main' ); ?></th>
					</tr>
				</thead>
				<tbody id="dr-tbody"></tbody>
			</table>
		</div>
		<style>
            .dr-trash-link { color: #b32d2e; cursor: pointer; }
            .dr-row-fading { background-color: #ffe4e4 !important; transition: opacity 0.5s; opacity: 0; }
        </style>
		<?php
	}

	public function handle_scan() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permessi insufficienti.' );
        }

		global $wpdb;
        
        // Sanitizzazione input con wp_unslash
		$offset        = max( 0, intval( wp_unslash( $_POST['offset'] ?? 0 ) ) );
        $check_title   = filter_var( wp_unslash( $_POST['check_title'] ?? false ), FILTER_VALIDATE_BOOLEAN );
        $check_slug    = filter_var( wp_unslash( $_POST['check_slug'] ?? false ), FILTER_VALIDATE_BOOLEAN );
        $check_content = filter_var( wp_unslash( $_POST['check_content'] ?? false ), FILTER_VALIDATE_BOOLEAN );
        $threshold     = max( 10, min( 100, intval( wp_unslash( $_POST['threshold'] ?? 80 ) ) ) );
		
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'" );
        
        $fields = ['ID', 'post_title'];
        if ( $check_slug ) {
            $fields[] = 'post_name';
        }
        if ( $check_content ) {
            $fields[] = 'post_content';
        }
        $fields_sql = esc_sql( implode( ', ', $fields ) );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		$target = $wpdb->get_row( $wpdb->prepare( "SELECT {$fields_sql} FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT %d, 1", $offset ) );

		if ( ! $target ) {
            wp_send_json_success( ['matches' => [], 'total' => $total] );
        }

		$matches = [];
		$candidates = $wpdb->get_results( $wpdb->prepare( "SELECT {$fields_sql} FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' AND ID > %d", $target->ID ) );
        // phpcs:enable

		foreach ( $candidates as $can ) {
			$reasons = [];
            
			if ( $check_title && strtolower(trim($target->post_title)) === strtolower(trim($can->post_title)) ) {
                $reasons[] = 'Titolo';
            }
            
            if ( $check_slug && strtolower(trim($target->post_name)) === strtolower(trim($can->post_name)) ) {
                $reasons[] = 'Permalink';
            }

            if ( $check_content && !empty($target->post_content) && !empty($can->post_content) ) {
                $t_text = mb_substr( wp_strip_all_tags( $target->post_content ), 0, self::MAX_TEXT );
                $c_text = mb_substr( wp_strip_all_tags( $can->post_content ), 0, self::MAX_TEXT );
                
                similar_text( $t_text, $c_text, $perc );
                if ( $perc >= $threshold ) {
                    $reasons[] = sprintf( 'Testo (%.1f%%)', $perc );
                }
            }
			
			if ( !empty($reasons) ) {
				$matches[] = [
					'p1' => [ 'id' => $target->ID, 'title' => esc_html( $target->post_title ?: 'Senza Titolo' ) ],
					'p2' => [ 'id' => $can->ID, 'title' => esc_html( $can->post_title ?: 'Senza Titolo' ) ],
					'reason' => esc_html( implode(', ', $reasons) )
				];
			}
		}
		wp_send_json_success( ['matches' => $matches, 'total' => $total] );
	}

	public function handle_trash() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        // Verifica validazione array post_id
        if ( ! isset( $_POST['post_id'] ) ) {
            wp_send_json_error( 'ID post non fornito.' );
        }

		$post_id = intval( wp_unslash( $_POST['post_id'] ) );
		if ( wp_trash_post( $post_id ) ) {
			wp_send_json_success();
		}
		wp_send_json_error( 'Impossibile cestinare il post.' );
	}
}
new Duplicate_Radar();
