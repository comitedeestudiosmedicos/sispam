<?php
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
        $_SESSION['sede_id'] = $userData['sede_id'] ?? 1;
        $_SESSION['sede_nombre'] = $userData['sede_nombre'] ?? 'Sede Principal';
        $_SESSION['active_sede_id'] = $userData['sede_id'] ?? 1;
        $_SESSION['active_sede_nombre'] = $userData['sede_nombre'] ?? 'Sede Principal';
        
        $permisos_raw = json_decode($userData['rol_permisos'] ?? '[]', true);
        if ($userData['rol_nombre'] === 'Administrador') {
            $_SESSION['permisos'] = ['dashboard', 'ingreso', 'expedientes', 'transcripcion', 'monitoreo', 'alistamiento', 'supervision_alistamiento', 'entrega', 'reportes', 'empresa', 'usuarios', 'auditoria', 'modulos', 'turneros'];
        } else {
            $_SESSION['permisos'] = is_array($permisos_raw) ? $permisos_raw : ['dashboard'];
        }

        registrar_log_auditoria('AUTENTICACION', 'LOGIN_EXITOSO', $userData['id'], "Inicio de sesión exitoso usuario: {$userData['usuario']} ({$userData['nombre_completo']})");

        header('Location: index.php?page=dashboard');
        exit;
    } else {
        registrar_log_auditoria('AUTENTICACION', 'LOGIN_FALLIDO', null, "Intento fallido de inicio de sesión con el usuario: {$user}");
        $error = 'Usuario o contraseña incorrectos, o la cuenta se encuentra inactiva.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - <?= htmlspecialchars(APP_NAME) ?></title>
    <!-- Favicon SISPAM -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="apple-touch-icon" href="assets/img/logo_sispam.jpg">
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0284c7 0%, #2563eb 50%, #1d4ed8 100%);
            --dark-gradient: linear-gradient(145deg, #090e17 0%, #0f172a 45%, #1e3a8a 100%);
            --accent-cyan: #00f2fe;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b1329;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            color: #1e293b;
        }

        .login-wrapper {
            min-height: 100vh;
            width: 100vw;
            display: flex;
            overflow-x: hidden;
        }

        /* PANEL IZQUIERDO (Branding & Logo) */
        .brand-panel {
            flex: 1 1 52%;
            background: var(--dark-gradient);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3.5rem 4rem;
            overflow: hidden;
            color: #ffffff;
        }

        /* Efectos de fondo y halo */
        .brand-panel::before {
            content: '';
            position: absolute;
            top: -15%;
            left: -15%;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(2, 132, 199, 0.35) 0%, rgba(2, 132, 199, 0) 70%);
            filter: blur(40px);
            pointer-events: none;
        }

        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.3) 0%, rgba(37, 99, 235, 0) 70%);
            filter: blur(50px);
            pointer-events: none;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            max-width: 560px;
            margin: auto 0;
        }

        .logo-container {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .logo-glow {
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00f2fe, #2563eb, #38bdf8);
            opacity: 0.75;
            filter: blur(12px);
            animation: pulseGlow 4s infinite alternate ease-in-out;
        }

        .logo-image {
            position: relative;
            width: 140px;
            height: 140px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            display: block;
        }

        @keyframes pulseGlow {
            0% { transform: scale(0.96); opacity: 0.5; filter: blur(10px); }
            100% { transform: scale(1.06); opacity: 0.9; filter: blur(16px); }
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 3.2rem;
            font-weight: 800;
            letter-spacing: 2px;
            background: linear-gradient(to right, #ffffff, #93c5fd, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            line-height: 1.1;
        }

        .brand-subtitle {
            font-size: 1.15rem;
            color: #cbd5e1;
            font-weight: 400;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-top: 1rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(56, 189, 248, 0.4);
            transform: translateY(-2px);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.4), rgba(37, 99, 235, 0.4));
            border: 1px solid rgba(56, 189, 248, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #38bdf8;
            flex-shrink: 0;
        }

        .feature-text h6 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
        }

        .feature-text small {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.3;
            display: block;
        }

        .brand-footer {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #64748b;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1.5rem;
        }

        /* PANEL DERECHO (Formulario de Login) */
        .form-panel {
            flex: 1 1 48%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3.5rem 4rem;
            position: relative;
        }

        .form-container {
            width: 100%;
            max-width: 420px;
        }

        .form-header {
            margin-bottom: 2.2rem;
        }

        .form-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }

        .form-header p {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0;
        }

        .custom-form-group {
            margin-bottom: 1.4rem;
        }

        .custom-form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.45rem;
        }

        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-wrapper .input-icon {
            position: absolute;
            left: 1rem;
            color: #94a3b8;
            font-size: 1.05rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .input-icon-wrapper .form-control {
            padding-left: 2.85rem;
            padding-right: 2.85rem;
            height: 52px;
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.95rem;
            font-weight: 500;
            color: #0f172a;
            background-color: #f8fafc;
            transition: all 0.25s ease;
        }

        .input-icon-wrapper .form-control:focus {
            background-color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            outline: none;
        }

        .input-icon-wrapper .form-control:focus + .input-icon,
        .input-icon-wrapper:focus-within .input-icon {
            color: #2563eb;
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: #94a3b8;
            padding: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.2s ease;
        }

        .btn-toggle-pwd:hover {
            color: #334155;
        }

        .btn-submit-login {
            background: var(--primary-gradient);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            height: 52px;
            width: 100%;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            transition: all 0.3s ease;
            margin-top: 1.8rem;
        }

        .btn-submit-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(37, 99, 235, 0.55);
            background: linear-gradient(135deg, #0369a1 0%, #1d4ed8 50%, #1e40af 100%);
            color: #ffffff;
        }

        .btn-submit-login:active {
            transform: translateY(0);
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 0.85rem;
            background: #f1f5f9;
            border-radius: 12px;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 500;
        }

        .security-badge i {
            color: #10b981;
            font-size: 0.95rem;
        }

        /* ALERTAS */
        .custom-alert {
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .custom-alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* RESPONSIVE DESIGN (Tablets y Smartphones) */
        @media (max-width: 991.98px) {
            .login-wrapper {
                flex-direction: column;
            }

            .brand-panel {
                flex: 0 0 auto;
                padding: 2.5rem 1.75rem 2rem 1.75rem;
                text-align: center;
            }

            .brand-content {
                margin: 0 auto;
                max-width: 100%;
            }

            .logo-image {
                width: 100px;
                height: 100px;
                margin: 0 auto;
            }

            .brand-title {
                font-size: 2.3rem;
            }

            .brand-subtitle {
                font-size: 1rem;
                margin-bottom: 1.25rem;
            }

            .feature-grid {
                display: none; /* Ocultar tarjetas de características en pantallas pequeñas para dar prioridad al login */
            }

            .brand-footer {
                display: none;
            }

            .form-panel {
                flex: 1 0 auto;
                padding: 2.5rem 1.75rem;
                border-radius: 28px 28px 0 0;
                margin-top: -16px;
                box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.2);
            }

            .form-header h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- PANEL IZQUIERDO: Branding, Logo Grande y Valor del Sistema -->
    <div class="brand-panel">
        <div class="brand-content">
            <!-- Logo Grande con Efecto de Halo Luminoso -->
            <div class="logo-container">
                <div class="logo-glow"></div>
                <img src="assets/img/logo_sispam.jpg" alt="SISPAM Logo" class="logo-image">
            </div>

            <!-- Nombre y Eslogan -->
            <h1 class="brand-title">SISPAM</h1>
            <p class="brand-subtitle">
                Sistema Integral de Gestión Farmacéutica, Transcripción Clínica & Dispensación Segura de Medicamentos.
            </p>

            <!-- Tarjetas de Características Clave -->
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="feature-text">
                        <h6>Seguridad & Trazabilidad</h6>
                        <small>Auditoría en tiempo real de cada prescripción</small>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-file-prescription"></i>
                    </div>
                    <div class="feature-text">
                        <h6>Savia Salud & Mipres</h6>
                        <small>Gestión de fórmulas y soporte documental</small>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-signature"></i>
                    </div>
                    <div class="feature-text">
                        <h6>Firma Digital & Entrega</h6>
                        <small>Actas digitales con captura fotográfica</small>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </div>
                    <div class="feature-text">
                        <h6>Control de Alistamiento</h6>
                        <small>Picking verificado y reducción de faltantes</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(APP_NAME) ?></strong>. Todos los derechos reservados.</span>
            <span><i class="fa-solid fa-circle-check text-info me-1"></i> Plataforma Operativa v2.6</span>
        </div>
    </div>

    <!-- PANEL DERECHO: Formulario de Acceso Directo y Seguro -->
    <div class="form-panel">
        <div class="form-container">
            <div class="form-header">
                <h2>Iniciar Sesión</h2>
                <p>Ingrese sus credenciales autorizadas para ingresar a la plataforma.</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="custom-alert custom-alert-danger" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-5 flex-shrink-0"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on" id="loginForm">
                <!-- Usuario -->
                <div class="custom-form-group">
                    <label for="inputUsuario">Usuario o Documento</label>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input type="text" 
                               name="usuario" 
                               id="inputUsuario" 
                               class="form-control" 
                               placeholder="Ingrese su usuario corporativo" 
                               required 
                               autofocus 
                               autocomplete="username">
                    </div>
                </div>

                <!-- Contraseña con botón Mostrar/Ocultar -->
                <div class="custom-form-group">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="inputPassword" class="mb-0">Contraseña</label>
                    </div>
                    <div class="input-icon-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" 
                               name="password" 
                               id="inputPassword" 
                               class="form-control" 
                               placeholder="••••••••••••" 
                               required 
                               autocomplete="current-password">
                        <button type="button" class="btn-toggle-pwd" id="btnTogglePassword" title="Mostrar/Ocultar contraseña" tabindex="-1">
                            <i class="fa-solid fa-eye" id="iconTogglePassword"></i>
                        </button>
                    </div>
                </div>

                <!-- Botón de Envío -->
                <button type="submit" class="btn-submit-login" id="btnSubmit">
                    <span>Acceder a la Plataforma</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <!-- Distintivo de Seguridad y Cumplimiento Normativo -->
            <div class="security-badge">
                <i class="fa-solid fa-lock"></i>
                <span>Acceso seguro con cifrado TLS y auditoría de eventos</span>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Toggle para mostrar/ocultar contraseña
    const btnToggle = document.getElementById('btnTogglePassword');
    const inputPwd  = document.getElementById('inputPassword');
    const iconPwd   = document.getElementById('iconTogglePassword');

    if (btnToggle && inputPwd && iconPwd) {
        btnToggle.addEventListener('click', () => {
            const isPassword = inputPwd.getAttribute('type') === 'password';
            inputPwd.setAttribute('type', isPassword ? 'text' : 'password');
            iconPwd.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    }

    // Efecto visual al enviar formulario
    const form = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnSubmit');
    if (form && btnSubmit) {
        form.addEventListener('submit', () => {
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Verificando credenciales...';
            btnSubmit.style.opacity = '0.85';
            btnSubmit.style.pointerEvents = 'none';
        });
    }
</script>

</body>
</html>
