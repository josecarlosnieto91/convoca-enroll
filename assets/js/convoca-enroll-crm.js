/**
 * Biodevas Enroll — CRM Monitor JS
 * Vanilla JS refactor
 */
(function (bdvAdmin) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Copy enrollment link
        document.querySelectorAll('.copy-link').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var link = this.dataset.link;
                if (!link) return;

                var originalHTML = this.innerHTML;
                navigator.clipboard.writeText(link).then(() => {
                    this.innerHTML = '<span class="dashicons dashicons-yes"></span> Copiado';
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 2000);
                });
            });
        });

        // Toggle attendance AJAX (Unified .attendance-btn and .mark-attendance)
        document.querySelectorAll('.attendance-btn, .mark-attendance').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var currentBtn = this;
                var idElement = currentBtn.closest('[data-id]');
                if (!idElement) return;

                var id = idElement.dataset.id;
                var status = currentBtn.dataset.status;

                if (!currentBtn.classList.contains('active')) {
                    currentBtn.disabled = true;
                    currentBtn.style.opacity = '0.5';

                    var fd = new FormData();
                    fd.append('id', id); // For mark_attendance action
                    fd.append('inscripcion_id', id); // For update_attendance action
                    fd.append('status', status);
                    
                    var nonce = window.bdeCrm ? window.bdeCrm.nonce : '';
                    var action = currentBtn.classList.contains('mark-attendance') ? 'bde_mark_attendance' : 'bde_crm_update_attendance';

                    bdvAdmin.ajaxPost(action, fd, nonce,
                        (res) => {
                            currentBtn.disabled = false;
                            currentBtn.style.opacity = '1';

                            // UI feedback
                            var parent = currentBtn.closest('.attendance-control') || currentBtn.parentElement;
                            parent.querySelectorAll('.attendance-btn, .mark-attendance').forEach(b => b.classList.remove('active'));
                            currentBtn.classList.add('active');
                            
                            var row = currentBtn.closest('.checkin-row');
                            if (row) {
                                row.classList.remove('checked-in', 'absent');
                                row.classList.add('updated');
                                if (status === '1') row.classList.add('checked-in');
                                else if (status === '0') row.classList.add('absent');
                                setTimeout(() => row.classList.remove('updated'), 500);
                            }
                        },
                        (res) => {
                            currentBtn.disabled = false;
                            currentBtn.style.opacity = '1';
                            alert(res.data?.message || res.data || 'Error al actualizar la asistencia');
                        }
                    );
                }
            });
        });
    });

})(window.convocaAdmin || window.convoca || null);
