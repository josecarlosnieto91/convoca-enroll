/**
 * Convoca Enroll — Panel de Reservas JS
 * Login via email+code, display reservations, cancel with confirmation.
 */
(function (bdv) {
    'use strict';

    const wrapper = conv.$('.bde-panel-wrapper');
    if (!wrapper) return;

    const loginSection = conv.$('#bde-panel-login', wrapper);
    const reservasSection = conv.$('#bde-panel-reservas', wrapper);
    const loginForm = conv.$('#bde-panel-login-form', wrapper);
    const alert = conv.$('#bde-panel-alert', wrapper);
    const modal = conv.$('#bde-panel-modal', wrapper);
    const listContainer = conv.$('#bde-panel-list', wrapper);
    const emptyState = conv.$('#bde-panel-empty', wrapper);
    const userEmailEl = conv.$('#bde-panel-user-email', wrapper);

    let sessionEmail = '';
    let sessionCodigo = '';
    let cancelTargetId = null;

    /* ── Login ─────────────────────────────────── */

    loginForm.addEventListener('submit', e => {
        e.preventDefault();

        const email = conv.$('#bde-panel-email', wrapper).value.trim();
        const codigo = conv.$('#bde-panel-codigo', wrapper).value.trim().toUpperCase();

        if (!email || !codigo) {
            showAlert('Introduce tu email y código de reserva.');
            return;
        }

        conv.hideAlert(alert);
        const btn = loginForm.querySelector('[type="submit"]');
        conv.setLoading(btn, true, '🔍 Consultar reservas');

        const fd = new FormData();
        fd.append('email', email);
        fd.append('codigo', codigo);

        const nonce = window.bdePanel?.nonce || '';

        conv.ajaxPost('conv_enroll_panel_login', fd, nonce,
            (res) => {
                sessionEmail = email;
                sessionCodigo = codigo;
                showReservas(res.data.reservas, email);
                conv.setLoading(btn, false, '🔍 Consultar reservas');
            },
            (res) => {
                showAlert(res.data?.message || 'Error desconocido.');
                conv.setLoading(btn, false, '🔍 Consultar reservas');
            }
        );
    });

    /* ── Show reservations ─────────────────────── */

    function showReservas(reservas, email) {
        loginSection.style.display = 'none';
        reservasSection.style.display = 'block';
        userEmailEl.textContent = email;

        if (!reservas || reservas.length === 0) {
            listContainer.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';
        renderList(reservas);
    }

    function renderList(reservas) {
        listContainer.innerHTML = reservas.map(r => `
            <div class="bde-panel-card convoca-card ${r.estado === 'cancelada' ? 'bde-panel-card--cancelled' : ''}" data-id="${r.id}">
                <div class="bde-panel-card-header">
                    <h4 class="bde-panel-card-title">${escHtml(r.actividad)}</h4>
                    <span class="${escHtml(r.estado_class)}">${escHtml(r.estado_label)}</span>
                </div>
                <div class="bde-panel-card-meta">
                    ${r.es_menor ? `<span class="bde-panel-tag">👶 ${escHtml(r.participante)}</span>` : ''}
                    <span>📅 ${escHtml(r.fecha)}</span>
                    <span>🕐 ${escHtml(r.hora)}</span>
                    <span>📍 ${escHtml(r.ubicacion)}</span>
                </div>
                <div class="bde-panel-card-footer">
                    <span class="bde-panel-code">🔑 ${escHtml(r.codigo)}</span>
                    <span class="bde-small">Inscripción: ${escHtml(r.fecha_inscripcion)}</span>
                    ${r.cancelable ? `<button class="btn btn-danger btn-sm bde-cancel-btn" data-id="${r.id}" data-actividad="${escHtml(r.actividad)}">✖ Cancelar</button>` : ''}
                </div>
            </div>
        `).join('');

        // Bind cancel buttons.
        conv.$$('.bde-cancel-btn', listContainer).forEach(btn => {
            btn.addEventListener('click', () => {
                cancelTargetId = parseInt(btn.dataset.id, 10);
                const msg = `¿Estás seguro de que quieres cancelar tu reserva para "${btn.dataset.actividad}"? Se liberará tu plaza.`;
                conv.$('#bde-modal-msg', modal).textContent = msg;
                modal.style.display = 'flex';
            });
        });
    }

    /* ── Logout ─────────────────────────────────── */

    conv.$('#bde-panel-logout', wrapper).addEventListener('click', () => {
        sessionEmail = '';
        sessionCodigo = '';
        cancelTargetId = null;
        reservasSection.style.display = 'none';
        loginSection.style.display = 'block';
        listContainer.innerHTML = '';
        conv.hideAlert(alert);
        const btn = loginForm.querySelector('[type="submit"]');
        conv.setLoading(btn, false, '🔍 Consultar reservas');
    });

    /* ── Modal ──────────────────────────────────── */

    conv.$('#bde-modal-close', wrapper).addEventListener('click', () => {
        modal.style.display = 'none';
        cancelTargetId = null;
    });

    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.style.display = 'none';
            cancelTargetId = null;
        }
    });

    conv.$('#bde-modal-confirm', wrapper).addEventListener('click', () => {
        if (!cancelTargetId) return;

        const confirmBtn = conv.$('#bde-modal-confirm', wrapper);
        conv.setLoading(confirmBtn, true, 'Sí, cancelar reserva');

        const fd = new FormData();
        fd.append('email', sessionEmail);
        fd.append('codigo', sessionCodigo);
        fd.append('inscripcion_id', cancelTargetId);

        const nonce = window.bdePanel?.nonce || '';

        conv.ajaxPost('conv_enroll_panel_cancelar', fd, nonce,
            (res) => {
                modal.style.display = 'none';
                conv.setLoading(confirmBtn, false, 'Sí, cancelar reserva');
                
                conv.showAlert(alert, res.data.message, 'success');
                renderList(res.data.reservas);
                cancelTargetId = null;
            },
            (res) => {
                modal.style.display = 'none';
                conv.setLoading(confirmBtn, false, 'Sí, cancelar reserva');
                
                showAlert(res.data?.message || 'Error al cancelar.');
                cancelTargetId = null;
            }
        );
    });

    /* ── Helpers ────────────────────────────────── */

    function showAlert(msg) {
        conv.showAlert(alert, msg, 'danger');
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

})(window.convoca || {});
