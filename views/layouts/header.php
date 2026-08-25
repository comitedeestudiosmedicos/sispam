<?php
require_once __DIR__ . '/../../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <!-- Favicon SISPAM -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="apple-touch-icon" href="assets/img/logo_sispam.jpg">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<?php 
    $user_role = $_SESSION['rol_nombre'] ?? ''; 
    $user_name = $_SESSION['nombre_completo'] ?? 'Usuario'; 
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand navbar-brand-custom text-white d-flex align-items-center gap-2" href="index.php?page=dashboard">
            <img src="assets/img/logo_sispam.jpg" alt="SISPAM Logo" class="rounded-circle border border-info shadow-sm" style="height: 38px; width: 38px; object-fit: cover;">
            <span class="fw-bold fs-5 text-white" style="letter-spacing: 1px;">SISPAM</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'dashboard' ? 'active fw-bold' : '' ?>" href="index.php?page=dashboard">
                        <i class="fa-solid fa-chart-line me-1"></i> Inicio
                    </a>
                </li>
                
                <?php if (has_permission('ingreso')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'ingreso' ? 'active fw-bold' : '' ?>" href="index.php?page=ingreso">
                        <i class="fa-solid fa-user-plus me-1"></i> Admisión / Ingreso
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('transcripcion')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'transcripcion' ? 'active fw-bold' : '' ?>" href="index.php?page=transcripcion">
                        <i class="fa-solid fa-file-signature me-1"></i> Transcripción & Stock
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('monitoreo') || has_permission('transcripcion') || $user_role === 'Administrador' || $user_role === 'Monitor'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'monitoreo' ? 'active fw-bold' : '' ?>" href="index.php?page=monitoreo">
                        <i class="fa-solid fa-eye me-1"></i> Monitoreo & Verificación
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('alistamiento') || has_permission('supervision_alistamiento') || $user_role === 'Administrador' || $user_role === 'Alistamiento' || $user_role === 'Supervisor de Alistamiento'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'alistamiento' ? 'active fw-bold' : '' ?>" href="index.php?page=alistamiento">
                        <i class="fa-solid fa-user-check me-1"></i> Supervisión de Alistamiento
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('entrega')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'entrega' ? 'active fw-bold' : '' ?>" href="index.php?page=entrega">
                        <i class="fa-solid fa-hand-holding-medical me-1"></i> Entrega & Factura
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('reportes')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'reportes' ? 'active fw-bold' : '' ?>" href="index.php?page=reportes">
                        <i class="fa-solid fa-chart-pie me-1"></i> Reportes & SLA
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('expedientes') || has_permission('ingreso')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['page'] ?? '') === 'expedientes' ? 'active fw-bold' : '' ?>" href="index.php?page=expedientes">
                        <i class="fa-solid fa-folder-open me-1"></i> Consulta Ordenes
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array(($_GET['page'] ?? ''), ['empresa', 'usuarios', 'modulos', 'auditoria']) ? 'active fw-bold' : '' ?>" href="#" id="navbarDropdownConfig" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-gears me-1"></i> Configuración
                    </a>
                    <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdownConfig">
                        <?php if (has_permission('empresa') || $user_role === 'Administrador'): ?>
                        <li><a class="dropdown-item py-2" href="index.php?page=empresa"><i class="fa-solid fa-building me-2 text-primary"></i> Empresa & Sede</a></li>
                        <li><a class="dropdown-item py-2" href="index.php?page=importar_pacientes"><i class="fa-solid fa-file-csv me-2 text-info"></i> Carga Masiva Pacientes (CSV)</a></li>
                        <?php endif; ?>
                        <?php if (has_permission('usuarios') || $user_role === 'Administrador'): ?>
                        <li><a class="dropdown-item py-2" href="index.php?page=usuarios"><i class="fa-solid fa-users me-2 text-success"></i> Usuarios & Permisos</a></li>
                        <?php endif; ?>
                        <?php if (has_permission('modulos') || $user_role === 'Administrador'): ?>
                        <li><a class="dropdown-item py-2" href="index.php?page=modulos"><i class="fa-solid fa-door-open me-2 text-warning"></i> Ventanillas & Módulos</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item py-2" href="index.php?page=auditoria"><i class="fa-solid fa-clock-rotate-left me-2 text-danger"></i> Log de Auditoría & Trazas</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array(($_GET['page'] ?? ''), ['turnero1', 'turnero2']) ? 'active fw-bold' : '' ?>" href="#" id="navbarDropdownTurneros" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-tv me-1"></i> Turneros TV
                    </a>
                    <ul class="dropdown-menu shadow border-0" aria-labelledby="navbarDropdownTurneros">
                        <li><a class="dropdown-item py-2" href="index.php?page=turnero1" target="_blank"><i class="fa-solid fa-desktop me-2 text-info"></i> Turnero 1 (En Proceso)</a></li>
                        <li><a class="dropdown-item py-2" href="index.php?page=turnero2" target="_blank"><i class="fa-solid fa-bullhorn me-2 text-danger"></i> Turnero 2 (Listo Entrega)</a></li>
                    </ul>
                </li>
            </ul>

            <div class="d-flex align-items-center text-white gap-3">
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold small"><?= htmlspecialchars($user_name) ?></div>
                    <span class="badge bg-info text-dark"><?= htmlspecialchars($user_role ?: 'Usuario') ?></span>
                </div>
                <a href="index.php?page=logout" class="btn btn-outline-light btn-sm" title="Cerrar Sesión">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container-fluid px-4 pb-5">
