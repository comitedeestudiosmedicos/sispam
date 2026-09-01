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
            max-width: 1100px;
            margin: 15px auto;
            padding: 18px 22px;
            transition: max-width 0.2s ease;
        }
        .print-table-wrapper {
            width: 100%;
            border-collapse: collapse;
        }
        .ticket-header-band {
            background: #f8fafc;
            border: 2px solid #0284c7;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 12px;
        }
        .logo-print {
            max-height: 42px;
        }
        .empresa-titulo {
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.15;
        }
        .sede-subtitulo {
            font-size: 0.8rem;
        }
        .paciente-nombre {
            font-size: 1.22rem;
            font-weight: 800;
            line-height: 1.15;
            color: #0f172a;
        }
        .paciente-detalles {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.2;
        }
        .tiquete-label {
            font-size: 0.72rem;
            letter-spacing: 0.5px;
        }
        .ticket-number-badge {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 1.55rem;
            font-weight: 800;
            padding: 4px 14px;
            border-radius: 6px;
            letter-spacing: 1px;
            display: inline-block;
            line-height: 1.2;
        }
        .seccion-header-titulo {
            font-size: 0.95rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .formula-zoom-container {
            width: 100%;
            overflow-x: auto;
            text-align: center;
        }
        .pdf-page-canvas {
            display: block;
            margin: 0 auto 12px auto;
            width: 100%;
            max-width: 100%;
            height: auto;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            transition: transform 0.2s ease;
        }
        .formula-img-print {
            width: 100%;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
            transition: transform 0.2s ease;
        }

        /* CONFIGURACIÓN EXCLUSIVA DE IMPRESIÓN (ENCABEZADO REPETIDO EN CADA HOJA) */
        @media print {
            @page {
                size: letter portrait;
                margin: 4mm 5mm 4mm 5mm;
            }
            .no-print, .seccion-header-titulo {
                display: none !important;
            }
            html, body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                color: #000000 !important;
                width: 100% !important;
            }
            .container, .print-card {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }
            thead {
                display: table-header-group !important; /* Fuerza la repetición del encabezado en TODAS las páginas */
            }
            tbody {
                display: table-row-group !important;
            }
            tr {
                page-break-inside: avoid !important;
            }
            .ticket-header-band {
                border: 2px solid #000000 !important;
                background: #f8fafc !important;
                padding: 4px 8px !important;
                margin-bottom: 4px !important;
                border-radius: 6px !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .logo-print {
                max-height: 36px !important;
            }
            .empresa-titulo {
                font-size: 0.85rem !important;
            }
            .sede-subtitulo {
                font-size: 0.78rem !important;
            }
            .paciente-nombre {
                font-size: 1.18rem !important;
                font-weight: 800 !important;
                line-height: 1.15 !important;
                color: #000000 !important;
            }
            .paciente-detalles {
                font-size: 0.82rem !important;
                color: #222222 !important;
                line-height: 1.1 !important;
            }
            .tiquete-label {
                font-size: 0.72rem !important;
            }
            .ticket-number-badge {
                background-color: #000000 !important;
                color: #ffffff !important;
                font-size: 1.45rem !important;
                font-weight: 800 !important;
                padding: 2px 10px !important;
                border-radius: 4px !important;
                line-height: 1.15 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .formula-zoom-container, .formula-render-area, .pdf-canvas-container {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
            }
            .pdf-page-canvas, .formula-img-print {
                display: block !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: none !important;
                object-fit: contain !important;
                border: none !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container py-2">
    <!-- Barra de Controles y Zoom en Pantalla -->
    <div class="no-print d-flex justify-content-between align-items-center mb-3 mx-auto flex-wrap gap-2" style="max-width: 1100px;">
        <a href="index.php?page=alistamiento" class="btn btn-outline-secondary fw-bold">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver
        </a>
        
        <!-- Controles de Tamaño / Zoom -->
        <div class="btn-group shadow-sm" role="group">
            <button type="button" class="btn btn-light border fw-semibold" onclick="cambiarZoom(-0.15)" title="Reducir">
                <i class="fa-solid fa-magnifying-glass-minus me-1"></i> Reducir
            </button>
            <button type="button" class="btn btn-light border fw-semibold" onclick="resetZoom()" title="Restablecer tamaño">
                <span id="zoom-level-badge">100%</span>
            </button>
            <button type="button" class="btn btn-light border fw-semibold" onclick="cambiarZoom(0.15)" title="Agrandar">
                <i class="fa-solid fa-magnifying-glass-plus me-1 text-primary"></i> Agrandar
            </button>
            <button type="button" class="btn btn-outline-primary fw-semibold" onclick="toggleAnchoCompleto()" title="Alternar Ancho Completo">
                <i class="fa-solid fa-arrows-left-right me-1"></i> Ancho Total
            </button>
        </div>

        <button class="btn btn-primary btn-lg fw-bold shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i> Imprimir Orden Unificada
        </button>
    </div>

    <div class="print-card" id="print-card-container">
        <!-- Tabla con thead que el navegador replica automáticamente en cada hoja física de impresión -->
        <table class="print-table-wrapper w-100 border-0">
            <thead>
                <tr>
                    <th class="p-0 border-0 fw-normal text-start">
                        <!-- FRANJA DEL TIQUETE DE TURNO E INFORMACIÓN DEL PACIENTE -->
                        <div class="ticket-header-band">
                            <div class="row align-items-center">
                                <div class="col-md-3 col-3 text-start mb-0">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($config['logo_url']) && file_exists(BASE_DIR . '/' . $config['logo_url'])): ?>
                                            <img src="<?= $config['logo_url'] ?>" alt="Logo" class="logo-print">
                                        <?php else: ?>
                                            <i class="fa-solid fa-prescription-bottle-medical fs-2 text-primary"></i>
                                        <?php endif; ?>
                                        <div>
                                            <div class="empresa-titulo text-dark"><?= htmlspecialchars($config['razon_social']) ?></div>
                                            <div class="sede-subtitulo text-primary fw-semibold"><i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($nombre_sede) ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-6 text-center border-start border-end px-2 mb-0">
                                    <div class="paciente-nombre text-uppercase"><?= htmlspecialchars($ingreso['nombres'] . ' ' . $ingreso['apellidos']) ?></div>
                                    <div class="paciente-detalles d-flex align-items-center justify-content-center gap-1 flex-wrap">
                                        <span><strong>Doc:</strong> <?= htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento']) ?></span>
                                        <span>•</span>
                                        <span><strong>EPS:</strong> <?= htmlspecialchars($ingreso['eps_nombre']) ?></span>
                                        <span>•</span>
                                        <span><?= get_prioridad_badge($ingreso['prioridad'] ?? 'NORMAL') ?></span>
                                        <span>•</span>
                                        <span><i class="fa-solid fa-clock me-1"></i> <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?></span>
                                    </div>
                                </div>

                                <div class="col-md-3 col-3 text-end mb-0">
                                    <div class="d-inline-flex flex-column align-items-center">
                                        <span class="tiquete-label text-muted font-monospace fw-bold">TIQUETE DE TURNO</span>
                                        <div class="ticket-number-badge"><?= htmlspecialchars($ingreso['ticket_numero']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="p-0 border-0">
                        <!-- SECCIÓN: FÓRMULA MÉDICA TRANSCRITA (TAMAÑO AMPLIO) -->
                        <div class="formula-render-area">
                            <div class="seccion-header-titulo d-flex align-items-center justify-content-between w-100 no-print">
                                <span><i class="fa-solid fa-file-medical text-primary me-2"></i> FÓRMULA MÉDICA TRANSCRITA PARA ALISTAMIENTO</span>
                                <small class="text-muted font-monospace">Tiquete: <?= htmlspecialchars($ingreso['ticket_numero']) ?></small>
                            </div>

                            <?php 
                                $archivosTrans = !empty($ingreso['transcripciones_archivos']) ? $ingreso['transcripciones_archivos'] : [];
                                if (empty($archivosTrans) && !empty($ingreso['pdf_transcripcion_url'])) {
                                    $archivosTrans = [['url' => $ingreso['pdf_transcripcion_url'], 'nombre' => 'Orden Transcrita']];
                                }
                            ?>

                            <div class="formula-zoom-container" id="formula-zoom-wrapper">
                                <?php if (!empty($archivosTrans)): ?>
                                    <?php foreach ($archivosTrans as $idxTrans => $tFile): ?>
                                        <?php 
                                            $urlT = $tFile['url'];
                                            $extTrans = strtolower(pathinfo($urlT, PATHINFO_EXTENSION));
                                            $canvasId = 'pdf-transcripcion-canvas-' . $idxTrans;
                                        ?>
                                        <div class="w-100 mb-2">
                                            <?php if (count($archivosTrans) > 1): ?>
                                                <div class="badge bg-primary text-white mb-1 fs-6 no-print">
                                                    <i class="fa-solid fa-file-pdf me-1"></i> <?= htmlspecialchars($tFile['nombre'] ?? ('Documento #' . ($idxTrans + 1))) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array($extTrans, ['jpg', 'jpeg', 'png', 'webp', 'gif'])): ?>
                                                <div class="text-center">
                                                    <img src="<?= htmlspecialchars($urlT) ?>" class="img-fluid rounded formula-img-print zoomable-element">
                                                </div>
                                            <?php else: ?>
                                                <div id="<?= $canvasId ?>" class="pdf-canvas-container text-center" data-pdf-url="<?= htmlspecialchars($urlT) ?>">
                                                    <div class="spinner-border text-primary my-3" role="status">
                                                        <span class="visually-hidden">Cargando páginas de la fórmula transcrita...</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif (!empty($ingreso['transcripcion_texto'])): ?>
                                    <div class="p-4 bg-light rounded border font-monospace text-dark text-start w-100" style="white-space: pre-wrap; font-size: 1.1rem; line-height: 1.6;">
                                        <?= htmlspecialchars($ingreso['transcripcion_texto']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center py-3 w-100">
                                        <i class="fa-solid fa-file-invoice fa-2x mb-1 d-block"></i>
                                        <strong>Orden transcrita registrada sin archivo PDF adjunto.</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let currentZoom = 1.0;

function cambiarZoom(delta) {
    currentZoom = Math.min(Math.max(0.6, currentZoom + delta), 2.2);
    aplicarZoom();
}

function resetZoom() {
    currentZoom = 1.0;
    aplicarZoom();
}

function toggleAnchoCompleto() {
    const card = document.getElementById('print-card-container');
    if (card.style.maxWidth === '100%') {
        card.style.maxWidth = '1100px';
    } else {
        card.style.maxWidth = '100%';
    }
}

function aplicarZoom() {
    document.getElementById('zoom-level-badge').innerText = Math.round(currentZoom * 100) + '%';
    const elementos = document.querySelectorAll('.zoomable-element, .pdf-page-canvas');
    elementos.forEach(el => {
        el.style.width = (currentZoom * 100) + '%';
        el.style.maxWidth = (currentZoom * 100) + '%';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function renderizarPdfCompleto(pdfUrl, containerId) {
        if (!pdfUrl) return Promise.resolve();
        const container = document.getElementById(containerId);
        if (!container) return Promise.resolve();

        return pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
            container.innerHTML = '';
            let renderPromises = [];
            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                renderPromises.push(
                    pdf.getPage(pageNum).then(function(page) {
                        const scale = 2.5;
                        const viewport = page.getViewport({ scale: scale });

                        const canvas = document.createElement('canvas');
                        canvas.className = 'pdf-page-canvas zoomable-element';

                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        return page.render({ canvasContext: context, viewport: viewport }).promise.then(() => {
                            container.appendChild(canvas);
                        });
                    })
                );
            }
            return Promise.all(renderPromises);
        }).catch(function(err) {
            console.error("Error al renderizar PDF:", err);
            container.innerHTML = `<div class="alert alert-warning">No se pudo cargar la vista previa del PDF. <a href="${pdfUrl}" target="_blank">Abrir PDF original</a></div>`;
        });
    }

    const containers = document.querySelectorAll('.pdf-canvas-container');
    if (containers.length > 0) {
        let promises = [];
        containers.forEach(cont => {
            const pdfUrl = cont.getAttribute('data-pdf-url');
            if (pdfUrl) {
                promises.push(renderizarPdfCompleto(pdfUrl, cont.id));
            }
        });
        Promise.all(promises).then(() => {
            <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == '1'): ?>
                setTimeout(() => { window.print(); }, 600);
            <?php endif; ?>
        });
    } else {
        <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == '1'): ?>
            setTimeout(() => { window.print(); }, 500);
        <?php endif; ?>
    }
});
</script>

</body>
</html>
