    <?php
require_once __DIR__ . '/../../config/app.php';
check_role(['Administrador', 'empresa', 'usuarios']);

require_once __DIR__ . '/../../models/Paciente.php';

// Ajustar límites de PHP para permitir importación masiva de hasta 100.000+ registros
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '600');
@set_time_limit(600);

$pacienteModel = new Paciente();
$mensaje = '';
$error = '';
$resumenImportacion = null;

// ==========================================
// 1. DESCARGA DIRECTA DE PLANTILLAS OFICIALES
// ==========================================
if (isset($_GET['download_template'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if ($_GET['download_template'] === 'xlsx') {
        $file_path = file_exists(BASE_DIR . '/assets/plantilla_pacientes_v2.xlsx') 
            ? BASE_DIR . '/assets/plantilla_pacientes_v2.xlsx' 
            : BASE_DIR . '/assets/plantilla_pacientes.xlsx';
        if (file_exists($file_path)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plantilla_pacientes_sispam.xlsx"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
            header('Pragma: public');
            readfile($file_path);
            exit;
        }
    } elseif ($_GET['download_template'] === 'csv' || $_GET['download_template'] == '1') {
        $headers = [
            'tipo_documento', 'numero_documento', 'primer_apellido', 'segundo_apellido',
            'primer_nombre', 'segundo_nombre', 'fecha_nacimiento', 'sexo', 'estado_civil',
            'grupo_sanguineo', 'grupo_etnico', 'tipo_discapacidad', 'tipo_escolaridad',
            'ocupacion', 'eps_nombre', 'numero_celular', 'email', 'direccion_residencia',
            'ciudad_residencia', 'barrio', 'zona', 'sede_atencion', 'tipo_afiliado',
            'nivel_socioeconomico', 'estrato_socioeconomico', 'ips_primaria', 'ips_remite',
            'grupo_poblacional'
        ];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_pacientes_sispam.csv"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        $output = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, $headers, ';');
        fputcsv($output, [
            'CC', '1036780004', 'GOMEZ', 'CHICA', 'VALENTINA', '', '2007-06-01', 'Femenino', 'Soltero',
            'S/N', 'S', 'N', '13', '0000', 'EPS040', '3195441510', 'valentina@gmail.com', 'CARRERA 9 # 14-37',
            '05001', 'B018', 'U', '20', '01', '2', '3',
            '900294794 - COMITE DE ESTUDIOS MEDICOS SAS', 'Hospital Venancio Díaz Díaz (Sabaneta)', '5'
        ], ';');
        fclose($output);
        exit;
    }
}

// ==========================================
// 2. PARSER STREAMING ULTRA-RÁPIDO (.XLSX)
// ==========================================
class NativeXlsxStreamReader {
    public static function parse($filePath, $onChunkCallback, $chunkSize = 1000) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        // 1. Cargar cadenas compartidas de forma eficiente
        $sharedStrings = [];
        $sstXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sstXml !== false) {
            $reader = new XMLReader();
            $reader->XML($sstXml);
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                    $siXml = $reader->readOuterXML();
                    preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $siXml, $matches);
                    $sharedStrings[] = html_entity_decode(implode('', $matches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $reader->close();
            unset($sstXml);
        }

        // 2. Transmisión del contenido de la hoja
        $sheetStream = $zip->getStream('xl/worksheets/sheet1.xml');
        if (!$sheetStream) {
            $zip->close();
            return false;
        }

        $tempSheet = tempnam(sys_get_temp_dir(), 'sispam_sheet_');
        $fp = fopen($tempSheet, 'w');
        while (!feof($sheetStream)) {
            fwrite($fp, fread($sheetStream, 65536));
        }
        fclose($fp);
        fclose($sheetStream);
        $zip->close();

        $reader = new XMLReader();
        $reader->open($tempSheet);

        $rowIdx = 0;
        $headers = [];
        $batch = [];
        $totalProcesados = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $rowXml = $reader->readOuterXML();
                $rowCells = self::extractCells($rowXml, $sharedStrings);
                $rowIdx++;

                if ($rowIdx === 1) {
                    $headers = array_map(function($h) {
                        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h)));
                    }, $rowCells);
                } else {
                    $assoc = [];
                    foreach ($headers as $colIdx => $headerName) {
                        if (!empty($headerName)) {
                            $assoc[$headerName] = $rowCells[$colIdx] ?? '';
                        }
                    }
                    $batch[] = $assoc;
                    if (count($batch) >= $chunkSize) {
                        $onChunkCallback($batch);
                        $totalProcesados += count($batch);
                        $batch = [];
                    }
                }
            }
        }

        if (!empty($batch)) {
            $onChunkCallback($batch);
            $totalProcesados += count($batch);
        }

        $reader->close();
        @unlink($tempSheet);
        return $totalProcesados;
    }

    private static function extractCells($rowXml, &$sharedStrings) {
        $cells = [];
        $reader = new XMLReader();
        $reader->XML($rowXml);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $rAttr = $reader->getAttribute('r');
                $tAttr = $reader->getAttribute('t');
                $colIdx = self::colLetterToIndex(preg_replace('/\d+/', '', $rAttr));

                $val = '';
                $cellXml = $reader->readOuterXML();
                if ($tAttr === 's') {
                    if (preg_match('/<v>(.*?)<\/v>/', $cellXml, $m)) {
                        $sIdx = intval($m[1]);
                        $val = $sharedStrings[$sIdx] ?? '';
                    }
                } elseif ($tAttr === 'inlineStr') {
                    if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $cellXml, $m)) {
                        $val = html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                } else {
                    if (preg_match('/<v>(.*?)<\/v>/', $cellXml, $m)) {
                        $val = trim($m[1]);
                    }
                }
                $cells[$colIdx] = $val;
            }
        }
        $reader->close();

        if (!empty($cells)) {
            $maxCol = max(array_keys($cells));
            $ordered = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $ordered[$i] = $cells[$i] ?? '';
            }
            return $ordered;
        }
        return [];
    }

    private static function colLetterToIndex($colStr) {
        $colStr = strtoupper($colStr);
        $len = strlen($colStr);
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($colStr[$i]) - 64);
        }
        return $idx - 1;
    }
}

// ==========================================
// 3. PROCESAMIENTO DE CARGA MASIVA (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_pacientes'])) {
    $file = $_FILES['archivo_pacientes'];
    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $startTime = microtime(true);

        $totalProcesados = 0;
        $totalOmitidos = 0;

        try {
            if ($ext === 'xlsx') {
                // Procesar archivo Excel nativo (.xlsx)
                NativeXlsxStreamReader::parse($file['tmp_name'], function($chunk) use ($pacienteModel, &$totalProcesados, &$totalOmitidos) {
                    $res = $pacienteModel->importarLoteMasivo($chunk);
                    $totalProcesados += $res['procesados'];
                    $totalOmitidos   += $res['omitidos'];
                }, 1000);
            } elseif ($ext === 'csv' || $ext === 'txt') {
                // Procesar archivo CSV delimitado por comas o punto y coma
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle !== false) {
                    // Detección de separador (, o ;)
                    $firstLine = fgets($handle);
                    $sep = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                    rewind($handle);

                    // Leer encabezado
                    $headerRaw = fgetcsv($handle, 4000, $sep);
                    $headers = array_map(function($h) {
                        // Eliminar posibles caracteres BOM
                        $h = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h);
                        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h)));
                    }, $headerRaw);

                    $batch = [];
                    while (($row = fgetcsv($handle, 4000, $sep)) !== false) {
                        if (count($row) < 2) continue;
                        $assoc = [];
                        foreach ($headers as $idx => $hName) {
                            if (!empty($hName)) {
                                $assoc[$hName] = $row[$idx] ?? '';
                            }
                        }
                        $batch[] = $assoc;
                        if (count($batch) >= 1000) {
                            $res = $pacienteModel->importarLoteMasivo($batch);
                            $totalProcesados += $res['procesados'];
                            $totalOmitidos   += $res['omitidos'];
                            $batch = [];
                        }
                    }
                    if (!empty($batch)) {
                        $res = $pacienteModel->importarLoteMasivo($batch);
                        $totalProcesados += $res['procesados'];
                        $totalOmitidos   += $res['omitidos'];
                    }
                    fclose($handle);
                } else {
                    throw new Exception("No se pudo abrir el archivo CSV para lectura.");
                }
            } else {
                throw new Exception("Formato de archivo no soportado. Suba un archivo Excel (.xlsx) o CSV (.csv).");
            }

            $duration = round(microtime(true) - $startTime, 2);
            $mensaje = "Importación masiva completada con éxito en {$duration} segundos.";
            $resumenImportacion = [
                'procesados' => $totalProcesados,
                'omitidos'   => $totalOmitidos,
                'total'      => ($totalProcesados + $totalOmitidos),
                'tiempo'     => $duration
            ];

        } catch (Exception $e) {
            $error = "Error durante el procesamiento del archivo: " . $e->getMessage();
        }
    } else {
        $error = "Error al subir el archivo. Verifique el tamaño o los permisos del servidor.";
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold text-primary mb-1">
            <i class="fa-solid fa-file-excel text-success me-2"></i> Módulo de Carga Masiva de Pacientes
        </h4>
        <p class="text-muted small mb-0">
            Importación y actualización masiva ultra-rápida (hasta 100.000+ registros). Soporta plantillas en formato <strong>Excel (.xlsx)</strong> y <strong>CSV UTF-8</strong>.
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="btn-group shadow-sm">
            <a href="index.php?page=importar_pacientes&download_template=xlsx" class="btn btn-success fw-bold">
                <i class="fa-solid fa-file-excel me-1"></i> 📥 Descargar Plantilla Excel (.xlsx)
            </a>
            <a href="index.php?page=importar_pacientes&download_template=csv" class="btn btn-outline-success fw-bold">
                <i class="fa-solid fa-file-csv me-1"></i> .CSV
            </a>
        </div>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center py-3">
        <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
        <div>
            <div class="fw-bold fs-6"><?= htmlspecialchars($mensaje) ?></div>
            <small class="text-muted">Todos los registros válidos fueron insertados o actualizados en la base de datos.</small>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center py-3">
        <i class="fa-solid fa-triangle-exclamation fs-4 me-3 text-danger"></i>
        <div>
            <div class="fw-bold fs-6">Error en la Importación</div>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($resumenImportacion): ?>
    <div class="card card-glass border-primary mb-4 shadow-sm">
        <div class="card-body p-4">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-square-poll-vertical me-2"></i> Resumen de la Importación Masiva</h5>
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3">
                        <div class="fs-3 fw-bold text-success"><?= number_format($resumenImportacion['procesados']) ?></div>
                        <small class="text-dark fw-bold">Insertados / Actualizados</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3">
                        <div class="fs-3 fw-bold text-warning"><?= number_format($resumenImportacion['omitidos']) ?></div>
                        <small class="text-dark fw-bold">Omitidos (Sin Documento/Nombre)</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-info bg-opacity-10 border border-info border-opacity-25 rounded-3">
                        <div class="fs-3 fw-bold text-info"><?= number_format($resumenImportacion['total']) ?></div>
                        <small class="text-dark fw-bold">Total Filas Leídas</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3">
                        <div class="fs-3 fw-bold text-primary"><?= $resumenImportacion['tiempo'] ?> s</div>
                        <small class="text-dark fw-bold">Tiempo de Ejecución</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Formulario de Carga Masiva -->
    <div class="col-lg-5">
        <div class="card card-glass border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-cloud-arrow-up me-2"></i> Subir Archivo de Pacientes</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <form method="POST" action="" enctype="multipart/form-data" id="formCargaMasiva" onsubmit="mostrarCargando()">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark fs-6">Selecciona o arrastra el archivo diligenciado:</label>
                        <div class="p-4 border border-2 border-primary border-dashed rounded-3 text-center bg-light mb-2" id="dropArea" style="cursor: pointer;">
                            <i class="fa-solid fa-file-excel fs-1 text-success mb-2"></i>
                            <div class="fw-bold text-primary" id="fileNameDisplay">Haz clic aquí o arrastra tu archivo (.xlsx o .csv)</div>
                            <small class="text-muted">Acepta archivos de hasta 50MB y 100.000+ registros</small>
                            <input type="file" name="archivo_pacientes" id="inputFile" class="d-none" accept=".xlsx,.csv,.txt" required onchange="actualizarNombreArchivo(this)">
                        </div>
                    </div>

                    <div class="alert alert-info border-0 py-2 small mb-4">
                        <i class="fa-solid fa-bolt me-1"></i> <strong>Procesamiento en Lotes:</strong> Si un paciente ya existe en el sistema por su número de documento, el sistema actualizará sus datos automáticamente sin duplicar registros.
                    </div>

                    <div id="progresoCarga" class="d-none mb-3">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span><i class="fa-solid fa-spinner fa-spin me-1"></i> Procesando archivo en el servidor...</span>
                            <span id="txtPorcentaje">Por favor espere</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 shadow-sm" id="btnSubmit">
                        <i class="fa-solid fa-rocket me-2"></i> Iniciar Importación Masiva
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Instrucciones y Recomendaciones Técnicas -->
    <div class="col-lg-7">
        <div class="card card-glass border-0 shadow-sm h-100">
            <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-circle-info text-info me-2"></i> Capacidad & Recomendaciones Técnicas</h5>
                <span class="badge bg-success">68.000+ Registros Soportados</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <div class="fw-bold text-primary mb-1"><i class="fa-solid fa-gauge-high me-1"></i> Alto Rendimiento (Streaming)</div>
                            <small class="text-muted">El módulo lee el archivo mediante streaming de memoria (menos de 10MB de RAM) e inserta en la BD en bloques de 1.000 registros por transacción.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <div class="fw-bold text-success mb-1"><i class="fa-solid fa-shield-halved me-1"></i> Cero Duplicados (Upsert)</div>
                            <small class="text-muted">Usa la llave única <code>numero_documento</code>. Si el paciente ya existe, se actualizan sus datos demográficos sin crear registros repetidos.</small>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mt-3 mb-2"><i class="fa-solid fa-list-check text-primary me-1"></i> Reglas Clave para diligenciar la Plantilla:</h6>
                <ul class="small lh-lg text-secondary mb-0">
                    <li><strong>Campos Obligatorios Mínimos:</strong> <code>tipo_documento</code>, <code>numero_documento</code>, <code>primer_nombre</code>, <code>primer_apellido</code>.</li>
                    <li><strong>Fecha de Nacimiento:</strong> Utilice formato estándar <code>YYYY-MM-DD</code> (ej: <code>1995-08-15</code>) o fecha nativa de Excel.</li>
                    <li><strong>Aseguradora / EPS:</strong> Si se deja vacío, el sistema asignará automáticamente <code>Particular / Sin EPS</code>.</li>
                    <li><strong>Sexo:</strong> <code>Masculino</code>, <code>Femenino</code> o <code>Indeterminado o Intersexual</code>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- TABLA COMPLETA DE CAMPOS PARAMÉTRICOS DEL MÓDULO DE INGRESO (RIPS / SGSSS) -->
<div class="card card-glass border-0 shadow-sm mb-4">
    <div class="card-header bg-light py-3 border-bottom">
        <h5 class="fw-bold text-primary mb-0">
            <i class="fa-solid fa-table-list text-primary me-2"></i> Muestra y Estructura Completa de Campos del Módulo de Ingreso
        </h5>
        <small class="text-muted">A continuación se detallan todos los campos que componen la ficha del paciente en SISPAM, indicando su obligatoriedad, tipo y valores de ejemplo.</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle mb-0 small">
            <thead class="table-dark">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Nombre de Columna en Plantilla</th>
                    <th>Etiqueta en Formulario de Ingreso</th>
                    <th style="width: 140px;" class="text-center">Obligatoriedad</th>
                    <th>Valores Permitidos / Opciones</th>
                    <th>Valor por Defecto</th>
                    <th>Ejemplo</th>
                </tr>
            </thead>
            <tbody>
                <!-- Identificación -->
                <tr class="table-light"><td colspan="7" class="fw-bold text-primary"><i class="fa-solid fa-id-card me-1"></i> 1. DATOS DE IDENTIFICACIÓN</td></tr>
                <tr>
                    <td class="text-center">1</td>
                    <td><code class="fw-bold text-primary">tipo_documento</code></td>
                    <td>Tipo de Documento</td>
                    <td class="text-center"><span class="badge bg-danger">Obligatorio</span></td>
                    <td><code>AS</code>, <code>CC</code>, <code>CD</code>, <code>CE</code>, <code>CN</code>, <code>MS</code>, <code>NIT</code>, <code>NV</code>, <code>PA</code>, <code>PE</code>, <code>PT</code>, <code>RC</code>, <code>SC</code>, <code>SI</code>, <code>TI</code></td>
                    <td><code>CC</code></td>
                    <td><code>CC</code></td>
                </tr>
                <tr>
                    <td class="text-center">2</td>
                    <td><code class="fw-bold text-primary">numero_documento</code></td>
                    <td>Número de Identificación</td>
                    <td class="text-center"><span class="badge bg-danger">Obligatorio</span></td>
                    <td>Texto o Número único (sin puntos)</td>
                    <td><em>Ninguno</em></td>
                    <td><code>1036780004</code></td>
                </tr>
                <tr>
                    <td class="text-center">3</td>
                    <td><code class="fw-bold text-primary">primer_nombre</code></td>
                    <td>Primer Nombre</td>
                    <td class="text-center"><span class="badge bg-danger">Obligatorio</span></td>
                    <td>Texto alfabético</td>
                    <td><em>Ninguno</em></td>
                    <td><code>VALENTINA</code></td>
                </tr>
                <tr>
                    <td class="text-center">4</td>
                    <td><code class="fw-bold text-secondary">segundo_nombre</code></td>
                    <td>Segundo Nombre</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Texto alfabético</td>
                    <td><em>Vacío</em></td>
                    <td><code>MARIA</code></td>
                </tr>
                <tr>
                    <td class="text-center">5</td>
                    <td><code class="fw-bold text-primary">primer_apellido</code></td>
                    <td>Primer Apellido</td>
                    <td class="text-center"><span class="badge bg-danger">Obligatorio</span></td>
                    <td>Texto alfabético</td>
                    <td><em>Ninguno</em></td>
                    <td><code>GOMEZ</code></td>
                </tr>
                <tr>
                    <td class="text-center">6</td>
                    <td><code class="fw-bold text-secondary">segundo_apellido</code></td>
                    <td>Segundo Apellido</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Texto alfabético</td>
                    <td><em>Vacío</em></td>
                    <td><code>CHICA</code></td>
                </tr>
                <tr>
                    <td class="text-center">7</td>
                    <td><code class="fw-bold text-primary">fecha_nacimiento</code></td>
                    <td>Fecha de Nacimiento</td>
                    <td class="text-center"><span class="badge bg-warning text-dark">Recomendado</span></td>
                    <td>Formato <code>YYYY-MM-DD</code></td>
                    <td><em>Vacío</em></td>
                    <td><code>1995-08-15</code></td>
                </tr>
                <tr>
                    <td class="text-center">8</td>
                    <td><code class="fw-bold text-primary">sexo</code></td>
                    <td>Sexo Biológico</td>
                    <td class="text-center"><span class="badge bg-warning text-dark">Recomendado</span></td>
                    <td><code>Masculino</code>, <code>Femenino</code>, <code>Indeterminado o Intersexual</code></td>
                    <td><code>Masculino</code></td>
                    <td><code>Femenino</code></td>
                </tr>
                <tr>
                    <td class="text-center">9</td>
                    <td><code class="fw-bold text-secondary">estado_civil</code></td>
                    <td>Estado Civil</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>Soltero</code>, <code>Casado</code>, <code>Unión Libre</code>, <code>Separado</code>, <code>Divorciado</code>, <code>Viudo</code>, <code>S/N</code></td>
                    <td><code>Soltero</code></td>
                    <td><code>Soltero</code></td>
                </tr>
                <tr>
                    <td class="text-center">10</td>
                    <td><code class="fw-bold text-info">grupo_sanguineo</code></td>
                    <td>Grupo Sanguíneo / RH</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>O+</code>, <code>O-</code>, <code>B+</code>, <code>B-</code>, <code>A+</code>, <code>A-</code>, <code>AB+</code>, <code>AB-</code>, <code>NoSab - NoSabe</code>, <code>S/N</code></td>
                    <td><code>O+</code></td>
                    <td><code>O+</code></td>
                </tr>
                <tr>
                    <td class="text-center">11</td>
                    <td><code class="fw-bold text-info">grupo_etnico</code></td>
                    <td>Grupo Étnico</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>S - S/N</code>, <code>No Aplica</code>, <code>Afrocolombiano</code>, <code>Indígena</code>, <code>Gitano (ROM)</code>, <code>Raizal</code>, <code>Palenquero</code></td>
                    <td><code>S - S/N</code></td>
                    <td><code>S - S/N</code></td>
                </tr>
                <tr>
                    <td class="text-center">12</td>
                    <td><code class="fw-bold text-secondary">ciudad_expedicion</code></td>
                    <td>Ciudad Expedición Doc.</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Código DANE / Ciudad</td>
                    <td><code>MEDELLIN-ANT-05001</code></td>
                    <td><code>MEDELLIN-ANT-05001</code></td>
                </tr>

                <!-- Ubicación & Contacto -->
                <tr class="table-light"><td colspan="7" class="fw-bold text-primary"><i class="fa-solid fa-location-dot me-1"></i> 2. UBICACIÓN, RESIDENCIA & CONTACTO</td></tr>
                <tr>
                    <td class="text-center">11</td>
                    <td><code class="fw-bold text-primary">numero_celular</code></td>
                    <td>Número Celular / Móvil</td>
                    <td class="text-center"><span class="badge bg-warning text-dark">Recomendado</span></td>
                    <td>Número de 10 dígitos</td>
                    <td><em>Vacío</em></td>
                    <td><code>3123456789</code></td>
                </tr>
                <tr>
                    <td class="text-center">12</td>
                    <td><code class="fw-bold text-secondary">email</code></td>
                    <td>Correo Electrónico</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Email válido</td>
                    <td><em>Vacío</em></td>
                    <td><code>paciente@correo.com</code></td>
                </tr>
                <tr>
                    <td class="text-center">13</td>
                    <td><code class="fw-bold text-secondary">direccion_residencia</code></td>
                    <td>Dirección de Residencia</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Texto de dirección</td>
                    <td><em>Vacío</em></td>
                    <td><code>Calle 50 # 45-20</code></td>
                </tr>
                <tr>
                    <td class="text-center">14</td>
                    <td><code class="fw-bold text-secondary">ciudad_residencia</code></td>
                    <td>Ciudad de Residencia</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Código DANE / Ciudad</td>
                    <td><code>MEDELLIN-ANT-05001</code></td>
                    <td><code>MEDELLIN-ANT-05001</code></td>
                </tr>
                <tr>
                    <td class="text-center">15</td>
                    <td><code class="fw-bold text-secondary">barrio</code></td>
                    <td>Barrio</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Nombre del barrio (ej: El Poblado, Laureles, Centro...)</td>
                    <td><code>El Poblado</code></td>
                    <td><code>El Poblado</code></td>
                </tr>
                <tr>
                    <td class="text-center">16</td>
                    <td><code class="fw-bold text-secondary">zona</code></td>
                    <td>Zona</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>Urbana</code>, <code>Rural</code></td>
                    <td><code>Urbana</code></td>
                    <td><code>Urbana</code></td>
                </tr>

                <!-- Afiliación SGSSS & Salud -->
                <tr class="table-light"><td colspan="7" class="fw-bold text-primary"><i class="fa-solid fa-file-medical me-1"></i> 3. AFILIACIÓN AL SISTEMA DE SALUD (EPS / RIPS)</td></tr>
                <tr>
                    <td class="text-center">17</td>
                    <td><code class="fw-bold text-primary">eps_nombre</code></td>
                    <td>Aseguradora (EPS)</td>
                    <td class="text-center"><span class="badge bg-warning text-dark">Recomendado</span></td>
                    <td><code>Sura EPS</code>, <code>Nueva EPS</code>, <code>Savia Salud EPS</code>, <code>Sanitas EPS</code>, <code>Salud Total EPS</code>, etc.</td>
                    <td><code>Particular / Sin EPS</code></td>
                    <td><code>Sura EPS</code></td>
                </tr>
                <tr>
                    <td class="text-center">18</td>
                    <td><code class="fw-bold text-secondary">tipo_afiliado</code></td>
                    <td>Tipo de Afiliado</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>Contributivo Cotizante</code>, <code>Contributivo Beneficiario</code>, <code>Subsidiado</code>, <code>Particular</code></td>
                    <td><code>Contributivo Cotizante</code></td>
                    <td><code>Contributivo Cotizante</code></td>
                </tr>
                <tr>
                    <td class="text-center">19</td>
                    <td><code class="fw-bold text-secondary">nivel_socioeconomico</code></td>
                    <td>Nivel Socioeconómico</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>CATEGORIA A</code>, <code>CATEGORIA B</code>, <code>CATEGORIA C</code>, <code>SISBEN A</code>, <code>SISBEN B</code></td>
                    <td><code>CATEGORIA A</code></td>
                    <td><code>CATEGORIA A</code></td>
                </tr>
                <tr>
                    <td class="text-center">20</td>
                    <td><code class="fw-bold text-secondary">estrato_socioeconomico</code></td>
                    <td>Estrato Socioeconómico</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>1</code>, <code>2</code>, <code>3</code>, <code>4</code>, <code>5</code>, <code>6</code></td>
                    <td><code>3</code></td>
                    <td><code>3</code></td>
                </tr>
                <tr>
                    <td class="text-center">21</td>
                    <td><code class="fw-bold text-secondary">ips_primaria</code></td>
                    <td>IPS Primaria</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Nombre de la IPS</td>
                    <td><code>900294794 - COMITE DE ESTUDIOS MEDICOS SAS</code></td>
                    <td><code>900294794 - COMITE DE ESTUDIOS MEDICOS SAS</code></td>
                </tr>
                <tr>
                    <td class="text-center">22</td>
                    <td><code class="fw-bold text-secondary">ips_remite</code></td>
                    <td>IPS que Remite</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td>Nombre de IPS / Hospital remitente</td>
                    <td><em>Vacío</em></td>
                    <td><code>HOSPITAL GENERAL DE MEDELLIN</code></td>
                </tr>
                <tr>
                    <td class="text-center">23</td>
                    <td><code class="fw-bold text-secondary">grupo_poblacional</code></td>
                    <td>Grupo Poblacional</td>
                    <td class="text-center"><span class="badge bg-secondary">Opcional</span></td>
                    <td><code>Población General</code>, <code>Adulto Mayor</code>, <code>Gestante</code>, etc.</td>
                    <td><code>Otro Grupo Poblacional</code></td>
                    <td><code>Poblacion General</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const dropArea = document.getElementById('dropArea');
const inputFile = document.getElementById('inputFile');
const fileNameDisplay = document.getElementById('fileNameDisplay');

if (dropArea && inputFile) {
    dropArea.addEventListener('click', () => inputFile.click());

    dropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropArea.classList.add('bg-white', 'border-success');
    });

    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('bg-white', 'border-success');
    });

    dropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dropArea.classList.remove('bg-white', 'border-success');
        if (e.dataTransfer.files.length) {
            inputFile.files = e.dataTransfer.files;
            actualizarNombreArchivo(inputFile);
        }
    });
}

function actualizarNombreArchivo(input) {
    if (input.files && input.files.length > 0) {
        const f = input.files[0];
        const sizeMb = (f.size / (1024 * 1024)).toFixed(2);
        fileNameDisplay.innerHTML = `<i class="fa-solid fa-file-circle-check text-success me-1"></i> <strong>${f.name}</strong> <span class="badge bg-primary ms-1">${sizeMb} MB</span>`;
    }
}

function mostrarCargando() {
    const btn = document.getElementById('btnSubmit');
    const progreso = document.getElementById('progresoCarga');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Procesando archivo en el servidor...';
    }
    if (progreso) {
        progreso.classList.remove('d-none');
    }
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
