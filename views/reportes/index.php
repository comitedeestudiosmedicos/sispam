<?php
require_once __DIR__ . '/../../config/app.php';
check_role('reportes');

require_once __DIR__ . '/../../models/Ingreso.php';

$ingresoModel = new Ingreso();

$tab          = $_GET['tab'] ?? 'pacientes';
$fecha_desde  = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta  = $_GET['fecha_hasta'] ?? date('Y-m-d');
$eps_filtro   = $_GET['eps'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';

// Exportación a Excel (CSV con UTF-8 BOM)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "reporte_" . $tab . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Escribir BOM UTF-8 para compatibilidad directa con Excel en español
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($tab === 'pacientes') {
        fputcsv($output, ['Tiquete', 'Tipo Doc', 'Documento', 'Nombres', 'Apellidos', 'EPS', 'Teléfono', 'Email', 'Fecha Ingreso', 'Estado', 'Orientador']);
        $datos = $ingresoModel->getReportePacientes($fecha_desde, $fecha_hasta, $eps_filtro, $estado_filtro);
        foreach ($datos as $row) {
            fputcsv($output, [
                $row['ticket_numero'], $row['tipo_documento'], $row['numero_documento'],
                $row['nombres'], $row['apellidos'], $row['eps_nombre'],
                $row['telefono'], $row['email'], $row['fecha_ingreso'],
                $row['estado_tramite'], $row['orientador_nombre']
            ]);
        }
    } else if ($tab === 'tiempos') {
        fputcsv($output, ['Tiquete', 'Tipo Doc', 'Documento', 'Paciente', 'EPS', 'Fecha/Hora Ingreso', 'Fecha/Hora Finalización', 'Tiempo Total (Minutos)', 'Estado Tramite']);
        $datos = $ingresoModel->getReporteTiemposSLA($fecha_desde, $fecha_hasta);
        foreach ($datos as $row) {
            fputcsv($output, [
                $row['ticket_numero'], $row['tipo_documento'], $row['numero_documento'],
                $row['nombres'] . ' ' . $row['apellidos'], $row['eps_nombre'],
                $row['fecha_ingreso'], $row['fecha_finalizacion'],
                $row['tiempo_total_minutos'] ?? 0, $row['estado_tramite']
            ]);
        }
    } else if ($tab === 'pendientes') {
        fputcsv($output, ['Tiquete', 'Tipo Doc', 'Documento', 'Paciente', 'EPS', 'Fecha Ingreso', 'Fecha Entrega/Actualización', 'Estado', 'Detalle Medicamentos Faltantes / Novedades']);
        $datos = $ingresoModel->getReportePendientes($fecha_desde, $fecha_hasta);
        foreach ($datos as $row) {
            $detalleFaltantes = !empty($row['faltantes_alistamiento']) ? $row['faltantes_alistamiento'] : ($row['observaciones_pendientes'] ?? '');
            fputcsv($output, [
                $row['ticket_numero'], $row['tipo_documento'], $row['numero_documento'],
                $row['nombres'] . ' ' . $row['apellidos'], $row['eps_nombre'],
                $row['fecha_ingreso'], $row['updated_at'] ?? '', $row['estado_tramite'],
                $detalleFaltantes
            ]);
        }
    }
    exit;
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-chart-pie me-2"></i> Módulo de Reportes, Analítica & Tiempos de Atención (SLA)</h4>
        <p class="text-muted small">Generación de informes de gestión, análisis de cuellos de botella en atención y exportación a Excel.</p>
    </div>
</div>

<!-- Selector de Pestañas de Reporte -->
<ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'pacientes' ? 'active bg-primary' : 'bg-white border text-dark' ?>" href="index.php?page=reportes&tab=pacientes">
            <i class="fa-solid fa-users me-1"></i> Reporte de Pacientes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'tiempos' ? 'active bg-primary' : 'bg-white border text-dark' ?>" href="index.php?page=reportes&tab=tiempos">
            <i class="fa-solid fa-stopwatch me-1"></i> Análisis de Tiempos (SLA)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'pendientes' ? 'active bg-warning text-dark' : 'bg-white border text-dark' ?>" href="index.php?page=reportes&tab=pendientes">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> Medicamentos Faltantes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'productividad' ? 'active bg-success' : 'bg-white border text-dark' ?>" href="index.php?page=reportes&tab=productividad">
            <i class="fa-solid fa-user-check me-1"></i> Productividad Usuarios
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'eps' ? 'active bg-info text-dark' : 'bg-white border text-dark' ?>" href="index.php?page=reportes&tab=eps">
            <i class="fa-solid fa-hospital-user me-1"></i> Distribución por EPS
        </a>
    </li>
</ul>

<!-- Filtros Globales de Fecha -->
<div class="card card-glass p-3 mb-4 border-0 shadow-sm">
    <form method="GET" action="" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="reportes">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

        <div class="col-md-3">
            <label class="form-label small fw-semibold">Fecha Desde</label>
            <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label small fw-semibold">Fecha Hasta</label>
            <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
        </div>

        <?php if ($tab === 'pacientes'): ?>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Filtrar por EPS</label>
            <select name="eps" class="form-select">
                <option value="">-- Todas las EPS --</option>
                <?php foreach (EPS_COLOMBIA as $eps): ?>
                    <option value="<?= $eps ?>" <?= $eps_filtro === $eps ? 'selected' : '' ?>><?= $eps ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
                <i class="fa-solid fa-filter me-1"></i> Aplicar Filtros
            </button>
            <?php if (in_array($tab, ['pacientes', 'tiempos', 'pendientes'])): ?>
            <a href="index.php?page=reportes&tab=<?= $tab ?>&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&eps=<?= urlencode($eps_filtro) ?>&export=csv" class="btn btn-success fw-bold" title="Exportar datos directamente a formato Microsoft Excel">
                <i class="fa-solid fa-file-excel me-1"></i> Excel
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- CONTENIDO DE REPORTES POR PESTAÑA -->

<?php if ($tab === 'pacientes'): ?>
    <?php $listaPacientes = $ingresoModel->getReportePacientes($fecha_desde, $fecha_hasta, $eps_filtro, $estado_filtro); ?>
    <div class="card card-glass border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users me-2 text-primary"></i> Pacientes Atendidos en el Periodo</h5>
            <span class="badge bg-primary fs-6"><?= count($listaPacientes) ?> Registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Tiquete</th>
                            <th>Paciente</th>
                            <th>Identificación</th>
                            <th>EPS</th>
                            <th>Fecha Ingreso</th>
                            <th>Estado Actual</th>
                            <th>Orientador</th>
                            <th class="text-end pe-3">Acciones / Reimpresión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaPacientes as $r): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($r['ticket_numero']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($r['nombres'] . ' ' . $r['apellidos']) ?></td>
                            <td><?= htmlspecialchars($r['tipo_documento'] . ' ' . $r['numero_documento']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($r['eps_nombre']) ?></span></td>
                            <td><?= date('d/m/Y h:i A', strtotime($r['fecha_ingreso'])) ?></td>
                            <td><?= get_estado_badge($r['estado_tramite']) ?></td>
                            <td><?= htmlspecialchars($r['orientador_nombre']) ?></td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="index.php?page=imprimir_ticket&id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reimprimir Ticket">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <?php if ($r['estado_tramite'] === 'ENTREGADO' || !empty($r['firma_paciente_url'])): ?>
                                        <a href="index.php?page=imprimir_acta&id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-success fw-bold" title="Reimprimir Acta de Entrega Firmada">
                                            <i class="fa-solid fa-file-signature me-1"></i> Reimprimir Acta
                                        </a>
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

<?php elseif ($tab === 'tiempos'): ?>
    <?php 
    $listaTiempos = $ingresoModel->getReporteTiemposSLA($fecha_desde, $fecha_hasta); 
    $total_minutos_totales = 0;
    $total_minutos_farmacia = 0;
    $total_minutos_fila = 0;
    $count_atendidos = count($listaTiempos);

    foreach ($listaTiempos as $t) { 
        $total_minutos_totales += floatval($t['tiempo_total_minutos'] ?? 0); 
        $total_minutos_farmacia += floatval($t['tiempo_tramite_farmacia_min'] ?? 0); 
        $total_minutos_fila += floatval($t['tiempo_fila_externa_min'] ?? 0); 
    }

    $promedio_total = $count_atendidos > 0 ? round($total_minutos_totales / $count_atendidos, 1) : 0;
    $promedio_farmacia = $count_atendidos > 0 ? round($total_minutos_farmacia / $count_atendidos, 1) : 0;
    $promedio_fila = $count_atendidos > 0 ? round($total_minutos_fila / $count_atendidos, 1) : 0;
    ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-glass border-start border-4 border-primary p-3 shadow-sm">
                <div class="text-muted small fw-semibold">TOTAL ATENCIONES EN PERIODO</div>
                <div class="fs-2 fw-bold text-primary"><?= $count_atendidos ?> Pacientes</div>
                <div class="small text-muted">Procesados exitosamente</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-glass border-start border-4 border-warning p-3 shadow-sm">
                <div class="text-muted small fw-semibold"><i class="fa-solid fa-clock me-1"></i> PROMEDIO FILA EXTERIOR (PREVIA)</div>
                <div class="fs-2 fw-bold text-warning"><?= $promedio_fila ?> Minutos</div>
                <div class="small text-muted">Espera en exterior antes de apertura</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-glass border-start border-4 border-success p-3 shadow-sm">
                <div class="text-muted small fw-semibold"><i class="fa-solid fa-stopwatch me-1"></i> PROMEDIO TRÁMITE FARMACIA (SLA)</div>
                <div class="fs-2 fw-bold text-success"><?= $promedio_farmacia ?> Minutos</div>
                <div class="small text-muted">Contado desde Apertura Oficial u Hora Ingreso</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-glass border-start border-4 border-info p-3 shadow-sm">
                <div class="text-muted small fw-semibold">CUMPLE OBJETIVO SLA (< 30 MIN)</div>
                <div class="fs-2 fw-bold text-info">
                    <?php 
                        $cumplen = count(array_filter($listaTiempos, fn($x) => floatval($x['tiempo_tramite_farmacia_min'] ?? 0) <= 30));
                        $porcentaje = $count_atendidos > 0 ? round(($cumplen / $count_atendidos) * 100) : 0;
                        echo $porcentaje . '%';
                    ?>
                </div>
                <div class="small text-muted">Evaluado sobre tiempo farmacéutico</div>
            </div>
        </div>
    </div>

    <div class="card card-glass border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-stopwatch me-2 text-primary"></i> Análisis Detallado de Tiempos de Atención por Paciente</h5>
            <span class="badge bg-light text-dark border">
                <i class="fa-solid fa-info-circle me-1"></i> Normalizado por Hora de Apertura Sede/Empresa
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Tiquete</th>
                            <th>Paciente / EPS</th>
                            <th>Hora Registro Fila</th>
                            <th>Hora Apertura / Inicio SLA</th>
                            <th>Hora Finalización</th>
                            <th>⌛ Fila Exterior</th>
                            <th>⏱️ Trámite Farmacia (SLA)</th>
                            <th>Evaluación SLA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaTiempos as $t): ?>
                        <?php 
                            $minSla = floatval($t['tiempo_tramite_farmacia_min'] ?? 0); 
                            $minFila = floatval($t['tiempo_fila_externa_min'] ?? 0); 
                            $esTemprano = !empty($t['ingresado_antes_apertura']);
                            $horaAperturaFormatted = date('h:i A', strtotime($t['hora_apertura_oficial'] ?? '07:20:00'));
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary fs-6"><?= htmlspecialchars($t['ticket_numero']) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($t['nombres'] . ' ' . $t['apellidos']) ?></div>
                                <span class="badge bg-info text-dark small"><?= htmlspecialchars($t['eps_nombre']) ?></span>
                            </td>
                            <td>
                                <div><?= date('d/m/Y h:i A', strtotime($t['fecha_ingreso'])) ?></div>
                                <?php if ($esTemprano): ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-sun me-1"></i> Llegada Temprana</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($esTemprano): ?>
                                    <span class="fw-bold text-primary"><i class="fa-solid fa-door-open me-1"></i> <?= $horaAperturaFormatted ?></span>
                                    <div class="small text-muted">(Hora Apertura Sede)</div>
                                <?php else: ?>
                                    <div><?= date('h:i A', strtotime($t['fecha_ingreso'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y h:i A', strtotime($t['fecha_finalizacion'])) ?></td>
                            <td>
                                <?php if ($minFila > 0): ?>
                                    <span class="badge bg-light text-dark border"><?= $minFila ?> min</span>
                                <?php else: ?>
                                    <span class="text-muted small">0 min</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark fs-6">
                                <span class="badge bg-success fs-6 p-2"><?= $minSla ?> min</span>
                            </td>
                            <td>
                                <?php if ($minSla <= 15): ?>
                                    <span class="badge bg-success p-2"><i class="fa-solid fa-bolt me-1"></i> Excelente (<= 15 min)</span>
                                <?php elseif ($minSla <= 30): ?>
                                    <span class="badge bg-primary p-2"><i class="fa-solid fa-circle-check me-1"></i> Cumple (<= 30 min)</span>
                                <?php elseif ($minSla <= 45): ?>
                                    <span class="badge bg-warning text-dark p-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Tolerable (<= 45 min)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger p-2"><i class="fa-solid fa-circle-xmark me-1"></i> Fuera de SLA (> 45 min)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'pendientes'): ?>
    <?php $listaPendientes = $ingresoModel->getReportePendientes($fecha_desde, $fecha_hasta); ?>
    <div class="card card-glass border-0 shadow-sm border-start border-4 border-warning">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i> Reporte de Pacientes con Medicamentos Faltantes / Entregas Parciales</h5>
            <span class="badge bg-warning text-dark fs-6"><?= count($listaPendientes) ?> Casos Registrados</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Tiquete</th>
                            <th>Paciente / Identificación</th>
                            <th>EPS</th>
                            <th>Fecha Ingreso / Entrega</th>
                            <th>Estado Trámite</th>
                            <th>Detalle de Medicamentos Faltantes / Novedades</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listaPendientes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-circle-info fs-4 me-2 text-warning"></i> No se encontraron registros de medicamentos faltantes o pendientes en el rango de fechas seleccionado (<?= htmlspecialchars($fecha_desde) ?> a <?= htmlspecialchars($fecha_hasta) ?>).
                                    <div class="mt-2">
                                        <a href="index.php?page=reportes&tab=pendientes&fecha_desde=&fecha_hasta=" class="btn btn-sm btn-outline-warning text-dark fw-bold">
                                            <i class="fa-solid fa-list me-1"></i> Ver Histórico Completo (Sin filtro de fechas)
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($listaPendientes as $p): ?>
                        <?php 
                            $detalleFaltantes = !empty($p['faltantes_alistamiento']) ? $p['faltantes_alistamiento'] : ($p['observaciones_pendientes'] ?? '');
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary fs-6"><?= htmlspecialchars($p['ticket_numero']) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['nombres'] . ' ' . $p['apellidos']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars(($p['tipo_documento'] ?? '') . ' ' . ($p['numero_documento'] ?? '')) ?></small>
                            </td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($p['eps_nombre']) ?></span></td>
                            <td>
                                <div><i class="fa-solid fa-calendar-plus text-primary me-1"></i> <?= date('d/m/Y h:i A', strtotime($p['fecha_ingreso'])) ?></div>
                                <?php if (!empty($p['updated_at'])): ?>
                                    <small class="text-muted"><i class="fa-solid fa-hand-holding-medical text-success me-1"></i> <?= date('d/m/Y h:i A', strtotime($p['updated_at'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= get_estado_badge($p['estado_tramite']) ?></td>
                            <td style="min-width: 280px; max-width: 450px;">
                                <div class="p-2 bg-light rounded border border-warning text-dark small font-monospace" style="white-space: pre-wrap; max-height: 140px; overflow-y: auto;">
                                    <?= htmlspecialchars($detalleFaltantes) ?>
                                </div>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <?php if (!empty($p['numero_documento'])): ?>
                                        <a href="index.php?page=expedientes&buscar=<?= urlencode($p['numero_documento']) ?>" class="btn btn-sm btn-outline-primary" title="Ver Expediente Completo">
                                            <i class="fa-solid fa-folder-open me-1"></i> Expediente
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($p['estado_tramite'] === 'ENTREGADO' || !empty($p['firma_paciente_url'])): ?>
                                        <a href="index.php?page=imprimir_acta&id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-success fw-bold" title="Reimprimir Acta de Entrega">
                                            <i class="fa-solid fa-file-signature"></i>
                                        </a>
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

<?php elseif ($tab === 'productividad'): ?>
    <?php $listaProd = $ingresoModel->getReporteProductividad($fecha_desde, $fecha_hasta); ?>
    <div class="card card-glass border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-check me-2 text-success"></i> Reporte de Productividad por Usuario</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nombre del Usuario</th>
                            <th>Perfil / Rol</th>
                            <th class="text-end pe-3">Total de Ingresos / Atenciones Registradas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaProd as $pr): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= htmlspecialchars($pr['nombre_completo']) ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($pr['rol']) ?></span></td>
                            <td class="text-end pe-3 fw-bold fs-5 text-success"><?= $pr['total_ingresos'] ?> Atenciones</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'eps'): ?>
    <?php $listaEPS = $ingresoModel->getReportePorEPS($fecha_desde, $fecha_hasta); ?>
    <div class="card card-glass border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hospital-user me-2 text-info"></i> Distribución de Pacientes Atendidos por EPS</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Entidad Prestadora de Salud (EPS)</th>
                            <th class="text-end pe-3">Cantidad de Pacientes Atendidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaEPS as $ep): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-dark"><i class="fa-solid fa-notes-medical me-2 text-info"></i> <?= htmlspecialchars($ep['eps_nombre']) ?></td>
                            <td class="text-end pe-3 fw-bold fs-5 text-primary"><?= $ep['total_pacientes'] ?> Pacientes</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
