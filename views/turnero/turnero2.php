<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../models/Empresa.php';

$empresaModel = new Empresa();
$config = $empresaModel->getConfig();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnero 2 - Llamado a Ventanilla - <?= htmlspecialchars($config['razon_social']) ?></title>
    <!-- Favicon SISPAM -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="apple-touch-icon" href="assets/img/logo_sispam.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="turnero-bg">

<!-- Encabezado Turnero TV -->
<div class="container-fluid py-3 px-4 bg-dark bg-opacity-50 border-bottom border-secondary d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <?php if (!empty($config['logo_url']) && file_exists(BASE_DIR . '/' . $config['logo_url'])): ?>
            <img src="<?= $config['logo_url'] ?>" alt="Logo" style="max-height: 60px;">
        <?php else: ?>
            <i class="fa-solid fa-prescription-bottle-medical fs-1 text-success"></i>
        <?php endif; ?>
        <div>
            <h2 class="fw-bold mb-0 text-white"><?= htmlspecialchars($config['razon_social']) ?></h2>
            <div class="text-success fw-semibold fs-4"><i class="fa-solid fa-bullhorn me-2"></i> LLAMADO A VENTANILLA - LISTO PARA RECLAMAR</div>
        </div>
    </div>
    <div class="text-end text-white">
        <div id="reloj-digital" class="display-6 fw-bold">00:00:00</div>
        <div id="fecha-digital" class="small text-success">--</div>
    </div>
</div>

<div class="container-fluid p-4">
    <div class="row g-4">
        <!-- Columna Izquierda: Llamado Principal en Grande -->
        <div class="col-lg-5">
            <div class="turnero-card p-4 text-center h-100 shadow-lg d-flex flex-column justify-content-center">
                <div class="badge bg-success fs-4 px-4 py-2 mb-3">ÚLTIMO LLAMADO</div>
                <div class="text-muted fs-4">PACIENTE</div>
                <div id="ultimo-paciente" class="turnero-ticket-active fs-1 my-2 text-wrap">--</div>
                <div class="text-muted fs-5">TIQUETE: <span id="ultimo-ticket" class="text-info fw-bold">--</span></div>
                
                <div class="text-muted fs-4 mt-3">PASAR A:</div>
                <div id="ultimo-modulo" class="turnero-modulo-active display-3 mb-4">--</div>
                
                <div class="d-flex justify-content-center gap-2 flex-wrap" id="audio-controls-container">
                    <button class="btn btn-warning btn-lg fw-bold text-dark shadow" id="btn-audio" onclick="habilitarAudio()">
                        <i class="fa-solid fa-volume-high me-2"></i> Activar Audio de Llamado
                    </button>

                    <button class="btn btn-outline-light btn-lg fw-bold shadow" id="btn-rellamar" onclick="reLlamarActual()" style="display: none;">
                        <i class="fa-solid fa-rotate me-2"></i> Volver a Llamar Paciente
                    </button>
                </div>
                <div class="small text-muted mt-2" id="audio-status-msg">Haga clic en el botón amarillo para habilitar los llamados por voz en este televisor/equipo.</div>
            </div>
        </div>

        <!-- Columna Derecha: Tabla de Todos los Pacientes Listos -->
        <div class="col-lg-7">
            <div class="turnero-card p-4 h-100 shadow-lg">
                <h3 class="fw-bold text-white mb-3 text-center border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-circle-check text-success me-2"></i> LISTOS PARA LA ENTREGA
                </h3>

                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle fs-3">
                        <thead>
                            <tr class="text-success border-secondary">
                                <th>TIQUETE</th>
                                <th>PACIENTE</th>
                                <th class="text-end">VENTANILLA</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-turnero2">
                            <!-- Inyección vía AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Marquesina Informativa -->
<div class="fixed-bottom marquesina-container shadow-lg bg-success">
    <div class="marquesina-text" id="marquesina-content">
        <?= htmlspecialchars($config['marquesina_turnero']) ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/turnero_speech.js?v=<?= time() ?>"></script>
<script>
let lastCalledTicketId = null;
let currentTicketObj = null;
let audioEnabled = false;

document.addEventListener('DOMContentLoaded', () => {
    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    actualizarTurnero2();
    setInterval(actualizarTurnero2, 3000);
});

function habilitarAudio() {
    audioEnabled = true;
    const btnAudio = document.getElementById('btn-audio');
    const btnRellamar = document.getElementById('btn-rellamar');
    const statusMsg = document.getElementById('audio-status-msg');

    btnAudio.className = 'btn btn-success btn-lg fw-bold shadow';
    btnAudio.innerHTML = '<i class="fa-solid fa-volume-high me-2"></i> Audio Activado';
    btnRellamar.style.display = 'inline-block';
    statusMsg.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Audio habilitado. Los nuevos llamados sonarán por Nombre del Paciente.</span>';

    // Llamar por nombre inmediatamente al paciente que esté en pantalla
    if (currentTicketObj) {
        lastCalledTicketId = currentTicketObj.id;
        window.turneroSpeech.speak(currentTicketObj.nombre_completo, currentTicketObj.modulo_entrega_asignado);
    } else {
        window.turneroSpeech.playChime();
    }
}

function reLlamarActual() {
    if (currentTicketObj) {
        window.turneroSpeech.speak(currentTicketObj.nombre_completo, currentTicketObj.modulo_entrega_asignado);
    } else {
        alert('No hay ningún paciente en cola para llamar.');
    }
}

function actualizarReloj() {
    const now = new Date();
    document.getElementById('reloj-digital').innerText = now.toLocaleTimeString('es-CO');
    document.getElementById('fecha-digital').innerText = now.toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

function actualizarTurnero2() {
    fetch('api/turnero_data.php?type=2')
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('tabla-turnero2');
            let html = '';

            if (data.turnos && data.turnos.length > 0) {
                const primerTurno = data.turnos[0];
                currentTicketObj = primerTurno;

                document.getElementById('ultimo-paciente').innerText = primerTurno.nombre_completo;
                document.getElementById('ultimo-ticket').innerText = primerTurno.ticket_numero;
                document.getElementById('ultimo-modulo').innerText = primerTurno.modulo_entrega_asignado;

                // Si hay un turno nuevo que no ha sido llamado aún por nombre
                if (primerTurno.id !== lastCalledTicketId) {
                    lastCalledTicketId = primerTurno.id;
                    if (audioEnabled) {
                        window.turneroSpeech.speak(primerTurno.nombre_completo, primerTurno.modulo_entrega_asignado);
                    }
                }

                data.turnos.forEach(row => {
                    html += `
                        <tr class="border-secondary">
                            <td class="fw-bold text-success">${row.ticket_numero}</td>
                            <td class="fw-bold text-white">${row.nombre_completo}</td>
                            <td class="text-end">
                                <span class="badge bg-warning text-dark fs-5 fw-bold"><i class="fa-solid fa-door-open me-1"></i> ${row.modulo_entrega_asignado}</span>
                            </td>
                        </tr>
                    `;
                });
            } else {
                currentTicketObj = null;
                document.getElementById('ultimo-paciente').innerText = '--';
                document.getElementById('ultimo-ticket').innerText = '--';
                document.getElementById('ultimo-modulo').innerText = '--';
                html = `<tr><td colspan="3" class="text-center text-muted py-4 fs-4">No hay llamados activos en ventanilla.</td></tr>`;
            }

            tbody.innerHTML = html;
        });
}
</script>
</body>
</html>
