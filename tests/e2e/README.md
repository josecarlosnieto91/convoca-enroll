# Tests E2E (Playwright)

Prueban el **módulo de medios** (carteles, plantillas, social) por su interfaz real: la API REST.
No hay pantalla que pulsar: aquella UI de metabox/editor (`assets/js/media-admin.js` y
`convoca-editor.js`) nunca llegó a tener PHP detrás —nadie la encolaba, `convoca_render_poster` no
tenía handler y la página `convoca-media-editor` no estaba registrada— y se retiró como código muerto
en la **2.7.19** (hay copia y explicación en el CHANGELOG). El recorrido se hace con llamadas a la API.

## Cómo se ejecuta

```bash
npm test              # prepara el entorno (pretest) y lanza la suite
npm run test:media    # solo el módulo de medios
npm run test:visual   # solo la regresión visual
npm run snapshots:update   # rehace la línea base tras un cambio de diseño deliberado
```

Necesita un WordPress vivo con Convoca Enroll activo. Por defecto apunta a la **demo**:

```bash
npm run test:seed     # siembra el fixture en el sitio (por ssh + wp-cli)
npm test              # pretest = lo anterior, y luego la suite
```

El sembrador (`seed-remoto.sh` → `seed.php`) se lanza con `wp eval-file` en el sitio y trae el fixture de
vuelta. **Ya no hace falta el contenedor de desarrollo** (`convoca-dev-wp-1`, retirado en julio de 2026),
que era lo que impedía ejecutar la suite en cualquier otro sitio (enroll#24). Para apuntar a otro sitio:

```bash
E2E_SSH=andromeda E2E_WP_PATH=/var/www/demo.getconvoca.app/public E2E_WP_USER=http \
E2E_BASE_URL=https://demo.getconvoca.app npm test
```

Y para el navegador, si algún spec necesita sesión: `E2E_AUTH=auth.json` (por defecto también se lee
`auth.json` si existe; se ignora en git).

**No se apunta a producción**: la suite **escribe** (crea carteles y toca su actividad de fixture).

## Qué hay aquí

| Fichero | Para qué |
|---|---|
| `seed.php` | Prepara el entorno: una actividad de fixture y una contraseña de aplicación. Idempotente. Escribe `.fixture.json` (ignorado por git). Elige al administrador **por capacidad** (`manage_options`), no por nombre: el login «admin» no existe en todas partes. |
| `seed-remoto.sh` | Lo lanza en el sitio de verdad, por ssh + `wp eval-file`, y trae el fixture de vuelta. |
| `helpers.js` | Lee el fixture y arma la cabecera de autenticación. |
| `media-api.spec.js` | Plantillas reales, generación del cartel, PNG servido, caché y regenerado. |
| `user-journey-media.spec.js` | El recorrido completo: cerrado sin identificarse, abierto con credenciales. |
| `visual-regression.spec.js` | Los píxeles del cartel contra la línea base de `tests/snapshots/posters.json`. |
| `user-journey-social.spec.js` | El módulo social por su API: permiso exigido, cuentas conectadas (ninguna inventada), OAuth que redirige sin romper y callback sin código. |

## Por qué el social se prueba así

El módulo social **no está huérfano** y por eso no se retira: expone seis rutas bajo
`convoca/v1/social/*`, tiene un healthcheck semanal del token (`convoca_social_token_healthcheck`)
y publica por evento de cron (`convoca_social_publish` → `Social_Scheduler::process()`). Lo que no
tiene es pantalla, como el módulo de medios, así que se prueba por la API.

Lo que **no** se prueba es la conexión real con Meta o Google: no hay credenciales en el entorno de
desarrollo y no se le piden a nadie. Se comprueba la única parte que depende de este código, que es
la respuesta que da al arrancar el OAuth y al recibir un callback sin código — en los dos casos una
redirección, nunca un 500. Por eso esas dos pruebas van con `maxRedirects: 0`: seguir la
redirección llevaría a Meta y la prueba dejaría de medir lo nuestro.

## Dos cosas que cuestan media hora si no se saben

1. **La API se autentica con contraseña de aplicación, no con la cookie de sesión.** Una petición
   con cookie y sin nonce la rechaza WordPress con un 401 (`rest_cookie_invalid_nonce`) aunque
   mandes la contraseña. Por eso los specs de API llevan
   `test.use({ storageState: { cookies: [], origins: [] } })`.
2. **El contenedor necesita `AllowOverride All`** (en `convoca-dev/apache-allowoverride.conf`) y
   `.htaccess` en el webroot, o `/wp-json/...` responde 404 y todo esto falla por el servidor web,
   no por el código. Las reglas del `.htaccess` solo las escribe WordPress si se vacían **desde la
   web** (desde línea de comandos no ve `SERVER_SOFTWARE` y no sabe que es Apache).

## Y dos cosas que se descubrieron al volver a ejecutarla (enroll#24)

1. **El destino salía del entorno equivocado.** El sembrador escribe la URL en el fixture, pero el
   `playwright.config.js` solo miraba `E2E_BASE_URL` y, sin ella, caía en `http://localhost:8080` (el
   contenedor retirado): 11 pruebas en rojo midiendo un sitio que no existe. Ahora el config lee la URL
   **del propio fixture**.
2. **La línea base visual es del entorno, no solo del código.** Se rehízo contra la demo. Si se ejecuta
   en otro host con otras fuentes, hay que rehacerla allí y **mirar el cartel** antes de darla por buena.

La suite **no** se ejecuta en el CI a propósito: necesita un WordPress vivo al que escribir.
