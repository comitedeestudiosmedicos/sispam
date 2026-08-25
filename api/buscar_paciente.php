<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Paciente.php';

$tipo_doc = $_GET['tipo_documento'] ?? '';
$num_doc = $_GET['numero_documento'] ?? '';

if (empty($tipo_doc) || empty($num_doc)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros']);
    exit;
}

$model = new Paciente();
$paciente = $model->getByDocumento($tipo_doc, $num_doc);

if ($paciente) {
    echo json_encode(['status' => 'found', 'data' => $paciente]);
} else {
    echo json_encode(['status' => 'not_found']);
}
