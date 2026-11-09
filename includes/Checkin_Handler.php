<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Checkin_Handler class.
 * Manages QR code check-in logic and endpoint.
 */
class Checkin_Handler {

	public function __construct() {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_checkin_page' ) );
		add_action( 'wp_ajax_convoca_qr_checkin', array( $this, 'ajax_qr_checkin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkin_assets' ) );
	}

	/**
	 * Enqueue check-in scanner assets.
	 */
	public function enqueue_checkin_assets(): void {
		if ( ! get_query_var( 'convoca_enroll_checkin_page' ) && ! get_query_var( 'convoca_enroll_checkin' ) ) {
			return;
		}

		// Assets locales (requisito wp.org: prohibido offloading a CDNs externos).
		wp_enqueue_style( 'convoca-checkin-fonts', CONVOCA_ENROLL_URL . 'assets/css/convoca-checkin-fonts.css', array(), CONVOCA_ENROLL_VERSION );
		wp_enqueue_script( 'convoca-checkin-qrcode', CONVOCA_ENROLL_URL . 'assets/vendor/html5-qrcode.min.js', array(), '2.3.8', false );
	}

	/**
	 * Register rewrite rules for /checkin/ and /checkin/{id}/
	 */
	public function register_rewrite_rules(): void {
		// Los nombres tienen que ser EXACTAMENTE los de la query var registrada abajo
		// (`convoca_enroll_checkin*`). Estaban como `conv_enroll_checkin*`, así que
		// `/checkin/` y `/checkin/<token>/` no rellenaban ninguna query var, el handler
		// no se ejecutaba y la página del escáner —con su cámara y su PWA— era
		// inalcanzable por su propia URL (verificado: 200 con la web normal en vez del
		// 403 del handler).
		add_rewrite_rule( '^checkin/([^/]+)/?', 'index.php?convoca_enroll_checkin=$matches[1]', 'top' );
		add_rewrite_rule( '^checkin/?$', 'index.php?convoca_enroll_checkin_page=1', 'top' );

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
		$checkin_token   = (string) get_query_var( 'convoca_enroll_checkin' );
		$is_scanner_page = (bool) get_query_var( 'convoca_enroll_checkin_page' );

		if ( '' === $checkin_token && ! $is_scanner_page ) {
			return;
		}

		// El escáner es herramienta de trabajo: solo personal.
		if ( $is_scanner_page ) {
			if ( ! $this->es_personal() ) {
				$this->denegar();
			}

			$this->render_scanner_page();
			exit;
		}

		// Check-in directo: solo personal. Antes bastaba la firma `h` que viajaba en el enlace del
		// correo, así que cualquiera con ese enlace se marcaba la asistencia a sí mismo, sin sesión
		// y semanas antes de la actividad (enroll#76). El QR sigue siendo el mismo; lo lee el
		// escáner del monitor, que exige sesión y responsabilidad sobre la actividad.
		$inscripcion_id = Checkin_Link::id_for_token( $checkin_token );

		switch ( $this->respuesta_checkin_directo( $inscripcion_id ) ) {
			case 200:
				$this->process_direct_checkin( $checkin_token );
				exit;
			case 404:
				$this->no_encontrado();
				break;
			default:
				$this->denegar();
		}
	}

	/**
	 * Qué se responde a un intento de check-in directo.
	 *
	 * Decisión pura, sin consultas ni salidas, para poder probarla sin arrancar WordPress:
	 * un enlace que no corresponde a ninguna inscripción no es un problema de permisos —es
	 * que no existe—, y decir «Acceso Denegado» ahí confunde a quien lo recibe.
	 *
	 * @param int $inscripcion_id Inscripción del token (0 si no existe).
	 * @return int 200 se hace, 404 el enlace no existe, 403 no es personal.
	 */
	public function respuesta_checkin_directo( int $inscripcion_id ): int {
		if ( $inscripcion_id <= 0 ) {
			return 404;
		}

		return $this->puede_checkin_directo( $inscripcion_id ) ? 200 : 403;
	}

	/**
	 * ¿Es personal con permiso para el escáner de check-in?
	 *
	 * `convoca_manage_checkin` es la capacidad del módulo; `manage_inscripciones` se acepta para
	 * instalaciones antiguas y el rol `voluntario_aprobado` mientras la migración concede la
	 * capacidad (es lo que mantiene la puerta abierta en un sitio ya desplegado).
	 *
	 * @return bool
	 */
	public function es_personal(): bool {
		return current_user_can( 'convoca_manage_checkin' )
			|| current_user_can( 'manage_inscripciones' )
			|| in_array( 'voluntario_aprobado', (array) wp_get_current_user()->roles, true );
	}

	/**
	 * ¿Puede hacerse el check-in directo de esa inscripción?
	 *
	 * Solo el personal. La firma del enlace ya no autoriza nada: el enlace es una comodidad para
	 * el monitor, no una credencial del asistente (enroll#76).
	 *
	 * @param int $inscripcion_id Inscripción a la que pertenece el token (0 si no existe).
	 * @return bool
	 */
	public function puede_checkin_directo( int $inscripcion_id = 0 ): bool {
		return $inscripcion_id > 0 && $this->es_personal();
	}

	/**
	 * Código HTTP que corresponde a un error de check-in.
	 *
	 * Antes cualquier error salía como 500, que significa «el servidor se rompió» cuando en
	 * realidad lo que no valía era el enlace.
	 *
	 * @param WP_Error $error Error del motor.
	 * @return int
	 */
	public function status_para_error( WP_Error $error ): int {
		switch ( $error->get_error_code() ) {
			case 'invalid_token':
				return 404;
			case 'not_confirmed':
			case 'checkin_temprano':
			case 'checkin_cerrado':
				return 409;
			case 'no_user':
				return 403;
			default:
				return 400;
		}
	}

	/**
	 * Corta la petición por falta de permiso.
	 */
	private function denegar(): void {
		wp_die(
			esc_html__( 'No tienes permisos para realizar check-in.', 'convoca-enroll' ),
			esc_html__( 'Acceso Denegado', 'convoca-enroll' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Corta la petición porque el enlace no corresponde a ninguna inscripción.
	 */
	private function no_encontrado(): void {
		wp_die(
			esc_html__( 'Este enlace de check-in no corresponde a ninguna inscripción. Comprueba que has escaneado el código correcto.', 'convoca-enroll' ),
			esc_html__( 'Enlace no válido', 'convoca-enroll' ),
			array( 'response' => 404 )
		);
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
			<title><?php esc_html_e( 'Check-in Convoca', 'convoca-enroll' ); ?></title>
			<?php wp_head(); ?>
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

				.manual-checkin {
					width: calc(100% - 40px); padding: 12px; border-radius: 12px;
					background: rgba(15,23,42,0.85); border: 1px solid rgba(255,255,255,0.15);
				}
				.manual-checkin label { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px; }
				.manual-row { display: flex; gap: 8px; }
				.manual-row input {
					flex: 1; min-width: 0; padding: 10px; border-radius: 8px;
					background: rgba(0,0,0,0.6); color: white; border: 1px solid rgba(255,255,255,0.2);
					font-family: inherit;
				}
				.manual-row button {
					padding: 10px 16px; border-radius: 8px; border: none;
					background: var(--primary); color: white; font-weight: 600; cursor: pointer;
				}
			</style>
		</head>
		<body>
			<div class="app-container">
				<div class="header">
					<h1><?php esc_html_e( 'Check-in Convoca', 'convoca-enroll' ); ?></h1>
					<p class="subtitle"><?php esc_html_e( 'Escáner de Asistencia', 'convoca-enroll' ); ?></p>
				</div>

				<div class="scanner-wrapper">
					<div id="reader"></div>

					<div class="overlay-controls">
						<div id="status-badge" class="status-badge badge-scanning"><?php esc_html_e( 'Escaneando...', 'convoca-enroll' ); ?></div>

						<div class="manual-checkin">
							<label for="manual-code"><?php esc_html_e( 'Sin cámara: teclea el código de reserva o pega el enlace', 'convoca-enroll' ); ?></label>
							<div class="manual-row">
								<input type="text" id="manual-code" inputmode="text" autocomplete="off" placeholder="<?php esc_attr_e( 'Código o enlace', 'convoca-enroll' ); ?>">
								<button type="button" id="btn-manual"><?php esc_html_e( 'Marcar', 'convoca-enroll' ); ?></button>
							</div>
						</div>

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
					<div class="result-title" id="res-title"></div>
					<div class="result-name" id="res-name"></div>
					<div class="result-act" id="res-act"></div>
					<button class="btn-primary" id="btn-continue"><?php esc_html_e( 'Siguiente Escaneo', 'convoca-enroll' ); ?></button>
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

					statusBadge.innerText = "<?php echo esc_js( __( 'Procesando...', 'convoca-enroll' ) ); ?>";
					statusBadge.className = 'status-badge badge-processing';

					const token = tokenFromScan(decodedText);

					const formData = new URLSearchParams();
					formData.append('action', 'convoca_qr_checkin');
					formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'convoca_enroll_qr_checkin' ) ); ?>');
					formData.append('id', token);

					try {
						const response = await fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
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
						statusBadge.innerText = "<?php echo esc_js( __( 'Escaneando...', 'convoca-enroll' ) ); ?>";
					}
				}

				/**
				 * El QR del correo es una URL (/checkin/<token>/?h=…), no un token suelto. Antes se
				 * mandaba la URL entera como `id` y el servidor la rechazaba: el escáner nunca
				 * encontraba la inscripción. Acepta también token suelto y ?token=.
				 */
				function tokenFromScan(texto) {
					const limpio = String(texto || '').trim();
					try {
						const url = new URL(limpio);
						const enRuta = url.pathname.match(/\/checkin\/([^/]+)\/?$/);
						if (enRuta) return decodeURIComponent(enRuta[1]);
						const enQuery = url.searchParams.get('token') || url.searchParams.get('convoca_enroll_token');
						if (enQuery) return enQuery;
					} catch (e) {
						// No es una URL: es el token.
					}
					return limpio;
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
					statusBadge.innerText = "<?php echo esc_js( __( 'Escaneando...', 'convoca-enroll' ) ); ?>";
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

				// Respaldo sin cámara: se teclea el código de reserva o se pega el enlace. Es el
				// camino que queda cuando el permiso de cámara está denegado o la librería falla.
				const manualInput = document.getElementById('manual-code');
				const manualBtn   = document.getElementById('btn-manual');
				manualBtn.onclick = () => {
					const texto = manualInput.value.trim();
					if (!texto) { manualInput.focus(); return; }
					manualInput.value = '';
					onScanSuccess(texto);
				};
				manualInput.addEventListener('keydown', (e) => {
					if (e.key === 'Enter') { e.preventDefault(); manualBtn.click(); }
				});

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
				// Base de datos: mismo nombre y MISMA versión que el service worker. La página abre
				// con versión explícita y crea el almacén si falta: antes abría `open(nombre)` (v1)
				// sin `onupgradeneeded`, creaba la base vacía y el service worker ya no podía
				// añadir `pending` nunca (enroll#82).
				const DB_NAME = 'conv-checkin-queue';
				const DB_VERSION = 2;
				const STORE = 'pending';

				function abrirCola() {
					return new Promise((resolve, reject) => {
						const req = indexedDB.open(DB_NAME, DB_VERSION);
						req.onupgradeneeded = () => {
							if (!req.result.objectStoreNames.contains(STORE)) {
								req.result.createObjectStore(STORE, { autoIncrement: true });
							}
						};
						req.onsuccess = () => resolve(req.result);
						req.onerror = () => reject(req.error);
					});
				}

				async function syncOfflineQueue() {
					if (!navigator.serviceWorker) return;
					try {
						const reg = await navigator.serviceWorker.ready;
						if ('sync' in reg) {
							await reg.sync.register('conv-flush-checkins');
						} else if (reg.active) {
							// Sin Background Sync (Firefox/Safari): se pide el volcado al worker.
							reg.active.postMessage({ type: 'conv-flush-checkins' });
						}
					} catch (e) {
						// Permiso denegado o Background Sync deshabilitado: no es un error de la página.
						console.warn('Background Sync no disponible', e);
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

				// Monitor IndexedDB queue length. Cualquier fallo de la base se traga: el badge es
				// informativo y no puede tumbar el escáner cada cinco segundos.
				setInterval(async () => {
					if (!('indexedDB' in window)) return;
					try {
						const db = await abrirCola();
						const tx = db.transaction(STORE, 'readonly');
						const count = await new Promise((resolve, reject) => {
							const req = tx.objectStore(STORE).count();
							req.onsuccess = () => resolve(req.result);
							req.onerror = () => reject(req.error);
						});
						queueBadge.style.display = count > 0 ? 'block' : 'none';
						queueBadge.innerText = '⏳ ' + count + ' pendientes' + (navigator.onLine ? '' : ' (sin conexión)');
						db.close();
					} catch (e) {
						queueBadge.style.display = 'none';
					}
				}, 5000);
			</script>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Marca la asistencia con el token del check-in, con bloqueo de la inscripción.
	 *
	 * La transacción y el `FOR UPDATE` evitan que dos escaneos simultáneos se pisen. Las reglas de
	 * verdad (estado, ventana, hora y usuario) viven en `Checkin::registrar()`, para que las tres
	 * rutas no puedan separarse otra vez.
	 *
	 * @param string $token   Token del QR.
	 * @param int    $user_id Quién lo registra (0 = sesión actual).
	 * @return array{inscripcion_id: int, cambiada: bool, anterior: string, hora: string, ya_marcada: bool}|\WP_Error
	 */
	public function mark_as_attended_by_token( string $token, int $user_id = 0 ): array|\WP_Error {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. Find and LOCK the inscription post row.
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

			// 2. Lock the relevant postmeta rows to prevent concurrent modifications.
			$wpdb->query(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key IN ('_convoca_checkin_token', '_convoca_estado', '_convoca_asistencia', '_convoca_checkin_hora', '_convoca_checkin_by')
                 FOR UPDATE",
					$id
				)
			);

			// 3. Verify token (now safe from concurrent writes).
			$db_token = (string) get_post_meta( (int) $id, '_convoca_checkin_token', true );
			if ( ! hash_equals( $db_token, $token ) ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'invalid_token', __( 'Token de check-in no válido.', 'convoca-enroll' ) );
			}

			// 4. Marcar con las reglas compartidas.
			$resultado = Checkin::registrar( (int) $id, $user_id );

			if ( is_wp_error( $resultado ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $resultado;
			}

			$wpdb->query( 'COMMIT' );

			return $resultado;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'checkin_error', __( 'Error al procesar el check-in.', 'convoca-enroll' ) );
		}
	}

	/**
	 * Process a direct check-in (token from the staff's link).
	 *
	 * Solo llega aquí personal: `handle_checkin_page()` corta al resto antes.
	 *
	 * @param string $token Token del QR.
	 */
	private function process_direct_checkin( string $token ): void {
		$result = $this->mark_as_attended_by_token( $token );

		if ( is_wp_error( $result ) ) {
			// El estado se calcula aparte: el comprobador de WordPress.org marca `$this`
			// en `wp_die` como salida sin escapar, aunque el valor sea un entero (el
			// código HTTP) y no haya nada que escapar.
			$estado = $this->status_para_error( $result );

			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Error de Check-in', 'convoca-enroll' ),
				array( 'response' => absint( $estado ) )
			);
		}

		$id        = (int) $result['inscripcion_id'];
		$nombre    = get_post_meta( $id, '_convoca_nombre', true );
		$act_id    = (int) get_post_meta( $id, '_convoca_actividad_id', true );
		$act_title = get_the_title( $act_id );

		if ( $result['ya_marcada'] ) {
			wp_die(
				sprintf(
					/* translators: %1$s: participant name, %2$s: activity title, %3$s: first check-in time */
					esc_html__( 'La asistencia de %1$s en "%2$s" ya estaba registrada a las %3$s.', 'convoca-enroll' ),
					'<strong>' . esc_html( $nombre ) . '</strong>',
					esc_html( $act_title ),
					esc_html( Checkin::hora_legible( (string) $result['hora'] ) )
				),
				esc_html__( 'Check-in ya registrado', 'convoca-enroll' ),
				array(
					'response'  => 200,
					'back_link' => true,
				)
			);
		}

		wp_die(
			sprintf(
				/* translators: %1$s: participant name, %2$s: activity title */
				esc_html__( 'Check-in confirmado para %1$s en "%2$s".', 'convoca-enroll' ),
				'<strong>' . esc_html( $nombre ) . '</strong>',
				esc_html( $act_title )
			),
			esc_html__( 'Check-in Exitoso', 'convoca-enroll' ),
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
		if ( ! $this->es_personal() ) {
			wp_send_json_error( __( 'No tienes permisos para realizar check-in.', 'convoca-enroll' ), 403 );
		}

		$token          = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$inscripcion_id = Checkin_Link::id_for_token( $token );

		if ( $inscripcion_id <= 0 ) {
			\Convoca\Core\Logger::info( 'Intento de check-in fallido (AJAX): token no válido', 'Enroll/Checkin', $user_id );
			wp_send_json_error( __( 'Token de check-in no válido.', 'convoca-enroll' ), 404 );
		}

		$act_id = (int) get_post_meta( $inscripcion_id, '_convoca_actividad_id', true );

		// El permiso sobre la actividad se comprueba ANTES de marcar: antes se marcaba y luego se
		// respondía «sin permisos», así que el intento quedaba registrado igual.
		if ( ! CPT_Actividad::is_user_responsible( $user_id, $act_id ) ) {
			\Convoca\Core\Logger::info( "Intento de check-in fallido (AJAX): Sin permisos para actividad #$act_id", 'Enroll/Checkin', $user_id, array( 'inscripcion_id' => $inscripcion_id ) );
			wp_send_json_error( __( 'No tienes permiso para gestionar esta actividad.', 'convoca-enroll' ), 403 );
		}

		$result = $this->mark_as_attended_by_token( $token, $user_id );

		if ( is_wp_error( $result ) ) {
			\Convoca\Core\Logger::info( 'Intento de check-in fallido (AJAX): ' . $result->get_error_message(), 'Enroll/Checkin', $user_id, array( 'inscripcion_id' => $inscripcion_id ) );
			wp_send_json_error( $result->get_error_message(), $this->status_para_error( $result ) );
		}

		$nombre  = get_post_meta( $inscripcion_id, '_convoca_nombre', true );
		$ya      = (bool) $result['ya_marcada'];
		$mensaje = $ya
			? sprintf(
				/* translators: %s: hora del primer check-in */
				__( 'Esta reserva ya se había escaneado a las %s', 'convoca-enroll' ),
				Checkin::hora_legible( (string) $result['hora'] )
			)
			: __( 'Asistencia confirmada', 'convoca-enroll' );

		\Convoca\Core\Logger::info(
			$ya
				? "Check-in repetido (AJAX) por usuario #$user_id; primera marca a las {$result['hora']}"
				: "Check-in exitoso vía QR (AJAX) por usuario #$user_id",
			'Enroll/Checkin',
			$user_id,
			array( 'inscripcion_id' => $inscripcion_id )
		);

		wp_send_json_success(
			array(
				'message'         => $mensaje,
				'already_checked' => $ya,
				'details'         => array(
					'nombre'    => $nombre,
					'actividad' => get_the_title( $act_id ),
				),
			)
		);
	}
}
