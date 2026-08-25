<?php
require_once __DIR__ . '/../../config/app.php';
check_role('transcripcion');

require_once __DIR__ . '/../../models/Ingreso.php';
$ingresoModel = new Ingreso();

$esAdmin = (($_SESSION['rol_nombre'] ?? '') === 'Administrador');
$mensaje = '';
$error = '';

// AJAX Endpoint: Obtener lista de trabajo en JSON para autorefresco silencioso
if (isset($_GET['ajax_get_list'])) {
    header('Content-Type: application/json');
    $lista = $ingresoModel->getListaTranscripcion();
    echo json_encode([
        'status' => 'ok',
        'user_id' => $_SESSION['user_id'],
        'es_admin' => $esAdmin,
        'data' => $lista
    ]);
    exit;
}

// AJAX Endpoint: Obtener detalles del ingreso
if (isset($_GET['ajax_get_detail']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $detalles = $ingresoModel->getById($_GET['id']);
    echo json_encode($detalles ?: []);
    exit;
}

// AJAX Endpoint: Obtener historial clínico del paciente
if (isset($_GET['ajax_get_historial']) && (isset($_GET['paciente_id']) || isset($_GET['numero_documento']) || isset($_GET['ingreso_id']))) {
    header('Content-Type: application/json');
    $paciente_id      = intval($_GET['paciente_id'] ?? 0);
    $ingreso_id       = intval($_GET['ingreso_id'] ?? 0);
    $numero_documento = trim($_GET['numero_documento'] ?? '');
    
    if ($ingreso_id > 0) {
        $ing = $ingresoModel->getById($ingreso_id);
        if ($ing) {
            $paciente_id = intval($ing['paciente_id'] ?? 0);
            if (empty($numero_documento)) {
                $numero_documento = trim($ing['numero_documento'] ?? '');
            }
        }
    }

    $historial = $ingresoModel->getHistorialPacienteByDocumentoOrPacienteId($paciente_id, $numero_documento);
    echo json_encode([
        'status' => 'ok',
        'paciente_id' => $paciente_id,
        'numero_documento' => $numero_documento,
        'data' => $historial
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_transcripcion') {
    $ingreso_id = intval($_POST['ingreso_id']);
    $pdf_file = $_FILES['pdf_transcripcion'] ?? null;

    if ($ingreso_id > 0) {
        $exito = $ingresoModel->guardarTranscripcion($ingreso_id, $pdf_file, $_SESSION['user_id']);
        if ($exito) {
            registrar_log_auditoria('TRANSCRIPCION', 'GUARDAR_TRANSCRIPCION', $ingreso_id, "Transcripción completada con PDF e inventario verificado para la orden #{$ingreso_id}.");
            $mensaje = 'Transcripción completada con éxito. La orden pasó a Monitoreo y Verificación.';
        } else {
            $error = 'Error al guardar la transcripción.';
        }
    }
}

$listaTrabajo = $ingresoModel->getListaTranscripcion();

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    /* Jerarquía estricta de z-index para modales superpuestos */
    #modalGestionarTranscripcion {
        z-index: 1055 !important;
    }
    #modalHistorialPaciente {
        z-index: 1075 !important;
    }
    #modalVisorPDF {
        z-index: 1099 !important;
    }
</style>

<!-- Librería PDF.js para Lectura y Validación Automática de Texto en el PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>

<div class="row justify-content-center">
    <div class="col-lg-12">
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show small"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card card-glass border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i> Lista de Trabajo de Transcripción</h5>
                <span class="badge bg-primary fs-6" id="badge-total-cola"><?= count($listaTrabajo) ?> En Cola</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tiquete</th>
                                <th>Hora Ingreso</th>
                                <th>Paciente</th>
                                <th>EPS</th>
                                <th>Estado Actual</th>
                                <th>Estado Bloqueo</th>
                                <th class="text-end pe-3">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-transcripcion-body">
                            <?php if (empty($listaTrabajo)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No hay órdenes pendientes en la lista de transcripción.</td></tr>
                            <?php endif; ?>

                            <?php foreach ($listaTrabajo as $row): ?>
                            <?php 
                                $detalles = $ingresoModel->getById($row['id']);
                                $isLockedByMe = (!empty($row['locked_by_user_id']) && $row['locked_by_user_id'] == $_SESSION['user_id']);
                                $isLockedByOther = (!empty($row['locked_by_user_id']) && $row['locked_by_user_id'] != $_SESSION['user_id']);
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($row['ticket_numero']) ?></td>
                                <td><?= date('h:i A', strtotime($row['fecha_ingreso'])) ?></td>
                                <td>
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?>
                                        <?= get_prioridad_badge($row['prioridad'] ?? 'NORMAL') ?>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($row['tipo_documento'] . ' ' . $row['numero_documento']) ?></small>
                                </td>
                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['eps_nombre']) ?></span></td>
                                <td><?= get_estado_badge($row['estado_tramite']) ?></td>
                                <td>
                                    <?php if ($isLockedByOther): ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Bloqueado por: <?= htmlspecialchars($row['locked_by_nombre']) ?></span>
                                    <?php elseif ($isLockedByMe): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-user-gear me-1"></i> En gestión por ti</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-lock-open me-1"></i> Disponible</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if ($isLockedByOther): ?>
                                            <button class="btn btn-sm btn-secondary" disabled><i class="fa-solid fa-lock me-1"></i> Ocupado</button>
                                            <?php if ($esAdmin): ?>
                                                <button class="btn btn-sm btn-outline-danger" title="Forzar Desbloqueo (Admin)" onclick="forzarDesbloqueo(<?= $row['id'] ?>)">
                                                    <i class="fa-solid fa-key"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($isLockedByMe): ?>
                                            <button class="btn btn-sm btn-warning fw-bold text-dark" onclick="gestionarTranscripcion(<?= $row['id'] ?>)">
                                                <i class="fa-solid fa-folder-open me-1"></i> Continuar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary fw-bold" onclick="gestionarTranscripcion(<?= $row['id'] ?>)">
                                                <i class="fa-solid fa-keyboard me-1"></i> Transcribir
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-info text-dark fw-semibold ms-1" title="Ver Historial de Atenciones del Paciente" onclick="abrirHistorialPacienteModal(<?= $row['paciente_id'] ?>, '<?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['numero_documento'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Historial
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal para Gestionar Transcripción de Orden -->
<div class="modal fade" id="modalGestionarTranscripcion" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-keyboard me-2"></i> Gestión y Transcripción de Orden Médica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="liberarBloqueoActual()"></button>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="formTranscripcion">
                <input type="hidden" name="action" value="guardar_transcripcion">
                <input type="hidden" name="ingreso_id" id="modal_ingreso_id">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="text-muted small fw-semibold">DATOS DEL PACIENTE EN SISPAM</div>
                                    <div class="fw-bold fs-5 text-dark" id="modal_paciente_nombre">-</div>
                                    <div class="small text-primary fw-bold" id="modal_paciente_doc">-</div>
                                    <div class="badge bg-info text-dark mt-1" id="modal_paciente_eps">-</div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100 mt-2 shadow-sm" id="btn_abrir_historial_modal" onclick="abrirHistorialPacienteModal(currentPacienteId, currentPacienteNombreCompleto, currentPacienteDocNumero)">
                                    <i class="fa-solid fa-clock-rotate-left me-1 text-primary"></i> 📜 Historial de Atenciones Previas (<span id="count_historial_badge">0</span>)
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded border h-100">
                                <div class="text-muted small fw-semibold mb-2">DOCUMENTOS INGRESADOS</div>
                                <div id="modal_documentos_iconos"></div>
                            </div>
                        </div>

                        <hr>

                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark">
                                <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Adjuntar PDF de Orden Transcrita (Exportado de Sistema Externo)
                            </label>
                            <input type="file" name="pdf_transcripcion" id="input_pdf_transcripcion" class="form-control form-control-lg border-primary" accept=".pdf" onchange="validarPdfTranscripcion(event)">
                            <div class="form-text">El sistema leerá el texto del PDF y verificará automáticamente si contiene la cédula/documento del paciente.</div>
                            
                            <!-- Contenedor para Estado de Validación del PDF -->
                            <div id="pdf-validation-status"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="liberarBloqueoActual()">Cancelar / Liberar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btn-submit-transcripcion">
                        <i class="fa-solid fa-paper-plane me-1"></i> Enviar a Alistamiento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Historial Clínico y Atenciones Previas del Paciente -->
<div class="modal fade" id="modalHistorialPaciente" tabindex="-1" style="z-index: 1085;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="historial_modal_title">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-info"></i> Historial Clínico y Atenciones Previas del Paciente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div id="historial_modal_loader" class="text-center py-4 d-none">
                    <div class="spinner-border text-info my-2" role="status"></div>
                    <div class="text-muted fw-semibold">Cargando atenciones previas del paciente...</div>
                </div>

                <div class="table-responsive bg-white rounded shadow-sm border">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-3"># Tiquete</th>
                                <th>Fecha & Hora</th>
                                <th>EPS</th>
                                <th>Estado de Atención</th>
                                <th>Orientador / Transcriptor</th>
                                <th class="text-end pe-3">Fórmulas & Documentos de Entrega</th>
                            </tr>
                        </thead>
                        <tbody id="historial_paciente_tbody">
                            <!-- Inyectado dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cerrar Historial</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visor PDF para ver documentos escaneados (Optimizado para Tablet) -->
<div class="modal fade" id="modalVisorPDF" tabindex="-1" style="z-index: 1090;">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw; width: 95vw;">
        <div class="modal-content shadow-lg" style="height: 92vh; display: flex; flex-direction: column; background-color: #0f172a; border: 1px solid #334155;">
            <div class="modal-header py-2 bg-dark text-white flex-shrink-0 d-flex justify-content-between align-items-center border-bottom border-secondary">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="modal-title fw-bold mb-0 text-white" id="visorPDFTitulo">
                        <i class="fa-solid fa-file-pdf text-danger me-2"></i> Visor de Documento
                    </h6>
                </div>

                <!-- Botones de Control de Zoom y Modos para Tablet -->
                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <div class="btn-group btn-group-sm me-2" role="group" id="groupZoomControls">
                        <button type="button" class="btn btn-outline-info btn-sm fw-bold active" id="btnZoomFit" onclick="cambiarZoomVisor('Fit')" title="Página Completa (Ideal para Tablet sin desplazamientos)">
                            <i class="fa-solid fa-expand me-1"></i> Página Completa
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm fw-semibold" id="btnZoomFitH" onclick="cambiarZoomVisor('FitH')" title="Ajustar al Ancho">
                            <i class="fa-solid fa-arrows-left-right me-1"></i> Ancho
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm fw-semibold" id="btnZoom100" onclick="cambiarZoomVisor('100')" title="Zoom 100%">
                            100%
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm fw-semibold" id="btnZoom75" onclick="cambiarZoomVisor('75')" title="Zoom 75%">
                            75%
                        </button>
                    </div>

                    <!-- Controles de Ajuste para Imágenes (JPG / PNG) -->
                    <div class="btn-group btn-group-sm me-2 d-none" role="group" id="groupImgControls">
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="zoomImagenVisor(0.15)" title="Acercar"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="zoomImagenVisor(-0.15)" title="Alejar"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="rotarImagenVisor()" title="Rotar 90°"><i class="fa-solid fa-rotate-right"></i> Rotar</button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="resetImagenVisor()" title="Restablecer"><i class="fa-solid fa-arrow-rotate-left"></i></button>
                    </div>

                    <a id="btnAbrirPDFFull" href="#" target="_blank" class="btn btn-sm btn-info fw-bold text-dark me-2">
                        <i class="fa-solid fa-up-right-from-square me-1"></i> Pestaña Nueva
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0 flex-grow-1 position-relative" id="containerVisorPDF" style="overflow: hidden; background-color: #1e293b;">
                <!-- PDF / Imagen inyectado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<script>
let currentIngresoIdLock = null;
let currentPacienteDocNumero = '';
let heartbeatInterval = null;
let autoRefreshInterval = null;
const currentUserId = <?= json_encode($_SESSION['user_id']) ?>;
const esAdmin = <?= json_encode($esAdmin) ?>;

let visorCurrentUrl = '';
let visorCurrentType = 'pdf';
let imgScale = 1;
let imgRotation = 0;

document.addEventListener('DOMContentLoaded', () => {
    const modalGestionEl = document.getElementById('modalGestionarTranscripcion');
    if (modalGestionEl) {
        modalGestionEl.addEventListener('hidden.bs.modal', () => {
            liberarBloqueoActual();
        });
    }

    iniciarAutoRefresco();
});

function abrirVisorPDF(url, titulo) {
    visorCurrentUrl = url.split('#')[0];
    const ext = visorCurrentUrl.split('.').pop().toLowerCase();
    
    document.getElementById('visorPDFTitulo').innerHTML = `<i class="fa-solid fa-file-contract text-info me-2"></i> ${titulo}`;
    
    const btnFull = document.getElementById('btnAbrirPDFFull');
    if (btnFull) btnFull.href = visorCurrentUrl;

    const groupZoom = document.getElementById('groupZoomControls');
    const groupImg  = document.getElementById('groupImgControls');

    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
        visorCurrentType = 'image';
        if (groupZoom) groupZoom.classList.add('d-none');
        if (groupImg)  groupImg.classList.remove('d-none');
        renderImagenVisor();
    } else {
        visorCurrentType = 'pdf';
        if (groupZoom) groupZoom.classList.remove('d-none');
        if (groupImg)  groupImg.classList.add('d-none');
        // Modo por defecto optimizado para Tablet: Fit (Página Completa en pantalla)
        renderPDFVisor('Fit');
    }

    const modalEl = document.getElementById('modalVisorPDF');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();

    setTimeout(() => {
        modalEl.style.zIndex = '1099';
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length > 0) {
            backdrops[backdrops.length - 1].style.zIndex = '1095';
        }
    }, 50);
}

function renderPDFVisor(modoZoom = 'Fit') {
    const container = document.getElementById('containerVisorPDF');
    if (!container) return;

    // Actualizar estados visuales de botones de zoom
    ['btnZoomFit', 'btnZoomFitH', 'btnZoom100', 'btnZoom75'].forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            btn.classList.remove('btn-outline-info', 'active', 'fw-bold');
            btn.classList.add('btn-outline-light', 'fw-semibold');
        }
    });

    let params = '#view=Fit&zoom=page-fit&toolbar=1&navpanes=0';
    if (modoZoom === 'FitH') {
        params = '#view=FitH&zoom=page-width&toolbar=1&navpanes=0';
        const b = document.getElementById('btnZoomFitH');
        if (b) { b.classList.remove('btn-outline-light'); b.classList.add('btn-outline-info', 'active', 'fw-bold'); }
    } else if (modoZoom === '100') {
        params = '#zoom=100&toolbar=1&navpanes=0';
        const b = document.getElementById('btnZoom100');
        if (b) { b.classList.remove('btn-outline-light'); b.classList.add('btn-outline-info', 'active', 'fw-bold'); }
    } else if (modoZoom === '75') {
        params = '#zoom=75&toolbar=1&navpanes=0';
        const b = document.getElementById('btnZoom75');
        if (b) { b.classList.remove('btn-outline-light'); b.classList.add('btn-outline-info', 'active', 'fw-bold'); }
    } else {
        const b = document.getElementById('btnZoomFit');
        if (b) { b.classList.remove('btn-outline-light'); b.classList.add('btn-outline-info', 'active', 'fw-bold'); }
    }

    const finalUrl = visorCurrentUrl + params;

    container.innerHTML = `
        <object data="${finalUrl}" type="application/pdf" width="100%" height="100%" style="width:100%; height:100%; min-height:100%; border:none;">
            <embed src="${finalUrl}" type="application/pdf" width="100%" height="100%" style="width:100%; height:100%; min-height:100%; border:none;" />
            <div class="p-4 text-center text-white">
                <p>No se pudo renderizar el PDF dentro de este marco.</p>
                <a href="${visorCurrentUrl}" target="_blank" class="btn btn-info fw-bold">Clic para abrir en nueva ventana</a>
            </div>
        </object>
    `;
}

function cambiarZoomVisor(modoZoom) {
    if (visorCurrentType === 'pdf') {
        renderPDFVisor(modoZoom);
    }
}

function renderImagenVisor() {
    const container = document.getElementById('containerVisorPDF');
    if (!container) return;

    imgScale = 1;
    imgRotation = 0;

    container.innerHTML = `
        <div class="d-flex justify-content-center align-items-center h-100 p-2 overflow-auto bg-dark">
            <img src="${visorCurrentUrl}" id="imgVisorTarget" class="img-fluid rounded shadow-lg" style="max-height: 86vh; object-fit: contain; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); transform: scale(1) rotate(0deg);">
        </div>
    `;
}

function zoomImagenVisor(delta) {
    const img = document.getElementById('imgVisorTarget');
    if (!img) return;
    imgScale = Math.max(0.3, Math.min(3.5, imgScale + delta));
    aplicarTransformImagenVisor();
}

function rotarImagenVisor() {
    imgRotation = (imgRotation + 90) % 360;
    aplicarTransformImagenVisor();
}

function resetImagenVisor() {
    imgScale = 1;
    imgRotation = 0;
    aplicarTransformImagenVisor();
}

function aplicarTransformImagenVisor() {
    const img = document.getElementById('imgVisorTarget');
    if (!img) return;
    img.style.transform = `scale(${imgScale}) rotate(${imgRotation}deg)`;
}

function gestionarTranscripcion(id) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'lock');

    fetch('api/lock_record.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                currentIngresoIdLock = id;
                iniciarHeartbeat(id);
                cargarDatosModal(id);
            } else {
                alert(res.message);
                refrescarListaTabla();
            }
        })
        .catch(err => console.error("Error al bloquear registro:", err));
}

function forzarDesbloqueo(id) {
    if (!confirm("¿Está seguro de forzar el desbloqueo de esta orden como Administrador?")) return;

    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'unlock');
    formData.append('force', '1');

    fetch('api/lock_record.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            alert(res.message);
            refrescarListaTabla();
        });
}

function iniciarHeartbeat(id) {
    detenerHeartbeat();
    heartbeatInterval = setInterval(() => {
        if (currentIngresoIdLock === id) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'lock');
            fetch('api/lock_record.php', { method: 'POST', body: formData });
        }
    }, 25000);
}

function detenerHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
        heartbeatInterval = null;
    }
}

function cargarDatosModal(id) {
    document.getElementById('modal_ingreso_id').value = id;

    // Limpiar estado de validación de PDF previo
    const pdfInput = document.getElementById('input_pdf_transcripcion');
    if (pdfInput) pdfInput.value = '';
    const statusDiv = document.getElementById('pdf-validation-status');
    if (statusDiv) statusDiv.innerHTML = '';
    const btnSubmit = document.getElementById('btn-submit-transcripcion');
    if (btnSubmit) btnSubmit.disabled = false;

    fetch(`index.php?page=transcripcion&ajax_get_detail=1&id=${id}`)
        .then(res => res.json())
        .then(data => {
            currentPacienteId = data.paciente_id || 0;
            currentPacienteDocNumero = (data.numero_documento || '').trim();
            currentPacienteNombreCompleto = ((data.nombres || '') + ' ' + (data.apellidos || '')).trim();

            document.getElementById('modal_paciente_nombre').innerText = currentPacienteNombreCompleto;
            document.getElementById('modal_paciente_doc').innerText = (data.tipo_documento || '') + ' ' + currentPacienteDocNumero;
            document.getElementById('modal_paciente_eps').innerText = data.eps_nombre || '';

            consultarCantidadHistorial(currentPacienteId, currentPacienteDocNumero);

            let htmlDocs = '';
            if (data.documentos && data.documentos.length > 0) {
                data.documentos.forEach(d => {
                    htmlDocs += `<button type="button" class="btn btn-sm btn-outline-danger me-1 mb-1 fw-semibold" onclick="abrirVisorPDF('${d.ruta_archivo}', '${d.tipo_documento}')">
                        <i class="fa-solid fa-file-pdf me-1"></i> Ver ${d.tipo_documento}
                    </button>`;
                });
            } else {
                htmlDocs = '<span class="text-muted small">Sin documentos adjuntos</span>';
            }
            document.getElementById('modal_documentos_iconos').innerHTML = htmlDocs;

            const modalEl = document.getElementById('modalGestionarTranscripcion');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        })
        .catch(err => console.error("Error al cargar detalles:", err));
}

function consultarCantidadHistorial(pacienteId, numeroDocumento) {
    const badge = document.getElementById('count_historial_badge');
    if (!badge) return;
    fetch(`index.php?page=transcripcion&ajax_get_historial=1&paciente_id=${pacienteId}&numero_documento=${encodeURIComponent(numeroDocumento || '')}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok' && res.data) {
                badge.textContent = res.data.length;
            }
        })
        .catch(() => {});
}

function abrirHistorialPacienteModal(pacienteId, pacienteNombre, numeroDocumento) {
    if (!pacienteId && !numeroDocumento) {
        pacienteId = currentPacienteId;
        pacienteNombre = currentPacienteNombreCompleto;
        numeroDocumento = currentPacienteDocNumero;
    }

    if (!pacienteId && !numeroDocumento) {
        alert("No se pudo identificar el paciente seleccionado.");
        return;
    }

    const titleEl = document.getElementById('historial_modal_title');
    const loaderEl = document.getElementById('historial_modal_loader');
    const tbodyEl = document.getElementById('historial_paciente_tbody');

    if (titleEl) {
        titleEl.innerHTML = `<i class="fa-solid fa-clock-rotate-left me-2 text-info"></i> Historial Clínico de Atenciones - ${escapeHtml(pacienteNombre || '')} (${escapeHtml(numeroDocumento || '')})`;
    }

    if (tbodyEl) tbodyEl.innerHTML = '';
    if (loaderEl) loaderEl.classList.remove('d-none');

    const modalEl = document.getElementById('modalHistorialPaciente');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();

    setTimeout(() => {
        modalEl.style.zIndex = '1075';
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length > 0) {
            backdrops[backdrops.length - 1].style.zIndex = '1070';
        }
    }, 50);

    fetch(`index.php?page=transcripcion&ajax_get_historial=1&paciente_id=${pacienteId}&numero_documento=${encodeURIComponent(numeroDocumento || '')}`)
        .then(res => res.json())
        .then(res => {
            if (loaderEl) loaderEl.classList.add('d-none');
            if (res.status === 'ok' && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach(row => {
                    let btnsDocs = '';
                    
                    // 1. Botón para ver Fórmula Final / Comprobante de Terceros subido en Entrega
                    if (row.pdf_formula_final_url) {
                        btnsDocs += `<button type="button" class="btn btn-sm btn-success fw-bold me-1 mb-1 shadow-sm" onclick="abrirVisorPDF('${row.pdf_formula_final_url}', 'Fórmula Final de Terceros - ${escapeHtml(row.ticket_numero)}')">
                            <i class="fa-solid fa-file-invoice-dollar me-1"></i> 📄 Ver Fórmula Entregada
                        </button>`;
                    }
                    
                    // 2. Botón para ver Fórmula Médica Transcrita
                    if (row.pdf_transcripcion_url) {
                        btnsDocs += `<button type="button" class="btn btn-sm btn-primary fw-bold me-1 mb-1 shadow-sm" onclick="abrirVisorPDF('${row.pdf_transcripcion_url}', 'Fórmula Transcrita - ${escapeHtml(row.ticket_numero)}')">
                            <i class="fa-solid fa-file-pdf me-1"></i> 📝 Ver Orden Transcrita
                        </button>`;
                    }

                    // 3. Botón para ver Acta de Entrega Firmada
                    if (row.estado_tramite === 'ENTREGADO' || row.firma_paciente_url) {
                        btnsDocs += `<a href="index.php?page=imprimir_acta&id=${row.id}" target="_blank" class="btn btn-sm btn-outline-dark fw-bold mb-1 shadow-sm">
                            <i class="fa-solid fa-print me-1"></i> 📋 Ver Acta Firmada
                        </a>`;
                    }

                    if (!btnsDocs) {
                        btnsDocs = '<span class="text-muted small">Sin documentos adjuntos</span>';
                    }

                    html += `
                    <tr>
                        <td class="ps-3 fw-bold text-primary fs-6">${escapeHtml(row.ticket_numero)}</td>
                        <td>${formatHora(row.fecha_ingreso)} <br><small class="text-muted">${new Date(row.fecha_ingreso).toLocaleDateString('es-CO')}</small></td>
                        <td><span class="badge bg-info text-dark">${escapeHtml(row.eps_nombre)}</span></td>
                        <td>${getEstadoBadge(row.estado_tramite)}</td>
                        <td>
                            <small class="d-block"><strong>Orientador:</strong> ${escapeHtml(row.orientador_nombre || 'SISPAM')}</small>
                            <small class="d-block text-muted"><strong>Alistador:</strong> ${escapeHtml(row.alistador_nombre || 'N/A')}</small>
                        </td>
                        <td class="text-end pe-3">${btnsDocs}</td>
                    </tr>`;
                });
                tbodyEl.innerHTML = html;
            } else {
                tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa-solid fa-folder-open me-2"></i> No se registraron atenciones previas para este paciente.</td></tr>';
            }
        })
        .catch(err => {
            if (loaderEl) loaderEl.classList.add('d-none');
            console.error("Error al cargar historial:", err);
            if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error al cargar el historial del paciente.</td></tr>';
        });
}

// VALIDACIÓN AUTOMÁTICA DE DOCUMENTO EN EL PDF SUBIDO
async function validarPdfTranscripcion(event) {
    const file = event.target.files[0];
    const statusDiv = document.getElementById('pdf-validation-status');
    const btnSubmit = document.getElementById('btn-submit-transcripcion');

    if (!file) {
        if (statusDiv) statusDiv.innerHTML = '';
        if (btnSubmit) btnSubmit.disabled = false;
        return;
    }

    if (!currentPacienteDocNumero) {
        return;
    }

    statusDiv.innerHTML = `<div class="alert alert-info p-2 small fw-bold mt-2"><i class="fa-solid fa-spinner fa-spin me-1"></i> Leyendo texto del PDF y comparando número de documento (${currentPacienteDocNumero})...</div>`;

    try {
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        
        let fullText = '';
        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map(item => item.str).join(' ');
            fullText += ' ' + pageText;
        }

        // Extraer dígitos limpios del documento del paciente y del texto del PDF
        const targetDigits = currentPacienteDocNumero.replace(/\D/g, '');
        const pdfDigitsOnly = fullText.replace(/\D/g, '');

        if (targetDigits.length >= 4 && pdfDigitsOnly.includes(targetDigits)) {
            statusDiv.innerHTML = `
                <div class="alert alert-success p-2 small fw-bold mt-2 shadow-sm">
                    <i class="fa-solid fa-circle-check me-1"></i> VALIDACIÓN EXITOSA: Se confirmó la Coincidencia Correcta del Documento (${currentPacienteDocNumero}) en el PDF transcrito.
                </div>`;
            if (btnSubmit) btnSubmit.disabled = false;
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-danger p-2 small fw-bold mt-2 shadow-sm">
                    <i class="fa-solid fa-circle-xmark me-1"></i> ERROR CRÍTICO DE VALIDACIÓN DE PACIENTE:<br>
                    El número de documento <strong>${currentPacienteDocNumero}</strong> del paciente gestionado NO fue encontrado dentro del texto del archivo PDF adjuntado.<br>
                    <small>Se ha bloqueado el envío. Verifique que no esté subiendo la orden de otro paciente.</small>
                </div>`;
            if (btnSubmit) btnSubmit.disabled = true;
        }
    } catch (e) {
        console.warn("Fallo lectura de texto plano PDF.js:", e);
        statusDiv.innerHTML = `
            <div class="alert alert-warning p-2 small fw-bold mt-2 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> PDF adjuntado. (No se pudo extraer capa de texto plano automática para validar el documento, por favor confirme visualmente).
            </div>`;
        if (btnSubmit) btnSubmit.disabled = false;
    }
}

function liberarBloqueoActual() {
    detenerHeartbeat();
    if (currentIngresoIdLock) {
        const formData = new FormData();
        formData.append('id', currentIngresoIdLock);
        formData.append('action', 'unlock');
        fetch('api/lock_record.php', { method: 'POST', body: formData });
        currentIngresoIdLock = null;
        refrescarListaTabla();
    }
}

function iniciarAutoRefresco() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
        if (!currentIngresoIdLock) {
            refrescarListaTabla();
        }
    }, 4000);
}

function refrescarListaTabla() {
    fetch('index.php?page=transcripcion&ajax_get_list=1')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'ok') {
                renderizarTabla(res.data, res.user_id, res.es_admin);
            }
        })
        .catch(err => console.error("Error al refrescar lista:", err));
}

function renderizarTabla(lista, currentUserId, esAdmin) {
    const tbody = document.getElementById('tabla-transcripcion-body');
    const badgeTotal = document.getElementById('badge-total-cola');
    if (!tbody) return;

    if (badgeTotal) badgeTotal.textContent = `${lista.length} En Cola`;

    if (!lista || lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay órdenes pendientes en la lista de transcripción.</td></tr>';
        return;
    }

    let html = '';
    lista.forEach(row => {
        const isLockedByMe = row.locked_by_user_id && row.locked_by_user_id == currentUserId;
        const isLockedByOther = row.locked_by_user_id && row.locked_by_user_id != currentUserId;
        
        let lockBadge = '';
        let btnAccion = '';
        let btnHistorial = `<button type="button" class="btn btn-sm btn-outline-info text-dark fw-semibold ms-1" title="Ver Historial de Atenciones del Paciente" onclick="abrirHistorialPacienteModal(${row.paciente_id}, '${escapeHtml(row.nombres + ' ' + row.apellidos)}', '${escapeHtml(row.numero_documento)}')"><i class="fa-solid fa-clock-rotate-left"></i> Historial</button>`;

        if (isLockedByOther) {
            lockBadge = `<span class="badge bg-danger"><i class="fa-solid fa-lock me-1"></i> Bloqueado por: ${escapeHtml(row.locked_by_nombre || 'Otro usuario')}</span>`;
            
            let btnAdmin = esAdmin ? `<button class="btn btn-sm btn-outline-danger ms-1" title="Forzar Desbloqueo (Admin)" onclick="forzarDesbloqueo(${row.id})"><i class="fa-solid fa-key"></i></button>` : '';

            btnAccion = `<div class="d-flex justify-content-end gap-1"><button class="btn btn-sm btn-secondary" disabled><i class="fa-solid fa-lock me-1"></i> Ocupado</button>${btnAdmin}${btnHistorial}</div>`;
        } else if (isLockedByMe) {
            lockBadge = `<span class="badge bg-warning text-dark"><i class="fa-solid fa-user-gear me-1"></i> En gestión por ti</span>`;
            btnAccion = `<div class="d-flex justify-content-end gap-1"><button class="btn btn-sm btn-warning fw-bold text-dark" onclick="gestionarTranscripcion(${row.id})">
                <i class="fa-solid fa-folder-open me-1"></i> Continuar
            </button>${btnHistorial}</div>`;
        } else {
            lockBadge = `<span class="badge bg-success"><i class="fa-solid fa-lock-open me-1"></i> Disponible</span>`;
            btnAccion = `<div class="d-flex justify-content-end gap-1"><button class="btn btn-sm btn-primary fw-bold" onclick="gestionarTranscripcion(${row.id})">
                <i class="fa-solid fa-keyboard me-1"></i> Transcribir
            </button>${btnHistorial}</div>`;
        }

        let prioBadge = getPrioridadBadge(row.prioridad);

        html += `
        <tr>
            <td class="ps-3 fw-bold text-primary">${escapeHtml(row.ticket_numero)}</td>
            <td>${formatHora(row.fecha_ingreso)}</td>
            <td>
                <div class="fw-bold">${escapeHtml(row.nombres + ' ' + row.apellidos)} ${prioBadge}</div>
                <small class="text-muted">${escapeHtml(row.tipo_documento + ' ' + row.numero_documento)}</small>
            </td>
            <td><span class="badge bg-info text-dark">${escapeHtml(row.eps_nombre)}</span></td>
            <td>${getEstadoBadge(row.estado_tramite)}</td>
            <td>${lockBadge}</td>
            <td class="text-end pe-3">${btnAccion}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

function getPrioridadBadge(prioridad) {
    switch (prioridad) {
        case 'TERCERA_EDAD': return '<span class="badge bg-warning text-dark ms-1"><i class="fa-solid fa-person-cane me-1"></i> 👴 Tercera Edad</span>';
        case 'EMBARAZADA': return '<span class="badge bg-danger text-white ms-1"><i class="fa-solid fa-person-pregnant me-1"></i> 🤰 Embarazada</span>';
        case 'DISCAPACIDAD': return '<span class="badge bg-info text-dark ms-1"><i class="fa-solid fa-wheelchair me-1"></i> ♿ Discapacidad</span>';
        case 'NIÑO_LACTANTE': return '<span class="badge bg-primary text-white ms-1"><i class="fa-solid fa-baby me-1"></i> 👶 Niño/Lactante</span>';
        case 'OTRO_PREFERENCIAL': return '<span class="badge bg-warning text-dark ms-1"><i class="fa-solid fa-star me-1"></i> ⭐ Preferencial</span>';
        default: return '';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function formatHora(fechaStr) {
    if (!fechaStr) return '';
    const d = new Date(fechaStr.replace(/-/g, '/'));
    if (isNaN(d.getTime())) return fechaStr;
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
}

function getEstadoBadge(estado) {
    switch (estado) {
        case 'INGRESADO': return '<span class="badge bg-secondary"><i class="fa-solid fa-user-clock me-1"></i> Ingresado</span>';
        case 'EN_TRANSCRIPCION': return '<span class="badge bg-primary"><i class="fa-solid fa-keyboard me-1"></i> En Transcripción</span>';
        case 'TRANSCRITO_COMPLETO': return '<span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Transcrito Completo</span>';
        case 'TRANSCRITO_PENDIENTE': return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Con Pendientes</span>';
        case 'SIN_STOCK': return '<span class="badge bg-danger"><i class="fa-solid fa-boxes-packing me-1"></i> Sin Stock</span>';
        default: return `<span class="badge bg-light text-dark">${escapeHtml(estado)}</span>`;
    }
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
