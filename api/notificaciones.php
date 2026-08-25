<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Notificacion.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$notifModel = new Notificacion();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_leido') {
    $id = intval($_POST['id'] ?? 0);
    $notifModel->marcarComoLeido($id);
    echo json_encode(['status' => 'ok']);
    exit;
}

$notificaciones = $notifModel->getSinLeerePorUsuario($_SESSION['user_id']);
echo json_encode($notificaciones);
