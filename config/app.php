<?php
/**
 * Configuración Global de la Aplicación y Constantes del Sistema RIPS / SGSSS
 */

// Habilitar reporte de errores para diagnóstico en producción/VPS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona Horaria Colombia
date_default_timezone_set('America/Bogota');

// Rutas base
define('APP_NAME', 'SISPAM - Sistema de Gestión Farmacéutica');
define('BASE_DIR', dirname(__DIR__));

// Detectar URL base automáticamente para XAMPP y VPS Hostinger
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url = rtrim($protocol . '://' . $host . $script_name, '/') . '/';
define('BASE_URL', $base_url);

// Lista Oficial de Tipos de Documento en Colombia
define('TIPOS_DOCUMENTO', [
    'CC'  => 'Cédula de Ciudadanía',
    'CE'  => 'Cédula de Extranjería',
    'PA'  => 'Pasaporte',
    'TI'  => 'Tarjeta de Identidad',
    'RC'  => 'Registro Civil',
    'PEP' => 'Permiso Especial de Permanencia',
    'PPT' => 'Permiso por Protección Temporal',
    'NV'  => 'Nacido Vivo'
]);

// Lista de EPS Principales en Colombia (Aseguradoras)
define('EPS_COLOMBIA', [
    'Sura EPS',
    'Savia Salud EPS',
    'Nueva EPS',
    'Sanitas EPS',
    'Compensar EPS',
    'Salud Total EPS',
    'Capital Salud EPS',
    'Asmet Salud EPS',
    'Famisanar EPS',
    'Coosalud EPS',
    'Mutual Ser EPS',
    'Mallamas EPS',
    'Pijaos Salud EPS',
    'Capresoca EPS',
    'Aliansalud EPS',
    'EPM - Empresas Públicas de Medellín',
    'Fuerzas Militares / Policía Nacional',
    'Particular / Sin EPS'
]);

// Lista de IPS Remitentes Principales en Antioquia (Hospitales / Clínicas / Centros de Salud)
define('IPS_ANTIOQUIA', [
    'Hospital Pablo Tobón Uribe',
    'Hospital Universitario San Vicente Fundación',
    'Clínica Las Américas Auna',
    'Clínica León XIII (IPS Universitaria)',
    'Clínica Medellín (Sede Centro / El Poblado)',
    'Clínica Rosario (Sede El Tesoro / Centro)',
    'Hospital General de Medellín E.S.E.',
    'Clínica CardioVID',
    'Clínica CES (Sede Prado / Almacentro)',
    'Clínica Clofan',
    'Clínica Soma',
    'Hospital Manuel Uribe Ángel (Envigado)',
    'Hospital San Rafael (Itagüí)',
    'Hospital Marco Fidel Suárez (Bello)',
    'Hospital San Juan de Dios (Rionegro)',
    'Clínica Somer (Rionegro)',
    'Hospital San Juan de Dios (La Ceja)',
    'Hospital San Juan de Dios (Marinilla)',
    'Hospital San Juan de Dios (Santa Fe de Antioquia)',
    'Hospital San Vicente de Paúl (Caldas)',
    'Hospital Venancio Díaz Díaz (Sabaneta)',
    'Hospital Nuestra Señora de la Candelaria (Guarne)',
    'Hospital San Juan de Dios (Yarumal)',
    'Hospital Antonio Roldán Betancur (Apartadó)',
    'IPS Comfama (Distintas Sedes Antioquia)',
    'IPS Comfenalco Antioquia',
    'IPS Sura (Distintas Sedes Antioquia)',
    'IPS Promedan',
    'IPS Viva 1A',
    'Metrosalud E.S.E. (Red Municipal Medellín)',
    'Comité de Estudios Médicos S.A.S.',
    'Otra IPS / Centro de Salud de Antioquia'
]);

// Tipos de Documentos Adjuntos
define('TIPOS_DOC_ADJUNTO', [
    'CEDULA'           => 'Cédula / Doc. Identidad',
    'AUTORIZACION'     => 'Autorización de Servicios',
    'ORDEN_MEDICA'     => 'Fórmula / Orden Médica',
    'HISTORIA_CLINICA' => 'Historia Clínica / Anexo',
    'OTRO'             => 'Otro Documento'
]);

// Constantes Paramétricas RIPS & SGSSS
define('SEDES_ATENCION', [
    'Sede Prado',
    'Sede Ayacucho',
    'Sede Centro',
    'Sede Poblado',
    'Sede Laureles',
    'Sede Belén',
    'Sede Robledo'
]);

define('ESTADOS_CIVILES', [
    'Soltero(a)',
    'Casado(a)',
    'Unión Libre',
    'Divorciado(a)',
    'Viudo(a)'
]);

define('GRUPOS_POBLACIONALES', [
    'Otro Grupo Poblacional',
    'Indigente / Habitante de Calle',
    'Población ROM (Gitana)',
    'Población Raizal',
    'Población Palenquera',
    'Población Afrocolombiana',
    'Víctima del Conflicto / Desplazado',
    'Adulto Mayor',
    'Persona en Reincorporación'
]);

define('GRUPOS_ETNICOS', [
    'No Aplica',
    'Afrocolombiano',
    'Gitano (ROM)',
    'Indígena',
    'Palenquero',
    'Raizal'
]);

define('TIPOS_DISCAPACIDAD', [
    'No Aplica',
    'Auditiva',
    'Física',
    'Visual',
    'Intelectual',
    'Mental / Psicosocial',
    'Múltiple'
]);

define('TIPOS_ESCOLARIDAD', [
    'NA',
    'Preescolar',
    'Básica Primaria',
    'Básica Secundaria',
    'Media',
    'Técnica / Tecnológica',
    'Universitaria',
    'Postgrado'
]);

define('BARRIOS_MEDELLIN', [
    'El Poblado',
    'Laureles',
    'Belén',
    'Aranjuez',
    'Robledo',
    'Manrique',
    'Buenos Aires',
    'San Javier',
    'Castilla',
    'Doce de Octubre',
    'Villa Hermosa',
    'Candelaria (Centro)',
    'Guayabal',
    'La América',
    'Santa Cruz',
    'Popular',
    'San Antonio de Prado',
    'San Cristóbal',
    'Santa Elena',
    'Altavista',
    'Palmitas',
    'Otro Barrio'
]);

define('MUNICIPIOS_ANTIOQUIA', [
    'MEDELLIN-ANT-05001',
    'BELLO-ANT-05088',
    'ITAGUI-ANT-05360',
    'ENVIGADO-ANT-05266',
    'SABANETA-ANT-05631',
    'CALDAS-ANT-05129',
    'LA ESTRELLA-ANT-05380',
    'RIONEGRO-ANT-05615',
    'APARTADO-ANT-05045',
    'TURBO-ANT-05837',
    'CAUCASIA-ANT-05154',
    'CHIGORODO-ANT-05172',
    'GIRARDOTA-ANT-05308',
    'COPACABANA-ANT-05212',
    'MARINILLA-ANT-05440',
    'GUARNE-ANT-05318',
    'SANTA FE DE ANTIOQUIA-ANT-05042',
    'YARUMAL-ANT-05887',
    'EL CARMEN DE VIBORAL-ANT-05148',
    'LA CEJA-ANT-05376',
    'PUERTO BERRIO-ANT-05579'
]);

define('OCUPACIONES', [
    'Abogado',
    'Agente de Viajes',
    'Agricultor',
    'Ama de Casa',
    'Arquitecto',
    'Comerciante',
    'Contador',
    'Docente / Profesor',
    'Estudiante',
    'Enfermero(a)',
    'Ingeniero(a)',
    'Médico(a)',
    'Pensionado(a)',
    'Independiente',
    'Empleado',
    'Desempleado',
    'Técnico(a)',
    'Operario(a)',
    'Conductor(a)',
    'Vigilante / Seguridad',
    'Servidor Público',
    'Otro'
]);

define('TIPOS_AFILIADO', [
    'Contributivo Cotizante',
    'Contributivo Beneficiario',
    'Contributivo Adicional',
    'Subsidiado',
    'Vinculado',
    'Particular',
    'Especial / Excepción'
]);

define('NIVELES_SOCIOECONOMICOS', [
    'CATEGORIA A',
    'CATEGORIA B',
    'CATEGORIA C',
    'SIN CATEGORIA'
]);

// Opciones de Prioridad de Atención del Ingreso
define('OPCIONES_PRIORIDAD', [
    'NORMAL'           => 'Normal (Atención Estándar)',
    'TERCERA_EDAD'     => '👴 Tercera Edad / Adulto Mayor',
    'EMBARAZADA'       => '🤰 Mujer Embarazada / Gestante',
    'DISCAPACIDAD'     => '♿ Persona con Discapacidad',
    'NIÑO_LACTANTE'    => '👶 Niño / Lactante',
    'OTRO_PREFERENCIAL'=> '⭐ Otro Caso Preferencial'
]);

// Configuración de Auditoría SGSSS
if (!defined('AUDITORIA_DERECHOS_BLOQUEANTE')) {
    define('AUDITORIA_DERECHOS_BLOQUEANTE', false);
}
require_once __DIR__ . '/../models/AuditLog.php';

// Helper para verificar sesión activa
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'index.php?page=login');
        exit;
    }
}

// Helper para registrar traza en el Log de Auditoría
function registrar_log_auditoria($modulo, $accion, $registro_id = null, $detalles = null) {
    try {
        $auditModel = new AuditLog();
        return $auditModel->log($modulo, $accion, $registro_id, $detalles);
    } catch (Throwable $e) {
        error_log("Error al registrar audit log: " . $e->getMessage());
        return false;
    }
}

// Helper para verificar permiso dinámico de un módulo
function has_permission($module_key) {
    if (!isset($_SESSION['user_id'])) return false;
    $role = $_SESSION['rol_nombre'] ?? '';
    if ($role === 'Administrador' || $role === 'Regente') return true;
    if ($module_key === 'auditoria') {
        return true; // Permitir visualización del log de auditoría a todos los usuarios autenticados
    }
    $permisos = $_SESSION['permisos'] ?? [];
    return in_array($module_key, $permisos);
}

// Helper para verificar permisos por rol o clave de módulo
function check_role($module_key_or_roles = []) {
    check_auth();
    $user_role = $_SESSION['rol_nombre'] ?? '';
    if ($user_role === 'Administrador' || $user_role === 'Regente') return;

    if (!is_array($module_key_or_roles)) {
        $module_key_or_roles = [$module_key_or_roles];
    }

    $user_role = $_SESSION['rol_nombre'] ?? '';
    $user_permisos = $_SESSION['permisos'] ?? [];

    // 1. Verificación directa de nombre de rol
    if (in_array($user_role, $module_key_or_roles)) {
        return;
    }

    // 2. Verificación por permisos de módulo o alias de rol
    foreach ($module_key_or_roles as $item) {
        $item_lower = strtolower($item);
        
        // Si el usuario tiene el permiso asignado en la matriz de permisos
        if (in_array($item_lower, $user_permisos)) {
            return;
        }

        if (($item_lower === 'monitoreo' || $item_lower === 'monitor') && 
            (in_array('monitoreo', $user_permisos) || strcasecmp($user_role, 'Monitor') === 0 || strcasecmp($user_role, 'Farmacéutico') === 0)) {
            return;
        }

        if (($item_lower === 'supervision_alistamiento' || $item_lower === 'supervisor_alistamiento' || $item_lower === 'supervisor') && 
            (in_array('supervision_alistamiento', $user_permisos) || strcasecmp($user_role, 'Supervisor de Alistamiento') === 0)) {
            return;
        }

        if (($item_lower === 'transcriptor' || $item_lower === 'transcripcion') && 
            (in_array('transcripcion', $user_permisos) || strcasecmp($user_role, 'Transcripcion') === 0 || strcasecmp($user_role, 'Transcriptor') === 0)) {
            return;
        }

        if (($item_lower === 'orientador' || $item_lower === 'ingreso') && 
            (in_array('ingreso', $user_permisos) || strcasecmp($user_role, 'Orientador') === 0)) {
            return;
        }

        if (($item_lower === 'alistamiento' || $item_lower === 'alistador') && 
            (in_array('alistamiento', $user_permisos) || in_array('supervision_alistamiento', $user_permisos) || strcasecmp($user_role, 'Alistamiento') === 0 || strcasecmp($user_role, 'Alistador') === 0 || strcasecmp($user_role, 'Supervisor de Alistamiento') === 0)) {
            return;
        }

        if (($item_lower === 'entrega' || $item_lower === 'entregador') && 
            (in_array('entrega', $user_permisos) || strcasecmp($user_role, 'Entrega') === 0 || strcasecmp($user_role, 'Entregador') === 0)) {
            return;
        }

        if (($item_lower === 'regente' || $item_lower === 'reportes') && 
            (in_array('reportes', $user_permisos) || strcasecmp($user_role, 'Regente') === 0)) {
            return;
        }
    }

    header('Location: ' . BASE_URL . 'index.php?page=dashboard&error=acceso_denegado');
    exit;
}

// Helper para obtener badge de estado
function get_estado_badge($estado) {
    switch ($estado) {
        case 'INGRESADO':
            return '<span class="badge bg-secondary"><i class="fa-solid fa-user-clock me-1"></i> Ingresado</span>';
        case 'EN_TRANSCRIPCION':
            return '<span class="badge bg-primary"><i class="fa-solid fa-keyboard me-1"></i> En Transcripción</span>';
        case 'TRANSCRITO':
        case 'TRANSCRITO_COMPLETO':
            return '<span class="badge bg-purple text-white" style="background-color: #6f42c1;"><i class="fa-solid fa-file-pen me-1"></i> Transcrito (Por Verificar)</span>';
        case 'VERIFICADO':
        case 'VERIFICADA':
            return '<span class="badge bg-success"><i class="fa-solid fa-user-check me-1"></i> Verificado (Alistamiento)</span>';
        case 'CON_ERRORES':
        case 'CON_ERRORES_TRANSCRIPCION':
            return '<span class="badge bg-danger"><i class="fa-solid fa-circle-exclamation me-1"></i> Error Transcripción</span>';
        case 'TRANSCRITO_PENDIENTE':
            return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i> Con Pendientes</span>';
        case 'SIN_STOCK':
            return '<span class="badge bg-danger"><i class="fa-solid fa-boxes-packing me-1"></i> Sin Stock</span>';
        case 'ALISTADO':
        case 'GESTIONADO':
            return '<span class="badge bg-info text-dark"><i class="fa-solid fa-box-open me-1"></i> Alistado / Listo para Entrega</span>';
        case 'ENTREGADO':
            return '<span class="badge bg-dark"><i class="fa-solid fa-square-check me-1"></i> Entregado</span>';
        case 'CANCELADO':
            return '<span class="badge bg-outline-secondary">Cancelado</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars($estado) . '</span>';
    }
}

// Helper para obtener badge de Prioridad
function get_prioridad_badge($prioridad) {
    switch ($prioridad) {
        case 'TERCERA_EDAD':
            return '<span class="badge bg-warning text-dark fw-bold"><i class="fa-solid fa-person-cane me-1"></i> 👴 Tercera Edad</span>';
        case 'EMBARAZADA':
            return '<span class="badge bg-danger text-white fw-bold"><i class="fa-solid fa-person-pregnant me-1"></i> 🤰 Embarazada</span>';
        case 'DISCAPACIDAD':
            return '<span class="badge bg-info text-dark fw-bold"><i class="fa-solid fa-wheelchair me-1"></i> ♿ Discapacidad</span>';
        case 'NIÑO_LACTANTE':
            return '<span class="badge bg-primary text-white fw-bold"><i class="fa-solid fa-baby me-1"></i> 👶 Niño / Lactante</span>';
        case 'OTRO_PREFERENCIAL':
            return '<span class="badge bg-warning text-dark fw-bold"><i class="fa-solid fa-star me-1"></i> ⭐ Preferencial</span>';
        default:
            return '';
    }
}
