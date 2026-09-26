<?php
/**
 * Prepara el entorno para los tests E2E: una actividad de pruebas y una contraseña de
 * aplicación para llamar a la API REST autenticada.
 *
 * Se ejecuta DENTRO del contenedor de desarrollo, antes de la suite:
 *
 *     npm run test:seed        (o lo lanza npm test solo, con pretest)
 *
 * Escribe `tests/e2e/.fixture.json` (ignorado por git) con lo que los specs necesitan. Es
 * idempotente: reutiliza la actividad y la contraseña si ya existen, y no toca nada más.
 *
 * @package Convoca\Enroll\Tests
 */

require '/var/www/html/wp-load.php';

$salida = array( 'baseUrl' => 'http://localhost:8080' );

// ── Actividad de pruebas ─────────────────────────────────────────────────────────────
$existente = get_posts(
	array(
		'post_type'   => 'actividad',
		'post_status' => 'any',
		'meta_key'    => '_convoca_e2e_fixture',
		'meta_value'  => '1',
		'numberposts' => 1,
	)
);

if ( $existente ) {
	$actividad = (int) $existente[0]->ID;
} else {
	$actividad = (int) wp_insert_post(
		array(
			'post_type'   => 'actividad',
			'post_title'  => 'E2E Carteles (fixture, no borrar a mano)',
			'post_status' => 'publish',
		)
	);
	update_post_meta( $actividad, '_convoca_e2e_fixture', '1' );
	update_post_meta( $actividad, '_convoca_fecha_inicio', gmdate( 'Y-m-d', strtotime( '+10 days' ) ) . ' 10:00:00' );
	update_post_meta( $actividad, '_convoca_fecha_fin', gmdate( 'Y-m-d', strtotime( '+10 days' ) ) . ' 13:00:00' );
	update_post_meta( $actividad, '_convoca_ubicacion', 'Oviedo' );
	update_post_meta( $actividad, '_convoca_plazas_totales', 20 );
	update_post_meta( $actividad, '_convoca_plazas_disponibles', 20 );
}
$salida['actividadId'] = $actividad;

// ── Contraseña de aplicación (la API REST con cookie exige un nonce que no está
//    disponible fuera del editor; con contraseña de aplicación no hace falta nonce) ────
$usuario = get_user_by( 'login', 'admin' );
if ( ! $usuario ) {
	fwrite( STDERR, "No existe el usuario admin en este WordPress.\n" );
	exit( 1 );
}

$aplicaciones = WP_Application_Passwords::get_user_application_passwords( (int) $usuario->ID );
$clave        = null;

foreach ( $aplicaciones as $app ) {
	if ( 'e2e-playwright' !== $app['name'] ) {
		continue;
	}
	// Existe pero no se puede recuperar la contraseña (WordPress la guarda cifrada): se
	// revoca y se crea otra, que es más limpio que arrastrar una desconocida.
	WP_Application_Passwords::delete_application_password( (int) $usuario->ID, $app['uuid'] );
}

$creada = WP_Application_Passwords::create_new_application_password(
	(int) $usuario->ID,
	array( 'name' => 'e2e-playwright' )
);

if ( is_wp_error( $creada ) ) {
	fwrite( STDERR, 'No se pudo crear la contraseña de aplicación: ' . $creada->get_error_message() . "\n" );
	exit( 1 );
}

list( $clave ) = $creada;

$salida['appUser'] = $usuario->user_login;
$salida['appPass'] = $clave;

// ── Plantillas reales, para que los specs no inventen slugs ──────────────────────────
$plantillas          = \Convoca\Enroll\Media\Template_Manager::get_all();
$salida['templates'] = array_values(
	array_filter(
		array_map(
			static function ( $t ) {
				return is_array( $t ) ? ( $t['slug'] ?? null ) : $t;
			},
			(array) $plantillas
		)
	)
);

$ruta = __DIR__ . '/.fixture.json';
file_put_contents( $ruta, wp_json_encode( $salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

printf(
	"  fixture: actividad #%d · %d plantillas · contraseña de aplicación creada\n  escrito: %s\n",
	$actividad,
	count( $salida['templates'] ),
	$ruta
);
