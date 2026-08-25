<?php
require_once __DIR__ . '/../config/database.php';

class ModuloEntrega {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->initTable();
    }

    private function initTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS modulos_entrega (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL UNIQUE,
                descripcion VARCHAR(255) NULL,
                estado ENUM('ACTIVO', 'INACTIVO') DEFAULT 'ACTIVO',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $this->db->exec($sql);

        // Insertar módulos por defecto si la tabla está vacía
        $stmtCount = $this->db->query("SELECT COUNT(*) FROM modulos_entrega");
        if ($stmtCount->fetchColumn() == 0) {
            $defaultModules = [
                ['nombre' => 'MÓDULO 1', 'descripcion' => 'Ventanilla General 1'],
                ['nombre' => 'MÓDULO 2', 'descripcion' => 'Ventanilla General 2'],
                ['nombre' => 'MÓDULO 3', 'descripcion' => 'Ventanilla General 3'],
                ['nombre' => 'MÓDULO 4', 'descripcion' => 'Ventanilla General 4'],
                ['nombre' => 'VENTANILLA PREFERENCIAL', 'descripcion' => 'Atención Prioritaria (Adulto Mayor, Discapacidad, Embarazadas)']
            ];
            $stmtIns = $this->db->prepare("INSERT INTO modulos_entrega (nombre, descripcion, estado) VALUES (:nombre, :descripcion, 'ACTIVO')");
            foreach ($defaultModules as $m) {
                $stmtIns->execute([':nombre' => $m['nombre'], ':descripcion' => $m['descripcion']]);
            }
        }
    }

    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM modulos_entrega ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getActivos() {
        $stmt = $this->db->query("SELECT * FROM modulos_entrega WHERE estado = 'ACTIVO' ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM modulos_entrega WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function create($nombre, $descripcion = '') {
        $stmt = $this->db->prepare("INSERT INTO modulos_entrega (nombre, descripcion, estado) VALUES (:nombre, :descripcion, 'ACTIVO')");
        return $stmt->execute([':nombre' => mb_strtoupper(trim($nombre), 'UTF-8'), ':descripcion' => trim($descripcion)]);
    }

    public function update($id, $nombre, $descripcion, $estado) {
        $stmt = $this->db->prepare("UPDATE modulos_entrega SET nombre = :nombre, descripcion = :descripcion, estado = :estado WHERE id = :id");
        return $stmt->execute([
            ':nombre' => mb_strtoupper(trim($nombre), 'UTF-8'),
            ':descripcion' => trim($descripcion),
            ':estado' => $estado,
            ':id' => $id
        ]);
    }

    public function updateEstado($id, $estado) {
        $stmt = $this->db->prepare("UPDATE modulos_entrega SET estado = :estado WHERE id = :id");
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }
}
