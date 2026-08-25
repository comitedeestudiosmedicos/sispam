<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Ingreso.php';
require_once __DIR__ . '/../models/Empresa.php';

$type = $_GET['type'] ?? '1'; // 1 = En proceso, 2 = Listo para entrega

$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$config = $empresaModel->getConfig();

if ($type === '1') {
    $lista = $ingresoModel->getTurnero1();
    // Anonimizar nombres para Habeas Data en TV pública (ej. "JUAN P*** G***")
    foreach ($lista as &$item) {
        $n = explode(' ', trim($item['nombres']))[0] ?? '';
        $a = explode(' ', trim($item['apellidos']))[0] ?? '';
        $item['nombre_habeas'] = mb_strtoupper($n) . ' ' . mb_substr($a, 0, 1) . '***';
    }
    echo json_encode([
        'config' => $config,
        'turnos' => $lista
    ]);
} else {
    $lista = $ingresoModel->getTurnero2();
    foreach ($lista as &$item) {
        $item['nombre_completo'] = mb_strtoupper($item['nombres'] . ' ' . $item['apellidos']);
    }
    echo json_encode([
        'config' => $config,
        'turnos' => $lista
    ]);
}
