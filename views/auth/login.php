<?php

/**
 * ====================================================================
 * SISPAM - Sistema Integral de Gestión Farmacéutica
 * PHARMACEUTICAL HEALTHCARE SOFTWARE
 * Vista y Controlador Oficial de Inicio de Sesión
 * ====================================================================
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../models/Usuario.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $sede_seleccionada = trim($_POST['sede_id'] ?? '');

    $model = new Usuario();
    $userData = $model->login($user, $pass);

    if ($userData) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['nombre_completo'] = $userData['nombre_completo'];
        $_SESSION['usuario'] = $userData['usuario'];
        $_SESSION['rol_id'] = $userData['rol_id'];
        $_SESSION['rol_nombre'] = $userData['rol_nombre'];
        $_SESSION['empresa_id'] = $userData['empresa_id'] ?? 1;
        $_SESSION['empresa_nombre'] = $userData['empresa_nombre'] ?? 'SISPAM';

        // Sede asignada o seleccionada
        $sede_id = !empty($sede_seleccionada) ? (int)$sede_seleccionada : ($userData['sede_id'] ?? 1);
        $_SESSION['sede_id'] = $sede_id;
        $_SESSION['sede_nombre'] = $userData['sede_nombre'] ?? 'Farmacia Principal';
        $_SESSION['active_sede_id'] = $sede_id;
        $_SESSION['active_sede_nombre'] = $userData['sede_nombre'] ?? 'Farmacia Principal';

        $permisos_raw = json_decode($userData['rol_permisos'] ?? '[]', true);
        if ($userData['rol_nombre'] === 'Administrador') {
            $_SESSION['permisos'] = ['dashboard', 'ingreso', 'expedientes', 'transcripcion', 'monitoreo', 'alistamiento', 'supervision_alistamiento', 'entrega', 'reportes', 'empresa', 'usuarios', 'auditoria', 'modulos', 'turneros'];
        } else {
            $_SESSION['permisos'] = is_array($permisos_raw) ? $permisos_raw : ['dashboard'];
        }

        if (function_exists('registrar_log_auditoria')) {
            registrar_log_auditoria('AUTENTICACION', 'LOGIN_EXITOSO', $userData['id'], "Inicio de sesión exitoso usuario: {$userData['usuario']} ({$userData['nombre_completo']})");
        }

        header('Location: index.php?page=dashboard');
        exit;
    } else {
        if (function_exists('registrar_log_auditoria')) {
            registrar_log_auditoria('AUTENTICACION', 'LOGIN_FALLIDO', null, "Intento fallido de inicio de sesión con el usuario: {$user}");
        }
        $error = 'Usuario o contraseña incorrectos, o la cuenta se encuentra inactiva.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISPAM - Pharmaceutical Healthcare Software</title>

    <!-- Google Fonts: Outfit & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <div class="sispam-main-card">

        <!-- ========================================================= -->
        <!-- PANEL IZQUIERDO: Branding & 4 Módulos Clínicos             -->
        <!-- ========================================================= -->
        <section class="left-panel">
            <div>
                <!-- Header con Cápsula Glow -->
                <div class="brand-header">
                    <div class="brand-logo-badge">
                        <!-- Imagen del Logo en lugar del SVG -->
                        <img src="assets/img/logo_sispam.jpg" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=300&auto=format&fit=crop&q=80';" alt="SISPAM Logo" class="logo-image">
                    </div>
                    <div>
                        <h1 class="brand-title">SISPAM</h1>
                        <p class="brand-subtitle">PHARMACEUTICAL HEALTHCARE SOFTWARE</p>
                    </div>
                </div>

                <p class="brand-desc">
                    Sistema Integral de Gestión Farmacéutica, Transcripción Clínica &amp; Dispensación Segura de Medicamentos.
                </p>

                <!-- Cuadrícula 2x2 de Módulos Clínicos -->
                <div class="modules-grid">

                    <!-- 1. Seguridad & Trazabilidad -->
                    <div class="module-item">
                        <div class="module-icon-box icon-shield">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div class="module-info">
                            <h4>Seguridad &amp; Trazabilidad</h4>
                            <p>Auditoría en tiempo real de cada prescripción</p>
                        </div>
                    </div>

                    <!-- 2. Savia Salud & Mipres -->
                    <div class="module-item">
                        <div class="module-icon-box icon-file">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="module-info">
                            <h4>Savia Salud &amp; Mipres</h4>
                            <p>Gestión de fórmulas y soporte documental</p>
                        </div>
                    </div>

                    <!-- 3. Firma Digital & Entrega -->
                    <div class="module-item">
                        <div class="module-icon-box icon-signature">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </div>
                        <div class="module-info">
                            <h4>Firma Digital &amp; Entrega</h4>
                            <p>Actas digitales con captura fotográfica</p>
                        </div>
                    </div>

                    <!-- 4. Control de Alistamiento -->
                    <div class="module-item">
                        <div class="module-icon-box icon-box">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div class="module-info">
                            <h4>Control de Alistamiento</h4>
                            <p>Picking verificado y reducción de faltantes</p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer con Versión Operativa -->
            <footer class="left-footer">
                <span>&copy; <?= date('Y') ?> <strong>SISPAM</strong> - Sistema de Gestión Farmacéutica. Todos los derechos reservados.</span>
                <span class="status-pill">
                    <span class="status-dot"></span>
                    Plataforma Operativa v2.6
                </span>
            </footer>
        </section>

        <!-- ========================================================= -->
        <!-- PANEL DERECHO: Formulario de Inicio de Sesión             -->
        <!-- ========================================================= -->
        <section class="right-panel">
            <div>
                <h2 class="form-title">Iniciar Sesión</h2>
                <p class="form-subtitle">Ingrese sus credenciales autorizadas para ingresar a la plataforma.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="on" id="loginForm">

                    <!-- Campo 1: Usuario -->
                    <div class="form-group">
                        <label for="inputUsuario" class="form-label">USUARIO O DOCUMENTO</label>
                        <div class="input-box">
                            <span class="input-icon-left">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                            <input type="text"
                                name="usuario"
                                id="inputUsuario"
                                class="custom-input"
                                placeholder="farmaceutico@sispam.com"
                                required
                                autofocus
                                autocomplete="username">
                        </div>
                    </div>

                    <!-- Campo 2: Contraseña -->
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                            <label for="inputPassword" class="form-label" style="margin-bottom: 0;">CONTRASEÑA</label>
                            <a href="#" class="forgot-link">¿Olvidó su contraseña?</a>
                        </div>
                        <div class="input-box">
                            <span class="input-icon-left">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password"
                                name="password"
                                id="inputPassword"
                                class="custom-input"
                                placeholder="••••••••••••"
                                required
                                autocomplete="current-password"
                                style="padding-right: 2.85rem;">
                            <button type="button" class="btn-toggle-eye" id="btnTogglePassword" title="Mostrar u ocultar contraseña">
                                <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Desplegable Opcional: Asignar Sede / Turno / Ventanilla -->
                    <div>
                        <button type="button" class="sede-toggle-btn" onclick="toggleSedeSelector()">
                            <span>🏛️ ▼ Asignar Sede / Turno / Ventanilla</span>
                        </button>
                        <div class="sede-dropdown-wrapper" id="sedeDropdown">
                            <div class="input-box">
                                <select name="sede_id" class="custom-input" style="padding-left: 1rem;">
                                    <option value="1">Farmacia Principal - Hospital Universitario (SUC-001)</option>
                                    <option value="2">Dispensario Urgencias 24H (SUC-002)</option>
                                    <option value="3">Sede Ambulatoria Norte - Savia & MIPRES (SUC-003)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Botón Principal -->
                    <button type="submit" class="btn-submit-main" id="btnSubmit">
                        <span>Acceder a la Plataforma</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </form>

                <!-- Distintivo TLS -->
                <div class="tls-banner">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    <span>Acceso seguro con cifrado TLS y auditoría de eventos</span>
                </div>
            </div>

            <!-- Accesos Rápidos Demo (1 Clic) -->
            <div class="demo-section">
                <p class="demo-title">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    ACCESOS RÁPIDOS DE PRUEBA (1 CLIC):
                </p>
                <div class="demo-grid">
                    <button type="button" class="demo-card" onclick="fillDemo('farmaceutico@sispam.com', 'Dispensacion2026!')">
                        <span class="demo-role dispensador">💊 Dispensador</span>
                        <span class="demo-user">farmaceutico@...</span>
                    </button>
                    <button type="button" class="demo-card" onclick="fillDemo('regente@sispam.com', 'Regencia2026!')">
                        <span class="demo-role regente">🩺 Químico Regente</span>
                        <span class="demo-user">regente@...</span>
                    </button>
                </div>
            </div>
        </section>

    </div>

    <!-- Scripts de Interacción -->
    <script>
        // Alternar visualización de contraseña
        const btnToggle = document.getElementById('btnTogglePassword');
        const inputPwd = document.getElementById('inputPassword');
        const eyeIcon = document.getElementById('eyeIcon');

        if (btnToggle && inputPwd && eyeIcon) {
            btnToggle.addEventListener('click', () => {
                const isPassword = inputPwd.getAttribute('type') === 'password';
                inputPwd.setAttribute('type', isPassword ? 'text' : 'password');
                if (isPassword) {
                    eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>';
                } else {
                    eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
                }
            });
        }

        // Desplegar Asignar Sede / Turno
        function toggleSedeSelector() {
            const dropdown = document.getElementById('sedeDropdown');
            dropdown.classList.toggle('active');
        }

        // Llenar datos de prueba rápido
        // Llenar datos de prueba rápido
        function fillDemo(user, pass) {
            document.getElementById('inputUsuario').value = user;
            document.getElementById('inputPassword').value = pass;
        }

        // Efecto de carga en botón
        const form = document.getElementById('loginForm');
        const btnSubmit不易 = document.getElementById('btnSubmit');
        if (form && btnSubmit) {
            form.addEventListener('submit', () => {
                btnSubmit.innerHTML = 'Verificando credenciales...';
                btnSubmit.style.opacity = '0.85';
                btnSubmit.style.pointerEvents = 'none';
            });
        }
    </script>
</body>

</html>