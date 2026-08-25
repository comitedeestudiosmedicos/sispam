<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Ingreso.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? 'lock';

$ingresoModel = new Ingreso();

if ($action === 'lock') {
    $locked = $ingresoModel->lockRecord($id, $_SESSION['user_id']);
    if ($locked) {
        echo json_encode(['status' => 'ok', 'message' => 'Registro bloqueado para este usuario']);
    } else {
        $data = $ingresoModel->getById($id);
        echo json_encode([
            'status' => 'locked', 
            'message' => 'El expediente está siendo gestionado en este momento por: ' . ($data['lock_user_nombre'] ?? 'Otro usuario')
        ]);
    }
} else if ($action === 'unlock') {
    $ingresoModel->unlockRecord($id, $_SESSION['user_id']);
    echo json_encode(['status' => 'ok', 'message' => 'Registro liberado']);
}
