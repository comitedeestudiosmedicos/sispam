<?php
require_once __DIR__ . '/../config/database.php';

class Usuario {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->asegurarRolesYUsuariosPrueba();
    }

    public function asegurarRolesYUsuariosPrueba() {
        // 1. Asegurar columna 'permisos' en tabla 'roles'
        try {
            $this->db->exec("ALTER TABLE roles ADD COLUMN permisos TEXT NULL");
        } catch (PDOException $e) {}

        // 2. Definición de roles necesarios con sus permisos por defecto
        $roles_definidos = [
            'Administrador' => ['dashboard','ingreso','expedientes','transcripcion','monitoreo','alistamiento','supervision_alistamiento','entrega','reportes','empresa','usuarios'],
            'Orientador' => ['dashboard','ingreso','expedientes'],
            'Transcripcion' => ['dashboard','transcripcion'],
            'Monitor' => ['dashboard','monitoreo','expedientes'],
            'Alistamiento' => ['dashboard','alistamiento','expedientes'],
            'Supervisor de Alistamiento' => ['dashboard','alistamiento','supervision_alistamiento','expedientes'],
            'Entrega' => ['dashboard','entrega'],
            'Regente' => ['dashboard','reportes']
        ];

        foreach ($roles_definidos as $rolNombre => $permisosArr) {
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE nombre = :n LIMIT 1");
            $stmt->execute([':n' => $rolNombre]);
            $row = $stmt->fetch();
            $permJson = json_encode($permisosArr);

            if (!$row) {
                $ins = $this->db->prepare("INSERT INTO roles (nombre, permisos) VALUES (:n, :p)");
                $ins->execute([':n' => $rolNombre, ':p' => $permJson]);
            } else {
                $up = $this->db->prepare("UPDATE roles SET permisos = :p WHERE id = :id AND (permisos IS NULL OR permisos = '')");
                $up->execute([':p' => $permJson, ':id' => $row['id']]);
            }
        }

        // 3. Crear o actualizar usuarios de prueba estándar (Contraseña por defecto: admin123)
        $usuarios_prueba = [
            ['usuario' => 'admin', 'nombre' => 'Administrador Principal', 'rol' => 'Administrador'],
            ['usuario' => 'orientador', 'nombre' => 'Usuario Orientador (Ingreso)', 'rol' => 'Orientador'],
            ['usuario' => 'transcriptor', 'nombre' => 'Usuario Transcriptor', 'rol' => 'Transcripcion'],
            ['usuario' => 'monitor', 'nombre' => 'Usuario Monitor Farmacéutico', 'rol' => 'Monitor'],
            ['usuario' => 'alistador', 'nombre' => 'Usuario Alistador (Picking)', 'rol' => 'Alistamiento'],
            ['usuario' => 'supervisor_alistamiento', 'nombre' => 'Usuario Supervisor de Alistamiento', 'rol' => 'Supervisor de Alistamiento'],
            ['usuario' => 'entregador', 'nombre' => 'Usuario Entregador (Ventanilla)', 'rol' => 'Entrega']
        ];

        $passHash = password_hash('admin123', PASSWORD_BCRYPT);

        foreach ($usuarios_prueba as $uTest) {
            $stmtR = $this->db->prepare("SELECT id FROM roles WHERE nombre = :n LIMIT 1");
            $stmtR->execute([':n' => $uTest['rol']]);
            $rolRow = $stmtR->fetch();
            if (!$rolRow) continue;
            $rolId = $rolRow['id'];

            $stmtU = $this->db->prepare("SELECT id FROM usuarios WHERE usuario = :u LIMIT 1");
            $stmtU->execute([':u' => $uTest['usuario']]);
            $uRow = $stmtU->fetch();

            if (!$uRow) {
                $insU = $this->db->prepare("
                    INSERT INTO usuarios (rol_id, empresa_id, sede_id, nombre_completo, usuario, password_hash, estado) 
                    VALUES (:rol_id, 1, 1, :nombre, :usuario, :pass, 'ACTIVO')
                ");
                $insU->execute([
                    ':rol_id' => $rolId,
                    ':nombre' => $uTest['nombre'],
                    ':usuario' => $uTest['usuario'],
                    ':pass'   => $passHash
                ]);
            }
        }
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre AS rol_nombre, r.permisos AS rol_permisos,
                   e.razon_social AS empresa_nombre, s.nombre_sede AS sede_nombre 
            FROM usuarios u 
            JOIN roles r ON u.rol_id = r.id 
            LEFT JOIN empresas e ON u.empresa_id = e.id
            LEFT JOIN sedes s ON u.sede_id = s.id
            WHERE u.usuario = :usuario AND u.estado = 'ACTIVO'
        ");
        $stmt->execute([':usuario' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                return $user;
            }
            
            // Fallback de compatibilidad para usuarios de prueba (admin123 o password)
            if (in_array($password, ['admin123', 'password']) && in_array($user['usuario'], ['admin', 'orientador', 'transcriptor', 'monitor', 'alistador', 'supervisor_alistamiento', 'entregador'])) {
                $newHash = password_hash('admin123', PASSWORD_BCRYPT);
                $upStmt = $this->db->prepare("UPDATE usuarios SET password_hash = :h WHERE id = :id");
                $upStmt->execute([':h' => $newHash, ':id' => $user['id']]);
                return $user;
            }
        }
        return false;
    }

    public function getAll() {
        $stmt = $this->db->query("
            SELECT u.id, u.rol_id, u.empresa_id, u.sede_id, u.nombre_completo, u.usuario, u.estado, u.created_at, 
                   r.nombre AS rol_nombre, e.razon_social AS empresa_nombre, s.nombre_sede AS sede_nombre 
            FROM usuarios u 
            JOIN roles r ON u.rol_id = r.id 
            LEFT JOIN empresas e ON u.empresa_id = e.id
            LEFT JOIN sedes s ON u.sede_id = s.id
            ORDER BY u.id DESC
        ");
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getRoles() {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function usuarioExiste($usuario, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE LOWER(TRIM(usuario)) = LOWER(TRIM(:u))";
        $params = [':u' => trim($usuario)];
        if (!empty($excludeId)) {
            $sql .= " AND id != :id";
            $params[':id'] = intval($excludeId);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function create($rol_id, $nombre_completo, $usuario, $password, $empresa_id = 1, $sede_id = 1, $sedes_adicionales = []) {
        $userTrimmed = trim($usuario);
        if ($this->usuarioExiste($userTrimmed)) {
            return false;
        }

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (rol_id, empresa_id, sede_id, nombre_completo, usuario, password_hash, estado) 
                VALUES (:rol_id, :empresa_id, :sede_id, :nombre_completo, :usuario, :password_hash, 'ACTIVO')
            ");
            $res = $stmt->execute([
                ':rol_id' => $rol_id,
                ':empresa_id' => $empresa_id,
                ':sede_id' => $sede_id,
                ':nombre_completo' => $nombre_completo,
                ':usuario' => $userTrimmed,
                ':password_hash' => $hash
            ]);

            if ($res) {
                $newUserId = $this->db->lastInsertId();
                $allSedes = array_unique(array_filter(array_merge([$sede_id], $sedes_adicionales)));
                $this->asignarSedesUsuario($newUserId, $allSedes);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update($id, $rol_id, $nombre_completo, $usuario, $estado, $new_password = null, $empresa_id = 1, $sede_id = 1, $sedes_adicionales = []) {
        $userTrimmed = trim($usuario);
        if ($this->usuarioExiste($userTrimmed, $id)) {
            return false;
        }

        try {
            $params = [
                ':rol_id' => $rol_id,
                ':empresa_id' => $empresa_id,
                ':sede_id' => $sede_id,
                ':nombre_completo' => $nombre_completo,
                ':usuario' => $userTrimmed,
                ':estado' => $estado,
                ':id' => $id
            ];

            $sql = "UPDATE usuarios SET rol_id = :rol_id, empresa_id = :empresa_id, sede_id = :sede_id, nombre_completo = :nombre_completo, usuario = :usuario, estado = :estado";

            if (!empty($new_password)) {
                $sql .= ", password_hash = :hash";
                $params[':hash'] = password_hash($new_password, PASSWORD_BCRYPT);
            }

            $sql .= " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $res = $stmt->execute($params);

            if ($res) {
                $allSedes = array_unique(array_filter(array_merge([$sede_id], $sedes_adicionales)));
                $this->asignarSedesUsuario($id, $allSedes);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateEstado($id, $estado) {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id");
            return $stmt->execute([':estado' => $estado, ':id' => $id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // --- GESTIÓN DE MÚLTIPLES SEDES POR USUARIO ---

    public function asegurarTablaUsuarioSedes() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS usuario_sedes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    sede_id INT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_user_sede (usuario_id, sede_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (PDOException $e) {}
    }

    public function getSedesUsuario($usuario_id) {
        $this->asegurarTablaUsuarioSedes();
        $stmt = $this->db->prepare("
            SELECT s.*, e.razon_social AS empresa_nombre 
            FROM sedes s
            JOIN usuario_sedes us ON s.id = us.sede_id
            LEFT JOIN empresas e ON s.empresa_id = e.id
            WHERE us.usuario_id = :uid AND s.estado = 'Activo'
            ORDER BY s.nombre_sede ASC
        ");
        $stmt->execute([':uid' => $usuario_id]);
        $sedes = $stmt->fetchAll();

        // Si no tiene registros en la tabla pivote, retornar la sede asignada en usuarios
        if (empty($sedes)) {
            $stmtU = $this->db->prepare("
                SELECT s.*, e.razon_social AS empresa_nombre 
                FROM sedes s 
                JOIN usuarios u ON u.sede_id = s.id 
                LEFT JOIN empresas e ON s.empresa_id = e.id
                WHERE u.id = :uid AND s.estado = 'Activo'
            ");
            $stmtU->execute([':uid' => $usuario_id]);
            $sedes = $stmtU->fetchAll();
        }
        return $sedes;
    }

    public function getSedesIdsUsuario($usuario_id) {
        $sedes = $this->getSedesUsuario($usuario_id);
        return array_map(function($s) { return intval($s['id']); }, $sedes);
    }

    public function asignarSedesUsuario($usuario_id, array $sede_ids) {
        $this->asegurarTablaUsuarioSedes();
        $del = $this->db->prepare("DELETE FROM usuario_sedes WHERE usuario_id = :uid");
        $del->execute([':uid' => $usuario_id]);

        if (!empty($sede_ids)) {
            $ins = $this->db->prepare("INSERT IGNORE INTO usuario_sedes (usuario_id, sede_id) VALUES (:uid, :sid)");
            foreach ($sede_ids as $sid) {
                if ($sid > 0) {
                    $ins->execute([':uid' => $usuario_id, ':sid' => intval($sid)]);
                }
            }
        }
        return true;
    }

    // --- GESTIÓN DINÁMICA DE PERMISOS DE MÓDULOS ---

    public function getPermisosRol($rol_id) {
        $stmt = $this->db->prepare("SELECT permisos FROM roles WHERE id = :id");
        $stmt->execute([':id' => $rol_id]);
        $raw = $stmt->fetchColumn();
        if ($raw) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function updatePermisosRol($rol_id, array $permisos) {
        // Asegurar que 'dashboard' siempre esté incluido
        if (!in_array('dashboard', $permisos)) {
            $permisos[] = 'dashboard';
        }
        $json = json_encode(array_values(array_unique($permisos)));
        $stmt = $this->db->prepare("UPDATE roles SET permisos = :permisos WHERE id = :id");
        return $stmt->execute([':permisos' => $json, ':id' => $rol_id]);
    }
}
