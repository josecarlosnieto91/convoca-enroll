/**
 * Biodevas Enroll — Public JS
 * Form validation, socio toggle, AJAX submission using convoca namespaces.
 */
(function (bdv) {
    'use strict';

    // Hook forms dynamically and standard static ones
    bdv.observeDynamicForms('.bde-enroll-wrapper', function (form) {
        const wrapper = form.closest('.bde-enroll-wrapper');
        const alert = bdv.$('#bde-alert', wrapper);
        const success = bdv.$('#bde-success', wrapper);
        
        const socioSelect = bdv.$('#bde-es-socio', wrapper);
        const tipoInscripcion = bdv.$('#bde-tipo-inscripcion', wrapper);
        const authBanner = bdv.$('#bde-auth-banner', wrapper);

        /* RGPD-compliant Auth Load */
        const apiRoot = window.bdeEnroll?.apiRoot || '/wp-json/';
        fetch(apiRoot + 'convoca-enroll/v1/me/session-status')
            .then(r => r.json())
            .then(data => {
                if (!data.authenticated) {
                    if (socioSelect) socioSelect.value = '0';
                    if (tipoInscripcion) tipoInscripcion.value = 'socio_dia';
                    
                    const precioS = bdv.$('#bde-precio-socio', wrapper);
                    const precioSD = bdv.$('#bde-precio-socio-dia', wrapper);
                    if (precioS) precioS.classList.remove('bde-price--active');
                    if (precioSD) precioSD.classList.add('bde-price--active');

                    if (authBanner) {
                        authBanner.innerHTML = `Identifícate <strong><a href="/acceder/" style="text-decoration:underline;">como socio/a</a></strong> para autocompletar tus datos y aplicar tu aportación reducida. (De lo contrario, se aplica aportación Trasgu).`;
                        authBanner.style.display = 'block';
                    }
                    return;
                }

                // If authenticated, fetch profile data from Members API (Task 3: Security)
                fetch(apiRoot + 'convoca/v1/me')
                    .then(r => r.json())
                    .then(member => {
                        if (member.code === 'rest_forbidden' || !member.name) return;

                        if (socioSelect) socioSelect.value = '1';
                        if (tipoInscripcion) tipoInscripcion.value = 'socio';
                        
                        const precioS = bdv.$('#bde-precio-socio', wrapper);
                        const precioSD = bdv.$('#bde-precio-socio-dia', wrapper);
                        if (precioS) precioS.classList.add('bde-price--active');
                        if (precioSD) precioSD.classList.remove('bde-price--active');

                        if (authBanner) {
                            authBanner.innerHTML = `<strong>Autenticado como socio/a:</strong> ${member.name}. Tienes aplicada la aportación de socio. Opcionalmente puedes <a href="/acceder/?action=logout" style="text-decoration:underline;">cerrar sesión</a>.`;
                            authBanner.style.display = 'block';
                        }

                        // Pre-fill form (visual only, real validation is in backend)
                        const n = bdv.$('#bde-nombre', form);
                        const e = bdv.$('#bde-email', form);
                        const d = bdv.$('#bde-dni', form);
                        const t = bdv.$('#bde-telefono', form);
                        if (n) n.value = member.name || '';
                        if (e) e.value = member.email || '';
                        if (d) {
                            d.value = 'VINCULADO_A_SESION';
                            d.readOnly = true;
                            d.style.opacity = '0.6';
                        }
                        if (t) t.value = member.phone || '';
                    });
            })
            .catch(err => console.error('Error verificando sesión de socio:', err));

        /* Minor handling: Toggle fields visibility and required state */
        const minorToggle = bdv.$('#bde-es-menor', wrapper);
        const minorFields = bdv.$('#bde-minor-fields', wrapper);
        const inputNombreParticipante = bdv.$('#bde-nombre-participante', wrapper);
        const inputEdadParticipante = bdv.$('#bde-edad-participante', wrapper);

        if (minorToggle && minorFields) {
            const updateMinorFields = () => {
                const isVisible = minorToggle.checked;
                minorFields.style.display = isVisible ? 'block' : 'none';
                
                // Toggle required attribute for validation
                if (inputNombreParticipante) inputNombreParticipante.required = isVisible;
                if (inputEdadParticipante) inputEdadParticipante.required = isVisible;
            };
            
            minorToggle.addEventListener('change', updateMinorFields);
            // Run once on init in case it's checked by some persistent browser state
            updateMinorFields();
        }

        /* Submit hook */
        form.addEventListener('submit', e => {
            e.preventDefault();

            // Native shared validation
            const ok = bdv.form.validate(form);
            
            const consentimiento = bdv.$('#bde-consentimiento', form) || bdv.$('#bde-consentimiento', wrapper);
            if (consentimiento && !consentimiento.checked) {
                bdv.showAlert(alert, 'Debes aceptar la política de privacidad.', 'danger');
                return;
            }

            if (!ok) {
                bdv.showAlert(alert, 'Rellena todos los campos obligatorios.', 'danger');
                return;
            }

            bdv.hideAlert(alert);

            const btn = form.querySelector('[type="submit"]');
            bdv.setLoading(btn, true, '✔ Inscribirme');

            const fd = new FormData(form);
            const nonce = window.bdeEnroll?.nonce || '';

            bdv.ajaxPost('conv_enroll_inscribir', fd, nonce, 
                /* On Success */
                (res) => {
                    // Redirect directly without showing success pane if gateway rules
                    if (res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                        return;
                    }

                    form.style.display = 'none';
                    success.style.display = 'block';

                    const icon = bdv.$('#bde-success-icon', wrapper);
                    const title = bdv.$('#bde-success-title', wrapper);
                    const msg = bdv.$('#bde-success-msg', wrapper);

                    if (res.data.gateway_error) {
                        if (icon) icon.textContent = '⚠️';
                        if (title) title.textContent = 'Inscripción recibida (Aportación pendiente)';
                        if (msg) msg.innerHTML = `<strong>Aviso:</strong> ${res.data.error_message}. Tu plaza se confirmará cuando recibamos la aportación.`;
                    } else if (res.data.estado === 'lista_espera') {
                        if (icon) icon.textContent = '📋';
                        if (title) title.textContent = 'Estás en la lista de espera';
                        if (msg) msg.textContent = 'Te avisaremos por email si se libera una plaza.';
                    } else if (res.data.estado === 'pendiente_pago') {
                        if (icon) icon.textContent = '💳';
                        if (title) title.textContent = 'Inscripción pendiente de aportación';
                        if (msg) msg.textContent = 'Completa la aportación para confirmar tu plaza.';
                    } else {
                        if (icon) icon.textContent = '🎉';
                        if (title) title.textContent = '¡Inscripción confirmada!';
                        if (msg) msg.textContent = 'Hemos enviado un email de confirmación a tu correo.';
                    }

                    // Update plazas badge if present.
                    const badge = bdv.$('#bde-plazas-badge', wrapper);
                    if (badge && typeof res.data.plazas === 'number') {
                        const totalMatch = badge.textContent.match(/\/(\d+)/);
                        const t = totalMatch ? totalMatch[1] : '?';
                        if (res.data.plazas <= 0) {
                            badge.textContent = '🎟 Plazas agotadas';
                            badge.className = 'bde-plazas--agotada';
                        } else {
                            badge.textContent = '🎟 ' + res.data.plazas + '/' + t + ' plazas';
                        }
                    }
                },
                /* On Error */
                (res) => {
                    const msgs = res.data?.errors ? res.data.errors.join('<br>') : (res.data?.message || 'Error desconocido.');
                    bdv.showAlert(alert, msgs, 'danger');
                    bdv.setLoading(btn, false, '✔ Inscribirme');
                }
            );
        });
    });

})(window.convoca || {});
