<?php

//FORZAR MUESTRA DE ERRORES REALES (Temporal para diagnóstico)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Front Controller & Router Principal SISPAM
 */

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/models/Ingreso.php';
require_once __DIR__ . '/config/database.php';

//PRUEBA DE CONEXION A LA BASE DE DATOS

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    die("<h1 style='color:red;'>Error en la configuración local: " . $e->getMessage() . "</h1>");
}

$page = $_GET['page'] ?? 'dashboard';

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
        require_once __DIR__ . '/views/empresa/config.php';
        break;

    case 'usuarios':
        require_once __DIR__ . '/views/usuarios/index.php';
        break;

    case 'auditoria':
        require_once __DIR__ . '/views/auditoria/index.php';
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
