<?php
require_once __DIR__ . '/../../config/app.php';
check_role('transcripcion');

require_once __DIR__ . '/../../models/Ingreso.php';
$ingresoModel = new Ingreso();

$esAdmin = (($_SESSION['rol_nombre'] ?? '') === 'Administrador');
$mensaje = '';
$error = '';

// =============================================================================
// EXPORTAR PACIENTE EN FORMATO EXCEL (.XLS) - QRYSTALOS (SIN LIBRERIAS)
// =============================================================================
if (isset($_GET['exportar_excel_paciente']) && isset($_GET['ingreso_id'])) {
    $ingreso_id = intval($_GET['ingreso_id']);
    $detalle = $ingresoModel->getById($ingreso_id);
    
    if ($detalle && !empty($detalle['numero_documento'])) {
        require_once __DIR__ . '/../../models/Paciente.php';
        $pacienteModel = new Paciente();
        $paciente = $pacienteModel->getByDocumento($detalle['tipo_documento'], $detalle['numero_documento']);
        
        $data = array_merge($detalle, $paciente ?: []);
        
        $rowData = [
            'TIPO_DOC'            => strtoupper(trim($data['tipo_documento'] ?? 'CC')),
            'DOCIDAFILIADO'       => preg_replace('/[^\dA-Z]/', '', trim($data['numero_documento'] ?? '')),
            'PAPELLIDO'           => strtoupper(trim($data['primer_apellido'] ?? 'APELLIDO')),
            'SAPELLIDO'           => strtoupper(trim($data['segundo_apellido'] ?? '')),
            'PNOMBRE'             => strtoupper(trim($data['primer_nombre'] ?? 'NOMBRE')),
            'SNOMBRE'             => strtoupper(trim($data['segundo_nombre'] ?? '')),
            'FNACIMIENTO'         => $data['fecha_nacimiento'] ?? '',
            'CIUDADNAC'           => '05001',
            'SEXO'                => trim($data['sexo'] ?? 'Masculino'),
            'ESTADO_CIVIL'        => 'S/N',
            'GRUPO_SANG'          => 'S/N',
            'GRUPOETNICO'         => 'S - S/N',
            'TIPODISCAPACIDAD'    => 'N - No Aplica',
            'IDESCOLARIDAD'       => '13 - NINGUNO',
            'IDPAIS'              => '',
            'DIRECCION'           => strtoupper(trim($data['direccion_residencia'] ?? 'DIRECCION NO ESPECIFICADA')),
            'CIUDAD'              => '05001',
            'ZONA'                => 'U - Urbana',
            'IDBARRIO'            => '',
            'CELULAR'             => preg_replace('/[^\d]/', '', trim($data['numero_celular'] ?? '3000000000')),
            'TELEFONORES'         => '',
            'EMAIL'               => !empty($data['email']) ? trim($data['email']) : ('paciente_' . ($data['numero_documento'] ?? '000') . '@temp.sispam.local'),
            'IDOCUPACION'         => '0000 - No Aplica',
            'IDADMINISTRADORA'    => 'EPS040',
            'IDPLAN'              => '',
            'TIPOUSUARIO'         => '04 - Subsidiado',
            'NIVELSOCIOEC'        => '1 - CATEGORIA A',
            'ESTRATO'             => intval($data['estrato_socioeconomico'] ?? 3),
            'IDSEDE'              => '20 - SEDE EXTERNA',
            'FECHAAFILIACION'     => $data['fecha_afiliacion'] ?? '',
            'CIUDADDOC'           => '',
            'EDAD_ESTIMADA'       => '',
            'GRUPOPOB'            => '5 - Otro Grupo Poblacional',
            'CATEGORIA'           => 'NOR - Normal',
            'CORREGIMIENTO'       => ''
        ];
        
        $headers = array_keys($rowData);
        $filename = 'paciente_' . ($data['numero_documento'] ?? 'sin_doc') . '_qrystalos.xls';
        
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo "\xEF\xBB\xBF";
        
        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        echo '<style>td{border:1px solid #ccc;padding:4px;}th{border:1px solid #4472C4;background:#4472C4;color:white;font-weight:bold;padding:4px;}</style>';
        echo '</head><body><table>';
        
        echo '<tr>';
        foreach ($headers as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
        echo '</tr>';
        
        echo '<tr>';
        foreach ($rowData as $v) echo '<td>' . htmlspecialchars($v) . '</td>';
        echo '</tr>';
        
        echo '</table></body></html>';
        exit;
    }
}
// =============================================================================

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

if (isset($_GET['ajax_get_detail']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $detalles = $ingresoModel->getById($_GET['id']);
    echo json_encode($detalles ?: []);
    exit;
}

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
    $ingreso_id = intval($_POST['ingreso_id'] ?? 0);
    $contiene_mipres = trim($_POST['contiene_mipres'] ?? 'NO');
    if (empty($contiene_mipres) || !in_array($contiene_mipres, ['SI', 'NO'])) {
        $contiene_mipres = 'NO';
    }
    $pdf_file = $_FILES['pdf_transcripcion'] ?? null;

    if (empty($pdf_file) || !isset($pdf_file['tmp_name']) || !is_uploaded_file($pdf_file['tmp_name'])) {
        $error = 'Por favor adjunte obligatoriamente el archivo PDF de la orden médica transcrita.';
    } else if ($ingreso_id > 0) {
        $exito = $ingresoModel->guardarTranscripcion($ingreso_id, $pdf_file, $_SESSION['user_id'], $contiene_mipres);
        if ($exito) {
            registrar_log_auditoria('TRANSCRIPCION', 'GUARDAR_TRANSCRIPCION', $ingreso_id, "Transcripción completada con PDF para la orden #{$ingreso_id}. Enviada a Monitoreo.");
            $mensaje = 'Transcripción completada con éxito. La orden pasó a Monitoreo.';
        } else {
            $error = 'Error al guardar la transcripción.';
        }
    }
}

$listaTrabajo = $ingresoModel->getListaTranscripcion();

$totalCola = count($listaTrabajo);
$totalPrioritarios = 0;
$totalEnGestion = 0;
foreach ($listaTrabajo as $row) {
    if (($row['prioridad'] ?? 'NORMAL') !== 'NORMAL') {
        $totalPrioritarios++;
    }
    if (!empty($row['locked_by_user_id'])) {
        $totalEnGestion++;
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    #modalGestionarTranscripcion { z-index: 1055 !important; }
    #modalHistorialPaciente { z-index: 1065 !important; }
    #modalVisorPDF { z-index: 1080 !important; }
    #modalHistorialPaciente .modal-dialog, #modalGestionarTranscripcion .modal-dialog, #modalVisorPDF .modal-dialog { pointer-events: auto !important; }
    #modalHistorialPaciente .modal-content, #modalGestionarTranscripcion .modal-content, #modalVisorPDF .modal-content { pointer-events: auto !important; }
    .modal-backdrop { z-index: 1050 !important; }
    .btn-outline-purple { color: #7c3aed; border-color: #7c3aed; background-color: transparent; }
    .btn-outline-purple:hover, .btn-check:checked+.btn-outline-purple { color: #fff; background-color: #7c3aed; border-color: #7c3aed; }
    .bg-purple { background-color: #7c3aed !important; }
    .text-purple { color: #7c3aed !important; }
    .bg-purple-subtle { background-color: #f5f3ff !important; border-color: #ddd6fe !important; }
    @keyframes shakeAnimation { 0%, 100% { transform: translateX(0); } 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }
    .animate-shake { animation: shakeAnimation 0.4s ease-in-out; }
    @keyframes pulseLive { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.15); } }
    .animate-pulse { animation: pulseLive 1.8s infinite ease-in-out; }
    .header-gradient-transcripcion { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #3b82f6 100%); }
    .modal-header-gradient { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #2563eb 100%); }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>

<div class="row justify-content-center">
    <div class="col-lg-12">
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show small shadow-sm border-0 d-flex align-items-center mb-3">
                <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i> <div><?= htmlspecialchars($mensaje) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small shadow-sm border-0 d-flex align-items-center mb-3">
                <i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-danger"></i> <div><?= htmlspecialchars($error) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 header-gradient-transcripcion text-white p-3 h-100 position-relative overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 text-uppercase fw-bold small tracking-wide">Órdenes en Espera</div>
                            <h2 class="display-6 fw-bold mb-0 mt-1" id="kpi-total-cola"><?= $totalCola ?></h2>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-20 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-keyboard fs-3 text-white"></i>
                        </div>
                    </div>
                    <div class="small text-white-50 mt-2 d-flex align-items-center">
                        <i class="fa-solid fa-circle text-warning me-1 animate-pulse" style="font-size: 8px;"></i> Actualización en tiempo real
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-warning border-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase fw-bold small">Atención Preferencial</div>
                            <h2 class="display-6 fw-bold text-dark mb-0 mt-1" id="kpi-prioritarios"><?= $totalPrioritarios ?></h2>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-star fs-3 text-warning"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Tercera edad, embarazadas, discapacidad</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100 border-start border-info border-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase fw-bold small">En Gestión por Transcriptor</div>
                            <h2 class="display-6 fw-bold text-dark mb-0 mt-1" id="kpi-gestion"><?= $totalEnGestion ?></h2>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-user-clock fs-3 text-info"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Tomados por el equipo actualmente</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary">
                        <i class="fa-solid fa-list-check fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Lista de Trabajo de Transcripción</h5>
                        <small class="text-muted">Órdenes médicas listas para verificar y adjuntar PDF</small>
                    </div>
                </div>
                <span class="badge bg-primary px-3 py-2 rounded-pill fs-6 fw-semibold shadow-sm" id="badge-total-cola"><?= $totalCola ?> En Cola</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">Tiquete</th>
                                <th class="py-3">Sede</th>
                                <th class="py-3">Hora Ingreso</th>
                                <th class="py-3">Paciente</th>
                                <th class="py-3">EPS</th>
                                <th class="py-3">Estado Actual</th>
                                <th class="py-3">Estado Bloqueo</th>
                                <th class="text-end pe-4 py-3">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-transcripcion-body">
                            <?php if (empty($listaTrabajo)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fa-solid fa-inbox fs-2 mb-2 d-block text-secondary opacity-50"></i>No hay órdenes pendientes en la lista de transcripción.</td></tr>
                            <?php endif; ?>

                            <?php foreach ($listaTrabajo as $row): ?>
                                <?php
                                $isLockedByMe = (!empty($row['locked_by_user_id']) && $row['locked_by_user_id'] == $_SESSION['user_id']);
                                $isLockedByOther = (!empty($row['locked_by_user_id']) && $row['locked_by_user_id'] != $_SESSION['user_id']);
                                ?>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace fs-6 fw-bold">
                                            <?= htmlspecialchars($row['ticket_numero']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($row['nombre_sede'] ?? 'Sede Principal') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= date('h:i A', strtotime($row['fecha_ingreso'])) ?></div>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($row['fecha_ingreso'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?>
                                            <?= get_prioridad_badge($row['prioridad'] ?? 'NORMAL') ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($row['tipo_documento'] . ' ' . $row['numero_documento']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 px-2 py-1 fw-semibold">
                                            <?= htmlspecialchars($row['eps_nombre']) ?>
                                        </span>
                                    </td>
                                    <td><?= get_estado_badge($row['estado_tramite']) ?></td>
                                    <td>
                                        <?php if ($isLockedByOther): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fa-solid fa-lock me-1"></i> Bloqueado: <?= htmlspecialchars($row['locked_by_nombre']) ?></span>
                                        <?php elseif ($isLockedByMe): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1 fw-semibold"><i class="fa-solid fa-user-gear me-1 text-warning"></i> En gestión por ti</span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-semibold"><i class="fa-solid fa-lock-open me-1"></i> Disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="index.php?page=transcripcion&exportar_excel_paciente=1&ingreso_id=<?= $row['id'] ?>" class="btn btn-sm btn-success fw-semibold" title="Descargar Excel para Qrystalos">
                                                <i class="fa-solid fa-file-excel me-1"></i> Excel
                                            </a>
                                            
                                            <?php if ($isLockedByOther): ?>
                                                <button class="btn btn-sm btn-secondary opacity-75" disabled><i class="fa-solid fa-lock me-1"></i> Ocupado</button>
                                                <?php if ($esAdmin): ?>
                                                    <button class="btn btn-sm btn-outline-danger" title="Forzar Desbloqueo (Admin)" onclick="forzarDesbloqueo(<?= $row['id'] ?>)">
                                                        <i class="fa-solid fa-key"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($isLockedByMe): ?>
                                                <button class="btn btn-sm btn-warning fw-bold text-dark shadow-sm" onclick="gestionarTranscripcion(<?= $row['id'] ?>)">
                                                    <i class="fa-solid fa-folder-open me-1"></i> Continuar
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-primary fw-bold shadow-sm px-3" onclick="gestionarTranscripcion(<?= $row['id'] ?>)">
                                                    <i class="fa-solid fa-keyboard me-1"></i> Transcribir
                                                </button>
                                            <?php endif; ?>
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

<div class="modal fade" id="modalGestionarTranscripcion" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header modal-header-gradient text-white py-3 px-4 border-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-3">
                        <i class="fa-solid fa-keyboard fs-4 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0">Gestión y Transcripción de Orden Médica</h5>
                        <small class="text-white-50">Valide los soportes ingresados, adjunte la orden transcrita y envíe a Monitoreo</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="liberarBloqueoActual()"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="formTranscripcion">
                <input type="hidden" name="action" value="guardar_transcripcion">
                <input type="hidden" name="ingreso_id" id="modal_ingreso_id">
                <div class="modal-body p-4 bg-light bg-opacity-50">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="fa-solid fa-user-injured fs-6"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small fw-bold text-uppercase">Paciente en SISPAM</div>
                                            <div class="fw-bold fs-6 text-dark" id="modal_paciente_nombre">-</div>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-top">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted small">Documento:</span>
                                            <span class="badge bg-light text-primary border fw-bold fs-6" id="modal_paciente_doc">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">EPS Aseguradora:</span>
                                            <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 fw-semibold" id="modal_paciente_eps">-</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100 mt-3 rounded-2 shadow-sm" id="btn_abrir_historial_modal" onclick="abrirHistorialDesdeTranscripcion()">
                                    <i class="fa-solid fa-clock-rotate-left me-1 text-primary"></i> 📜 Ver Historial Clínico Previo (<span id="count_historial_badge">0</span>)
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="text-muted small fw-bold text-uppercase">
                                        <i class="fa-solid fa-folder-open text-primary me-1"></i> Documentos Ingresados
                                    </div>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border small">Disponibles</span>
                                </div>
                                <div class="p-2 rounded-2 bg-light border border-light-subtle h-100 overflow-auto" style="min-height: 100px;">
                                    <div id="modal_documentos_iconos" class="d-flex flex-wrap gap-1"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 d-none">
                            <input type="hidden" name="contiene_mipres" id="input_contiene_mipres_hidden" value="NO">
                            <div class="card border border-2 border-primary border-opacity-25 bg-white rounded-3 p-3 shadow-sm" id="card-mipres-selector">
                                <div class="row g-2 mt-1">
                                    <div class="col-sm-6">
                                        <input type="radio" class="btn-check" name="contiene_mipres_opt" id="mipres_si" value="SI" autocomplete="off">
                                        <label class="btn btn-outline-success w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm" for="mipres_si">
                                            <i class="fa-solid fa-circle-check fs-5"></i> <span>SÍ Contiene MIPRES</span>
                                        </label>
                                    </div>
                                    <div class="col-sm-6">
                                        <input type="radio" class="btn-check" name="contiene_mipres_opt" id="mipres_no" value="NO" autocomplete="off" checked>
                                        <label class="btn btn-outline-secondary w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2 rounded-3 shadow-sm" for="mipres_no">
                                            <i class="fa-solid fa-circle-xmark fs-5"></i> <span>NO Contiene MIPRES</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                                <label class="form-label fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-file-pdf text-danger fs-5"></i>
                                    <span>Adjuntar PDF de Orden Transcrita <span class="text-danger fw-bold">* (Obligatorio)</span></span>
                                </label>
                                <input type="file" name="pdf_transcripcion" id="input_pdf_transcripcion" class="form-control form-control-lg border-2 border-primary border-opacity-50 rounded-3" accept=".pdf" required onchange="validarPdfTranscripcion(event)">
                                <div class="form-text mt-1 text-muted small">
                                    <i class="fa-solid fa-shield-halved text-success me-1"></i> El sistema leerá el texto digital del PDF y verificará automáticamente si coincide con la cédula del paciente.
                                </div>
                                <div id="pdf-validation-status" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-3 px-4 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary fw-semibold px-3 rounded-2" data-bs-dismiss="modal" onclick="liberarBloqueoActual()">
                        <i class="fa-solid fa-xmark me-1"></i> Cancelar / Liberar Turno
                    </button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-2 shadow-sm" id="btn-submit-transcripcion">
                        <i class="fa-solid fa-paper-plane me-1"></i> Enviar a Monitoreo <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalHistorialPaciente" tabindex="-1" aria-hidden="true" style="z-index: 1065 !important;">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="pointer-events: auto !important;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="pointer-events: auto !important;">
            <div class="modal-header modal-header-gradient text-white py-3 px-4 border-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 p-2 rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fa-solid fa-clock-rotate-left fs-4 text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="historial_modal_title">Historial Clínico de Atenciones del Paciente</h5>
                        <small class="text-white-50" id="historial_modal_subtitle">Consulte las fórmulas y entregas anteriores para este paciente</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" onclick="cerrarHistorialYAbrirTranscripcion()"></button>
            </div>
            <div class="modal-body p-4 bg-light bg-opacity-50">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5 shadow-sm" id="historial_avatar_iniciales" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-user-injured"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-bold text-uppercase">Paciente en Consulta</div>
                                <h5 class="fw-bold text-dark mb-0" id="historial_paciente_nombre_resumen">-</h5>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                    <span class="badge bg-light text-primary border font-monospace fw-bold fs-6" id="historial_paciente_doc_resumen">-</span>
                                    <span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 fw-semibold" id="historial_paciente_eps_resumen">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill fw-bold" id="historial_total_atenciones_badge">
                                <i class="fa-solid fa-folder-open me-1"></i> <span id="historial_count_txt">0</span> Atenciones Previas
                            </span>
                        </div>
                    </div>
                </div>
                <div id="historial_modal_loader" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary my-2" role="status" style="width: 3rem; height: 3rem;"></div>
                    <div class="text-muted fw-semibold mt-2">Consultando atenciones anteriores en el historial clínico...</div>
                </div>
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden" id="historial_table_container">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3"># Tiquete</th>
                                    <th class="py-3">Sede</th>
                                    <th class="py-3">Fecha & Hora</th>
                                    <th class="py-3">EPS</th>
                                    <th class="py-3">Estado de Atención</th>
                                    <th class="py-3">Responsables</th>
                                    <th class="text-end pe-4 py-3">Fórmulas & Documentos</th>
                                </tr>
                            </thead>
                            <tbody id="historial_paciente_tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-3 px-4 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary fw-semibold rounded-2 px-3" onclick="cancelarTodoYDesbloquear()">
                    <i class="fa-solid fa-xmark me-1"></i> Cancelar y Volver a la Lista
                </button>
                <button type="button" class="btn btn-primary fw-bold px-4 py-2 rounded-2 shadow-sm" onclick="cerrarHistorialYAbrirTranscripcion()">
                    <i class="fa-solid fa-file-pen me-1"></i> Continuar a Transcribir Orden <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVisorPDF" tabindex="-1" style="z-index: 1090;">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw; width: 95vw;">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden" style="height: 92vh; display: flex; flex-direction: column; background-color: #0f172a; border: 1px solid #334155;">
            <div class="modal-header py-2 bg-dark text-white flex-shrink-0 d-flex justify-content-between align-items-center border-bottom border-secondary">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="modal-title fw-bold mb-0 text-white" id="visorPDFTitulo">
                        <i class="fa-solid fa-file-pdf text-danger me-2"></i> Visor de Documento
                    </h6>
                </div>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <div class="btn-group btn-group-sm me-2" role="group" id="groupZoomControls">
                        <button type="button" class="btn btn-outline-info btn-sm fw-bold active" id="btnZoomFit" onclick="cambiarZoomVisor('Fit')" title="Página Completa">
                            <i class="fa-solid fa-expand me-1"></i> Completa
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm fw-semibold" id="btnZoomFitH" onclick="cambiarZoomVisor('FitH')" title="Ajustar al Ancho">
                            <i class="fa-solid fa-arrows-left-right me-1"></i> Ancho
                        </button>
                        <button type="button" class="btn btn-outline-light btn-sm fw-semibold" id="btnZoom100" onclick="cambiarZoomVisor('100')" title="Zoom 100%">
                            100%
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm me-2 d-none" role="group" id="groupImgControls">
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="zoomImagenVisor(0.15)" title="Acercar"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="zoomImagenVisor(-0.15)" title="Alejar"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="rotarImagenVisor()" title="Rotar 90°"><i class="fa-solid fa-rotate-right"></i> Rotar</button>
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="resetImagenVisor()" title="Restablecer"><i class="fa-solid fa-arrow-rotate-left"></i></button>
                    </div>
                    <a id="btnAbrirPDFFull" href="#" target="_blank" class="btn btn-sm btn-info fw-bold text-dark me-2 rounded-2">
                        <i class="fa-solid fa-up-right-from-square me-1"></i> Pestaña Nueva
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0 flex-grow-1 position-relative" id="containerVisorPDF" style="overflow: hidden; background-color: #1e293b;"></div>
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
    let isSwitchingModals = false;

    document.addEventListener('DOMContentLoaded', () => {
        ['modalHistorialPaciente', 'modalGestionarTranscripcion', 'modalVisorPDF'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.parentElement !== document.body) document.body.appendChild(el);
        });

        const modalGestionEl = document.getElementById('modalGestionarTranscripcion');
        if (modalGestionEl) {
            modalGestionEl.addEventListener('hidden.bs.modal', () => {
                if (!isSwitchingModals) liberarBloqueoActual();
            });
        }

        const modalVisorEl = document.getElementById('modalVisorPDF');
        if (modalVisorEl) {
            modalVisorEl.addEventListener('hidden.bs.modal', () => {
                const modalHistEl = document.getElementById('modalHistorialPaciente');
                const modalTransEl = document.getElementById('modalGestionarTranscripcion');
                if ((modalHistEl && modalHistEl.classList.contains('show')) || (modalTransEl && modalTransEl.classList.contains('show'))) {
                    document.body.classList.add('modal-open');
                }
            });
        }
        iniciarAutoRefresco();
    });

    function abrirVisorPDF(url, titulo) {
        if (!url) {
            modalAlert("No se encontró la ruta del archivo a visualizar.", "warning", "Documento No Disponible");
            return;
        }
        visorCurrentUrl = url.split('#')[0];
        const ext = visorCurrentUrl.split('.').pop().toLowerCase();
        document.getElementById('visorPDFTitulo').innerHTML = `<i class="fa-solid fa-file-contract text-info me-2"></i> ${escapeHtml(titulo)}`;
        const btnFull = document.getElementById('btnAbrirPDFFull');
        if (btnFull) btnFull.href = visorCurrentUrl;

        const groupZoom = document.getElementById('groupZoomControls');
        const groupImg = document.getElementById('groupImgControls');

        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
            visorCurrentType = 'image';
            if (groupZoom) groupZoom.classList.add('d-none');
            if (groupImg) groupImg.classList.remove('d-none');
            renderImagenVisor();
        } else {
            visorCurrentType = 'pdf';
            if (groupZoom) groupZoom.classList.remove('d-none');
            if (groupImg) groupImg.classList.add('d-none');
            renderPDFVisor('Fit');
        }

        const modalEl = document.getElementById('modalVisorPDF');
        if (modalEl && modalEl.parentElement !== document.body) document.body.appendChild(modalEl);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function renderPDFVisor(modoZoom = 'Fit') {
        const container = document.getElementById('containerVisorPDF');
        if (!container) return;
        ['btnZoomFit', 'btnZoomFitH', 'btnZoom100', 'btnZoom75'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) { btn.classList.remove('btn-outline-info', 'active', 'fw-bold'); btn.classList.add('btn-outline-light', 'fw-semibold'); }
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
            </object>`;
    }

    function cambiarZoomVisor(modoZoom) {
        if (visorCurrentType === 'pdf') renderPDFVisor(modoZoom);
    }

    function renderImagenVisor() {
        const container = document.getElementById('containerVisorPDF');
        if (!container) return;
        imgScale = 1; imgRotation = 0;
        container.innerHTML = `
            <div class="d-flex justify-content-center align-items-center h-100 p-2 overflow-auto bg-dark">
                <img src="${visorCurrentUrl}" id="imgVisorTarget" class="img-fluid rounded shadow-lg" style="max-height: 86vh; object-fit: contain; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); transform: scale(1) rotate(0deg);">
            </div>`;
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
        imgScale = 1; imgRotation = 0;
        aplicarTransformImagenVisor();
    }

    function aplicarTransformImagenVisor() {
        const img = document.getElementById('imgVisorTarget');
        if (!img) return;
        img.style.transform = `scale(${imgScale}) rotate(${imgRotation}deg)`;
    }

    let currentIngresoIdParaTranscripcion = null;

    function gestionarTranscripcion(id) {
        currentIngresoIdParaTranscripcion = id;
        const formData = new FormData();
        formData.append('id', id);
        formData.append('action', 'lock');
        fetch('api/lock_record.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    currentIngresoIdLock = id;
                    iniciarHeartbeat(id);
                    cargarDatosYVerificarHistorial(id);
                } else {
                    alert(res.message || 'El registro se encuentra bloqueado por otro usuario.');
                    refrescarListaTabla();
                }
            })
            .catch(err => {
                console.error("Error al bloquear registro:", err);
                cargarDatosYVerificarHistorial(id);
            });
    }

    function forzarDesbloqueo(id) {
        modalConfirm("¿Está seguro de forzar el desbloqueo de esta orden como Administrador?", () => {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'unlock');
            formData.append('force', '1');
            fetch('api/lock_record.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    modalAlert(res.message, res.status === 'ok' ? 'success' : 'info', 'Desbloqueo de Registro', () => { refrescarListaTabla(); });
                });
        }, null, 'Forzar Desbloqueo', 'Sí, Desbloquear', 'Cancelar');
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
        if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null; }
    }

    let currentPacienteEps = '';

    function cargarDatosYVerificarHistorial(id) {
        document.getElementById('modal_ingreso_id').value = id;
        const pdfInput = document.getElementById('input_pdf_transcripcion');
        if (pdfInput) pdfInput.value = '';
        const statusDiv = document.getElementById('pdf-validation-status');
        if (statusDiv) statusDiv.innerHTML = '';
        const btnSubmit = document.getElementById('btn-submit-transcripcion');
        if (btnSubmit) btnSubmit.disabled = false;

        const radioSi = document.getElementById('mipres_si');
        const radioNo = document.getElementById('mipres_no');
        if (radioSi) radioSi.checked = false;
        if (radioNo) radioNo.checked = false;
        const feedback = document.getElementById('mipres-validation-feedback');
        if (feedback) feedback.classList.add('d-none');
        const cardMipres = document.getElementById('card-mipres-selector');
        if (cardMipres) cardMipres.classList.remove('border-danger');

        fetch(`index.php?page=transcripcion&ajax_get_detail=1&id=${id}`)
            .then(res => res.json())
            .then(data => {
                currentPacienteId = data.paciente_id || 0;
                currentPacienteDocNumero = (data.numero_documento || '').trim();
                currentPacienteNombreCompleto = ((data.nombres || '') + ' ' + (data.apellidos || '')).trim();
                currentPacienteEps = (data.eps_nombre || '').trim();

                document.getElementById('modal_paciente_nombre').innerText = currentPacienteNombreCompleto;
                document.getElementById('modal_paciente_doc').innerText = (data.tipo_documento || '') + ' ' + currentPacienteDocNumero;
                document.getElementById('modal_paciente_eps').innerText = currentPacienteEps;

                let htmlDocs = '';
                if (data.documentos && data.documentos.length > 0) {
                    data.documentos.forEach(d => {
                        const rawTipo = (d.tipo_documento || '').toUpperCase();
                        const ruta = (d.ruta_archivo || '').toLowerCase();
                        const orig = (d.nombre_original || '').toLowerCase();
                        const fullSearch = (rawTipo + ' ' + ruta + ' ' + orig).toUpperCase();
                        let btnClass = 'btn-outline-secondary', icon = 'fa-file-lines', label = 'Documento';

                        if (fullSearch.includes('MIPRES') || fullSearch.includes('DIRECCIONAMIENTO')) { btnClass = 'btn-outline-purple'; icon = 'fa-file-waveform'; label = 'MIPRES'; }
                        else if (fullSearch.includes('CEDULA') || fullSearch.includes('IDENTIDAD')) { btnClass = 'btn-outline-primary'; icon = 'fa-id-card'; label = 'Cédula'; }
                        else if (fullSearch.includes('ORDEN') || fullSearch.includes('FORMULA') || fullSearch.includes('MEDICA')) { btnClass = 'btn-outline-danger'; icon = 'fa-file-medical'; label = 'Fórmula Médica'; }
                        else if (fullSearch.includes('AUTORIZACION') || fullSearch.includes('PODER')) { btnClass = 'btn-outline-warning text-dark'; icon = 'fa-file-shield'; label = 'Autorización'; }
                        else if (fullSearch.includes('HISTORIA') || fullSearch.includes('CLINICA')) { btnClass = 'btn-outline-info text-dark'; icon = 'fa-clipboard-user'; label = 'Historia Clínica'; }
                        else if (fullSearch.includes('DERECHOS') || fullSearch.includes('SAVIA')) { btnClass = 'btn-outline-info text-dark'; icon = 'fa-file-shield'; label = 'Derechos Savia'; }

                        htmlDocs += `<button type="button" class="btn btn-sm ${btnClass} me-1 mb-1 fw-bold shadow-sm d-inline-flex align-items-center gap-1 rounded-2" onclick="abrirVisorPDF('${d.ruta_archivo}', '${escapeHtml(label)}')"><i class="fa-solid ${icon}"></i> <span>Ver ${escapeHtml(label)}</span></button>`;
                    });
                } else {
                    htmlDocs = '<div class="text-muted small py-2"><i class="fa-solid fa-folder-open me-1"></i> Sin documentos adjuntos en el ingreso</div>';
                }
                document.getElementById('modal_documentos_iconos').innerHTML = htmlDocs;

                fetch(`index.php?page=transcripcion&ajax_get_historial=1&paciente_id=${currentPacienteId}&numero_documento=${encodeURIComponent(currentPacienteDocNumero)}`)
                    .then(res => res.json())
                    .then(res => {
                        const atencionesPrevias = (res.status === 'ok' && res.data) ? res.data.filter(row => row.id != id) : [];
                        const badge = document.getElementById('count_historial_badge');
                        const btnHist = document.getElementById('btn_abrir_historial_modal');
                        if (badge) badge.textContent = atencionesPrevias.length;
                        if (atencionesPrevias.length > 0) {
                            if (btnHist) btnHist.classList.remove('d-none');
                            mostrarHistorialModalDirecto(currentPacienteId, currentPacienteNombreCompleto, currentPacienteDocNumero, id, atencionesPrevias);
                        } else {
                            if (btnHist) btnHist.classList.add('d-none');
                            abrirModalTranscripcionDirecto();
                        }
                    })
                    .catch(() => { abrirModalTranscripcionDirecto(); });
            })
            .catch(err => console.error("Error al cargar detalles:", err));
    }

    function abrirModalTranscripcionDirecto() {
        const modalHistEl = document.getElementById('modalHistorialPaciente');
        if (modalHistEl) { const mHist = bootstrap.Modal.getInstance(modalHistEl); if (mHist) mHist.hide(); }
        const modalEl = document.getElementById('modalGestionarTranscripcion');
        if (modalEl && modalEl.parentElement !== document.body) document.body.appendChild(modalEl);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function mostrarHistorialModalDirecto(pacienteId, pacienteNombre, numeroDocumento, currentIngresoId, filasPrevias = null) {
        const modalTransEl = document.getElementById('modalGestionarTranscripcion');
        if (modalTransEl && modalTransEl.classList.contains('show')) { const mTrans = bootstrap.Modal.getInstance(modalTransEl); if (mTrans) mTrans.hide(); }

        const titleEl = document.getElementById('historial_modal_title');
        const subtitleEl = document.getElementById('historial_modal_subtitle');
        const loaderEl = document.getElementById('historial_modal_loader');
        const tbodyEl = document.getElementById('historial_paciente_tbody');
        const nombreResumen = document.getElementById('historial_paciente_nombre_resumen');
        const docResumen = document.getElementById('historial_paciente_doc_resumen');
        const epsResumen = document.getElementById('historial_paciente_eps_resumen');
        const avatarResumen = document.getElementById('historial_avatar_iniciales');
        const countTxt = document.getElementById('historial_count_txt');

        if (titleEl) titleEl.innerHTML = `Historial Clínico de Atenciones`;
        if (subtitleEl) subtitleEl.innerHTML = `Consultando historial de: <strong>${escapeHtml(pacienteNombre || '')}</strong>`;
        if (nombreResumen) nombreResumen.textContent = pacienteNombre || '-';
        if (docResumen) docResumen.textContent = numeroDocumento || '-';
        if (epsResumen) epsResumen.textContent = currentPacienteEps || 'EPS Registrada';
        if (avatarResumen) {
            const parts = (pacienteNombre || '').trim().split(' ');
            const initials = parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : (pacienteNombre ? pacienteNombre[0].toUpperCase() : 'P');
            avatarResumen.innerHTML = `<span>${escapeHtml(initials)}</span>`;
        }

        const modalEl = document.getElementById('modalHistorialPaciente');
        if (modalEl && modalEl.parentElement !== document.body) document.body.appendChild(modalEl);
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        if (filasPrevias) {
            if (countTxt) countTxt.textContent = filasPrevias.length;
            renderizarFilasHistorial(filasPrevias, tbodyEl);
        } else {
            if (tbodyEl) tbodyEl.innerHTML = '';
            if (loaderEl) loaderEl.classList.remove('d-none');
            fetch(`index.php?page=transcripcion&ajax_get_historial=1&paciente_id=${pacienteId}&numero_documento=${encodeURIComponent(numeroDocumento || '')}`)
                .then(res => res.json())
                .then(res => {
                    if (loaderEl) loaderEl.classList.add('d-none');
                    if (res.status === 'ok' && res.data) {
                        const filtradas = currentIngresoId ? res.data.filter(r => r.id != currentIngresoId) : res.data;
                        if (countTxt) countTxt.textContent = filtradas.length;
                        renderizarFilasHistorial(filtradas, tbodyEl);
                    }
                })
                .catch(err => {
                    if (loaderEl) loaderEl.classList.add('d-none');
                    if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> Error al cargar el historial.</td></tr>';
                });
        }
    }

    function escapeJs(str) {
        if (!str) return '';
        return str.toString().replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function renderizarFilasHistorial(filas, tbodyEl) {
        if (!tbodyEl) return;
        if (!filas || filas.length === 0) {
            tbodyEl.innerHTML = `<tr><td colspan="6" class="text-center py-5"><div class="py-4"><div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-3"><i class="fa-solid fa-folder-open fs-2"></i></div><h6 class="fw-bold text-dark mb-1">No se encontraron atenciones previas</h6><p class="text-muted small mb-3">Este paciente no registra entregas o transcripciones anteriores en el sistema.</p><button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-2 rounded-2 shadow-sm" onclick="cerrarHistorialYAbrirTranscripcion()"><i class="fa-solid fa-file-pen me-1"></i> Continuar a Transcribir Orden <i class="fa-solid fa-arrow-right ms-1"></i></button></div></td></tr>`;
            return;
        }
        let html = '';
        filas.forEach(row => {
            let btnsDocs = '';
            const ticketSafe = escapeJs(row.ticket_numero || '');
            if (row.pdf_formula_final_url) {
                const urlSafe = escapeJs(row.pdf_formula_final_url);
                btnsDocs += `<button type="button" class="btn btn-sm btn-success fw-bold me-1 mb-1 shadow-sm rounded-2 d-inline-flex align-items-center gap-1" onclick="abrirVisorPDF('${urlSafe}', 'Fórmula Entregada - ${ticketSafe}')"><i class="fa-solid fa-file-invoice-dollar"></i> <span>Ver Fórmula Entregada</span></button>`;
            }
            if (row.pdf_transcripcion_url) {
                const urlSafe = escapeJs(row.pdf_transcripcion_url);
                btnsDocs += `<button type="button" class="btn btn-sm btn-primary fw-bold me-1 mb-1 shadow-sm rounded-2 d-inline-flex align-items-center gap-1" onclick="abrirVisorPDF('${urlSafe}', 'Fórmula Transcrita - ${ticketSafe}')"><i class="fa-solid fa-file-pdf"></i> <span>Ver Orden Transcrita</span></button>`;
            }
            if (row.pdf_mipres_url) {
                const urlSafe = escapeJs(row.pdf_mipres_url);
                btnsDocs += `<button type="button" class="btn btn-sm btn-outline-purple fw-bold me-1 mb-1 shadow-sm rounded-2 d-inline-flex align-items-center gap-1" onclick="abrirVisorPDF('${urlSafe}', 'MIPRES - ${ticketSafe}')"><i class="fa-solid fa-file-waveform"></i> <span>Ver MIPRES</span></button>`;
            }
            const pdfDerechos = row.pdf_validacion_derechos_url || row.pdf_derechos_url;
            if (pdfDerechos) {
                const urlSafe = escapeJs(pdfDerechos);
                btnsDocs += `<button type="button" class="btn btn-sm btn-outline-info text-dark fw-bold me-1 mb-1 shadow-sm rounded-2 d-inline-flex align-items-center gap-1" onclick="abrirVisorPDF('${urlSafe}', 'Validación Derechos - ${ticketSafe}')"><i class="fa-solid fa-file-shield"></i> <span>Ver Derechos Savia</span></button>`;
            }
            if (row.estado_tramite === 'ENTREGADO' || row.firma_paciente_url) {
                btnsDocs += `<a href="index.php?page=imprimir_acta&id=${row.id}" target="_blank" class="btn btn-sm btn-outline-dark fw-bold mb-1 shadow-sm rounded-2 d-inline-flex align-items-center gap-1"><i class="fa-solid fa-signature"></i> <span>Ver Acta Firmada</span></a>`;
            }
            if (!btnsDocs) btnsDocs = '<span class="text-muted small">Sin documentos adjuntos</span>';

            html += `<tr>
                <td class="ps-4 py-3"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace fs-6 fw-bold">${escapeHtml(row.ticket_numero)}</span></td>
                <td><span class="badge bg-light text-dark border"><i class="fa-solid fa-location-dot text-warning me-1"></i> ${escapeHtml(row.nombre_sede || 'Sede Principal')}</span></td>
                <td><div class="fw-semibold text-dark">${formatHora(row.fecha_ingreso)}</div><small class="text-muted">${formatFecha(row.fecha_ingreso)}</small></td>
                <td><span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 px-2 py-1 fw-semibold">${escapeHtml(row.eps_nombre)}</span></td>
                <td>${getEstadoBadge(row.estado_tramite)}</td>
                <td><div class="small"><i class="fa-solid fa-user me-1 text-muted"></i> <strong>Orientador:</strong> ${escapeHtml(row.orientador_nombre || 'SISPAM')}</div><div class="small text-muted"><i class="fa-solid fa-boxes-packing me-1"></i> <strong>Alistador:</strong> ${escapeHtml(row.alistador_nombre || 'N/A')}</div></td>
                <td class="text-end pe-4">${btnsDocs}</td>
            </tr>`;
        });
        tbodyEl.innerHTML = html;
    }

    function formatFecha(fechaStr) {
        if (!fechaStr) return '';
        try { const d = new Date(fechaStr.replace(/-/g, '/')); return isNaN(d.getTime()) ? fechaStr : d.toLocaleDateString('es-CO'); } catch (e) { return fechaStr; }
    }

    function cerrarHistorialYAbrirTranscripcion() {
        isSwitchingModals = true;
        const modalHistEl = document.getElementById('modalHistorialPaciente');
        const modalTransEl = document.getElementById('modalGestionarTranscripcion');
        if (modalHistEl) { const mHist = bootstrap.Modal.getInstance(modalHistEl) || bootstrap.Modal.getOrCreateInstance(modalHistEl); mHist.hide(); }
        setTimeout(() => {
            isSwitchingModals = false;
            document.querySelectorAll('.modal-backdrop').forEach((b, i) => { if (i > 0) b.remove(); });
            const mTrans = bootstrap.Modal.getInstance(modalTransEl) || bootstrap.Modal.getOrCreateInstance(modalTransEl);
            mTrans.show();
        }, 200);
    }

    function abrirHistorialDesdeTranscripcion() {
        isSwitchingModals = true;
        const modalTransEl = document.getElementById('modalGestionarTranscripcion');
        if (modalTransEl) { const mTrans = bootstrap.Modal.getInstance(modalTransEl) || bootstrap.Modal.getOrCreateInstance(modalTransEl); mTrans.hide(); }
        setTimeout(() => {
            isSwitchingModals = false;
            document.querySelectorAll('.modal-backdrop').forEach((b, i) => { if (i > 0) b.remove(); });
            mostrarHistorialModalDirecto(currentPacienteId, currentPacienteNombreCompleto, currentPacienteDocNumero, currentIngresoIdParaTranscripcion);
        }, 200);
    }

    function cancelarTodoYDesbloquear() {
        isSwitchingModals = false;
        const modalHistEl = document.getElementById('modalHistorialPaciente');
        const modalTransEl = document.getElementById('modalGestionarTranscripcion');
        const modalVisorEl = document.getElementById('modalVisorPDF');
        if (modalHistEl) { const m = bootstrap.Modal.getInstance(modalHistEl); if (m) m.hide(); }
        if (modalTransEl) { const m = bootstrap.Modal.getInstance(modalTransEl); if (m) m.hide(); }
        if (modalVisorEl) { const m = bootstrap.Modal.getInstance(modalVisorEl); if (m) m.hide(); }
        setTimeout(() => { limpiarModalesYBackdrops(); liberarBloqueoActual(); }, 200);
    }

    function limpiarModalesYBackdrops() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const formT = document.getElementById('formTranscripcion');
        if (formT) {
            formT.addEventListener('submit', function(e) {
                const pdfInput = document.getElementById('input_pdf_transcripcion');
                const statusDiv = document.getElementById('pdf-validation-status');
                if (!pdfInput || !pdfInput.files || pdfInput.files.length === 0) {
                    e.preventDefault();
                    if (statusDiv) {
                        statusDiv.innerHTML = `<div class="alert alert-danger p-2 small fw-bold mt-2 shadow-sm border-0 d-flex align-items-center"><i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-danger"></i><div>⚠️ Debe adjuntar obligatoriamente el archivo PDF de la orden médica transcrita antes de enviar a Monitoreo.</div></div>`;
                    }
                    if (pdfInput) { pdfInput.classList.add('is-invalid', 'border-danger'); pdfInput.focus(); }
                    return false;
                }
            });
        }
    });

    async function validarPdfTranscripcion(event) {
        const file = event.target.files[0];
        const statusDiv = document.getElementById('pdf-validation-status');
        const btnSubmit = document.getElementById('btn-submit-transcripcion');
        if (!file) { if (statusDiv) statusDiv.innerHTML = ''; if (btnSubmit) btnSubmit.disabled = false; return; }
        if (!currentPacienteDocNumero) return;

        statusDiv.innerHTML = `<div class="alert alert-info p-2 small fw-bold mt-2 shadow-sm d-flex align-items-center"><i class="fa-solid fa-spinner fa-spin me-2 fs-5"></i> <div>Leyendo texto del PDF y comparando número de documento (${currentPacienteDocNumero})...</div></div>`;

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
            const targetDigits = currentPacienteDocNumero.replace(/\D/g, '');
            const pdfDigitsOnly = fullText.replace(/\D/g, '');

            if (targetDigits.length >= 4 && pdfDigitsOnly.includes(targetDigits)) {
                statusDiv.innerHTML = `<div class="alert alert-success p-2 small fw-bold mt-2 shadow-sm border-0 d-flex align-items-center"><i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i> <div>VALIDACIÓN EXITOSA: Se confirmó la Coincidencia Correcta del Documento (${currentPacienteDocNumero}) en el PDF transcrito.</div></div>`;
                if (btnSubmit) btnSubmit.disabled = false;
            } else {
                statusDiv.innerHTML = `<div class="alert alert-danger p-2 small fw-bold mt-2 shadow-sm border-0 d-flex align-items-start"><i class="fa-solid fa-circle-xmark fs-5 me-2 text-danger mt-1"></i><div><strong>ERROR CRÍTICO DE VALIDACIÓN DE PACIENTE:</strong><br>El número de documento <strong>${currentPacienteDocNumero}</strong> del paciente gestionado NO fue encontrado dentro del texto del archivo PDF adjuntado.<br><small>Se ha bloqueado el envío. Verifique que no esté subiendo la orden de otro paciente.</small></div></div>`;
                if (btnSubmit) btnSubmit.disabled = true;
            }
        } catch (e) {
            console.warn("Fallo lectura de texto plano PDF.js:", e);
            statusDiv.innerHTML = `<div class="alert alert-warning p-2 small fw-bold mt-2 shadow-sm border-0 d-flex align-items-center"><i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-warning"></i> <div>PDF adjuntado. (No se pudo extraer capa de texto plano automática para validar el documento, por favor confirme visualmente).</div></div>`;
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
            if (!currentIngresoIdLock) refrescarListaTabla();
        }, 4000);
    }

    function refrescarListaTabla() {
        fetch('index.php?page=transcripcion&ajax_get_list=1')
            .then(res => res.json())
            .then(res => { if (res.status === 'ok') renderizarTabla(res.data, res.user_id, res.es_admin); })
            .catch(err => console.error("Error al refrescar lista:", err));
    }

    function renderizarTabla(lista, currentUserId, esAdmin) {
        const tbody = document.getElementById('tabla-transcripcion-body');
        const badgeTotal = document.getElementById('badge-total-cola');
        const kpiTotal = document.getElementById('kpi-total-cola');
        const kpiPrio = document.getElementById('kpi-prioritarios');
        const kpiGest = document.getElementById('kpi-gestion');
        if (!tbody) return;

        let totalPrio = 0, totalGest = 0;
        if (lista && Array.isArray(lista)) {
            lista.forEach(r => {
                if ((r.prioridad || 'NORMAL') !== 'NORMAL') totalPrio++;
                if (r.locked_by_user_id) totalGest++;
            });
        }

        if (badgeTotal) badgeTotal.textContent = `${lista.length} En Cola`;
        if (kpiTotal) kpiTotal.textContent = lista.length;
        if (kpiPrio) kpiPrio.textContent = totalPrio;
        if (kpiGest) kpiGest.textContent = totalGest;

        if (!lista || lista.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fa-solid fa-inbox fs-2 mb-2 d-block text-secondary opacity-50"></i>No hay órdenes pendientes en la lista de transcripción.</td></tr>';
            return;
        }

        let html = '';
        lista.forEach(row => {
            const isLockedByMe = row.locked_by_user_id && row.locked_by_user_id == currentUserId;
            const isLockedByOther = row.locked_by_user_id && row.locked_by_user_id != currentUserId;
            
            const btnExportar = `<a href="index.php?page=transcripcion&exportar_excel_paciente=1&ingreso_id=${row.id}" class="btn btn-sm btn-success fw-semibold me-1" title="Descargar Excel para Qrystalos"><i class="fa-solid fa-file-excel"></i> Excel</a>`;

            let lockBadge = '', btnAccion = '';

            if (isLockedByOther) {
                lockBadge = `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fa-solid fa-lock me-1"></i> Bloqueado: ${escapeHtml(row.locked_by_nombre || 'Otro')}</span>`;
                let btnAdmin = esAdmin ? `<button class="btn btn-sm btn-outline-danger" title="Forzar Desbloqueo (Admin)" onclick="forzarDesbloqueo(${row.id})"><i class="fa-solid fa-key"></i></button>` : '';
                btnAccion = `<div class="d-flex justify-content-end gap-1">${btnExportar}<button class="btn btn-sm btn-secondary opacity-75" disabled><i class="fa-solid fa-lock me-1"></i> Ocupado</button>${btnAdmin}</div>`;
            } else if (isLockedByMe) {
                lockBadge = `<span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1 fw-semibold"><i class="fa-solid fa-user-gear me-1 text-warning"></i> En gestión por ti</span>`;
                btnAccion = `<div class="d-flex justify-content-end gap-1">${btnExportar}<button class="btn btn-sm btn-warning fw-bold text-dark shadow-sm" onclick="gestionarTranscripcion(${row.id})"><i class="fa-solid fa-folder-open me-1"></i> Continuar</button></div>`;
            } else {
                lockBadge = `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-semibold"><i class="fa-solid fa-lock-open me-1"></i> Disponible</span>`;
                btnAccion = `<div class="d-flex justify-content-end gap-1">${btnExportar}<button class="btn btn-sm btn-primary fw-bold shadow-sm px-3" onclick="gestionarTranscripcion(${row.id})"><i class="fa-solid fa-keyboard me-1"></i> Transcribir</button></div>`;
            }

            let prioBadge = getPrioridadBadge(row.prioridad);
            html += `<tr>
                <td class="ps-4 py-3"><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 font-monospace fs-6 fw-bold">${escapeHtml(row.ticket_numero)}</span></td>
                <td><span class="badge bg-light text-dark border"><i class="fa-solid fa-location-dot text-warning me-1"></i> ${escapeHtml(row.nombre_sede || 'Sede Principal')}</span></td>
                <td><div class="fw-semibold text-dark">${formatHora(row.fecha_ingreso)}</div><small class="text-muted">${new Date(row.fecha_ingreso).toLocaleDateString('es-CO')}</small></td>
                <td><div class="fw-bold text-dark">${escapeHtml(row.nombres + ' ' + row.apellidos)} ${prioBadge}</div><small class="text-muted">${escapeHtml(row.tipo_documento + ' ' + row.numero_documento)}</small></td>
                <td><span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 px-2 py-1 fw-semibold">${escapeHtml(row.eps_nombre)}</span></td>
                <td>${getEstadoBadge(row.estado_tramite)}</td>
                <td>${lockBadge}</td>
                <td class="text-end pe-4">${btnAccion}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function getPrioridadBadge(prioridad) {
        switch (prioridad) {
            case 'TERCERA_EDAD': return '<span class="badge bg-warning text-dark ms-1 rounded-pill"><i class="fa-solid fa-person-cane me-1"></i> 👴 Tercera Edad</span>';
            case 'EMBARAZADA': return '<span class="badge bg-danger text-white ms-1 rounded-pill"><i class="fa-solid fa-person-pregnant me-1"></i> 🤰 Embarazada</span>';
            case 'DISCAPACIDAD': return '<span class="badge bg-info text-dark ms-1 rounded-pill"><i class="fa-solid fa-wheelchair me-1"></i> ♿ Discapacidad</span>';
            case 'NIÑO_LACTANTE': return '<span class="badge bg-primary text-white ms-1 rounded-pill"><i class="fa-solid fa-baby me-1"></i> 👶 Niño/Lactante</span>';
            case 'OTRO_PREFERENCIAL': return '<span class="badge bg-warning text-dark ms-1 rounded-pill"><i class="fa-solid fa-star me-1"></i> ⭐ Preferencial</span>';
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
            case 'INGRESADO': return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1"><i class="fa-solid fa-user-clock me-1"></i> Ingresado</span>';
            case 'EN_TRANSCRIPCION': return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="fa-solid fa-keyboard me-1"></i> En Transcripción</span>';
            case 'TRANSCRITO': case 'TRANSCRITO_COMPLETO': return '<span class="badge bg-info bg-opacity-10 text-dark border border-info border-opacity-25 px-2 py-1"><i class="fa-solid fa-file-signature me-1 text-info"></i> Transcrito</span>';
            case 'TRANSCRITO_PENDIENTE': return '<span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1"><i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i> Con Pendientes</span>';
            case 'VERIFICADO': case 'VERIFICADA': return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="fa-solid fa-check-to-slot me-1"></i> Verificado</span>';
            case 'ALISTADO': case 'ESPERA_ENTREGA': return '<span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2 py-1"><i class="fa-solid fa-boxes-packing me-1 text-warning"></i> Alistado</span>';
            case 'EN_ENTREGA': return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fa-solid fa-bell me-1"></i> En Entrega</span>';
            case 'ENTREGADO': return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Entregado</span>';
            case 'SIN_STOCK': return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fa-solid fa-boxes-packing me-1"></i> Sin Stock</span>';
            default: return `<span class="badge bg-light text-dark border">${escapeHtml(estado)}</span>`;
        }
    }
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>