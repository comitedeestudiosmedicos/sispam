<?php
require_once __DIR__ . '/../config/database.php';

class Paciente {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getByDocumento($tipo_doc, $num_doc) {
        $rawDoc = trim($num_doc);
        $cleanDoc = preg_replace('/[^\w\-]/', '', $rawDoc);
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
            'estado_civil'                  => $data['estado_civil'] ?? 'Soltero',
            'grupo_sanguineo'               => $data['grupo_sanguineo'] ?? 'O+',
            'sede_atencion'                 => $data['sede_atencion'] ?? '01',
            'contacto_emergencia_nombre'    => $data['contacto_emergencia_nombre'] ?? null,
            'contacto_emergencia_telefono'  => $data['contacto_emergencia_telefono'] ?? null,
            'contacto_emergencia_parentesco'=> $data['contacto_emergencia_parentesco'] ?? null,
            'grupo_poblacional'             => $data['grupo_poblacional'] ?? '5',
            'grupo_etnico'                  => $data['grupo_etnico'] ?? 'S',
            'comunidad_etnica'              => $data['comunidad_etnica'] ?? null,
            'tipo_discapacidad'             => $data['tipo_discapacidad'] ?? 'N',
            'tipo_escolaridad'              => $data['tipo_escolaridad'] ?? '13',
            'direccion_residencia'          => $data['direccion_residencia'] ?? null,
            'indicativo_1'                  => $data['indicativo_1'] ?? '+57',
            'numero_celular'                => $data['numero_celular'] ?? ($data['telefono'] ?? null),
            'indicativo_2'                  => $data['indicativo_2'] ?? '+57',
            'otro_telefono'                 => $data['otro_telefono'] ?? null,
            'telefono'                      => $data['numero_celular'] ?? ($data['telefono'] ?? null),
            'email'                         => $data['email'] ?? null,
            'ciudad_residencia'             => $data['ciudad_residencia'] ?? '05001',
            'zona'                          => $data['zona'] ?? 'U',
            'barrio'                        => $data['barrio'] ?? 'B080',
            'direccion_laboral'             => $data['direccion_laboral'] ?? null,
            'telefono_laboral'              => $data['telefono_laboral'] ?? null,
            'ocupacion'                     => $data['ocupacion'] ?? '0000',
            'tipo_afiliado'                 => $data['tipo_afiliado'] ?? '01',
            'eps_nombre'                    => $data['eps_nombre'] ?? 'EPS040',
            'plan_salud'                    => $data['plan_salud'] ?? 'Plan Básico',
            'actualiza_citas_plan'          => !empty($data['actualiza_citas_plan']) ? 1 : 0,
            'nivel_socioeconomico'          => $data['nivel_socioeconomico'] ?? '1',
            'estrato_socioeconomico'        => intval($data['estrato_socioeconomico'] ?? 3),
            'fecha_sgsss'                   => !empty($data['fecha_sgsss']) ? $data['fecha_sgsss'] : null,
            'fecha_afiliacion'              => !empty($data['fecha_afiliacion']) ? $data['fecha_afiliacion'] : null,
            'municipio_afiliacion'          => $data['municipio_afiliacion'] ?? '05001',
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

    public function buscarPorDocumento($num_doc) {
        $stmt = $this->db->prepare("SELECT * FROM pacientes WHERE numero_documento = :num_doc LIMIT 1");
        $stmt->execute([':num_doc' => trim($num_doc)]);
        return $stmt->fetch();
    }

    /**
     * Inserción y actualización masiva ultra-rápida (Bulk Upsert) por lotes
     * Optimizado para procesar 10.000 a 100.000+ registros en segundos.
     */
    public function importarLoteMasivo(array $filas) {
        if (empty($filas)) {
            return ['procesados' => 0, 'insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        }

        // Obtener columnas reales de la tabla
        $stmtCols = $this->db->query("SHOW COLUMNS FROM pacientes");
        $colsExistentes = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

        $colsPermitidas = [
            'tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'primer_apellido',
            'segundo_apellido', 'primer_nombre', 'segundo_nombre', 'fecha_nacimiento', 'sexo',
            'identidad_genero', 'estado_civil', 'grupo_sanguineo', 'sede_atencion', 'ciudad_expedicion',
            'pais_nacimiento', 'nacionalidad', 'ciudad_nacimiento', 'direccion_residencia',
            'indicativo_1', 'numero_celular', 'indicativo_2', 'otro_telefono', 'telefono',
            'email', 'ciudad_residencia', 'zona', 'barrio', 'direccion_laboral', 'telefono_laboral',
            'ocupacion', 'tipo_afiliado', 'eps_nombre', 'plan_salud', 'actualiza_citas_plan',
            'nivel_socioeconomico', 'estrato_socioeconomico', 'fecha_sgsss', 'fecha_afiliacion',
            'municipio_afiliacion', 'ips_primaria', 'ips_remite', 'empleador', 'grupo_poblacional',
            'grupo_etnico', 'comunidad_etnica', 'tipo_discapacidad', 'tipo_escolaridad',
            'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'contacto_emergencia_parentesco', 'estado'
        ];

        $colsUsadas = array_intersect($colsPermitidas, $colsExistentes);
        $colNamesList = implode('`, `', $colsUsadas);

        // Cláusula ON DUPLICATE KEY UPDATE
        $updateList = [];
        foreach ($colsUsadas as $c) {
            if ($c !== 'tipo_documento' && $c !== 'numero_documento' && $c !== 'id' && $c !== 'created_at') {
                $updateList[] = "`{$c}` = VALUES(`{$c}`)";
            }
        }
        $onDuplicateSql = implode(', ', $updateList);

        $loteSize = 1000;
        $chunks = array_chunk($filas, $loteSize);
        $totalProcesados = 0;
        $totalOmitidos = 0;

        $this->db->beginTransaction();
        try {
            foreach ($chunks as $chunk) {
                $valuesSql = [];
                $params = [];
                $pIdx = 0;

                foreach ($chunk as $f) {
                    $numDoc = preg_replace('/[^\w\-]/', '', trim($f['numero_documento'] ?? ''));
                    $pNombre = trim($f['primer_nombre'] ?? ($f['nombres'] ?? ''));
                    $pApellido = trim($f['primer_apellido'] ?? ($f['apellidos'] ?? ''));

                    if (empty($numDoc) || empty($pNombre)) {
                        $totalOmitidos++;
                        continue;
                    }

                    $sNombre = trim($f['segundo_nombre'] ?? '');
                    $sApellido = trim($f['segundo_apellido'] ?? '');
                    $nombresComp = trim($pNombre . ' ' . $sNombre);
                    $apellidosComp = trim($pApellido . ' ' . $sApellido) ?: 'REGISTRADO';

                    // Tipos de Documento válidos (extrae código si viene con guion ej: 'CC - Cédula')
                    $tipoDoc = strtoupper(trim($f['tipo_documento'] ?? 'CC'));
                    if (strpos($tipoDoc, '-') !== false) {
                        $tipoDoc = trim(explode('-', $tipoDoc)[0]);
                    }
                    if ($tipoDoc === 'PEP') $tipoDoc = 'PE';
                    if ($tipoDoc === 'PPT') $tipoDoc = 'PT';
                    $codigosValidos = defined('TIPOS_DOCUMENTO') ? array_keys(TIPOS_DOCUMENTO) : ['AS','CC','CD','CE','CN','MS','NIT','NV','PA','PE','PT','RC','SC','SI','TI'];
                    if (!in_array($tipoDoc, $codigosValidos)) {
                        $tipoDoc = 'CC';
                    }

                    // Sexo válido (Masculino, Femenino, Indeterminado)
                    $sexoRaw = trim($f['sexo'] ?? 'Masculino');
                    if (stripos($sexoRaw, 'Indeterminado') !== false || stripos($sexoRaw, 'Intersexual') !== false || strtoupper($sexoRaw) === 'I') {
                        $sexo = 'Indeterminado';
                    } elseif (stripos($sexoRaw, 'F') === 0 || strtoupper($sexoRaw) === 'MUJER') {
                        $sexo = 'Femenino';
                    } else {
                        $sexo = 'Masculino';
                    }

                    // Fecha de nacimiento válida
                    $fechaNac = null;
                    if (!empty($f['fecha_nacimiento'])) {
                        $ts = strtotime($f['fecha_nacimiento']);
                        if ($ts !== false) {
                            $fechaNac = date('Y-m-d', $ts);
                        }
                    }

                    $rowPlaceholders = [];
                    foreach ($colsUsadas as $col) {
                        $val = null;
                        switch ($col) {
                            case 'tipo_documento': $val = $tipoDoc; break;
                            case 'numero_documento': $val = $numDoc; break;
                            case 'nombres': $val = $nombresComp; break;
                            case 'apellidos': $val = $apellidosComp; break;
                            case 'primer_nombre': $val = $pNombre ?: $nombresComp; break;
                            case 'segundo_nombre': $val = $sNombre; break;
                            case 'primer_apellido': $val = $pApellido ?: $apellidosComp; break;
                            case 'segundo_apellido': $val = $sApellido; break;
                            case 'fecha_nacimiento': $val = $fechaNac; break;
                            case 'sexo': $val = $sexo; break;
                            case 'eps_nombre': 
                                $epsRaw = trim($f['eps_nombre'] ?? '');
                                if (isset(EPS_COLOMBIA[$epsRaw])) {
                                    $val = $epsRaw;
                                } else {
                                    $cleanCode = strpos($epsRaw, '-') !== false ? trim(explode('-', $epsRaw)[0]) : $epsRaw;
                                    if (isset(EPS_COLOMBIA[$cleanCode])) {
                                        $val = $cleanCode;
                                    } else {
                                        $found = null;
                                        foreach (EPS_COLOMBIA as $k => $lbl) {
                                            if (stripos($lbl, $epsRaw) !== false || stripos($epsRaw, $k) !== false) {
                                                $found = $k;
                                                break;
                                            }
                                        }
                                        $val = $found ?: ($epsRaw ?: 'EPS040');
                                    }
                                }
                                break;
                            case 'estado': $val = trim($f['estado'] ?? '') ?: 'Activo'; break;
                            case 'ciudad_expedicion': $val = trim($f['ciudad_expedicion'] ?? '') ?: 'MEDELLIN-ANT-05001'; break;
                            case 'ciudad_residencia': 
                                $crRaw = trim($f['ciudad_residencia'] ?? '');
                                if (strpos($crRaw, '-') !== false) {
                                    $parts = explode('-', $crRaw);
                                    $val = trim(end($parts));
                                } else {
                                    $val = $crRaw ?: '05001';
                                }
                                break;
                            case 'pais_nacimiento': $val = trim($f['pais_nacimiento'] ?? '') ?: 'COLOMBIA'; break;
                            case 'nacionalidad': $val = trim($f['nacionalidad'] ?? '') ?: 'COLOMBIANA'; break;
                            case 'ciudad_nacimiento': 
                                $cnRaw = trim($f['ciudad_nacimiento'] ?? '');
                                if (strpos($cnRaw, '-') !== false) {
                                    $parts = explode('-', $cnRaw);
                                    $val = trim(end($parts));
                                } else {
                                    $val = $cnRaw ?: '05001';
                                }
                                break;
                            case 'estado_civil': 
                                $ecRaw = trim($f['estado_civil'] ?? '');
                                if (stripos($ecRaw, 'Solter') !== false) {
                                    $val = 'Soltero';
                                } elseif (stripos($ecRaw, 'Casad') !== false) {
                                    $val = 'Casado';
                                } elseif (stripos($ecRaw, 'Uni') !== false || stripos($ecRaw, 'Libre') !== false) {
                                    $val = 'Unión Libre';
                                } elseif (stripos($ecRaw, 'Separad') !== false) {
                                    $val = 'Separado';
                                } elseif (stripos($ecRaw, 'Divorciad') !== false) {
                                    $val = 'Divorciado';
                                } elseif (stripos($ecRaw, 'Viud') !== false) {
                                    $val = 'Viudo';
                                } elseif ($ecRaw === 'S/N' || $ecRaw === 'SN') {
                                    $val = 'S/N';
                                } else {
                                    $val = $ecRaw ?: 'Soltero';
                                }
                                break;
                            case 'grupo_sanguineo': 
                                $gsRaw = trim($f['grupo_sanguineo'] ?? '');
                                if (stripos($gsRaw, 'NoSab') !== false) {
                                    $val = 'NoSab';
                                } elseif ($gsRaw === 'S/N' || $gsRaw === 'SN') {
                                    $val = 'S/N';
                                } else {
                                    $val = $gsRaw ?: 'O+';
                                }
                                break;
                            case 'sede_atencion': 
                                $saRaw = trim($f['sede_atencion'] ?? '');
                                if (strpos($saRaw, '-') !== false) {
                                    $codeSa = trim(explode('-', $saRaw)[0]);
                                    $val = str_pad($codeSa, 2, '0', STR_PAD_LEFT);
                                } elseif (is_numeric($saRaw)) {
                                    $val = str_pad($saRaw, 2, '0', STR_PAD_LEFT);
                                } elseif (isset(SEDES_ATENCION[$saRaw])) {
                                    $val = $saRaw;
                                } else {
                                    $found = null;
                                    foreach (SEDES_ATENCION as $k => $lbl) {
                                        if (stripos($lbl, $saRaw) !== false || stripos($saRaw, $lbl) !== false) {
                                            $found = $k;
                                            break;
                                        }
                                    }
                                    $val = $found ?: '01';
                                }
                                break;
                            case 'zona': 
                                $zRaw = trim($f['zona'] ?? '');
                                if (strpos($zRaw, '-') !== false) {
                                    $val = strtoupper(trim(explode('-', $zRaw)[0]));
                                } elseif (in_array(strtoupper($zRaw), ['U', 'R', 'S/N'])) {
                                    $val = strtoupper($zRaw);
                                } elseif (stripos($zRaw, 'Rur') !== false) {
                                    $val = 'R';
                                } elseif (stripos($zRaw, 'Urb') !== false) {
                                    $val = 'U';
                                } elseif ($zRaw === 'S/N' || $zRaw === 'SN') {
                                    $val = 'S/N';
                                } else {
                                    $val = 'U';
                                }
                                break;
                            case 'barrio': 
                                $bRaw = trim($f['barrio'] ?? '');
                                if (isset(BARRIOS_MEDELLIN[$bRaw])) {
                                    $val = $bRaw;
                                } else {
                                    $flipped = array_change_key_case(array_flip(BARRIOS_MEDELLIN), CASE_UPPER);
                                    $upperName = strtoupper($bRaw);
                                    if (isset($flipped[$upperName])) {
                                        $val = $flipped[$upperName];
                                    } else {
                                        $val = $bRaw ?: 'B080';
                                    }
                                }
                                break;
                            case 'tipo_afiliado': 
                                $taRaw = trim($f['tipo_afiliado'] ?? '');
                                if (strpos($taRaw, '-') !== false) {
                                    $codeTa = trim(explode('-', $taRaw)[0]);
                                    $val = str_pad($codeTa, 2, '0', STR_PAD_LEFT);
                                } elseif (is_numeric($taRaw)) {
                                    $val = str_pad($taRaw, 2, '0', STR_PAD_LEFT);
                                } elseif (stripos($taRaw, 'Beneficiario') !== false) {
                                    $val = (stripos($taRaw, 'Especial') !== false || stripos($taRaw, 'Excepci') !== false) ? '07' : '02';
                                } elseif (stripos($taRaw, 'Adicional') !== false) {
                                    $val = '03';
                                } elseif (stripos($taRaw, 'Subsidiado') !== false) {
                                    $val = '04';
                                } elseif (stripos($taRaw, 'No afiliado') !== false || stripos($taRaw, 'Vinculado') !== false) {
                                    $val = '05';
                                } elseif (stripos($taRaw, 'Especial') !== false || stripos($taRaw, 'Excepci') !== false) {
                                    $val = '06';
                                } elseif (stripos($taRaw, 'Libertad') !== false || stripos($taRaw, 'Fondo') !== false) {
                                    $val = '08';
                                } elseif (stripos($taRaw, 'ARL') !== false) {
                                    $val = '09';
                                } elseif (stripos($taRaw, 'SOAT') !== false) {
                                    $val = '10';
                                } elseif (stripos($taRaw, 'Voluntario') !== false) {
                                    $val = '11';
                                } elseif (stripos($taRaw, 'Particular') !== false) {
                                    $val = '12';
                                } else {
                                    $val = '01';
                                }
                                break;
                            case 'plan_salud': $val = trim($f['plan_salud'] ?? '') ?: 'Plan Básico'; break;
                            case 'nivel_socioeconomico': 
                                $nsRaw = trim($f['nivel_socioeconomico'] ?? '');
                                if (strpos($nsRaw, '-') !== false) {
                                    $val = trim(explode('-', $nsRaw)[0]);
                                } elseif (in_array($nsRaw, ['1', '2', '3'])) {
                                    $val = $nsRaw;
                                } elseif (stripos($nsRaw, 'B') !== false || $nsRaw === '2') {
                                    $val = '2';
                                } elseif (stripos($nsRaw, 'C') !== false || $nsRaw === '3') {
                                    $val = '3';
                                } else {
                                    $val = '1';
                                }
                                break;
                            case 'estrato_socioeconomico': 
                                $esRaw = trim($f['estrato_socioeconomico'] ?? '');
                                $num = intval(preg_replace('/[^\d]/', '', $esRaw));
                                $val = ($num >= 1 && $num <= 6) ? $num : 3;
                                break;
                            case 'ips_primaria': $val = trim($f['ips_primaria'] ?? '') ?: '900294794 - COMITE DE ESTUDIOS MEDICOS SAS'; break;
                            case 'ips_remite': $val = trim($f['ips_remite'] ?? '') ?: null; break;
                            case 'grupo_poblacional': 
                                $gpRaw = trim($f['grupo_poblacional'] ?? '');
                                if (strpos($gpRaw, '-') !== false) {
                                    $val = trim(explode('-', $gpRaw)[0]);
                                } elseif (isset(GRUPOS_POBLACIONALES[$gpRaw])) {
                                    $val = $gpRaw;
                                } else {
                                    $found = null;
                                    foreach (GRUPOS_POBLACIONALES as $k => $lbl) {
                                        if (stripos($lbl, $gpRaw) !== false || stripos($gpRaw, $lbl) !== false) {
                                            $found = $k;
                                            break;
                                        }
                                    }
                                    $val = $found ?: '5';
                                }
                                break;
                            case 'grupo_etnico': 
                                $geRaw = trim($f['grupo_etnico'] ?? '');
                                if (strpos($geRaw, '-') !== false) {
                                    $val = strtoupper(trim(explode('-', $geRaw)[0]));
                                } elseif (in_array(strtoupper($geRaw), ['A', 'I', 'G', 'R', 'P', 'N', 'S'])) {
                                    $val = strtoupper($geRaw);
                                } elseif (stripos($geRaw, 'Afro') !== false) {
                                    $val = 'A';
                                } elseif (stripos($geRaw, 'Ind') !== false) {
                                    $val = 'I';
                                } elseif (stripos($geRaw, 'Git') !== false) {
                                    $val = 'G';
                                } elseif (stripos($geRaw, 'Raiz') !== false) {
                                    $val = 'R';
                                } elseif (stripos($geRaw, 'Palen') !== false) {
                                    $val = 'P';
                                } elseif (stripos($geRaw, 'No Aplica') !== false) {
                                    $val = 'N';
                                } else {
                                    $val = 'S';
                                }
                                break;
                            case 'tipo_discapacidad': 
                                $tdRaw = trim($f['tipo_discapacidad'] ?? '');
                                if (strpos($tdRaw, '-') !== false) {
                                    $val = strtoupper(trim(explode('-', $tdRaw)[0]));
                                } elseif (in_array(strtoupper($tdRaw), ['A', 'C', 'F', 'M', 'N', 'S', 'V'])) {
                                    $val = strtoupper($tdRaw);
                                } elseif (stripos($tdRaw, 'Audit') !== false) {
                                    $val = 'A';
                                } elseif (stripos($tdRaw, 'Sordo') !== false) {
                                    $val = 'C';
                                } elseif (stripos($tdRaw, 'Fís') !== false || stripos($tdRaw, 'Fis') !== false) {
                                    $val = 'F';
                                } elseif (stripos($tdRaw, 'Ment') !== false) {
                                    $val = 'M';
                                } elseif (stripos($tdRaw, 'Psic') !== false) {
                                    $val = 'S';
                                } elseif (stripos($tdRaw, 'Vis') !== false) {
                                    $val = 'V';
                                } else {
                                    $val = 'N';
                                }
                                break;
                            case 'tipo_escolaridad': 
                                $teRaw = trim($f['tipo_escolaridad'] ?? '');
                                if (strpos($teRaw, '-') !== false) {
                                    $codeTe = trim(explode('-', $teRaw)[0]);
                                    $val = str_pad($codeTe, 2, '0', STR_PAD_LEFT);
                                } elseif (is_numeric($teRaw)) {
                                    $val = str_pad($teRaw, 2, '0', STR_PAD_LEFT);
                                } elseif (stripos($teRaw, 'Preescolar') !== false) {
                                    $val = '01';
                                } elseif (stripos($teRaw, 'Primaria') !== false) {
                                    $val = '02';
                                } elseif (stripos($teRaw, 'Secundaria') !== false) {
                                    $val = '03';
                                } elseif (stripos($teRaw, 'Media') !== false || stripos($teRaw, 'Academ') !== false) {
                                    $val = '04';
                                } elseif (stripos($teRaw, 'Tecnica') !== false || stripos($teRaw, 'Bachillerato') !== false) {
                                    $val = '05';
                                } elseif (stripos($teRaw, 'Normalista') !== false) {
                                    $val = '06';
                                } elseif (stripos($teRaw, 'Tecnologica') !== false) {
                                    $val = '08';
                                } elseif (stripos($teRaw, 'Profesional') !== false || stripos($teRaw, 'Universit') !== false) {
                                    $val = '09';
                                } elseif (stripos($teRaw, 'Especializ') !== false) {
                                    $val = '10';
                                } elseif (stripos($teRaw, 'Maest') !== false) {
                                    $val = '11';
                                } elseif (stripos($teRaw, 'Doctor') !== false) {
                                    $val = '12';
                                } else {
                                    $val = '13';
                                }
                                break;
                            case 'ocupacion': 
                                $ocRaw = trim($f['ocupacion'] ?? '');
                                if (strpos($ocRaw, '-') !== false) {
                                    $ocRaw = trim(explode('-', $ocRaw)[0]);
                                }
                                $val = $ocRaw ?: '0000'; 
                                break;
                            case 'direccion_residencia': $val = trim($f['direccion_residencia'] ?? '') ?: null; break;
                            case 'indicativo_1': $val = trim($f['indicativo_1'] ?? '') ?: '+57'; break;
                            case 'numero_celular': 
                            case 'telefono':
                                $val = trim($f['numero_celular'] ?? ($f['telefono'] ?? '')) ?: null;
                                break;
                            case 'email': $val = trim($f['email'] ?? '') ?: null; break;
                            default:
                                $val = isset($f[$col]) && trim($f[$col]) !== '' ? trim($f[$col]) : null;
                                break;
                        }

                        $pName = ":p{$pIdx}";
                        $rowPlaceholders[] = $pName;
                        $params[$pName] = $val;
                        $pIdx++;
                    }

                    $valuesSql[] = "(" . implode(', ', $rowPlaceholders) . ")";
                    $totalProcesados++;
                }

                if (!empty($valuesSql)) {
                    $sql = "INSERT INTO pacientes (`{$colNamesList}`) VALUES " . implode(', ', $valuesSql) . " ON DUPLICATE KEY UPDATE {$onDuplicateSql}";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'procesados' => $totalProcesados,
            'omitidos'   => $totalOmitidos
        ];
    }

    /**
     * Consulta paginada y filtrada de alto rendimiento para +68.000 registros
     */
    public function listarPaginado(array $filtros = [], int $pagina = 1, int $porPagina = 25) {
        $pagina = max(1, $pagina);
        $porPagina = max(5, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $where = [];
        $params = [];

        // 1. Búsqueda global por documento, nombres, apellidos, teléfono o email
        if (!empty($filtros['busqueda'])) {
            $q = trim($filtros['busqueda']);
            $where[] = "(
                numero_documento LIKE :q_num OR 
                primer_nombre LIKE :q_pnom OR 
                segundo_nombre LIKE :q_snom OR 
                primer_apellido LIKE :q_pape OR 
                segundo_apellido LIKE :q_sape OR 
                nombres LIKE :q_noms OR 
                apellidos LIKE :q_apes OR 
                numero_celular LIKE :q_cel OR 
                telefono LIKE :q_tel OR 
                email LIKE :q_mail
            )";
            $qWild = "%{$q}%";
            $params[':q_num']  = $qWild;
            $params[':q_pnom'] = $qWild;
            $params[':q_snom'] = $qWild;
            $params[':q_pape'] = $qWild;
            $params[':q_sape'] = $qWild;
            $params[':q_noms'] = $qWild;
            $params[':q_apes'] = $qWild;
            $params[':q_cel']  = $qWild;
            $params[':q_tel']  = $qWild;
            $params[':q_mail'] = $qWild;
        }

        // 2. Filtro por Tipo de Documento
        if (!empty($filtros['tipo_documento'])) {
            $where[] = "tipo_documento = :tipo_doc";
            $params[':tipo_doc'] = trim($filtros['tipo_documento']);
        }

        // 3. Filtro por Aseguradora / EPS
        if (!empty($filtros['eps_nombre'])) {
            $where[] = "(eps_nombre = :eps OR eps_nombre LIKE :eps_like)";
            $params[':eps'] = trim($filtros['eps_nombre']);
            $params[':eps_like'] = "%" . trim($filtros['eps_nombre']) . "%";
        }

        // 4. Filtro por Sede de Atención o Sede de Ingreso
        if (!empty($filtros['sede_atencion'])) {
            $sVal = trim($filtros['sede_atencion']);
            $where[] = "(
                p.sede_atencion = :sede_exact OR 
                p.sede_atencion LIKE :sede_like OR 
                p.id IN (
                    SELECT DISTINCT i_s.paciente_id 
                    FROM ingresos i_s 
                    LEFT JOIN sedes s_s ON i_s.sede_id = s_s.id 
                    WHERE s_s.nombre_sede = :sede_nom_exact OR s_s.nombre_sede LIKE :sede_nom_like OR i_s.sede_id = :sede_num_id
                )
            )";
            $params[':sede_exact'] = $sVal;
            $params[':sede_like'] = "%{$sVal}%";
            $params[':sede_nom_exact'] = $sVal;
            $params[':sede_nom_like'] = "%{$sVal}%";
            $params[':sede_num_id'] = is_numeric($sVal) ? intval($sVal) : 0;
        }

        // 5. Filtro por Sexo
        if (!empty($filtros['sexo'])) {
            $where[] = "p.sexo = :sexo";
            $params[':sexo'] = trim($filtros['sexo']);
        }

        // 6. Filtro por Estado
        if (!empty($filtros['estado'])) {
            $where[] = "p.estado = :estado";
            $params[':estado'] = trim($filtros['estado']);
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Total de registros coincidentes
        $countSql = "SELECT COUNT(*) as total FROM pacientes p {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        // Registros de la página actual con nombre de sede de ingreso
        $sql = "SELECT p.*,
                       COALESCE(
                           (SELECT s.nombre_sede FROM ingresos i JOIN sedes s ON i.sede_id = s.id WHERE i.paciente_id = p.id ORDER BY i.id DESC LIMIT 1),
                           p.sede_atencion
                       ) as ultima_sede_ingreso
                FROM pacientes p 
                {$whereSql} 
                ORDER BY p.id DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = $total > 0 ? ceil($total / $porPagina) : 1;
        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $porPagina, $total);

        return [
            'data' => $data,
            'pagination' => [
                'total'        => $total,
                'page'         => $pagina,
                'per_page'     => $porPagina,
                'total_pages'  => $totalPages,
                'from'         => $from,
                'to'           => $to
            ]
        ];
    }

    /**
     * Métricas estadísticas del módulo de pacientes
     */
    public function getEstadisticas() {
        try {
            $totales = $this->db->query("SELECT 
                COUNT(*) as total_pacientes,
                SUM(CASE WHEN estado = 'Activo' OR estado IS NULL THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as nuevos_mes
            FROM pacientes")->fetch(PDO::FETCH_ASSOC);

            $topEps = $this->db->query("SELECT eps_nombre, COUNT(*) as cantidad 
                FROM pacientes 
                WHERE eps_nombre IS NOT NULL AND eps_nombre != '' 
                GROUP BY eps_nombre 
                ORDER BY cantidad DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

            $conIngresos = $this->db->query("SELECT COUNT(DISTINCT paciente_id) as total FROM ingresos")->fetchColumn();

            return [
                'total'        => intval($totales['total_pacientes'] ?? 0),
                'activos'      => intval($totales['activos'] ?? 0),
                'nuevos_mes'   => intval($totales['nuevos_mes'] ?? 0),
                'top_eps'      => $topEps['eps_nombre'] ?? 'Sin EPS',
                'top_eps_cant' => intval($topEps['cantidad'] ?? 0),
                'con_ingresos' => intval($conIngresos ?? 0)
            ];
        } catch (Exception $e) {
            return [
                'total' => 0, 'activos' => 0, 'nuevos_mes' => 0,
                'top_eps' => 'N/A', 'top_eps_cant' => 0, 'con_ingresos' => 0
            ];
        }
    }

    /**
     * Actualización puntual y completa de un paciente con traza de auditoría
     */
    public function actualizarDatosPaciente(int $id, array $data) {
        $actual = $this->getById($id);
        if (!$actual) {
            throw new Exception("El paciente con ID #{$id} no existe en la base de datos.");
        }

        // Verificar si se intenta cambiar el número de documento a uno ya existente
        if (!empty($data['numero_documento']) && $data['numero_documento'] !== $actual['numero_documento']) {
            $tipoDoc = $data['tipo_documento'] ?? $actual['tipo_documento'];
            $duplicado = $this->getByDocumento($tipoDoc, $data['numero_documento']);
            if ($duplicado && intval($duplicado['id']) !== $id) {
                throw new Exception("Ya existe otro paciente registrado con el documento {$tipoDoc} - {$data['numero_documento']}.");
            }
        }

        // Ensamblar nombres y apellidos
        $pNom = trim($data['primer_nombre'] ?? ($actual['primer_nombre'] ?? ''));
        $sNom = trim($data['segundo_nombre'] ?? ($actual['segundo_nombre'] ?? ''));
        $pApe = trim($data['primer_apellido'] ?? ($actual['primer_apellido'] ?? ''));
        $sApe = trim($data['segundo_apellido'] ?? ($actual['segundo_apellido'] ?? ''));

        $nombres = trim("{$pNom} {$sNom}") ?: trim($data['nombres'] ?? ($actual['nombres'] ?? ''));
        $apellidos = trim("{$pApe} {$sApe}") ?: trim($data['apellidos'] ?? ($actual['apellidos'] ?? ''));

        $fields = [
            'tipo_documento'                => trim($data['tipo_documento'] ?? $actual['tipo_documento']),
            'numero_documento'              => trim($data['numero_documento'] ?? $actual['numero_documento']),
            'primer_nombre'                 => $pNom,
            'segundo_nombre'                => $sNom,
            'primer_apellido'               => $pApe,
            'segundo_apellido'              => $sApe,
            'nombres'                       => $nombres,
            'apellidos'                     => $apellidos,
            'fecha_nacimiento'              => !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null,
            'sexo'                          => $data['sexo'] ?? $actual['sexo'] ?? 'Masculino',
            'identidad_genero'              => $data['identidad_genero'] ?? null,
            'estado_civil'                  => $data['estado_civil'] ?? $actual['estado_civil'] ?? 'Soltero',
            'grupo_sanguineo'               => $data['grupo_sanguineo'] ?? $actual['grupo_sanguineo'] ?? 'O+',
            'pais_nacimiento'               => $data['pais_nacimiento'] ?? 'COLOMBIA',
            'nacionalidad'                  => $data['nacionalidad'] ?? 'COLOMBIANA',
            'ciudad_nacimiento'             => $data['ciudad_nacimiento'] ?? $actual['ciudad_nacimiento'] ?? '05001',
            'ciudad_expedicion'             => $data['ciudad_expedicion'] ?? $actual['ciudad_expedicion'] ?? '05001',
            'direccion_residencia'          => $data['direccion_residencia'] ?? null,
            'indicativo_1'                  => $data['indicativo_1'] ?? '+57',
            'numero_celular'                => $data['numero_celular'] ?? ($data['telefono'] ?? null),
            'indicativo_2'                  => $data['indicativo_2'] ?? '+57',
            'otro_telefono'                 => $data['otro_telefono'] ?? null,
            'telefono'                      => $data['numero_celular'] ?? ($data['telefono'] ?? null),
            'email'                         => $data['email'] ?? null,
            'ciudad_residencia'             => $data['ciudad_residencia'] ?? $actual['ciudad_residencia'] ?? '05001',
            'zona'                          => $data['zona'] ?? $actual['zona'] ?? 'U',
            'barrio'                        => $data['barrio'] ?? $actual['barrio'] ?? 'B080',
            'direccion_laboral'             => $data['direccion_laboral'] ?? null,
            'telefono_laboral'              => $data['telefono_laboral'] ?? null,
            'ocupacion'                     => $data['ocupacion'] ?? $actual['ocupacion'] ?? '0000',
            'eps_nombre'                    => $data['eps_nombre'] ?? $actual['eps_nombre'] ?? 'EPS040',
            'plan_salud'                    => $data['plan_salud'] ?? 'Plan Básico',
            'actualiza_citas_plan'          => !empty($data['actualiza_citas_plan']) ? 1 : 0,
            'tipo_afiliado'                 => $data['tipo_afiliado'] ?? $actual['tipo_afiliado'] ?? '01',
            'nivel_socioeconomico'          => $data['nivel_socioeconomico'] ?? $actual['nivel_socioeconomico'] ?? '1',
            'estrato_socioeconomico'        => intval($data['estrato_socioeconomico'] ?? 3),
            'sede_atencion'                 => $data['sede_atencion'] ?? $actual['sede_atencion'] ?? '01',
            'ips_primaria'                  => $data['ips_primaria'] ?? '900294794 - COMITE DE ESTUDIOS MEDICOS SAS',
            'ips_remite'                    => $data['ips_remite'] ?? null,
            'grupo_poblacional'             => $data['grupo_poblacional'] ?? $actual['grupo_poblacional'] ?? '5',
            'grupo_etnico'                  => $data['grupo_etnico'] ?? $actual['grupo_etnico'] ?? 'S',
            'comunidad_etnica'              => $data['comunidad_etnica'] ?? null,
            'tipo_discapacidad'             => $data['tipo_discapacidad'] ?? $actual['tipo_discapacidad'] ?? 'N',
            'tipo_escolaridad'              => $data['tipo_escolaridad'] ?? $actual['tipo_escolaridad'] ?? '13',
            'contacto_emergencia_nombre'    => $data['contacto_emergencia_nombre'] ?? null,
            'contacto_emergencia_telefono'  => $data['contacto_emergencia_telefono'] ?? null,
            'contacto_emergencia_parentesco'=> $data['contacto_emergencia_parentesco'] ?? null,
            'estado'                        => $data['estado'] ?? $actual['estado'] ?? 'Activo',
            'updated_at'                    => date('Y-m-d H:i:s')
        ];

        // Validar columnas existentes
        $stmtCols = $this->db->query("SHOW COLUMNS FROM pacientes");
        $colsExistentes = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

        $setParts = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            if (in_array($col, $colsExistentes)) {
                $setParts[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $val;
            }
        }

        $sql = "UPDATE pacientes SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute($params);

        if ($res && function_exists('registrar_log_auditoria')) {
            $nombreComp = "{$nombres} {$apellidos}";
            $doc = "{$fields['tipo_documento']} {$fields['numero_documento']}";
            registrar_log_auditoria('PACIENTES', 'ACTUALIZAR', $id, "Actualización de datos del paciente: {$nombreComp} ({$doc})");
        }

        return $res;
    }

    /**
     * Creación directa de paciente desde el módulo de administración
     */
    public function crearPacienteDirecto(array $data) {
        $pNom = trim($data['primer_nombre'] ?? '');
        $sNom = trim($data['segundo_nombre'] ?? '');
        $pApe = trim($data['primer_apellido'] ?? '');
        $sApe = trim($data['segundo_apellido'] ?? '');

        if (empty($data['tipo_documento']) || empty($data['numero_documento']) || empty($pNom) || empty($pApe)) {
            throw new Exception("El tipo y número de documento, primer nombre y primer apellido son obligatorios.");
        }

        $existente = $this->getByDocumento($data['tipo_documento'], $data['numero_documento']);
        if ($existente) {
            throw new Exception("Ya existe un paciente registrado con el documento {$data['tipo_documento']} - {$data['numero_documento']}.");
        }

        $newId = $this->createOrUpdate($data);

        if ($newId && function_exists('registrar_log_auditoria')) {
            $nombreComp = trim("{$pNom} {$sNom} {$pApe} {$sApe}");
            $doc = "{$data['tipo_documento']} {$data['numero_documento']}";
            registrar_log_auditoria('PACIENTES', 'CREAR', $newId, "Creación manual de paciente: {$nombreComp} ({$doc})");
        }

        return $newId;
    }

    /**
     * Historial de trámites / tickets de un paciente
     */
    public function getHistorialIngresos(int $pacienteId) {
        $stmt = $this->db->prepare("
            SELECT i.id, i.ticket_numero, i.estado_tramite, i.fecha_ingreso, i.prioridad, i.persona_reclama,
                   s.nombre_sede as sede_nombre,
                   u.nombre_completo as usuario_ingreso,
                   COUNT(d.id) as total_documentos
            FROM ingresos i
            LEFT JOIN sedes s ON i.sede_id = s.id
            LEFT JOIN usuarios u ON i.orientador_id = u.id
            LEFT JOIN ingreso_documentos d ON i.id = d.ingreso_id
            WHERE i.paciente_id = :pid
            GROUP BY i.id
            ORDER BY i.id DESC
            LIMIT 20
        ");
        $stmt->execute([':pid' => $pacienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * /////////////////////// IMPORTANTE PARA LA API QRYSTALOS ////////////////////////
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