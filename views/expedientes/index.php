<?php
require_once __DIR__ . '/../../config/app.php';
check_auth();

require_once __DIR__ . '/../../models/Ingreso.php';

$ingresoModel = new Ingreso();
$num_doc = trim($_GET['num_doc'] ?? $_GET['buscar'] ?? '');
$resultados = [];

if (!empty($num_doc)) {
    $resultados = $ingresoModel->buscarPorDocumento($num_doc);
}

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
.expediente-hero-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 16px;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
}
.avatar-patient-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 1.8rem;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
}
.table-expedientes thead th {
    background-color: #f8fafc;
    color: #475569;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    padding: 12px 14px;
}
.table-expedientes tbody td {
    padding: 14px 14px;
    vertical-align: middle;
}
.badge-soft-primary { background-color: #e0e7ff; color: #3730a3; }
.badge-soft-success { background-color: #dcfce7; color: #166534; }
.badge-soft-warning { background-color: #fef3c7; color: #92400e; }
.badge-soft-info { background-color: #e0f2fe; color: #075985; }
.dropdown-menu-custom {
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    padding: 8px;
    min-width: 240px;
}
.dropdown-item-custom {
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
}
.dropdown-item-custom:hover {
    background-color: #f8fafc;
    color: #0284c7;
}
</style>

<div class="container-fluid px-4 py-2">
    <!-- Encabezado de Página -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-primary mb-1">
                <i class="fa-solid fa-folder-tree me-2"></i> Módulo de Consulta de Expedientes
            </h4>
            <p class="text-muted small mb-0">Consulta centralizada de atenciones, trazabilidad de turnos, soportes médicos y actas de entrega firmadas.</p>
        </div>
    </div>

    <!-- Buscador Principal -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="" class="row g-2 align-items-center">
                <input type="hidden" name="page" value="expedientes">
                <div class="col-md-6 col-lg-5">
                    <label class="form-label small fw-bold text-muted mb-1">Buscar por Documento de Identidad:</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-id-card text-primary"></i></span>
                        <input type="text" name="num_doc" class="form-control bg-light border-start-0 ps-0" placeholder="Ej: 1036780004" value="<?= htmlspecialchars($num_doc) ?>" required autofocus>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Consultar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($num_doc)): ?>
        <?php if (!empty($resultados)): ?>
            <?php 
                $paciente = $resultados[0];
                $totalAtenciones = count($resultados);
            ?>
            <!-- Ficha Resumen del Paciente (Hero Card) -->
            <div class="expediente-hero-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-patient-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div>
                            <div class="text-white-50 small text-uppercase fw-semibold" style="letter-spacing: 0.5px;">PACIENTE REGISTRADO EN SISPAM</div>
                            <h3 class="fw-bold text-white mb-1"><?= htmlspecialchars($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></h3>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-primary px-2 py-1"><i class="fa-solid fa-id-badge me-1"></i> <?= htmlspecialchars($paciente['tipo_documento'] . ' ' . $paciente['numero_documento']) ?></span>
                                <span class="badge bg-info text-dark px-2 py-1"><i class="fa-solid fa-hospital-user me-1"></i> <?= htmlspecialchars($paciente['eps_nombre'] ?? 'No especificada') ?></span>
                                <?php if (!empty($paciente['telefono'])): ?>
                                    <span class="badge bg-secondary px-2 py-1"><i class="fa-solid fa-phone me-1"></i> <?= htmlspecialchars($paciente['telefono']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-md-end border-top border-md-top-0 pt-3 pt-md-0 border-secondary">
                        <div class="text-white-50 small">HISTORIAL DE ATENCIONES</div>
                        <div class="fs-2 fw-bold text-warning"><?= $totalAtenciones ?> <?= $totalAtenciones === 1 ? 'Atención' : 'Atenciones' ?></div>
                        <div class="small text-white-50"><i class="fa-solid fa-circle-check text-success me-1"></i> Expediente Activo</div>
                    </div>
                </div>
            </div>

            <!-- Grilla Principal de Historial de Órdenes -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-list-check me-2 text-primary"></i> Registro Cronológico de Atenciones
                    </h6>
                    <span class="badge badge-soft-primary px-3 py-2 fw-bold"><?= $totalAtenciones ?> Registros Encontrados</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-expedientes align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Tiquete & Estado</th>
                                    <th>Fecha de Ingreso</th>
                                    <th>Orientador / Sede</th>
                                    <th>Novedad / Faltantes</th>
                                    <th class="text-center">Soportes Digitales</th>
                                    <th class="text-end pe-4">Reimpresión & Acta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $ing): ?>
                                <?php 
                                    $detalles = $ingresoModel->getById($ing['id']);
                                    
                                    // Función para normalizar nombres e iconos de soportes
                                    if (!function_exists('resolverNombreDocExpediente')) {
                                        function resolverNombreDocExpediente($tipo, $url = '') {
                                            $t = strtoupper(trim($tipo ?? ''));
                                            $filename = strtolower(basename($url ?? ''));

                                            // 1. Mipres (evaluado antes para no confundirse con la subcarpeta soportes_savia)
                                            if (str_contains($t, 'MIPRES') || str_contains($filename, 'mipres')) {
                                                return ['tipo' => 'Direccionamiento / Mipres', 'icon' => 'fa-solid fa-file-prescription text-primary'];
                                            }
                                            // 2. Validación de Derechos de Savia
                                            if (str_contains($t, 'DERECHOS') || str_contains($filename, 'derechos') || str_contains($filename, 'validacion') || (str_contains($t, 'SAVIA') && !str_contains($t, 'MIPRES'))) {
                                                return ['tipo' => 'Validación Derechos Savia', 'icon' => 'fa-solid fa-shield-heart text-success'];
                                            }
                                            // 3. Cédula / Documento de Identidad
                                            if ($t === 'CEDULA' || str_contains($t, 'IDENTIDAD') || str_contains($filename, 'cedula')) {
                                                return ['tipo' => 'Cédula / Documento de Identidad', 'icon' => 'fa-solid fa-id-card text-primary'];
                                            }
                                            // 4. Fórmula / Orden Médica
                                            if ($t === 'ORDEN_MEDICA' || str_contains($t, 'FORMULA') || str_contains($t, 'MEDICA') || str_contains($filename, 'orden_medica') || str_contains($filename, 'formula')) {
                                                return ['tipo' => 'Fórmula / Orden Médica', 'icon' => 'fa-solid fa-file-medical text-danger'];
                                            }
                                            // 5. Autorización / Poder Tercero
                                            if ($t === 'AUTORIZACION' || str_contains($filename, 'autorizacion') || str_contains($filename, 'poder')) {
                                                return ['tipo' => 'Autorización / Poder Tercero', 'icon' => 'fa-solid fa-file-shield text-warning'];
                                            }
                                            // 6. Historia Clínica
                                            if ($t === 'HISTORIA_CLINICA' || str_contains($filename, 'historia')) {
                                                return ['tipo' => 'Historia Clínica / Anexo', 'icon' => 'fa-solid fa-clipboard-user text-info'];
                                            }
                                            // 7. Factura
                                            if ($t === 'FACTURA' || str_contains($filename, 'factura')) {
                                                return ['tipo' => 'Factura / Comprobante Terceros', 'icon' => 'fa-solid fa-file-invoice-dollar text-success'];
                                            }
                                            if (!empty($tipo) && $t !== 'DOCUMENTO' && $t !== 'OTRO') {
                                                return ['tipo' => trim($tipo), 'icon' => 'fa-solid fa-file-pdf text-danger'];
                                            }
                                            return ['tipo' => 'Documento Adjunto', 'icon' => 'fa-solid fa-file-lines text-secondary'];
                                        }
                                    }

                                    // Recopilar lista estructurada y DEDUPLICADA de soportes
                                    $soportes = [];
                                    $urlsVistas = [];

                                    $agregarSoporte = function($tipo, $url, $icono = null) use (&$soportes, &$urlsVistas) {
                                        if (empty($url)) return;
                                        $urlNorm = trim($url);
                                        if (empty($urlNorm) || isset($urlsVistas[$urlNorm])) return;
                                        $urlsVistas[$urlNorm] = true;

                                        $info = resolverNombreDocExpediente($tipo, $urlNorm);
                                        $soportes[] = [
                                            'tipo' => $icono ? $tipo : $info['tipo'],
                                            'url'  => $urlNorm,
                                            'icon' => $icono ?: $info['icon']
                                        ];
                                    };

                                    // 1. Documentos subidos en admisión o entrega
                                    if (!empty($detalles['documentos'])) {
                                        foreach ($detalles['documentos'] as $doc) {
                                            $agregarSoporte($doc['tipo_documento'] ?? '', $doc['ruta_archivo'] ?? '');
                                        }
                                    }

                                    // 2. PDF de Transcripción
                                    if (!empty($detalles['pdf_transcripcion_url'])) {
                                        $agregarSoporte('Orden Médica Transcrita', $detalles['pdf_transcripcion_url'], 'fa-solid fa-file-circle-check text-success');
                                    }

                                    // 3. Comprobante de Alistamiento
                                    if (!empty($detalles['pdf_alistamiento'])) {
                                        $agregarSoporte('Comprobante de Alistamiento', $detalles['pdf_alistamiento'], 'fa-solid fa-boxes-packing text-warning');
                                    }

                                    // 4. Comprobante de Factura/Terceros
                                    if (!empty($detalles['pdf_formula_final_url'])) {
                                        $agregarSoporte('Factura / Comprobante Terceros', $detalles['pdf_formula_final_url'], 'fa-solid fa-file-invoice-dollar text-success');
                                    }

                                    // 5. Validación de Derechos de Savia
                                    if (!empty($detalles['pdf_validacion_derechos_url'])) {
                                        $agregarSoporte('Validación Derechos Savia', $detalles['pdf_validacion_derechos_url'], 'fa-solid fa-shield-heart text-success');
                                    }

                                    // 6. Mipres
                                    if (!empty($detalles['pdf_mipres_url'])) {
                                        $agregarSoporte('Direccionamiento Mipres', $detalles['pdf_mipres_url'], 'fa-solid fa-file-prescription text-primary');
                                    }

                                    // 7. Firma Digital
                                    if (!empty($detalles['firma_paciente_url'])) {
                                        $agregarSoporte('Firma Digital del Paciente', $detalles['firma_paciente_url'], 'fa-solid fa-signature text-secondary');
                                    }

                                    // 8. Foto del Paciente
                                    if (!empty($detalles['foto_paciente_url'])) {
                                        $agregarSoporte('Registro Fotográfico Paciente', $detalles['foto_paciente_url'], 'fa-solid fa-camera text-primary');
                                    }

                                    $totalSoportes = count($soportes);
                                    $faltantesText = !empty($detalles['faltantes_alistamiento']) ? $detalles['faltantes_alistamiento'] : ($detalles['observaciones_pendientes'] ?? '');
                                    $tieneFaltantes = !empty($faltantesText) && !str_contains(strtoupper($faltantesText), 'VERIFICACIÓN EXITOSA');
                                ?>
                                <tr>
                                    <!-- Tiquete & Estado -->
                                    <td class="ps-4">
                                        <div class="fw-bold fs-5 text-primary mb-1"><?= htmlspecialchars($ing['ticket_numero']) ?></div>
                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                            <?= get_estado_badge($ing['estado_tramite']) ?>
                                            <?= get_prioridad_badge($ing['prioridad'] ?? 'NORMAL') ?>
                                        </div>
                                    </td>

                                    <!-- Fecha de Ingreso -->
                                    <td>
                                        <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($ing['fecha_ingreso'])) ?></div>
                                        <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> <?= date('h:i A', strtotime($ing['fecha_ingreso'])) ?></small>
                                    </td>

                                    <!-- Orientador / Sede -->
                                    <td>
                                        <div class="fw-semibold text-dark"><i class="fa-solid fa-user-tie text-secondary me-1"></i> <?= htmlspecialchars($ing['orientador_nombre'] ?? 'No registrado') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ing['modulo_entrega_asignado'] ?? 'Ventanilla General') ?></small>
                                    </td>

                                    <!-- Novedad / Faltantes -->
                                    <td>
                                        <?php if ($tieneFaltantes): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold border-warning shadow-sm" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top" title="Novedades de Medicamentos" data-bs-content="<?= htmlspecialchars($faltantesText) ?>">
                                                <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Con Faltantes
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-soft-success px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Entrega 100%</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Soportes Digitales Consolidados en Menú Moderno -->
                                    <td class="text-center">
                                        <?php if ($totalSoportes > 0): ?>
                                            <div class="dropdown d-inline-block">
                                                <button class="btn btn-sm btn-outline-primary fw-bold dropdown-toggle shadow-sm px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fa-solid fa-folder-open me-1"></i> Soportes (<?= $totalSoportes ?>)
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-custom shadow">
                                                    <li class="dropdown-header small text-uppercase fw-bold text-muted px-3 py-1">Documentos Adjuntos</li>
                                                    <?php foreach ($soportes as $sop): ?>
                                                        <li>
                                                            <a class="dropdown-item dropdown-item-custom d-flex align-items-center justify-content-between" href="<?= htmlspecialchars($sop['url']) ?>" target="_blank">
                                                                <span><i class="<?= $sop['icon'] ?> me-2"></i> <?= htmlspecialchars($sop['tipo']) ?></span>
                                                                <i class="fa-solid fa-arrow-up-right-from-square text-muted small ms-2"></i>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Sin archivos</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Reimpresión & Acciones -->
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                            <!-- Botón Acta Firmada (Principal) -->
                                            <?php if ($ing['estado_tramite'] === 'ENTREGADO' || !empty($detalles['firma_paciente_url'])): ?>
                                                <a href="index.php?page=imprimir_acta&id=<?= $ing['id'] ?>" target="_blank" class="btn btn-sm btn-success fw-bold shadow-sm px-3" title="Reimprimir Acta Digital de Conformidad y Entrega">
                                                    <i class="fa-solid fa-file-signature me-1"></i> Acta Firmada
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2 py-1 me-1">Sin Entrega</span>
                                            <?php endif; ?>

                                            <!-- Dropdown Tiquetes Térmicos -->
                                            <div class="dropdown d-inline-block">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Reimprimir Tiquetes Térmicos">
                                                    <i class="fa-solid fa-print"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom shadow">
                                                    <li class="dropdown-header small text-uppercase fw-bold text-muted px-3 py-1">Reimpresión Térmica</li>
                                                    <li>
                                                        <a class="dropdown-item dropdown-item-custom" href="index.php?page=imprimir_ticket&id=<?= $ing['id'] ?>" target="_blank">
                                                            <i class="fa-solid fa-receipt text-primary me-2"></i> Ticket de Ingreso / Turno
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item dropdown-item-custom" href="index.php?page=imprimir_ticket_alistamiento&id=<?= $ing['id'] ?>" target="_blank">
                                                            <i class="fa-solid fa-boxes-packing text-warning me-2"></i> Ticket de Alistamiento
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
                <div class="py-4">
                    <i class="fa-solid fa-folder-open fs-1 text-muted mb-3 d-block"></i>
                    <h5 class="fw-bold text-dark">No se encontraron expedientes</h5>
                    <p class="text-muted small mb-0">No existen registros de ingresos u órdenes para el documento <strong><?= htmlspecialchars($num_doc) ?></strong>.</p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inicializar tooltips y popovers de Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
