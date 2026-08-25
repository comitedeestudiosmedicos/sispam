<?php
require_once __DIR__ . '/../../config/app.php';
check_role(['Administrador', 'empresa', 'usuarios']);

require_once __DIR__ . '/../../models/Paciente.php';

$pacienteModel = new Paciente();
$mensaje = '';
$error = '';
$resumenImportacion = null;

// Descarga directa de la plantilla CSV
if (isset($_GET['download_template']) && $_GET['download_template'] == '1') {
    $file_path = BASE_DIR . '/assets/plantilla_pacientes.csv';
    if (file_exists($file_path)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_pacientes_sispam.csv"');
        readfile($file_path);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv'];
    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                $inserted = 0;
                $updated  = 0;
                $skipped  = 0;
                $rowNum   = 0;

                // Leer encabezado
                $header = fgetcsv($handle, 2000, ',');

                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    $rowNum++;
                    if (count($data) < 3) continue;

                    $tipo_doc    = strtoupper(trim($data[0] ?? 'CC'));
                    $num_doc     = preg_replace('/[^\d]/', '', trim($data[1] ?? ''));
                    $p_nombre    = trim($data[2] ?? '');
                    $s_nombre    = trim($data[3] ?? '');
                    $p_apellido  = trim($data[4] ?? '');
                    $s_apellido  = trim($data[5] ?? '');
                    $fecha_nac   = trim($data[6] ?? '');
                    $sexo        = trim($data[7] ?? 'Masculino');
                    $eps         = trim($data[8] ?? 'Sura EPS');
                    $celular     = trim($data[9] ?? '');
                    $direccion   = trim($data[10] ?? '');
                    $ciudad_res  = trim($data[11] ?? 'MEDELLIN-ANT-05001');

                    if (empty($num_doc) || empty($p_nombre)) {
                        $skipped++;
                        continue;
                    }

                    $nombres_comp = trim($p_nombre . ' ' . $s_nombre);
                    $apellidos_comp = trim($p_apellido . ' ' . $s_apellido);
                    if (empty($apellidos_comp)) $apellidos_comp = 'REGISTRADO';

                    // Verificar si paciente existe por documento
                    $pacExistente = $pacienteModel->buscarPorDocumento($num_doc);

                    $datosPaciente = [
                        'tipo_documento'     => $tipo_doc,
                        'numero_documento'   => $num_doc,
                        'nombres'            => $nombres_comp,
                        'apellidos'          => $apellidos_comp,
                        'primer_nombre'      => $p_nombre,
                        'segundo_nombre'     => $s_nombre,
                        'primer_apellido'    => $p_apellido,
                        'segundo_apellido'   => $s_apellido,
                        'fecha_nacimiento'   => !empty($fecha_nac) ? date('Y-m-d', strtotime($fecha_nac)) : null,
                        'sexo'               => in_array($sexo, ['Masculino','Femenino','Indeterminado o Intersexual']) ? $sexo : 'Masculino',
                        'eps_nombre'         => $eps ?: 'Sura EPS',
                        'numero_celular'     => $celular,
                        'direccion_residencia'=> $direccion,
                        'ciudad_residencia'  => $ciudad_res,
                        'telefono'           => $celular
                    ];

                    if ($pacExistente) {
                        $pacienteModel->actualizar($pacExistente['id'], $datosPaciente);
                        $updated++;
                    } else {
                        $pacienteModel->crear($datosPaciente);
                        $inserted++;
                    }
                }
                fclose($handle);

                $mensaje = "Proceso de importación masiva finalizado exitosamente.";
                $resumenImportacion = [
                    'insertados' => $inserted,
                    'actualizados' => $updated,
                    'omitidos' => $skipped,
                    'total' => ($inserted + $updated + $skipped)
                ];
            } else {
                $error = "No se pudo leer el contenido del archivo CSV.";
            }
        } else {
            $error = "Formato no válido. Por favor suba un archivo con extensión .csv";
        }
    } else {
        $error = "Ocurrió un error al subir el archivo CSV.";
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-file-csv me-2"></i> Carga Masiva de Pacientes (CSV)</h4>
        <p class="text-muted small">Importa o actualiza masivamente los registros de pacientes descargando la plantilla oficial de ejemplo.</p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="index.php?page=importar_pacientes&download_template=1" class="btn btn-outline-success fw-bold shadow-sm">
            <i class="fa-solid fa-download me-1"></i> 📄 Descargar Plantilla CSV
        </a>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($resumenImportacion): ?>
    <div class="card card-glass border-primary mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-chart-bar me-2"></i> Resumen de la Importación Masiva</h5>
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded border">
                        <div class="fs-4 fw-bold text-success"><?= $resumenImportacion['insertados'] ?></div>
                        <small class="text-muted fw-bold">Nuevos Registrados</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded border">
                        <div class="fs-4 fw-bold text-info"><?= $resumenImportacion['actualizados'] ?></div>
                        <small class="text-muted fw-bold">Actualizados</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded border">
                        <div class="fs-4 fw-bold text-warning"><?= $resumenImportacion['omitidos'] ?></div>
                        <small class="text-muted fw-bold">Sin Datos / Omitidos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded border">
                        <div class="fs-4 fw-bold text-dark"><?= $resumenImportacion['total'] ?></div>
                        <small class="text-muted fw-bold">Total Filas Procesadas</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Formulario de Carga CSV -->
    <div class="col-md-6">
        <div class="card card-glass border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-upload me-2"></i> Subir Archivo CSV de Pacientes</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">Seleccione el archivo CSV diligenciado:</label>
                        <input type="file" name="archivo_csv" class="form-control form-control-lg border-primary" accept=".csv" required>
                        <div class="form-text">Asegúrese de usar la codificación UTF-8 o separar por comas.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg fw-bold w-100 shadow-sm">
                        <i class="fa-solid fa-cloud-arrow-up me-2"></i> Procesar e Importar Pacientes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Instrucciones de la Plantilla -->
    <div class="col-md-6">
        <div class="card card-glass border-0 shadow-sm h-100">
            <div class="card-header bg-dark text-white py-3">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-circle-info me-2"></i> Instrucciones de la Plantilla</h5>
            </div>
            <div class="card-body p-4">
                <ol class="small lh-lg mb-3">
                    <li>Descargue la plantilla haciendo clic en el botón verde superior <strong>"Descargar Plantilla CSV"</strong>.</li>
                    <li>Abra el archivo en Excel o su editor preferido y diligencie las columnas respetando los encabezados.</li>
                    <li>Columnas obligatorias: <code>tipo_documento</code>, <code>numero_documento</code>, <code>primer_nombre</code>.</li>
                    <li>Formatos aceptados de Fecha de Nacimiento: <code>YYYY-MM-DD</code> (ej: <code>1990-05-20</code>).</li>
                    <li>Si el paciente ya existe en el sistema por número de documento, sus datos serán actualizados automáticamente.</li>
                </ol>
                <a href="index.php?page=importar_pacientes&download_template=1" class="btn btn-sm btn-outline-success fw-bold">
                    <i class="fa-solid fa-download me-1"></i> Descargar plantilla_pacientes.csv
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
