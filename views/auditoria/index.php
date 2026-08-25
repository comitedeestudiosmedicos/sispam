<?php
require_once __DIR__ . '/../../config/app.php';
check_role(['Administrador', 'Regente', 'auditoria']);

require_once __DIR__ . '/../../models/AuditLog.php';
require_once __DIR__ . '/../../models/Usuario.php';

$auditModel = new AuditLog();
$usuarioModel = new Usuario();

$filters = [
    'modulo'      => $_GET['modulo'] ?? '',
    'accion'      => $_GET['accion'] ?? '',
    'usuario_id'  => $_GET['usuario_id'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-7 days')),
    'fecha_hasta' => $_GET['fecha_hasta'] ?? date('Y-m-d'),
    'q'           => $_GET['q'] ?? ''
];

// Exportación CSV / Excel
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $logsExport = $auditModel->getLogs($filters, 5000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=log_auditoria_sispam_' . date('Y-m-d_H-i') . '.csv');
    
    $output = fopen('php://output', 'w');
    // BOM UTF-8 para abrir directo en Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['ID Log', 'Fecha y Hora', 'ID Usuario', 'Usuario Nombre', 'Rol', 'Módulo', 'Acción', 'ID Registro Afectado', 'Detalles', 'Dirección IP', 'Navegador/Agente']);
    
    foreach ($logsExport as $l) {
        fputcsv($output, [
            $l['id'],
            $l['created_at'],
            $l['usuario_id'] ?: 'N/A',
            $l['usuario_nombre'],
            $l['rol_nombre'],
            $l['modulo'],
            $l['accion'],
            $l['registro_id'] ?: 'N/A',
            $l['detalles'],
            $l['ip_address'],
            $l['user_agent']
        ]);
    }
    fclose($output);
    exit;
}

$logs = $auditModel->getLogs($filters, 500);
$stats = $auditModel->getEstadisticas();
$modulos = $auditModel->getModulosDisponibles();
$acciones = $auditModel->getAccionesDisponibles();
$usuarios = $usuarioModel->getAll();

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-clock-rotate-left me-2"></i> Log de Auditoría & Trazabilidad del Sistema</h4>
        <p class="text-muted small mb-0">Registro histórico de todas las acciones, modificaciones y eventos realizados por los usuarios en SISPAM.</p>
    </div>
    <div class="col-md-5 text-md-end mt-2 mt-md-0">
        <a href="index.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success fw-bold shadow-sm">
            <i class="fa-solid fa-file-excel me-1"></i> Exportar Log a Excel (CSV)
        </a>
    </div>
</div>

<!-- Tarjetas KPI Estadísticas de Log -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-primary p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">ACCIONES REGISTRADAS HOY</div>
                    <div class="fs-2 fw-bold text-primary"><?= number_format($stats['total_hoy']) ?></div>
                </div>
                <div class="bg-primary text-white p-3 rounded-circle">
                    <i class="fa-solid fa-list-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-success p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">USUARIOS ACTIVOS HOY</div>
                    <div class="fs-2 fw-bold text-success"><?= number_format($stats['usuarios_activos_hoy']) ?></div>
                </div>
                <div class="bg-success text-white p-3 rounded-circle">
                    <i class="fa-solid fa-users fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-info p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">MÓDULO MÁS OPERADO</div>
                    <div class="fs-6 fw-bold text-info text-truncate" style="max-width: 160px;" title="<?= htmlspecialchars($stats['modulo_mas_activo']) ?>">
                        <?= htmlspecialchars($stats['modulo_mas_activo']) ?>
                    </div>
                </div>
                <div class="bg-info text-dark p-3 rounded-circle">
                    <i class="fa-solid fa-chart-line fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-warning p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">INICIOS DE SESIÓN HOY</div>
                    <div class="fs-2 fw-bold text-warning"><?= number_format($stats['inicios_sesion_hoy']) ?></div>
                </div>
                <div class="bg-warning text-dark p-3 rounded-circle">
                    <i class="fa-solid fa-key fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros de Búsqueda de Auditoría -->
<div class="card card-glass border-0 shadow-sm p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-end">
        <input type="hidden" name="page" value="auditoria">

        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1"><i class="fa-solid fa-calendar me-1"></i> Desde:</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['fecha_desde']) ?>">
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1"><i class="fa-solid fa-calendar me-1"></i> Hasta:</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['fecha_hasta']) ?>">
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1"><i class="fa-solid fa-cubes me-1"></i> Módulo:</label>
            <select name="modulo" class="form-select form-select-sm">
                <option value="">-- Todos los Módulos --</option>
                <?php 
                $nombresModulos = [
                    'AUTENTICACION' => 'Autenticación & Inicios de Sesión',
                    'INGRESO'       => 'Admisión / Ingreso de Pacientes',
                    'TRANSCRIPCION' => 'Transcripción & Verificación de Stock',
                    'MONITOREO'     => 'Monitoreo & Verificación Técnica',
                    'ALISTAMIENTO'  => 'Supervisión de Alistamiento (Picking)',
                    'ENTREGA'       => 'Factura & Entrega con Firma Digital',
                    'EXPEDIENTES'   => 'Consulta de Expedientes',
                    'USUARIOS'      => 'Gestión de Usuarios y Permisos',
                    'EMPRESA'       => 'Parametrización de Empresa & Turneros'
                ];
                foreach ($modulos as $mod): 
                    $label = $nombresModulos[$mod] ?? $mod;
                ?>
                    <option value="<?= htmlspecialchars($mod) ?>" <?= $filters['modulo'] === $mod ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label fw-bold small text-muted mb-1"><i class="fa-solid fa-user me-1"></i> Usuario:</label>
            <select name="usuario_id" class="form-select form-select-sm">
                <option value="">-- Todos los Usuarios --</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= intval($filters['usuario_id']) === intval($u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre_completo']) ?> (<?= htmlspecialchars($u['usuario']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold small text-muted mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Búsqueda libre / Palabra clave:</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por detalle, ID o acción..." value="<?= htmlspecialchars($filters['q']) ?>">
        </div>

        <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
        </div>
    </form>
</div>

<!-- Tabla de Trazas de Log de Auditoría -->
<div class="card card-glass border-0 shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
        <span class="fw-bold"><i class="fa-solid fa-table-list me-2"></i> Trazas de Eventos (Mostrando <?= count($logs) ?> registros)</span>
        <small class="text-muted">Orden cronológico inverso (Más recientes primero)</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm" style="font-size: 0.88rem;">
                <thead class="table-secondary">
                    <tr>
                        <th class="ps-3" style="width: 150px;">Fecha y Hora</th>
                        <th style="width: 180px;">Usuario & Rol</th>
                        <th style="width: 130px;">Módulo</th>
                        <th style="width: 180px;">Acción Realizada</th>
                        <th style="width: 90px;">Registro ID</th>
                        <th>Detalles del Cambio / Evento</th>
                        <th class="pe-3" style="width: 120px;">Dirección IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-folder-open fs-3 d-block mb-2"></i>
                            No se encontraron registros de auditoría que coincidan con los filtros seleccionados.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        // Estilizado de insignias de módulo y acción
                        $modBadge = 'bg-secondary';
                        switch ($log['modulo']) {
                            case 'AUTENTICACION': $modBadge = 'bg-dark'; break;
                            case 'INGRESO': $modBadge = 'bg-primary'; break;
                            case 'TRANSCRIPCION': $modBadge = 'bg-indigo'; break;
                            case 'MONITOREO': $modBadge = 'bg-info text-dark'; break;
                            case 'ALISTAMIENTO': $modBadge = 'bg-warning text-dark'; break;
                            case 'ENTREGA': $modBadge = 'bg-success'; break;
                            case 'USUARIOS': $modBadge = 'bg-purple text-white'; break;
                            case 'EMPRESA': $modBadge = 'bg-danger'; break;
                        }

                        $accionIcon = 'fa-arrow-right';
                        if (strpos($log['accion'], 'CREAR') !== false || strpos($log['accion'], 'REGISTRAR') !== false) $accionIcon = 'fa-plus-circle text-success';
                        elseif (strpos($log['accion'], 'ACTUALIZAR') !== false || strpos($log['accion'], 'MODIFICAR') !== false || strpos($log['accion'], 'GUARDAR') !== false) $accionIcon = 'fa-pen-to-square text-primary';
                        elseif (strpos($log['accion'], 'LOGIN') !== false) $accionIcon = 'fa-key text-warning';
                        elseif (strpos($log['accion'], 'VERIFICACION') !== false || strpos($log['accion'], 'APROBAR') !== false) $accionIcon = 'fa-circle-check text-success';
                        elseif (strpos($log['accion'], 'ELIMINAR') !== false || strpos($log['accion'], 'FALLIDO') !== false) $accionIcon = 'fa-triangle-exclamation text-danger';
                    ?>
                    <tr>
                        <td class="ps-3 fw-bold text-nowrap">
                            <i class="fa-regular fa-clock me-1 text-muted"></i>
                            <?= date('d/m/Y h:i:s A', strtotime($log['created_at'])) ?>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($log['usuario_nombre']) ?></div>
                            <span class="badge bg-light text-dark border"><i class="fa-solid fa-user-shield me-1"></i> <?= htmlspecialchars($log['rol_nombre']) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $modBadge ?> fw-semibold"><?= htmlspecialchars($log['modulo']) ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">
                                <i class="fa-solid <?= $accionIcon ?> me-1"></i> <?= htmlspecialchars($log['accion']) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($log['registro_id']): ?>
                                <span class="badge bg-outline-primary text-primary border border-primary">#<?= $log['registro_id'] ?></span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="text-dark"><?= htmlspecialchars($log['detalles'] ?? 'Sin detalles adicionales') ?></div>
                        </td>
                        <td class="pe-3 font-monospace small text-muted">
                            <i class="fa-solid fa-network-wired me-1"></i> <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
