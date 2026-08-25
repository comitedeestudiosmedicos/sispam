<?php
require_once __DIR__ . '/../config/app.php';
check_auth();

require_once __DIR__ . '/../models/Ingreso.php';
require_once __DIR__ . '/../models/Empresa.php';

$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$config = $empresaModel->getConfig();

$transcripcionList = $ingresoModel->getListaTranscripcion();
$alistamientoList = $ingresoModel->getListaAlistamiento();
$entregaList = $ingresoModel->getListaEntrega();

require_once __DIR__ . '/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-hospital-user text-primary me-2"></i> Panel de Control y Operación</h3>
        <p class="text-muted"><?= htmlspecialchars($config['razon_social']) ?> | NIT: <?= htmlspecialchars($config['nit']) ?></p>
    </div>
    <div class="col-md-4 text-md-end align-self-center">
        <span class="badge bg-light text-dark p-2 border shadow-sm">
            <i class="fa-regular fa-clock me-1 text-primary"></i> <?= date('d/m/Y h:i A') ?>
        </span>
    </div>
</div>

<!-- Tarjetas KPI -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-primary p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">EN TRANSCRIPCIÓN</div>
                    <div class="fs-2 fw-bold text-primary"><?= count($transcripcionList) ?></div>
                </div>
                <div class="bg-primary text-white p-3 rounded-circle">
                    <i class="fa-solid fa-keyboard fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-warning p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">POR ALISTAR (PICKING)</div>
                    <div class="fs-2 fw-bold text-warning"><?= count($alistamientoList) ?></div>
                </div>
                <div class="bg-warning text-dark p-3 rounded-circle">
                    <i class="fa-solid fa-boxes-stacked fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-info p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">LISTOS EN ENTREGA</div>
                    <div class="fs-2 fw-bold text-info"><?= count($entregaList) ?></div>
                </div>
                <div class="bg-info text-dark p-3 rounded-circle">
                    <i class="fa-solid fa-hand-holding-medical fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card card-glass border-start border-4 border-success p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small fw-semibold">ROL ACTIVO</div>
                    <div class="fs-5 fw-bold text-success"><?= htmlspecialchars($_SESSION['rol_nombre'] ?? 'Usuario') ?></div>
                </div>
                <div class="bg-success text-white p-3 rounded-circle">
                    <i class="fa-solid fa-id-badge fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accesos Rápidos por Perfil -->
<div class="row g-4">
    <?php if (has_permission('ingreso') || $user_role === 'Administrador' || $user_role === 'Orientador'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-primary h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-primary"><i class="fa-solid fa-user-plus me-2"></i> Módulo de Admisión</h5>
                <p class="text-muted small">Ingreso de pacientes nuevos o existentes con cédula colombiana, registro de EPS y digitalización de documentos con generación de carpetas e impresión de tiquetes.</p>
                <a href="index.php?page=ingreso" class="btn btn-outline-primary btn-sm fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Ingreso</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('transcripcion') || $user_role === 'Administrador' || $user_role === 'Transcripcion'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-indigo h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold" style="color: #4f46e5;"><i class="fa-solid fa-file-signature me-2"></i> Transcripción & Visor PDF</h5>
                <p class="text-muted small">Lista de trabajo FEFO con bloqueo de registros por concurrencia, visor PDF dual en pantalla completa y notificación de pendientes al orientador.</p>
                <a href="index.php?page=transcripcion" class="btn btn-outline-primary btn-sm fw-bold" style="color:#4f46e5; border-color:#4f46e5;"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Transcripción</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('monitoreo') || $user_role === 'Administrador' || $user_role === 'Monitor'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-info h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-info"><i class="fa-solid fa-eye me-2"></i> Monitoreo & Verificación</h5>
                <p class="text-muted small">Verificación técnica farmacéutica de fórmulas transcritas, sustitución/edición de PDF transcrito con sello digital y auditoría de stock.</p>
                <a href="index.php?page=monitoreo" class="btn btn-outline-info btn-sm fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Monitoreo</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('supervision_alistamiento') || has_permission('alistamiento') || $user_role === 'Administrador' || $user_role === 'Supervisor de Alistamiento' || $user_role === 'Alistamiento'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-success h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-success"><i class="fa-solid fa-user-check me-2"></i> Supervisión de Alistamiento</h5>
                <p class="text-muted small">Auditoría, control de calidad y supervisión de picking de medicamentos, desbloqueo de órdenes y aprobación final para entrega.</p>
                <a href="index.php?page=alistamiento" class="btn btn-outline-success btn-sm fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Supervisión Alistamiento</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('entrega') || $user_role === 'Administrador' || $user_role === 'Entrega'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-teal h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold" style="color: #0d9488;"><i class="fa-solid fa-hand-holding-medical me-2"></i> Entrega y Firma Digital</h5>
                <p class="text-muted small">Recepción de pacientes en ventanilla, firma digitalizada en pantalla táctil/tableta e integración de acta de entrega firmada.</p>
                <a href="index.php?page=entrega" class="btn btn-outline-success btn-sm fw-bold" style="color: #0d9488; border-color: #0d9488;"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Entrega</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('expedientes') || $user_role === 'Administrador'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-secondary h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-secondary"><i class="fa-solid fa-folder-tree me-2"></i> Consulta de Expedientes</h5>
                <p class="text-muted small">Búsqueda rápida por número de documento para listar todas las órdenes históricas del paciente y sus documentos adjuntos.</p>
                <a href="index.php?page=expedientes" class="btn btn-outline-secondary btn-sm fw-bold"><i class="fa-solid fa-magnifying-glass me-1"></i> Consultar Expedientes</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('reportes') || $user_role === 'Administrador' || $user_role === 'Regente'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-danger h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-danger"><i class="fa-solid fa-chart-pie me-2"></i> Reportes & Analítica SLA</h5>
                <p class="text-muted small">Métricas de tiempo de atención, indicadores de gestión, cuellos de botella por estación y exportación de informes en Excel/CSV.</p>
                <a href="index.php?page=reportes" class="btn btn-outline-danger btn-sm fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Reportes</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (has_permission('auditoria') || $user_role === 'Administrador' || $user_role === 'Regente'): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-dark h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left me-2 text-danger"></i> Log de Auditoría & Trazas</h5>
                <p class="text-muted small">Registro histórico e inmutable de todas las acciones, modificaciones, accesos y transacciones realizadas por los usuarios en el sistema.</p>
                <a href="index.php?page=auditoria" class="btn btn-outline-dark btn-sm fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Ir a Log de Auditoría</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-md-6 col-lg-4">
        <div class="card card-glass card-module-dark h-100 p-3">
            <div class="card-body">
                <h5 class="fw-bold text-dark"><i class="fa-solid fa-tv me-2"></i> Pantallas TV Turnero</h5>
                <p class="text-muted small">Acceso a las vistas de sala de espera: Turnero 1 ("En Proceso") y Turnero 2 ("Listo para Entrega con audio").</p>
                <div class="d-flex gap-2">
                    <a href="index.php?page=turnero1" target="_blank" class="btn btn-sm btn-dark"><i class="fa-solid fa-desktop me-1"></i> TV 1</a>
                    <a href="index.php?page=turnero2" target="_blank" class="btn btn-sm btn-primary"><i class="fa-solid fa-bullhorn me-1"></i> TV 2</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
