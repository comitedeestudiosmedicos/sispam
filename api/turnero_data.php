<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Ingreso.php';
require_once __DIR__ . '/../models/Empresa.php';

$type = $_GET['type'] ?? '1'; // 1 = En proceso, 2 = Listo para entrega
$sede_id = isset($_GET['sede_id']) && $_GET['sede_id'] !== '' ? intval($_GET['sede_id']) : (isset($_SESSION['active_sede_id']) ? intval($_SESSION['active_sede_id']) : (isset($_SESSION['sede_id']) ? intval($_SESSION['sede_id']) : null));

$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$config = $empresaModel->getConfig();
$sedeInfo = $sede_id ? $empresaModel->getSedeById($sede_id) : null;
$nombre_sede_activa = $sedeInfo ? $sedeInfo['nombre_sede'] : ($_SESSION['active_sede_nombre'] ?? 'Todas las Sedes');

/**
 * Anonimizar nombres para cumplimiento de Habeas Data en pantallas públicas.
 * Ejemplo: William Gomez -> William Go******
 */
function formatearNombreHabeasData($nombres, $apellidos) {
    // Primer nombre en formato Título
    $nombresArr = preg_split('/\s+/', trim($nombres));
    $primerNombre = mb_convert_case($nombresArr[0] ?? '', MB_CASE_TITLE, "UTF-8");

    // Procesar apellidos: conservar las 2 primeras letras y sustituir el resto por asteriscos
    $apellidosArr = preg_split('/\s+/', trim($apellidos));
    $apellidosAnonimizados = [];

    foreach ($apellidosArr as $ape) {
        $ape = trim($ape);
        if (empty($ape)) continue;
        
        $len = mb_strlen($ape, 'UTF-8');
        if ($len <= 2) {
            $prefijo = mb_convert_case($ape, MB_CASE_TITLE, "UTF-8");
            $apellidosAnonimizados[] = $prefijo . '****';
        } else {
            $prefijo = mb_convert_case(mb_substr($ape, 0, 2, 'UTF-8'), MB_CASE_TITLE, "UTF-8");
            $apellidosAnonimizados[] = $prefijo . '******';
        }
    }

    $apellidosStr = implode(' ', $apellidosAnonimizados);
    return trim($primerNombre . ' ' . $apellidosStr);
}

if ($type === '1') {
    $lista = $ingresoModel->getTurnero1($sede_id);
    foreach ($lista as &$item) {
        $item['nombre_habeas'] = formatearNombreHabeasData($item['nombres'] ?? '', $item['apellidos'] ?? '');
        $item['nombre_completo'] = trim(($item['nombres'] ?? '') . ' ' . ($item['apellidos'] ?? ''));
    }
    echo json_encode([
        'config' => $config,
        'sede_id' => $sede_id,
        'sede_nombre' => $nombre_sede_activa,
        'turnos' => $lista
    ]);
} else {
    $lista = $ingresoModel->getTurnero2($sede_id);
    foreach ($lista as &$item) {
        $item['nombre_habeas'] = formatearNombreHabeasData($item['nombres'] ?? '', $item['apellidos'] ?? '');
        $item['nombre_completo'] = trim(($item['nombres'] ?? '') . ' ' . ($item['apellidos'] ?? ''));
    }
    echo json_encode([
        'config' => $config,
        'sede_id' => $sede_id,
        'sede_nombre' => $nombre_sede_activa,
        'turnos' => $lista
    ]);
}
