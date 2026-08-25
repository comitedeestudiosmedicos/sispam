<?php
require_once __DIR__ . '/../config/database.php';

class AuditLog {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->asegurarTablaLogs();
    }

    private function asegurarTablaLogs() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS logs_auditoria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                usuario_nombre VARCHAR(255) NULL,
                rol_nombre VARCHAR(100) NULL,
                modulo VARCHAR(100) NOT NULL,
                accion VARCHAR(100) NOT NULL,
                registro_id INT NULL,
                detalles TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_usuario (usuario_id),
                INDEX idx_modulo (modulo),
                INDEX idx_accion (accion),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Error al crear la tabla logs_auditoria: " . $e->getMessage());
        }
    }

    public function log($modulo, $accion, $registro_id = null, $detalles = null) {
        try {
            $usuario_id = $_SESSION['user_id'] ?? null;
            $usuario_nombre = $_SESSION['nombre_completo'] ?? ($_SESSION['usuario'] ?? 'Sistema / Invitado');
            $rol_nombre = $_SESSION['rol_nombre'] ?? 'Invitado';

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
            }

            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

            $stmt = $this->db->prepare("
                INSERT INTO logs_auditoria (usuario_id, usuario_nombre, rol_nombre, modulo, accion, registro_id, detalles, ip_address, user_agent) 
                VALUES (:uid, :unombre, :rol, :modulo, :accion, :reg_id, :detalles, :ip, :agent)
            ");

            return $stmt->execute([
                ':uid' => $usuario_id,
                ':unombre' => $usuario_nombre,
                ':rol' => $rol_nombre,
                ':modulo' => strtoupper($modulo),
                ':accion' => strtoupper($accion),
                ':reg_id' => $registro_id,
                ':detalles' => is_array($detalles) ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : $detalles,
                ':ip' => $ip,
                ':agent' => $user_agent
            ]);
        } catch (PDOException $e) {
            error_log("Error insertando log de auditoría: " . $e->getMessage());
            return false;
        }
    }

    public function getLogs($filters = [], $limit = 500) {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['modulo'])) {
            $where[] = "modulo = :modulo";
            $params[':modulo'] = strtoupper($filters['modulo']);
        }

        if (!empty($filters['accion'])) {
            $where[] = "accion = :accion";
            $params[':accion'] = strtoupper($filters['accion']);
        }

        if (!empty($filters['usuario_id'])) {
            $where[] = "usuario_id = :usuario_id";
            $params[':usuario_id'] = intval($filters['usuario_id']);
        }

        if (!empty($filters['fecha_desde'])) {
            $where[] = "created_at >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'] . ' 00:00:00';
        }

        if (!empty($filters['fecha_hasta'])) {
            $where[] = "created_at <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'] . ' 23:59:59';
        }

        if (!empty($filters['q'])) {
            $where[] = "(usuario_nombre LIKE :q OR detalles LIKE :q OR accion LIKE :q OR modulo LIKE :q OR registro_id LIKE :q)";
            $params[':q'] = '%' . trim($filters['q']) . '%';
        }

        $whereClause = implode(' AND ', $where);
        $limitVal = intval($limit);

        $sql = "SELECT * FROM logs_auditoria WHERE {$whereClause} ORDER BY id DESC LIMIT {$limitVal}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getEstadisticas() {
        $stats = [
            'total_hoy' => 0,
            'usuarios_activos_hoy' => 0,
            'modulo_mas_activo' => 'N/A',
            'inicios_sesion_hoy' => 0
        ];

        try {
            $hoy = date('Y-m-d');
            
            // Total hoy
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM logs_auditoria WHERE created_at >= :h");
            $stmt->execute([':h' => $hoy . ' 00:00:00']);
            $stats['total_hoy'] = $stmt->fetchColumn();

            // Usuarios distintos hoy
            $stmt = $this->db->prepare("SELECT COUNT(DISTINCT usuario_id) FROM logs_auditoria WHERE created_at >= :h AND usuario_id IS NOT NULL");
            $stmt->execute([':h' => $hoy . ' 00:00:00']);
            $stats['usuarios_activos_hoy'] = $stmt->fetchColumn();

            // Módulo más activo hoy
            $stmt = $this->db->prepare("SELECT modulo, COUNT(*) as total FROM logs_auditoria WHERE created_at >= :h GROUP BY modulo ORDER BY total DESC LIMIT 1");
            $stmt->execute([':h' => $hoy . ' 00:00:00']);
            $top = $stmt->fetch();
            if ($top) {
                $stats['modulo_mas_activo'] = $top['modulo'] . ' (' . $top['total'] . ')';
            }

            // Logins hoy
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM logs_auditoria WHERE created_at >= :h AND accion = 'LOGIN_EXITOSO'");
            $stmt->execute([':h' => $hoy . ' 00:00:00']);
            $stats['inicios_sesion_hoy'] = $stmt->fetchColumn();
        } catch (PDOException $e) {}

        return $stats;
    }

    public function getModulosDisponibles() {
        $modulosBase = [
            'AUTENTICACION',
            'INGRESO',
            'TRANSCRIPCION',
            'MONITOREO',
            'ALISTAMIENTO',
            'ENTREGA',
            'EXPEDIENTES',
            'USUARIOS',
            'EMPRESA'
        ];
        try {
            $stmt = $this->db->query("SELECT DISTINCT modulo FROM logs_auditoria ORDER BY modulo ASC");
            $dbMods = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $todos = array_unique(array_merge($modulosBase, $dbMods));
            sort($todos);
            return $todos;
        } catch (PDOException $e) {
            return $modulosBase;
        }
    }

    public function getAccionesDisponibles() {
        $stmt = $this->db->query("SELECT DISTINCT accion FROM logs_auditoria ORDER BY accion ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class Auditoria {
    // Constantes de eventos de auditoría
    const ATENCION_ABIERTA    = 'ATENCION_ABIERTA';
    const DERECHOS_VALIDADOS  = 'DERECHOS_VALIDADOS';
    const INGRESO_CREADO      = 'INGRESO_CREADO';
    const DOCUMENTO_ESCANEADO = 'DOCUMENTO_ESCANEADO';
    const TRANSCRIPCION       = 'TRANSCRIPCION';
    const ALISTAMIENTO        = 'ALISTAMIENTO';
    const ENTREGA             = 'ENTREGA';

    // Constantes de resultado
    const EXITO       = 'EXITO';
    const OMITIDO     = 'OMITIDO';
    const FALLO       = 'FALLO';
    const ADVERTENCIA = 'ADVERTENCIA';
    const INFO        = 'INFO';

    /**
     * Registra un evento de auditoría de manera centralizada
     *
     * @param string $evento Nombre del evento o acción
     * @param array $datos Metadatos del evento (atencion_id, entidad_tipo, resultado, detalle, etc.)
     * @return bool
     */
    public static function registrar($evento, array $datos = []) {
        try {
            $audit = new AuditLog();
            $modulo = $datos['modulo'] ?? ($datos['entidad_tipo'] ?? 'INGRESO');
            $registro_id = $datos['atencion_id'] ?? ($datos['entidad_id'] ?? ($datos['registro_id'] ?? null));
            $detalles = array_merge(['evento' => $evento], $datos);
            return $audit->log($modulo, $evento, $registro_id, $detalles);
        } catch (Throwable $e) {
            error_log("Error en Auditoria::registrar: " . $e->getMessage());
            return false;
        }
    }
}
