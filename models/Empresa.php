<?php
require_once __DIR__ . '/../config/database.php';

class Empresa {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getConfig($empresa_id = 1) {
        $stmt = $this->db->prepare("SELECT * FROM empresa_config WHERE id = :id");
        $stmt->execute([':id' => $empresa_id]);
        $config = $stmt->fetch();
        if (!$config) {
            $stmtFallback = $this->db->query("SELECT * FROM empresa_config ORDER BY id ASC LIMIT 1");
            $config = $stmtFallback->fetch();
        }
        return $config;
    }

    public function updateConfig($data, $empresa_id = 1) {
        $sql = "UPDATE empresa_config SET 
                    razon_social = :razon_social,
                    nit = :nit,
                    direccion = :direccion,
                    telefono = :telefono,
                    email = :email,
                    pie_tiquete = :pie_tiquete,
                    video_turnero_url = :video_turnero_url,
                    marquesina_turnero = :marquesina_turnero,
                    hora_apertura_atencion = :hora_apertura";
        
        $params = [
            ':razon_social' => $data['razon_social'],
            ':nit'          => $data['nit'],
            ':direccion'    => $data['direccion'],
            ':telefono'     => $data['telefono'],
            ':email'        => $data['email'],
            ':pie_tiquete'  => $data['pie_tiquete'],
            ':video_turnero_url' => $data['video_turnero_url'],
            ':marquesina_turnero' => $data['marquesina_turnero'],
            ':hora_apertura' => $data['hora_apertura_atencion'] ?? '07:20:00'
        ];

        if (!empty($data['logo_url'])) {
            $sql .= ", logo_url = :logo_url";
            $params[':logo_url'] = $data['logo_url'];
        }

        $sql .= " WHERE id = :id";
        $params[':id'] = $empresa_id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    // --- MÉTODOS MULTI-EMPRESA (CRUD) ---
    public function getTodasEmpresas() {
        $stmt = $this->db->query("SELECT * FROM empresas ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function getEmpresaById($id) {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function crearEmpresa($razon_social, $nit, $direccion, $telefono, $email) {
        $stmt = $this->db->prepare("
            INSERT INTO empresas (razon_social, nit, direccion, telefono, email, estado) 
            VALUES (:razon_social, :nit, :direccion, :telefono, :email, 'Activo')
        ");
        return $stmt->execute([
            ':razon_social' => $razon_social,
            ':nit'          => $nit,
            ':direccion'    => $direccion,
            ':telefono'     => $telefono,
            ':email'        => $email
        ]);
    }

    public function actualizarEmpresa($id, $razon_social, $nit, $direccion, $telefono, $email, $estado = 'Activo') {
        $stmt = $this->db->prepare("
            UPDATE empresas SET 
                razon_social = :razon_social, 
                nit = :nit, 
                direccion = :direccion, 
                telefono = :telefono, 
                email = :email, 
                estado = :estado 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':razon_social' => $razon_social,
            ':nit'          => $nit,
            ':direccion'    => $direccion,
            ':telefono'     => $telefono,
            ':email'        => $email,
            ':estado'       => $estado,
            ':id'           => $id
        ]);
    }

    // --- MÉTODOS MULTI-SEDE (CRUD) ---
    public function getTodasSedes() {
        $stmt = $this->db->query("
            SELECT s.*, e.razon_social AS empresa_nombre 
            FROM sedes s 
            JOIN empresas e ON s.empresa_id = e.id 
            ORDER BY s.empresa_id ASC, s.nombre_sede ASC
        ");
        return $stmt->fetchAll();
    }

    public function getSedesByEmpresa($empresa_id) {
        $stmt = $this->db->prepare("SELECT * FROM sedes WHERE empresa_id = :empresa_id AND estado = 'Activo' ORDER BY nombre_sede ASC");
        $stmt->execute([':empresa_id' => $empresa_id]);
        return $stmt->fetchAll();
    }

    public function getSedeById($id) {
        $stmt = $this->db->prepare("SELECT s.*, e.razon_social AS empresa_nombre FROM sedes s JOIN empresas e ON s.empresa_id = e.id WHERE s.id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function crearSede($empresa_id, $nombre_sede, $codigo_sede, $ciudad, $direccion, $telefono, $hora_apertura = '07:20:00') {
        $stmt = $this->db->prepare("
            INSERT INTO sedes (empresa_id, nombre_sede, codigo_sede, ciudad, direccion, telefono, hora_apertura_atencion, estado) 
            VALUES (:empresa_id, :nombre_sede, :codigo_sede, :ciudad, :direccion, :telefono, :hora_apertura, 'Activo')
        ");
        return $stmt->execute([
            ':empresa_id'     => $empresa_id,
            ':nombre_sede'    => $nombre_sede,
            ':codigo_sede'    => $codigo_sede,
            ':ciudad'         => $ciudad,
            ':direccion'      => $direccion,
            ':telefono'       => $telefono,
            ':hora_apertura'  => $hora_apertura ?: '07:20:00'
        ]);
    }

    public function actualizarSede($id, $empresa_id, $nombre_sede, $codigo_sede, $ciudad, $direccion, $telefono, $estado = 'Activo', $hora_apertura = '07:20:00') {
        $stmt = $this->db->prepare("
            UPDATE sedes SET 
                empresa_id = :empresa_id, 
                nombre_sede = :nombre_sede, 
                codigo_sede = :codigo_sede, 
                ciudad = :ciudad, 
                direccion = :direccion, 
                telefono = :telefono, 
                hora_apertura_atencion = :hora_apertura,
                estado = :estado 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':empresa_id'     => $empresa_id,
            ':nombre_sede'    => $nombre_sede,
            ':codigo_sede'    => $codigo_sede,
            ':ciudad'         => $ciudad,
            ':direccion'      => $direccion,
            ':telefono'       => $telefono,
            ':hora_apertura'  => $hora_apertura ?: '07:20:00',
            ':estado'         => $estado,
            ':id'             => $id
        ]);
    }
}
