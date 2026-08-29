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
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= file_exists(__DIR__ . '/../../assets/css/custom.css') ? filemtime(__DIR__ . '/../../assets/css/custom.css') : time() ?>">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<?php 
    $user_role = $_SESSION['rol_nombre'] ?? ''; 
    $user_name = $_SESSION['nombre_completo'] ?? 'Usuario'; 

    // Permisos dinámicos
    $has_ingreso       = has_permission('ingreso');
    $has_transcripcion = has_permission('transcripcion');
    $has_monitoreo     = has_permission('monitoreo');
    $has_alistamiento  = has_permission('alistamiento') || has_permission('supervision_alistamiento');
    $has_entrega       = has_permission('entrega');
    $operativos_count  = ($has_ingreso?1:0) + ($has_transcripcion?1:0) + ($has_monitoreo?1:0) + ($has_alistamiento?1:0) + ($has_entrega?1:0);

    $has_expedientes   = has_permission('expedientes');
    $has_reportes      = has_permission('reportes');
    $has_turneros      = has_permission('turneros');

    $has_empresa       = has_permission('empresa');
    $has_usuarios      = has_permission('usuarios');
    $has_modulos       = has_permission('modulos') || has_permission('empresa');
    $has_auditoria     = has_permission('auditoria');
    $can_config        = $has_empresa || $has_usuarios || $has_modulos || $has_auditoria;

    $curr_page = $_GET['page'] ?? 'dashboard';

    // Iniciales del usuario para avatar
    $palabras = explode(' ', trim($user_name));
    $iniciales = strtoupper(substr($palabras[0] ?? 'U', 0, 1) . substr($palabras[1] ?? ($palabras[0] ?? 'S'), 0, 1));
?>

<nav class="navbar navbar-expand-xl navbar-dark sispam-topbar shadow mb-4 py-1">
    <div class="container-fluid px-3">
        <!-- Logo & Marca SISPAM -->
        <a class="navbar-brand d-flex align-items-center gap-2 py-0 me-3 text-decoration-none" href="index.php?page=dashboard">
            <img src="assets/img/logo_sispam.jpg" alt="SISPAM Logo" class="rounded-circle border border-info shadow-sm" style="height: 34px; width: 34px; object-fit: cover;">
            <div class="d-flex flex-column" style="line-height: 1;">
                <span class="fw-bold fs-6 text-white" style="letter-spacing: 0.8px;">SISPAM</span>
                <span class="text-info text-uppercase" style="font-size: 0.58rem; letter-spacing: 0.5px; opacity: 0.85;">Gestión Farmacéutica</span>
            </div>
        </a>

        <!-- Botón Toggler para Dispositivos Móviles -->
        <button class="navbar-toggler py-1 px-2 border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon" style="width: 1.1em; height: 1.1em;"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Menú Principal con Agrupación Inteligente -->
            <ul class="navbar-nav me-auto mb-2 mb-xl-0 align-items-xl-center gap-1">
                <!-- 1. Inicio -->
                <li class="nav-item">
                    <a class="sispam-nav-link <?= $curr_page === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                        <i class="fa-solid fa-chart-line text-info"></i>
                        <span>Inicio</span>
                    </a>
                </li>

                <!-- 2. Flujo Operativo Asistencial -->
                <?php if ($operativos_count >= 3): ?>
                    <?php 
                        $is_operativo_active = in_array($curr_page, ['ingreso', 'transcripcion', 'monitoreo', 'alistamiento', 'entrega']);
                    ?>
                    <li class="nav-item dropdown">
                        <a class="sispam-nav-link dropdown-toggle <?= $is_operativo_active ? 'active' : '' ?>" href="#" id="dropOperativo" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-notes-medical text-primary"></i>
                            <span>Flujo Asistencial</span>
                        </a>
                        <ul class="dropdown-menu sispam-dropdown-menu mt-1" aria-labelledby="dropOperativo" style="min-width: 250px;">
                            <?php if ($has_ingreso): ?>
                            <li>
                                <a class="sispam-dropdown-item <?= $curr_page === 'ingreso' ? 'active' : '' ?>" href="index.php?page=ingreso">
                                    <i class="fa-solid fa-user-plus me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-bold">1. Admisión & Triage</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Ingreso de pacientes y turnos</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($has_transcripcion): ?>
                            <li>
                                <a class="sispam-dropdown-item <?= $curr_page === 'transcripcion' ? 'active' : '' ?>" href="index.php?page=transcripcion">
                                    <i class="fa-solid fa-file-signature me-2 text-info"></i>
                                    <div>
                                        <div class="fw-bold">2. Transcripción & Stock</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Validación de fórmulas y saldo</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($has_monitoreo): ?>
                            <li>
                                <a class="sispam-dropdown-item <?= $curr_page === 'monitoreo' ? 'active' : '' ?>" href="index.php?page=monitoreo">
                                    <i class="fa-solid fa-eye me-2 text-warning"></i>
                                    <div>
                                        <div class="fw-bold">3. Monitoreo Técnico</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Verificación de Regente / Químico</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($has_alistamiento): ?>
                            <li>
                                <a class="sispam-dropdown-item <?= $curr_page === 'alistamiento' ? 'active' : '' ?>" href="index.php?page=alistamiento">
                                    <i class="fa-solid fa-box-archive me-2 text-success"></i>
                                    <div>
                                        <div class="fw-bold">4. Alistamiento (Picking)</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Preparación física de medicamentos</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($has_entrega): ?>
                            <li>
                                <a class="sispam-dropdown-item <?= $curr_page === 'entrega' ? 'active' : '' ?>" href="index.php?page=entrega">
                                    <i class="fa-solid fa-hand-holding-medical me-2 text-danger"></i>
                                    <div>
                                        <div class="fw-bold">5. Entrega & Factura</div>
                                        <small class="text-muted" style="font-size: 0.72rem;">Llamado a ventanilla y firma digital</small>
                                    </div>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Módulos individuales si el rol solo tiene 1 o 2 módulos operativos -->
                    <?php if ($has_ingreso): ?>
                        <li class="nav-item"><a class="sispam-nav-link <?= $curr_page === 'ingreso' ? 'active' : '' ?>" href="index.php?page=ingreso"><i class="fa-solid fa-user-plus text-primary"></i> Admisión</a></li>
                    <?php endif; ?>
                    <?php if ($has_transcripcion): ?>
                        <li class="nav-item"><a class="sispam-nav-link <?= $curr_page === 'transcripcion' ? 'active' : '' ?>" href="index.php?page=transcripcion"><i class="fa-solid fa-file-signature text-info"></i> Transcripción</a></li>
                    <?php endif; ?>
                    <?php if ($has_monitoreo): ?>
                        <li class="nav-item"><a class="sispam-nav-link <?= $curr_page === 'monitoreo' ? 'active' : '' ?>" href="index.php?page=monitoreo"><i class="fa-solid fa-eye text-warning"></i> Monitoreo</a></li>
                    <?php endif; ?>
                    <?php if ($has_alistamiento): ?>
                        <li class="nav-item"><a class="sispam-nav-link <?= $curr_page === 'alistamiento' ? 'active' : '' ?>" href="index.php?page=alistamiento"><i class="fa-solid fa-box-archive text-success"></i> Alistamiento</a></li>
                    <?php endif; ?>
                    <?php if ($has_entrega): ?>
                        <li class="nav-item"><a class="sispam-nav-link <?= $curr_page === 'entrega' ? 'active' : '' ?>" href="index.php?page=entrega"><i class="fa-solid fa-hand-holding-medical text-danger"></i> Entrega</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- 3. Consulta de Expedientes -->
                <?php if ($has_expedientes): ?>
                <li class="nav-item">
                    <a class="sispam-nav-link <?= $curr_page === 'expedientes' ? 'active' : '' ?>" href="index.php?page=expedientes">
                        <i class="fa-solid fa-folder-open text-primary"></i>
                        <span>Expedientes</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- 4. Reportes & SLA -->
                <?php if ($has_reportes): ?>
                <li class="nav-item">
                    <a class="sispam-nav-link <?= $curr_page === 'reportes' ? 'active' : '' ?>" href="index.php?page=reportes">
                        <i class="fa-solid fa-chart-pie text-success"></i>
                        <span>Reportes</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- 5. Turneros TV -->
                <?php if ($has_turneros): ?>
                <li class="nav-item dropdown">
                    <a class="sispam-nav-link dropdown-toggle <?= in_array($curr_page, ['turnero1', 'turnero2']) ? 'active' : '' ?>" href="#" id="dropTurneros" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-tv text-warning"></i>
                        <span>Turneros TV</span>
                    </a>
                    <ul class="dropdown-menu sispam-dropdown-menu mt-1" aria-labelledby="dropTurneros" style="min-width: 220px;">
                        <li>
                            <a class="sispam-dropdown-item" href="index.php?page=turnero1" target="_blank">
                                <i class="fa-solid fa-desktop me-2 text-info"></i>
                                <div>
                                    <div class="fw-bold">Turnero 1</div>
                                    <small class="text-muted" style="font-size: 0.72rem;">Pantalla En Proceso</small>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="sispam-dropdown-item" href="index.php?page=turnero2" target="_blank">
                                <i class="fa-solid fa-bullhorn me-2 text-danger"></i>
                                <div>
                                    <div class="fw-bold">Turnero 2</div>
                                    <small class="text-muted" style="font-size: 0.72rem;">Pantalla Listo Entrega (Audio)</small>
                                </div>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- 6. Configuración & Administración -->
                <?php if ($can_config): ?>
                <?php 
                    $is_config_active = in_array($curr_page, ['empresa', 'usuarios', 'modulos', 'auditoria', 'importar_pacientes']);
                ?>
                <li class="nav-item dropdown">
                    <a class="sispam-nav-link dropdown-toggle <?= $is_config_active ? 'active' : '' ?>" href="#" id="dropConfig" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-gears text-secondary"></i>
                        <span>Administración</span>
                    </a>
                    <ul class="dropdown-menu sispam-dropdown-menu mt-1" aria-labelledby="dropConfig" style="min-width: 240px;">
                        <?php if ($has_empresa): ?>
                        <li>
                            <a class="sispam-dropdown-item <?= $curr_page === 'empresa' ? 'active' : '' ?>" href="index.php?page=empresa">
                                <i class="fa-solid fa-building me-2 text-primary"></i> Empresa & Sedes
                            </a>
                        </li>
                        <li>
                            <a class="sispam-dropdown-item <?= $curr_page === 'importar_pacientes' ? 'active' : '' ?>" href="index.php?page=importar_pacientes">
                                <i class="fa-solid fa-file-csv me-2 text-info"></i> Carga Masiva Pacientes
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($has_usuarios): ?>
                        <li>
                            <a class="sispam-dropdown-item <?= $curr_page === 'usuarios' ? 'active' : '' ?>" href="index.php?page=usuarios">
                                <i class="fa-solid fa-users me-2 text-success"></i> Usuarios & Permisos
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($has_modulos): ?>
                        <li>
                            <a class="sispam-dropdown-item <?= $curr_page === 'modulos' ? 'active' : '' ?>" href="index.php?page=modulos">
                                <i class="fa-solid fa-door-open me-2 text-warning"></i> Ventanillas & Módulos
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($has_auditoria): ?>
                        <li>
                            <a class="sispam-dropdown-item <?= $curr_page === 'auditoria' ? 'active' : '' ?>" href="index.php?page=auditoria">
                                <i class="fa-solid fa-clock-rotate-left me-2 text-danger"></i> Log de Auditoría
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Sección Derecha: Sede Activa + Perfil + Salir -->
            <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto mt-2 mt-xl-0">
                <?php 
                $active_sede_name = $_SESSION['active_sede_nombre'] ?? $_SESSION['sede_nombre'] ?? 'Sede Principal';
                $active_sede_id   = $_SESSION['active_sede_id'] ?? $_SESSION['sede_id'] ?? 1;
                
                $user_id_act = $_SESSION['user_id'] ?? 0;
                $mis_sedes   = [];
                if ($user_role === 'Administrador') {
                    require_once __DIR__ . '/../../models/Empresa.php';
                    $empModelHeader = new Empresa();
                    $mis_sedes = $empModelHeader->getTodasSedes();
                } else if ($user_id_act) {
                    require_once __DIR__ . '/../../models/Usuario.php';
                    $uModelHeader = new Usuario();
                    $mis_sedes = $uModelHeader->getSedesUsuario($user_id_act);
                }
                $can_switch_sede = (count($mis_sedes) > 1 || $user_role === 'Administrador');
                ?>

                <!-- 1. Píldora de Sede Activa (Nombre Completo y Destacado) -->
                <div class="dropdown">
                    <button class="btn sede-pill-btn border-0 shadow-sm" 
                            type="button" 
                            id="dropdownSede" 
                            <?= $can_switch_sede ? 'data-bs-toggle="dropdown" aria-expanded="false"' : '' ?> 
                            title="<?= $can_switch_sede ? 'Sede de trabajo activa. Haga clic para cambiar.' : 'Sede de trabajo asignada' ?>">
                        <i class="fa-solid fa-location-dot text-warning"></i>
                        <span class="text-white-50 small d-none d-lg-inline">Sede:</span>
                        <span class="text-warning fw-bold"><?= htmlspecialchars($active_sede_name) ?></span>
                        <?php if ($can_switch_sede): ?>
                            <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.65rem; opacity: 0.85;"></i>
                        <?php endif; ?>
                    </button>
                    <?php if ($can_switch_sede): ?>
                    <ul class="dropdown-menu sispam-dropdown-menu dropdown-menu-end mt-1" aria-labelledby="dropdownSede" style="min-width: 250px;">
                        <li class="dropdown-header text-uppercase fw-bold text-muted small px-3 py-1" style="font-size: 0.68rem;">
                            <i class="fa-solid fa-shuffle me-1 text-warning"></i> Cambiar Sede de Trabajo
                        </li>
                        <?php foreach ($mis_sedes as $s): ?>
                        <li>
                            <a class="sispam-dropdown-item justify-content-between <?= $s['id'] == $active_sede_id ? 'active' : '' ?>" href="index.php?cambiar_sede_id=<?= $s['id'] ?>">
                                <span><i class="fa-solid fa-building me-2 <?= $s['id'] == $active_sede_id ? 'text-white' : 'text-warning' ?>"></i> <?= htmlspecialchars($s['nombre_sede']) ?></span>
                                <?php if ($s['id'] == $active_sede_id): ?>
                                    <i class="fa-solid fa-check ms-2"></i>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- 2. Menú de Perfil con Avatar Bubble & Salir Integrado -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-dark border border-secondary border-opacity-50 text-white d-flex align-items-center gap-2 py-1 px-2 rounded-3 shadow-sm" 
                            type="button" 
                            id="dropdownUser" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false"
                            style="background: rgba(255, 255, 255, 0.06);">
                        <div class="user-avatar-bubble"><?= htmlspecialchars($iniciales) ?></div>
                        <div class="text-start d-none d-md-block" style="line-height: 1.15;">
                            <div class="fw-bold text-white text-truncate" style="max-width: 140px; font-size: 0.8rem;"><?= htmlspecialchars($user_name) ?></div>
                            <div class="text-info text-truncate" style="font-size: 0.65rem;"><?= htmlspecialchars($user_role ?: 'Usuario') ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-white-50 ms-1" style="font-size: 0.65rem;"></i>
                    </button>
                    <ul class="dropdown-menu sispam-dropdown-menu dropdown-menu-end mt-1 shadow-lg" aria-labelledby="dropdownUser" style="min-width: 240px;">
                        <li class="px-3 py-2 border-bottom border-secondary border-opacity-25 mb-1">
                            <div class="fw-bold text-white"><?= htmlspecialchars($user_name) ?></div>
                            <span class="badge bg-primary text-white mt-1" style="font-size: 0.68rem;"><?= htmlspecialchars($user_role ?: 'Usuario') ?></span>
                        </li>
                        <?php if ($has_usuarios): ?>
                        <li>
                            <a class="sispam-dropdown-item" href="index.php?page=usuarios&subtab=permisos">
                                <i class="fa-solid fa-shield-halved me-2 text-primary"></i> Matriz de Permisos
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-1 border-secondary border-opacity-25"></li>
                        <li>
                            <a class="sispam-dropdown-item text-danger fw-bold py-2" href="index.php?page=logout">
                                <i class="fa-solid fa-power-off me-2"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container-fluid px-3 px-md-4 pb-5">

