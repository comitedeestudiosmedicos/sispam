<?php
require_once __DIR__ . '/../../config/app.php';
check_auth();

require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/Empresa.php';

$id = intval($_GET['id'] ?? 0);
$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$ingreso = $ingresoModel->getById($id);
$config = $empresaModel->getConfig();

if (!$ingreso) {
    die("Registro de ingreso no encontrado.");
}

$faltantesRaw = !empty($ingreso['faltantes_alistamiento']) ? trim($ingreso['faltantes_alistamiento']) : trim($ingreso['observaciones_pendientes'] ?? '');
$faltantesDetalle = $faltantesRaw;

$esFaltanteReal = false;
if (!empty($faltantesDetalle)) {
    $txtUpper = mb_strtoupper($faltantesDetalle, 'UTF-8');
    if (!str_contains($txtUpper, 'VERIFICACIÓN EXITOSA') && 
        !str_contains($txtUpper, 'ENTREGA COMPLETA') && 
        !str_contains($txtUpper, 'SIN NOVEDADES') &&
        !str_contains($txtUpper, 'ENTREGA 100%') &&
        (str_contains($txtUpper, 'FALTANTE') || str_contains($txtUpper, 'DIFERENCIA') || str_contains($txtUpper, 'SIN REGISTRO') || str_contains($txtUpper, 'PENDIENTE') || str_contains($txtUpper, 'NOVEDAD'))) {
        $esFaltanteReal = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acta de Entrega Firmada - <?= htmlspecialchars($ingreso['ticket_numero']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- PDF.js CDN para renderizado directo de páginas PDF en alta resolución -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #0f172a;
        }
        .acta-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 20px auto;
            padding: 35px;
        }
        .firma-box {
            border: 2px dashed #94a3b8;
            border-radius: 10px;
            background: #f8fafc;
            padding: 15px;
            text-align: center;
            max-width: 350px;
            margin: 0 auto;
        }
        .firma-img {
            max-height: 120px;
            width: auto;
        }
        .pdf-page-canvas {
            display: block;
            margin: 0 auto 20px auto;
            max-width: 100%;
            height: auto;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff;
            }
            .acta-card {
                box-shadow: none;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            .pdf-embed-container {
                page-break-before: always;
            }
            .pdf-page-canvas {
                page-break-inside: avoid;
                page-break-after: always;
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<div class="container py-3">
    <!-- Botones de Acción / Impresión -->
    <div class="no-print d-flex justify-content-between align-items-center mb-3 max-w-900 mx-auto" style="max-width: 900px;">
        <a href="index.php?page=entrega" class="btn btn-outline-secondary fw-bold">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver a Entrega
        </a>
        <button class="btn btn-success btn-lg fw-bold shadow-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-2"></i> Imprimir Acta + PDFs de Transcripción & Alistamiento
        </button>
    </div>

    <div class="acta-card">
        <!-- Encabezado de la Empresa -->
        <div class="row align-items-center border-bottom pb-3 mb-4">
            <div class="col-md-3 text-center text-md-start">
                <?php if (!empty($config['logo_url']) && file_exists(BASE_DIR . '/' . $config['logo_url'])): ?>
                    <img src="<?= $config['logo_url'] ?>" alt="Logo" style="max-height: 70px;">
                <?php else: ?>
                    <i class="fa-solid fa-prescription-bottle-medical fs-1 text-primary"></i>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-center">
                <h4 class="fw-bold mb-1 text-uppercase text-primary"><?= htmlspecialchars($config['razon_social']) ?></h4>
                <div class="small text-muted">NIT: <?= htmlspecialchars($config['nit']) ?> | <?= htmlspecialchars($config['direccion']) ?></div>
                <div class="small text-muted">Tel: <?= htmlspecialchars($config['telefono']) ?> | <?= htmlspecialchars($config['email']) ?></div>
            </div>
            <div class="col-md-3 text-center text-md-end">
                <div class="p-2 border border-primary rounded bg-light">
                    <small class="text-muted d-block font-weight-bold">TIQUETE DE TURNO</small>
                    <span class="fs-4 fw-bold text-primary"><?= htmlspecialchars($ingreso['ticket_numero']) ?></span>
                </div>
            </div>
        </div>

        <h4 class="fw-bold text-center mb-4 text-dark text-uppercase border-bottom pb-2">
            ACTA DIGITAL DE CONFORMIDAD Y ENTREGA DE MEDICAMENTOS
        </h4>

        <!-- Información del Paciente y de la Atención -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="p-3 bg-light rounded border h-100">
                    <div class="fw-bold text-primary mb-2 border-bottom pb-1"><i class="fa-solid fa-user me-2"></i> DATOS DEL PACIENTE</div>
                    <div><strong>Nombre Completo:</strong> <?= htmlspecialchars($ingreso['nombres'] . ' ' . $ingreso['apellidos']) ?></div>
                    <div><strong>Documento:</strong> <?= htmlspecialchars($ingreso['tipo_documento'] . ' - ' . $ingreso['numero_documento']) ?></div>
                    <div><strong>EPS:</strong> <?= htmlspecialchars($ingreso['eps_nombre']) ?></div>
                    <div><strong>Teléfono:</strong> <?= htmlspecialchars($ingreso['telefono'] ?? 'No especificado') ?></div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="p-3 bg-light rounded border h-100">
                    <div class="fw-bold text-primary mb-2 border-bottom pb-1"><i class="fa-solid fa-hospital-user me-2"></i> DETALLES DE LA ATENCIÓN</div>
                    <div><strong>Sede de Atención:</strong> <span class="badge bg-primary text-white"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($ingreso['nombre_sede'] ?? ($ingreso['sede_nombre'] ?? 'Sede Principal')) ?></span></div>
                    <div><strong>Fecha de Ingreso:</strong> <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?></div>
                    <div><strong>Fecha de Entrega:</strong> <?= date('d/m/Y h:i A', strtotime($ingreso['updated_at'])) ?></div>
                    <div><strong>Ventanilla / Módulo:</strong> <span class="badge bg-success"><?= htmlspecialchars($ingreso['modulo_entrega_asignado'] ?? 'Ventanilla General') ?></span></div>
                    <div><strong>Orientador de Ingreso:</strong> <?= htmlspecialchars($ingreso['orientador_nombre']) ?></div>
                </div>
            </div>
        </div>

        <!-- REPORTE DE MEDICAMENTOS Y CANTIDADES FALTANTES / PENDIENTES (SOLO SI EXISTEN FALTANTES REALES) -->
        <?php if ($esFaltanteReal): ?>
        <div class="alert alert-warning border-warning mb-4 shadow-sm">
            <div class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-triangle-exclamation text-warning me-1 fs-5"></i> DETALLE DE MEDICAMENTOS Y CANTIDADES FALTANTES / ENTREGAS PARCIALES:
            </div>
            <div class="p-2 bg-white rounded border border-warning text-dark font-monospace small">
                <?= nl2br(htmlspecialchars($faltantesDetalle)) ?>
            </div>
            <div class="small text-muted mt-1">
                <i class="fa-solid fa-info-circle me-1"></i> El paciente firma el presente recibo estando notificado del saldo o cantidades pendientes descritas arriba.
            </div>
        </div>
        <?php endif; ?>

        <!-- Declaración de Conformidad -->
        <div class="p-3 bg-light border rounded mb-4 text-muted small" style="line-height: 1.6;">
            Hago constar que he recibido los medicamentos descritos en el acta correspondiente a la presente atención, verificando cantidades alistadas, fechas de vencimiento y estado físico de los empaques de acuerdo a la normativa vigente.
        </div>

        <!-- Firma Digital Táctil y Foto del Paciente -->
        <div class="row my-4 align-items-center">
            <div class="col-md-6 text-center">
                <div class="firma-box">
                    <?php if (!empty($ingreso['firma_paciente_url']) && file_exists(BASE_DIR . '/' . $ingreso['firma_paciente_url'])): ?>
                        <img src="<?= $ingreso['firma_paciente_url'] ?>" alt="Firma Paciente" class="firma-img mb-1">
                    <?php else: ?>
                        <div class="py-4 text-muted small"><i class="fa-solid fa-signature fs-2 me-1 d-block mb-1"></i> FIRMA DIGITAL REGISTRADA</div>
                    <?php endif; ?>
                    <div class="border-top pt-1 fw-bold small text-dark"><?= htmlspecialchars($ingreso['nombres'] . ' ' . $ingreso['apellidos']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($ingreso['tipo_documento'] . ': ' . $ingreso['numero_documento']) ?></div>
                    <small class="text-success fw-semibold"><i class="fa-solid fa-shield-check me-1"></i> Firma Digitalizada y Validada</small>
                </div>
            </div>

            <div class="col-md-6 text-center">
                <?php 
                    $fotoUrl = $ingreso['foto_paciente_url'] ?? '';
                    $fotoAbsoluta = !empty($fotoUrl) ? BASE_DIR . '/' . ltrim($fotoUrl, '/\\') : '';
                    $tieneFoto = !empty($fotoUrl) && (file_exists($fotoAbsoluta) || file_exists(BASE_DIR . '/' . $fotoUrl) || str_starts_with($fotoUrl, 'data:image'));
                ?>
                <?php if ($tieneFoto): ?>
                    <div class="firma-box">
                        <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto Paciente" class="firma-img mb-1 rounded border shadow-sm" style="max-height: 130px; object-fit: contain;">
                        <div class="border-top pt-1 fw-bold small text-dark">REGISTRO FOTOGRÁFICO DE RECEPCIÓN</div>
                        <small class="text-muted"><i class="fa-solid fa-camera me-1"></i> Foto registrada en ventanilla</small>
                    </div>
                <?php else: ?>
                    <div class="border-top pt-3 text-muted small" style="max-width: 300px; margin: 0 auto;">
                        <div class="fw-bold text-dark">FIRMA Y SELLO DE FARMACIA</div>
                        <div>Servicio Farmacéutico / Entrega</div>
                        <div><?= htmlspecialchars($config['razon_social']) ?></div>
                        <div class="mt-2 text-muted small">Copia expediente físico / digital</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ANEXO 1: PDF(S) DE TRANSCRIPCIÓN DE ORDEN MÉDICA -->
        <?php 
            $archivosTransActa = !empty($ingreso['transcripciones_archivos']) ? $ingreso['transcripciones_archivos'] : [];
            if (empty($archivosTransActa) && !empty($ingreso['pdf_transcripcion_url'])) {
                $archivosTransActa = [['url' => $ingreso['pdf_transcripcion_url'], 'nombre' => 'Orden Médica Transcrita']];
            }
        ?>
        <?php if (!empty($archivosTransActa)): ?>
            <?php foreach ($archivosTransActa as $idxT => $tDoc): ?>
                <?php if (file_exists(BASE_DIR . '/' . $tDoc['url'])): ?>
                <div class="pdf-embed-container mt-5 border-top pt-4">
                    <h5 class="fw-bold text-primary mb-3">
                        <i class="fa-solid fa-file-pdf text-danger me-2"></i> ANEXO 1<?= count($archivosTransActa) > 1 ? '.' . ($idxT + 1) : '' ?>: <?= htmlspecialchars($tDoc['nombre'] ?? 'ORDEN MÉDICA TRANSCRITA') ?>
                    </h5>

                    <div id="pdf-transcripcion-canvas-<?= $idxT ?>" class="pdf-acta-trans-canvas text-center my-3" data-pdf-url="<?= htmlspecialchars($tDoc['url']) ?>">
                        <div class="spinner-border text-primary my-4" role="status">
                            <span class="visually-hidden">Cargando páginas de transcripción...</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ANEXO 2: PDF DE ALISTAMIENTO DE FARMACIA -->
        <?php if (!empty($ingreso['pdf_alistamiento']) && file_exists(BASE_DIR . '/' . $ingreso['pdf_alistamiento'])): ?>
        <div class="pdf-embed-container mt-5 border-top pt-4">
            <h5 class="fw-bold text-success mb-3">
                <i class="fa-solid fa-file-shield text-success me-2"></i> ANEXO 2: COMPROBANTE DE ALISTAMIENTO
            </h5>

            <div id="pdf-alistamiento-canvas-list" class="text-center my-3">
                <div class="spinner-border text-success my-4" role="status">
                    <span class="visually-hidden">Cargando páginas de alistamiento...</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ANEXO 3: PDF FACTURA / COMPROBANTE FINAL -->
        <?php if (!empty($ingreso['pdf_formula_final_url']) && file_exists(BASE_DIR . '/' . $ingreso['pdf_formula_final_url']) && strtolower(pathinfo($ingreso['pdf_formula_final_url'], PATHINFO_EXTENSION)) === 'pdf'): ?>
        <div class="pdf-embed-container mt-5 border-top pt-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i> ANEXO 3: COMPROBANTE / FACTURA TERCEROS
            </h5>

            <div id="pdf-formula-final-canvas-list" class="text-center my-3">
                <div class="spinner-border text-dark my-4" role="status">
                    <span class="visually-hidden">Cargando factura...</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                pdf.getPage(pageNum).then(function(page) {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale: scale });

                    const canvas = document.createElement('canvas');
                    canvas.className = 'pdf-page-canvas border rounded mb-3';

                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    page.render({ canvasContext: context, viewport: viewport });
                    container.appendChild(canvas);
                });
            }
        }).catch(function(err) {
            console.error("Error al renderizar PDF:", err);
            container.innerHTML = `<div class="alert alert-info">Documento adjunto: <code>${pdfUrl}</code></div>`;
        });
    }

    const transContainers = document.querySelectorAll('.pdf-acta-trans-canvas');
    transContainers.forEach(cont => {
        const url = cont.getAttribute('data-pdf-url');
        if (url) {
            renderizarPdfCompleto(url, cont.id);
        }
    });

    <?php if (!empty($ingreso['pdf_alistamiento']) && file_exists(BASE_DIR . '/' . $ingreso['pdf_alistamiento'])): ?>
        renderizarPdfCompleto('<?= $ingreso['pdf_alistamiento'] ?>', 'pdf-alistamiento-canvas-list');
    <?php endif; ?>

    <?php if (!empty($ingreso['pdf_formula_final_url']) && file_exists(BASE_DIR . '/' . $ingreso['pdf_formula_final_url']) && strtolower(pathinfo($ingreso['pdf_formula_final_url'], PATHINFO_EXTENSION)) === 'pdf'): ?>
        renderizarPdfCompleto('<?= $ingreso['pdf_formula_final_url'] ?>', 'pdf-formula-final-canvas-list');
    <?php endif; ?>
});
</script>

</body>
</html>
