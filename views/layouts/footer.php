</main>

<footer class="footer mt-auto py-3 bg-white border-top text-center text-muted small no-print">
    <div class="container">
        <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(APP_NAME) ?></strong>. Todos los derechos reservados.</span>
    </div>
</footer>

<!-- Modal Moderno de Notificaciones y Alertas SISPAM -->
<div class="modal fade" id="modalSispamNotificacion" tabindex="-1" aria-hidden="true" style="z-index: 10999 !important;" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content sispam-modal-card text-center position-relative shadow-lg border-0">
            <!-- Barra superior decorativa con gradiente -->
            <div id="modalSispamNotifBar" style="height: 6px; width: 100%;" class="bg-primary"></div>
            
            <div class="modal-body p-4 pt-4">
                <!-- Icono animado -->
                <div class="d-flex justify-content-center mb-3">
                    <div id="modalSispamNotifIconContainer" class="sispam-icon-bubble icon-info">
                        <i id="modalSispamNotifIcon" class="fa-solid fa-circle-info fs-1"></i>
                    </div>
                </div>

                <!-- Título -->
                <h5 class="modal-title fw-bold text-dark mb-2" id="modalSispamNotifTitle">Notificación del Sistema</h5>

                <!-- Mensaje / Contenido -->
                <div class="text-secondary mb-4 fs-6" id="modalSispamNotifBody" style="line-height: 1.5; font-size: 0.95rem;"></div>

                <!-- Acciones -->
                <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap" id="modalSispamNotifActions">
                    <button type="button" class="btn sispam-modal-btn-confirm btn-primary" id="btnModalSispamNotifConfirm" data-bs-dismiss="modal">
                        <i class="fa-solid fa-check me-1"></i> Entendido
                    </button>
                    <button type="button" class="btn sispam-modal-btn-cancel d-none" id="btnModalSispamNotifCancel" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 JS Bundle con Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ==========================================================================
// SISTEMA UNIVERSAL DE MODALES MODERNOS SISPAM (Reemplazo de alertas nativas)
// ==========================================================================
let modalSispamCallback = null;
let modalSispamCancelCallback = null;

window.modalAlert = function(mensaje, tipo = 'info', titulo = null, callback = null) {
    const modalEl = document.getElementById('modalSispamNotificacion');
    if (!modalEl) {
        if (typeof window.nativeAlert === 'function') window.nativeAlert(mensaje);
        else window.alert(mensaje);
        if (callback) callback();
        return;
    }

    modalSispamCallback = callback;
    modalSispamCancelCallback = null;

    const iconCont = document.getElementById('modalSispamNotifIconContainer');
    const icon = document.getElementById('modalSispamNotifIcon');
    const titleEl = document.getElementById('modalSispamNotifTitle');
    const bodyEl = document.getElementById('modalSispamNotifBody');
    const barEl = document.getElementById('modalSispamNotifBar');
    const btnConfirm = document.getElementById('btnModalSispamNotifConfirm');
    const btnCancel = document.getElementById('btnModalSispamNotifCancel');

    btnCancel.classList.add('d-none');
    btnConfirm.className = 'btn sispam-modal-btn-confirm';

    tipo = (tipo || 'info').toLowerCase();
    if (tipo === 'success' || tipo === 'ok' || tipo === 'exito') {
        barEl.className = 'bg-success';
        iconCont.className = 'sispam-icon-bubble icon-success';
        icon.className = 'fa-solid fa-circle-check fs-1';
        titleEl.textContent = titulo || '¡Operación Exitosa!';
        btnConfirm.classList.add('btn-success');
        btnConfirm.innerHTML = '<i class="fa-solid fa-check me-1"></i> Aceptar';
    } else if (tipo === 'error' || tipo === 'danger') {
        barEl.className = 'bg-danger';
        iconCont.className = 'sispam-icon-bubble icon-error';
        icon.className = 'fa-solid fa-circle-xmark fs-1';
        titleEl.textContent = titulo || 'Error en la Operación';
        btnConfirm.classList.add('btn-danger');
        btnConfirm.innerHTML = '<i class="fa-solid fa-xmark me-1"></i> Entendido';
    } else if (tipo === 'warning' || tipo === 'alerta' || tipo === 'warn') {
        barEl.className = 'bg-warning';
        iconCont.className = 'sispam-icon-bubble icon-warning';
        icon.className = 'fa-solid fa-triangle-exclamation fs-1';
        titleEl.textContent = titulo || 'Atención Requerida';
        btnConfirm.classList.add('btn-warning', 'text-dark');
        btnConfirm.innerHTML = '<i class="fa-solid fa-check me-1"></i> Continuar';
    } else {
        barEl.className = 'bg-primary';
        iconCont.className = 'sispam-icon-bubble icon-info';
        icon.className = 'fa-solid fa-circle-info fs-1';
        titleEl.textContent = titulo || 'Notificación del Sistema';
        btnConfirm.classList.add('btn-primary');
        btnConfirm.innerHTML = '<i class="fa-solid fa-check me-1"></i> Aceptar';
    }

    bodyEl.innerHTML = mensaje;

    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });
    modal.show();
};

window.modalConfirm = function(mensaje, onConfirm, onCancel = null, titulo = '¿Confirmar Acción?', btnConfirmText = 'Sí, Confirmar', btnCancelText = 'Cancelar') {
    const modalEl = document.getElementById('modalSispamNotificacion');
    if (!modalEl) {
        if (confirm(mensaje)) {
            if (onConfirm) onConfirm();
        } else {
            if (onCancel) onCancel();
        }
        return;
    }

    modalSispamCallback = onConfirm;
    modalSispamCancelCallback = onCancel;

    const iconCont = document.getElementById('modalSispamNotifIconContainer');
    const icon = document.getElementById('modalSispamNotifIcon');
    const titleEl = document.getElementById('modalSispamNotifTitle');
    const bodyEl = document.getElementById('modalSispamNotifBody');
    const barEl = document.getElementById('modalSispamNotifBar');
    const btnConfirm = document.getElementById('btnModalSispamNotifConfirm');
    const btnCancel = document.getElementById('btnModalSispamNotifCancel');

    barEl.className = 'bg-primary';
    iconCont.className = 'sispam-icon-bubble icon-info';
    icon.className = 'fa-solid fa-circle-question fs-1';
    titleEl.textContent = titulo;
    bodyEl.innerHTML = mensaje;

    btnConfirm.className = 'btn sispam-modal-btn-confirm btn-primary';
    btnConfirm.innerHTML = `<i class="fa-solid fa-check me-1"></i> ${btnConfirmText}`;

    btnCancel.className = 'btn sispam-modal-btn-cancel';
    btnCancel.textContent = btnCancelText;
    btnCancel.classList.remove('d-none');

    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: true });
    modal.show();
};

// Interceptor global de alert() para capturar cualquier mensaje emergente nativo
window.nativeAlert = window.alert;
window.alert = function(msg, callback) {
    let tipo = 'info';
    let str = String(msg || '');
    if (str.includes('✔') || str.includes('éxito') || str.includes('exitosamente') || str.includes('correctamente') || str.includes('EXITOSA')) {
        tipo = 'success';
    } else if (str.includes('⚠') || str.includes('novedad') || str.includes('faltante') || str.includes('diferencia') || str.includes('Atención')) {
        tipo = 'warning';
    } else if (str.includes('Error') || str.includes('error') || str.includes('falló') || str.includes('bloqueado') || str.includes('No se pudo') || str.includes('CRÍTICO')) {
        tipo = 'error';
    }
    window.modalAlert(str, tipo, null, callback);
};

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    dropdownElementList.forEach(function (dropdownToggleEl) {
        new bootstrap.Dropdown(dropdownToggleEl);
    });

    // Manejador de eventos del modal moderno
    const modalEl = document.getElementById('modalSispamNotificacion');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (typeof modalSispamCallback === 'function') {
                const cb = modalSispamCallback;
                modalSispamCallback = null;
                cb();
            }
        });

        const btnCancel = document.getElementById('btnModalSispamNotifCancel');
        if (btnCancel) {
            btnCancel.addEventListener('click', function() {
                modalSispamCallback = null;
                if (typeof modalSispamCancelCallback === 'function') {
                    modalSispamCancelCallback();
                }
            });
        }
    }
});
</script>

<?php if (!empty($mensaje)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.modalAlert(<?= json_encode($mensaje, JSON_UNESCAPED_UNICODE) ?>, 'success', '¡Proceso Completado con Éxito!');
    }, 250);
});
</script>
<?php endif; ?>

<?php if (!empty($error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.modalAlert(<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>, 'error', 'Error en la Operación');
    }, 250);
});
</script>
<?php endif; ?>

<?php if (isset($_SESSION['user_id']) && ($_SESSION['rol_nombre'] ?? '') === 'Orientador'): ?>
<!-- Script de Notificaciones Push/Toast para Orientadores -->
<script src="assets/js/notifications.js"></script>
<?php endif; ?>

</body>
</html>
