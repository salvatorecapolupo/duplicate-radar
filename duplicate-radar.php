<?php
/**
 * Plugin Name:       Duplicate Radar
 * Plugin URI:        https://github.com/salvatorecapolupo/duplicate-radar
 * Description:       Scansione batch dei post duplicati con criteri selezionabili: titolo, permalink e somiglianza del testo. Leggero, sicuro, senza dipendenze esterne.
 * Version:           1.0.3
 * Author:            Salvatore Capolupo
 * License:           MIT License
 * License URI:       https://www.gnu.org/licenses/mit-license.php
 * Text Domain:       duplicate-radar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Motore principale del plugin.
 * Gestisce menu, rendering pagina e logica AJAX di scansione.
 */
class Duplicate_Radar {

	const AJAX_ACTION = 'duplicate_radar_scan';
	const NONCE_KEY   = 'duplicate_radar_nonce';

	// Limite caratteri per similar_text(): evita timeout su articoli enormi.
	const MAX_TEXT_LENGTH = 50000;

	public function __construct() {
		add_action( 'admin_menu',                        [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',             [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION,      [ $this, 'handle_ajax' ] );
	}

	// -------------------------------------------------------------------------
	// Menu e asset
	// -------------------------------------------------------------------------

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
		if ( $hook !== 'tools_page_duplicate-radar' ) {
			return;
		}

		// Passiamo tutto quello che serve al JS via localizzazione,
		// così non abbiamo mai URL hardcoded o dipendenze da variabili globali.
		wp_localize_script( 'jquery', 'duplicateRadar', [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE_KEY ),
			'editBase' => admin_url( 'post.php?action=edit&post=' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Rendering pagina admin
	// -------------------------------------------------------------------------

	public function render_page() {
		?>
		<div class="wrap" id="dr-wrap">
			<h1>📡 Duplicate Radar</h1>
			<p class="description">
				Scansiona tutti i post pubblicati e individua possibili duplicati in base ai criteri scelti.
				La scansione procede post per post: puoi fermarla e riprenderla o riavviarla da zero.
			</p>

			<div class="card" style="max-width:620px; padding:20px 24px; margin-top:12px;">
				<h3 style="margin-top:0;">Criteri di rilevamento</h3>
				<hr>

				<label style="display:block; margin-bottom:12px;">
					<input type="checkbox" id="dr-check-title" checked>
					<strong>Titolo identico</strong> <span class="description">(case-insensitive)</span>
				</label>

				<label style="display:block; margin-bottom:12px;">
					<input type="checkbox" id="dr-check-slug" checked>
					<strong>Permalink simile</strong> <span class="description">(es: articolo vs articolo-2)</span>
				</label>

				<label style="display:block; margin-bottom:12px;">
					<input type="checkbox" id="dr-check-content">
					<strong>Somiglianza contenuto ≥</strong>
					<input type="number" id="dr-threshold" value="80" min="10" max="100" style="width:55px; margin:0 4px;"> %
					<span class="description">(solo testo, ignora HTML)</span>
				</label>

				<hr>

				<button id="dr-start" class="button button-primary button-large">▶ Avvia scansione</button>
				<button id="dr-stop"  class="button button-secondary button-large" style="display:none;">⏹ Ferma</button>
				<button id="dr-reset" class="button button-secondary button-large" style="display:none;">↺ Ricomincia da zero</button>
			</div>

			<div id="dr-progress-wrap" style="display:none; margin-top:18px; max-width:620px;">
				<div style="background:#e0e0e0; height:22px; border-radius:4px; overflow:hidden;">
					<div id="dr-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.25s ease;"></div>
				</div>
				<p id="dr-status" style="margin:6px 0 0; color:#555;"></p>
			</div>

			<div id="dr-results-wrap" style="margin-top:20px; display:none;">
				<h3>Risultati <span id="dr-count" style="font-weight:normal; font-size:14px; color:#777;"></span></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:38%;">Post A</th>
							<th style="width:38%;">Post B (sospetto duplicato)</th>
							<th style="width:24%;">Criterio</th>
						</tr>
					</thead>
					<tbody id="dr-tbody"></tbody>
				</table>
			</div>
		</div>

		<style>
			#dr-wrap .row-actions { visibility: visible !important; margin-top: 4px; font-size: 13px; }
			#dr-wrap .row-actions a { text-decoration: none; }
			#dr-wrap .row-actions .trash a { color: #b32d2e; }
			#dr-stop  { background: #b32d2e; color: #fff; border-color: #8a1e1e; margin-left: 6px; }
			#dr-stop:hover  { background: #8a1e1e !important; border-color: #6e1818 !important; color: #fff; }
			#dr-reset { margin-left: 6px; }
			.dr-badge {
				display: inline-block;
				padding: 2px 7px;
				border-radius: 3px;
				background: #fff3cd;
				color: #856404;
				font-size: 12px;
				font-weight: 600;
			}
		</style>

		<script>
		(function($) {
			'use strict';

			var cfg        = window.duplicateRadar;
			var stopped    = false;
			var totalPosts = 0;
			var matchCount = 0;

			// ---- helpers UI ----

			function setStatus(msg)  { $('#dr-status').html(msg); }
			function setProgress(n)  { $('#dr-bar').css('width', n + '%'); }

			function showButtons(state) {
				// state: 'idle' | 'running' | 'stopped' | 'done'
				$('#dr-start').prop('disabled', state === 'running').text(
					state === 'done' ? '▶ Nuova scansione' : '▶ Avvia scansione'
				);
				$('#dr-stop').toggle(state === 'running');
				$('#dr-reset').toggle(state === 'stopped');
			}

			function buildPostCell(id, title, slug, trashUrl, editBase) {
				var editUrl = editBase + id;
				var viewUrl = '/?p=' + id;
				return '<strong>' + $('<span>').text(title).html() + '</strong>'
					+ ' <span style="color:#999;">#' + id + '</span>'
					+ '<br><code style="font-size:11px; color:#666;">/' + $('<span>').text(slug).html() + '/</code>'
					+ '<div class="row-actions">'
					+ '<span class="edit"><a href="' + editUrl + '" target="_blank">Modifica</a></span>'
					+ ' | <span class="view"><a href="' + viewUrl + '" target="_blank">Visualizza</a></span>'
					+ (trashUrl ? ' | <span class="trash"><a href="' + trashUrl + '" target="_blank">Cestina</a></span>' : '')
					+ '</div>';
			}

			function appendMatch(m) {
				matchCount++;
				$('#dr-results-wrap').show();
				$('#dr-count').text('(' + matchCount + ' coppie trovate)');
				$('#dr-tbody').append(
					'<tr>'
					+ '<td>' + buildPostCell(m.p1_id, m.p1_title, m.p1_slug, m.p1_trash, cfg.editBase) + '</td>'
					+ '<td>' + buildPostCell(m.p2_id, m.p2_title, m.p2_slug, m.p2_trash, cfg.editBase) + '</td>'
					+ '<td><span class="dr-badge">' + $('<span>').text(m.reason).html() + '</span></td>'
					+ '</tr>'
				);
			}

			// ---- reset completo ----

			function resetUI() {
				stopped    = false;
				matchCount = 0;
				totalPosts = 0;
				$('#dr-tbody').empty();
				$('#dr-results-wrap').hide();
				$('#dr-count').text('');
				setProgress(0);
				setStatus('');
				$('#dr-progress-wrap').hide();
			}

			// ---- loop di scansione ----

			function scan(offset) {
				if (stopped) return;

				$.ajax({
					url:      cfg.ajaxUrl,
					method:   'POST',
					timeout:  30000,   // 30s hard timeout per singola chiamata
					data: {
						action:        '<?php echo self::AJAX_ACTION; ?>',
						nonce:          cfg.nonce,
						offset:         offset,
						check_title:    $('#dr-check-title').is(':checked')   ? 1 : 0,
						check_slug:     $('#dr-check-slug').is(':checked')    ? 1 : 0,
						check_content:  $('#dr-check-content').is(':checked') ? 1 : 0,
						threshold:      parseInt($('#dr-threshold').val(), 10) || 80
					},
					success: function(res) {
						if (!res.success) {
							setStatus('<span style="color:#b32d2e;">Errore dal server: ' + (res.data || 'risposta non valida') + '</span>');
							showButtons('idle');
							return;
						}

						totalPosts = res.data.total;

						$.each(res.data.matches, function(i, m) {
							appendMatch(m);
						});

						var done = offset + 1;
						var pct  = Math.min(100, Math.round((done / totalPosts) * 100));
						setProgress(pct);
						setStatus('Analisi post ' + done + ' di ' + totalPosts + '&hellip;');

						if (done < totalPosts && !stopped) {
							scan(done);
						} else if (!stopped) {
							setStatus('<strong>✔ Scansione completata.</strong> ' + matchCount + ' coppie rilevate.');
							showButtons('done');
						}
					},
					error: function(xhr, textStatus) {
						if (textStatus === 'abort') return;
						setStatus('<span style="color:#b32d2e;">Errore di rete o timeout. Riprova.</span>');
						showButtons('stopped');
						$('#dr-reset').show();
					}
				});
			}

			// ---- event handlers ----

			$('#dr-start').on('click', function() {
				resetUI();
				$('#dr-progress-wrap').show();
				showButtons('running');
				scan(0);
			});

			$('#dr-stop').on('click', function() {
				stopped = true;
				showButtons('stopped');
				setStatus('<strong>Scansione interrotta.</strong> Puoi riavviarla da zero o riprendere da dove eri — riavviare è più affidabile.');
			});

			$('#dr-reset').on('click', function() {
				resetUI();
				$('#dr-progress-wrap').show();
				showButtons('running');
				scan(0);
			});

		})(jQuery);
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Handler AJAX (PHP)
	// -------------------------------------------------------------------------

	public function handle_ajax() {
		// 1. Verifica nonce — prima di qualsiasi altra cosa.
		if ( ! check_ajax_referer( self::NONCE_KEY, 'nonce', false ) ) {
			wp_send_json_error( 'Nonce non valido.' );
		}

		// 2. Sanitizzazione input.
		$offset        = max( 0, intval( $_POST['offset'] ) );
		$check_title   = ! empty( $_POST['check_title'] );
		$check_slug    = ! empty( $_POST['check_slug'] );
		$check_content = ! empty( $_POST['check_content'] );
		$threshold     = min( 100, max( 10, intval( $_POST['threshold'] ) ) );

		global $wpdb;

		// 3. Totale post pubblicati (query leggera, COUNT).
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type = 'post'"
		);

		// 4. Post da analizzare in questa iterazione.
		$target = $wpdb->get_row( $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_content
			 FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type = 'post'
			 LIMIT %d, 1",
			$offset
		) );

		$matches = [];

		if ( ! $target ) {
			wp_send_json_success( [ 'matches' => $matches, 'total' => $total ] );
		}

		// Base slug senza suffisso numerico (es: "articolo" da "articolo-2").
		$slug_base = preg_replace( '/-\d+$/', '', $target->post_name );

		// 5. Confronto solo contro i post con ID > target (evita coppie duplicate A-B / B-A).
		$candidates = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_content
			 FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type = 'post' AND ID > %d",
			$target->ID
		) );

		// Testo del target troncato per similar_text.
		$target_text = $check_content
			? substr( wp_strip_all_tags( $target->post_content ), 0, self::MAX_TEXT_LENGTH )
			: '';

		foreach ( $candidates as $candidate ) {
			$reasons = [];

			if ( $check_title && strtolower( trim( $target->post_title ) ) === strtolower( trim( $candidate->post_title ) ) ) {
				$reasons[] = 'Titolo';
			}

			if ( $check_slug && $slug_base === preg_replace( '/-\d+$/', '', $candidate->post_name ) ) {
				$reasons[] = 'Permalink';
			}

			if ( $check_content ) {
				$candidate_text = substr( wp_strip_all_tags( $candidate->post_content ), 0, self::MAX_TEXT_LENGTH );
				similar_text( $target_text, $candidate_text, $percent );
				if ( $percent >= $threshold ) {
					$reasons[] = 'Testo (' . round( $percent ) . '%)';
				}
			}

			if ( ! empty( $reasons ) ) {
				$matches[] = [
					'p1_id'      => $target->ID,
					'p1_title'   => $target->post_title,
					'p1_slug'    => $target->post_name,
					'p1_trash'   => get_delete_post_link( $target->ID, '', false ),
					'p2_id'      => $candidate->ID,
					'p2_title'   => $candidate->post_title,
					'p2_slug'    => $candidate->post_name,
					'p2_trash'   => get_delete_post_link( $candidate->ID, '', false ),
					'reason'     => implode( ' + ', $reasons ),
				];
			}
		}

		wp_send_json_success( [ 'matches' => $matches, 'total' => $total ] );
	}
}

new Duplicate_Radar();
