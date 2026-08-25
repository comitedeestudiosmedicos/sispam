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
    <title>Turnero 1 - En Proceso - <?= htmlspecialchars($config['razon_social']) ?></title>
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
            <i class="fa-solid fa-prescription-bottle-medical fs-1 text-info"></i>
        <?php endif; ?>
        <div>
            <h2 class="fw-bold mb-0 text-white"><?= htmlspecialchars($config['razon_social']) ?></h2>
            <div class="text-info fw-semibold fs-5"><i class="fa-solid fa-hourglass-half me-2"></i> SALA DE ESPERA - PACIENTES EN PROCESO</div>
        </div>
    </div>
    <div class="text-end text-white">
        <div id="reloj-digital" class="display-6 fw-bold">00:00:00</div>
        <div id="fecha-digital" class="small text-info">--</div>
    </div>
</div>

<div class="container-fluid p-4">
    <div class="row g-4">
        <!-- Columna Izquierda: Video Institucional -->
        <div class="col-lg-6">
            <div class="turnero-card p-3 h-100 shadow-lg">
                <h4 class="fw-bold text-white mb-3"><i class="fa-solid fa-video me-2 text-info"></i> Informativo Institucional</h4>
                <div class="ratio ratio-16x9 rounded overflow-hidden shadow">
                    <video id="video-player" src="<?= htmlspecialchars($config['video_turnero_url']) ?>" autoplay loop muted playsinline controls></video>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Grilla de Atenciones En Proceso -->
        <div class="col-lg-6">
            <div class="turnero-card p-4 h-100 shadow-lg">
                <h3 class="fw-bold text-white mb-3 text-center border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-spinner fa-spin text-info me-2"></i> PACIENTES EN PROCESO
                </h3>

                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle fs-4">
                        <thead>
                            <tr class="text-info border-secondary">
                                <th>TIQUETE</th>
                                <th>PACIENTE</th>
                                <th class="text-end">ESTADO</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-turnero1">
                            <!-- Inyección vía AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Marquesina Informativa -->
<div class="fixed-bottom marquesina-container shadow-lg">
    <div class="marquesina-text" id="marquesina-content">
        <?= htmlspecialchars($config['marquesina_turnero']) ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    actualizarTurnero1();
    setInterval(actualizarTurnero1, 4000);
});

function actualizarReloj() {
    const now = new Date();
    document.getElementById('reloj-digital').innerText = now.toLocaleTimeString('es-CO');
    document.getElementById('fecha-digital').innerText = now.toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

function actualizarTurnero1() {
    fetch('api/turnero_data.php?type=1')
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('tabla-turnero1');
            let html = '';

            if (data.turnos && data.turnos.length > 0) {
                data.turnos.forEach(row => {
                    html += `
                        <tr class="border-secondary">
                            <td class="fw-bold text-info">${row.ticket_numero}</td>
                            <td class="fw-bold text-white">${row.nombre_habeas}</td>
                            <td class="text-end">
                                <span class="badge bg-primary fs-6"><i class="fa-solid fa-gear fa-spin me-1"></i> En Proceso</span>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html = `<tr><td colspan="3" class="text-center text-muted py-4 fs-5">No hay órdenes en proceso en este momento.</td></tr>`;
            }

            tbody.innerHTML = html;
        });
}
</script>
</body>
</html>
