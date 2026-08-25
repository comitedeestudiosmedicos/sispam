<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Paciente.php';

$tipo_doc = trim($_GET['tipo_doc'] ?? 'CC');
$num_doc  = trim($_GET['num_doc'] ?? '');

if (empty($num_doc)) {
    echo json_encode(['status' => 'error', 'message' => 'Número de documento requerido']);
    exit;
}

$pacienteModel = new Paciente();
$paciente = $pacienteModel->getByDocumento($tipo_doc, $num_doc);

if ($paciente) {
    echo json_encode(['status' => 'success', 'data' => $paciente]);
} else {
    echo json_encode(['status' => 'not_found', 'message' => 'Paciente no encontrado']);
}
