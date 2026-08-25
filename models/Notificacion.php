<?php
require_once __DIR__ . '/../config/database.php';

class Notificacion {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function create($ingreso_id, $usuario_destino_id, $mensaje) {
        $stmt = $this->db->prepare("
            INSERT INTO notificaciones (ingreso_id, usuario_destino_id, mensaje) 
            VALUES (:ingreso_id, :usuario_destino_id, :mensaje)
        ");
        return $stmt->execute([
            ':ingreso_id' => $ingreso_id,
            ':usuario_destino_id' => $usuario_destino_id,
            ':mensaje' => $mensaje
        ]);
    }

    public function getSinLeerePorUsuario($usuario_id) {
        $stmt = $this->db->prepare("
            SELECT n.*, i.ticket_numero, p.nombres, p.apellidos 
            FROM notificaciones n
            JOIN ingresos i ON n.ingreso_id = i.id
            JOIN pacientes p ON i.paciente_id = p.id
            WHERE n.usuario_destino_id = :usuario_id AND n.leido = 0
            ORDER BY n.id DESC
        ");
        $stmt->execute([':usuario_id' => $usuario_id]);
        return $stmt->fetchAll();
    }

    public function marcarComoLeido($id) {
        $stmt = $this->db->prepare("UPDATE notificaciones SET leido = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
