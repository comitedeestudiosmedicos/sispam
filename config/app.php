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
    if ($role === 'Administrador') return true;
    
    $permisos = $_SESSION['permisos'] ?? [];
    if (!is_array($permisos)) return false;

    return in_array($module_key, $permisos);
}

// Helper para verificar permisos por rol o clave de módulo
function check_role($module_key_or_roles = []) {
    check_auth();
    $user_role = $_SESSION['rol_nombre'] ?? '';
    if ($user_role === 'Administrador') return;

    if (!is_array($module_key_or_roles)) {
        $module_key_or_roles = [$module_key_or_roles];
    }

    $user_permisos = $_SESSION['permisos'] ?? [];
    if (!is_array($user_permisos)) {
        $user_permisos = [];
    }

    // 1. Verificación directa de nombre de rol
    if (in_array($user_role, $module_key_or_roles)) {
        return;
    }

    // 2. Verificación por permisos de módulo asignados en la matriz
    foreach ($module_key_or_roles as $item) {
        $item_lower = strtolower($item);
        
        // Si el usuario tiene el permiso asignado en la matriz de permisos
        if (in_array($item_lower, $user_permisos)) {
            return;
        }

        // Aliases específicos para compatibilidad de permisos
        if ($item_lower === 'supervision_alistamiento' && (in_array('supervision_alistamiento', $user_permisos) || in_array('alistamiento', $user_permisos))) {
            return;
        }
        if ($item_lower === 'alistamiento' && (in_array('alistamiento', $user_permisos) || in_array('supervision_alistamiento', $user_permisos))) {
            return;
        }
    }

    // 3. Acceso denegado: Registrar traza y redirigir
    $usr = $_SESSION['usuario'] ?? 'Desconocido';
    registrar_log_auditoria('SEGURIDAD', 'ACCESO_DENEGADO', null, "Acceso denegado a usuario: {$usr} (Rol: {$user_role}) al módulo: " . implode(', ', $module_key_or_roles));
    
    $_SESSION['error_acceso'] = 'No cuenta con los permisos necesarios para acceder a este módulo. Comuníquese con el Administrador del sistema.';
    header('Location: index.php?page=dashboard');
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

/**
 * Cálculo Oficial de Festivos en Colombia (Ley 51 de 1983 / Ley Emiliani y Calendario Católico)
 */
function get_festivos_colombia($year) {
    $pascua = easter_date($year);

    $mover_lunes = function($mes, $dia) use ($year) {
        $t = mktime(0, 0, 0, $mes, $dia, $year);
        $dw = (int)date('N', $t); // 1 = Lunes, 7 = Domingo
        if ($dw === 1) return date('Y-m-d', $t);
        $diff = 8 - $dw;
        return date('Y-m-d', strtotime("+{$diff} days", $t));
    };

    $festivos = [];

    // 1. Días Fijos Inamovibles
    $festivos[] = sprintf('%04d-01-01', $year); // Año Nuevo
    $festivos[] = sprintf('%04d-05-01', $year); // Día del Trabajo
    $festivos[] = sprintf('%04d-07-20', $year); // Independencia de Colombia
    $festivos[] = sprintf('%04d-08-07', $year); // Batalla de Boyacá
    $festivos[] = sprintf('%04d-12-08', $year); // Inmaculada Concepción
    $festivos[] = sprintf('%04d-12-25', $year); // Navidad

    // 2. Ley Emiliani (Se trasladan al siguiente Lunes)
    $festivos[] = $mover_lunes(1, 6);   // Reyes Magos
    $festivos[] = $mover_lunes(3, 19);  // San José
    $festivos[] = $mover_lunes(6, 29);  // San Pedro y San Pablo
    $festivos[] = $mover_lunes(8, 15);  // Asunción de la Virgen
    $festivos[] = $mover_lunes(10, 12); // Día de la Raza
    $festivos[] = $mover_lunes(11, 1);  // Todos los Santos
    $festivos[] = $mover_lunes(11, 11); // Independencia de Cartagena

    // 3. Basados en Pascua / Semana Santa
    $festivos[] = date('Y-m-d', strtotime('-3 days', $pascua));  // Jueves Santo
    $festivos[] = date('Y-m-d', strtotime('-2 days', $pascua));  // Viernes Santo
    $festivos[] = date('Y-m-d', strtotime('+43 days', $pascua)); // Ascensión de Jesús
    $festivos[] = date('Y-m-d', strtotime('+64 days', $pascua)); // Corpus Christi
    $festivos[] = date('Y-m-d', strtotime('+71 days', $pascua)); // Sagrado Corazón de Jesús

    sort($festivos);
    return array_unique($festivos);
}

function es_festivo_colombia($fecha_str) {
    $year = (int)date('Y', strtotime($fecha_str));
    $festivos = get_festivos_colombia($year);
    $soloFecha = date('Y-m-d', strtotime($fecha_str));
    return in_array($soloFecha, $festivos);
}

/**
 * Obtener la hora oficial de apertura de atención y normalización SLA según el día:
 * - Lunes a Viernes no festivos: 07:00:00
 * - Sábados, Domingos y Festivos: 08:00:00
 */
function get_horario_apertura_dia($fecha_str, $hora_semana_custom = null, $hora_festivo_custom = null) {
    $dw = (int)date('N', strtotime($fecha_str)); // 1=Lunes ... 6=Sábado, 7=Domingo
    $esFestivo = es_festivo_colombia($fecha_str);

    $hora_semana  = !empty($hora_semana_custom) ? $hora_semana_custom : '07:00:00';
    $hora_festivo = !empty($hora_festivo_custom) ? $hora_festivo_custom : '08:00:00';

    if ($dw === 6 || $dw === 7 || $esFestivo) {
        $tipo = $esFestivo ? 'FESTIVO' : ($dw === 6 ? 'SABADO' : 'DOMINGO');
        $label = $esFestivo ? 'Día Festivo' : ($dw === 6 ? 'Sábado' : 'Domingo');
        return [
            'hora' => $hora_festivo,
            'tipo_dia' => $tipo,
            'es_festivo_o_finde' => true,
            'label' => "{$label} (Apertura " . date('h:i A', strtotime($hora_festivo)) . ")"
        ];
    }

    return [
        'hora' => $hora_semana,
        'tipo_dia' => 'HABIL',
        'es_festivo_o_finde' => false,
        'label' => "Lunes a Viernes (Apertura " . date('h:i A', strtotime($hora_semana)) . ")"
    ];
}
