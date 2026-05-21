/**
 * Biodevas Enroll — Admin JS
 * State change, check-in toggles, and email resends using convocaAdmin.
 */
(function (bdvAdmin) {
    'use strict';

    if (!bdvAdmin) return;

    // Fast helpers
    const $ = bdvAdmin.$;
    const $$ = bdvAdmin.$$;

    // Handle Quick State Change Form
    const stateForm = document.getElementById('bde-state-form');
    if (stateForm) {
        stateForm.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const submitBtn = stateForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Guardando...';
            }

            const fd = new FormData(stateForm);
            const nonce = window.bdeAdmin?.nonce || '';

            bdvAdmin.ajaxPost('bde_change_state', fd, nonce,
                (res) => { location.reload(); },
                (res) => {
                    alert(res.data?.message || res.data || 'Error al cambiar el estado.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Guardar cambios';
                    }
                }
            );
        });
    }

    // Check-in toggle
    $$('.bde-toggle-checkin').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const id = this.dataset.id;
            
            this.disabled = true;
            this.style.opacity = '0.5';

            const nonce = window.bdeAdmin?.nonce || '';
            const payload = { inscripcion_id: id };

            bdvAdmin.ajaxPost('bde_toggle_checkin', payload, nonce,
                (res) => {
                    this.disabled = false;
                    this.style.opacity = '1';
                    this.textContent = res.data.label;
                    if (res.data.asistencia === '1') {
                        this.classList.remove('button-secondary');
                        this.classList.add('button-primary');
                    } else {
                        this.classList.remove('button-primary');
                        this.classList.add('button-secondary');
                    }
                },
                (res) => {
                    this.disabled = false;
                    this.style.opacity = '1';
                    alert(res.data?.message || res.data || 'Error al cambiar la asistencia.');
                }
            );
        });
    });

    // Resend email
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.bde-resend-email') || e.target.closest('#bde-resend-email');
        if (!btn) return;

        e.preventDefault();
        
        if (!confirm('¿Seguro que quieres reenviar el email de confirmación?')) {
            return;
        }

        const id = btn.dataset.id;
        btn.disabled = true;
        btn.style.opacity = '0.5';
        
        const spinner = document.querySelector('.spinner');
        if (spinner) spinner.classList.add('is-active');

        const nonce = window.bdeAdmin?.nonce || '';
        const payload = { inscripcion_id: id };

        bdvAdmin.ajaxPost('bde_resend_email', payload, nonce,
            (res) => {
                btn.disabled = false;
                btn.style.opacity = '1';
                if (spinner) spinner.classList.remove('is-active');
                alert(res.data || 'Email enviado correctamente.');
            },
            (res) => {
                btn.disabled = false;
                btn.style.opacity = '1';
                if (spinner) spinner.classList.remove('is-active');
                alert(res.data?.message || res.data || 'Error al reenviar el email.');
            }
        );
    });

})(window.convocaAdmin);
