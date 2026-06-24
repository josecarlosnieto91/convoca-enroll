<?php

namespace Convoca\Enroll;

/**
 * Checkin_Handler class.
 * Manages QR code check-in logic and endpoint.
 */
class Checkin_Handler {

	public function __construct() {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_checkin_page' ) );
		add_action( 'wp_ajax_conv_qr_checkin', array( $this, 'ajax_qr_checkin' ) );
	}

	/**
	 * Register rewrite rules for /checkin/ and /checkin/{id}/
	 */
	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^checkin/([^/]+)/?', 'index.php?conv_enroll_checkin=$matches[1]', 'top' );
		add_rewrite_rule( '^checkin/?$', 'index.php?conv_enroll_checkin_page=1', 'top' );

		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'convoca_enroll_checkin';
				$vars[] = 'convoca_enroll_checkin_page';
				return $vars;
			}
		);
	}

	/**
	 * Handle the check-in page or direct check-in link.
	 */
	public function handle_checkin_page(): void {
		$checkin_token   = get_query_var( 'convoca_enroll_checkin' );
		$is_scanner_page = get_query_var( 'convoca_enroll_checkin_page' );

		if ( ! $checkin_token && ! $is_scanner_page ) {
			return;
		}

		// Allow administrators, editors, and approved volunteers to access the check-in interface.
		if ( ! current_user_can( 'manage_inscripciones' ) && ! in_array( 'voluntario_aprobado', (array) wp_get_current_user()->roles, true ) ) {
			wp_die( __( 'No tienes permisos para realizar check-in.', 'convoca-enroll' ), __( 'Acceso Denegado', 'convoca-enroll' ), array( 'response' => 403 ) );
		}

		if ( $is_scanner_page ) {
			$this->render_scanner_page();
			exit;
		}

		if ( $checkin_token ) {
			$this->process_direct_checkin( $checkin_token );
			exit;
		}
	}

	/**
	 * Render a modern, mobile-optimized QR scanner page.
	 */
	private function render_scanner_page(): void {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
			<title><?php _e( 'Check-in Convoca', 'convoca-enroll' ); ?></title>
			<?php wp_head(); ?>
			<link rel="preconnect" href="https://fonts.googleapis.com">.
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>.
			<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">.
			<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>.
			<style>
				:root {
					--primary: #10b981;
					--primary-dark: #059669;
					--bg: #0f172a;
					--card-bg: rgba(30, 41, 59, 0.8);
					--text: #f8fafc;
					--text-muted: #94a3b8;
					--success: #22c55e;
					--error: #ef4444;
					--warning: #f59e0b;
				}

				body {
					font-family: 'Outfit', sans-serif;
					background: var(--bg);
					background-image: radial-gradient(circle at top right, #1e293b, #0f172a);
					color: var(--text);
					margin: 0;
					padding: 0;
					height: 100vh;
					overflow: hidden;
				}

				.app-container {
					display: flex;
					flex-direction: column;
					height: 100%;
					max-width: 500px;
					margin: 0 auto;
					position: relative;
				}

				.header {
					padding: 20px;
					text-align: center;
					background: rgba(15, 23, 42, 0.8);
					backdrop-filter: blur(10px);
					border-bottom: 1px solid rgba(255,255,255,0.1);
					z-index: 100;
				}

				h1 { margin: 0; font-size: 1.4rem; font-weight: 600; }
				.subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

				.scanner-wrapper {
					flex: 1;
					position: relative;
					background: #000;
					display: flex;
					align-items: center;
					justify-content: center;
					overflow: hidden;
				}

				#reader { width: 100% !important; height: 100% !important; border: none !important; }
				#reader__scan_region video { object-fit: cover !important; width: 100% !important; height: 100% !important; }
				
				/* Hide default UI elements from library */
				#reader__dashboard_section_csr button, 
				#reader__dashboard_section_fsr button { display: none !important; }

				.overlay-controls {
					position: absolute;
					bottom: 40px;
					left: 0;
					right: 0;
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 15px;
					z-index: 50;
				}

				.status-badge {
					background: rgba(0,0,0,0.6);
					padding: 8px 16px;
					border-radius: 100px;
					font-size: 0.9rem;
					backdrop-filter: blur(5px);
					border: 1px solid rgba(255,255,255,0.2);
				}

				.badge-scanning { color: var(--primary); }
				.badge-processing { color: var(--warning); }

				.action-buttons { display: flex; gap: 20px; }
				.btn-circle {
					width: 60px; height: 60px;
					border-radius: 50%;
					background: rgba(255,255,255,0.15);
					border: 1px solid rgba(255,255,255,0.3);
					display: flex; align-items: center; justify-content: center;
					color: white; font-size: 1.5rem;
					cursor: pointer; backdrop-filter: blur(10px);
					transition: transform 0.2s, background 0.2s;
				}
				.btn-circle:active { transform: scale(0.9); background: rgba(255,255,255,0.3); }

				#result-screen {
					position: fixed; top: 0; left: 0; right: 0; bottom: 0;
					background: var(--bg);
					z-index: 1000;
					display: none;
					flex-direction: column;
					align-items: center;
					justify-content: center;
					padding: 30px;
					text-align: center;
					animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
				}

				@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

				.result-icon { font-size: 5rem; margin-bottom: 20px; }
				.result-title { font-size: 1.8rem; font-weight: 600; margin-bottom: 10px; }
				.result-name { font-size: 1.4rem; color: var(--primary); margin-bottom: 5px; }
				.result-act { font-size: 1rem; color: var(--text-muted); margin-bottom: 30px; }

				.btn-primary {
					background: var(--primary); color: white;
					border: none; padding: 15px 40px;
					border-radius: 12px; font-weight: 600; font-size: 1.1rem;
					cursor: pointer; width: 100%; max-width: 300px;
				}

				.camera-selector {
					position: absolute; top: 80px; left: 20px; right: 20px;
					z-index: 100; display: none;
				}
				select {
					width: 100%; padding: 12px; border-radius: 10px;
					background: rgba(0,0,0,0.8); color: white;
					border: 1px solid rgba(255,255,255,0.2); font-family: inherit;
				}
			</style>
		</head>
		<body>
			<div class="app-container">
				<div class="header">
					<h1><?php _e( 'Check-in Convoca', 'convoca-enroll' ); ?></h1>
					<p class="subtitle"><?php _e( 'Escáner de Asistencia', 'convoca-enroll' ); ?></p>
				</div>

				<div class="scanner-wrapper">
					<div id="reader"></div>
					
					<div class="overlay-controls">
						<div id="status-badge" class="status-badge badge-scanning"><?php _e( 'Escaneando...', 'convoca-enroll' ); ?></div>
						<div class="action-buttons">
							<button class="btn-circle" id="btn-camera" title="Cambiar Cámara">📷</button>
							<button class="btn-circle" id="btn-torch" title="Linterna">🔦</button>
						</div>
					</div>

					<div class="camera-selector" id="cam-select-wrap">
						<select id="cam-select"></select>
					</div>
				</div>

				<div id="result-screen">
					<div class="result-icon" id="res-icon">✅</div>
					<div class="result-title" id="res-title">¡Check-in Exitoso!</div>
					<div class="result-name" id="res-name">Jose Carlos</div>
					<div class="result-act" id="res-act">Visita al Centro Social</div>
					<button class="btn-primary" id="btn-continue"><?php _e( 'Siguiente Escaneo', 'convoca-enroll' ); ?></button>
				</div>
			</div>

			<script>
				const statusBadge = document.getElementById('status-badge');
				const resultScreen = document.getElementById('result-screen');
				const resIcon = document.getElementById('res-icon');
				const resTitle = document.getElementById('res-title');
				const resName = document.getElementById('res-name');
				const resAct = document.getElementById('res-act');
				const btnContinue = document.getElementById('btn-continue');
				const btnTorch = document.getElementById('btn-torch');
				const btnCamera = document.getElementById('btn-camera');
				const camSelect = document.getElementById('cam-select');
				const camWrap = document.getElementById('cam-select-wrap');

				let html5QrCode;
				let isProcessing = false;
				let torchOn = false;
				let currentCameraId;

				async function onScanSuccess(decodedText) {
					if (isProcessing) return;
					isProcessing = true;
					
					statusBadge.innerText = "<?php _e( 'Procesando...', 'convoca-enroll' ); ?>";
					statusBadge.className = 'status-badge badge-processing';

					// Extract token.
					let token = decodedText;
					if (token.includes('token=')) {
						token = token.split('token=')[1].split('&')[0];
					} else if (token.includes('convoca_enroll_token=')) {
						token = token.split('convoca_enroll_token=')[1].split('&')[0];
					}

					const formData = new URLSearchParams();
					formData.append('action', 'convoca_enroll_qr_checkin');
					formData.append('nonce', '<?php echo wp_create_nonce( 'convoca_enroll_qr_checkin' ); ?>');
					formData.append('id', token);

					try {
						const response = await fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: formData
						});
						const data = await response.json();
						
						showResult(data);
					} catch (err) {
						console.error(err);
						alert("Error de conexión");
						isProcessing = false;
						statusBadge.className = 'status-badge badge-scanning';
						statusBadge.innerText = "<?php _e( 'Escaneando...', 'convoca-enroll' ); ?>";
					}
				}

				function showResult(data) {
					resultScreen.style.display = 'flex';
					
					if (data.success) {
						resIcon.innerText = '✅';
						resTitle.innerText = data.data.message;
						resName.innerText = data.data.details.nombre;
						resAct.innerText = data.data.details.actividad;
						resultScreen.style.borderTop = '10px solid var(--success)';
						if (window.navigator.vibrate) window.navigator.vibrate([100, 50, 100]);
					} else {
						resIcon.innerText = '❌';
						resTitle.innerText = 'Error';
						resName.innerText = data.data;
						resAct.innerText = '';
						resultScreen.style.borderTop = '10px solid var(--error)';
						if (window.navigator.vibrate) window.navigator.vibrate(300);
					}
				}

				btnContinue.onclick = () => {
					resultScreen.style.display = 'none';
					statusBadge.className = 'status-badge badge-scanning';
					statusBadge.innerText = "<?php _e( 'Escaneando...', 'convoca-enroll' ); ?>";
					isProcessing = false;
				};

				btnCamera.onclick = () => {
					camWrap.style.display = camWrap.style.display === 'block' ? 'none' : 'block';
				};

				camSelect.onchange = (e) => {
					currentCameraId = e.target.value;
					camWrap.style.display = 'none';
					stopAndRestart();
				};

				btnTorch.onclick = async () => {
					torchOn = !torchOn;
					try {
						await html5QrCode.applyVideoConstraints({
							advanced: [{ torch: torchOn }]
						});
						btnTorch.style.background = torchOn ? 'var(--warning)' : 'rgba(255,255,255,0.15)';
					} catch (err) {
						console.warn("Torch no soportado", err);
						alert("La linterna no es compatible con este dispositivo o cámara.");
					}
				};

				async function initScanner() {
					html5QrCode = new Html5Qrcode("reader");
					
					try {
						const devices = await Html5Qrcode.getCameras();
						if (devices && devices.length > 0) {
							// Fill selector.
							devices.forEach(device => {
								const option = document.createElement('option');
								option.value = device.id;
								option.text = device.label;
								camSelect.appendChild(option);
							});

							// Auto-select back camera (usually last one).
							currentCameraId = devices[devices.length - 1].id;
							camSelect.value = currentCameraId;
							
							startScanner(currentCameraId);
						} else {
							statusBadge.innerText = "No se detectaron cámaras.";
						}
					} catch (e) {
						console.error(e);
						statusBadge.innerText = "Error al acceder a la cámara.";
					}
				}

				function startScanner(cameraId) {
					html5QrCode.start(
						cameraId, 
						{ fps: 15, qrbox: (width, height) => {
							const minSide = Math.min(width, height);
							const qrSize = Math.floor(minSide * 0.7);
							return { width: qrSize, height: qrSize };
						}},
						onScanSuccess
					).catch(err => console.error("Error starting scanner", err));
				}

				async function stopAndRestart() {
					if (html5QrCode.isScanning) {
						await html5QrCode.stop();
					}
					startScanner(currentCameraId);
				}

				initScanner();

				// ── PWA: installation prompt ────────────────────────
				let deferredPrompt;
				window.addEventListener('beforeinstallprompt', (e) => {
					e.preventDefault();
					deferredPrompt = e;
					// Show install button.
					const installBtn = document.createElement('button');
					installBtn.id = 'btn-install';
					installBtn.className = 'btn-circle';
					installBtn.title = 'Instalar app';
					installBtn.innerHTML = '📲';
					installBtn.style.background = 'rgba(16,185,129,0.3)';
					document.querySelector('.action-buttons')?.appendChild(installBtn);
					installBtn.onclick = async () => {
						deferredPrompt.prompt();
						const result = await deferredPrompt.userChoice;
						if (result.outcome === 'accepted') installBtn.remove();
						deferredPrompt = null;
					};
				});

				// ── Offline queue sync ───────────────────────────────
				async function syncOfflineQueue() {
					if (!navigator.serviceWorker) return;
					const reg = await navigator.serviceWorker.ready;
					// Request a sync event.
					if ('sync' in reg) {
						await reg.sync.register('conv-flush-checkins');
					}
				}
				window.addEventListener('online', syncOfflineQueue);
				syncOfflineQueue();

				// Show queue status indicator.
				const queueBadge = document.createElement('div');
				queueBadge.id = 'queue-status';
				queueBadge.style.cssText = 'position:fixed;top:80px;right:10px;background:rgba(245,158,11,0.9);color:#fff;padding:4px 10px;border-radius:20px;font-size:11px;z-index:1000;display:none';
				queueBadge.innerText = '⏳ Pendientes';
				document.body.appendChild(queueBadge);

				// Monitor IndexedDB queue length.
				setInterval(async () => {
					if ('indexedDB' in window) {
						const db = await new Promise((resolve) => {
							const req = indexedDB.open('conv-checkin-queue');
							req.onsuccess = () => resolve(req.result);
						});
						const tx = db.transaction('pending', 'readonly');
						const count = await new Promise((resolve) => {
							const req = tx.objectStore('pending').count();
							req.onsuccess = () => resolve(req.result);
						});
						queueBadge.style.display = count > 0 ? 'block' : 'none';
						queueBadge.innerText = '⏳ ' + count + ' pendientes' + (navigator.onLine ? ' (sin conexión)' : '');
					}
				}, 5000);
			</script>
		</body>
		</html>
		<?php
	}

	/**
	 * Mark an inscription as attended using its token.
	 * Uses SQL transactions and FOR UPDATE to prevent race conditions.
	 * Is idempotent: returns true if already marked.
	 */
	private function mark_as_attended_by_token( string $token ): bool|\WP_Error {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. Find and LOCK the inscription post row
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID 
                 FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                 WHERE p.post_type = 'inscripcion' 
                 AND pm.meta_key = '_convoca_checkin_token' 
                 AND pm.meta_value = %s 
                 LIMIT 1 FOR UPDATE",
					$token
				)
			);

			if ( ! $id ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'invalid_token', __( 'Token de check-in no válido.', 'convoca-enroll' ) );
			}

			// 2. Lock the relevant postmeta rows to prevent concurrent modifications
			$wpdb->query(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta} 
                 WHERE post_id = %d AND meta_key IN ('_convoca_checkin_token', '_convoca_estado', '_convoca_asistencia') 
                 FOR UPDATE",
					$id
				)
			);

			// 3. Verify token and check state (now safe from concurrent writes)
			$db_token = get_post_meta( $id, '_convoca_checkin_token', true );
			if ( ! hash_equals( $db_token, $token ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'invalid_token', __( 'Token de check-in no válido.', 'convoca-enroll' ) );
			}

			$estado = get_post_meta( $id, '_convoca_estado', true );
			if ( $estado !== 'confirmada' ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'not_confirmed', __( 'La inscripción no está confirmada.', 'convoca-enroll' ) );
			}

			// 4. IDEMPOTENCY: Check if already attended
			$asistencia = get_post_meta( $id, '_convoca_asistencia', true );
			if ( $asistencia === 'si' ) {
				$wpdb->query( 'COMMIT' );
				return true;
			}

			// 5. Mark attendance
			$result = Motor_Inscripcion::set_asistencia( $id, 'si' );

			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}

			$wpdb->query( 'COMMIT' );
			return true;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'checkin_error', __( 'Error al procesar el check-in.', 'convoca-enroll' ) );
		}
	}

	/**
	 * Process a direct check-in (token from scan).
	 */
	private function process_direct_checkin( string $token ): void {
		$result = $this->mark_as_attended_by_token( $token );

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message(), __( 'Error de Check-in', 'convoca-enroll' ) );
		}

		// Get info for the message.
		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'meta_key'       => '_convoca_checkin_token',
				'meta_value'     => $token,
				'posts_per_page' => 1,
			)
		);
		$id           = $inscriptions[0]->ID;
		$nombre       = get_post_meta( $id, '_convoca_nombre', true );
		$act_id       = (int) get_post_meta( $id, '_convoca_actividad_id', true );
		$act_title    = get_the_title( $act_id );

		wp_die(
			sprintf( __( 'Check-in confirmado para %1$s en "%2$s".', 'convoca-enroll' ), '<strong>' . esc_html( $nombre ) . '</strong>', esc_html( $act_title ) ),
			__( 'Check-in Exitoso', 'convoca-enroll' ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}

	/**
	 * AJAX handler for scanner check-in.
	 */
	public function ajax_qr_checkin(): void {
		check_ajax_referer( 'convoca_enroll_qr_checkin', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! current_user_can( 'manage_inscripciones' ) && ! in_array( 'voluntario_aprobado', (array) wp_get_current_user()->roles, true ) ) {
			wp_send_json_error( __( 'No tienes permisos para realizar check-in.', 'convoca-enroll' ) );
		}

		$token  = sanitize_text_field( $_POST['id'] );
		$result = $this->mark_as_attended_by_token( $token );

		if ( is_wp_error( $result ) ) {
			\Convoca\Core\Logger::info( 'Intento de check-in fallido (AJAX): ' . $result->get_error_message(), 'Enroll/Checkin', $user_id );
			wp_send_json_error( $result->get_error_message() );
		}

		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'meta_key'       => '_convoca_checkin_token',
				'meta_value'     => $token,
				'posts_per_page' => 1,
			)
		);
		$id           = $inscriptions[0]->ID;
		$nombre       = get_post_meta( $id, '_convoca_nombre', true );
		$act_id       = (int) get_post_meta( $id, '_convoca_actividad_id', true );

		// Check activity permission.
		if ( ! CPT_Actividad::is_user_responsible( $user_id, $act_id ) ) {
			\Convoca\Core\Logger::info( "Intento de check-in fallido (AJAX): Sin permisos para actividad #$act_id", 'Enroll/Checkin', $user_id, array( 'inscripcion_id' => $id ) );
			wp_send_json_error( __( 'No tienes permiso para gestionar esta actividad.', 'convoca-enroll' ) );
		}

		\Convoca\Core\Logger::info( "Check-in exitoso vía QR (AJAX) por usuario #$user_id", 'Enroll/Checkin', $user_id, array( 'inscripcion_id' => $id ) );

		wp_send_json_success(
			array(
				'message' => __( 'Asistencia confirmada', 'convoca-enroll' ),
				'details' => array(
					'nombre'    => $nombre,
					'actividad' => get_the_title( $act_id ),
				),
			)
		);
	}
}
