<?php
/**
 * Plugin Name:       Duplicate Radar
 * Plugin URI:        https://github.com/salvatorecapolupo/duplicate-radar
 * Description:       Batch scan of duplicate posts with selectable criteria: title, permalink, and text similarity.
 * Version:           1.1.0
 * Author:            Salvatore Capolupo
 * License:           MIT License
 * Text Domain:       duplicate-radar
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
		
		// Handlers AJAX
		add_action( 'wp_ajax_' . self::AJAX_SCAN,  [ $this, 'handle_scan' ] );
		add_action( 'wp_ajax_' . self::AJAX_TRASH, [ $this, 'handle_trash' ] );
	}

	public function register_menu() {
		add_management_page(
			__( 'Duplicate Radar', 'duplicate-radar' ),
			__( 'Duplicate Radar', 'duplicate-radar' ),
			'manage_options',
			'duplicate-radar',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'tools_page_duplicate-radar' ) return;

		// In produzione: sposta questo JS in /assets/js/radar.js
		wp_enqueue_script( 'dr-script', plugin_dir_url(__FILE__) . 'js/radar.js', ['jquery'], '1.1.0', true );

		wp_localize_script( 'dr-script', 'drData', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_KEY ),
			'editUrl' => admin_url( 'post.php?action=edit&post=' ),
			'labels'  => [
				'scanning' => __( 'Analisi post %d di %d...', 'duplicate-radar' ),
				'done'     => __( 'Scansione completata.', 'duplicate-radar' ),
				'error'    => __( 'Errore durante l\'operazione.', 'duplicate-radar' )
			]
		]);
	}

	public function render_page() {
		?>
		<div class="wrap" id="dr-wrap">
			<h1>📡 Duplicate Radar</h1>
			<div class="card" style="max-width:800px;">
				<h3><?php _e( 'Criteri di rilevamento', 'duplicate-radar' ); ?></h3>
				<label><input type="checkbox" id="dr-check-title" checked> <?php _e( 'Titolo identico', 'duplicate-radar' ); ?></label><br>
				<label><input type="checkbox" id="dr-check-slug" checked> <?php _e( 'Permalink simile', 'duplicate-radar' ); ?></label><br>
				<label>
					<input type="checkbox" id="dr-check-content"> <?php _e( 'Somiglianza testo ≥', 'duplicate-radar' ); ?>
					<input type="number" id="dr-threshold" value="80" min="10" max="100" style="width:50px;"> %
				</label>
				<hr>
				<button id="dr-start" class="button button-primary"><?php _e( 'Avvia scansione', 'duplicate-radar' ); ?></button>
			</div>

			<div id="dr-progress-wrap" style="display:none; margin: 20px 0;">
				<div style="background:#ddd; width:100%; height:20px;"><div id="dr-bar" style="background:#2271b1; width:0; height:100%;"></div></div>
				<p id="dr-status"></p>
			</div>

			<table class="wp-list-table widefat fixed striped" id="dr-table" style="display:none;">
				<thead>
					<tr>
						<th><?php _e( 'Post Originale', 'duplicate-radar' ); ?></th>
						<th><?php _e( 'Sospetto Duplicato', 'duplicate-radar' ); ?></th>
						<th><?php _e( 'Motivo', 'duplicate-radar' ); ?></th>
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

	// AJAX: Scansione
	public function handle_scan() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		global $wpdb;
		$offset = max( 0, intval( $_POST['offset'] ) );
		
		$total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'" );
		$target = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title, post_name, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' LIMIT %d, 1", $offset ) );

		if ( ! $target ) wp_send_json_success( ['matches' => [], 'total' => $total] );

		$matches = [];
		$candidates = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_name, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' AND ID > %d", $target->ID ) );

		foreach ( $candidates as $can ) {
			$reasons = [];
			if ( !empty($_POST['check_title']) && strtolower($target->post_title) === strtolower($can->post_title) ) $reasons[] = 'Titolo';
			
			if ( !empty($reasons) ) {
				$matches[] = [
					'p1' => [ 'id' => $target->ID, 'title' => $target->post_title ],
					'p2' => [ 'id' => $can->ID, 'title' => $can->post_title ],
					'reason' => implode(', ', $reasons)
				];
			}
		}
		wp_send_json_success( ['matches' => $matches, 'total' => $total] );
	}

	// AJAX: Cestinamento Asincrono
	public function handle_trash() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$post_id = intval( $_POST['post_id'] );
		if ( wp_trash_post( $post_id ) ) {
			wp_send_json_success();
		}
		wp_send_json_error();
	}
}
new Duplicate_Radar();
