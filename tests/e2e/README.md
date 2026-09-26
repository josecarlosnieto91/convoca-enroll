# Tests E2E (Playwright)

Prueban el **módulo de medios** (carteles, plantillas, social) por su interfaz real: la API REST.
No hay pantalla que pulsar —la UI del metabox (`assets/js/media-admin.js`, `convoca-editor.js`) no
está enganchada a ningún PHP: nadie la encola y su acción `convoca_render_poster` no tiene handler—,
así que el recorrido se hace con llamadas a la API.

## Cómo se ejecuta

```bash
npm test              # prepara el entorno (pretest) y lanza la suite
npm run test:media    # solo el módulo de medios
npm run test:visual   # solo la regresión visual
npm run snapshots:update   # rehace la línea base tras un cambio de diseño deliberado
```

Necesita el contenedor de desarrollo (`convoca-dev-wp-1`) en `http://localhost:8080`: `npm test`
siembra el fixture con `npm run test:seed`, que se ejecuta **dentro** del contenedor.

## Qué hay aquí

| Fichero | Para qué |
|---|---|
| `seed.php` | Prepara el entorno: una actividad de fixture y una contraseña de aplicación. Idempotente. Escribe `.fixture.json` (ignorado por git). |
| `helpers.js` | Lee el fixture y arma la cabecera de autenticación. |
| `media-api.spec.js` | Plantillas reales, generación del cartel, PNG servido, caché y regenerado. |
| `user-journey-media.spec.js` | El recorrido completo: cerrado sin identificarse, abierto con credenciales. |
| `visual-regression.spec.js` | Los píxeles del cartel contra la línea base de `tests/snapshots/posters.json`. |
| `user-journey-social.spec.js` | Comprobaciones públicas de la API social. |

## Dos cosas que cuestan media hora si no se saben

1. **La API se autentica con contraseña de aplicación, no con la cookie de sesión.** Una petición
   con cookie y sin nonce la rechaza WordPress con un 401 (`rest_cookie_invalid_nonce`) aunque
   mandes la contraseña. Por eso los specs de API llevan
   `test.use({ storageState: { cookies: [], origins: [] } })`.
2. **El contenedor necesita `AllowOverride All`** (en `convoca-dev/apache-allowoverride.conf`) y
   `.htaccess` en el webroot, o `/wp-json/...` responde 404 y todo esto falla por el servidor web,
   no por el código. Las reglas del `.htaccess` solo las escribe WordPress si se vacían **desde la
   web** (desde línea de comandos no ve `SERVER_SOFTWARE` y no sabe que es Apache).
