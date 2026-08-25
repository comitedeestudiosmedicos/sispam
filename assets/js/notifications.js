/**
 * Sistema de Notificaciones en Tiempo Real para Orientadores
 */

document.addEventListener('DOMContentLoaded', () => {
    // Polling cada 6 segundos para consultar nuevas notificaciones
    setInterval(checkNotificaciones, 6000);
    checkNotificaciones();
});

function checkNotificaciones() {
    fetch('api/notificaciones.php')
        .then(response => response.json())
        .then(data => {
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(notif => {
                    mostrarToastNotificacion(notif);
                });
            }
        })
        .catch(err => console.error("Error al consultar notificaciones:", err));
}

function mostrarToastNotificacion(notif) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast-' + notif.id;
    if (document.getElementById(toastId)) return; // Evitar duplicados

    const toastHtml = `
        <div id="${toastId}" class="toast bg-warning text-dark border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-dark text-white">
                <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>
                <strong class="me-auto">Alerta de Orden</strong>
                <small>Ahora</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" onclick="marcarLeido(${notif.id})"></button>
            </div>
            <div class="toast-body font-weight-bold">
                ${escapeHtml(notif.mensaje)}
                <div class="mt-2 pt-2 border-top border-secondary">
                    <button class="btn btn-sm btn-dark w-100" onclick="marcarLeido(${notif.id}); bootstrap.Toast.getInstance(document.getElementById('${toastId}')).hide();">
                        Entendido / Cerrar Alerta
                    </button>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastElem = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElem, { autohide: false });
    toast.show();

    // Sonido de alerta discreto usando Web Audio API
    playAlertSound();
}

function marcarLeido(id) {
    const formData = new FormData();
    formData.append('action', 'marcar_leido');
    formData.append('id', id);
    fetch('api/notificaciones.php', {
        method: 'POST',
        body: formData
    });
}

function playAlertSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880; // Tono A5
        gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.3);
    } catch (e) {}
}

function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}
