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

    public function getAll($sede_id = null) {
        $sql = "
            SELECT m.*, 
                   COALESCE(s.nombre_sede, 'Sede Principal') AS nombre_sede,
                   COALESCE(e.razon_social, 'Empresa Principal') AS empresa_nombre
            FROM modulos_entrega m
            LEFT JOIN sedes s ON m.sede_id = s.id
            LEFT JOIN empresas e ON s.empresa_id = e.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($sede_id)) {
            $sql .= " AND m.sede_id = :sede_id";
            $params[':sede_id'] = $sede_id;
        }
        $sql .= " ORDER BY s.nombre_sede ASC, m.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getActivos($sede_id = null) {
        $sql = "
            SELECT m.*, 
                   COALESCE(s.nombre_sede, 'Sede Principal') AS nombre_sede 
            FROM modulos_entrega m
            LEFT JOIN sedes s ON m.sede_id = s.id
            WHERE m.estado = 'ACTIVO'
        ";
        $params = [];
        if (!empty($sede_id)) {
            $sql .= " AND m.sede_id = :sede_id";
            $params[':sede_id'] = $sede_id;
        }
        $sql .= " ORDER BY m.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetchAll();

        // Si una sede específica no tiene módulos aún, crearlos por defecto
        if (empty($res) && !empty($sede_id)) {
            $this->crearModulosPorDefectoParaSede($sede_id);
            $stmt->execute($params);
            $res = $stmt->fetchAll();
        }

        return $res;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT m.*, s.nombre_sede, s.empresa_id 
            FROM modulos_entrega m 
            LEFT JOIN sedes s ON m.sede_id = s.id 
            WHERE m.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function create($nombre, $descripcion = '', $sede_id = 1) {
        $stmt = $this->db->prepare("
            INSERT INTO modulos_entrega (sede_id, nombre, descripcion, estado) 
            VALUES (:sede_id, :nombre, :descripcion, 'ACTIVO')
        ");
        return $stmt->execute([
            ':sede_id'     => intval($sede_id) ?: 1,
            ':nombre'      => mb_strtoupper(trim($nombre), 'UTF-8'),
            ':descripcion' => trim($descripcion)
        ]);
    }

    public function update($id, $nombre, $descripcion, $estado, $sede_id = 1) {
        $stmt = $this->db->prepare("
            UPDATE modulos_entrega SET 
                sede_id = :sede_id, 
                nombre = :nombre, 
                descripcion = :descripcion, 
                estado = :estado 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':sede_id'     => intval($sede_id) ?: 1,
            ':nombre'      => mb_strtoupper(trim($nombre), 'UTF-8'),
            ':descripcion' => trim($descripcion),
            ':estado'      => $estado,
            ':id'          => $id
        ]);
    }

    public function updateEstado($id, $estado) {
        $stmt = $this->db->prepare("UPDATE modulos_entrega SET estado = :estado WHERE id = :id");
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    public function crearModulosPorDefectoParaSede($sede_id) {
        $defaultModules = [
            ['nombre' => 'MÓDULO 1', 'descripcion' => 'Ventanilla General 1'],
            ['nombre' => 'MÓDULO 2', 'descripcion' => 'Ventanilla General 2'],
            ['nombre' => 'MÓDULO 3', 'descripcion' => 'Ventanilla General 3'],
            ['nombre' => 'VENTANILLA PREFERENCIAL', 'descripcion' => 'Atención Prioritaria (Adulto Mayor, Embarazadas, Discapacidad)']
        ];

        $stmtIns = $this->db->prepare("INSERT IGNORE INTO modulos_entrega (sede_id, nombre, descripcion, estado) VALUES (:sede_id, :nombre, :descripcion, 'ACTIVO')");
        foreach ($defaultModules as $dm) {
            $stmtIns->execute([
                ':sede_id'     => $sede_id,
                ':nombre'      => $dm['nombre'],
                ':descripcion' => $dm['descripcion']
            ]);
        }
        return true;
    }
}
