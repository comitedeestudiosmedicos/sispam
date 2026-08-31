<?php
require_once __DIR__ . '/../config/database.php';

class Paciente {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getByDocumento($tipo_doc, $num_doc) {
        $rawDoc = trim($num_doc);
        $cleanDoc = preg_replace('/[^\\w\\-]/', '', $rawDoc);
        $cleanTipo = strtoupper(trim(explode('-', $tipo_doc)[0]));

        // 1. Búsqueda prioritaria por tipo y documento exacto o normalizado
        $stmt = $this->db->prepare("
            SELECT * FROM pacientes 
            WHERE (tipo_documento = :tipo_doc OR TRIM(tipo_documento) = :clean_tipo)
              AND (numero_documento = :num_doc1 OR TRIM(numero_documento) = :num_doc2 OR REPLACE(REPLACE(numero_documento, '.', ''), ' ', '') = :clean_doc)
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([
            ':tipo_doc'   => $tipo_doc,
            ':clean_tipo' => $cleanTipo,
            ':num_doc1'   => $rawDoc,
            ':num_doc2'   => $rawDoc,
            ':clean_doc'  => $cleanDoc
        ]);
        $res = $stmt->fetch();

        // 2. Si no coincide el tipo, buscar solo por número de documento como fallback
        if (!$res) {
            $stmtFallback = $this->db->prepare("
                SELECT * FROM pacientes 
                WHERE numero_documento = :fb_doc1 OR TRIM(numero_documento) = :fb_doc2 OR REPLACE(REPLACE(numero_documento, '.', ''), ' ', '') = :fb_clean_doc
                ORDER BY id DESC LIMIT 1
            ");
            $stmtFallback->execute([
                ':fb_doc1'      => $rawDoc,
                ':fb_doc2'      => $rawDoc,
                ':fb_clean_doc' => $cleanDoc
            ]);
            $res = $stmtFallback->fetch();
        }

        return $res;
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM pacientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function createOrUpdate($data) {
        $existente = $this->getByDocumento($data['tipo_documento'], $data['numero_documento']);

        // Ensamblar nombres y apellidos completos si vienen por partes
        $primer_nombre   = trim($data['primer_nombre'] ?? '');
        $segundo_nombre  = trim($data['segundo_nombre'] ?? '');
        $primer_apellido = trim($data['primer_apellido'] ?? '');
        $segundo_apellido= trim($data['segundo_apellido'] ?? '');

        $nombres = trim(($primer_nombre . ' ' . $segundo_nombre)) ?: trim($data['nombres'] ?? '');
        $apellidos = trim(($primer_apellido . ' ' . $segundo_apellido)) ?: trim($data['apellidos'] ?? '');

        $fields = [
            'tipo_documento'                => $data['tipo_documento'],
            'numero_documento'              => $data['numero_documento'],
            'nombres'                       => $nombres,
            'apellidos'                     => $apellidos,
            'fecha_nacimiento'              => !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null,
            'ciudad_expedicion'             => $data['ciudad_expedicion'] ?? 'MEDELLIN-ANT-05001',
            'estado'                        => $data['estado'] ?? 'Activo',
            'primer_apellido'               => $primer_apellido ?: $apellidos,
            'segundo_apellido'              => $segundo_apellido,
            'primer_nombre'                 => $primer_nombre ?: $nombres,
            'segundo_nombre'                => $segundo_nombre,
            'pais_nacimiento'               => $data['pais_nacimiento'] ?? 'COLOMBIA',
            'nacionalidad'                  => $data['nacionalidad'] ?? 'COLOMBIANA',
            'ciudad_nacimiento'             => $data['ciudad_nacimiento'] ?? '05001',
            'sexo'                          => $data['sexo'] ?? 'Masculino',
            'identidad_genero'              => $data['identidad_genero'] ?? null,
            'estado_civil'                  => $data['estado_civil'] ?? 'Soltero(a)',
            'grupo_sanguineo'               => $data['grupo_sanguineo'] ?? 'O+',
            'sede_atencion'                 => $data['sede_atencion'] ?? 'Sede Prado',
            'contacto_emergencia_nombre'    => $data['contacto_emergencia_nombre'] ?? null,
            'contacto_emergencia_telefono'  => $data['contacto_emergencia_telefono'] ?? null,
            'contacto_emergencia_parentesco'=> $data['contacto_emergencia_parentesco'] ?? null,
            'grupo_poblacional'             => $data['grupo_poblacional'] ?? 'Otro Grupo Poblacional',
            'grupo_etnico'                  => $data['grupo_etnico'] ?? 'S - S/N',
            'comunidad_etnica'              => $data['comunidad_etnica'] ?? null,
            'tipo_discapacidad'             => $data['tipo_discapacidad'] ?? 'No Aplica',
            'tipo_escolaridad'              => $data['tipo_escolaridad'] ?? 'NA',
            'direccion_residencia'          => $data['direccion_residencia'] ?? null,
            'indicativo_1'                  => $data['indicativo_1'] ?? '+57',
            'numero_celular'                => $data['numero_celular'] ?? ($data['telefono'] ?? null),
            'indicativo_2'                  => $data['indicativo_2'] ?? '+57',
            'otro_telefono'                 => $data['otro_telefono'] ?? null,
            'telefono'                      => $data['numero_celular'] ?? ($data['telefono'] ?? null),
            'email'                         => $data['email'] ?? null,
            'ciudad_residencia'             => $data['ciudad_residencia'] ?? 'MEDELLIN-ANT-05001',
            'zona'                          => $data['zona'] ?? 'Urbana',
            'barrio'                        => $data['barrio'] ?? 'El Poblado',
            'direccion_laboral'             => $data['direccion_laboral'] ?? null,
            'telefono_laboral'              => $data['telefono_laboral'] ?? null,
            'ocupacion'                     => $data['ocupacion'] ?? 'Empleado',
            'tipo_afiliado'                 => $data['tipo_afiliado'] ?? 'Contributivo Cotizante',
            'eps_nombre'                    => $data['eps_nombre'] ?? 'Particular / Sin EPS',
            'plan_salud'                    => $data['plan_salud'] ?? 'Plan Básico',
            'actualiza_citas_plan'          => !empty($data['actualiza_citas_plan']) ? 1 : 0,
            'nivel_socioeconomico'          => $data['nivel_socioeconomico'] ?? 'CATEGORIA A',
            'estrato_socioeconomico'        => intval($data['estrato_socioeconomico'] ?? 3),
            'fecha_sgsss'                   => !empty($data['fecha_sgsss']) ? $data['fecha_sgsss'] : null,
            'fecha_afiliacion'              => !empty($data['fecha_afiliacion']) ? $data['fecha_afiliacion'] : null,
            'municipio_afiliacion'          => $data['municipio_afiliacion'] ?? 'MEDELLIN-ANT-05001',
            'ips_primaria'                  => $data['ips_primaria'] ?? '900294794 - COMITE DE ESTUDIOS MEDICOS SAS',
            'ips_remite'                    => $data['ips_remite'] ?? null,
            'empleador'                     => $data['empleador'] ?? null
        ];

        // Obtener columnas reales de la tabla 'pacientes' en la BD
        $stmtCols = $this->db->query("SHOW COLUMNS FROM pacientes");
        $colsExistentes = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

        // Auto-crear columna si no existe en la base de datos
        foreach ($fields as $col => $val) {
            if (!in_array($col, $colsExistentes)) {
                try {
                    $this->db->exec("ALTER TABLE `pacientes` ADD COLUMN `{$col}` VARCHAR(255) NULL");
                    $colsExistentes[] = $col;
                } catch (Exception $e) {
                    // Silenciar si ya fue agregada por otro proceso
                }
            }
        }

        // Filtrar solo las columnas que existen en la BD
        $filteredFields = [];
        foreach ($fields as $col => $val) {
            if (in_array($col, $colsExistentes)) {
                $filteredFields[$col] = $val;
            }
        }

        if ($existente) {
            // Actualizar paciente existente
            $setParts = [];
            $params = [':id' => $existente['id']];
            foreach ($filteredFields as $col => $val) {
                if ($col === 'tipo_documento' || $col === 'numero_documento') continue;
                $setParts[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $val;
            }

            $sql = "UPDATE pacientes SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $existente['id'];
        } else {
            // Insertar paciente nuevo
            $cols = array_keys($filteredFields);
            $colNames = implode('`, `', $cols);
            $paramNames = ':' . implode(', :', $cols);

            $sql = "INSERT INTO pacientes (`{$colNames}`) VALUES ({$paramNames})";
            $stmt = $this->db->prepare($sql);

            $params = [];
            foreach ($filteredFields as $col => $val) {
                $params[":{$col}"] = $val;
            }
            $stmt->execute($params);
            return $this->db->lastInsertId();
        }
    }

    /**
     * ///////////////////////IMPORTANTE PARA LA API ////////////////////////
     * Actualiza únicamente el ID de Qrystalos después de una sincronización exitosa
     */
    public function actualizarQrystalosId($tipo_doc, $num_doc, $consecutivo) {
        // Verificar si la columna existe en la tabla de la BD, si no, crearla
        $stmtCols = $this->db->query("SHOW COLUMNS FROM pacientes LIKE 'qrystalos_consecutivo'");
        if ($stmtCols->rowCount() == 0) {
            try {
                $this->db->exec("ALTER TABLE `pacientes` ADD COLUMN `qrystalos_consecutivo` VARCHAR(255) NULL");
            } catch (Exception $e) {
                // Silenciar error si otra instancia la creó al mismo tiempo
            }
        }

        // Ejecutar el update local buscando al paciente mediante el buscador optimizado
        $paciente = $this->getByDocumento($tipo_doc, $num_doc);
        if ($paciente) {
            $stmt = $this->db->prepare("UPDATE pacientes SET qrystalos_consecutivo = :consecutivo WHERE id = :id");
            return $stmt->execute([
                ':consecutivo' => $consecutivo,
                ':id'          => $paciente['id']
            ]);
        }
        return false;
    }
}
?>