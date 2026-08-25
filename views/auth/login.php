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
        
        $permisos_raw = json_decode($userData['rol_permisos'] ?? '[]', true);
        $_SESSION['permisos'] = is_array($permisos_raw) && !empty($permisos_raw) 
            ? $permisos_raw 
            : ['dashboard', 'ingreso', 'expedientes', 'transcripcion', 'alistamiento', 'entrega', 'reportes', 'empresa', 'usuarios'];

        registrar_log_auditoria('AUTENTICACION', 'LOGIN_EXITOSO', $userData['id'], "Inicio de sesión exitoso usuario: {$userData['usuario']} ({$userData['nombre_completo']})");

        header('Location: index.php?page=dashboard');
        exit;
    } else {
        registrar_log_auditoria('AUTENTICACION', 'LOGIN_FALLIDO', null, "Intento fallido de inicio de sesión con el usuario: {$user}");
        $error = 'Usuario o contraseña incorrectos, o usuario inactivo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= htmlspecialchars(APP_NAME) ?></title>
    <!-- Favicon SISPAM -->
    <link rel="icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/logo_sispam.jpg">
    <link rel="apple-touch-icon" href="assets/img/logo_sispam.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card card-glass text-dark border-0 p-4 shadow-lg">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3">
                        <img src="assets/img/logo_sispam.jpg" alt="SISPAM Logo" class="rounded-circle shadow-lg border border-primary border-3" style="width: 90px; height: 90px; object-fit: cover;">
                    </div>
                    <h3 class="fw-bold text-dark mb-1" style="letter-spacing: 1.5px;">SISPAM</h3>
                    <p class="text-muted small">Sistema Integral de Gestión Farmacéutica & Dispensación</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" name="usuario" class="form-control" placeholder="Ej: admin, orientador..." required autofocus>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Iniciar Sesión
                    </button>
                </form>

                <div class="mt-4 p-3 bg-light rounded text-muted small">
                    <div class="fw-bold mb-1 text-primary"><i class="fa-solid fa-key me-1"></i> Credenciales de Prueba (Clave: <code>admin123</code>):</div>
                    <div>• Admin: <code>admin</code></div>
                    <div>• Orientador (Ingreso): <code>orientador</code></div>
                    <div>• Transcriptor: <code>transcriptor</code></div>
                    <div>• Monitor Farmacéutico: <code>monitor</code></div>
                    <div>• Supervisor Alistamiento: <code>supervisor_alistamiento</code></div>
                    <div>• Entregador: <code>entregador</code></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
