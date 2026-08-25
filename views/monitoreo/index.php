<?php
require_once __DIR__ . '/../../config/app.php';
check_auth();

require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/Empresa.php';

$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$esAdmin = ($_SESSION['rol_nombre'] ?? '') === 'Administrador';
$mensaje = '';
$error = '';

// AJAX Endpoint: Obtener detalles del ingreso para el visor comparativo
if (isset($_GET['ajax_get_detail']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $detalles = $ingresoModel->getById($_GET['id']);
    echo json_encode($detalles ?: []);
    exit;
}

// Procesar decisión de verificación del Monitor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_verificacion') {
    $id = intval($_POST['ingreso_id'] ?? 0);
    $estado_verificacion = $_POST['estado_verificacion'] ?? 'VERIFICADA'; // 'VERIFICADA' o 'CON_ERRORES'
    $observaciones = trim($_POST['observaciones'] ?? '');
    $texto_transcrito_nuevo = isset($_POST['transcripcion_texto_nuevo']) ? trim($_POST['transcripcion_texto_nuevo']) : null;
    $nuevo_pdf_file = $_FILES['nuevo_pdf_file'] ?? null;

    $exito = $ingresoModel->marcarVerificadoMonitor(
        $id,
        $_SESSION['user_id'],
        $estado_verificacion,
        $observaciones,
        $nuevo_pdf_file,
        $texto_transcrito_nuevo
    );

    if ($exito) {
        registrar_log_auditoria('MONITOREO', 'VERIFICACION_TECNICA', $id, "Verificación técnica farmacéutica completada. Estado: {$estado_verificacion}. Observaciones: {$observaciones}");
    }

    if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        if ($exito) {
            echo json_encode(['status' => 'ok', 'message' => "¡Orden #{$id} verificada con éxito y enviada al Supervisor de Alistamiento!"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Error al actualizar la verificación de la orden #{$id}."]);
        }
        exit;
    }

    if ($exito) {
        $mensaje = ($estado_verificacion === 'VERIFICADA') 
            ? "¡Orden #{$id} marcada como VERIFICADA OK y enviada al Supervisor de Alistamiento!"
            : "¡Orden #{$id} corregida y enviada al Supervisor de Alistamiento!";
    } else {
        $error = "Error al actualizar la verificación de la orden.";
    }
}

$ingresosMonitoreo = $ingresoModel->getIngresosParaMonitoreo();

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-primary mb-1">
                <i class="fa-solid fa-eye me-2"></i> Módulo de Monitoreo & Verificación
            </h3>
            <p class="text-muted mb-0">Control de Calidad: Compare la orden médica escaneada vs la fórmula transcrita antes del alistamiento.</p>
        </div>
        <div>
            <span class="badge bg-purple text-white fs-6 px-3 py-2" style="background-color: #6f42c1;">
                <i class="fa-solid fa-clipboard-check me-1"></i> <?= count($ingresosMonitoreo) ?> Órdenes por Verificar
            </span>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card card-glass border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Tiquete</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>EPS</th>
                            <th>Prioridad</th>
                            <th>Transcriptor</th>
                            <th>Estado Bloqueo</th>
                            <th class="text-end pe-3">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ingresosMonitoreo)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-circle-check fa-3x mb-3 text-success"></i>
                                    <h5>No hay órdenes pendientes por monitorear</h5>
                                    <p class="mb-0">Todas las transcripciones han sido verificadas.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ingresosMonitoreo as $ing): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($ing['ticket_numero']) ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($ing['nombres'] . ' ' . $ing['apellidos']) ?></div>
                                        <small class="text-muted">Ingreso: <?= date('d/m/Y h:i A', strtotime($ing['fecha_ingreso'])) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($ing['tipo_documento'] . ' ' . $ing['numero_documento']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ing['eps_nombre']) ?></span></td>
                                    <td><?= get_prioridad_badge($ing['prioridad'] ?? 'NORMAL') ?></td>
                                    <td>
                                        <small><i class="fa-solid fa-user-pen me-1"></i> Transcrito</small>
                                    </td>
                                    <td>
                                        <?php if (!empty($ing['locked_by_user']) && $ing['locked_by_user'] != $_SESSION['user_id']): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fa-solid fa-lock me-1"></i> En uso por <?= htmlspecialchars($ing['locked_by_nombre'] ?? 'Otro Usuario') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                <i class="fa-solid fa-lock-open me-1"></i> Disponible
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if (!empty($ing['locked_by_user']) && $ing['locked_by_user'] != $_SESSION['user_id'] && !$esAdmin): ?>
                                            <button type="button" class="btn btn-sm btn-secondary disabled" disabled>
                                                <i class="fa-solid fa-lock me-1"></i> Bloqueado
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-purple text-white fw-bold shadow-sm" style="background-color: #6f42c1;" onclick="abrirMonitoreoComparativo(<?= $ing['id'] ?>)">
                                                <i class="fa-solid fa-code-compare me-1"></i> Comparar & Verificar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visor Comparativo Lado a Lado (Side-by-Side) para Monitor -->
<div class="modal fade" id="modalMonitoreoComparativo" tabindex="-1" data-bs-backdrop="static" style="z-index: 1080;">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content style-dark" style="background-color: #0f172a; color: #fff;">
            <div class="modal-header py-2 bg-dark text-white border-bottom border-secondary d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="modal-title fw-bold mb-0 text-info">
                        <i class="fa-solid fa-eye me-2"></i> Verificación de Calidad de Transcripción: <span id="lblTicketMonitoreo" class="text-white"></span>
                    </h5>
                    <span id="lblPacienteMonitoreo" class="badge bg-secondary fs-6"></span>
                </div>
                <button type="button" class="btn-close btn-close-white" onclick="cerrarMonitoreoComparativo()"></button>
            </div>
            
            <div class="modal-body p-2 d-flex flex-column" style="height: calc(100vh - 120px); overflow: hidden;">
                <div class="row g-2 flex-grow-1 h-100">
                    <!-- Panel Izquierdo: Orden Médica Escaneada Inicial -->
                    <div class="col-md-6 d-flex flex-column h-100">
                        <div class="card bg-dark border-secondary h-100 d-flex flex-column">
                            <div class="card-header py-2 bg-secondary bg-opacity-20 text-info fw-bold d-flex justify-content-between align-items-center border-bottom border-secondary">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-file-medical text-danger fs-5"></i>
                                    <span>1. Orden Médica Escaneada (Fórmula)</span>
                                </div>
                                <div id="selectorDocsMonitoreoCont" class="d-none">
                                    <select id="selectDocMonitoreo" class="form-select form-select-sm bg-dark text-info border-info fw-bold py-0" style="font-size: 0.82rem; max-width: 260px;" onchange="cambiarDocMonitoreo(this.value)">
                                    </select>
                                </div>
                            </div>
                            <div class="card-body p-0 flex-grow-1 position-relative overflow-hidden" id="containerEscaneadoMonitoreo">
                                <!-- Render de imagen u orden escaneada -->
                            </div>
                        </div>
                    </div>

                    <!-- Panel Derecho: PDF / Datos Transcritos por Transcriptor -->
                    <div class="col-md-6 d-flex flex-column h-100">
                        <div class="card bg-dark border-secondary h-100 d-flex flex-column">
                            <div class="card-header py-2 bg-secondary bg-opacity-20 text-success fw-bold d-flex justify-content-between align-items-center border-bottom border-secondary">
                                <span><i class="fa-solid fa-file-pdf me-2"></i> 2. Fórmula Transcrita por Transcriptor</span>
                                <span class="badge bg-success">Transcripción Subida</span>
                            </div>
                            <div class="card-body p-0 flex-grow-1 position-relative overflow-hidden" id="containerTranscritoMonitoreo">
                                <!-- Render de PDF Transcrito -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer: Botones de Decisión y Formulario de Corrección -->
            <div class="modal-footer bg-dark border-top border-secondary py-2 justify-content-between">
                <form id="formVerificacionMonitor" method="POST" action="index.php?page=monitoreo" enctype="multipart/form-data" class="w-100 d-flex align-items-center justify-content-between gap-3 flex-wrap">
                    <input type="hidden" name="action" value="guardar_verificacion">
                    <input type="hidden" name="ingreso_id" id="monitoreoIngresoId">
                    <input type="hidden" name="estado_verificacion" id="monitoreoEstadoVerificacion" value="VERIFICADA">

                    <div class="d-flex align-items-center gap-2 flex-grow-1" id="sectionErroresForm" style="display: none !important;">
                        <input type="text" name="observaciones" id="monitoreoObservaciones" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Describa el error de transcripción detectado..." style="max-width: 320px;">
                        <div class="input-group input-group-sm" style="max-width: 300px;">
                            <span class="input-group-text bg-secondary text-white border-secondary"><i class="fa-solid fa-file-upload"></i></span>
                            <input type="file" name="nuevo_pdf_file" class="form-control bg-dark text-white border-secondary" accept=".pdf,.png,.jpg,.jpeg">
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-warning text-white fw-bold" id="btnMarcarErrorMonitoreo" onclick="activarModoErrorMonitoreo()">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Con Errores de Transcripción (Editar/Subir Nuevo)
                        </button>
                        <button type="submit" class="btn btn-success fw-bold px-4" id="btnAprobarMonitoreo" onclick="document.getElementById('monitoreoEstadoVerificacion').value='VERIFICADA'">
                            <i class="fa-solid fa-circle-check me-1"></i> VERIFICADA OK (Pasar a Alistamiento)
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cerrarMonitoreoComparativo()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentMonitoreoIngresoId = null;

function cambiarDocMonitoreo(url) {
    renderizarDocEscaneado(url);
}

function renderizarDocEscaneado(url) {
    const containerEscaneado = document.getElementById('containerEscaneadoMonitoreo');
    if (!containerEscaneado) return;

    if (!url) {
        containerEscaneado.innerHTML = `
            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted p-4 text-center">
                <i class="fa-solid fa-file-circle-xmark fa-3x mb-3 text-warning"></i>
                <h6 class="text-white">No hay documento escaneado adjunto</h6>
                <small>El ingreso no cuenta con archivo escaneado de orden médica.</small>
            </div>`;
        return;
    }

    const ext = url.split('.').pop().split('?')[0].toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
        containerEscaneado.innerHTML = `
            <div class="h-100 p-2 overflow-auto text-center bg-dark d-flex align-items-center justify-content-center">
                <img src="${url}" class="img-fluid rounded shadow" style="max-height: 80vh; max-width: 100%; object-fit: contain;">
            </div>`;
    } else {
        containerEscaneado.innerHTML = `
            <object data="${url}#view=Fit&toolbar=1" type="application/pdf" width="100%" height="100%">
                <embed src="${url}#view=Fit&toolbar=1" type="application/pdf" width="100%" height="100%" />
            </object>`;
    }
}

function abrirMonitoreoComparativo(id) {
    currentMonitoreoIngresoId = id;
    document.getElementById('monitoreoIngresoId').value = id;
    
    // 1. Bloquear registro en tiempo real
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', 'lock');
    fetch('api/lock_record.php', { method: 'POST', body: formData });

    // 2. Cargar detalles del ingreso vía AJAX
    fetch(`index.php?page=monitoreo&ajax_get_detail=1&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data || !data.id) {
                console.warn("No se pudieron obtener los detalles completos vía AJAX, usando ID predeterminado:", id);
                document.getElementById('lblTicketMonitoreo').innerText = `ID #${id}`;
            } else {
                document.getElementById('lblTicketMonitoreo').innerText = data.ticket_numero || `ID #${id}`;
                document.getElementById('lblPacienteMonitoreo').innerText = `${data.nombres || ''} ${data.apellidos || ''} (${data.tipo_documento || ''} ${data.numero_documento || ''})`;
            }
            
            // Restablecer vista de formulario
            document.getElementById('sectionErroresForm').style.setProperty('display', 'none', 'important');
            document.getElementById('monitoreoObservaciones').value = '';
            document.getElementById('monitoreoEstadoVerificacion').value = 'VERIFICADA';

            // Panel Izquierdo: Cargar SIEMPRE la Orden Médica / Fórmula escaneada
            const currentDocs = data.documentos || [];
            const selectorCont = document.getElementById('selectorDocsMonitoreoCont');
            const selectDoc = document.getElementById('selectDocMonitoreo');
            if (selectDoc) selectDoc.innerHTML = '';

            let docOrdenMedicaUrl = '';

            if (currentDocs.length > 0) {
                // 1. Buscar específicamente el documento de ORDEN MÉDICA / FÓRMULA
                let docOrden = currentDocs.find(d => {
                    const tipo = (d.tipo_documento || '').toUpperCase();
                    const ruta = (d.ruta_archivo || '').toUpperCase();
                    const nombre = (d.nombre_original || '').toUpperCase();
                    return tipo.includes('ORDEN') || tipo.includes('FORMULA') || tipo.includes('MEDICA') || tipo.includes('RECETA') ||
                           ruta.includes('ORDEN') || ruta.includes('FORMULA') || nombre.includes('ORDEN') || nombre.includes('FORMULA');
                });

                // 2. Si no tiene etiqueta explícita de orden, buscar el que NO sea cédula
                if (!docOrden) {
                    docOrden = currentDocs.find(d => {
                        const tipo = (d.tipo_documento || '').toUpperCase();
                        return !tipo.includes('CEDULA') && !tipo.includes('IDENTIDAD');
                    });
                }

                // 3. Fallback: tomar el último documento (la fórmula suele ser el segundo documento tras la cédula) o el primero
                if (!docOrden) {
                    docOrden = currentDocs[currentDocs.length - 1];
                }

                docOrdenMedicaUrl = docOrden ? docOrden.ruta_archivo : '';

                // Poblar selector si hay múltiples documentos para que el monitor pueda alternar si lo desea
                if (selectDoc && currentDocs.length > 1) {
                    currentDocs.forEach((d, idx) => {
                        const opt = document.createElement('option');
                        opt.value = d.ruta_archivo;
                        const esEstaOrden = (d.ruta_archivo === docOrdenMedicaUrl);
                        opt.textContent = `${d.tipo_documento || ('Doc ' + (idx + 1))}${esEstaOrden ? ' (⭐ Orden Médica)' : ''}`;
                        if (esEstaOrden) opt.selected = true;
                        selectDoc.appendChild(opt);
                    });
                    if (selectorCont) selectorCont.classList.remove('d-none');
                } else if (selectorCont) {
                    selectorCont.classList.add('d-none');
                }
            } else if (data.foto_paciente_url) {
                docOrdenMedicaUrl = data.foto_paciente_url;
                if (selectorCont) selectorCont.classList.add('d-none');
            } else if (selectorCont) {
                selectorCont.classList.add('d-none');
            }

            // Renderizar la Orden Médica en el panel izquierdo
            renderizarDocEscaneado(docOrdenMedicaUrl);

            // Panel Derecho: Cargar PDF Transcrito por el Transcriptor
            const containerTranscrito = document.getElementById('containerTranscritoMonitoreo');
            if (data.pdf_transcripcion_url) {
                containerTranscrito.innerHTML = `<object data="${data.pdf_transcripcion_url}#view=Fit&toolbar=1" type="application/pdf" width="100%" height="100%"><embed src="${data.pdf_transcripcion_url}#view=Fit&toolbar=1" type="application/pdf" width="100%" height="100%" /></object>`;
            } else {
                containerTranscrito.innerHTML = `<div class="p-5 text-center text-muted">El transcriptor no ha adjuntado el PDF transcrito.</div>`;
            }

            const modalEl = document.getElementById('modalMonitoreoComparativo');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        });
}

function activarModoErrorMonitoreo() {
    document.getElementById('monitoreoEstadoVerificacion').value = 'CON_ERRORES';
    document.getElementById('sectionErroresForm').style.setProperty('display', 'flex', 'important');
    const btnAprobar = document.getElementById('btnAprobarMonitoreo');
    btnAprobar.className = "btn btn-warning fw-bold px-4 text-dark";
    btnAprobar.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i> Guardar Corrección y Enviar a Alistamiento';
    document.getElementById('monitoreoObservaciones').focus();
}

function cerrarMonitoreoComparativo() {
    if (currentMonitoreoIngresoId) {
        const formData = new FormData();
        formData.append('id', currentMonitoreoIngresoId);
        formData.append('action', 'unlock');
        fetch('api/lock_record.php', { method: 'POST', body: formData });
        currentMonitoreoIngresoId = null;
    }

    const modalEl = document.getElementById('modalMonitoreoComparativo');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
}

document.addEventListener('DOMContentLoaded', () => {
    const formVerif = document.getElementById('formVerificacionMonitor');
    if (formVerif) {
        formVerif.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax', '1');

            fetch('index.php?page=monitoreo', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Error al procesar la verificación.');
                }
            })
            .catch(err => {
                console.error("Error al enviar verificación vía AJAX:", err);
                this.submit();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
