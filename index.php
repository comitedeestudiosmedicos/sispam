<?php
/**
 * Front Controller & Router Principal SISPAM
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Ingreso.php';

$page = $_GET['page'] ?? 'dashboard';

// Manejo de cambio dinámico de Sede de trabajo para el usuario autenticado
if (isset($_GET['cambiar_sede_id']) && isset($_SESSION['user_id'])) {
    $nueva_sede_id = intval($_GET['cambiar_sede_id']);
    require_once __DIR__ . '/models/Empresa.php';
    $empModel = new Empresa();
    $sedeInfo = $empModel->getSedeById($nueva_sede_id);
    if ($sedeInfo) {
        $_SESSION['active_sede_id'] = $sedeInfo['id'];
        $_SESSION['active_sede_nombre'] = $sedeInfo['nombre_sede'];
        $_SESSION['sede_id'] = $sedeInfo['id'];
        $_SESSION['sede_nombre'] = $sedeInfo['nombre_sede'];
        
        registrar_log_auditoria('USUARIOS', 'CAMBIO_SEDE_ACTIVA', $sedeInfo['id'], "Usuario cambió su sede de trabajo activa a: {$sedeInfo['nombre_sede']}");
    }
    $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php?page=dashboard';
    $redirect = preg_replace('/([?&])cambiar_sede_id=[^&]+(&|$)/', '$1', $redirect);
    $redirect = rtrim($redirect, '?&');
    if (empty($redirect)) $redirect = 'index.php?page=dashboard';
    header("Location: " . $redirect);
    exit;
}

// Manejo especial AJAX para obtener detalles de un ingreso en Transcripción
if (isset($_GET['ajax_get_detail']) && $_GET['ajax_get_detail'] == '1') {
    header('Content-Type: application/json');
    $ingresoModel = new Ingreso();
    $detail = $ingresoModel->getById(intval($_GET['id'] ?? 0));
    echo json_encode($detail);
    exit;
}

switch ($page) {
    case 'login':
        require_once __DIR__ . '/views/auth/login.php';
        break;

    case 'logout':
        if (isset($_SESSION['user_id'])) {
            registrar_log_auditoria('AUTENTICACION', 'LOGOUT', $_SESSION['user_id'], "Cierre de sesión del usuario: " . ($_SESSION['nombre_completo'] ?? ''));
        }
        session_destroy();
        header('Location: index.php?page=login');
        exit;

    case 'dashboard':
        require_once __DIR__ . '/views/dashboard.php';
        break;

    case 'empresa':
    case 'empresa_config':
        require_once __DIR__ . '/views/empresa/config.php';
        break;

    case 'usuarios':
        require_once __DIR__ . '/views/usuarios/index.php';
        break;

    case 'auditoria':
        require_once __DIR__ . '/views/auditoria/index.php';
        break;

    case 'pacientes':
        require_once __DIR__ . '/views/pacientes/index.php';
        break;

    case 'importar_pacientes':
        require_once __DIR__ . '/views/pacientes/importar.php';
        break;

    case 'modulos':
        require_once __DIR__ . '/views/modulos/index.php';
        break;

    case 'ingreso':
        require_once __DIR__ . '/views/ingreso/index.php';
        break;

    case 'imprimir_ticket':
        require_once __DIR__ . '/views/ingreso/imprimir_ticket.php';
        break;

    case 'expedientes':
        require_once __DIR__ . '/views/expedientes/index.php';
        break;

    case 'transcripcion':
        require_once __DIR__ . '/views/transcripcion/index.php';
        break;

    case 'monitoreo':
        require_once __DIR__ . '/views/monitoreo/index.php';
        break;

    case 'alistamiento':
        require_once __DIR__ . '/views/alistamiento/index.php';
        break;

    case 'imprimir_ticket_alistamiento':
        require_once __DIR__ . '/views/alistamiento/imprimir_ticket_alistamiento.php';
        break;

    case 'imprimir_orden_unificada':
        require_once __DIR__ . '/views/alistamiento/imprimir_orden_unificada.php';
        break;

    case 'entrega':
        require_once __DIR__ . '/views/entrega/index.php';
        break;

    case 'imprimir_acta':
        require_once __DIR__ . '/views/entrega/imprimir_acta.php';
        break;

    case 'reportes':
        require_once __DIR__ . '/views/reportes/index.php';
        break;

    case 'turnero1':
        require_once __DIR__ . '/views/turnero/turnero1.php';
        break;

    case 'turnero2':
        require_once __DIR__ . '/views/turnero/turnero2.php';
        break;

    default:
        require_once __DIR__ . '/views/dashboard.php';
        break;
}
