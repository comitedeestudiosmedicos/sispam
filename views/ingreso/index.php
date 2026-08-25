<?php
require_once __DIR__ . '/../../config/app.php';
check_role('ingreso');

require_once __DIR__ . '/../../models/Paciente.php';
require_once __DIR__ . '/../../models/Ingreso.php';

$pacienteModel = new Paciente();
$ingresoModel = new Ingreso();

$mensaje = '';
$error = '';
$ticketGenerado = null;

// Inicio de la orientación: el orientador abre el formulario. Se registra solo en GET —
// tras un POST la misma vista se vuelve a pintar y contarlo ahí falsearía el arranque.
// Va sin atencion_id porque la atención todavía no existe; el tablero lo correlaciona por
// usuario y cercanía en el tiempo con el envío a cola.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Auditoria::registrar(Auditoria::ATENCION_ABIERTA, [
        'entidad_tipo' => 'atencion',
        'detalle'      => ['etapa' => 'orientacion']
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_doc = trim($_POST['tipo_documento'] ?? '');
    $num_doc  = trim($_POST['numero_documento'] ?? '');
    $primer_apellido = trim($_POST['primer_apellido'] ?? '');
    $primer_nombre   = trim($_POST['primer_nombre'] ?? '');
    $eps_nombre      = trim($_POST['eps_nombre'] ?? '');

    // Los soportes llegan como LISTA, uno por archivo, cada uno con su propio tipo. Antes
    // se indexaban por categoría y dos filas del mismo tipo se pisaban entre sí: se perdía
    // un soporte sin avisar y la validación de abajo lo daba por completo igual, porque
    // solo mira qué tipos vienen. Ver Ingreso::normalizarSoportes().
    // doc_recorte_manual va en paralelo: 1 si el escáner necesitó ajuste manual del recorte.
    $archivos_adjuntos = Ingreso::normalizarSoportes(
        $_FILES['doc_archivos'] ?? [],
        $_POST['doc_tipo_categoria'] ?? [],
        $_POST['doc_recorte_manual'] ?? []
    );

    $tipos_adjuntados = array_column($archivos_adjuntos, 'tipo');

    // Archivos con extensión no admitida: se nombran en el error en vez de descartarse
    // calladamente, que es como se pierde un soporte sin que nadie se entere.
    $soportes_rechazados = [];
    foreach ($archivos_adjuntos as $soporte) {
        if (!$soporte['ext_valida']) $soportes_rechazados[] = $soporte['name'];
    }

    $persona_reclama  = $_POST['persona_reclama'] ?? '';
    $tieneCedula      = in_array('CEDULA', $tipos_adjuntados);
    $tieneOrden       = in_array('ORDEN_MEDICA', $tipos_adjuntados);
    $tieneAutorizacion= in_array('AUTORIZACION', $tipos_adjuntados);

    // Casilla de validación de derechos en Conexiones. Hoy NO bloquea: se registra
    // marcada u omitida y el ingreso procede igual. Volverla obligatoria es cambiar
    // AUDITORIA_DERECHOS_BLOQUEANTE en config/auditoria.php, sin refactorizar nada.
    $derechos_validados = !empty($_POST['derechos_validados']);

    if (empty($tipo_doc) || empty($num_doc) || empty($primer_apellido) || empty($primer_nombre) || empty($eps_nombre)) {
        $error = 'Por favor complete todos los datos obligatorios marcados con asterisco (*).';
    } else if (!empty($soportes_rechazados)) {
        $error = '⚠️ FORMATO DE ARCHIVO NO ADMITIDO en: ' . implode(', ', $soportes_rechazados)
               . '. Los soportes deben ser PDF, JPG, JPEG o PNG.';
    } else if (AUDITORIA_DERECHOS_BLOQUEANTE && !$derechos_validados) {
        $error = '⚠️ Debe confirmar que consultó la validación de derechos en Conexiones antes de continuar.';
    } else if (empty($persona_reclama)) {
        $error = '⚠️ Deber seleccionar la modalidad de reclamación (si los medicamentos los reclama el paciente o un tercero/acudiente).';
    } else if ($persona_reclama === 'PACIENTE_DIRECTO' && (!$tieneCedula || !$tieneOrden)) {
        $error = '⚠️ REQUISITO OBLIGATORIO DE SOPORTES: Para reclamación directa del paciente, debe adjuntar por separado los 2 soportes: 1) Cédula / Doc. Identidad y 2) Fórmula / Orden Médica.';
    } else if ($persona_reclama === 'TERCERO_ACUDIENTE' && (!$tieneCedula || !$tieneOrden || !$tieneAutorizacion)) {
        $error = '⚠️ REQUISITO OBLIGATORIO DE SOPORTES: Para entrega a nombre de otra persona (tercero/acudiente), debe adjuntar por separado los 3 soportes: 1) Cédula del paciente, 2) Fórmula / Orden Médica y 3) Autorización / Doc. del Tercero.';
    } else {
        // Registrar o actualizar paciente con todos los datos RIPS/SGSSS
        $paciente_id = $pacienteModel->createOrUpdate($_POST);

        $prioridad = $_POST['prioridad'] ?? 'NORMAL';
        $prioridad_obs = trim($_POST['prioridad_observacion'] ?? '');
        $ips_remite = trim($_POST['ips_remite'] ?? '');

        $resultado = $ingresoModel->crearIngreso($paciente_id, $_SESSION['user_id'], $archivos_adjuntos, $prioridad, $prioridad_obs, $persona_reclama, $ips_remite);

        if ($resultado) {
            $ticketGenerado = $resultado['ticket'];
            $ingreso_id_creado = $resultado['id'];
            $mensaje = "¡Ingreso registrado exitosamente! Tiquete Generado: <strong>{$ticketGenerado}</strong>";

            // Queda constancia tanto de que se consultó como de que se omitió: sin el
            // segundo caso no habría forma de saber con qué frecuencia se salta el paso.
            Auditoria::registrar(Auditoria::DERECHOS_VALIDADOS, [
                'atencion_id'  => $resultado['id'],
                'entidad_tipo' => 'atencion',
                'entidad_id'   => $resultado['id'],
                'resultado'    => $derechos_validados ? Auditoria::EXITO : Auditoria::OMITIDO,
                'detalle'      => ['fuente' => 'Conexiones', 'bloqueante' => AUDITORIA_DERECHOS_BLOQUEANTE]
            ]);
        } else {
            $error = 'Ocurrió un error al procesar el ingreso y guardar los datos del paciente.';
        }
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
/* Estilos del Escáner Profesional SISPAM */
.scanner-modal-dialog {
    max-width: 650px;
    margin: 1.5rem auto;
}
.scanner-modal-content {
    background: #0f172a;
    color: #ffffff;
    border-radius: 16px;
    border: 1px solid #334155;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.scanner-modal-header {
    padding: 14px 20px;
    background: #1e293b;
    border-bottom: 1px solid #334155;
}
.scanner-modal-body {
    padding: 16px;
    background: #0f172a;
}
.scanner-canvas-wrapper {
    width: 100%;
    min-height: 380px;
    max-height: 60vh;
    background: #020617;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border: 2px dashed #334155;
}
.scanner-canvas-wrapper video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}
.scanner-canvas-wrapper canvas {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.scanner-shutter {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: #ffffff;
    border: 4px solid #38bdf8;
    box-shadow: 0 0 20px rgba(56, 189, 248, 0.5);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
    margin: 12px auto;
}
.scanner-shutter:hover {
    transform: scale(1.08);
    box-shadow: 0 0 25px rgba(56, 189, 248, 0.8);
}
.scanner-shutter:active {
    transform: scale(0.95);
}
.scanner-shutter-core {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: #0284c7;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.scanner-link {
    color: #cbd5e1;
    background: #1e293b;
    border: 1px solid #475569;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
}
.scanner-link:hover {
    color: #ffffff;
    background: #334155;
    border-color: #38bdf8;
}
.scanner-icon-btn {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #1e293b;
    color: #38bdf8;
    border: 1px solid #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    cursor: pointer;
    margin: 0 6px;
    transition: all 0.2s ease;
}
.scanner-icon-btn:hover {
    background: #38bdf8;
    color: #0f172a;
}
.scanner-modal-footer {
    padding: 12px 20px;
    background: #1e293b;
    border-top: 1px solid #334155;
    display: flex;
    gap: 10px;
}
.pages-thumbnail-strip {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 8px 4px;
}
.page-thumb-item {
    position: relative;
    width: 60px;
    height: 75px;
    border-radius: 6px;
    border: 2px solid #38bdf8;
    overflow: hidden;
    cursor: pointer;
    flex-shrink: 0;
}
.page-thumb-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Jerarquía de z-index y pointer-events estricta para evitar bloqueos por backdrops */
#modalEscanerDocPro {
    z-index: 1090 !important;
}
#modalEscanerDocPro .modal-dialog {
    position: relative;
    z-index: 1095 !important;
    pointer-events: auto !important;
}
#modalEscanerDocPro .modal-content {
    position: relative;
    z-index: 1096 !important;
    pointer-events: auto !important;
}
#modalConfirmEscaner, #modalNotificacionEscaner, #modalPreviewPagina {
    z-index: 1110 !important;
}
/* Toast de confirmación de escáner */
#toast-escaner-cont {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1099;
    pointer-events: none !important;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.toast-escaner {
    background: #065f46;
    color: #ffffff;
    padding: 12px 18px;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    font-size: 0.95rem;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
    pointer-events: none;
}
.toast-escaner.visible {
    opacity: 1;
    transform: translateY(0);
}
</style>

<!-- Escáner Profesional: OpenCV.js (~9MB WASM), jsPDF y scanner_doc.js se cargan de forma
     perezosa (ver ensureScannerLibsLoaded más abajo), solo al abrir el modal por primera
     vez — no aquí, para no penalizar la carga de la página de ingreso en general. -->
<script>
let _scannerLibsPromise = null;

// Reintenta fn() hasta 'attempts' veces (los CDN a veces fallan la primera petición por
// conexión fría/lenta; sin reintento, un solo hipo obligaba a cerrar y reabrir el modal).
function withRetry(fn, attempts = 3, delayMs = 800) {
    return new Promise((resolve, reject) => {
        const tryOnce = (remaining) => {
            fn().then(resolve).catch((err) => {
                if (remaining <= 1) {
                    reject(err);
                } else {
                    console.warn(`[Scanner] Carga falló, reintentando (${remaining - 1} intentos restantes):`, err.message);
                    setTimeout(() => tryOnce(remaining - 1), delayMs);
                }
            });
        };
        tryOnce(attempts);
    });
}

function ensureScannerLibsLoaded() {
    if (_scannerLibsPromise) return _scannerLibsPromise;

    const loadScript = (src, fallbackSrc, isReady) => new Promise((resolve, reject) => {
        if (isReady && isReady()) { resolve(); return; }

        const existing = document.querySelector(`script[data-scanner-lib="${src}"]`);
        if (existing) existing.remove();

        const s = document.createElement('script');
        s.src = src;
        s.dataset.scannerLib = src;
        s.onload = () => resolve();
        s.onerror = () => {
            s.remove();
            if (fallbackSrc) {
                console.warn(`[Scanner] Falló ${src}, intentando CDN fallback ${fallbackSrc}...`);
                const sFallback = document.createElement('script');
                sFallback.src = fallbackSrc;
                sFallback.dataset.scannerLib = fallbackSrc;
                sFallback.onload = () => resolve();
                sFallback.onerror = () => reject(new Error('No se pudo descargar ' + fallbackSrc));
                document.head.appendChild(sFallback);
            } else {
                reject(new Error('No se pudo descargar ' + src));
            }
        };
        document.head.appendChild(s);
    });

    const loadOpenCVAsync = () => {
        if (typeof cv !== 'undefined' && cv.Mat) return;
        const OPENCV_LOCAL = 'assets/js/vendor/opencv.js';
        const OPENCV_CDN   = 'https://docs.opencv.org/4.8.0/opencv.js';

        const waitForRuntime = () => {
            const check = () => {
                if (typeof cv !== 'undefined' && cv.Mat) {
                    console.log('[Scanner] OpenCV.js inicializado en segundo plano.');
                    if (scannerPro) {
                        scannerPro.isOpenCVReady = true;
                        scannerPro.startLiveDetection();
                    }
                    window.dispatchEvent(new Event('opencv-ready'));
                } else {
                    setTimeout(check, 150);
                }
            };
            check();
        };

        const s = document.createElement('script');
        s.src = OPENCV_LOCAL;
        s.dataset.scannerLib = OPENCV_LOCAL;
        s.onload = waitForRuntime;
        s.onerror = () => {
            s.remove();
            const sCdn = document.createElement('script');
            sCdn.src = OPENCV_CDN;
            sCdn.dataset.scannerLib = OPENCV_CDN;
            sCdn.onload = waitForRuntime;
            sCdn.onerror = () => console.warn('[Scanner] OpenCV no disponible, escáner en modo estándar.');
            document.head.appendChild(sCdn);
        };
        document.head.appendChild(s);
    };

    // Cargar librerías esenciales primero (rápido y local)
    _scannerLibsPromise = Promise.all([
        withRetry(() => loadScript('assets/js/scanner_detect.js', null, () => !!window.SISPAM_Scanner)),
        withRetry(() => loadScript('assets/js/scanner_doc.js', null, () => typeof DocumentScannerPro !== 'undefined')),
        withRetry(() => loadScript('assets/js/vendor/jspdf.umd.min.js', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', () => !!(window.jspdf && window.jspdf.jsPDF)))
    ]).then(() => {
        // Cargar OpenCV en segundo plano sin demorar el visor de la cámara
        loadOpenCVAsync();
    }).catch((err) => {
        _scannerLibsPromise = null;
        throw err;
    });

    return _scannerLibsPromise;
}
</script>

<div class="row justify-content-center">
    <div class="col-lg-11">
        <?php if ($ticketGenerado): ?>
        <div class="card card-glass border-success border-2 mb-4 text-center p-4 shadow-lg">
            <div class="text-success display-4 mb-2"><i class="fa-solid fa-circle-check"></i></div>
            <h3 class="fw-bold text-dark mb-1">¡Registro de Ingreso Exitoso!</h3>
            <p class="text-muted">Se ha generado el tiquete para el paciente en el sistema.</p>
            
            <div class="my-3">
                <span class="fs-1 fw-bold text-primary px-4 py-2 bg-light border border-primary rounded shadow-sm">
                    <?= htmlspecialchars($ticketGenerado) ?>
                </span>
            </div>

            <div class="d-flex justify-content-center gap-3 mt-3">
                <a href="index.php?page=imprimir_ticket&id=<?= $ingreso_id_creado ?>" target="_blank" class="btn btn-success btn-lg fw-bold shadow">
                    <i class="fa-solid fa-print me-2"></i> Imprimir Tiquete Térmico
                </a>
                <a href="index.php?page=ingreso" class="btn btn-outline-primary btn-lg fw-bold shadow-sm">
                    <i class="fa-solid fa-plus me-2"></i> Registrar Otro Ingreso
                </a>
            </div>
        </div>
        <?php else: ?>

        <div class="card card-glass p-3 p-md-4 shadow-sm border-0 mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center flex-wrap gap-2 mb-2">
                <div>
                    <h4 class="fw-bold text-primary mb-0 fs-5 fs-md-4">
                        <i class="fa-solid fa-address-card me-2"></i> Módulo de Ingreso RIPS / SGSSS & Priorización
                    </h4>
                    <p class="text-muted small mb-0 mt-1">Digita el documento para autocompletar la historia del paciente o diligencia los campos paramétricos oficiales RIPS/SGSSS.</p>
                </div>
                <span class="badge bg-primary px-3 py-2 fs-6 flex-shrink-0"><i class="fa-solid fa-hospital-user me-1"></i> Formulario Oficial de Salud</span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show small fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" id="formIngreso">
                
                <!-- SECCIÓN 0: ATENCIÓN PREFERENCIAL / PRIORIDAD DEL INGRESO -->
                <div class="card border-warning border-2 bg-warning bg-opacity-10 mb-4 shadow-sm">
                    <div class="card-header bg-warning text-dark fw-bold py-2 d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-star me-2"></i> 1. PRIORIZACIÓN Y ATENCIÓN PREFERENCIAL DEL INGRESO</span>
                        <span class="badge bg-dark text-white">Prioridad de Atenciones</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Tipo de Prioridad de Atención <span class="text-danger">*</span></label>
                                <select name="prioridad" id="prioridad" class="form-select form-select-lg fw-bold text-primary" required>
                                    <?php foreach (OPCIONES_PRIORIDAD as $key => $label): ?>
                                        <option value="<?= $key ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Observaciones / Novedad de Prioridad</label>
                                <input type="text" name="prioridad_observacion" id="prioridad_observacion" class="form-control" placeholder="Ej: Adulto mayor en silla de ruedas, embarazada en 3er trimestre...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 1: DATOS DE IDENTIFICACIÓN -->
                <div class="card border-0 bg-light p-3 mb-4 shadow-sm">
                    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-id-card me-2"></i> 2. Datos Principales de Identificación</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tipo de Documento <span class="text-danger">*</span></label>
                            <select name="tipo_documento" id="tipo_documento" class="form-select" required>
                                <?php foreach (TIPOS_DOCUMENTO as $code => $label): ?>
                                    <option value="<?= $code ?>"><?= $code ?> - <?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Número de Identificación <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="numero_documento" id="numero_documento" class="form-control fw-bold" placeholder="Ej: 88197902" required autocomplete="off">
                                <button class="btn btn-primary" type="button" id="btnBuscarPaciente">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                                </button>
                            </div>
                            <span id="search-status" class="small text-muted"></span>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Fecha y Hora Ingreso</label>
                            <input type="text" class="form-control bg-white" value="<?= date('d/m/Y h:i A') ?>" readonly>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Estado <span class="text-danger">*</span></label>
                            <select name="estado" id="estado" class="form-select fw-semibold" required>
                                <option value="Activo" selected>Activo</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </div>

                        <!-- Nombres y Apellidos Separados -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Primer Apellido <span class="text-danger">*</span></label>
                            <input type="text" name="primer_apellido" id="primer_apellido" class="form-control" required placeholder="Ej: Gómez">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Segundo Apellido</label>
                            <input type="text" name="segundo_apellido" id="segundo_apellido" class="form-control" placeholder="Ej: Rodríguez">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Primer Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="primer_nombre" id="primer_nombre" class="form-control" required placeholder="Ej: Juan">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Segundo Nombre</label>
                            <input type="text" name="segundo_nombre" id="segundo_nombre" class="form-control" placeholder="Ej: Pablo">
                        </div>

                        <!-- Demográficos Básicos -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Fecha Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo" id="sexo" class="form-select" required>
                                <option value="Masculino" selected>Masculino</option>
                                <option value="Femenino">Femenino</option>
                                <option value="Indeterminado o Intersexual">Indeterminado o Intersexual</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Estado Civil <span class="text-danger">*</span></label>
                            <select name="estado_civil" id="estado_civil" class="form-select" required>
                                <?php foreach (ESTADOS_CIVILES as $ec): ?>
                                    <option value="<?= $ec ?>"><?= $ec ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ciudad Expedición Doc. <span class="text-danger">*</span></label>
                            <input type="text" name="ciudad_expedicion" id="ciudad_expedicion" class="form-control" value="MEDELLIN-ANT-05001" required placeholder="Ej: MEDELLIN-ANT-05001">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">País de Nacimiento</label>
                            <input type="text" name="pais_nacimiento" id="pais_nacimiento" class="form-control" value="COLOMBIA">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Nacionalidad</label>
                            <input type="text" name="nacionalidad" id="nacionalidad" class="form-control" value="COLOMBIANA">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Ciudad de Nacimiento</label>
                            <input type="text" name="ciudad_nacimiento" id="ciudad_nacimiento" class="form-control" value="MEDELLIN-ANT-05001">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Identidad de Género</label>
                            <input type="text" name="identidad_genero" id="identidad_genero" class="form-control" placeholder="Ej: Cisgénero, Transgénero...">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: UBICACIÓN Y RESIDENCIA -->
                <div class="card border-0 bg-light p-3 mb-4 shadow-sm">
                    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-location-dot me-2"></i> 3. Ubicación, Residencia & Datos de Contacto</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Dirección de Residencia <span class="text-danger">*</span></label>
                            <input type="text" name="direccion_residencia" id="direccion_residencia" class="form-control" required placeholder="Ej: Calle 50 # 45-20, Apto 301">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Indicativo 1</label>
                            <select name="indicativo_1" id="indicativo_1" class="form-select">
                                <option value="+57" selected>+57 (Colombia)</option>
                                <option value="+1">+1 (EE.UU.)</option>
                                <option value="+34">+34 (España)</option>
                                <option value="+58">+58 (Venezuela)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Número Celular <span class="text-danger">*</span></label>
                            <input type="text" name="numero_celular" id="numero_celular" class="form-control" required placeholder="Ej: 3109876543">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Indicativo 2</label>
                            <select name="indicativo_2" id="indicativo_2" class="form-select">
                                <option value="+57" selected>+57 (Colombia)</option>
                                <option value="+1">+1 (EE.UU.)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Otro Teléfono / Fijo</label>
                            <input type="text" name="otro_telefono" id="otro_telefono" class="form-control" placeholder="Ej: 6044440000">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Correo Electrónico (Email) <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control" required placeholder="ejemplo@correo.com">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ciudad de Residencia <span class="text-danger">*</span></label>
                            <input type="text" name="ciudad_residencia" id="ciudad_residencia" class="form-control" value="MEDELLIN-ANT-05001" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Zona <span class="text-danger">*</span></label>
                            <select name="zona" id="zona" class="form-select" required>
                                <option value="Urbana" selected>Urbana</option>
                                <option value="Rural">Rural</option>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Barrio de Residencia <span class="text-danger">*</span></label>
                            <select name="barrio" id="barrio" class="form-select" required>
                                <?php foreach (BARRIOS_MEDELLIN as $b): ?>
                                    <option value="<?= $b ?>"><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Dirección Laboral</label>
                            <input type="text" name="direccion_laboral" id="direccion_laboral" class="form-control" placeholder="Ej: Transversal 39 A # 70-12">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono Laboral</label>
                            <input type="text" name="telefono_laboral" id="telefono_laboral" class="form-control" placeholder="Ej: 6043120000">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: AFILIACIÓN AL SGSSS & SALUD -->
                <div class="card border-0 bg-light p-3 mb-4 shadow-sm">
                    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-file-medical me-2"></i> 4. Afiliación al Sistema de Salud & EPS (RIPS)</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Aseguradora (EPS) <span class="text-danger">*</span></label>
                            <select name="eps_nombre" id="eps_nombre" class="form-select" required>
                                <option value="">-- Seleccionar EPS --</option>
                                <?php foreach (EPS_COLOMBIA as $eps): ?>
                                    <option value="<?= $eps ?>"><?= $eps ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Plan de Salud</label>
                            <input type="text" name="plan_salud" id="plan_salud" class="form-control" value="Plan Básico" placeholder="Ej: Plan Básico, Plan Complementario, POS">
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="actualiza_citas_plan" value="1" id="actualiza_citas_plan">
                                <label class="form-check-label fw-semibold" for="actualiza_citas_plan">Actualiza Citas por Plan</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipo de Afiliado <span class="text-danger">*</span></label>
                            <select name="tipo_afiliado" id="tipo_afiliado" class="form-select" required>
                                <?php foreach (TIPOS_AFILIADO as $ta): ?>
                                    <option value="<?= $ta ?>"><?= $ta ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nivel Socioeconómico <span class="text-danger">*</span></label>
                            <select name="nivel_socioeconomico" id="nivel_socioeconomico" class="form-select" required>
                                <?php foreach (NIVELES_SOCIOECONOMICOS as $ns): ?>
                                    <option value="<?= $ns ?>"><?= $ns ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Estrato Socioeconómico</label>
                            <select name="estrato_socioeconomico" id="estrato_socioeconomico" class="form-select">
                                <option value="1">Estrato 1</option>
                                <option value="2">Estrato 2</option>
                                <option value="3" selected>Estrato 3</option>
                                <option value="4">Estrato 4</option>
                                <option value="5">Estrato 5</option>
                                <option value="6">Estrato 6</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Ocupación</label>
                            <select name="ocupacion" id="ocupacion" class="form-select">
                                <?php foreach (OCUPACIONES as $oc): ?>
                                    <option value="<?= $oc ?>"><?= $oc ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Grupo Sanguíneo / RH</label>
                            <select name="grupo_sanguineo" id="grupo_sanguineo" class="form-select">
                                <option value="O+" selected>O+</option>
                                <option value="O-">O-</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sede de Atención <span class="text-danger">*</span></label>
                            <select name="sede_atencion" id="sede_atencion" class="form-select" required>
                                <?php foreach (SEDES_ATENCION as $sa): ?>
                                    <option value="<?= $sa ?>"><?= $sa ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">IPS Primaria (Predeterminada) <span class="text-danger">*</span></label>
                            <input type="text" name="ips_primaria" id="ips_primaria" class="form-control fw-semibold" value="900294794 - COMITE DE ESTUDIOS MEDICOS SAS" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-primary"><i class="fa-solid fa-hospital-user me-1"></i> IPS que Remite la Orden Médica <span class="text-danger">*</span></label>
                            <select name="ips_remite" id="ips_remite" class="form-select fw-semibold border-primary" required>
                                <option value="" selected>-- Seleccionar IPS Remitente de Antioquia --</option>
                                <?php foreach (IPS_ANTIOQUIA as $ips): ?>
                                    <option value="<?= $ips ?>"><?= $ips ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Municipio de Afiliación</label>
                            <select name="municipio_afiliacion" id="municipio_afiliacion" class="form-select">
                                <?php foreach (MUNICIPIOS_ANTIOQUIA as $ma): ?>
                                    <option value="<?= $ma ?>"><?= $ma ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha SGSSS</label>
                            <input type="date" name="fecha_sgsss" id="fecha_sgsss" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de Afiliación</label>
                            <input type="date" name="fecha_afiliacion" id="fecha_afiliacion" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Empleador / Empresa</label>
                            <input type="text" name="empleador" id="empleador" class="form-control" placeholder="Ej: Independiente / Nombre Empresa">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 4: DATOS DEMOGRÁFICOS, ÉTNICOS & EMERGENCIA -->
                <div class="card border-0 bg-light p-3 mb-4 shadow-sm">
                    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-people-roof me-2"></i> 5. Caracterización Poblacional, Étnica & Contacto de Emergencia</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Grupo Poblacional <span class="text-danger">*</span></label>
                            <select name="grupo_poblacional" id="grupo_poblacional" class="form-select" required>
                                <?php foreach (GRUPOS_POBLACIONALES as $gp): ?>
                                    <option value="<?= $gp ?>"><?= $gp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Grupo Étnico <span class="text-danger">*</span></label>
                            <select name="grupo_etnico" id="grupo_etnico" class="form-select" required>
                                <?php foreach (GRUPOS_ETNICOS as $ge): ?>
                                    <option value="<?= $ge ?>"><?= $ge ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Comunidad Étnica</label>
                            <input type="text" name="comunidad_etnica" id="comunidad_etnica" class="form-control" placeholder="Ej: Cabildo Emberá...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo de Discapacidad <span class="text-danger">*</span></label>
                            <select name="tipo_discapacidad" id="tipo_discapacidad" class="form-select" required>
                                <?php foreach (TIPOS_DISCAPACIDAD as $td): ?>
                                    <option value="<?= $td ?>"><?= $td ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipo de Escolaridad <span class="text-danger">*</span></label>
                            <select name="tipo_escolaridad" id="tipo_escolaridad" class="form-select" required>
                                <?php foreach (TIPOS_ESCOLARIDAD as $te): ?>
                                    <option value="<?= $te ?>"><?= $te ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Contacto de Emergencia -->
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Nombre Contacto de Emergencia</label>
                            <input type="text" name="contacto_emergencia_nombre" id="contacto_emergencia_nombre" class="form-control" placeholder="Ej: María Rodríguez">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Teléfono Contacto Emergencia</label>
                            <input type="text" name="contacto_emergencia_telefono" id="contacto_emergencia_telefono" class="form-control" placeholder="Ej: 3001234567">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Parentesco</label>
                            <input type="text" name="contacto_emergencia_parentesco" id="contacto_emergencia_parentesco" class="form-control" placeholder="Ej: Madre / Cónyuge">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 6: PERSONA QUE RECLAMA LOS MEDICAMENTOS -->
                <div class="card border-0 bg-light p-3 mb-4 shadow-sm">
                    <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-user-check me-2"></i> 6. Persona que Reclama los Medicamentos</h5>
                    <div class="row align-items-center g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold text-dark fs-6">
                                ¿Los medicamentos son reclamados por el mismo paciente o por un tercero/acudiente? <span class="text-danger">*</span>
                            </label>
                            <select name="persona_reclama" id="select_persona_reclama" class="form-select form-select-lg fw-bold border-primary shadow-sm" required onchange="evaluarVisualizacionSoportes()">
                                <option value="" selected>-- Seleccione Modalidad de Reclamación --</option>
                                <option value="PACIENTE_DIRECTO">👤 El Mismo Paciente (Reclama Personalmente en Ventanilla)</option>
                                <option value="TERCERO_ACUDIENTE">👥 Un Tercero / Acudiente Autorizado (Reclama a Nombre de Otra Persona)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <div id="info_requisitos_reclamacion" class="alert alert-info p-3 mb-0 small fw-semibold d-none shadow-sm">
                                <!-- Mensaje explicativo dinámico -->
                            </div>
                        </div>
                    </div>

                    <!-- Validación de derechos: un solo clic que deja constancia de que se
                         consultó. Marcada u omitida se registra igual, así que no frena la
                         atención; para volverla obligatoria basta cambiar
                         AUDITORIA_DERECHOS_BLOQUEANTE en config/auditoria.php. -->
                    <div class="mt-3 pt-3 border-top">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1"
                                   name="derechos_validados" id="chk_derechos_validados"
                                   <?php echo AUDITORIA_DERECHOS_BLOQUEANTE ? 'required' : ''; ?>>
                            <label class="form-check-label fw-semibold text-dark" for="chk_derechos_validados">
                                <i class="fa-solid fa-shield-halved text-success me-1"></i>
                                Se consultó la validación de derechos en Conexiones
                                <?php if (AUDITORIA_DERECHOS_BLOQUEANTE): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 7: DOCUMENTOS ADJUNTOS POR SEPARADO (OCULTA INICIALMENTE) -->
                <div id="seccion_soportes_container" class="card border-0 bg-light p-3 mb-4 shadow-sm d-none">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-primary mb-0"><i class="fa-solid fa-file-arrow-up me-2"></i> 7. Documentos Adjuntos del Ingreso (Por Separado)</h5>
                            <div id="lbl_requisito_soportes_sub" class="small text-danger fw-semibold mt-1">
                                <!-- Requisitos cargados dinámicamente -->
                            </div>
                        </div>
                    </div>

                    <div id="contenedor-documentos">
                        <!-- Las filas de soportes obligatorios se generan dinámicamente según la opción seleccionada -->
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 fw-semibold" onclick="agregarFilaDoc()">
                        <i class="fa-solid fa-plus me-1"></i> Agregar Otro Documento Adicional
                    </button>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold px-5 shadow">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Registrar Ingreso & Generar Tiquete
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE NOTIFICACIONES BOOTSTRAP PERSONALIZADO (REEMPLAZA LOS ALERTS DEL NAVEGADOR) -->
<!-- HISTORIAL DE ATENCIONES DEL PACIENTE
     Se muestra al reconocer a un paciente ya registrado. No frena el flujo: se lee de un
     vistazo y se cierra con ESC, con clic fuera o con el botón. Lo importante son las
     atenciones incompletas: con el paciente enfrente todavía se pueden subsanar. -->
<div class="modal fade" id="modalHistorialPaciente" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <div class="overflow-hidden">
                    <h5 class="modal-title fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>Historial de atenciones</h5>
                    <small id="historialPacienteNombre" class="opacity-75"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-2">
                <div id="historialAlertaDuplicado" class="d-none alert alert-danger py-2 px-3 mb-2 fw-bold"></div>
                <div id="historialResumen" class="small text-muted mb-2"></div>
                <div id="historialTabla"></div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary fw-bold" data-bs-dismiss="modal">
                    Continuar con el ingreso
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmación genérica (sí/no) para el escáner. Se abre por encima del modal del
     escáner (z-index 1055), por eso 1090. -->
<div class="modal fade" id="modalConfirmEscaner" tabindex="-1" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 1095;">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title fw-bold" id="modalConfirmTitle">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Confirmar
                </h5>
            </div>
            <div class="modal-body" id="modalConfirmMessage"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnConfirmEscanerNo">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold btn-sm text-dark" id="btnConfirmEscanerSi">Continuar</button>
            </div>
        </div>
    </div>
</div>

<!-- Vista previa de una página del lote a tamaño completo: las miniaturas son de 75x95px
     y no permiten verificar que el documento quedó legible antes de finalizar. -->
<div class="modal fade" id="modalPreviewPagina" tabindex="-1" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-lg modal-dialog-centered" style="z-index: 1095;">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header py-2 border-secondary">
                <h5 class="modal-title fw-bold text-white" id="previewPaginaTitulo">Página</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="previewPaginaImg" class="img-fluid rounded" style="max-height:75vh;" alt="Vista previa de la página escaneada">
            </div>
            <div class="modal-footer py-2 border-secondary justify-content-between">
                <small class="text-muted" id="previewPaginaInfo"></small>
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNotificacionEscaner" tabindex="-1" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 1095;">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header text-white" id="modalNotifHeader">
                <h5 class="modal-title fw-bold" id="modalNotifTitle">
                    <i class="fa-solid fa-bell me-2"></i> Notificación
                </h5>
                <button type="button" class="btn-close btn-close-white" onclick="cerrarNotificacionModal()" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="modalNotifIcon" class="display-3 mb-3"></div>
                <div id="modalNotifMessage" class="fs-5 text-dark fw-semibold"></div>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="cerrarNotificacionModal()" data-bs-dismiss="modal">
                    Entendido / Aceptar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ESCÁNER DE DOCUMENTOS PROFESIONAL (CAMSCANNER MULTIPÁGINA & REINICIO DE CÁMARA) -->
<!-- backdrop="static" + keyboard="false": sin esto, cerrar con ESC o con un clic fuera
     esquivaba cerrarEscanerPro(), dejando la cámara encendida y perdiendo el lote de
     páginas sin ningún aviso. Ahora el cierre pasa siempre por el botón X o Cancelar. -->
<div class="modal fade" id="modalEscanerDocPro" tabindex="-1" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 1090 !important;">
    <div class="modal-dialog scanner-modal-dialog">
        <div class="modal-content scanner-modal-content">
            
            <!-- El título es el NOMBRE DEL DOCUMENTO que se está escaneando, no el del
                 escáner: el orientador necesita saber qué está subiendo. El subtítulo solo
                 aparece en los flujos guiados de varias caras ("Frente" / "Reverso"). -->
            <div class="scanner-modal-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <i class="fa-solid fa-camera-retro text-info fs-5"></i>
                    <div class="overflow-hidden">
                        <h5 class="modal-title fw-bold mb-0 text-white text-truncate" id="scanner-doc-titulo">Documento</h5>
                        <small class="text-warning fw-bold d-none" id="scanner-doc-paso"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" onclick="cerrarEscanerPro()"></button>
            </div>

            <div class="scanner-modal-body">
                <!-- Alerta HTTPS para Servidor de Producción -->
                <div id="camera-https-alert" class="alert alert-warning alert-dismissible fade show d-none small mb-2">
                    <i class="fa-solid fa-lock me-1"></i> <strong>Conexión Segura Requerida:</strong> Para usar la cámara en vivo en Hostinger, ingrese por HTTPS.
                    <a href="javascript:void(0)" onclick="window.location.href = window.location.href.replace('http:', 'https:')" class="fw-bold text-dark ms-2">Clic aquí para cambiar a HTTPS</a>
                </div>

                <!-- El tipo de documento ya lo fijó la fila desde la que se abrió el escáner
                     (botón "Escanear"). Tenerlo además en un selector aquí era estado
                     duplicado y una decisión técnica que el orientador no debe tomar. -->

                <!-- VISOR ÚNICO: el mismo recuadro sirve para la cámara en vivo y para la
                     revisión del recorte; solo cambia qué capa está visible y qué controles
                     hay debajo. Así no se mueven nodos del DOM entre pantallas. -->
                <div id="paso-captura-container">
                    <div class="scanner-canvas-wrapper shadow-lg position-relative mb-2">
                        <!-- Estado de carga de librerías (OpenCV.js/jsPDF/scanner_doc.js), perezosas -->
                        <div id="scanner-libs-loading" class="d-none text-center text-white p-3">
                            <div class="spinner-border text-info mb-2" role="status"></div>
                            <div class="small">Cargando componentes del escáner...</div>
                        </div>

                        <!-- Video WebRTC en vivo -->
                        <video id="webcam-video" autoplay playsinline muted class="w-100 h-100"></video>

                        <!-- Overlay de detección de bordes EN VIVO (Fase 2): sigue el documento mientras se encuadra -->
                        <canvas id="scanner-live-overlay" class="position-absolute top-0 start-0"></canvas>

                        <!-- Overlay Canvas para ajuste interactivo de esquinas (después de capturar) -->
                        <canvas id="scanner-canvas-overlay" class="d-none position-absolute top-0 start-0"></canvas>

                        <!-- Lupa de precisión táctil -->
                        <div id="loupe-container" class="d-none">
                            <canvas id="canvas-loupe"></canvas>
                        </div>
                    </div>

                    <!-- Panel de depuración de la detección de bordes. Oculto salvo que se
                         abra la página con ?scannerdebug=1 — permite afinar los umbrales
                         geométricos con datos reales en vez de a ciegas. -->
                    <div id="scanner-debug-panel" class="d-none mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-warning fw-bold">
                                <i class="fa-solid fa-bug me-1"></i> Depuración de detección
                                <span class="text-muted">(bordes Canny · candidatos naranja · ganador verde)</span>
                            </small>
                        </div>
                        <canvas id="scanner-debug-canvas" class="w-100 rounded border border-warning" style="max-height:40vh;object-fit:contain;background:#000;"></canvas>
                    </div>

                    <!-- CONTROLES DE CAPTURA: obturador y dos accesos discretos. El aviso
                         "mantenga el documento quieto" se eliminó: lo que importa no es
                         "no lo muevas" sino "va a disparar solo", y eso lo comunica el
                         anillo de progreso sobre el documento y sobre el obturador. -->
                    <div id="controles-captura" class="text-center my-2">
                        <button type="button" class="scanner-shutter" id="btn-snap-cam"
                                onclick="capturarFotoEscaner()" title="Capturar Foto / Documento">
                            <span class="scanner-shutter-core"><i class="fa-solid fa-camera"></i></span>
                        </button>

                        <div class="d-flex justify-content-center align-items-center gap-3 mt-2">
                            <button type="button" class="scanner-link" id="btn-torch" onclick="toggleLinternaEscaner()">
                                <i class="fa-solid fa-bolt me-1"></i> Linterna
                            </button>
                            <label class="scanner-link mb-0" title="Usar una foto que ya está en el teléfono o equipo">
                                <i class="fa-solid fa-image me-1"></i> Galería / Archivo
                                <input type="file" id="input-foto-nativa" accept="image/*,application/pdf" capture="environment" class="d-none" onchange="cargarFotoNativaEscaner(event)">
                            </label>
                        </div>
                    </div>

                    <!-- CONTROLES DE REVISIÓN: solo giro, como iconos pequeños. Los presets
                         de formato se eliminaron porque el tipo de documento ya determina el
                         formato esperado, y el selector de modo de color porque el modo lo
                         decide el tipo (ver CONFIG_DOCUMENTOS). -->
                    <div id="controles-revision" class="d-none text-center">
                        <button type="button" class="scanner-icon-btn" onclick="scannerPro.rotateImage('left')" title="Girar 90° a la izquierda">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                        <button type="button" class="scanner-icon-btn" onclick="scannerPro.rotateImage('right')" title="Girar 90° a la derecha">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Canvas de salida: fuera de pantalla. Sigue siendo donde processScan()
                     deja el resultado final que se guarda en el lote, pero ya no es un paso
                     que el orientador tenga que mirar ni confirmar. -->
                <canvas id="canvas-processed" class="d-none"></canvas>

                <!-- Miniaturas: ocultas mientras el lote esté vacío, para no gastar alto -->
                <div class="mt-1 d-none" id="bloque-miniaturas">
                    <div class="pages-thumbnail-strip" id="strip-miniaturas"></div>
                </div>
            </div>

            <!-- Pie de REVISIÓN: exactamente tres acciones, sin nada más. Queda oculto
                 durante la captura para no gastar alto en pantalla de teléfono. -->
            <div class="scanner-modal-footer d-none" id="scanner-footer-revision">
                <button type="button" class="btn btn-outline-light btn-sm flex-fill" id="btn-repetir-foto" onclick="repetirFotoEscaner()">
                    <i class="fa-solid fa-arrow-rotate-left me-1"></i> Tomar de nuevo
                </button>

                <button type="button" class="btn btn-outline-info btn-sm fw-bold flex-fill" id="btn-agregar-otra-pag" onclick="guardarPaginaYOtra()">
                    <i class="fa-solid fa-plus me-1"></i> Otra página
                </button>

                <!-- Oculto mientras el lote esté vacío: un botón cuya única acción posible
                     es fallar con "tome al menos una foto" no debe existir en ese estado. -->
                <button type="button" class="btn btn-success btn-sm fw-bold flex-fill shadow d-none" id="btn-finalizar-pdf" onclick="finalizarYAdjuntarPDF()">
                    <i class="fa-solid fa-check me-1"></i> Adjuntar
                </button>
            </div>

        </div>
    </div>
</div>

<script>
let scannerPro = null;

document.addEventListener('DOMContentLoaded', () => {
    const btnBuscar = document.getElementById('btnBuscarPaciente');
    const inputDoc = document.getElementById('numero_documento');
    const selectTipoDoc = document.getElementById('tipo_documento');
    const statusSpan = document.getElementById('search-status');

    function buscarPacienteAJAX() {
        const numDoc = inputDoc.value.trim();
        const tipoDoc = selectTipoDoc.value;

        if (!numDoc) return;

        statusSpan.className = 'small text-primary';
        statusSpan.innerText = 'Buscando datos del paciente...';

        fetch(`api/get_paciente.php?tipo_doc=${tipoDoc}&num_doc=${numDoc}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success' && res.data) {
                    statusSpan.className = 'small text-success fw-bold';
                    statusSpan.innerText = '✓ Paciente registrado encontrado. Datos cargados automáticamente.';
                    autocompletarFormulario(res.data);
                    // Paciente ya conocido: mostrar su historial de atenciones.
                    mostrarHistorialPaciente(tipoDoc, numDoc);
                } else {
                    statusSpan.className = 'small text-muted';
                    statusSpan.innerText = 'Nuevo paciente. Complete los datos obligatorios (*).';
                }
            })
            .catch(err => {
                console.error("Error al buscar paciente:", err);
                statusSpan.innerText = '';
            });
    }

    if (btnBuscar) btnBuscar.addEventListener('click', buscarPacienteAJAX);
    if (inputDoc) {
        inputDoc.addEventListener('blur', () => {
            if (inputDoc.value.trim().length >= 5) {
                buscarPacienteAJAX();
            }
        });
    }

    // Validar Requisito de Soportes según modalidad de reclamación (Paciente vs Acudiente/Tercero) antes de enviar
    const formIngreso = document.getElementById('formIngreso');
    if (formIngreso) {
        formIngreso.addEventListener('submit', (e) => {
            const selectPersona = document.getElementById('select_persona_reclama');
            const valPersona = selectPersona ? selectPersona.value : '';

            if (!valPersona) {
                e.preventDefault();
                mostrarNotificacionModal(
                    'Requisito Obligatorio',
                    'Debe seleccionar en la Sección 6 si los medicamentos son reclamados por el mismo paciente o por un tercero/acudiente.',
                    'warning'
                );
                selectPersona.focus();
                return false;
            }

            const rows = document.querySelectorAll('.item-documento');
            let tieneCedula = false;
            let tieneOrden = false;
            let tieneAutorizacion = false;

            rows.forEach(r => {
                const selectCat = r.querySelector('select[name="doc_tipo_categoria[]"]');
                const inputFile = r.querySelector('input[type="file"]');

                if (selectCat && inputFile && inputFile.files && inputFile.files.length > 0) {
                    if (selectCat.value === 'CEDULA') tieneCedula = true;
                    if (selectCat.value === 'ORDEN_MEDICA') tieneOrden = true;
                    if (selectCat.value === 'AUTORIZACION') tieneAutorizacion = true;
                }
            });

            if (valPersona === 'PACIENTE_DIRECTO' && (!tieneCedula || !tieneOrden)) {
                e.preventDefault();
                let faltantes = [];
                if (!tieneCedula) faltantes.push("• 1. Cédula / Documento de Identidad del Paciente");
                if (!tieneOrden) faltantes.push("• 2. Fórmula / Orden Médica");

                mostrarNotificacionModal(
                    'Requisito Obligatorio de Soportes (Paciente Directo)',
                    `Debe adjuntar por separado los 2 soportes requeridos para reclamación directa por el paciente:<br><br><div class="text-start ps-4">${faltantes.join('<br>')}</div><br><small class="text-muted">Puede adjuntarlos seleccionándolos en archivo o escaneándolos directamente.</small>`,
                    'danger'
                );
                return false;
            }

            if (valPersona === 'TERCERO_ACUDIENTE' && (!tieneCedula || !tieneOrden || !tieneAutorizacion)) {
                e.preventDefault();
                let faltantes = [];
                if (!tieneCedula) faltantes.push("• 1. Cédula / Documento de Identidad del Paciente");
                if (!tieneOrden) faltantes.push("• 2. Fórmula / Orden Médica");
                if (!tieneAutorizacion) faltantes.push("• 3. Autorización / Doc. del Tercero o Acudiente");

                mostrarNotificacionModal(
                    'Requisito Obligatorio de Soportes (Entrega a Terceros)',
                    `Debe adjuntar por separado los 3 soportes requeridos para entrega a nombre de otra persona:<br><br><div class="text-start ps-4">${faltantes.join('<br>')}</div><br><small class="text-muted">Puede adjuntarlos seleccionándolos en archivo o escaneándolos directamente.</small>`,
                    'danger'
                );
                return false;
            }
        });
    }
});

// EVALUAR VISUALIZACIÓN DINÁMICA DE SECCIÓN DE SOPORTES Y GENERAR FILAS OBLIGATORIAS
function evaluarVisualizacionSoportes() {
    const select = document.getElementById('select_persona_reclama');
    const container = document.getElementById('seccion_soportes_container');
    const infoBox = document.getElementById('info_requisitos_reclamacion');
    const lblSub = document.getElementById('lbl_requisito_soportes_sub');
    const contenedorDocs = document.getElementById('contenedor-documentos');

    if (!select || !container || !contenedorDocs) return;

    const val = select.value;

    if (!val) {
        container.classList.add('d-none');
        infoBox.classList.add('d-none');
        contenedorDocs.innerHTML = '';
        return;
    }

    container.classList.remove('d-none');
    infoBox.classList.remove('d-none');

    if (val === 'PACIENTE_DIRECTO') {
        infoBox.className = 'alert alert-info p-3 mb-0 small fw-semibold shadow-sm border-info';
        infoBox.innerHTML = `<i class="fa-solid fa-circle-info me-1 fs-5 align-middle"></i> <strong>Reclamación Directa por el Paciente:</strong><br>Se exige adjuntar por separado <strong>2 Soportes Obligatorios</strong>: Cédula de Identidad y Fórmula / Orden Médica.`;
        lblSub.innerHTML = `<i class="fa-solid fa-circle-exclamation me-1"></i> Requisito Obligatorio (2 Soportes): Se exige Cédula y Fórmula Médica por separado.`;
        
        generarFilasSoportes(['CEDULA', 'ORDEN_MEDICA']);
    } else if (val === 'TERCERO_ACUDIENTE') {
        infoBox.className = 'alert alert-warning p-3 mb-0 small fw-semibold shadow-sm border-warning';
        infoBox.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-1 fs-5 align-middle"></i> <strong>Entrega a Nombre de Otra Persona (Tercero/Acudiente):</strong><br>Se exige adjuntar por separado <strong>3 Soportes Obligatorios</strong>: Cédula del paciente, Fórmula Médica y Autorización / Doc. del Tercero.`;
        lblSub.innerHTML = `<i class="fa-solid fa-circle-exclamation me-1"></i> Requisito Obligatorio (3 Soportes): Se exige Cédula, Fórmula Médica y Autorización/Doc. del Tercero por separado.`;
        
        generarFilasSoportes(['CEDULA', 'ORDEN_MEDICA', 'AUTORIZACION']);
    }
}

function generarFilasSoportes(tiposRequeridos) {
    const contenedorDocs = document.getElementById('contenedor-documentos');
    if (!contenedorDocs) return;

    contenedorDocs.innerHTML = '';

    tiposRequeridos.forEach((tipoTag, index) => {
        let labelText = '';
        let iconClass = '';
        let defaultOpt = tipoTag;

        if (tipoTag === 'CEDULA') {
            labelText = `${index + 1}. Cédula / Doc. Identidad del Paciente`;
            iconClass = 'fa-id-card';
        } else if (tipoTag === 'ORDEN_MEDICA') {
            labelText = `${index + 1}. Fórmula / Orden Médica`;
            iconClass = 'fa-file-medical';
        } else if (tipoTag === 'AUTORIZACION') {
            labelText = `${index + 1}. Autorización de Acudiente / Tercero & Doc. Identidad`;
            iconClass = 'fa-file-signature';
        }

        const divRow = document.createElement('div');
        divRow.className = 'card border-primary border-1 mb-3 item-documento bg-white p-3 shadow-sm';
        divRow.innerHTML = `
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-primary mb-1">
                        <i class="fa-solid ${iconClass} me-1"></i> ${labelText} <span class="text-danger">*</span>
                    </label>
                    <!-- Señal de auditoría: 1 si el orientador tuvo que corregir el recorte a mano.
                         La escribe finalizarYAdjuntarPDF() al adjuntar el PDF. -->
                    <input type="hidden" name="doc_recorte_manual[]" class="input-recorte-manual" value="0">
                    <select name="doc_tipo_categoria[]" class="form-select form-select-sm fw-bold border-primary select-doc-cat">
                        <option value="CEDULA" ${defaultOpt === 'CEDULA' ? 'selected' : ''}>Cédula / Doc. Identidad *</option>
                        <option value="ORDEN_MEDICA" ${defaultOpt === 'ORDEN_MEDICA' ? 'selected' : ''}>Fórmula / Orden Médica *</option>
                        <option value="AUTORIZACION" ${defaultOpt === 'AUTORIZACION' ? 'selected' : ''}>Autorización / Doc. Tercero *</option>
                        <option value="HISTORIA_CLINICA">Historia Clínica / Anexo</option>
                        <option value="OTRO">Otro Documento</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold small mb-1">Adjuntar Archivo desde Dispositivo (PDF/JPG):</label>
                    <input type="file" name="doc_archivos[]" class="form-control form-control-sm input-doc-file" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="status-doc-adjunto mt-1"></div>
                </div>
                <div class="col-md-3 text-end">
                    <button type="button" class="btn btn-sm btn-success fw-bold w-100 mb-1" onclick="abrirEscanerDesdeFila(this)">
                        <i class="fa-solid fa-camera me-1"></i> Escanear (Pro)
                    </button>
                </div>
            </div>
        `;
        contenedorDocs.appendChild(divRow);
    });
}

// MOSTRAR NOTIFICACIONES FLOTANTES CON BOOTSTRAP MODAL
function mostrarNotificacionModal(titulo, mensajeHtml, tipo = 'success') {
    const modalEl = document.getElementById('modalNotificacionEscaner');
    const headerEl = document.getElementById('modalNotifHeader');
    const titleEl = document.getElementById('modalNotifTitle');
    const iconEl = document.getElementById('modalNotifIcon');
    const msgEl = document.getElementById('modalNotifMessage');

    if (!modalEl) {
        alert(mensajeHtml.replace(/<[^>]*>?/gm, ''));
        return;
    }

    if (tipo === 'success') {
        headerEl.className = 'modal-header bg-success text-white py-2';
        titleEl.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i> ${titulo || '¡Proceso Exitoso!'}`;
        iconEl.innerHTML = `<i class="fa-solid fa-circle-check text-success"></i>`;
    } else if (tipo === 'warning') {
        headerEl.className = 'modal-header bg-warning text-dark py-2';
        titleEl.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-2"></i> ${titulo || 'Atención'}`;
        iconEl.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-warning"></i>`;
    } else if (tipo === 'danger' || tipo === 'error') {
        headerEl.className = 'modal-header bg-danger text-white py-2';
        titleEl.innerHTML = `<i class="fa-solid fa-circle-xmark me-2"></i> ${titulo || 'Requisito Obligatorio'}`;
        iconEl.innerHTML = `<i class="fa-solid fa-circle-xmark text-danger"></i>`;
    } else {
        headerEl.className = 'modal-header bg-primary text-white py-2';
        titleEl.innerHTML = `<i class="fa-solid fa-info-circle me-2"></i> ${titulo || 'Información'}`;
        iconEl.innerHTML = `<i class="fa-solid fa-circle-info text-primary"></i>`;
    }

    msgEl.innerHTML = mensajeHtml;

    try {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
        modal.show();
    } catch (e) {
        alert(mensajeHtml.replace(/<[^>]*>?/gm, ''));
    }
}

function cerrarNotificacionModal() {
    const modalEl = document.getElementById('modalNotificacionEscaner');
    if (!modalEl) return;

    try {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.hide();
        } else {
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
        }
    } catch (e) {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
    }

    // Asegurar que si el modal del escáner sigue visible, el cuerpo conserve la clase modal-open
    setTimeout(() => {
        const escanerModalEl = document.getElementById('modalEscanerDocPro');
        if (escanerModalEl && (escanerModalEl.classList.contains('show') || escanerModalEl.style.display === 'block')) {
            document.body.classList.add('modal-open');
        }
    }, 150);
}

document.addEventListener('DOMContentLoaded', () => {
    const notifModalEl = document.getElementById('modalNotificacionEscaner');
    if (notifModalEl) {
        notifModalEl.addEventListener('show.bs.modal', function () {
            notifModalEl.style.zIndex = '1090';
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length > 1) {
                    const lastBackdrop = backdrops[backdrops.length - 1];
                    lastBackdrop.style.zIndex = '1085';
                }
            }, 10);
        });

        notifModalEl.addEventListener('hidden.bs.modal', function () {
            const escanerModalEl = document.getElementById('modalEscanerDocPro');
            if (escanerModalEl && (escanerModalEl.classList.contains('show') || escanerModalEl.style.display === 'block')) {
                document.body.classList.add('modal-open');
            }
        });
    }
});

function autocompletarFormulario(data) {
    const mapFields = [
        'primer_apellido', 'segundo_apellido', 'primer_nombre', 'segundo_nombre',
        'fecha_nacimiento', 'ciudad_expedicion', 'estado', 'sexo', 'estado_civil',
        'pais_nacimiento', 'nacionalidad', 'ciudad_nacimiento', 'identidad_genero',
        'direccion_residencia', 'indicativo_1', 'numero_celular', 'indicativo_2',
        'otro_telefono', 'email', 'ciudad_residencia', 'zona', 'barrio',
        'direccion_laboral', 'telefono_laboral', 'eps_nombre', 'plan_salud',
        'tipo_afiliado', 'nivel_socioeconomico', 'estrato_socioeconomico',
        'ocupacion', 'grupo_sanguineo', 'sede_atencion', 'ips_primaria', 'ips_remite',
        'municipio_afiliacion', 'fecha_sgsss', 'fecha_afiliacion', 'empleador',
        'grupo_poblacional', 'grupo_etnico', 'comunidad_etnica', 'tipo_discapacidad',
        'tipo_escolaridad', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
        'contacto_emergencia_parentesco'
    ];

    mapFields.forEach(field => {
        const el = document.getElementById(field);
        if (el && data[field] !== undefined && data[field] !== null) {
            el.value = data[field];
        }
    });

    if (!data.primer_apellido && data.apellidos) {
        const partsA = data.apellidos.trim().split(' ');
        document.getElementById('primer_apellido').value = partsA[0] || '';
        document.getElementById('segundo_apellido').value = partsA.slice(1).join(' ') || '';
    }
    if (!data.primer_nombre && data.nombres) {
        const partsN = data.nombres.trim().split(' ');
        document.getElementById('primer_nombre').value = partsN[0] || '';
        document.getElementById('segundo_nombre').value = partsN.slice(1).join(' ') || '';
    }

    if (document.getElementById('actualiza_citas_plan')) {
        document.getElementById('actualiza_citas_plan').checked = (data.actualiza_citas_plan == 1);
    }
}

function agregarFilaDoc() {
    const container = document.getElementById('contenedor-documentos');
    const div = document.createElement('div');
    div.className = 'card border-secondary border-1 mb-3 item-documento bg-white p-3 shadow-sm';
    div.innerHTML = `
        <div class="row align-items-center g-2">
            <div class="col-md-4">
                <label class="form-label fw-bold text-dark mb-1">Categoría del Soporte:</label>
                <input type="hidden" name="doc_recorte_manual[]" class="input-recorte-manual" value="0">
                <select name="doc_tipo_categoria[]" class="form-select form-select-sm select-doc-cat">
                    <option value="CEDULA">Cédula / Doc. Identidad</option>
                    <option value="ORDEN_MEDICA">Fórmula / Orden Médica</option>
                    <option value="AUTORIZACION" selected>Autorización de Servicios</option>
                    <option value="HISTORIA_CLINICA">Historia Clínica / Anexo</option>
                    <option value="OTRO">Otro Documento</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold small mb-1">Adjuntar Archivo (PDF/JPG):</label>
                <input type="file" name="doc_archivos[]" class="form-control form-control-sm input-doc-file" accept=".pdf,.jpg,.jpeg,.png">
                <div class="status-doc-adjunto mt-1"></div>
            </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-sm btn-outline-success fw-bold w-100 mb-1" onclick="abrirEscanerDesdeFila(this)">
                    <i class="fa-solid fa-camera me-1"></i> Escanear este Soporte
                </button>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="eliminarFilaDoc(this)"><i class="fa-solid fa-trash me-1"></i> Eliminar</button>
            </div>
        </div>
    `;
    container.appendChild(div);
}

function eliminarFilaDoc(btn) {
    const item = btn.closest('.item-documento');
    if (document.querySelectorAll('.item-documento').length > 1) {
        item.remove();
    } else {
        mostrarNotificacionModal('Atención', 'Debe conservar al menos un soporte de documento en la lista.', 'warning');
    }
}

// ============================================================================
// HISTORIAL DE ATENCIONES DEL PACIENTE
// ============================================================================

// Evita reabrir el modal si el campo pierde el foco varias veces con el mismo documento
// (el buscador se dispara también en 'blur').
let _ultimoHistorialMostrado = null;

async function mostrarHistorialPaciente(tipoDoc, numDoc) {
    const clave = `${tipoDoc}|${numDoc}`;
    if (_ultimoHistorialMostrado === clave) return;

    let res;
    try {
        const r = await fetch(`api/historial_paciente.php?tipo_doc=${encodeURIComponent(tipoDoc)}&num_doc=${encodeURIComponent(numDoc)}`);
        res = await r.json();
    } catch (e) {
        console.warn('[Historial] No se pudo consultar el historial:', e);
        return;
    }

    // Paciente sin atenciones previas: no hay nada que mostrar, no se interrumpe.
    if (res.status !== 'success' || !res.atenciones || res.atenciones.length === 0) return;

    _ultimoHistorialMostrado = clave;

    document.getElementById('historialPacienteNombre').textContent =
        `${res.paciente.nombre} · Doc. ${res.paciente.documento}`;

    // Posible dispensación duplicada: los crónicos son mensuales, volver antes es raro.
    const alerta = document.getElementById('historialAlertaDuplicado');
    if (res.alerta_duplicado) {
        alerta.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-2"></i>
            POSIBLE DISPENSACIÓN DUPLICADA — ${res.alerta_duplicado.mensaje}
            <span class="fw-normal">(turno ${res.alerta_duplicado.turno}, ${res.alerta_duplicado.fecha})</span>`;
        alerta.classList.remove('d-none');
    } else {
        alerta.classList.add('d-none');
    }

    document.getElementById('historialResumen').innerHTML = res.incompletas > 0
        ? `<span class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>
           ${res.incompletas} de ${res.total} atención(es) quedaron incompletas.</span>
           Aproveche que el paciente está presente para subsanarlas.`
        : `${res.total} atención(es) previas, todas completas.`;

    const filas = res.atenciones.map((a, i) => {
        const detalle = a.faltantes.length
            ? `<div class="small text-danger">Falta: ${a.faltantes.join(', ')}</div>` : '';
        return `
            <tr class="hist-${a.clase}">
                <td class="text-muted">${i + 1}</td>
                <td class="fw-semibold">${a.fecha}</td>
                <td><span class="badge bg-secondary">${a.turno}</span></td>
                <td>
                    <span class="hist-estado hist-${a.clase}">${a.etiqueta}</span>
                    ${detalle}
                </td>
            </tr>`;
    }).join('');

    document.getElementById('historialTabla').innerHTML = `
        <table class="table table-sm align-middle mb-0">
            <thead><tr class="table-light">
                <th style="width:2.5rem">#</th><th>Fecha</th><th>Turno</th><th>Estado</th>
            </tr></thead>
            <tbody>${filas}</tbody>
        </table>`;

    try {
        const el = document.getElementById('modalHistorialPaciente');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    } catch (e) {
        console.warn('[Historial] No se pudo abrir el modal:', e);
    }
}

// LÓGICA DEL MODAL DE ESCÁNER PROFESIONAL

// Fila de "7. Documentos" desde la que se abrió el escáner: el PDF resultante se adjunta
// exactamente ahí, en vez de buscar a ciegas la primera fila de la misma categoría.
let _filaEscanerOrigen = null;

/**
 * Comportamiento del escáner por tipo de documento.
 *
 * Está aquí, como configuración, y no repartido en condicionales por el código: el orientador
 * no debe tomar ninguna decisión técnica (ni modo de color, ni formato de recorte), así que
 * todas esas decisiones se declaran una sola vez por tipo. Si mañana cambia una regla de
 * negocio —por ejemplo, que baste una sola cara de la cédula— se cambia el número aquí y el
 * paso guiado se desactiva solo, sin tocar lógica ni arriesgar regresiones.
 *
 *  - paginasEsperadas: número de capturas del flujo guiado. null = multipágina libre
 *                      (el orientador decide cuántas con "Otra página").
 *  - realce:           'ninguno'      → solo perspectiva y recorte, colores tal cual salen
 *                                        de la cámara (documentos plastificados y a color).
 *                      'documento-bn' → normalización de fondo + binarización, para hoja
 *                                        blanca con texto negro.
 *  - composicion:      'una-por-pagina' | 'ambas-caras-una-pagina'.
 *  - rotulos:          subtítulos del flujo guiado, uno por captura esperada.
 */
const CONFIG_DOCUMENTOS = {
    // SIN realce. El pipeline de realce (división de fondo + CLAHE + unsharp) está pensado
    // para papel blanco con texto negro; sobre un documento plastificado y a color aplana
    // la foto del titular, se come el holograma y puede volver ilegible el número — que es
    // motivo de glosa al radicar ante la EPS.
    CEDULA: {
        titulo: 'Cédula / Documento de Identidad',
        paginasEsperadas: 2,
        realce: 'ninguno',
        composicion: 'ambas-caras-una-pagina',
        rotulos: ['Frente', 'Reverso']
    },
    // Fórmula médica: sin realce agresivo para preservar sellos, firmas a tinta y notas
    ORDEN_MEDICA: {
        titulo: 'Fórmula / Orden Médica',
        paginasEsperadas: null,
        realce: 'ninguno',
        composicion: 'una-por-pagina'
    },
    // Hoja de poder con la que el paciente autoriza a un tercero a reclamar
    AUTORIZACION: {
        titulo: 'Autorización / Poder del Tercero',
        paginasEsperadas: null,
        realce: 'ninguno',
        composicion: 'una-por-pagina'
    },
    // Los tipos sin regla propia van sin realce: es el valor que nunca destruye información
    HISTORIA_CLINICA: {
        titulo: 'Historia Clínica / Anexo',
        paginasEsperadas: null,
        realce: 'ninguno',
        composicion: 'una-por-pagina'
    },
    OTRO: {
        titulo: 'Otro Documento',
        paginasEsperadas: null,
        realce: 'ninguno',
        composicion: 'una-por-pagina'
    }
};

// Traduce el realce declarado al modo interno del motor. `null` = no aplicar nada.
function filtroDeRealce(realce) {
    return (realce === 'documento-bn' || realce === 'binary') ? 'binary' : null;
}

function configDoc(categoria) {
    return CONFIG_DOCUMENTOS[categoria] || CONFIG_DOCUMENTOS.OTRO;
}

// Tipo de documento del escaneo en curso.
let _categoriaEscaner = 'ORDEN_MEDICA';

/**
 * Abre el escáner desde el botón de una fila, tomando la categoría del propio selector de esa fila.
 */
function abrirEscanerDesdeFila(btn) {
    const fila = btn ? btn.closest('.item-documento') : null;
    const sel = fila ? fila.querySelector('.select-doc-cat') : null;
    abrirEscanerProModal(sel ? sel.value : 'ORDEN_MEDICA', fila);
}

async function abrirEscanerProModal(categoriaTarget = 'ORDEN_MEDICA', filaOrigen = null) {
    _filaEscanerOrigen = filaOrigen;
    _capturaEnCurso = false;

    // Limpieza preventiva total de backdrops residuales
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('pointer-events');

    const modalEl = document.getElementById('modalEscanerDocPro');
    if (!modalEl) {
        mostrarNotificacionModal('Error', 'No se encontró la ventana modal del escáner.', 'danger');
        return;
    }

    // El tipo llega de la fila que abrió el escáner
    _categoriaEscaner = categoriaTarget || 'ORDEN_MEDICA';
    _capturasDelFlujo = 0;
    aplicarTituloEscaner();

    const btnSnap = document.getElementById('btn-snap-cam');
    if (btnSnap) {
        btnSnap.disabled = false;
        btnSnap.classList.remove('d-none');
    }
    if (scannerPro) scannerPro.setDocType(_categoriaEscaner);

    // 1. Mostrar Modal usando Bootstrap o Fallback Directo DOM
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            document.body.classList.add('modal-open');
        }
    } catch (e) {
        console.warn("Bootstrap JS no respondió, usando apertura directa:", e);
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        document.body.classList.add('modal-open');
    }

    // 2. Cargar librerías del escáner de forma perezosa (solo la primera vez en esta página)
    if (typeof DocumentScannerPro === 'undefined') {
        const loadingEl = document.getElementById('scanner-libs-loading');
        if (loadingEl) loadingEl.classList.remove('d-none');

        try {
            await ensureScannerLibsLoaded();
        } catch (err) {
            console.error("[Scanner] Error cargando librerías del escáner:", err);
        } finally {
            if (loadingEl) loadingEl.classList.add('d-none');
        }
    }

    // 3. Reiniciar escáner anterior para nuevo lote de páginas
    if (scannerPro) {
        scannerPro.resetDoc();
        actualizarTiraMiniaturas();
    }

    // 4. Iniciar componente de escaneo
    reiniciarCamaraEscaner();
}

/**
 * Cierre del escáner. Si hay páginas capturadas sin adjuntar, se confirma antes: el lote
 * vive solo en memoria del navegador y cerrar sin finalizar lo descarta por completo.
 * @param {boolean} forzar  true al finalizar y adjuntar (ahí no hay nada que perder)
 */
async function cerrarEscanerPro(forzar = false) {
    if (!forzar && scannerPro && scannerPro.scannedPages && scannerPro.scannedPages.length > 0) {
        const n = scannerPro.scannedPages.length;
        const ok = await confirmarEscaner(
            'Descartar páginas escaneadas',
            `Hay <strong>${n} página(s)</strong> escaneada(s) que todavía no se han adjuntado a ningún soporte.<br><br>Si cierra ahora se <strong>pierden</strong>. Para conservarlas use <strong>"Finalizar &amp; Adjuntar a este Soporte"</strong>.`,
            'Cerrar y descartar'
        );
        if (!ok) return;
    }

    if (scannerPro) {
        try { scannerPro.stopCamera(); } catch (e) {}
    }

    const modalEl = document.getElementById('modalEscanerDocPro');
    if (modalEl) {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
        } catch (e) {}

        modalEl.style.display = 'none';
        modalEl.classList.remove('show');
    }

    // Limpieza radical de backdrop para garantizar que ningún botón quede bloqueado
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('pointer-events');

    // Desbloquear cualquier elemento de la interfaz
    document.querySelectorAll('button, input, select').forEach(el => {
        el.style.removeProperty('pointer-events');
    });
}

// Capturas hechas dentro del flujo guiado del documento actual (p. ej. frente y reverso
// de la cédula). Se reinicia al abrir el escáner y al adjuntar.
let _capturasDelFlujo = 0;

/** Título del modal = nombre del documento; subtítulo = paso del flujo guiado, si aplica. */
function aplicarTituloEscaner() {
    const cfg = configDoc(_categoriaEscaner);
    const elTitulo = document.getElementById('scanner-doc-titulo');
    const elPaso = document.getElementById('scanner-doc-paso');
    if (elTitulo) elTitulo.textContent = cfg.titulo;

    if (!elPaso) return;
    const rotulo = (cfg.rotulos && cfg.paginasEsperadas && _capturasDelFlujo < cfg.paginasEsperadas)
        ? cfg.rotulos[_capturasDelFlujo]
        : null;

    if (rotulo) {
        elPaso.textContent = `${rotulo} — ${_capturasDelFlujo + 1} de ${cfg.paginasEsperadas}`;
        elPaso.classList.remove('d-none');
    } else {
        elPaso.textContent = '';
        elPaso.classList.add('d-none');
    }
}

/** Muestra la cámara en vivo y oculta todo lo de revisión. */
function mostrarPantallaCaptura() {
    document.getElementById('scanner-canvas-overlay').classList.add('d-none');
    document.getElementById('scanner-live-overlay').classList.remove('d-none');
    document.getElementById('webcam-video').classList.remove('d-none');
    document.getElementById('controles-captura').classList.remove('d-none');
    document.getElementById('controles-revision').classList.add('d-none');
    document.getElementById('scanner-footer-revision').classList.add('d-none');
    const btnSnap = document.getElementById('btn-snap-cam');
    if (btnSnap) btnSnap.classList.remove('d-none');
    aplicarTituloEscaner();
}

/** Muestra el recorte sobre la foto ya realzada, con las esquinas arrastrables. */
function mostrarPantallaRevision() {
    document.getElementById('webcam-video').classList.add('d-none');
    document.getElementById('scanner-live-overlay').classList.add('d-none');
    
    const canvasOverlay = document.getElementById('scanner-canvas-overlay');
    if (canvasOverlay) canvasOverlay.classList.remove('d-none');
    
    document.getElementById('controles-captura').classList.add('d-none');
    document.getElementById('controles-revision').classList.remove('d-none');
    document.getElementById('scanner-footer-revision').classList.remove('d-none');
    
    // Redibujar el recorte y la imagen en el canvas ya visible
    if (scannerPro) {
        requestAnimationFrame(() => {
            scannerPro.drawOverlay();
        });
    }

    actualizarBotonesRevision();
}

/**
 * "Adjuntar" solo existe cuando hay algo que adjuntar: contando el lote guardado más la
 * página que esté en revisión sin guardar. Un botón cuya única acción posible es fallar
 * con "tome al menos una foto" no debe estar visible.
 */
function actualizarBotonesRevision() {
    if (!scannerPro) return;
    const hayAlgo = scannerPro.scannedPages.length > 0 || !!scannerPro.rawImage;
    document.getElementById('btn-finalizar-pdf').classList.toggle('d-none', !hayAlgo);
    aplicarTituloEscaner();
}

function reiniciarCamaraEscaner() {
    if (scannerPro) {
        scannerPro.rawImage = null;
        scannerPro.previewImage = null;
    }
    mostrarPantallaCaptura();
    iniciarCamaraEscaner();
}

/** "Tomar de nuevo": descarta la captura en revisión y vuelve a la cámara, mismo paso. */
function repetirFotoEscaner() {
    if (scannerPro) {
        scannerPro.rawImage = null;
        scannerPro.previewImage = null;
    }
    mostrarPantallaCaptura();
    iniciarCamaraEscaner();
}

async function iniciarCamaraEscaner() {
    if (typeof DocumentScannerPro === 'undefined') return;
    if (!scannerPro) scannerPro = new DocumentScannerPro();

    // Auto-captura (Fase 3): el detector avisa cuando el documento estuvo quieto lo
    // suficiente; se reutiliza exactamente el mismo flujo del botón manual.
    scannerPro.onAutoCaptureRequested = () => { capturarFotoEscaner(); };

    // El tipo de documento restringe la proporción que la detección acepta como válida.
    scannerPro.setDocType(_categoriaEscaner);
    aplicarModoDepuracionEscaner();

    // Progreso de la auto-captura, también sobre el obturador: el anillo sobre el documento
    // puede quedar fuera de la mirada cuando el pulgar ya está sobre el botón.
    scannerPro.onCountdownProgress = (p) => {
        const b = document.getElementById('btn-snap-cam');
        if (b) {
            b.style.setProperty('--progreso', (p * 360).toFixed(1) + 'deg');
            b.classList.toggle('cargando', p > 0);
        }
    };

    const success = await scannerPro.startCamera();

    const btnSnap = document.getElementById('btn-snap-cam');
    const btnTorch = document.getElementById('btn-torch');

    if (btnSnap) {
        btnSnap.disabled = false;
        btnSnap.classList.remove('d-none');
    }
    if (btnTorch) btnTorch.classList.toggle('d-none', !(scannerPro && scannerPro.torchSupported));
}

let _capturaEnCurso = false;

async function capturarFotoEscaner() {
    if (!scannerPro) scannerPro = new DocumentScannerPro();

    // Si la cámara no pudo iniciar stream (sin permisos o sin cámara web), permitir subir desde galería/archivo
    if (!scannerPro.stream) {
        const inputNativo = document.getElementById('input-foto-nativa');
        if (inputNativo) {
            inputNativo.click();
            return;
        }
    }

    // Evita capturar dos veces si la auto-captura y el botón manual coinciden.
    if (_capturaEnCurso) return;
    _capturaEnCurso = true;

    const btnSnap = document.getElementById('btn-snap-cam');
    if (btnSnap) btnSnap.disabled = true;

    try {
        await scannerPro.takeSnapshot();
        await trasCapturar();
    } catch (err) {
        console.error("[Scanner] Error en capturarFotoEscaner:", err);
    } finally {
        if (btnSnap) btnSnap.disabled = false;
        _capturaEnCurso = false;
    }
}

/**
 * Qué pasa justo después de capturar.
 * En los tipos con flujo guiado (cédula: frente y reverso) las capturas intermedias NO
 * paran en la pantalla de revisión — se guardan y la cámara sigue activa con el rótulo
 * cambiado para capturar el reverso de inmediato.
 */
async function trasCapturar() {
    if (!scannerPro || !scannerPro.rawImage) {
        console.warn("[Scanner] No hay rawImage tras captura.");
        return;
    }

    const cfg = configDoc(_categoriaEscaner);

    // Realce del previo: lo que se revisa es la foto ya realzada, no la foto cruda.
    try {
        await scannerPro.construirPreviewRealzado(filtroDeRealce(cfg.realce));
    } catch (e) {
        console.warn("[Scanner] Preview error:", e);
    }

    _capturasDelFlujo++;

    const faltanCaras = cfg.paginasEsperadas && _capturasDelFlujo < cfg.paginasEsperadas;
    if (faltanCaras) {
        // Guardar esta cara y continuar con la cámara viva para la siguiente
        scannerPro.processScan(filtroDeRealce(cfg.realce), _categoriaEscaner);
        scannerPro.saveCurrentPageToDoc();
        scannerPro.rawImage = null;
        scannerPro.previewImage = null;
        actualizarTiraMiniaturas();
        mostrarPantallaCaptura();
        // Si el stream de la cámara se hubiese detenido por alguna razón, reiniciarla
        if (!scannerPro.stream) {
            await iniciarCamaraEscaner();
        }
        return;
    }

    // Si ya completó todas las caras esperadas (o documento de 1 página), detener cámara y pasar a revisión
    try { scannerPro.stopCamera(); } catch (e) {}
    mostrarPantallaRevision();
}

function cargarFotoNativaEscaner(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        const img = new Image();
        img.onload = async () => {
            if (!scannerPro) scannerPro = new DocumentScannerPro();
            scannerPro.stopCamera();
            scannerPro._lastCaptureMethod = 'archivo nativo/galería';
            scannerPro.loadCapturedImage(img);
            await trasCapturar();
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    event.target.value = '';
}

// El modo depuración se activa abriendo la página con ?scannerdebug=1.
function escanerDebugActivo() {
    try {
        return new URLSearchParams(window.location.search).get('scannerdebug') === '1';
    } catch (e) {
        return false;
    }
}

function aplicarModoDepuracionEscaner() {
    const activo = escanerDebugActivo();
    const panel = document.getElementById('scanner-debug-panel');
    if (panel) panel.classList.toggle('d-none', !activo);
    if (scannerPro) {
        scannerPro.setDebugMode(activo, document.getElementById('scanner-debug-canvas'));
    }
}

/**
 * Guarda la página en revisión y vuelve a la cámara para la siguiente.
 * El recorte y el realce se aplican aquí, con el modo que fija el tipo de documento.
 */
async function guardarPaginaYOtra() {
    if (!scannerPro) return;

    if (scannerPro.rawImage) {
        const cfg = configDoc(_categoriaEscaner);
        scannerPro.processScan(filtroDeRealce(cfg.realce), _categoriaEscaner);
        scannerPro.saveCurrentPageToDoc();
        scannerPro.rawImage = null;   // Prevenir duplicación de página
        scannerPro.previewImage = null;
        actualizarTiraMiniaturas();
    }

    // Salir del flujo guiado: a partir de aquí el orientador decide cuántas páginas más.
    const cfg = configDoc(_categoriaEscaner);
    if (cfg.paginasEsperadas) _capturasDelFlujo = cfg.paginasEsperadas;

    mostrarPantallaCaptura();
    await iniciarCamaraEscaner();
}

function actualizarTiraMiniaturas() {
    if (!scannerPro) return;
    const strip = document.getElementById('strip-miniaturas');
    const bloque = document.getElementById('bloque-miniaturas');
    const pages = scannerPro.scannedPages;

    // La tira solo ocupa alto cuando hay algo que mostrar: en pantalla de teléfono cada
    // bloque permanente es scroll que el orientador tiene que hacer en cada paciente.
    if (bloque) bloque.classList.toggle('d-none', pages.length === 0);
    if (pages.length === 0) {
        strip.innerHTML = '';
        return;
    }

    let html = '';
    pages.forEach((p, idx) => {
        // El orden del arreglo es el orden de las hojas del PDF, así que las flechas
        // reordenan de verdad el documento final, no solo la vista.
        const flechaIzq = idx > 0
            ? `<button type="button" class="page-thumb-move izq" title="Mover antes" onclick="moverPaginaEscaner(${idx}, -1)"><i class="fa-solid fa-chevron-left"></i></button>`
            : '';
        const flechaDer = idx < pages.length - 1
            ? `<button type="button" class="page-thumb-move der" title="Mover después" onclick="moverPaginaEscaner(${idx}, 1)"><i class="fa-solid fa-chevron-right"></i></button>`
            : '';

        html += `
            <div class="page-thumb-item ${idx === scannerPro.currentPageIndex ? 'active' : ''}">
                <img src="${p.dataUrl}" title="Clic para ver la página completa" onclick="verPaginaEscaner(${idx})">
                <span class="page-thumb-badge">Pág ${idx + 1}</span>
                <button type="button" class="page-thumb-delete" title="Eliminar página" onclick="borrarPaginaEscaner(${idx})"><i class="fa-solid fa-xmark"></i></button>
                ${flechaIzq}${flechaDer}
            </div>
        `;
    });
    strip.innerHTML = html;
}

async function borrarPaginaEscaner(index) {
    if (!scannerPro) return;
    // Borrar una página capturada es irreversible (no hay historial de deshacer), así que
    // se confirma en vez de descartarla al primer toque.
    const ok = await confirmarEscaner(
        'Eliminar página',
        `¿Eliminar la <strong>página ${index + 1}</strong> de este lote? No se puede deshacer.`,
        'Eliminar'
    );
    if (!ok) return;
    scannerPro.deletePage(index);
    actualizarTiraMiniaturas();
}

function moverPaginaEscaner(index, delta) {
    if (!scannerPro) return;
    scannerPro.movePage(index, delta);
    actualizarTiraMiniaturas();
}

/**
 * Documento de identidad: las dos caras apiladas verticalmente en UNA sola página, que es
 * el formato de la fotocopia de toda la vida y lo que el radicador de la EPS espera.
 *
 * Orden fijo: frente arriba, reverso abajo — lo garantiza el flujo guiado, que captura en
 * ese orden. Si solo hay una cara (el orientador salió antes, o falló el reverso) se
 * renderiza esa sola, centrada verticalmente, en vez de dejar media hoja en blanco.
 */
function componerAmbasCaras(jsPDF, paginas, anchoPag, altoPag) {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const caras = paginas.slice(0, 2);

    // ~68% del ancho de página: prioriza que el número de cédula se lea sin ampliar por
    // encima de reproducir el tamaño físico real de la tarjeta.
    const anchoCara = anchoPag * 0.68;
    const SEPARACION = 12;

    const alturas = caras.map(p => anchoCara * (p.height / p.width));
    const altoTotal = alturas.reduce((a, b) => a + b, 0) + SEPARACION * (caras.length - 1);

    // Si por proporciones no cupieran, se reduce todo en bloque para no recortar nada.
    const disponible = altoPag - 24;
    const ajuste = altoTotal > disponible ? (disponible / altoTotal) : 1;

    const x = (anchoPag - anchoCara * ajuste) / 2;
    let y = (altoPag - altoTotal * ajuste) / 2;

    caras.forEach((p, i) => {
        const w = anchoCara * ajuste;
        const h = alturas[i] * ajuste;
        doc.addImage(p.dataUrl, 'JPEG', x, y, w, h);
        y += h + SEPARACION * ajuste;
    });

    // Cualquier página extra (el orientador añadió más con "Otra página") sigue el formato
    // normal de una imagen por hoja, para no perderla.
    paginas.slice(2).forEach(page => {
        const orientacion = (page.width > page.height) ? 'landscape' : 'portrait';
        doc.addPage('a4', orientacion);
        const aP = (orientacion === 'landscape') ? altoPag : anchoPag;
        const hP = (orientacion === 'landscape') ? anchoPag : altoPag;
        const esc = Math.min((aP - 16) / page.width, (hP - 16) / page.height);
        const w = page.width * esc, h = page.height * esc;
        doc.addImage(page.dataUrl, 'JPEG', (aP - w) / 2, (hP - h) / 2, w, h);
    });

    return doc;
}

/**
 * Aviso efímero que se desvanece solo. Reemplaza al modal de "¡Escaneo finalizado!" con
 * botón Aceptar: ese clic obligatorio se pagaba 2-3 veces por paciente y no aportaba nada,
 * porque la confirmación que el orientador consulta de verdad es el estado de la fila.
 */
function mostrarToastEscaner(mensajeHtml, ms = 2600) {
    let cont = document.getElementById('toast-escaner-cont');
    if (!cont) {
        cont = document.createElement('div');
        cont.id = 'toast-escaner-cont';
        document.body.appendChild(cont);
    }

    const t = document.createElement('div');
    t.className = 'toast-escaner';
    t.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i><div>${mensajeHtml}</div>`;
    cont.appendChild(t);

    requestAnimationFrame(() => t.classList.add('visible'));
    setTimeout(() => {
        t.classList.remove('visible');
        setTimeout(() => t.remove(), 300);
    }, ms);
}

/** Marca la fila del soporte como cargada: es la confirmación persistente y consultable. */
function marcarFilaAdjunta(fila, nPaginas, pesoMb) {
    if (!fila) return;
    fila.classList.add('doc-adjunto-ok');

    const btn = fila.querySelector('button[onclick^="abrirEscanerDesdeFila"]');
    if (btn) {
        btn.classList.remove('btn-success', 'btn-outline-success');
        btn.classList.add('btn-outline-secondary');
        btn.innerHTML = '<i class="fa-solid fa-rotate-right me-1"></i> Volver a escanear';
    }

    const estado = fila.querySelector('.status-doc-adjunto');
    if (estado) {
        estado.innerHTML = `
            <div class="alert alert-success p-1 px-2 mb-0 small fw-bold d-flex align-items-center gap-1">
                <i class="fa-solid fa-circle-check"></i>
                <span>Cargado · ${nPaginas} pág. · ${pesoMb} MB</span>
            </div>`;
    }
}

function verPaginaEscaner(index) {
    if (!scannerPro) return;
    const page = scannerPro.getPage(index);
    if (!page) return;

    const img = document.getElementById('previewPaginaImg');
    const titulo = document.getElementById('previewPaginaTitulo');
    const info = document.getElementById('previewPaginaInfo');
    if (!img) return;

    img.src = page.dataUrl;
    if (titulo) titulo.textContent = `Página ${index + 1} de ${scannerPro.scannedPages.length}`;
    if (info) info.textContent = `${page.width} × ${page.height} px`;

    try {
        const el = document.getElementById('modalPreviewPagina');
        (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    } catch (e) {
        console.warn('[Scanner] No se pudo abrir la vista previa:', e);
    }
}

/**
 * Confirmación sí/no basada en modal Bootstrap, coherente con el resto del flujo de
 * ingreso (el escáner usaba confirm() nativo, que además bloquea el bucle de detección).
 * @returns {Promise<boolean>}
 */
function confirmarEscaner(titulo, mensajeHtml, textoSi = 'Continuar') {
    return new Promise((resolve) => {
        const modalEl = document.getElementById('modalConfirmEscaner');
        const btnSi = document.getElementById('btnConfirmEscanerSi');
        const btnNo = document.getElementById('btnConfirmEscanerNo');

        if (!modalEl || !btnSi || !btnNo || typeof bootstrap === 'undefined') {
            resolve(window.confirm(mensajeHtml.replace(/<[^>]*>?/gm, '')));
            return;
        }

        document.getElementById('modalConfirmTitle').innerHTML =
            `<i class="fa-solid fa-triangle-exclamation me-2"></i> ${titulo}`;
        document.getElementById('modalConfirmMessage').innerHTML = mensajeHtml;
        btnSi.textContent = textoSi;

        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        let resuelto = false;

        // Los listeners son { once: true } y se limpian entre sí: sin esto, cada llamada
        // acumularía un handler más sobre los mismos botones.
        const terminar = (valor) => {
            if (resuelto) return;
            resuelto = true;
            btnSi.removeEventListener('click', onSi);
            btnNo.removeEventListener('click', onNo);
            modalEl.removeEventListener('hidden.bs.modal', onCerrar);
            resolve(valor);
        };
        const onSi = () => { modal.hide(); terminar(true); };
        const onNo = () => { modal.hide(); terminar(false); };
        const onCerrar = () => terminar(false); // cerrar con la X o ESC = cancelar

        btnSi.addEventListener('click', onSi);
        btnNo.addEventListener('click', onNo);
        modalEl.addEventListener('hidden.bs.modal', onCerrar);

        modal.show();
    });
}

// Límites reales de subida, definidos en .htaccess (php_value). El POST es compartido por
// TODOS los documentos del formulario, no solo por el PDF que se acaba de escanear: si se
// excede post_max_size, PHP entrega $_POST y $_FILES vacíos y el ingreso se pierde sin
// ningún mensaje de error. Por eso se avisa aquí, en el navegador.
const LIMITE_ARCHIVO_MB = 25;
const LIMITE_POST_MB = 30;

function pesoOtrosAdjuntosMb() {
    let bytes = 0;
    document.querySelectorAll('.input-doc-file').forEach(inp => {
        if (inp.files) for (const f of inp.files) bytes += f.size;
    });
    return bytes / (1024 * 1024);
}

async function confirmarPesoPaginas(paginas) {
    if (typeof DocumentScannerPro === 'undefined'
        || typeof DocumentScannerPro.pesoPaginas !== 'function') return true;

    const pdfMb = DocumentScannerPro.pesoPaginas(paginas) / (1024 * 1024);
    const otrosMb = pesoOtrosAdjuntosMb();
    const totalMb = pdfMb + otrosMb;

    const excedeArchivo = pdfMb > LIMITE_ARCHIVO_MB;
    const excedePost = totalMb > LIMITE_POST_MB;
    if (!excedeArchivo && !excedePost) return true;

    const motivo = excedeArchivo
        ? `El PDF pesa alrededor de <strong>${pdfMb.toFixed(1)} MB</strong>, por encima del máximo de ${LIMITE_ARCHIVO_MB} MB por archivo.`
        : `Entre este PDF (${pdfMb.toFixed(1)} MB) y los demás soportes ya adjuntos (${otrosMb.toFixed(1)} MB) el envío sumaría <strong>${totalMb.toFixed(1)} MB</strong>, por encima del máximo de ${LIMITE_POST_MB} MB por envío.`;

    return await confirmarEscaner(
        'El archivo puede ser demasiado pesado',
        `${motivo}<br><br>Si continúa, es probable que <strong>el servidor rechace el ingreso completo</strong>.
         <br><br>Recomendación: use el modo <strong>Blanco y Negro</strong> (mucho más liviano) o divida el lote
         en menos páginas por soporte.`,
        'Continuar de todos modos'
    );
}

async function finalizarYAdjuntarPDF() {
    if (!scannerPro) return;

    // Guardar la página pendiente (capturada y procesada pero sin pulsar "Guardar y
    // Escanear Otra"). Antes esto solo ocurría si el lote estaba vacío, así que en un
    // escaneo de varias páginas la ÚLTIMA se perdía en silencio.
    const targetCat = _categoriaEscaner;
    const catText = configDoc(targetCat).titulo;

    if (scannerPro.rawImage) {
        scannerPro.processScan(filtroDeRealce(configDoc(targetCat).realce), targetCat);
        scannerPro.saveCurrentPageToDoc();
        scannerPro.rawImage = null;
        scannerPro.previewImage = null;
        actualizarTiraMiniaturas();
    }

    // El botón "Adjuntar" solo está visible cuando hay algo que adjuntar, así que este
    // caso ya no debería alcanzarse; se conserva como red de seguridad.
    if (scannerPro.scannedPages.length === 0) return;

    const btnFinalizar = document.getElementById('btn-finalizar-pdf');
    const textoOriginalBtn = btnFinalizar ? btnFinalizar.innerHTML : '';
    if (btnFinalizar) {
        btnFinalizar.disabled = true;
        btnFinalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando PDF...';
    }

    let file, fileName, pesoMb;
    try {
        // Comprimir cada página antes de armar el PDF (lado largo ≤2000px, JPEG 0.8).
        const paginas = await scannerPro.getPagesForPdf(2000, 0.8);

        // Guardia de peso sobre el resultado REAL de la compresión (no sobre una
        // predicción): si supera los límites de subida, avisar antes de armar el PDF.
        if (!(await confirmarPesoPaginas(paginas))) return;

        const { jsPDF } = window.jspdf;
        const A4_ANCHO = 210, A4_ALTO = 297, MARGEN = 8;
        let doc = null;

        // Composición de documento de identidad: ambas caras apiladas en UNA página
        // vertical, como la fotocopia tradicional que el radicador espera recibir.
        if (configDoc(targetCat).composicion === 'ambas-caras-una-pagina') {
            doc = componerAmbasCaras(jsPDF, paginas, A4_ANCHO, A4_ALTO);
        } else {

        paginas.forEach((page, i) => {
            // Orientación por página: una cédula (apaisada) en una hoja vertical quedaría
            // diminuta; así cada página usa la orientación que le corresponde.
            const orientacion = (page.width > page.height) ? 'landscape' : 'portrait';

            if (i === 0) {
                doc = new jsPDF({ orientation: orientacion, unit: 'mm', format: 'a4' });
            } else {
                doc.addPage('a4', orientacion);
            }

            const anchoPag = (orientacion === 'landscape') ? A4_ALTO : A4_ANCHO;
            const altoPag  = (orientacion === 'landscape') ? A4_ANCHO : A4_ALTO;

            // Ajustar preservando la proporción y centrar. Antes se estiraba la imagen a
            // la hoja completa (0,0,210,297), deformando todo documento que no fuera A4.
            const dispW = anchoPag - MARGEN * 2;
            const dispH = altoPag - MARGEN * 2;
            const escala = Math.min(dispW / page.width, dispH / page.height);
            const w = page.width * escala;
            const h = page.height * escala;

            doc.addImage(page.dataUrl, 'JPEG', (anchoPag - w) / 2, (altoPag - h) / 2, w, h);
        });

        } // fin de la composición "una imagen por página"

        const pdfBlob = doc.output('blob');
        fileName = `${targetCat.toLowerCase()}_escaneada_${Date.now()}.pdf`;
        file = new File([pdfBlob], fileName, { type: 'application/pdf' });
        pesoMb = (pdfBlob.size / (1024 * 1024)).toFixed(2);
        console.log(`[Scanner] PDF generado: ${paginas.length} pág(s), ${pesoMb} MB`);
    } catch (err) {
        console.error("[Scanner] Error generando el PDF:", err);
        mostrarNotificacionModal('Error', 'No se pudo generar el PDF del documento escaneado. Intente nuevamente.', 'danger');
        return;
    } finally {
        if (btnFinalizar) {
            btnFinalizar.disabled = false;
            btnFinalizar.innerHTML = textoOriginalBtn;
        }
    }

    // Destino: preferir la fila desde la que se abrió el escáner. Buscar solo por
    // categoría fallaba cuando había dos filas con el mismo tipo (o si el usuario cambió
    // el selector entre abrir el escáner y finalizar): el PDF terminaba en otra fila.
    let targetInput = null;
    let targetStatusDiv = null;

    if (_filaEscanerOrigen && document.body.contains(_filaEscanerOrigen)) {
        targetInput = _filaEscanerOrigen.querySelector('.input-doc-file');
        targetStatusDiv = _filaEscanerOrigen.querySelector('.status-doc-adjunto');
        const selOrigen = _filaEscanerOrigen.querySelector('.select-doc-cat');
        if (selOrigen) selOrigen.value = targetCat;
    }

    // Respaldo: primera fila libre de esa categoría (y si no, cualquiera de esa categoría).
    if (!targetInput) {
        const rows = document.querySelectorAll('.item-documento');
        let ocupada = null;
        rows.forEach(r => {
            const select = r.querySelector('.select-doc-cat');
            if (!select || select.value !== targetCat) return;
            const input = r.querySelector('.input-doc-file');
            if (!input) return;
            if (!targetInput && (!input.files || input.files.length === 0)) {
                targetInput = input;
                targetStatusDiv = r.querySelector('.status-doc-adjunto');
            } else if (!ocupada) {
                ocupada = r;
            }
        });
        if (!targetInput && ocupada) {
            targetInput = ocupada.querySelector('.input-doc-file');
            targetStatusDiv = ocupada.querySelector('.status-doc-adjunto');
        }
    }

    // Si no existía una fila para esa categoría, crearla
    if (!targetInput) {
        agregarFilaDoc();
        const lastRow = document.querySelector('.item-documento:last-child');
        if (lastRow) {
            const select = lastRow.querySelector('.select-doc-cat');
            if (select) select.value = targetCat;
            targetInput = lastRow.querySelector('.input-doc-file');
            targetStatusDiv = lastRow.querySelector('.status-doc-adjunto');
        }
    }

    const cantPaginas = scannerPro.scannedPages.length;

    // Asignar el archivo PDF generado al input correspondiente
    if (targetInput) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        targetInput.files = dataTransfer.files;

        // Señal de auditoría: si en este lote hubo que arrastrar esquinas o lados, queda
        // marcado en la fila y viaja al servidor junto con el archivo.
        const fila = targetInput.closest('.item-documento');
        const flagManual = fila ? fila.querySelector('.input-recorte-manual') : null;
        if (flagManual) flagManual.value = scannerPro.huboAjusteManual ? '1' : '0';

        // Confirmación PERSISTENTE: es la que el orientador consulta de un vistazo para
        // saber qué soportes lleva del paciente actual.
        marcarFilaAdjunta(fila, cantPaginas, pesoMb);
    }

    // forzar=true: el lote ya quedó adjunto como PDF, no hay nada que confirmar.
    cerrarEscanerPro(true);

    // Confirmación EFÍMERA, sin botón que aceptar: se desvanece sola en ~2,5s.
    mostrarToastEscaner(
        `<strong>${catText}</strong><br><span class="small opacity-75">${cantPaginas} página(s) · ${pesoMb} MB</span>`
    );
}

async function toggleLinternaEscaner() {
    if (!scannerPro) return;
    const active = await scannerPro.toggleTorch();
    const btn = document.getElementById('btn-torch');
    if (btn) btn.classList.toggle('activo', active);
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
