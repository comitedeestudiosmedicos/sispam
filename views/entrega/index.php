<?php
require_once __DIR__ . '/../../config/app.php';
check_role('entrega');

require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/ModuloEntrega.php';

$ingresoModel = new Ingreso();
$modModel     = new ModuloEntrega();

$mensaje = '';
$error = '';
$active_sede = $_SESSION['active_sede_id'] ?? $_SESSION['sede_id'] ?? null;

// AJAX Endpoint: Buscar paciente por cédula o tiquete
if (isset($_GET['ajax_buscar']) || (isset($_POST['action']) && $_POST['action'] === 'buscar_paciente')) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    session_write_close();
    try {
        $term = trim($_GET['query'] ?? $_POST['query'] ?? '');
        $resultados = $ingresoModel->buscarParaEntrega($term, $active_sede);
        echo json_encode([
            'status' => 'ok',
            'data' => $resultados ?: []
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'data' => []
        ]);
    }
    exit;
}

// Procesar búsqueda tanto por GET/POST tradicional como por AJAX
$busquedaTermino = trim($_GET['query'] ?? $_POST['query'] ?? $_GET['busqueda'] ?? $_POST['busqueda'] ?? '');
$pacientesBuscados = [];
if (!empty($busquedaTermino)) {
    $pacientesBuscados = $ingresoModel->buscarParaEntrega($busquedaTermino, $active_sede);
}

// AJAX Endpoint: Iniciar llamado a Turnero 2 y asignar módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'llamar_turno_entrega') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $ingreso_id     = intval($_POST['ingreso_id'] ?? 0);
    $modulo_nombre  = trim($_POST['modulo_nombre'] ?? 'MÓDULO 1');
    if ($ingreso_id > 0) {
        $exito = $ingresoModel->iniciarLlamadoEntrega($ingreso_id, $modulo_nombre, $_SESSION['user_id']);
        echo json_encode([
            'status' => $exito ? 'ok' : 'error',
            'message' => $exito ? "Paciente llamado exitosamente al {$modulo_nombre}." : "No se pudo actualizar el turno."
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID de orden inválido.']);
    }
    exit;
}

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
            $mensaje = "¡Entrega finalizada con éxito! Se adjuntó la fórmula final y los soportes de Savia Salud / Mipres en el expediente. <a href='index.php?page=imprimir_acta&id={$ingreso_id}' target='_blank' class='btn btn-sm btn-success ms-2 fw-bold shadow-sm'><i class='fa-solid fa-print me-1'></i> Imprimir Acta Firmada + PDFs</a>";
        } else {
            $error = 'Ocurrió un error al guardar la entrega final.';
        }
    } else {
        $error = 'Es obligatorio capturar la firma digital del paciente antes de finalizar la entrega.';
    }
}

$modulos_activos = $modModel->getActivos($active_sede);
$ultimasEntregas = $ingresoModel->getUltimasEntregasHoy($active_sede, null, 8);

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
    /* Jerarquía y Capas de Entrega */
    #modalFirmaDigital {
        --bs-modal-zindex: 1070 !important;
        --bs-backdrop-zindex: 1050 !important;
        z-index: 1070 !important;
    }
    #modalFirmaDigital.modal {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        pointer-events: auto !important;
    }
    #modalFirmaDigital .modal-dialog {
        z-index: 1071 !important;
        pointer-events: auto !important;
    }
    #modalFirmaDigital .modal-content {
        z-index: 1072 !important;
        pointer-events: auto !important;
    }
    .modal-backdrop {
        z-index: 1050 !important;
        pointer-events: none !important;
    }
</style>

<div class="row mb-3 align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold text-primary mb-1">
            <i class="fa-solid fa-hand-holding-medical me-2"></i> Módulo de Facturación, Entrega & Firma Digital
            <span class="badge bg-light text-dark border ms-2 fs-6 fw-bold">
                <i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($_SESSION['active_sede_nombre'] ?? 'Sede Principal') ?>
            </span>
        </h4>
        <p class="text-muted small mb-0">
            Búsqueda directa por tiquete o cédula, llamado a pantalla por ventanilla, validación de empaque y captura de firma.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-2 mt-md-0">
        <div class="d-inline-flex align-items-center bg-white p-2 rounded-3 shadow-sm border">
            <label for="select-mi-modulo" class="fw-bold text-dark small me-2 mb-0">
                <i class="fa-solid fa-desktop text-primary me-1"></i> Mi Módulo / Ventanilla:
            </label>
            <select id="select-mi-modulo" class="form-select form-select-sm fw-bold border-primary text-primary" style="width: auto;" onchange="guardarModuloLocal(this.value)">
                <?php if (!empty($modulos_activos)): ?>
                    <?php foreach ($modulos_activos as $mod): ?>
                        <option value="<?= htmlspecialchars($mod['nombre_modulo'] ?? $mod['nombre']) ?>">
                            <?= htmlspecialchars($mod['nombre_modulo'] ?? $mod['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="MÓDULO 1">MÓDULO 1</option>
                    <option value="MÓDULO 2">MÓDULO 2</option>
                    <option value="MÓDULO 3">MÓDULO 3</option>
                    <option value="VENTANILLA PREFERENCIAL">VENTANILLA PREFERENCIAL</option>
                <?php endif; ?>
            </select>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small shadow-sm"><i class="fa-solid fa-circle-check me-1"></i> <?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small shadow-sm"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- PANEL DE BÚSQUEDA PRINCIPAL (Sin lista abierta) -->
<div class="card card-glass border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form id="formBuscarEntrega" method="GET" action="index.php" onsubmit="ejecutarBusquedaEntrega(event)">
            <input type="hidden" name="page" value="entrega">
            <div class="row g-3 align-items-center">
                <div class="col-lg-9 col-md-8">
                    <label for="inputBuscarEntrega" class="form-label fw-bold text-dark fs-6 mb-2">
                        <i class="fa-solid fa-barcode text-primary me-2"></i> Ingrese Número de Cédula, Tiquete o Nombre del Paciente:
                    </label>
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-white text-primary border-primary"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" 
                               name="query"
                               id="inputBuscarEntrega" 
                               class="form-control border-primary fw-bold" 
                               value="<?= htmlspecialchars($busquedaTermino) ?>"
                               placeholder="Ej: 1036780004  o  TK-260827-0012" 
                               autocomplete="off" 
                               autofocus>
                        <button type="submit" class="btn btn-primary fw-bold px-4">
                            <i class="fa-solid fa-search me-1"></i> Buscar Orden
                        </button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 text-center text-md-start">
                    <div class="p-3 bg-light rounded-3 border text-muted small mt-md-4">
                        <i class="fa-solid fa-bolt text-warning me-1"></i> <strong>Atajo Rápido:</strong><br>
                        Tome el paquete de su estante y use la pistola de código de barras o presione <strong>Enter</strong>.
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- CONTENEDOR DINÁMICO DE RESULTADO DEL PACIENTE ENCONTRADO -->
<div id="contenedorResultadoEntrega" class="<?= empty($busquedaTermino) ? 'd-none' : '' ?> mb-4">
    <?php if (!empty($busquedaTermino)): ?>
        <?php if (empty($pacientesBuscados)): ?>
            <div class="card card-glass border-0 shadow-sm text-center py-4">
                <i class="fa-solid fa-circle-exclamation text-warning fs-1 mb-2"></i>
                <h5 class="fw-bold text-dark">No se encontró ninguna orden con "<?= htmlspecialchars($busquedaTermino) ?>"</h5>
                <p class="text-muted small mb-0">Verifique que el número de cédula o tiquete esté correcto y que la orden haya sido ingresada en el sistema.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pacientesBuscados as $row): 
                $esPreferencial = (!empty($row['prioridad']) && $row['prioridad'] !== 'NORMAL');
                $estado = $row['estado_tramite'];
                $estaLlamado = ($estado === 'EN_ENTREGA');
                $estado = $row['estado_tramite'];
                $estaLlamado = ($estado === 'EN_ENTREGA');
                $estaEntregado = ($estado === 'ENTREGADO');
                $estaListoEntrega = in_array($estado, ['ALISTADO', 'GESTIONADO', 'ESPERA_ENTREGA', 'EN_ENTREGA', 'ENTREGADO']);
                $enProcesoPrevio = !$estaListoEntrega;
            ?>
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 bg-white" id="card-orden-<?= $row['id'] ?>">
                <!-- Header -->
                <div class="p-3 px-4 d-flex flex-wrap justify-content-between align-items-center <?= $estaEntregado ? 'bg-secondary' : ($enProcesoPrevio ? 'bg-secondary' : ($estaLlamado ? 'bg-success' : 'bg-primary')) ?> bg-gradient text-white" id="card-header-<?= $row['id'] ?>">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="bg-white text-dark fw-bold font-monospace px-3 py-1 rounded-pill shadow-sm fs-5">
                            <i class="fa-solid fa-receipt text-primary me-1"></i> <?= htmlspecialchars($row['ticket_numero']) ?>
                        </span>
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($row['nombre_sede'] ?? 'Sede Principal') ?>
                        </span>
                        <?php if ($esPreferencial): ?>
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-star me-1"></i> Preferencial</span>
                        <?php endif; ?>
                        <span class="badge bg-black bg-opacity-25 rounded-pill px-3 py-1 small">
                            <i class="fa-solid fa-clock me-1"></i> <?= date('d/m/Y h:i A', strtotime($row['created_at'])) ?>
                        </span>
                    </div>
                    <div id="badge-estado-<?= $row['id'] ?>">
                        <?php if ($estaEntregado): ?>
                            <span class="badge bg-dark text-white px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-check-double me-1"></i> Entregado & Finalizado</span>
                        <?php elseif ($enProcesoPrevio): ?>
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-hourglass-half me-1"></i> En Preparación: <?= htmlspecialchars($estado) ?></span>
                        <?php elseif ($estaLlamado): ?>
                            <span class="badge bg-white text-success px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-bell me-1"></i> Llamado Activo en <?= htmlspecialchars($row['modulo_entrega_asignado'] ?? 'Ventanilla') ?></span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-box-archive me-1"></i> Paquete en Estante (Pendiente de Llamar)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-4">
                    <div class="row g-4 align-items-center">
                        <!-- Patient Info -->
                        <div class="col-lg-7 col-md-12">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-none d-sm-flex align-items-center justify-content-center shadow-sm" style="width: 54px; height: 54px;">
                                    <i class="fa-solid fa-user-check fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h4 class="fw-bold text-dark mb-1">
                                        <?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?>
                                    </h4>
                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <i class="fa-solid fa-id-card text-muted me-1"></i> <?= htmlspecialchars($row['tipo_documento'] . ' ' . $row['numero_documento']) ?>
                                        </span>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 fw-bold">
                                            <i class="fa-solid fa-hospital me-1"></i> <?= htmlspecialchars($row['eps_nombre'] ?? 'Savia Salud') ?>
                                        </span>
                                        <?php if (!empty($row['telefono'])): ?>
                                            <span class="text-muted small"><i class="fa-solid fa-phone text-muted me-1"></i> <?= htmlspecialchars($row['telefono']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($row['direccion_residencia'] ?? 'Dirección no registrada') ?> <?= !empty($row['ciudad_residencia']) ? '• ' . htmlspecialchars($row['ciudad_residencia']) : '' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div class="col-lg-5 col-md-12 border-start-lg ps-lg-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-secondary text-uppercase">
                                        <i class="fa-solid fa-folder-open text-primary me-1"></i> Documentos & Soportes
                                    </span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if (!empty($row['documentos'])): ?>
                                        <?php foreach ($row['documentos'] as $d): ?>
                                            <a href="<?= htmlspecialchars($d['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-1"><i class="fa-solid fa-file-pdf me-1"></i> <?= htmlspecialchars($d['tipo_documento']) ?></a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($row['pdf_alistamiento'])): ?>
                                        <a href="<?= htmlspecialchars($row['pdf_alistamiento']) ?>" target="_blank" class="btn btn-sm btn-outline-success mb-1"><i class="fa-solid fa-boxes-packing me-1"></i> PDF Alistamiento</a>
                                    <?php endif; ?>
                                    <?php if (empty($row['documentos']) && empty($row['pdf_alistamiento'])): ?>
                                        <span class="text-muted small">Sin archivos adjuntos</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sequential Action Buttons Footer -->
                    <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="small" id="guia-estado-<?= $row['id'] ?>">
                            <?php if ($estaEntregado): ?>
                                <span class="text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i> Esta orden ya fue entregada y cuenta con acta digital firmada.</span>
                            <?php elseif ($enProcesoPrevio): ?>
                                <span class="text-warning-emphasis fw-bold"><i class="fa-solid fa-circle-info me-1"></i> Esta orden aún se encuentra en etapa interna de preparación (<strong><?= htmlspecialchars($estado) ?></strong>). No está lista para entregar.</span>
                            <?php elseif ($estaLlamado): ?>
                                <span class="text-success fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Paciente llamado a <?= htmlspecialchars($row['modulo_entrega_asignado'] ?? 'Ventanilla') ?>. Listo para gestionar y firmar.</span>
                            <?php else: ?>
                                <span class="text-warning-emphasis fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Paso 1 Obligatorio:</strong> Debe llamar al paciente al Turnero 2 para habilitar la entrega.</span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2" id="acciones-orden-<?= $row['id'] ?>">
                            <?php if ($estaEntregado): ?>
                                <a href="index.php?page=imprimir_acta&id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-success btn-lg fw-bold px-4 shadow-sm">
                                    <i class="fa-solid fa-print me-2"></i> Ver Acta Firmada + PDFs
                                </a>
                            <?php elseif ($enProcesoPrevio): ?>
                                <button type="button" class="btn btn-secondary btn-lg fw-semibold px-4 shadow-sm opacity-75" disabled>
                                    <i class="fa-solid fa-clock me-2"></i> En Proceso: <?= htmlspecialchars($estado) ?>
                                </button>
                            <?php elseif ($estaLlamado): ?>
                                <button type="button" 
                                        class="btn btn-outline-warning btn-lg fw-bold text-dark px-3 shadow-sm"
                                        id="btn-llamar-<?= $row['id'] ?>"
                                        onclick="llamarTurnoATurnero(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ticket_numero']) ?>')">
                                    <i class="fa-solid fa-volume-high me-1"></i> 🔁 Re-llamar
                                </button>
                                <button type="button" 
                                        class="btn btn-success btn-lg fw-bold text-white px-4 shadow-sm"
                                        id="btn-gestionar-<?= $row['id'] ?>"
                                        onclick="abrirModalFirmaPorId(<?= $row['id'] ?>)">
                                    <i class="fa-solid fa-signature me-2"></i> ✍️ Gestionar Entrega & Firma
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        class="btn btn-warning btn-lg fw-bold text-dark px-4 shadow-sm"
                                        id="btn-llamar-<?= $row['id'] ?>"
                                        onclick="llamarTurnoATurnero(<?= $row['id'] ?>, '<?= htmlspecialchars($row['ticket_numero']) ?>')">
                                    <i class="fa-solid fa-bullhorn me-2"></i> 📢 Paso 1: Llamar a Turnero 2 a <span class="badge bg-dark text-warning ms-1 lbl-mi-modulo"><?= htmlspecialchars($modModel->getActivos()[0]['nombre'] ?? 'MÓDULO 1') ?></span>
                                </button>
                                <button type="button" 
                                        class="btn btn-secondary btn-lg fw-bold px-4 shadow-sm opacity-50"
                                        id="btn-gestionar-<?= $row['id'] ?>"
                                        disabled
                                        title="Primero debe llamar al paciente a su ventanilla">
                                    <i class="fa-solid fa-lock me-2"></i> ✍️ Paso 2: Gestionar Entrega & Firma
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <script>
                window.pacientesEncontradosCache = window.pacientesEncontradosCache || {};
                <?php foreach ($pacientesBuscados as $pItem): ?>
                window.pacientesEncontradosCache[<?= $pItem['id'] ?>] = <?= json_encode($pItem, JSON_UNESCAPED_UNICODE) ?>;
                <?php endforeach; ?>
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- HISTORIAL DE ÚLTIMAS ENTREGAS REALIZADAS HOY -->
<div class="card card-glass border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-clock-rotate-left me-2 text-info"></i> Últimas Entregas Finalizadas Hoy</h6>
        <span class="badge bg-secondary"><?= count($ultimasEntregas) ?> Registros</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tiquete</th>
                        <th>Sede</th>
                        <th>Paciente</th>
                        <th>Documento</th>
                        <th>EPS</th>
                        <th>Ventanilla</th>
                        <th>Hora Finalización</th>
                        <th class="text-end pe-3">Acta Firmada</th>
                    </tr>
                </thead>
                <tbody id="tablaUltimasEntregas">
                    <?php if (empty($ultimasEntregas)): ?>
                        <tr><td colspan="8" class="text-center py-3 text-muted">Aún no se han registrado entregas finalizadas en esta jornada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimasEntregas as $ult): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($ult['ticket_numero'] ?? '') ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($ult['nombre_sede'] ?? 'Sede Principal') ?>
                                </span>
                            </td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars(($ult['nombres'] ?? '') . ' ' . ($ult['apellidos'] ?? '')) ?></td>
                            <td class="text-muted"><?= htmlspecialchars(($ult['tipo_documento'] ?? '') . ' ' . ($ult['numero_documento'] ?? '')) ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($ult['eps_nombre'] ?? '') ?></span></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ult['modulo_entrega_asignado'] ?? 'Ventanilla') ?></span></td>
                            <td class="text-muted"><?= date('h:i A', strtotime($ult['updated_at'])) ?></td>
                            <td class="text-end pe-3">
                                <a href="index.php?page=imprimir_acta&id=<?= $ult['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success fw-bold">
                                    <i class="fa-solid fa-print me-1"></i> Ver Acta
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Firma Digital Táctil & Foto Paciente -->
<div class="modal fade" id="modalFirmaDigital" tabindex="-1" aria-hidden="true" style="--bs-modal-zindex: 1070; --bs-backdrop-zindex: 1050; z-index: 1070 !important;">
    <div class="modal-dialog modal-lg modal-dialog-centered my-4" style="pointer-events: auto !important;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="pointer-events: auto !important;">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fw-bold text-white mb-0"><i class="fa-solid fa-signature me-2 text-warning"></i> Entrega, Firma Digital & Registro Fotográfico del Paciente</h5>
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
                            <div class="col-md-4">
                                <span class="text-muted small">Tiquete de Atención:</span>
                                <div class="fw-bold text-primary fs-5" id="entrega_ticket_txt">-</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Paciente Recepcionado:</span>
                                <div class="fw-bold text-dark fs-5" id="entrega_paciente_txt">-</div>
                                <small class="text-muted" id="entrega_doc_txt"></small>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Sede de Atención:</span>
                                <div class="fw-bold text-dark fs-6" id="entrega_sede_txt">-</div>
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

                    <label class="form-label fw-semibold mb-1">
                        <i class="fa-solid fa-signature me-1 text-primary"></i> Captura de Firma Digital del Paciente:
                    </label>

                    <!-- PANEL DE INTEGRACIÓN TABLETA DIGITALIZADORA TOPAZ SYSTEMS (T-S460) -->
                    <div class="p-2 mb-2 bg-light rounded-3 border d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 shadow-sm" id="panelTopazSigWeb">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary p-2" id="badgeEstadoTopaz">
                                <i class="fa-solid fa-tablet me-1"></i> Pad Topaz: Comprobando...
                            </span>
                            <small class="text-muted" id="txtEstadoTopaz">Modelo SigLite LCD (T-S460)</small>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-primary small" onclick="abrirModalAyudaTopaz()" title="Ver instrucciones para instalar y activar la tableta Topaz">
                                <i class="fa-solid fa-circle-question"></i> ¿Cómo configurar?
                            </button>
                        </div>
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="btnActivarTopaz" onclick="activarPadTopaz()" title="Activa la pantallita LCD del Topaz para firmar con el lápiz">
                                <i class="fa-solid fa-pen-nib me-1"></i> 🖊️ Firmar en Pad Topaz
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" id="btnLimpiarTopaz" onclick="limpiarPadTopaz()" title="Borrar firma en la pantallita del Pad">
                                <i class="fa-solid fa-eraser me-1"></i> Limpiar Pad
                            </button>
                            <button type="button" class="btn btn-sm btn-success fw-bold" id="btnCapturarTopaz" onclick="capturarFirmaPadTopaz()" title="Transferir el trazo del pad al Acta de Entrega">
                                <i class="fa-solid fa-check me-1"></i> Pasar Firma al Acta
                            </button>
                        </div>
                    </div>
                    
                    <div class="signature-container text-center mb-2">
                        <canvas id="canvas-firma" class="signature-pad"></canvas>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnLimpiarFirma">
                            <i class="fa-solid fa-eraser me-1"></i> Borrar Firma en Pantalla
                        </button>
                        <span class="small text-muted"><i class="fa-solid fa-tablet-screen-button me-1"></i> Compatible con Topaz SigLite (T-S460), Wacom, iPad y pantallas táctiles</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="cerrarPadTopaz()">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="fa-solid fa-circle-check me-1"></i> Confirmar Entrega y Generar Acta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL AYUDA E INSTALACIÓN TOPAZ SIGWEB -->
<div class="modal fade" id="modalAyudaTopaz" tabindex="-1" style="z-index: 1080;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card-glass">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-tablet me-2"></i> Configuración de Tableta Digitalizadora Topaz (T-S460)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-3">
                    <i class="fa-solid fa-circle-info fs-3 me-3 text-primary"></i>
                    <div>
                        <strong>¿Por qué se requiere el software SigWeb?</strong><br>
                        Las tabletas Topaz transmiten firmas biométricas de forma segura. Para que el navegador web pueda comunicarse con la pantalla LCD del pad, se requiere tener instalado el software oficial gratuito <strong>Topaz SigWeb™</strong> en este computador.
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-check text-primary me-2"></i> Pasos para activar la tableta en 3 minutos:</h6>

                <ol class="list-group list-group-numbered mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-start py-3">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">1. Descargar e instalar SigWeb for Windows</div>
                            Descargue el instalador oficial desde el sitio web de Topaz Systems e instálelo en el computador de la ventanilla.
                        </div>
                        <a href="https://www.topazsystems.com/sigweb.html" target="_blank" class="btn btn-sm btn-primary fw-bold text-nowrap">
                            <i class="fa-solid fa-download me-1"></i> Descargar SigWeb
                        </a>
                    </li>
                    <li class="list-group-item py-3">
                        <div class="ms-2">
                            <div class="fw-bold">2. Seleccionar el modelo durante la instalación</div>
                            Cuando el instalador le pregunte su modelo:
                            <ul>
                                <li>Modelo: <strong>SigLite LCD 1x5</strong> (Serie <code>T-S460</code>)</li>
                                <li>Tipo de conexión: <strong>HID USB</strong> (o USB)</li>
                            </ul>
                        </div>
                    </li>
                    <li class="list-group-item py-3">
                        <div class="ms-2">
                            <div class="fw-bold">3. Reconocimiento de certificado seguro (HTTPS)</div>
                            Si su sistema usa conexión segura (HTTPS), abra el siguiente enlace de prueba en una pestaña nueva:
                            <div class="mt-2">
                                <a href="https://tablet.sigwebtablet.com:47290/SigWeb/GetDaysUntilCertificateExpires" target="_blank" class="badge bg-dark text-white p-2 text-decoration-none me-2">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Probar Puerto Seguro 47290
                                </a>
                                <a href="https://tablet.sigwebtablet.com:47289/SigWeb/GetDaysUntilCertificateExpires" target="_blank" class="badge bg-secondary text-white p-2 text-decoration-none">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Probar Puerto 47289
                                </a>
                            </div>
                            <small class="text-muted d-block mt-1">Si el navegador muestra advertencia de certificado local, haga clic en <em>"Avanzado"</em> y luego en <em>"Continuar a tablet.sigwebtablet.com (seguro)"</em>.</small>
                        </div>
                    </li>
                </ol>

                <div class="bg-light p-3 rounded-3 border">
                    <p class="mb-0 small text-muted">
                        <i class="fa-solid fa-lightbulb text-warning me-1"></i> <strong>Nota:</strong> Mientras realiza la instalación de SigWeb, el paciente puede firmar directamente en la pantalla con el ratón o pantalla táctil en el recuadro digital de SISPAM.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido / Cerrar</button>
            </div>
        </div>
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
let currentPacienteData = null;

document.addEventListener('DOMContentLoaded', () => {
    // Mover modal directamente a document.body para evitar que el backdrop quede encima
    const modalFirmaEl = document.getElementById('modalFirmaDigital');
    if (modalFirmaEl && modalFirmaEl.parentElement !== document.body) {
        document.body.appendChild(modalFirmaEl);
    }

    // 1. Cargar módulo persistente en el navegador
    const modGuardado = localStorage.getItem('sispam_modulo_entrega');
    const selectMod = document.getElementById('select-mi-modulo');
    if (modGuardado && selectMod) {
        selectMod.value = modGuardado;
    }

    // 2. Inicializar Canvas de Firma Digital
    canvas = document.getElementById('canvas-firma');
    ctx = canvas.getContext('2d');

    // Ajustar resolución del canvas
    canvas.width = canvas.offsetWidth || 700;
    canvas.height = canvas.offsetHeight || 200;

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

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function guardarModuloLocal(val) {
    localStorage.setItem('sispam_modulo_entrega', val);
    document.querySelectorAll('.lbl-mi-modulo').forEach(el => el.innerText = val);
}

function getMiModuloActual() {
    const sel = document.getElementById('select-mi-modulo');
    return sel ? sel.value : (localStorage.getItem('sispam_modulo_entrega') || 'MÓDULO 1');
}

function ejecutarBusquedaEntrega(e) {
    if (e) e.preventDefault();
    const input = document.getElementById('inputBuscarEntrega');
    const query = input ? input.value.trim() : '';
    if (!query) {
        alert('Por favor ingrese un número de cédula o tiquete para buscar.');
        if (input) input.focus();
        return;
    }

    const cont = document.getElementById('contenedorResultadoEntrega');
    cont.classList.remove('d-none');
    cont.innerHTML = `
        <div class="card card-glass border-0 shadow-sm text-center py-5">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <div class="fw-bold text-dark">Buscando orden en estante y estado de entrega...</div>
        </div>
    `;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 6000);

    fetch(`index.php?page=entrega&ajax_buscar=1&query=${encodeURIComponent(query)}`, { signal: controller.signal })
        .then(async res => {
            clearTimeout(timeoutId);
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Respuesta del servidor no válida:", text);
                throw new Error("Respuesta no válida del servidor.");
            }
        })
        .then(data => {
            if (data.status === 'ok' && data.data && data.data.length > 0) {
                renderizarResultadosEntrega(data.data);
            } else if (data.status === 'error') {
                cont.innerHTML = `
                    <div class="card card-glass border-0 shadow-sm text-center py-4 text-danger">
                        <i class="fa-solid fa-triangle-exclamation fs-1 mb-2"></i>
                        <h5 class="fw-bold">${escapeHtml(data.message || 'Error al buscar orden')}</h5>
                    </div>
                `;
            } else {
                cont.innerHTML = `
                    <div class="card card-glass border-0 shadow-sm text-center py-4">
                        <i class="fa-solid fa-circle-exclamation text-warning fs-1 mb-2"></i>
                        <h5 class="fw-bold text-dark">No se encontró ninguna orden con "${escapeHtml(query)}"</h5>
                        <p class="text-muted small mb-0">Verifique que el número de cédula o tiquete esté correcto y que la orden haya sido ingresada en el sistema.</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            clearTimeout(timeoutId);
            console.error("Error al buscar orden:", err);
            cont.innerHTML = `
                <div class="card card-glass border-0 shadow-sm text-center py-4 text-danger">
                    <i class="fa-solid fa-triangle-exclamation fs-1 mb-2"></i>
                    <h5 class="fw-bold">No se pudo completar la búsqueda vía rápida</h5>
                    <p class="text-muted small mb-3">${escapeHtml(err.name === 'AbortError' ? 'El servidor tardó más de lo esperado en responder.' : (err.message || 'Error de conexión'))}</p>
                    <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm" onclick="document.getElementById('formBuscarEntrega').submit()">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Realizar Búsqueda Directa del Servidor
                    </button>
                </div>
            `;
        });
}

function renderizarResultadosEntrega(lista) {
    const cont = document.getElementById('contenedorResultadoEntrega');
    cont.classList.remove('d-none');
    let html = '';
    window.pacientesEncontradosCache = {};

    const miModulo = getMiModuloActual();

    lista.forEach(row => {
        window.pacientesEncontradosCache[row.id] = row;

        const esPreferencial = (row.prioridad && row.prioridad !== 'NORMAL');
        const badgePrio = esPreferencial 
            ? `<span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold shadow-sm"><i class="fa-solid fa-star me-1"></i> Preferencial</span>`
            : ``;

        const estado = row.estado_tramite;
        const estaLlamado = (estado === 'EN_ENTREGA');
        const estaEntregado = (estado === 'ENTREGADO');

        let headerBgClass = 'bg-primary';
        let estadoBadge = '';
        let guiaFlujoHtml = '';
        let botonesAccionHtml = '';

        if (estaEntregado) {
            headerBgClass = 'bg-secondary';
            estadoBadge = `<span class="badge bg-dark text-white px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-check-double me-1"></i> Entregado & Finalizado</span>`;
            guiaFlujoHtml = `<span class="text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i> Esta orden ya fue entregada y cuenta con acta digital firmada.</span>`;
            botonesAccionHtml = `
                <a href="index.php?page=imprimir_acta&id=${row.id}" target="_blank" class="btn btn-outline-success btn-lg fw-bold px-4 shadow-sm">
                    <i class="fa-solid fa-print me-2"></i> Ver Acta Firmada + PDFs
                </a>
            `;
        } else if (estaLlamado) {
            headerBgClass = 'bg-success';
            estadoBadge = `<span class="badge bg-white text-success px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-bell me-1"></i> Llamado Activo en ${escapeHtml(row.modulo_entrega_asignado || miModulo)}</span>`;
            guiaFlujoHtml = `<span class="text-success fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Paciente llamado a ${escapeHtml(row.modulo_entrega_asignado || miModulo)}. Listo para gestionar y firmar.</span>`;
            botonesAccionHtml = `
                <button type="button" 
                        class="btn btn-outline-warning btn-lg fw-bold text-dark px-3 shadow-sm"
                        id="btn-llamar-${row.id}"
                        onclick="llamarTurnoATurnero(${row.id}, '${escapeHtml(row.ticket_numero)}')">
                    <i class="fa-solid fa-volume-high me-1"></i> 🔁 Re-llamar
                </button>
                <button type="button" 
                        class="btn btn-success btn-lg fw-bold text-white px-4 shadow-sm"
                        id="btn-gestionar-${row.id}"
                        onclick="abrirModalFirmaPorId(${row.id})">
                    <i class="fa-solid fa-signature me-2"></i> ✍️ Gestionar Entrega & Firma
                </button>
            `;
        } else {
            headerBgClass = 'bg-primary';
            estadoBadge = `<span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-box-archive me-1"></i> Paquete en Estante (Pendiente de Llamar)</span>`;
            guiaFlujoHtml = `<span class="text-warning-emphasis fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Paso 1 Obligatorio:</strong> Debe llamar al paciente al Turnero 2 para habilitar la entrega.</span>`;
            botonesAccionHtml = `
                <button type="button" 
                        class="btn btn-warning btn-lg fw-bold text-dark px-4 shadow-sm"
                        id="btn-llamar-${row.id}"
                        onclick="llamarTurnoATurnero(${row.id}, '${escapeHtml(row.ticket_numero)}')">
                    <i class="fa-solid fa-bullhorn me-2"></i> 📢 Paso 1: Llamar a Turnero 2 a <span class="badge bg-dark text-warning ms-1 lbl-mi-modulo">${escapeHtml(miModulo)}</span>
                </button>
                <button type="button" 
                        class="btn btn-secondary btn-lg fw-bold px-4 shadow-sm opacity-50"
                        id="btn-gestionar-${row.id}"
                        disabled
                        title="Primero debe llamar al paciente a su ventanilla">
                    <i class="fa-solid fa-lock me-2"></i> ✍️ Paso 2: Gestionar Entrega & Firma
                </button>
            `;
        }

        let docsHtml = '';
        if (row.documentos && row.documentos.length > 0) {
            row.documentos.forEach(d => {
                docsHtml += `<a href="${escapeHtml(d.ruta_archivo)}" target="_blank" class="btn btn-sm btn-outline-secondary mb-1 me-1"><i class="fa-solid fa-file-pdf me-1"></i> ${escapeHtml(d.tipo_documento)}</a>`;
            });
        }
        if (row.pdf_alistamiento) {
            docsHtml += `<a href="${escapeHtml(row.pdf_alistamiento)}" target="_blank" class="btn btn-sm btn-outline-success mb-1 me-1"><i class="fa-solid fa-boxes-packing me-1"></i> PDF Alistamiento</a>`;
        }
        if (!docsHtml) {
            docsHtml = '<span class="text-muted small">Sin archivos adjuntos</span>';
        }

        const fechaStr = row.created_at || '';

        html += `
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 bg-white" id="card-orden-${row.id}">
                <!-- Header -->
                <div class="p-3 px-4 d-flex flex-wrap justify-content-between align-items-center ${headerBgClass} bg-gradient text-white" id="card-header-${row.id}">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="bg-white text-dark fw-bold font-monospace px-3 py-1 rounded-pill shadow-sm fs-5">
                            <i class="fa-solid fa-receipt text-primary me-1"></i> ${escapeHtml(row.ticket_numero)}
                        </span>
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold shadow-sm">
                            <i class="fa-solid fa-location-dot text-warning me-1"></i> ${escapeHtml(row.nombre_sede || 'Sede Principal')}
                        </span>
                        ${badgePrio}
                        <span class="badge bg-black bg-opacity-25 rounded-pill px-3 py-1 small">
                            <i class="fa-solid fa-clock me-1"></i> ${escapeHtml(fechaStr)}
                        </span>
                    </div>
                    <div id="badge-estado-${row.id}">${estadoBadge}</div>
                </div>

                <!-- Body -->
                <div class="p-4">
                    <div class="row g-4 align-items-center">
                        <!-- Patient Info -->
                        <div class="col-lg-7 col-md-12">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-none d-sm-flex align-items-center justify-content-center shadow-sm" style="width: 54px; height: 54px;">
                                    <i class="fa-solid fa-user-check fs-4"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h4 class="fw-bold text-dark mb-1">
                                        ${escapeHtml(row.nombres + ' ' + row.apellidos)}
                                    </h4>
                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <i class="fa-solid fa-id-card text-muted me-1"></i> ${escapeHtml(row.tipo_documento)} ${escapeHtml(row.numero_documento)}
                                        </span>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 fw-bold">
                                            <i class="fa-solid fa-hospital me-1"></i> ${escapeHtml(row.eps_nombre || 'Savia Salud')}
                                        </span>
                                        ${row.telefono ? `<span class="text-muted small"><i class="fa-solid fa-phone text-muted me-1"></i> ${escapeHtml(row.telefono)}</span>` : ''}
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i> ${escapeHtml(row.direccion_residencia || 'Dirección no registrada')} ${row.ciudad_residencia ? `• ${escapeHtml(row.ciudad_residencia)}` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div class="col-lg-5 col-md-12 border-start-lg ps-lg-4">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-secondary text-uppercase">
                                        <i class="fa-solid fa-folder-open text-primary me-1"></i> Documentos & Soportes
                                    </span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    ${docsHtml}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sequential Action Buttons Footer -->
                    <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="small" id="guia-estado-${row.id}">
                            ${guiaFlujoHtml}
                        </div>

                        <div class="d-flex flex-wrap gap-2" id="acciones-orden-${row.id}">
                            ${botonesAccionHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    cont.innerHTML = html;
}

function abrirModalFirmaPorId(id) {
    if (window.pacientesEncontradosCache && window.pacientesEncontradosCache[id]) {
        abrirModalFirmaDirecto(window.pacientesEncontradosCache[id]);
    }
}

function llamarTurnoATurnero(id, ticket) {
    const miModulo = getMiModuloActual();
    modalConfirm(`¿Desea proyectar en el <strong>Turnero 2</strong> y llamar por voz al paciente del tiquete <strong>${escapeHtml(ticket)}</strong> a <strong>${escapeHtml(miModulo)}</strong>?`, () => {
        const btnLlamar = document.getElementById(`btn-llamar-${id}`);
        if (btnLlamar) {
            btnLlamar.disabled = true;
            btnLlamar.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Llamando...`;
        }

        const formData = new FormData();
        formData.append('action', 'llamar_turno_entrega');
        formData.append('ingreso_id', id);
        formData.append('modulo_nombre', miModulo);

        fetch('index.php?page=entrega', {
            method: 'POST',
            body: formData
        })
        .then(async res => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch(e) {
                throw new Error("Respuesta no válida del servidor");
            }
        })
        .then(data => {
            if (data.status === 'ok') {
                // Actualizar caché
                if (window.pacientesEncontradosCache && window.pacientesEncontradosCache[id]) {
                    window.pacientesEncontradosCache[id].estado_tramite = 'EN_ENTREGA';
                    window.pacientesEncontradosCache[id].modulo_entrega_asignado = miModulo;
                }

                // Actualizar interfaz al instante
                const cardHeader = document.getElementById(`card-header-${id}`);
                if (cardHeader) {
                    cardHeader.classList.remove('bg-primary');
                    cardHeader.classList.add('bg-success');
                }

                const badgeEstado = document.getElementById(`badge-estado-${id}`);
                if (badgeEstado) {
                    badgeEstado.innerHTML = `<span class="badge bg-white text-success px-3 py-2 rounded-pill shadow-sm fw-bold"><i class="fa-solid fa-bell me-1"></i> Llamado Activo en ${escapeHtml(miModulo)}</span>`;
                }

                const guia = document.getElementById(`guia-estado-${id}`);
                if (guia) {
                    guia.innerHTML = `<span class="text-success fw-bold"><i class="fa-solid fa-circle-check me-1"></i> Paciente llamado a ${escapeHtml(miModulo)}. Listo para gestionar y firmar.</span>`;
                }

                const contenedorAcciones = document.getElementById(`acciones-orden-${id}`);
                if (contenedorAcciones) {
                    contenedorAcciones.innerHTML = `
                        <button type="button" 
                                class="btn btn-outline-warning btn-lg fw-bold text-dark px-3 shadow-sm"
                                id="btn-llamar-${id}"
                                onclick="llamarTurnoATurnero(${id}, '${escapeHtml(ticket)}')">
                            <i class="fa-solid fa-volume-high me-1"></i> 🔁 Re-llamar
                        </button>
                        <button type="button" 
                                class="btn btn-success btn-lg fw-bold text-white px-4 shadow-sm animate__animated animate__pulse"
                                id="btn-gestionar-${id}"
                                onclick="abrirModalFirmaPorId(${id})">
                            <i class="fa-solid fa-signature me-2"></i> ✍️ Gestionar Entrega & Firma
                        </button>
                    `;
                }

                modalAlert(`¡Paciente proyectado en el Turnero 2 y llamado por voz al ${escapeHtml(miModulo)} con éxito!<br><span class="text-muted small">Ya puedes proceder a hacer clic en <strong>Gestionar Entrega & Firma</strong>.</span>`, 'success', 'Llamado a Turnero 2');
            } else {
                modalAlert(data.message || 'No se pudo completar el llamado.', 'error', 'Aviso de Turnero');
                if (btnLlamar) {
                    btnLlamar.disabled = false;
                    btnLlamar.innerHTML = `<i class="fa-solid fa-bullhorn me-2"></i> 📢 Paso 1: Llamar a Turnero 2 a <span class="badge bg-dark text-warning ms-1 lbl-mi-modulo">${escapeHtml(miModulo)}</span>`;
                }
            }
        })
        .catch(err => {
            console.error("Error al llamar turno:", err);
            modalAlert("Ocurrió un error al enviar el llamado al Turnero 2: " + (err.message || ''), 'error', 'Error de Conexión');
            if (btnLlamar) {
                btnLlamar.disabled = false;
                btnLlamar.innerHTML = `<i class="fa-solid fa-bullhorn me-2"></i> 📢 Paso 1: Llamar a Turnero 2 a <span class="badge bg-dark text-warning ms-1 lbl-mi-modulo">${escapeHtml(miModulo)}</span>`;
            }
        });
    }, null, 'Llamar Paciente a Turnero 2', '📢 Sí, Llamar Ahora', 'Cancelar');
}

function abrirModalFirmaDirecto(row) {
    currentPacienteData = row;
    currentOrderTicket = row.ticket_numero || '';
    currentOrderPdfTranscripcion = row.pdf_transcripcion_url || '';
    currentOrderPdfAlistamiento = row.pdf_formula_final_url || row.pdf_alistamiento || '';
    currentOrderTranscripcionTexto = row.transcripcion_texto || '';

    // Si pdf_transcripcion_url no está en el campo principal, buscar en documentos escaneados/ingresados
    if (!currentOrderPdfTranscripcion && row.documentos && Array.isArray(row.documentos) && row.documentos.length > 0) {
        const docFormula = row.documentos.find(d => {
            const t = ((d.tipo_documento || '') + ' ' + (d.nombre_original || '') + ' ' + (d.ruta_archivo || '')).toUpperCase();
            return t.includes('FORMULA') || t.includes('ORDEN') || t.includes('TRANSCRIP');
        });
        if (docFormula) {
            currentOrderPdfTranscripcion = docFormula.ruta_archivo;
        } else {
            currentOrderPdfTranscripcion = row.documentos[0].ruta_archivo;
        }
    }

    document.getElementById('entrega_ingreso_id').value = row.id;
    document.getElementById('entrega_ticket_txt').innerText = row.ticket_numero || '-';
    document.getElementById('entrega_paciente_txt').innerText = (row.nombres || '') + ' ' + (row.apellidos || '');
    document.getElementById('entrega_doc_txt').innerText = (row.tipo_documento || '') + ' ' + (row.numero_documento || '');
    const elSede = document.getElementById('entrega_sede_txt');
    if (elSede) elSede.innerText = row.nombre_sede || 'Sede Principal';

    document.getElementById('faltantes_alistamiento_entrega').value = row.faltantes_alistamiento || '';

    limpiarCanvas();
    detenerCamaraPaciente();

    const modalEl = document.getElementById('modalFirmaDigital');
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => {
        if (canvas) {
            canvas.width = canvas.offsetWidth || 700;
            canvas.height = canvas.offsetHeight || 200;
            ctx.strokeStyle = "#000000";
            ctx.lineWidth = 3;
            ctx.lineCap = "round";
        }
    }, 400);
}

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
    if (!canvas) {
        canvas = document.getElementById('canvas-firma');
        if (canvas) ctx = canvas.getContext('2d');
    }
    if (!canvas) return { x: 0, y: 0 };
    const rect = canvas.getBoundingClientRect();
    const clientX = e.clientX !== undefined ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
    const clientY = e.clientY !== undefined ? e.clientY : (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
    return {
        x: clientX - rect.left,
        y: clientY - rect.top
    };
}

function startDrawing(e) {
    if (!ctx) {
        canvas = document.getElementById('canvas-firma');
        if (canvas) ctx = canvas.getContext('2d');
    }
    if (!ctx) return;
    isDrawing = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
}

function draw(e) {
    if (!isDrawing || !ctx) return;
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
}

function stopDrawing() {
    isDrawing = false;
}

function limpiarCanvas() {
    if (!canvas) canvas = document.getElementById('canvas-firma');
    if (!ctx && canvas) ctx = canvas.getContext('2d');
    if (canvas && ctx) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function isCanvasBlank(c) {
    if (!c) return true;
    try {
        const blank = document.createElement('canvas');
        blank.width = c.width;
        blank.height = c.height;
        return c.toDataURL() === blank.toDataURL();
    } catch(e) {
        return false;
    }
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

    const modalEl = document.getElementById('modalFirmaDigital');
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    setTimeout(() => {
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        ctx.strokeStyle = "#000000";
        ctx.lineWidth = 3;
        ctx.lineCap = "round";
        checkTopazSigWeb();
    }, 300);
}

// ----------------------------------------------------
// INTEGRACIÓN CON TABLETA DIGITALIZADORA TOPAZ (T-S460)
// ----------------------------------------------------
let topazActivo = false;
let topazWorkingUrl = null;

async function checkTopazSigWeb() {
    const badge = document.getElementById('badgeEstadoTopaz');
    const txt = document.getElementById('txtEstadoTopaz');

    // Candidatos oficiales de puertos de Topaz SigWeb (HTTPS / HTTP)
    const candidateUrls = [
        'https://tablet.sigwebtablet.com:47290',
        'https://tablet.sigwebtablet.com:47289',
        'http://127.0.0.1:47289',
        'http://localhost:47289'
    ];

    for (const baseUrl of candidateUrls) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 800);
            const res = await fetch(baseUrl + '/SigWeb/GetDaysUntilCertificateExpires', {
                signal: controller.signal,
                mode: 'cors'
            }).catch(() => null);
            clearTimeout(timeoutId);

            if (res && (res.ok || res.status === 200)) {
                topazWorkingUrl = baseUrl;
                if (badge) {
                    badge.className = 'badge bg-success p-2 shadow-sm';
                    badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Pad Topaz Listo';
                }
                if (txt) txt.textContent = `Conectado (${baseUrl.split(':')[2]} OK)`;
                return true;
            }
        } catch(e) {}
    }

    // Si no respondió ningún puerto
    topazWorkingUrl = null;
    if (badge) {
        badge.className = 'badge bg-secondary p-2';
        badge.innerHTML = '<i class="fa-solid fa-tablet-screen-button me-1"></i> Modo Pantalla / Táctil';
    }
    if (txt) txt.textContent = 'Firme en pantalla o active SigWeb';
    return false;
}

function getSigWebBaseUrl() {
    return topazWorkingUrl || 'https://tablet.sigwebtablet.com:47289';
}

async function activarPadTopaz() {
    const badge = document.getElementById('badgeEstadoTopaz');
    if (!topazWorkingUrl) {
        const ok = await checkTopazSigWeb();
        if (!ok) {
            abrirModalAyudaTopaz();
            return;
        }
    }

    try {
        const base = getSigWebBaseUrl();
        await fetch(base + '/SigWeb/SetImageXSize?500', { mode: 'cors' });
        await fetch(base + '/SigWeb/SetImageYSize?200', { mode: 'cors' });
        await fetch(base + '/SigWeb/SetImagePenWidth?5', { mode: 'cors' });
        await fetch(base + '/SigWeb/SetImageFileFormat?4', { mode: 'cors' });
        await fetch(base + '/SigWeb/ClearTablet', { mode: 'cors' });
        await fetch(base + '/SigWeb/SetTabletState?1', { mode: 'cors' });
        topazActivo = true;

        if (badge) {
            badge.className = 'badge bg-warning text-dark p-2 shadow-sm animate-pulse';
            badge.innerHTML = '<i class="fa-solid fa-pen-fancy me-1"></i> Firmando en Pad Topaz...';
        }
    } catch(e) {
        alert('No se pudo activar el Pad Topaz.\n\nVerifique que la tableta Topaz T-S460 esté conectada por USB y que el software Topaz SigWeb esté en ejecución.');
    }
}

async function limpiarPadTopaz() {
    try {
        const base = getSigWebBaseUrl();
        await fetch(base + '/SigWeb/ClearTablet', { mode: 'cors' });
        limpiarCanvas();
    } catch(e) {}
}

async function capturarFirmaPadTopaz() {
    const badge = document.getElementById('badgeEstadoTopaz');
    try {
        const base = getSigWebBaseUrl();
        const res = await fetch(base + '/SigWeb/GetSigImage/1', { mode: 'cors' });
        const data = await res.json();
        let rawBase64 = '';
        if (data && typeof data === 'object') {
            rawBase64 = data.imageData || data.image || data.SigString || '';
        } else if (typeof data === 'string') {
            rawBase64 = data;
        }

        if (rawBase64 && rawBase64.length > 50) {
            let fullDataUrl = rawBase64.startsWith('data:image') ? rawBase64 : ('data:image/png;base64,' + rawBase64);
            const img = new Image();
            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                document.getElementById('firma_base64').value = fullDataUrl;
            };
            img.src = fullDataUrl;

            await fetch(base + '/SigWeb/SetTabletState?0', { mode: 'cors' });
            topazActivo = false;

            if (badge) {
                badge.className = 'badge bg-success p-2 shadow-sm';
                badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Firma Topaz Capturada';
            }
        } else {
            alert('No se detectó ningún trazo en la tableta Topaz.\n\nPor favor solicite al paciente realizar la firma en la pantalla del pad antes de pulsar "Pasar Firma al Acta".');
        }
    } catch(e) {
        alert('Error al transferir la firma desde el pad Topaz.');
    }
}

async function cerrarPadTopaz() {
    if (topazActivo) {
        try {
            const base = getSigWebBaseUrl();
            await fetch(base + '/SigWeb/SetTabletState?0', { mode: 'cors' });
            topazActivo = false;
        } catch(e) {}
    }
}

function abrirModalAyudaTopaz() {
    const modalEl = document.getElementById('modalAyudaTopaz');
    if (modalEl) {
        const m = bootstrap.Modal.getOrCreateInstance(modalEl);
        m.show();
    }
}

async function extraerTextoPDF(fileOrUrl) {
    if (!fileOrUrl) return '';
    if (typeof pdfjsLib === 'undefined') return '';
    try {
        let arrayBuffer;
        if (fileOrUrl instanceof File) {
            arrayBuffer = await fileOrUrl.arrayBuffer();
        } else if (typeof fileOrUrl === 'string' && fileOrUrl.trim().length > 0) {
            let url = fileOrUrl.trim();
            let resp = null;
            
            // 1. Intentar fetch directo con URL codificada
            try {
                resp = await fetch(encodeURI(url));
            } catch (e) {}

            // 2. Si falla, intentar con la ruta base del aplicativo
            if (!resp || !resp.ok) {
                const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
                const cleanRel = url.replace(/^\/+/, '');
                try {
                    resp = await fetch(basePath + encodeURI(cleanRel));
                } catch (e) {}
            }

            // 3. Fallback con origin
            if (!resp || !resp.ok) {
                try {
                    resp = await fetch(window.location.origin + '/' + url.replace(/^\/+/, ''));
                } catch (e) {}
            }

            if (!resp || !resp.ok) {
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
        if ((!textoTranscripcion || textoTranscripcion.trim().length < 10) && currentPacienteData && currentPacienteData.documentos) {
            for (const doc of currentPacienteData.documentos) {
                if (doc.ruta_archivo) {
                    const txt = await extraerTextoPDF(doc.ruta_archivo);
                    if (txt && txt.trim().length >= 10) {
                        textoTranscripcion = txt;
                        break;
                    }
                }
            }
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
