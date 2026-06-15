/**
 * Convoca Enroll — Public JS
 * Form validation, socio toggle, AJAX submission using convoca namespaces.
 */
(function (conv) {
    'use strict';

    // Hook forms dynamically and standard static ones
    conv.observeDynamicForms('.conv-enroll-wrapper', function (form) {
        const wrapper = form.closest('.conv-enroll-wrapper');
        const alert = conv.$('#conv-alert', wrapper);
        const success = conv.$('#conv-success', wrapper);
        
        const socioSelect = conv.$('#conv-es-socio', wrapper);
        const tipoInscripcion = conv.$('#conv-tipo-inscripcion', wrapper);
        const authBanner = conv.$('#conv-auth-banner', wrapper);

        /* RGPD-compliant Auth Load */
        const apiRoot = window.bdeEnroll?.apiRoot || '/wp-json/';
        fetch(apiRoot + 'convoca-enroll/v1/me/session-status')
            .then(r => r.json())
            .then(data => {
                if (!data.authenticated) {
                    if (socioSelect) socioSelect.value = '0';
                    if (tipoInscripcion) tipoInscripcion.value = 'socio_dia';
                    
                    const precioS = conv.$('#conv-precio-socio', wrapper);
                    const precioSD = conv.$('#conv-precio-socio-dia', wrapper);
                    if (precioS) precioS.classList.remove('conv-price--active');
                    if (precioSD) precioSD.classList.add('conv-price--active');

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
                        
                        const precioS = conv.$('#conv-precio-socio', wrapper);
                        const precioSD = conv.$('#conv-precio-socio-dia', wrapper);
                        if (precioS) precioS.classList.add('conv-price--active');
                        if (precioSD) precioSD.classList.remove('conv-price--active');

                        if (authBanner) {
                            authBanner.innerHTML = `<strong>Autenticado como socio/a:</strong> ${member.name}. Tienes aplicada la aportación de socio. Opcionalmente puedes <a href="/acceder/?action=logout" style="text-decoration:underline;">cerrar sesión</a>.`;
                            authBanner.style.display = 'block';
                        }

                        // Pre-fill form (visual only, real validation is in backend)
                        const n = conv.$('#conv-nombre', form);
                        const e = conv.$('#conv-email', form);
                        const d = conv.$('#conv-dni', form);
                        const t = conv.$('#conv-telefono', form);
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
        const minorToggle = conv.$('#conv-es-menor', wrapper);
        const minorFields = conv.$('#conv-minor-fields', wrapper);
        const inputNombreParticipante = conv.$('#conv-nombre-participante', wrapper);
        const inputEdadParticipante = conv.$('#conv-edad-participante', wrapper);

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
            const ok = conv.form.validate(form);
            
            const consentimiento = conv.$('#conv-consentimiento', form) || conv.$('#conv-consentimiento', wrapper);
            if (consentimiento && !consentimiento.checked) {
                conv.showAlert(alert, 'Debes aceptar la política de privacidad.', 'danger');
                return;
            }

            if (!ok) {
                conv.showAlert(alert, 'Rellena todos los campos obligatorios.', 'danger');
                return;
            }

            conv.hideAlert(alert);

            const btn = form.querySelector('[type="submit"]');
            conv.setLoading(btn, true, '✔ Inscribirme');

            const fd = new FormData(form);
            const nonce = window.bdeEnroll?.nonce || '';

            conv.ajaxPost('conv_enroll_inscribir', fd, nonce, 
                /* On Success */
                (res) => {
                    // Redirect directly without showing success pane if gateway rules
                    if (res.data && res.data.redirect) {
                        window.location.href = res.data.redirect;
                        return;
                    }

                    form.style.display = 'none';
                    success.style.display = 'block';

                    const icon = conv.$('#conv-success-icon', wrapper);
                    const title = conv.$('#conv-success-title', wrapper);
                    const msg = conv.$('#conv-success-msg', wrapper);

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
                    const badge = conv.$('#conv-plazas-badge', wrapper);
                    if (badge && typeof res.data.plazas === 'number') {
                        const totalMatch = badge.textContent.match(/\/(\d+)/);
                        const t = totalMatch ? totalMatch[1] : '?';
                        if (res.data.plazas <= 0) {
                            badge.textContent = '🎟 Plazas agotadas';
                            badge.className = 'conv-plazas--agotada';
                        } else {
                            badge.textContent = '🎟 ' + res.data.plazas + '/' + t + ' plazas';
                        }
                    }
                },
                /* On Error */
                (res) => {
                    const msgs = res.data?.errors ? res.data.errors.join('<br>') : (res.data?.message || 'Error desconocido.');
                    conv.showAlert(alert, msgs, 'danger');
                    conv.setLoading(btn, false, '✔ Inscribirme');
                }
            );
        });
    });

})(window.convoca || {});
