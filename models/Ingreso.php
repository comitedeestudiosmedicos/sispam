<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Notificacion.php';

class Ingreso {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function generarTicketConsecutivo() {
        $prefix = "TK-" . date('ymd') . "-";

        $stmt = $this->db->prepare("
            SELECT ticket_numero FROM ingresos 
            WHERE ticket_numero LIKE :prefix 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '%']);
        $ultimo = $stmt->fetchColumn();

        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $secuencia = intval(end($partes)) + 1;
        } else {
            $secuencia = 1;
        }

        return $prefix . str_pad($secuencia, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Normaliza los archivos subidos (individuales o por escáner) en una lista homogénea.
     */
    public static function normalizarSoportes($files = [], $tipos = [], $recortes = []) {
        $resultado = [];
        if (empty($files['name']) || !is_array($files['name'])) {
            return $resultado;
        }

        $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        foreach ($files['name'] as $idx => $nombreOriginal) {
            if (empty($nombreOriginal)) continue;
            if (isset($files['error'][$idx]) && $files['error'][$idx] !== UPLOAD_ERR_OK && $files['error'][$idx] !== UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $tmpName = $files['tmp_name'][$idx] ?? '';
            if (empty($tmpName)) continue;

            $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            $tipo = $tipos[$idx] ?? 'OTRO';
            $recorte = !empty($recortes[$idx]) ? 1 : 0;
            $esValida = in_array($ext, $extensionesPermitidas);

            $resultado[] = [
                'tipo'           => $tipo,
                'name'           => $nombreOriginal,
                'tmp_name'       => $tmpName,
                'type'           => $files['type'][$idx] ?? '',
                'ext'            => $ext,
                'ext_valida'     => $esValida,
                'recorte_manual' => $recorte
            ];
        }

        return $resultado;
    }

    public function crearIngreso($paciente_id, $orientador_id, $archivos_subidos = [], $prioridad = 'NORMAL', $prioridad_obs = '', $persona_reclama = 'PACIENTE_DIRECTO', $ips_remite = null) {
        $stmtP = $this->db->prepare("SELECT tipo_documento, numero_documento FROM pacientes WHERE id = :id");
        $stmtP->execute([':id' => $paciente_id]);
        $paciente = $stmtP->fetch();

        $ticket = $this->generarTicketConsecutivo();
        $fecha_folder = date('dmy');

        $folder_name = $paciente['numero_documento'] . '_' . $fecha_folder;
        $rel_dir = 'assets/uploads/pacientes/' . $paciente['tipo_documento'] . '_' . $paciente['numero_documento'] . '/' . $folder_name . '/';
        $full_dir = BASE_DIR . '/' . $rel_dir;

        if (!file_exists($full_dir)) {
            mkdir($full_dir, 0755, true);
        }

        // Auto-crear columnas si no existen en la BD (Mover fuera de la transacción explícita para evitar commit implícito de MySQL DDL)
        try {
            $stmtColsI = $this->db->query("SHOW COLUMNS FROM ingresos");
            $colsExistentesI = $stmtColsI ? $stmtColsI->fetchAll(PDO::FETCH_COLUMN) : [];

            if (!in_array('persona_reclama', $colsExistentesI)) {
                $this->db->exec("ALTER TABLE `ingresos` ADD COLUMN `persona_reclama` ENUM('PACIENTE_DIRECTO','TERCERO_ACUDIENTE') DEFAULT 'PACIENTE_DIRECTO'");
            }
            if (!in_array('ips_remite', $colsExistentesI)) {
                $this->db->exec("ALTER TABLE `ingresos` ADD COLUMN `ips_remite` VARCHAR(255) NULL");
            }
        } catch (Exception $e) {}

        $this->db->beginTransaction();

        try {
            $empresa_id = $_SESSION['empresa_id'] ?? 1;
            $sede_id    = $_SESSION['active_sede_id'] ?? $_SESSION['sede_id'] ?? 1;

            $stmt = $this->db->prepare("
                INSERT INTO ingresos (empresa_id, sede_id, ticket_numero, paciente_id, orientador_id, fecha_ingreso, estado_tramite, prioridad, prioridad_observacion, persona_reclama, ips_remite) 
                VALUES (:empresa_id, :sede_id, :ticket, :paciente_id, :orientador_id, NOW(), 'INGRESADO', :prioridad, :prioridad_obs, :persona_reclama, :ips_remite)
            ");
            $stmt->execute([
                ':empresa_id'      => $empresa_id,
                ':sede_id'         => $sede_id,
                ':ticket'          => $ticket,
                ':paciente_id'     => $paciente_id,
                ':orientador_id'   => $orientador_id,
                ':prioridad'       => $prioridad ?: 'NORMAL',
                ':prioridad_obs'   => $prioridad_obs ?: null,
                ':persona_reclama' => $persona_reclama ?: 'PACIENTE_DIRECTO',
                ':ips_remite'      => $ips_remite ?: null
            ]);
            $ingreso_id = $this->db->lastInsertId();

            if (!empty($archivos_subidos)) {
                $stmtDoc = $this->db->prepare("
                    INSERT INTO ingreso_documentos (ingreso_id, tipo_documento, ruta_archivo, nombre_original) 
                    VALUES (:ingreso_id, :tipo_doc, :ruta, :nombre_orig)
                ");

                foreach ($archivos_subidos as $key => $file_info) {
                    $tipo_doc_tag = $file_info['tipo'] ?? (is_string($key) ? $key : 'DOCUMENTO');
                    if (isset($file_info['tmp_name']) && is_uploaded_file($file_info['tmp_name'])) {
                        $ext = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                        $clean_filename = strtolower($tipo_doc_tag) . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        $target_file = $full_dir . $clean_filename;
                        $ruta_relativa = $rel_dir . $clean_filename;

                        if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                            $stmtDoc->execute([
                                ':ingreso_id' => $ingreso_id,
                                ':tipo_doc' => $tipo_doc_tag,
                                ':ruta' => $ruta_relativa,
                                ':nombre_orig' => $file_info['name']
                            ]);
                        }
                    }
                }
            }

            $docTipo = $paciente['tipo_documento'] ?? '';
            $docNum  = $paciente['numero_documento'] ?? '';
            registrar_log_auditoria('INGRESO', 'CREAR_INGRESO', $ingreso_id, "Nuevo paciente registrado Doc: {$docTipo} {$docNum}. Tiquete: {$ticket}");

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            return ['id' => $ingreso_id, 'ticket' => $ticket];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error al crear ingreso: " . $e->getMessage());
            return false;
        }
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre,
                   l.nombre_completo AS lock_user_nombre,
                   s.nombre_sede AS sede_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN usuarios l ON i.locked_by_user_id = l.id
            LEFT JOIN sedes s ON i.sede_id = s.id
            WHERE i.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $ingreso = $stmt->fetch();

        if ($ingreso) {
            $stmtDocs = $this->db->prepare("SELECT * FROM ingreso_documentos WHERE ingreso_id = :id");
            $stmtDocs->execute([':id' => $id]);
            $ingreso['documentos'] = $stmtDocs->fetchAll();
        }

        return $ingreso;
    }

    // Lista de trabajo para Transcripción (Módulo 2)
    public function getListaTranscripcion() {
        $sql = "
            SELECT i.*, p.tipo_documento, p.numero_documento, p.nombres, p.apellidos, p.eps_nombre,
                   u.nombre_completo AS orientador_nombre,
                   l.nombre_completo AS locked_by_nombre,
                   (SELECT COUNT(*) FROM ingreso_documentos d WHERE d.ingreso_id = i.id) AS num_docs
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN usuarios l ON i.locked_by_user_id = l.id
            WHERE i.estado_tramite IN ('INGRESADO', 'EN_TRANSCRIPCION')";

        $params = [];
        $active_sede = $_SESSION['active_sede_id'] ?? $_SESSION['sede_id'] ?? null;
        if ($active_sede && ($_SESSION['rol_nombre'] ?? '') !== 'Administrador') {
            $sql .= " AND (i.sede_id IS NULL OR i.sede_id = :sede_id)";
            $params[':sede_id'] = $active_sede;
        }

        $sql .= " ORDER BY IF(i.prioridad = 'NORMAL', 1, 0) ASC, i.fecha_ingreso ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Bloqueo de concurrencia de registro
    public function lockRecord($ingreso_id, $user_id) {
        $this->db->exec("SET time_zone = '-05:00'");
        $this->db->exec("UPDATE ingresos SET locked_by_user_id = NULL, locked_at = NULL WHERE locked_at IS NOT NULL AND locked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");

        $stmtCheck = $this->db->prepare("SELECT locked_by_user_id FROM ingresos WHERE id = :id");
        $stmtCheck->execute([':id' => $ingreso_id]);
        $current_lock = $stmtCheck->fetchColumn();

        if ($current_lock && $current_lock != $user_id) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                locked_by_user_id = :user_id, 
                locked_at = NOW(), 
                estado_tramite = IF(estado_tramite = 'INGRESADO', 'EN_TRANSCRIPCION', estado_tramite)
            WHERE id = :id
        ");
        $stmt->execute([':user_id' => $user_id, ':id' => $ingreso_id]);
        registrar_log_auditoria('TRANSCRIPCION', 'BLOQUEAR_REGISTRO', $ingreso_id, "Orden #{$ingreso_id} tomada para transcripción (bloqueo de concurrencia).");
        return true;
    }

    public function unlockRecord($ingreso_id, $user_id) {
        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                locked_by_user_id = NULL, 
                locked_at = NULL,
                estado_tramite = IF(estado_tramite = 'EN_TRANSCRIPCION', 'INGRESADO', estado_tramite)
            WHERE id = :id AND locked_by_user_id = :user_id
        ");
        return $stmt->execute([':id' => $ingreso_id, ':user_id' => $user_id]);
    }

    public function unlockRecordForce($ingreso_id) {
        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                locked_by_user_id = NULL, 
                locked_at = NULL,
                estado_tramite = IF(estado_tramite = 'EN_TRANSCRIPCION', 'INGRESADO', estado_tramite)
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $ingreso_id]);
    }

    public function guardarTranscripcion($ingreso_id, $pdf_file = null, $user_id = null) {
        $ingreso = $this->getById($ingreso_id);
        $ruta_pdf = $ingreso['pdf_transcripcion_url'];

        if ($pdf_file && isset($pdf_file['tmp_name']) && is_uploaded_file($pdf_file['tmp_name'])) {
            $rel_dir = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/transcripciones/';
            $full_dir = BASE_DIR . '/' . $rel_dir;
            if (!file_exists($full_dir)) {
                mkdir($full_dir, 0755, true);
            }
            $filename = 'transcripcion_' . $ingreso['ticket_numero'] . '_' . time() . '.pdf';
            if (move_uploaded_file($pdf_file['tmp_name'], $full_dir . $filename)) {
                $ruta_pdf = $rel_dir . $filename;
            }
        }

        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = 'TRANSCRITO_COMPLETO', 
                pdf_transcripcion_url = :pdf, 
                locked_by_user_id = NULL, 
                locked_at = NULL 
            WHERE id = :id
        ");
        $res = $stmt->execute([
            ':pdf' => $ruta_pdf,
            ':id' => $ingreso_id
        ]);
        if ($res) {
            registrar_log_auditoria('TRANSCRIPCION', 'GUARDAR_TRANSCRIPCION', $ingreso_id, "Transcripción completada con PDF e inventario verificado para la orden #{$ingreso_id}. Tiquete: " . ($ingreso['ticket_numero'] ?? 'N/A'));
        }
        return $res;
    }

    public function reportarEstadoStockAlistamiento($ingreso_id, $estado, $observaciones) {
        $ingreso = $this->getById($ingreso_id);
        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = :estado, 
                observaciones_pendientes = :obs 
            WHERE id = :id
        ");
        $success = $stmt->execute([
            ':estado' => $estado,
            ':obs' => $observaciones,
            ':id' => $ingreso_id
        ]);

        if ($success && in_array($estado, ['TRANSCRITO_PENDIENTE', 'SIN_STOCK'])) {
            $notif = new Notificacion();
            $txtEstado = ($estado === 'TRANSCRITO_PENDIENTE') ? 'CON MEDICAMENTOS PENDIENTES' : 'SIN STOCK';
            $msg = "⚠️ ATENCIÓN (ALISTAMIENTO): El paciente {$ingreso['nombres']} {$ingreso['apellidos']} (Tiquete: {$ingreso['ticket_numero']}) ha sido reportado por Alistamiento como {$txtEstado}. Detalle: {$observaciones}";
            $notif->create($ingreso_id, $ingreso['orientador_id'], $msg);
        }

        return $success;
    }

    // Lista de trabajo para Alistamiento (Módulo 6) con prioridad antepuesta y bloqueo de concurrencia
    // Lista de trabajo para Alistamiento con prioridad antepuesta y desbloqueado universal
    public function getListaAlistamiento($filtro = 'TODOS') {
        $this->asegurarTablaLocks();
        $sql = "
            SELECT i.*, p.tipo_documento, p.numero_documento, p.nombres, p.apellidos, p.eps_nombre,
                   l.nombre_completo AS locked_by_nombre
            FROM ingresos i
            LEFT JOIN pacientes p ON i.paciente_id = p.id
            LEFT JOIN usuarios l ON i.locked_by_user_id = l.id
            WHERE i.estado_tramite NOT IN ('ALISTADO', 'GESTIONADO', 'ENTREGADO', 'CANCELADO')";

        if (!empty($filtro) && $filtro !== 'TODOS') {
            $sql .= " AND i.estado_tramite = " . $this->db->quote($filtro);
        }

        $sql .= " ORDER BY IF(i.prioridad = 'NORMAL', 1, 0) ASC, i.fecha_ingreso ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function obtenerModuloEquitativo() {
        require_once __DIR__ . '/ModuloEntrega.php';
        $modModel = new ModuloEntrega();
        $modulos = $modModel->getActivos();
        
        if (empty($modulos)) {
            return ['modulo' => 'Ventanilla 1', 'cola_actual' => 0];
        }

        $stmt = $this->db->query("
            SELECT modulo_entrega_asignado, COUNT(*) as total 
            FROM ingresos 
            WHERE estado_tramite = 'ALISTADO' 
            GROUP BY modulo_entrega_asignado
        ");
        $filas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $colas = [];
        foreach ($modulos as $m) {
            $mod = $m['nombre_modulo'] ?? $m['nombre'];
            $colas[$mod] = intval($filas[$mod] ?? 0);
        }

        $min_pacientes = min($colas);
        $modulos_menos_cargados = array_keys(array_filter($colas, fn($cant) => $cant === $min_pacientes));
        $modulo_seleccionado = $modulos_menos_cargados[array_rand($modulos_menos_cargados)];

        return [
            'modulo' => $modulo_seleccionado,
            'cola_actual' => $min_pacientes
        ];
    }

    public function guardarAlistamiento($ingreso_id, $pdf_file = null, $faltantes_text = '', $user_id = null, $modulo_entrega = 'AUTO') {
        $ingreso = $this->getById($ingreso_id);
        $ruta_pdf = $ingreso['pdf_alistamiento'] ?? null;

        if ($pdf_file && isset($pdf_file['tmp_name']) && is_uploaded_file($pdf_file['tmp_name'])) {
            $rel_dir = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/alistamientos/';
            $full_dir = BASE_DIR . '/' . $rel_dir;
            if (!file_exists($full_dir)) {
                mkdir($full_dir, 0755, true);
            }
            $filename = 'alistamiento_' . $ingreso['ticket_numero'] . '_' . time() . '.pdf';
            if (move_uploaded_file($pdf_file['tmp_name'], $full_dir . $filename)) {
                $ruta_pdf = $rel_dir . $filename;
            }
        }

        if (empty($modulo_entrega) || strtoupper(trim($modulo_entrega)) === 'AUTO') {
            $bal = $this->obtenerModuloEquitativo();
            $modulo_entrega = $bal['modulo'];
        }

        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = 'ALISTADO', 
                pdf_alistamiento = :pdf, 
                faltantes_alistamiento = :faltantes,
                alistado_por_user_id = :user_id,
                fecha_alistado = NOW(),
                modulo_entrega_asignado = :modulo,
                locked_by_user_id = NULL,
                locked_at = NULL
            WHERE id = :id
        ");
        $result = $stmt->execute([
            ':pdf' => $ruta_pdf,
            ':faltantes' => $faltantes_text ?: null,
            ':user_id' => $user_id,
            ':modulo' => $modulo_entrega,
            ':id' => $ingreso_id
        ]);
        if ($result) {
            registrar_log_auditoria('ALISTAMIENTO', 'GUARDAR_ALISTAMIENTO', $ingreso_id, "Medicamentos alistados en estación de picking. Asignado a: {$modulo_entrega}. Tiquete: " . ($ingreso['ticket_numero'] ?? 'N/A'));
        }
        return $result ? $modulo_entrega : false;
    }

    public function getListaEntrega($modulo_filtro = null) {
        $sql = "
            SELECT i.*, p.tipo_documento, p.numero_documento, p.nombres, p.apellidos, p.eps_nombre, p.telefono,
                   (SELECT d.ruta_archivo FROM ingreso_documentos d WHERE d.ingreso_id = i.id AND (d.tipo_documento LIKE '%FORMULA%' OR d.tipo_documento LIKE '%TRANSCRIPCION%') ORDER BY d.id DESC LIMIT 1) AS pdf_documento_ingreso
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            WHERE i.estado_tramite IN ('ALISTADO', 'TRANSCRITO_COMPLETO', 'TRANSCRITO_PENDIENTE')";
        
        $params = [];
        $active_sede = $_SESSION['active_sede_id'] ?? $_SESSION['sede_id'] ?? null;
        if ($active_sede && ($_SESSION['rol_nombre'] ?? '') !== 'Administrador') {
            $sql .= " AND (i.sede_id IS NULL OR i.sede_id = :sede_id)";
            $params[':sede_id'] = $active_sede;
        }

        if ($modulo_filtro && $modulo_filtro !== 'TODOS') {
            $sql .= " AND i.modulo_entrega_asignado = " . $this->db->quote($modulo_filtro);
        }

        $sql .= " ORDER BY IF(i.prioridad = 'NORMAL', 1, 0) ASC, i.updated_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function finalizarEntrega($ingreso_id, $firma_base64, $foto_base64 = null, $foto_file = null) {
        $ingreso = $this->getById($ingreso_id);
        $ruta_firma = null;
        $ruta_foto  = null;

        if (!empty($firma_base64) && strpos($firma_base64, 'data:image') === 0) {
            $rel_dir = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/firmas/';
            $full_dir = BASE_DIR . '/' . $rel_dir;
            if (!file_exists($full_dir)) {
                mkdir($full_dir, 0755, true);
            }
            $data = explode(',', $firma_base64);
            $img_bytes = base64_decode($data[1]);
            $filename = 'firma_' . $ingreso['ticket_numero'] . '_' . time() . '.png';
            file_put_contents($full_dir . $filename, $img_bytes);
            $ruta_firma = $rel_dir . $filename;
        }

        $rel_dir_f = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/fotos/';
        $full_dir_f = BASE_DIR . '/' . $rel_dir_f;

        // Opción A: Foto capturada en vivo por Cámara Web (Base64)
        if (!empty($foto_base64) && strpos($foto_base64, 'data:image') === 0) {
            if (!file_exists($full_dir_f)) {
                mkdir($full_dir_f, 0755, true);
            }
            $data_f = explode(',', $foto_base64);
            $img_bytes_f = base64_decode($data_f[1]);
            $filename_f = 'foto_paciente_' . $ingreso['ticket_numero'] . '_' . time() . '.jpg';
            file_put_contents($full_dir_f . $filename_f, $img_bytes_f);
            $ruta_foto = $rel_dir_f . $filename_f;
        }
        // Opción B: Foto subida como archivo desde el equipo
        else if (!empty($foto_file) && isset($foto_file['tmp_name']) && is_uploaded_file($foto_file['tmp_name'])) {
            $ext = strtolower(pathinfo($foto_file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                if (!file_exists($full_dir_f)) {
                    mkdir($full_dir_f, 0755, true);
                }
                $filename_f = 'foto_paciente_' . $ingreso['ticket_numero'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($foto_file['tmp_name'], $full_dir_f . $filename_f)) {
                    $ruta_foto = $rel_dir_f . $filename_f;
                }
            }
        }

        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = 'ENTREGADO', 
                firma_paciente_url = :firma,
                foto_paciente_url = COALESCE(:foto, foto_paciente_url)
            WHERE id = :id
        ");
        $res = $stmt->execute([':firma' => $ruta_firma, ':foto' => $ruta_foto, ':id' => $ingreso_id]);
        if ($res) {
            registrar_log_auditoria('ENTREGA', 'REGISTRAR_ENTREGA_FIRMA', $ingreso_id, "Entrega finalizada con éxito. Firma digital capturada para la orden #{$ingreso_id}. Tiquete: " . ($ingreso['ticket_numero'] ?? 'N/A'));
        }
        return $res;
    }

    public function getTurnero1() {
        $stmt = $this->db->query("
            SELECT i.ticket_numero, p.nombres, p.apellidos, i.estado_tramite, i.fecha_ingreso, i.prioridad
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            WHERE i.estado_tramite IN ('INGRESADO', 'EN_TRANSCRIPCION', 'TRANSCRITO_COMPLETO', 'TRANSCRITO_PENDIENTE')
            ORDER BY IF(i.prioridad = 'NORMAL', 1, 0) ASC, i.fecha_ingreso ASC
            LIMIT 12
        ");
        return $stmt->fetchAll();
    }

    public function getTurnero2() {
        $stmt = $this->db->query("
            SELECT i.id, i.ticket_numero, i.modulo_entrega_asignado, p.nombres, p.apellidos, i.updated_at, i.prioridad
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            WHERE i.estado_tramite = 'ALISTADO'
            ORDER BY IF(i.prioridad = 'NORMAL', 1, 0) ASC, i.updated_at DESC
            LIMIT 10
        ");
        return $stmt->fetchAll();
    }

    public function buscarPorDocumento($num_doc) {
        $stmt = $this->db->prepare("
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            JOIN usuarios u ON i.orientador_id = u.id
            WHERE p.numero_documento = :doc
            ORDER BY i.fecha_ingreso DESC
        ");
        $stmt->execute([':doc' => $num_doc]);
        return $stmt->fetchAll();
    }

    public function getReportePacientes($fecha_desde = null, $fecha_hasta = null, $eps = null, $estado = null) {
        $sql = "
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            JOIN usuarios u ON i.orientador_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($fecha_desde)) {
            $sql .= " AND DATE(i.fecha_ingreso) >= :f_desde";
            $params[':f_desde'] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND DATE(i.fecha_ingreso) <= :f_hasta";
            $params[':f_hasta'] = $fecha_hasta;
        }
        if (!empty($eps)) {
            $sql .= " AND p.eps_nombre = :eps";
            $params[':eps'] = $eps;
        }
        if (!empty($estado)) {
            $sql .= " AND i.estado_tramite = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY i.fecha_ingreso DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getReporteTiemposSLA($fecha_desde = null, $fecha_hasta = null) {
        $sql = "
            SELECT i.id AS id, i.ticket_numero, i.fecha_ingreso, i.updated_at AS fecha_finalizacion, i.estado_tramite, i.modulo_entrega_asignado, i.prioridad,
                   p.tipo_documento, p.numero_documento, p.nombres, p.apellidos, p.eps_nombre,
                   u.nombre_completo AS orientador_nombre,
                   COALESCE(s.hora_apertura_atencion, ec.hora_apertura_atencion, '07:20:00') AS hora_apertura_oficial
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN sedes s ON i.sede_id = s.id
            LEFT JOIN empresa_config ec ON ec.id = 1
            WHERE 1=1
        ";
        $params = [];

        if (!empty($fecha_desde)) {
            $sql .= " AND DATE(i.fecha_ingreso) >= :f_desde";
            $params[':f_desde'] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND DATE(i.fecha_ingreso) <= :f_hasta";
            $params[':f_hasta'] = $fecha_hasta;
        }

        $sql .= " ORDER BY i.fecha_ingreso DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        foreach ($results as &$r) {
            try {
                $fechaIngreso = new DateTime($r['fecha_ingreso']);
                $fechaFinal   = !empty($r['fecha_finalizacion']) ? new DateTime($r['fecha_finalizacion']) : new DateTime();
                
                $horaAperturaStr = !empty($r['hora_apertura_oficial']) ? $r['hora_apertura_oficial'] : '07:20:00';
                $fechaApertura   = new DateTime($fechaIngreso->format('Y-m-d') . ' ' . $horaAperturaStr);
                
                $diffTotalSeg = max(0, $fechaFinal->getTimestamp() - $fechaIngreso->getTimestamp());
                $r['tiempo_total_minutos'] = round($diffTotalSeg / 60, 1);
                
                if ($fechaIngreso < $fechaApertura) {
                    $diffFilaSeg = max(0, min($fechaFinal->getTimestamp(), $fechaApertura->getTimestamp()) - $fechaIngreso->getTimestamp());
                    $r['tiempo_fila_externa_min'] = round($diffFilaSeg / 60, 1);
                    
                    $diffFarmaciaSeg = max(0, $fechaFinal->getTimestamp() - $fechaApertura->getTimestamp());
                    $r['tiempo_tramite_farmacia_min'] = round($diffFarmaciaSeg / 60, 1);
                    $r['ingresado_antes_apertura'] = true;
                } else {
                    $r['tiempo_fila_externa_min'] = 0;
                    $r['tiempo_tramite_farmacia_min'] = $r['tiempo_total_minutos'];
                    $r['ingresado_antes_apertura'] = false;
                }
            } catch (Exception $ex) {
                $r['tiempo_total_minutos'] = 0;
                $r['tiempo_fila_externa_min'] = 0;
                $r['tiempo_tramite_farmacia_min'] = 0;
                $r['ingresado_antes_apertura'] = false;
            }
        }

        return $results;
    }

    public function getReportePendientes($fecha_desde = null, $fecha_hasta = null) {
        $sql = "
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            JOIN usuarios u ON i.orientador_id = u.id
            WHERE (
                i.estado_tramite IN ('TRANSCRITO_PENDIENTE', 'SIN_STOCK') 
                OR (i.observaciones_pendientes IS NOT NULL AND TRIM(i.observaciones_pendientes) != '')
                OR (
                    i.faltantes_alistamiento IS NOT NULL 
                    AND TRIM(i.faltantes_alistamiento) != ''
                    AND i.faltantes_alistamiento NOT LIKE '%VERIFICACIÓN EXITOSA%'
                    AND i.faltantes_alistamiento NOT LIKE '%100% Coincide%'
                    AND i.faltantes_alistamiento NOT LIKE '%No hay novedades%'
                )
            )
        ";
        $params = [];

        if (!empty($fecha_desde)) {
            $sql .= " AND DATE(i.fecha_ingreso) >= :f_desde";
            $params[':f_desde'] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND DATE(i.fecha_ingreso) <= :f_hasta";
            $params[':f_hasta'] = $fecha_hasta;
        }

        $sql .= " ORDER BY i.fecha_ingreso DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getReporteProductividad($fecha_desde = null, $fecha_hasta = null) {
        $sql = "
            SELECT u.nombre_completo, r.nombre AS rol, COUNT(i.id) AS total_ingresos
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            LEFT JOIN ingresos i ON i.orientador_id = u.id
        ";
        $params = [];

        if (!empty($fecha_desde) || !empty($fecha_hasta)) {
            $sql .= " WHERE 1=1";
            if (!empty($fecha_desde)) {
                $sql .= " AND DATE(i.fecha_ingreso) >= :f_desde";
                $params[':f_desde'] = $fecha_desde;
            }
            if (!empty($fecha_hasta)) {
                $sql .= " AND DATE(i.fecha_ingreso) <= :f_hasta";
                $params[':f_hasta'] = $fecha_hasta;
            }
        }

        $sql .= " GROUP BY u.id, u.nombre_completo, r.nombre ORDER BY total_ingresos DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getReportePorEPS($fecha_desde = null, $fecha_hasta = null) {
        $sql = "
            SELECT p.eps_nombre, COUNT(i.id) AS total_pacientes
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($fecha_desde)) {
            $sql .= " AND DATE(i.fecha_ingreso) >= :f_desde";
            $params[':f_desde'] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND DATE(i.fecha_ingreso) <= :f_hasta";
            $params[':f_hasta'] = $fecha_hasta;
        }

        $sql .= " GROUP BY p.eps_nombre ORDER BY total_pacientes DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function asegurarTablaLocks() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `locks` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `record_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_record` (`record_id`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Exception $e) {}
    }

    // ==========================================
    // MÓDULO DE MONITOREO Y VERIFICACIÓN (MONITOR)
    // ==========================================
    public function getIngresosParaMonitoreo() {
        $this->asegurarTablaLocks();
        $sql = "
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre,
                   l.user_id AS locked_by_user,
                   u_lock.nombre_completo AS locked_by_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            LEFT JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN locks l ON l.record_id = i.id AND l.expires_at > NOW()
            LEFT JOIN usuarios u_lock ON l.user_id = u_lock.id
            WHERE i.estado_tramite IN ('TRANSCRITO', 'TRANSCRITO_COMPLETO', 'EN_TRANSCRIPCION')
            ORDER BY 
                CASE WHEN i.prioridad = 'EMBARAZADA' THEN 1
                     WHEN i.prioridad = 'TERCERA_EDAD' THEN 2
                     WHEN i.prioridad = 'DISCAPACIDAD' THEN 3
                     WHEN i.prioridad = 'NIÑO_LACTANTE' THEN 4
                     WHEN i.prioridad = 'OTRO_PREFERENCIAL' THEN 5
                     ELSE 6 END,
                i.fecha_ingreso ASC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function marcarVerificadoMonitor($id, $user_id, $estado_verificacion, $observaciones = '', $file_remplazo = null, $transcripcion_texto_nuevo = null) {
        $id = intval($id);
        $ingreso = $this->getById($id);
        if (!$ingreso) return false;

        $cols = [
            "ALTER TABLE `ingresos` ADD COLUMN `estado_verificacion` VARCHAR(50) DEFAULT 'PENDIENTE'",
            "ALTER TABLE `ingresos` ADD COLUMN `verificado_por_user_id` INT NULL",
            "ALTER TABLE `ingresos` ADD COLUMN `fecha_verificacion` DATETIME NULL",
            "ALTER TABLE `ingresos` ADD COLUMN `observacion_verificacion` TEXT NULL",
            "ALTER TABLE `ingresos` ADD COLUMN `pdf_formula_final_url` VARCHAR(255) NULL",
            "ALTER TABLE `ingresos` ADD COLUMN `locked_by_user_id` INT NULL",
            "ALTER TABLE `ingresos` ADD COLUMN `locked_at` DATETIME NULL"
        ];
        foreach ($cols as $sqlCol) {
            try { $this->db->exec($sqlCol); } catch (Exception $e) {}
        }

        $pdf_url = $ingreso['pdf_transcripcion_url'];

        if ($file_remplazo && isset($file_remplazo['tmp_name']) && is_uploaded_file($file_remplazo['tmp_name'])) {
            $ext = strtolower(pathinfo($file_remplazo['name'], PATHINFO_EXTENSION));
            $folder_name = $ingreso['numero_documento'] . '_' . date('dmy');
            $rel_dir = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/' . $folder_name . '/';
            $full_dir = BASE_DIR . '/' . $rel_dir;

            if (!file_exists($full_dir)) {
                mkdir($full_dir, 0755, true);
            }

            $filename = 'formula_transcrita_verificada_' . time() . '.' . $ext;
            $target_file = $full_dir . $filename;

            if (move_uploaded_file($file_remplazo['tmp_name'], $target_file)) {
                $pdf_url = $rel_dir . $filename;
            }
        }

        $stmtMain = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = 'VERIFICADO',
                pdf_transcripcion_url = :pdf_url
            WHERE id = :id
        ");
        $exitoMain = $stmtMain->execute([
            ':pdf_url' => $pdf_url,
            ':id'      => $id
        ]);

        try {
            $stmtSec = $this->db->prepare("
                UPDATE ingresos SET 
                    estado_verificacion = :estado_verif,
                    verificado_por_user_id = :user_id,
                    fecha_verificacion = NOW(),
                    observacion_verificacion = :obs,
                    locked_by_user_id = NULL,
                    locked_at = NULL
                WHERE id = :id
            ");
            $stmtSec->execute([
                ':estado_verif' => $estado_verificacion,
                ':user_id'      => $user_id,
                ':obs'          => $observaciones ?: null,
                ':id'           => $id
            ]);
        } catch (Exception $e) {}

        if ($transcripcion_texto_nuevo !== null) {
            try {
                $stmtTxt = $this->db->prepare("UPDATE ingresos SET transcripcion_texto = :txt WHERE id = :id");
                $stmtTxt->execute([':txt' => $transcripcion_texto_nuevo, ':id' => $id]);
            } catch (Exception $e) {}
        }

        try { $this->db->exec("DELETE FROM `locks` WHERE `record_id` = " . $id); } catch (Exception $e) {}

        return $exitoMain;
    }

    public function getIngresosParaSupervisor() {
        $this->asegurarTablaLocks();
        $sql = "
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre,
                   l.user_id AS locked_by_user,
                   u_lock.nombre_completo AS locked_by_nombre
            FROM ingresos i
            LEFT JOIN pacientes p ON i.paciente_id = p.id
            LEFT JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN locks l ON l.record_id = i.id AND l.expires_at > NOW()
            LEFT JOIN usuarios u_lock ON l.user_id = u_lock.id
            WHERE i.estado_tramite IN ('VERIFICADO', 'VERIFICADA')
            ORDER BY 
                CASE WHEN i.prioridad = 'EMBARAZADA' THEN 1
                     WHEN i.prioridad = 'TERCERA_EDAD' THEN 2
                     WHEN i.prioridad = 'DISCAPACIDAD' THEN 3
                     WHEN i.prioridad = 'NIÑO_LACTANTE' THEN 4
                     WHEN i.prioridad = 'OTRO_PREFERENCIAL' THEN 5
                     ELSE 6 END,
                i.fecha_ingreso ASC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function marcarGestionadoSupervisor($id, $user_id, $pdf_alistamiento_file = null, $faltantes = null) {
        $ingreso = $this->getById($id);
        if (!$ingreso) return false;

        $pdf_alist_url = $ingreso['pdf_alistamiento'];

        if ($pdf_alistamiento_file && isset($pdf_alistamiento_file['tmp_name']) && is_uploaded_file($pdf_alistamiento_file['tmp_name'])) {
            $ext = strtolower(pathinfo($pdf_alistamiento_file['name'], PATHINFO_EXTENSION));
            $folder_name = $ingreso['numero_documento'] . '_' . date('dmy');
            $rel_dir = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/' . $folder_name . '/';
            $full_dir = BASE_DIR . '/' . $rel_dir;

            if (!file_exists($full_dir)) {
                mkdir($full_dir, 0755, true);
            }

            $filename = 'alistamiento_' . time() . '.' . $ext;
            $target_file = $full_dir . $filename;

            if (move_uploaded_file($pdf_alistamiento_file['tmp_name'], $target_file)) {
                $pdf_alist_url = $rel_dir . $filename;
            }
        }

        // Asignar módulo de entrega si no tiene uno asignado
        $modulo_entrega = $ingreso['modulo_entrega_asignado'] ?? '';
        if (empty($modulo_entrega) || strtoupper(trim($modulo_entrega)) === 'AUTO') {
            $bal = $this->obtenerModuloEquitativo();
            $modulo_entrega = $bal['modulo'] ?? 'Ventanilla 1';
        }

        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = 'ALISTADO',
                modulo_entrega_asignado = :modulo,
                alistado_por_user_id = :user_id,
                fecha_alistado = NOW(),
                pdf_alistamiento = :pdf_alist,
                faltantes_alistamiento = :faltantes,
                locked_by_user_id = NULL,
                locked_at = NULL
            WHERE id = :id
        ");
        $res = $stmt->execute([
            ':modulo'    => $modulo_entrega,
            ':user_id'   => $user_id,
            ':pdf_alist' => $pdf_alist_url,
            ':faltantes' => $faltantes ?: $ingreso['faltantes_alistamiento'],
            ':id'        => $id
        ]);
        try { $this->db->exec("DELETE FROM `locks` WHERE `record_id` = " . intval($id)); } catch (Exception $e) {}
        return $res;
    }

    public function getIngresosParaEntrega() {
        $this->asegurarTablaLocks();
        $sql = "
            SELECT p.*, i.*, i.id AS id,
                   u.nombre_completo AS orientador_nombre,
                   l.user_id AS locked_by_user,
                   u_lock.nombre_completo AS locked_by_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            LEFT JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN locks l ON l.record_id = i.id AND l.expires_at > NOW()
            LEFT JOIN usuarios u_lock ON l.user_id = u_lock.id
            WHERE i.estado_tramite IN ('ALISTADO', 'GESTIONADO')
            ORDER BY i.fecha_ingreso ASC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function procesarEntregaFinalConFormula($id, $user_id, $file_formula_final = null, $firma_paciente_base64 = null, $faltantes_manuales = null, $foto_paciente_base64 = null, $foto_paciente_file = null, $pdf_savia_derechos = null, $pdf_savia_mipres = null) {
        $ingreso = $this->getById($id);
        if (!$ingreso) return false;

        // Auto-crear columnas si no existen
        try { $this->db->exec("ALTER TABLE `ingresos` ADD COLUMN `foto_paciente_url` VARCHAR(255) NULL"); } catch (Exception $e) {}
        try { $this->db->exec("ALTER TABLE `ingresos` ADD COLUMN `pdf_validacion_derechos_url` VARCHAR(255) NULL"); } catch (Exception $e) {}
        try { $this->db->exec("ALTER TABLE `ingresos` ADD COLUMN `pdf_mipres_url` VARCHAR(255) NULL"); } catch (Exception $e) {}

        $pdf_final_url    = $ingreso['pdf_formula_final_url'];
        $firma_url        = $ingreso['firma_paciente_url'];
        $foto_url          = $ingreso['foto_paciente_url'] ?? null;
        $pdf_derechos_url = $ingreso['pdf_validacion_derechos_url'] ?? null;
        $pdf_mipres_url   = $ingreso['pdf_mipres_url'] ?? null;

        $folder_name = $ingreso['numero_documento'] . '_' . date('dmy');
        $rel_dir = 'assets/uploads/pacientes/' . $ingreso['tipo_documento'] . '_' . $ingreso['numero_documento'] . '/' . $folder_name . '/';
        $full_dir = BASE_DIR . '/' . $rel_dir;

        if (!file_exists($full_dir)) {
            mkdir($full_dir, 0755, true);
        }

        // Subcarpeta 'soportes Savia' para Validación de Derechos y Mipres
        $rel_savia_dir = $rel_dir . 'soportes Savia/';
        $full_savia_dir = BASE_DIR . '/' . $rel_savia_dir;
        if (!file_exists($full_savia_dir)) {
            mkdir($full_savia_dir, 0755, true);
        }

        // Subida de la fórmula final descargada del software de terceros
        if ($file_formula_final && isset($file_formula_final['tmp_name']) && is_uploaded_file($file_formula_final['tmp_name'])) {
            $ext = strtolower(pathinfo($file_formula_final['name'], PATHINFO_EXTENSION));
            $filename = 'formula_final_terceros_' . time() . '.' . $ext;
            $target_file = $full_dir . $filename;

            if (move_uploaded_file($file_formula_final['tmp_name'], $target_file)) {
                $pdf_final_url = $rel_dir . $filename;
            }
        }

        // Subida de Validación de Derechos de Savia (en subcarpeta 'soportes Savia')
        if ($pdf_savia_derechos && isset($pdf_savia_derechos['tmp_name']) && is_uploaded_file($pdf_savia_derechos['tmp_name'])) {
            $ext_der = strtolower(pathinfo($pdf_savia_derechos['name'], PATHINFO_EXTENSION));
            $filename_der = 'validacion_derechos_savia_' . time() . '.' . $ext_der;
            $target_file_der = $full_savia_dir . $filename_der;

            if (move_uploaded_file($pdf_savia_derechos['tmp_name'], $target_file_der)) {
                $pdf_derechos_url = $rel_savia_dir . $filename_der;
                try {
                    $stmtDoc = $this->db->prepare("INSERT INTO ingreso_documentos (ingreso_id, tipo_documento, ruta_archivo) VALUES (:iid, 'Validación Derechos Savia', :ruta)");
                    $stmtDoc->execute([':iid' => $id, ':ruta' => $pdf_derechos_url]);
                } catch (Exception $e) {}
            }
        }

        // Subida de Mipres (en subcarpeta 'soportes Savia')
        if ($pdf_savia_mipres && isset($pdf_savia_mipres['tmp_name']) && is_uploaded_file($pdf_savia_mipres['tmp_name'])) {
            $ext_mip = strtolower(pathinfo($pdf_savia_mipres['name'], PATHINFO_EXTENSION));
            $filename_mip = 'mipres_' . time() . '.' . $ext_mip;
            $target_file_mip = $full_savia_dir . $filename_mip;

            if (move_uploaded_file($pdf_savia_mipres['tmp_name'], $target_file_mip)) {
                $pdf_mipres_url = $rel_savia_dir . $filename_mip;
                try {
                    $stmtDoc = $this->db->prepare("INSERT INTO ingreso_documentos (ingreso_id, tipo_documento, ruta_archivo) VALUES (:iid, 'Mipres', :ruta)");
                    $stmtDoc->execute([':iid' => $id, ':ruta' => $pdf_mipres_url]);
                } catch (Exception $e) {}
            }
        }

        // Guardar Firma digital del Paciente
        if (!empty($firma_paciente_base64)) {
            $img_data = str_replace('data:image/png;base64,', '', $firma_paciente_base64);
            $img_data = str_replace(' ', '+', $img_data);
            $data = base64_decode($img_data);
            $filename_firma = 'firma_' . time() . '.png';
            $file_path_firma = $full_dir . $filename_firma;
            file_put_contents($file_path_firma, $data);
            $firma_url = $rel_dir . $filename_firma;
        }

        // Guardar Foto del Paciente desde Cámara Web (Base64)
        if (!empty($foto_paciente_base64)) {
            $img_data = str_replace('data:image/jpeg;base64,', '', $foto_paciente_base64);
            $img_data = str_replace('data:image/png;base64,', '', $img_data);
            $img_data = str_replace(' ', '+', $img_data);
            $data = base64_decode($img_data);
            $filename_foto = 'foto_paciente_' . time() . '.jpg';
            $file_path_foto = $full_dir . $filename_foto;
            file_put_contents($file_path_foto, $data);
            $foto_url = $rel_dir . $filename_foto;
        }

        // Guardar Foto del Paciente desde Subida de Archivo
        if ($foto_paciente_file && isset($foto_paciente_file['tmp_name']) && is_uploaded_file($foto_paciente_file['tmp_name'])) {
            $ext_foto = strtolower(pathinfo($foto_paciente_file['name'], PATHINFO_EXTENSION));
            $filename_foto = 'foto_paciente_' . time() . '.' . $ext_foto;
            $target_file_foto = $full_dir . $filename_foto;
            if (move_uploaded_file($foto_paciente_file['tmp_name'], $target_file_foto)) {
                $foto_url = $rel_dir . $filename_foto;
            }
        }

        $stmt = $this->db->prepare("
            UPDATE ingresos SET 
                estado_tramite = 'ENTREGADO',
                pdf_formula_final_url = :pdf_final,
                pdf_validacion_derechos_url = :pdf_derechos,
                pdf_mipres_url = :pdf_mipres,
                firma_paciente_url = :firma,
                foto_paciente_url = :foto,
                faltantes_alistamiento = :faltantes
            WHERE id = :id
        ");

        return $stmt->execute([
            ':pdf_final'    => $pdf_final_url,
            ':pdf_derechos' => $pdf_derechos_url,
            ':pdf_mipres'   => $pdf_mipres_url,
            ':firma'        => $firma_url,
            ':foto'         => $foto_url,
            ':faltantes'    => $faltantes_manuales ?: $ingreso['faltantes_alistamiento'],
            ':id'           => $id
        ]);
    }

    public function getHistorialPacienteByDocumentoOrPacienteId($paciente_id, $numero_documento = '') {
        $num_doc_clean = preg_replace('/\D/', '', $numero_documento);
        
        // Si el número de documento viene vacío, obtenerlo desde la tabla pacientes
        if (empty($num_doc_clean) && $paciente_id > 0) {
            $stmtP = $this->db->prepare("SELECT numero_documento FROM pacientes WHERE id = :pid LIMIT 1");
            $stmtP->execute([':pid' => $paciente_id]);
            $rowP = $stmtP->fetch();
            if ($rowP && !empty($rowP['numero_documento'])) {
                $num_doc_clean = preg_replace('/\D/', '', $rowP['numero_documento']);
            }
        }

        $sql = "
            SELECT i.*, p.tipo_documento, p.numero_documento, p.nombres, p.apellidos, p.eps_nombre,
                   u.nombre_completo AS orientador_nombre,
                   u_alist.nombre_completo AS alistador_nombre
            FROM ingresos i
            JOIN pacientes p ON i.paciente_id = p.id
            LEFT JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN usuarios u_alist ON i.alistado_por_user_id = u_alist.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($num_doc_clean)) {
            $sql .= " AND (REPLACE(REPLACE(REPLACE(p.numero_documento, '.', ''), ' ', ''), '-', '') LIKE :num_doc OR i.paciente_id = :pid)";
            $params[':num_doc'] = '%' . $num_doc_clean . '%';
            $params[':pid']     = $paciente_id;
        } else {
            $sql .= " AND i.paciente_id = :pid";
            $params[':pid'] = $paciente_id;
        }

        $sql .= " ORDER BY i.fecha_ingreso DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
