<?php
require_once __DIR__ . '/../../config/app.php';
check_role('entrega');

require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/ModuloEntrega.php';

$ingresoModel = new Ingreso();
$modModel     = new ModuloEntrega();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalizar_entrega') {
    $ingreso_id           = intval($_POST['ingreso_id'] ?? 0);
    $firma_base64         = $_POST['firma_base64'] ?? '';
    $foto_paciente_base64 = $_POST['foto_paciente_base64'] ?? '';
    $foto_paciente_file   = $_FILES['foto_paciente_file'] ?? null;
    $file_formula_final   = $_FILES['pdf_formula_final'] ?? null;
    $pdf_savia_derechos   = $_FILES['pdf_savia_derechos'] ?? null;
    $pdf_savia_mipres     = $_FILES['pdf_savia_mipres'] ?? null;
    $faltantes_manuales   = trim($_POST['faltantes_alistamiento_entrega'] ?? '');

    if (!empty($firma_base64)) {
        if ($ingresoModel->procesarEntregaFinalConFormula($ingreso_id, $_SESSION['user_id'], $file_formula_final, $firma_base64, $faltantes_manuales, $foto_paciente_base64, $foto_paciente_file, $pdf_savia_derechos, $pdf_savia_mipres)) {
            registrar_log_auditoria('ENTREGA', 'REGISTRAR_ENTREGA_FIRMA', $ingreso_id, "Entrega finalizada con éxito. Firma digital, soportes Savia/Mipres y acta generada para la orden #{$ingreso_id}.");
            $mensaje = "Entrega finalizada con éxito. Se adjuntó la fórmula final y los soportes de Savia Salud / Mipres en el expediente. <a href='index.php?page=imprimir_acta&id={$ingreso_id}' target='_blank' class='btn btn-sm btn-success ms-2 fw-bold'><i class='fa-solid fa-print me-1'></i> Imprimir Acta Firmada + PDFs</a>";
        } else {
            $error = 'Ocurrió un error al guardar la entrega final.';
        }
    } else {
        $error = 'Es obligatorio capturar la firma digital del paciente.';
    }
}

// Obtener todos los registros listos para entrega en cola abierta (sin filtro de módulos)
$listaEntrega = $ingresoModel->getListaEntrega('TODOS');

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-hand-holding-medical me-2"></i> Módulo de Facturación, Entrega & Firma Digital</h4>
        <p class="text-muted small">Atención en ventanilla en cola abierta, validación de empaque, novedades de alistamiento, foto del paciente y captura de firma digital.</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small"><i class="fa-solid fa-circle-check me-1"></i> <?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card card-glass border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-rectangle me-2 text-info"></i> Cola General de Entrega & Facturación</h5>
        <span class="badge bg-info text-dark fs-6"><?= count($listaEntrega) ?> Esperando Entrega</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tiquete</th>
                        <th>Estado Cola</th>
                        <th>Paciente</th>
                        <th>EPS</th>
                        <th>Novedad / Faltantes</th>
                        <th>PDFs Adjuntos</th>
                        <th class="text-end pe-3">Gestionar Entrega</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listaEntrega)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No hay pacientes esperando en la cola general de entrega.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($listaEntrega as $row): ?>
                    <tr>
                        <td class="ps-3 fw-bold text-primary fs-5"><?= htmlspecialchars($row['ticket_numero'] ?? '') ?></td>
                        <td><span class="badge bg-primary text-white"><i class="fa-solid fa-clock me-1"></i> Cola General</span></td>
                        <td>
                            <div class="fw-bold">
                                <?= htmlspecialchars(($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? '')) ?>
                                <?= get_prioridad_badge($row['prioridad'] ?? 'NORMAL') ?>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars(($row['tipo_documento'] ?? '') . ' ' . ($row['numero_documento'] ?? '')) ?></small>
                        </td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['eps_nombre'] ?? '') ?></span></td>
                        <td>
                            <?php if (!empty($row['faltantes_alistamiento'])): ?>
                                <span class="badge bg-warning text-dark p-2 text-wrap" style="max-width: 250px;">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Con Diferencia de Cantidad
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success p-2"><i class="fa-solid fa-circle-check me-1"></i> Entrega 100% Completa</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if (!empty($row['pdf_transcripcion_url'])): ?>
                                    <a href="<?= $row['pdf_transcripcion_url'] ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF Transcripción">
                                        <i class="fa-solid fa-file-pdf me-1"></i> Transcripción
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($row['pdf_alistamiento'])): ?>
                                    <a href="<?= $row['pdf_alistamiento'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Ver PDF Alistamiento">
                                        <i class="fa-solid fa-boxes-packing me-1"></i> Alistamiento
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($row['pdf_validacion_derechos_url'])): ?>
                                    <a href="<?= $row['pdf_validacion_derechos_url'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Ver Validación Derechos Savia">
                                        <i class="fa-solid fa-file-shield me-1"></i> Derechos Savia
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($row['pdf_mipres_url'])): ?>
                                    <a href="<?= $row['pdf_mipres_url'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver Mipres">
                                        <i class="fa-solid fa-file-prescription me-1"></i> Mipres
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end pe-3">
                            <button type="button" 
                                    class="btn btn-primary fw-bold btn-abrir-firma" 
                                    data-id="<?= $row['id'] ?>"
                                    data-ticket="<?= htmlspecialchars($row['ticket_numero'] ?? '', ENT_QUOTES) ?>"
                                    data-paciente="<?= htmlspecialchars(($row['nombres'] ?? '') . ' ' . ($row['apellidos'] ?? ''), ENT_QUOTES) ?>"
                                    data-doc="<?= htmlspecialchars(($row['tipo_documento'] ?? '') . ' ' . ($row['numero_documento'] ?? ''), ENT_QUOTES) ?>"
                                    <?php $pdfTranscripcionUrl = !empty($row['pdf_transcripcion_url']) ? $row['pdf_transcripcion_url'] : ($row['pdf_documento_ingreso'] ?? ''); ?>
                                    data-transcripcion="<?= htmlspecialchars($row['transcripcion_texto'] ?? '', ENT_QUOTES) ?>"
                                    data-pdf-transcripcion="<?= htmlspecialchars($pdfTranscripcionUrl, ENT_QUOTES) ?>"
                                    data-pdf-alistamiento="<?= htmlspecialchars($row['pdf_alistamiento'] ?? '', ENT_QUOTES) ?>">
                                <i class="fa-solid fa-signature me-1"></i> Entregar & Capturar Firma
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Firma Digital Táctil & Foto Paciente -->
<div class="modal fade" id="modalFirmaDigital" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card-glass">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-signature me-2 text-warning"></i> Entrega, Firma Digital & Registro Fotográfico del Paciente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="formEntregaFirma">
                <input type="hidden" name="action" value="finalizar_entrega">
                <input type="hidden" name="ingreso_id" id="entrega_ingreso_id">
                <input type="hidden" name="firma_base64" id="firma_base64">
                <input type="hidden" name="foto_paciente_base64" id="foto_paciente_base64">

                <div class="modal-body">
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <span class="text-muted small">Tiquete de Atención:</span>
                                <div class="fw-bold text-primary fs-5" id="entrega_ticket_txt">-</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small">Paciente Recepcionado:</span>
                                <div class="fw-bold text-dark fs-5" id="entrega_paciente_txt">-</div>
                                <small class="text-muted" id="entrega_doc_txt"></small>
                            </div>
                        </div>
                    </div>

                    <!-- Carga de Fórmula Final de Software de Terceros & Comparación IA de Faltantes -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <label class="form-label fw-bold text-dark mb-1">
                            <i class="fa-solid fa-file-invoice-dollar text-success me-1"></i> Adjuntar Comprobante / Factura de Entrega (Terceros):
                        </label>
                        <input type="file" name="pdf_formula_final" id="pdf_formula_final" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png">

                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-info fw-bold" onclick="ejecutarComparacionFaltantesIA()">
                                <i class="fa-solid fa-robot me-1"></i> Comparar Fórmulas con IA (Extraer Faltantes)
                            </button>
                        </div>

                        <label class="form-label fw-bold text-dark small mb-1">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Faltantes Detectados / Cantidades Parciales:
                        </label>
                        <textarea name="faltantes_alistamiento_entrega" id="faltantes_alistamiento_entrega" class="form-control form-control-sm font-monospace" rows="3" placeholder="Si hay medicamentos no entregados o entregados en menor cantidad, indíquelos aquí..."></textarea>
                    </div>

                    <!-- Soportes Savia Salud: Validación de Derechos y Mipres -->
                    <div class="p-3 bg-light rounded border mb-3 border-info">
                        <div class="fw-bold text-dark mb-2">
                            <i class="fa-solid fa-folder-open text-primary me-1"></i> Soportes Savia Salud & Mipres (Opcional):
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-file-shield text-info me-1"></i> Validación de Derechos de Savia (PDF):
                                </label>
                                <input type="file" name="pdf_savia_derechos" id="pdf_savia_derechos" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text text-muted" style="font-size: 0.75rem;">Archivo de comprobación de derechos activo de Savia.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <i class="fa-solid fa-file-prescription text-primary me-1"></i> Mipres (PDF):
                                </label>
                                <input type="file" name="pdf_savia_mipres" id="pdf_savia_mipres" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text text-muted" style="font-size: 0.75rem;">Prescripción / Dirección Mipres en formato PDF.</div>
                            </div>
                        </div>
                        <div class="mt-2 text-muted small">
                            <i class="fa-solid fa-circle-info text-info me-1"></i> Los archivos se guardarán automáticamente en la subcarpeta <code>soportes Savia</code> del expediente del paciente.
                        </div>
                    </div>

                    <!-- Captura Fotográfica del Paciente -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <label class="form-label fw-bold text-dark mb-2">
                            <i class="fa-solid fa-camera text-primary me-1"></i> Captura o Subida de Foto del Paciente (Opcional):
                        </label>
                        <div class="row align-items-center">
                            <div class="col-md-6 text-center">
                                <video id="video-camara-paciente" class="img-fluid rounded border bg-dark mb-2 d-none" style="max-height: 160px; width: 100%; object-fit: cover;" autoplay playsinline></video>
                                <canvas id="canvas-foto-paciente" class="img-fluid rounded border d-none" style="max-height: 160px;"></canvas>
                                <div id="foto-paciente-preview-placeholder" class="p-3 bg-white rounded border text-muted small text-center">
                                    <i class="fa-solid fa-user-shield fs-2 d-block mb-1 text-secondary"></i>
                                    Sin foto capturada
                                </div>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2 fw-bold" id="btnIniciarCamaraPaciente">
                                    <i class="fa-solid fa-video me-1"></i> Activar Cámara Web
                                </button>
                                <button type="button" class="btn btn-warning btn-sm w-100 mb-2 fw-bold text-dark d-none" id="btnTomarFotoPaciente">
                                    <i class="fa-solid fa-camera me-1"></i> 📸 Capturar Foto
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-2 d-none" id="btnRepetirFotoPaciente">
                                    <i class="fa-solid fa-rotate-right me-1"></i> Repetir Foto
                                </button>

                                <div class="mt-2 border-top pt-2">
                                    <label class="form-label small fw-semibold text-muted mb-1"><i class="fa-solid fa-upload me-1"></i> O seleccionar foto desde archivo:</label>
                                    <input type="file" name="foto_paciente_file" class="form-control form-control-sm" accept="image/*">
                                </div>
                            </div>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Firme en el recuadro inferior (Tableta digitalizadora / Pantalla táctil / Mouse):</label>
                    
                    <div class="signature-container text-center mb-2">
                        <canvas id="canvas-firma" class="signature-pad"></canvas>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnLimpiarFirma">
                            <i class="fa-solid fa-eraser me-1"></i> Borrar / Limpiar Firma
                        </button>
                        <span class="small text-muted"><i class="fa-solid fa-tablet-screen-button me-1"></i> Compatible con Wacom, Topaz, iPad y pantallas táctiles</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="fa-solid fa-circle-check me-1"></i> Confirmar Entrega y Generar Acta
                    </button>
                </div>
            </form>
        </div>
    </div>
<!-- PDF.js Engine para Extracción de Texto de Fórmulas y Comprobantes -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
    }
</script>

<script>
let canvas, ctx, isDrawing = false;
let videoStreamPaciente = null;

document.addEventListener('DOMContentLoaded', () => {
    canvas = document.getElementById('canvas-firma');
    ctx = canvas.getContext('2d');

    // Ajustar resolución del canvas
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;

    ctx.strokeStyle = "#000000";
    ctx.lineWidth = 3;
    ctx.lineCap = "round";

    // Eventos Mouse
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    // Eventos Touch (Tabletas táctiles / Celulares / iPad)
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDrawing(e.touches[0]); });
    canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e.touches[0]); });
    canvas.addEventListener('touchend', stopDrawing);

    document.getElementById('btnLimpiarFirma').addEventListener('click', limpiarCanvas);

    // Lógica de Cámara Web Paciente
    document.getElementById('btnIniciarCamaraPaciente').addEventListener('click', iniciarCamaraPaciente);
    document.getElementById('btnTomarFotoPaciente').addEventListener('click', tomarFotoPaciente);
    document.getElementById('btnRepetirFotoPaciente').addEventListener('click', repetirFotoPaciente);

    document.getElementById('formEntregaFirma').addEventListener('submit', (e) => {
        if (isCanvasBlank(canvas)) {
            alert('Por favor solicite al paciente realizar la firma en la pantalla antes de finalizar.');
            e.preventDefault();
            return;
        }
        document.getElementById('firma_base64').value = canvas.toDataURL('image/png');
        detenerCamaraPaciente();
    });

    // Delegación de eventos para el botón de entrega
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-abrir-firma');
        if (btn) {
            const id = btn.getAttribute('data-id');
            const ticket = btn.getAttribute('data-ticket');
            const paciente = btn.getAttribute('data-paciente');
            const doc = btn.getAttribute('data-doc');
            const faltantes = btn.getAttribute('data-faltantes');
            const transcripcion = btn.getAttribute('data-transcripcion');
            const pdfTranscripcion = btn.getAttribute('data-pdf-transcripcion');
            const pdfAlistamiento = btn.getAttribute('data-pdf-alistamiento');

            abrirModalFirma(id, ticket, paciente, doc, faltantes, transcripcion, pdfTranscripcion, pdfAlistamiento);
        }
    });
});

async function iniciarCamaraPaciente() {
    try {
        videoStreamPaciente = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
        const video = document.getElementById('video-camara-paciente');
        video.srcObject = videoStreamPaciente;
        video.classList.remove('d-none');
        document.getElementById('foto-paciente-preview-placeholder').classList.add('d-none');
        document.getElementById('canvas-foto-paciente').classList.add('d-none');
        document.getElementById('btnIniciarCamaraPaciente').classList.add('d-none');
        document.getElementById('btnTomarFotoPaciente').classList.remove('d-none');
    } catch (e) {
        alert("No se pudo acceder a la cámara web. Verifique los permisos en el navegador.");
    }
}

function tomarFotoPaciente() {
    const video = document.getElementById('video-camara-paciente');
    const canvasFoto = document.getElementById('canvas-foto-paciente');
    const ctxFoto = canvasFoto.getContext('2d');

    canvasFoto.width = video.videoWidth || 640;
    canvasFoto.height = video.videoHeight || 480;
    ctxFoto.drawImage(video, 0, 0, canvasFoto.width, canvasFoto.height);

    const dataUrl = canvasFoto.toDataURL('image/jpeg', 0.85);
    document.getElementById('foto_paciente_base64').value = dataUrl;

    canvasFoto.classList.remove('d-none');
    video.classList.add('d-none');
    document.getElementById('btnTomarFotoPaciente').classList.add('d-none');
    document.getElementById('btnRepetirFotoPaciente').classList.remove('d-none');

    detenerCamaraPaciente();
}

function repetirFotoPaciente() {
    document.getElementById('foto_paciente_base64').value = '';
    document.getElementById('btnRepetirFotoPaciente').classList.add('d-none');
    iniciarCamaraPaciente();
}

function detenerCamaraPaciente() {
    if (videoStreamPaciente) {
        videoStreamPaciente.getTracks().forEach(track => track.stop());
        videoStreamPaciente = null;
    }
}

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
}

function startDrawing(e) {
    isDrawing = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
}

function draw(e) {
    if (!isDrawing) return;
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
}

function stopDrawing() {
    isDrawing = false;
}

function limpiarCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function isCanvasBlank(c) {
    const blank = document.createElement('canvas');
    blank.width = c.width;
    blank.height = c.height;
    return c.toDataURL() === blank.toDataURL();
}

let currentOrderTranscripcionTexto = '';
let currentOrderTicket = '';
let currentOrderPdfTranscripcion = '';
let currentOrderPdfAlistamiento = '';

function abrirModalFirma(id, ticket, paciente, doc, faltantes, transcripcion, pdfTranscripcion, pdfAlistamiento) {
    document.getElementById('entrega_ingreso_id').value = id;
    document.getElementById('entrega_ticket_txt').innerText = ticket;
    document.getElementById('entrega_paciente_txt').innerText = paciente;
    document.getElementById('entrega_doc_txt').innerText = doc;

    currentOrderTranscripcionTexto = transcripcion || '';
    currentOrderTicket = ticket || '';
    currentOrderPdfTranscripcion = pdfTranscripcion || '';
    currentOrderPdfAlistamiento = pdfAlistamiento || '';

    const divFaltantes = document.getElementById('container-novedades-entrega');
    const txtFaltantes = document.getElementById('entrega_faltantes_txt');
    if (divFaltantes && txtFaltantes) {
        if (faltantes && faltantes.trim()) {
            divFaltantes.classList.remove('d-none');
            txtFaltantes.innerText = faltantes;
        } else {
            divFaltantes.classList.add('d-none');
            txtFaltantes.innerText = '';
        }
    }

    limpiarCanvas();
    const txtFaltantesEntrega = document.getElementById('faltantes_alistamiento_entrega');
    if (txtFaltantesEntrega) txtFaltantesEntrega.value = faltantes || '';

    const modal = new bootstrap.Modal(document.getElementById('modalFirmaDigital'));
    modal.show();

    setTimeout(() => {
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        ctx.strokeStyle = "#000000";
        ctx.lineWidth = 3;
        ctx.lineCap = "round";
    }, 300);
}

async function extraerTextoPDF(fileOrUrl) {
    if (typeof pdfjsLib === 'undefined') return '';
    try {
        let arrayBuffer;
        if (fileOrUrl instanceof File) {
            arrayBuffer = await fileOrUrl.arrayBuffer();
        } else if (typeof fileOrUrl === 'string' && fileOrUrl.trim().length > 0) {
            let url = fileOrUrl.trim();
            let resp = await fetch(url);
            if (!resp.ok && !url.startsWith('http') && !url.startsWith('/')) {
                resp = await fetch('/' + url);
            }
            if (!resp.ok) {
                console.warn("No se pudo descargar PDF desde la URL:", url);
                return '';
            }
            arrayBuffer = await resp.arrayBuffer();
        } else {
            return '';
        }

        const pdfDoc = await pdfjsLib.getDocument({ data: new Uint8Array(arrayBuffer) }).promise;
        let fullText = '';
        for (let i = 1; i <= pdfDoc.numPages; i++) {
            const page = await pdfDoc.getPage(i);
            const content = await page.getTextContent();
            let lastY = null;
            let pageText = '';

            for (const item of content.items) {
                if (lastY !== null && Math.abs(item.transform[5] - lastY) > 4) {
                    pageText += '\n';
                } else if (item.hasEOL) {
                    pageText += '\n';
                } else {
                    pageText += ' ';
                }
                pageText += item.str;
                lastY = item.transform[5];
            }
            fullText += '\n' + pageText;
        }
        return fullText;
    } catch (e) {
        console.warn("No se pudo extraer texto del PDF:", e);
        return '';
    }
}

function extraerCantidadPrescrita(textoBloque) {
    if (!textoBloque) return 30;
    const str = textoBloque.toString();

    // 1. "Equivale a X unidades" o "Equivale a X"
    const mEquivale = str.match(/equivale\s+a\s*(\d+)/i);
    if (mEquivale) {
        return parseInt(mEquivale[1], 10);
    }

    // 2. Columna o etiqueta "Cantidad: X" o "Cant: X" o "X unidades"
    const mCant = str.match(/(?:cantidad|cant\.?|unidades)\s*[:=\s]*(\d+)\b/i);
    if (mCant) {
        return parseInt(mCant[1], 10);
    }

    // 3. Buscar número al final del bloque que no sea parte de días ni horas
    const sinDiasHoras = str.replace(/(?:por|cada)\s*\d+\s*(?:días|dias|horas|hora|meses|mes)/gi, '');
    const matchEnd = sinDiasHoras.match(/(\d+)\s*$/);
    if (matchEnd) {
        const val = parseInt(matchEnd[1], 10);
        if (val > 0 && val <= 500) {
            return val;
        }
    }

    return 30;
}

function extraerCantidadDispensada(textoBloque) {
    if (!textoBloque) return 30;
    const str = textoBloque.toString();

    // 1. Coincidencia EXACTA por columnas del Acta de Entrega:
    // Fecha de Vencimiento (DD/MM/YYYY) + Unidad de Medida (MG/MCG/etc.) + Cantidad Dispensada (Cant.Disp.)
    const matchColumnasActa = str.match(/\b\d{1,2}\/\d{1,2}\/\d{2,4}\s+[A-Z\.]+\s+(\d+)\b/i);
    if (matchColumnasActa) {
        return parseInt(matchColumnasActa[1], 10);
    }

    // 2. Unidad de Medida seguida de Cantidad al final de línea
    const matchUmed = str.match(/\b(?:MG|MCG|ML|G|L|TABLETA|CAPSULA|RECUBIERTA|UNID|U)\s+(\d+)\s*(?:\n|$)/i);
    if (matchUmed) {
        return parseInt(matchUmed[1], 10);
    }

    // 3. Etiqueta explícita Cant.Disp
    const matchCantDisp = str.match(/(?:cant\.?disp\.?|entregado|despachado)\s*[:=\s]*(\d+)\b/i);
    if (matchCantDisp) {
        return parseInt(matchCantDisp[1], 10);
    }

    // 4. Último número entero del bloque descartando lotes (> 1000) y frecuencias
    const sinFrecuencia = str.replace(/Frecuencia:.*$/gim, '').trim();
    const matchUltimoNumero = sinFrecuencia.match(/(\d+)\s*$/);
    if (matchUltimoNumero) {
        const val = parseInt(matchUltimoNumero[1], 10);
        if (val > 0 && val <= 1000) {
            return val;
        }
    }

    return 30;
}

function parsearMedicamentosConCantidades(texto) {
    if (!texto || texto.trim().length === 0) return [];

    const resultados = [];
    // Unificar saltos de línea a espacios para procesamiento continuo
    const textoContinuo = texto.replace(/\r\n/g, ' ').replace(/[\r\n]+/g, ' ');

    // 1. Capturar bloques que comiencen con código MX (ej: MX470, MX358, MX874, MX86, MX24)
    const regexMX = /\b(MX\d+)\s+([\s\S]+?)(?=\bMX\d+\b|SEDE\s+LA\s+30|LUIS\s+CARLOS|$)/gi;
    let match;

    while ((match = regexMX.exec(textoContinuo)) !== null) {
        const codigo = match[1].toUpperCase();
        const cuerpo = match[2].trim();

        // Extraer cantidad prescrita: "Equivale a X unidades" o "Equivale a X"
        let cantidad = 30;
        const mEquivale = cuerpo.match(/equivale\s+a\s*(\d+)/i);
        if (mEquivale) {
            cantidad = parseInt(mEquivale[1], 10);
        } else {
            const mCant = cuerpo.match(/(?:cantidad|cant\.?|unidades)\s*[:=\s]*(\d+)\b/i);
            if (mCant) {
                cantidad = parseInt(mCant[1], 10);
            }
        }

        // Extraer nombre del medicamento
        let nombre = cuerpo.replace(/\s*(?:suministrar|equivale|tomar|cada|por|días|observacion).*$/i, '').trim();
        nombre = nombre.replace(/^[\d\.\-\•\*\>\s]+/, '').trim();

        if (nombre.length > 3) {
            resultados.push({
                codigo: codigo,
                cuerpo: cuerpo,
                nombre: nombre,
                cantidadPrescrita: cantidad
            });
        }
    }

    // 2. Si no se detectó código MX, procesar por palabras clave de fármacos
    if (resultados.length === 0) {
        const lineas = texto.split(/[\r\n]+/).map(l => l.trim()).filter(l => l.length > 3);
        lineas.forEach(linea => {
            const u = linea.toUpperCase();
            if (u.startsWith('DATOS') || u.startsWith('SEDE') || u.startsWith('PACIENTE') || u.startsWith('DIAGNOSTIC') || u.startsWith('MEDICAMENTOS') || u.startsWith('CÓDIGO') || u.startsWith('CLIENTE') || u.startsWith('FECHA')) {
                return;
            }

            const tieneFarmaco = u.includes('MG') || u.includes('MCG') || u.includes('ML') || u.includes('TABLET') || u.includes('CAPSUL') || u.includes('PREDNISOLONA') || u.includes('LEVOTIROXINA') || u.includes('HIDROXICLOROQUINA') || u.includes('AZATIOPRINA') || u.includes('ACETILSALICILICO');

            if (tieneFarmaco) {
                let cantidad = extraerCantidadPrescrita(linea);
                let nombre = linea.replace(/^\s*(?:MX\d+[\-\d]*|[\d\.\-\•\*\>]+)\s*/i, '').trim();
                nombre = nombre.replace(/\s*(?:suministrar|equivale|tomar|cada|por|días|observacion).*$/i, '').trim();

                if (nombre.length > 3) {
                    resultados.push({
                        codigo: '',
                        cuerpo: linea,
                        nombre: nombre,
                        cantidadPrescrita: cantidad
                    });
                }
            }
        });
    }

    return resultados;
}

function parsearEntregaTerceros(texto) {
    if (!texto || texto.trim().length === 0) return [];

    const items = [];
    const textoContinuo = texto.replace(/\r\n/g, ' ').replace(/[\r\n]+/g, ' ');

    const regexMX = /\b(MX\d+(?:\-\d+)?)\s+([\s\S]+?)(?=\bMX\d+(?:\-\d+)?\b|RESUMEN:|Observaciones:|$)/gi;
    let match;

    while ((match = regexMX.exec(textoContinuo)) !== null) {
        const codigoCompleto = match[1].toUpperCase();
        const cuerpo = match[2].trim();

        // 1. Extraer cantidad dispensada por la columna del acta (Fecha DD/MM/YYYY + Unidad + Cantidad)
        let cantidad = 30;
        const matchColumna = cuerpo.match(/\b\d{1,2}\/\d{1,2}\/\d{2,4}\s+[A-Z\.]+\s+(\d+)\b/i);
        if (matchColumna) {
            cantidad = parseInt(matchColumna[1], 10);
        } else {
            const matchUmed = cuerpo.match(/\b(?:MG|MCG|ML|G|L|TABLETA|CAPSULA|RECUBIERTA|UNID|U)\s+(\d+)\b/i);
            if (matchUmed) {
                cantidad = parseInt(matchUmed[1], 10);
            }
        }

        const codigoBase = codigoCompleto.split('-')[0];

        items.push({
            codigo: codigoCompleto,
            codigoBase: codigoBase,
            cuerpo: cuerpo,
            cantidadDispensada: cantidad
        });
    }

    return items;
}

async function ejecutarComparacionFaltantesIA() {
    const fileInput = document.getElementById('pdf_formula_final');
    const txtArea = document.getElementById('faltantes_alistamiento_entrega');

    if (!fileInput || fileInput.files.length === 0) {
        alert("Por favor seleccione primero el archivo PDF del Comprobante / Factura de Entrega para realizar la comparación inteligente.");
        return;
    }

    if (typeof pdfjsLib === 'undefined') {
        alert("El motor de análisis PDF no ha terminado de cargar. Por favor verifique su conexión a internet e intente nuevamente.");
        return;
    }

    const timestamp = new Date().toLocaleTimeString();
    txtArea.value = `🤖 [IA EN PROCESO] Analizando orden ${currentOrderTicket}...\nExtrayendo texto de la Fórmula Médica prescrita y del comprobante de entrega...`;

    try {
        const fileSubido = fileInput.files[0];

        // 1. Obtener texto de la transcripción original (Fórmula Médica) desde el servidor
        let textoTranscripcion = '';
        if (currentOrderPdfTranscripcion) {
            textoTranscripcion = await extraerTextoPDF(currentOrderPdfTranscripcion);
        }
        if ((!textoTranscripcion || textoTranscripcion.trim().length < 10) && currentOrderPdfAlistamiento) {
            textoTranscripcion = await extraerTextoPDF(currentOrderPdfAlistamiento);
        }
        if (!textoTranscripcion || textoTranscripcion.trim().length < 10) {
            textoTranscripcion = currentOrderTranscripcionTexto || '';
        }

        // 2. Obtener texto del comprobante de entrega subido (Acta/Factura)
        let textoTerceros = await extraerTextoPDF(fileSubido);

        if (!textoTranscripcion || textoTranscripcion.trim().length < 5) {
            alert("⚠️ No se encontró la Fórmula Médica prescrita original de esta orden en el servidor. Verifique que se haya cargado el soporte en Ingreso o Transcripción.");
            txtArea.value = `⚠ [AVISO] No se encontró el soporte PDF original de la Fórmula Médica prescrita en el servidor.`;
            return;
        }

        if (!textoTerceros || textoTerceros.trim().length < 5) {
            alert("⚠️ No se pudo extraer texto digital del archivo PDF subido de entrega. Asegúrese de que el PDF contenga texto seleccionable.");
            txtArea.value = `⚠ [AVISO] No se pudo extraer texto del comprobante subido. Novedades registradas manualmente:`;
            return;
        }

        // Parsear medicamentos prescritos y medicamentos entregados con flujo continuo
        const medsPrescritos = parsearMedicamentosConCantidades(textoTranscripcion);
        const medsEntregados = parsearEntregaTerceros(textoTerceros);

        let novedades = [];
        let completosCount = 0;

        if (medsPrescritos.length > 0) {
            medsPrescritos.forEach((medPrescrito) => {
                // 1. Buscar en medsEntregados por código base (ej: MX874 coincide con MX874-1)
                let entregado = medsEntregados.find(e => e.codigoBase === medPrescrito.codigo || e.codigo.startsWith(medPrescrito.codigo));

                // 2. Si no coincide por código, buscar por palabras clave
                if (!entregado) {
                    const palabras = medPrescrito.nombre.toUpperCase().split(/\s+/).filter(w => w.length >= 4);
                    entregado = medsEntregados.find(e => {
                        const cuerpoU = e.cuerpo.toUpperCase();
                        return palabras.some(p => cuerpoU.includes(p));
                    });
                }

                if (!entregado) {
                    novedades.push(`${novedades.length + 1}. [FALTANTE TOTAL] ${medPrescrito.nombre}\n   - Prescrito: ${medPrescrito.cantidadPrescrita} unidad(es)\n   - Entregado en Comprobante: 0 unidad(es)\n   - Estado: Medicamento NO incluido en el comprobante de entrega.`);
                } else {
                    const cantPresc = medPrescrito.cantidadPrescrita;
                    const cantEntreg = entregado.cantidadDispensada;

                    if (cantEntreg !== cantPresc) {
                        const dif = Math.abs(cantPresc - cantEntreg);
                        if (cantEntreg < cantPresc) {
                            novedades.push(`${novedades.length + 1}. [DIFERENCIA DE CANTIDAD] ${medPrescrito.nombre}\n   - Prescrito: ${cantPresc} unidad(es)\n   - Entregado en Comprobante: ${cantEntreg} unidad(es)\n   - Diferencia Faltante: ${dif} unidad(es) pendientes por entregar.`);
                        } else {
                            novedades.push(`${novedades.length + 1}. [EXCEDENTE / DIFERENCIA] ${medPrescrito.nombre}\n   - Prescrito: ${cantPresc} unidad(es)\n   - Entregado en Comprobante: ${cantEntreg} unidad(es)\n   - Diferencia: ${dif} unidad(es) adicionales entregadas.`);
                        }
                    } else {
                        completosCount++;
                    }
                }
            });
        } else {
            alert("⚠️ No se detectaron líneas de medicamentos prescritos en la Fórmula Médica del servidor. Verifique el documento cargado en la transcripción.");
            return;
        }

        if (novedades.length > 0) {
            let reporte = `[REPORTE DE AUDITORÍA Y NOVEDADES IA - ORDEN ${currentOrderTicket} - ${timestamp}]\n\n`;
            reporte += `⚠ DIFERENCIAS Y MEDICAMENTOS FALTANTES DETECTADOS:\n\n`;
            reporte += novedades.join('\n\n') + '\n\n';
            reporte += `──────────────────────────────────────────────\n`;
            reporte += `• Resumen Auditoría IA: ${completosCount} de ${medsPrescritos.length} medicamento(s) verificados al 100%. Novedades registradas: ${novedades.length}.`;

            txtArea.value = reporte;
            alert(`⚠️ ¡Se detectaron ${novedades.length} novedad(es) de cantidades/medicamentos faltantes en la orden ${currentOrderTicket}! Revise el detalle en el recuadro.`);
        } else {
            txtArea.value = '';
            alert(`✔ ¡VERIFICACIÓN EXITOSA para la orden ${currentOrderTicket}! Todos los medicamentos y cantidades coinciden al 100%. No hay novedades registradas.`);
        }

    } catch (e) {
        console.error("Error comparativo IA:", e);
        alert("Ocurrió un error al procesar la comparación de los archivos PDF: " + e.message);
    }
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
