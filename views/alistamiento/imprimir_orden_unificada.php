<?php
require_once __DIR__ . '/../../config/app.php';
check_auth();

require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/Empresa.php';

$id = intval($_GET['id'] ?? 0);
$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$ingreso = $ingresoModel->getById($id);
$config  = $empresaModel->getConfig();

if (!$ingreso) {
    die("Registro de ingreso u orden no encontrado.");
}

$nombre_sede = $ingreso['nombre_sede'] ?? ($ingreso['sede_nombre'] ?? 'Sede Principal');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden Unificada + Tiquete - <?= htmlspecialchars($ingreso['ticket_numero']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- PDF.js CDN para renderizado directo de páginas PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #0f172a;
        }
        .print-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 20px auto;
            padding: 25px;
        }
        .ticket-header-band {
            background: #f8fafc;
            border: 2px solid #0284c7;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .ticket-number-badge {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 1.8rem;
            font-weight: 800;
            padding: 6px 16px;
            border-radius: 8px;
            letter-spacing: 1px;
            display: inline-block;
        }
        .pdf-page-canvas {
            display: block;
            margin: 0 auto 15px auto;
            max-width: 100%;
            height: auto;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff;
            }
            .print-card {
                box-shadow: none;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            .ticket-header-band {
                border: 2px solid #000000;
                background: #ffffff;
            }
            .ticket-number-badge {
                background-color: #000000;
                color: #ffffff;
            }
            .pdf-page-canvas {
                page-break-inside: avoid;
                page-break-after: always;
                border: none !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<div class="container py-3">
    <!-- Botones de Impresión en Pantalla -->
    <div class="no-print d-flex justify-content-between align-items-center mb-3 mx-auto" style="max-width: 900px;">
        <a href="index.php?page=alistamiento" class="btn btn-outline-secondary fw-bold">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver a Alistamiento
        </a>
        <button class="btn btn-primary btn-lg fw-bold shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i> Imprimir Orden Unificada (Tiquete + PDF Transcrito)
        </button>
    </div>

    <div class="print-card">
        <!-- FRANJA DEL TIQUETE DE TURNO E INFORMACIÓN DEL PACIENTE -->
        <div class="ticket-header-band">
            <div class="row align-items-center">
                <div class="col-md-3 text-center text-md-start mb-2 mb-md-0">
                    <?php if (!empty($config['logo_url']) && file_exists(BASE_DIR . '/' . $config['logo_url'])): ?>
                        <img src="<?= $config['logo_url'] ?>" alt="Logo" style="max-height: 50px;">
                    <?php else: ?>
                        <i class="fa-solid fa-prescription-bottle-medical fs-2 text-primary"></i>
                    <?php endif; ?>
                    <div class="fw-bold small mt-1"><?= htmlspecialchars($config['razon_social']) ?></div>
                    <div class="small fw-semibold text-primary"><i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($nombre_sede) ?></div>
                </div>

                <div class="col-md-5 text-center text-md-start mb-2 mb-md-0 border-start border-end px-3">
                    <div class="small text-muted font-monospace">PACIENTE DE ALISTAMIENTO</div>
                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($ingreso['nombres'] . ' ' . $ingreso['apellidos']) ?></h5>
                    <div class="small text-secondary">
                        <?= htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento']) ?> | 
                        <strong>EPS:</strong> <?= htmlspecialchars($ingreso['eps_nombre']) ?>
                    </div>
                    <div class="small text-dark mt-1">
                        <strong>SEDE:</strong> <span class="badge bg-light text-dark border"><i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($nombre_sede) ?></span>
                    </div>
                    <div class="mt-1">
                        <?= get_prioridad_badge($ingreso['prioridad'] ?? 'NORMAL') ?>
                        <small class="text-muted ms-2"><i class="fa-solid fa-clock me-1"></i> <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?></small>
                    </div>
                </div>

                <div class="col-md-4 text-center">
                    <small class="text-muted d-block font-monospace fw-bold mb-1">TIQUETE DE ATENCIÓN</small>
                    <div class="ticket-number-badge"><?= htmlspecialchars($ingreso['ticket_numero']) ?></div>
                    <div class="small fw-bold text-secondary mt-1"><i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($nombre_sede) ?></div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN: FÓRMULA MÉDICA TRANSCRITA -->
        <div class="mt-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="fa-solid fa-file-medical text-primary me-2"></i> FÓRMULA MÉDICA TRANSCRITA PARA ALISTAMIENTO
            </h5>

            <?php if (!empty($ingreso['pdf_transcripcion_url']) && file_exists(BASE_DIR . '/' . $ingreso['pdf_transcripcion_url'])): ?>
                <?php 
                    $extTrans = strtolower(pathinfo($ingreso['pdf_transcripcion_url'], PATHINFO_EXTENSION));
                ?>
                <?php if (in_array($extTrans, ['jpg', 'jpeg', 'png', 'webp', 'gif'])): ?>
                    <div class="text-center my-3">
                        <img src="<?= htmlspecialchars($ingreso['pdf_transcripcion_url']) ?>" class="img-fluid rounded border shadow-sm" style="max-width: 100%;">
                    </div>
                <?php else: ?>
                    <div id="pdf-transcripcion-canvas-list" class="text-center my-3">
                        <div class="spinner-border text-primary my-4" role="status">
                            <span class="visually-hidden">Cargando páginas de la fórmula transcrita...</span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif (!empty($ingreso['transcripcion_texto'])): ?>
                <div class="p-3 bg-light rounded border font-monospace text-dark" style="white-space: pre-wrap;">
                    <?= htmlspecialchars($ingreso['transcripcion_texto']) ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fa-solid fa-file-invoice fa-2x mb-2 d-block"></i>
                    <strong>Orden transcrita registrada sin archivo PDF adjunto.</strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function renderizarPdfCompleto(pdfUrl, containerId) {
        if (!pdfUrl) return;
        const container = document.getElementById(containerId);
        if (!container) return;

        pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
            container.innerHTML = '';
            let renderPromises = [];
            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                renderPromises.push(
                    pdf.getPage(pageNum).then(function(page) {
                        const scale = 1.5;
                        const viewport = page.getViewport({ scale: scale });

                        const canvas = document.createElement('canvas');
                        canvas.className = 'pdf-page-canvas border rounded mb-3';

                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        return page.render({ canvasContext: context, viewport: viewport }).promise.then(() => {
                            container.appendChild(canvas);
                        });
                    })
                );
            }
            Promise.all(renderPromises).then(() => {
                <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == '1'): ?>
                    setTimeout(() => { window.print(); }, 600);
                <?php endif; ?>
            });
        }).catch(function(err) {
            console.error("Error al renderizar PDF:", err);
            container.innerHTML = `<div class="alert alert-warning">No se pudo cargar la vista previa del PDF. <a href="${pdfUrl}" target="_blank">Abrir PDF original</a></div>`;
        });
    }

    <?php if (!empty($ingreso['pdf_transcripcion_url']) && file_exists(BASE_DIR . '/' . $ingreso['pdf_transcripcion_url']) && strtolower(pathinfo($ingreso['pdf_transcripcion_url'], PATHINFO_EXTENSION)) === 'pdf'): ?>
        renderizarPdfCompleto('<?= $ingreso['pdf_transcripcion_url'] ?>', 'pdf-transcripcion-canvas-list');
    <?php else: ?>
        <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == '1'): ?>
            setTimeout(() => { window.print(); }, 500);
        <?php endif; ?>
    <?php endif; ?>
});
</script>

</body>
</html>
