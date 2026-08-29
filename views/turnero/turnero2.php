<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../models/Empresa.php';

$empresaModel = new Empresa();
$config = $empresaModel->getConfig();

$active_sede_id = intval($_GET['sede_id'] ?? ($_SESSION['active_sede_id'] ?? ($_SESSION['sede_id'] ?? 0)));
$sedeInfo = $active_sede_id ? $empresaModel->getSedeById($active_sede_id) : null;
$nombre_sede_mostrar = $sedeInfo ? $sedeInfo['nombre_sede'] : ($_SESSION['active_sede_nombre'] ?? 'Todas las Sedes');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sala de Espera - Entrega de Medicamentos - <?= htmlspecialchars($config['razon_social']) ?> (<?= htmlspecialchars($nombre_sede_mostrar) ?>)</title>
    <!-- Favicon SISPAM -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="apple-touch-icon" href="assets/img/logo_sispam.jpg">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root {
            --savia-primary: #224b5b;
            --savia-accent: #04ac8c;
            --savia-accent-hover: #05c7a3;
            --savia-dark: #0b1a20;
            --savia-card-bg: rgba(34, 75, 91, 0.35);
            --savia-border: rgba(4, 172, 140, 0.28);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #09151a 0%, #16323d 50%, #0c1c24 100%);
            color: #f1f5f9;
            min-height: 100vh;
            margin: 0;
            padding-bottom: 50px;
            overflow-x: hidden;
        }

        /* Header Institucional */
        .turnero-header {
            background: linear-gradient(90deg, #132a33 0%, #224b5b 50%, #15323e 100%);
            border-bottom: 3px solid var(--savia-accent);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .text-accent {
            color: var(--savia-accent) !important;
        }

        .bg-accent {
            background-color: var(--savia-accent) !important;
        }

        .border-accent {
            border-color: var(--savia-accent) !important;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Tarjetas de Turnero */
        .savia-card {
            background: var(--savia-card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--savia-border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            transition: all 0.3s ease;
        }

        .savia-card-active {
            border: 2px solid var(--savia-accent);
            box-shadow: 0 0 25px rgba(4, 172, 140, 0.3);
            background: linear-gradient(180deg, rgba(34, 75, 91, 0.55) 0%, rgba(11, 26, 32, 0.85) 100%);
        }

        /* Efecto de pulso en el último llamado */
        @keyframes pulseSavia {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(4, 172, 140, 0.5); }
            70% { transform: scale(1.01); box-shadow: 0 0 0 12px rgba(4, 172, 140, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(4, 172, 140, 0); }
        }

        .pulse-active {
            animation: pulseSavia 2.5s infinite;
        }

        /* Tabla de Turnos */
        .turnos-table {
            --bs-table-bg: transparent !important;
            --bs-table-color: #ffffff !important;
            color: #ffffff !important;
        }

        .turnos-table thead th {
            background-color: rgba(34, 75, 91, 0.85) !important;
            color: #ffffff !important;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--savia-accent) !important;
            padding: 12px 16px;
        }

        .turnos-table tbody tr {
            border-bottom: 1px solid rgba(4, 172, 140, 0.15) !important;
            background-color: rgba(15, 34, 43, 0.55);
            transition: background-color 0.2s ease;
        }

        .turnos-table tbody tr:nth-child(even) {
            background-color: rgba(22, 50, 61, 0.4);
        }

        .turnos-table tbody tr:hover {
            background-color: rgba(4, 172, 140, 0.2) !important;
        }

        .turnos-table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 1.05rem;
            color: #ffffff !important;
        }

        .paciente-nombre-td {
            color: #fde047 !important; /* Amarillo brillante de máxima visibilidad en TV */
            font-weight: 700 !important;
            font-size: 1.15rem !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.7);
        }

        .paciente-nombre-activo {
            color: #38bdf8 !important; /* Cyan / Celeste eléctrico para el primer llamado */
            font-size: 1.22rem !important;
            text-shadow: 0 0 8px rgba(56, 189, 248, 0.4);
        }

        .paciente-box-destacado {
            background: rgba(8, 20, 26, 0.92);
            border: 2px solid var(--savia-accent);
            border-radius: 12px;
            padding: 12px 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5), inset 0 0 12px rgba(4, 172, 140, 0.25);
        }

        /* Video Institucional Responsive */
        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            border: 1px solid rgba(4, 172, 140, 0.35);
            background: #000;
        }

        .video-container iframe,
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 12px;
            object-fit: cover;
        }

        /* Marquesina */
        .marquesina-savia {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(90deg, #16323d 0%, #224b5b 50%, #04ac8c 100%);
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 600;
            padding: 9px 0;
            box-shadow: 0 -4px 15px rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow: hidden;
            white-space: nowrap;
        }

        .marquesina-track {
            display: inline-block;
            padding-left: 100%;
            animation: marquee 32s linear infinite;
        }

        @keyframes marquee {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-100%, 0); }
        }

        /* Badges estilizados */
        .badge-savia {
            background-color: var(--savia-accent);
            color: #ffffff;
            font-weight: 600;
            border-radius: 8px;
            padding: 6px 12px;
        }

        .badge-preferencial {
            background-color: #f59e0b;
            color: #111827;
            font-weight: 700;
            border-radius: 8px;
            padding: 4px 10px;
        }
    </style>
</head>
<body>

<!-- Encabezado Turnero TV -->
<header class="turnero-header py-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <?php if (!empty($config['logo_url']) && file_exists(BASE_DIR . '/' . $config['logo_url'])): ?>
            <img src="<?= $config['logo_url'] ?>" alt="Logo" style="max-height: 52px;" class="rounded shadow-sm">
        <?php else: ?>
            <div class="p-2 rounded-3 bg-accent text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                <i class="fa-solid fa-hospital fs-4"></i>
            </div>
        <?php endif; ?>
        <div>
            <h4 class="fw-bold mb-0 text-white tracking-wide"><?= htmlspecialchars($config['razon_social']) ?></h4>
            <div class="text-accent fw-bold small d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-warning text-dark py-1 px-2 fw-bold shadow-sm"><i class="fa-solid fa-location-dot me-1"></i> SEDE: <?= htmlspecialchars($nombre_sede_mostrar) ?></span>
                <span class="badge bg-accent text-white py-1 px-2"><i class="fa-solid fa-hand-holding-medical me-1"></i> EPS SAVIA SALUD</span>
                <span>SALA DE ESPERA • ENTREGA DE MEDICAMENTOS</span>
            </div>
        </div>
    </div>
    
    <!-- Reloj Digital y Fecha -->
    <div class="text-end">
        <div id="reloj-digital" class="font-mono text-accent fw-bold" style="font-size: 1.85rem; line-height: 1.1;">00:00:00</div>
        <div id="fecha-digital" class="small text-white-50 text-capitalize">--</div>
    </div>
</header>

<!-- Contenido Principal -->
<main class="container-fluid p-3 p-lg-4">
    <div class="row g-4">
        
        <!-- Columna Izquierda: Último Llamado & Video Institucional -->
        <div class="col-lg-5 col-xl-5 d-flex flex-column gap-3">
            
            <!-- Tarjeta 1: Último Llamado Activo -->
            <div class="savia-card savia-card-active p-4 text-center pulse-active">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="badge-savia text-uppercase small shadow-sm">
                        <i class="fa-solid fa-bell me-1"></i> Turno en Llamado
                    </span>
                    <span class="badge bg-dark bg-opacity-75 text-accent border border-accent border-opacity-50 small">
                        <i class="fa-solid fa-volume-high me-1"></i> Audio Activo
                    </span>
                </div>

                <div class="text-accent small fw-bold text-uppercase mb-1 tracking-wide">
                    <i class="fa-solid fa-user me-1"></i> Paciente Convocado
                </div>
                <div class="paciente-box-destacado mb-3">
                    <div id="ultimo-paciente" class="fw-bold tracking-wide" style="font-size: 1.85rem; min-height: 2.4rem; line-height: 1.25; color: #fde047; text-shadow: 0 2px 8px rgba(0,0,0,0.85);">--</div>
                </div>

                <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                    <span class="text-white-50 small fw-bold">TIQUETE:</span>
                    <span id="ultimo-ticket" class="badge bg-dark border border-accent text-accent font-mono fs-4 px-3 py-1 fw-bold shadow-sm">--</span>
                </div>

                <!-- Destacado del Módulo / Ventanilla Asignada -->
                <div class="p-3 rounded-4 border d-flex flex-column align-items-center justify-content-center gap-1 mb-3" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.25) 0%, rgba(4, 172, 140, 0.3) 100%); border-color: #f59e0b !important;">
                    <span class="text-warning small fw-bold text-uppercase tracking-wider">
                        <i class="fa-solid fa-person-walking-arrow-right me-1"></i> DIRÍJASE A LA VENTANILLA:
                    </span>
                    <div id="ultimo-modulo" class="fw-bold text-white fs-2 text-uppercase tracking-wide" style="text-shadow: 0 2px 10px rgba(0,0,0,0.8);">--</div>
                </div>

                <!-- Controles de Audio y Re-llamado -->
                <div class="d-flex justify-content-center gap-2 flex-wrap" id="audio-controls-container">
                    <button class="btn btn-sm btn-warning text-dark fw-bold px-3 py-2 shadow-sm rounded-3" id="btn-audio" onclick="habilitarAudio()">
                        <i class="fa-solid fa-volume-high me-1"></i> Activar Voz de Llamados
                    </button>
                    <button class="btn btn-sm btn-outline-light fw-bold px-3 py-2 shadow-sm rounded-3" id="btn-rellamar" onclick="reLlamarActual()" style="display: none;">
                        <i class="fa-solid fa-rotate me-1"></i> Re-llamar
                    </button>
                </div>
                <div class="small text-white-50 mt-2" id="audio-status-msg" style="font-size: 0.78rem;">
                    Haga clic para habilitar el sintetizador de voz en esta pantalla.
                </div>
            </div>

            <!-- Tarjeta 2: Video Institucional Savia Salud -->
            <div class="savia-card p-3 flex-grow-1 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-bold text-white small d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-play text-accent"></i>
                        <span>Información y Promoción en Salud</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-white-50 p-0 text-decoration-none" onclick="configurarVideo()" title="Cambiar enlace de video" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-gear"></i>
                    </button>
                </div>

                <!-- Video Frame -->
                <div class="video-container shadow">
                    <iframe id="iframeVideoInstitucional" 
                            src="https://www.youtube.com/embed/videoseries?list=PL_Xv_X_default&autoplay=1&mute=1&loop=1&controls=0&modestbranding=1" 
                            title="Video Institucional" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen>
                    </iframe>
                </div>
                <div class="text-white-50 text-center mt-2" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-shield-heart text-accent me-1"></i> EPS Savia Salud • Cuidamos tu bienestar
                </div>
            </div>

        </div>

        <!-- Columna Derecha: Listado de Pacientes Listos para Reclamar -->
        <div class="col-lg-7 col-xl-7">
            <div class="savia-card p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between border-bottom border-secondary border-opacity-50 pb-3 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-accent text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-boxes-packing fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-white mb-0">ÓRDENES LISTAS PARA ENTREGA</h5>
                            <span class="text-white-50 small">Pacientes llamados a ventanilla de despacho</span>
                        </div>
                    </div>
                    <span class="badge-savia font-mono fs-6" id="total-listos-badge">
                        0 En Cola
                    </span>
                </div>

                <div class="table-responsive flex-grow-1">
                    <table class="table turnos-table table-borderless align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 22%;">Tiquete</th>
                                <th style="width: 38%;">Paciente</th>
                                <th class="text-center" style="width: 15%;">Prioridad</th>
                                <th class="text-center" style="width: 25%;">Ventanilla / Módulo</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-turnero2">
                            <!-- Inyección dinámica vía AJAX -->
                            <tr>
                                <td colspan="4" class="text-center text-white-50 py-5">
                                    <div class="spinner-border text-accent mb-2" role="status"></div>
                                    <div>Cargando listado de turnos...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Marquesina Informativa Inferior -->
<footer class="marquesina-savia">
    <div class="marquesina-track" id="marquesina-content">
        <i class="fa-solid fa-circle-info text-warning me-2"></i>
        <?= htmlspecialchars($config['marquesina_turnero'] ?? 'Bienvenido a la Sala de Espera de Savia Salud EPS. Por favor permanezca atento a su llamado en pantalla. Recuerde presentar su documento de identidad original al momento de la entrega de medicamentos.') ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <i class="fa-solid fa-notes-medical text-accent me-2"></i>
        Verifique sus medicamentos, dosis y fechas de vencimiento antes de retirarse del punto de atención.
    </div>
</footer>

<!-- Modal Configuración Rápida de Video -->
<div class="modal fade" id="modalConfigVideo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content bg-dark text-white border border-secondary">
            <div class="modal-header border-secondary py-2">
                <h6 class="modal-title fw-bold small"><i class="fa-solid fa-video me-1 text-accent"></i> URL de Video Institucional</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <label class="form-label text-white-50 small">Enlace YouTube o MP4:</label>
                <input type="text" id="inputUrlVideo" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="https://www.youtube.com/embed/...">
                <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">Se guardará localmente en el navegador de este televisor.</small>
            </div>
            <div class="modal-footer border-secondary py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-success fw-bold px-3" onclick="guardarUrlVideo()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/turnero_speech.js?v=<?= time() ?>"></script>
<script>
let lastCalledTicketId = null;
let currentTicketObj = null;
let audioEnabled = false;

// Manejo de Video Institucional (Persistente en LocalStorage)
const DEFAULT_VIDEO_URL = "https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1&loop=1&playlist=dQw4w9WgXcQ&controls=0";

function cargarVideoGuardado() {
    const urlGuardada = localStorage.getItem('savia_turnero_video_url') || "https://www.youtube-nocookie.com/embed/live_stream?channel=SaviaSaludEPS&autoplay=1&mute=1&loop=1";
    const iframe = document.getElementById('iframeVideoInstitucional');
    if (iframe) {
        // Enlace por defecto optimizado para Savia Salud o video embebido
        iframe.src = urlGuardada;
    }
}

function configurarVideo() {
    const input = document.getElementById('inputUrlVideo');
    input.value = localStorage.getItem('savia_turnero_video_url') || '';
    const modalEl = document.getElementById('modalConfigVideo');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function guardarUrlVideo() {
    const input = document.getElementById('inputUrlVideo');
    let url = input.value.trim();
    if (url) {
        // Normalizar URLs estándar de youtube a embed
        if (url.includes('youtube.com/watch?v=')) {
            const vId = url.split('watch?v=')[1].split('&')[0];
            url = `https://www.youtube.com/embed/${vId}?autoplay=1&mute=1&loop=1&playlist=${vId}&controls=0`;
        } else if (url.includes('youtu.be/')) {
            const vId = url.split('youtu.be/')[1].split('?')[0];
            url = `https://www.youtube.com/embed/${vId}?autoplay=1&mute=1&loop=1&playlist=${vId}&controls=0`;
        }
        localStorage.setItem('savia_turnero_video_url', url);
    } else {
        localStorage.removeItem('savia_turnero_video_url');
    }
    cargarVideoGuardado();
    bootstrap.Modal.getInstance(document.getElementById('modalConfigVideo')).hide();
}

document.addEventListener('DOMContentLoaded', () => {
    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    cargarVideoGuardado();

    actualizarTurnero2();
    setInterval(actualizarTurnero2, 3000);
});

function habilitarAudio() {
    audioEnabled = true;
    const btnAudio = document.getElementById('btn-audio');
    const btnRellamar = document.getElementById('btn-rellamar');
    const statusMsg = document.getElementById('audio-status-msg');

    btnAudio.className = 'btn btn-sm btn-success fw-bold px-3 py-2 shadow-sm rounded-3';
    btnAudio.innerHTML = '<i class="fa-solid fa-volume-high me-1"></i> Audio Activado';
    btnRellamar.style.display = 'inline-block';
    statusMsg.innerHTML = '<span class="text-accent fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Audio habilitado. Los llamados sonarán por Nombre del Paciente y Ventanilla.</span>';

    // Llamar por nombre y módulo inmediatamente al paciente que esté en pantalla
    if (currentTicketObj) {
        lastCalledTicketId = currentTicketObj.id;
        window.turneroSpeech.speak(currentTicketObj.nombre_completo, currentTicketObj.modulo_entrega_asignado || 'Ventanilla');
    } else {
        window.turneroSpeech.playChime();
    }
}

function reLlamarActual() {
    if (currentTicketObj) {
        window.turneroSpeech.speak(currentTicketObj.nombre_completo, currentTicketObj.modulo_entrega_asignado || 'Ventanilla');
    } else {
        alert('No hay ningún paciente en cola para llamar.');
    }
}

function actualizarReloj() {
    const now = new Date();
    document.getElementById('reloj-digital').innerText = now.toLocaleTimeString('es-CO');
    document.getElementById('fecha-digital').innerText = now.toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

const activeSedeId = '<?= $active_sede_id ?>';

function actualizarTurnero2() {
    fetch('api/turnero_data.php?type=2&sede_id=' + encodeURIComponent(activeSedeId))
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('tabla-turnero2');
            const totalBadge = document.getElementById('total-listos-badge');
            let html = '';

            if (data.turnos && data.turnos.length > 0) {
                const total = data.turnos.length;
                if (totalBadge) totalBadge.innerText = `${total} Llamado${total > 1 ? 's' : ''}`;

                const primerTurno = data.turnos[0];
                currentTicketObj = primerTurno;

                document.getElementById('ultimo-paciente').innerText = primerTurno.nombre_habeas || primerTurno.nombre_completo;
                document.getElementById('ultimo-ticket').innerText = primerTurno.ticket_numero;
                const elModulo = document.getElementById('ultimo-modulo');
                if (elModulo) {
                    elModulo.innerText = primerTurno.modulo_entrega_asignado || 'VENTANILLA DE ENTREGA';
                }

                // Si hay un turno nuevo que no ha sido llamado aún por nombre
                if (primerTurno.id !== lastCalledTicketId) {
                    lastCalledTicketId = primerTurno.id;
                    if (audioEnabled) {
                        window.turneroSpeech.speak(primerTurno.nombre_completo || primerTurno.nombre_habeas, primerTurno.modulo_entrega_asignado || 'Ventanilla');
                    }
                }

                data.turnos.forEach((row, idx) => {
                    const esPreferencial = (row.prioridad && row.prioridad !== 'NORMAL');
                    const badgePrio = esPreferencial 
                        ? `<span class="badge-preferencial small shadow-sm"><i class="fa-solid fa-star me-1"></i> Preferencial</span>`
                        : `<span class="badge bg-secondary bg-opacity-50 text-white small">Normal</span>`;

                    const filaActivaClass = (idx === 0) ? 'style="background-color: rgba(4, 172, 140, 0.22) !important;"' : '';
                    const nombreClass = (idx === 0) ? 'paciente-nombre-activo' : 'paciente-nombre-td';

                    html += `
                        <tr ${filaActivaClass}>
                            <td class="fw-bold text-accent font-mono fs-6">
                                ${idx === 0 ? '<i class="fa-solid fa-caret-right text-warning me-1"></i>' : ''}
                                ${escapeHtml(row.ticket_numero)}
                            </td>
                            <td class="${nombreClass}">
                                ${escapeHtml(row.nombre_habeas || row.nombre_completo)}
                            </td>
                            <td class="text-center">
                                ${badgePrio}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-warning text-dark px-2 py-1 small fw-bold shadow-sm">
                                    <i class="fa-solid fa-desktop me-1"></i> ${escapeHtml(row.modulo_entrega_asignado || 'Ventanilla')}
                                </span>
                            </td>
                        </tr>
                    `;
                });
            } else {
                currentTicketObj = null;
                if (totalBadge) totalBadge.innerText = '0 En Cola';
                document.getElementById('ultimo-paciente').innerText = '--';
                document.getElementById('ultimo-ticket').innerText = '--';
                const elModulo = document.getElementById('ultimo-modulo');
                if (elModulo) elModulo.innerText = '--';

                html = `
                    <tr>
                        <td colspan="4" class="text-center text-white-50 py-5">
                            <i class="fa-solid fa-clipboard-check fa-3x mb-3 text-accent opacity-50"></i>
                            <h6 class="text-white">No hay órdenes en atención en ventanilla en este momento</h6>
                            <small class="text-white-50">Los pacientes llamados a entrega aparecerán aquí automáticamente.</small>
                        </td>
                    </tr>`;
            }

            tbody.innerHTML = html;
        })
        .catch(err => {
            console.error("Error al actualizar turnero 2:", err);
        });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
</script>
</body>
</html>
