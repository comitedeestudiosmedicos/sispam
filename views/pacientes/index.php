<?php
/**
 * SISPAM - Módulo de Gestión y Censo de Pacientes
 * Optimizado para alto rendimiento con +68.000 registros
 */
require_once __DIR__ . '/../../config/app.php';
check_auth();

require_once __DIR__ . '/../../models/Paciente.php';
require_once __DIR__ . '/../../models/Empresa.php';

$pacienteModel = new Paciente();
$empresaModel = new Empresa();

// Obtener sedes reales activas desde la base de datos
$listaSedes = $empresaModel->getTodasSedes();
if (empty($listaSedes)) {
    $listaSedes = [
        ['id' => 1, 'nombre_sede' => 'Sede Prado'],
        ['id' => 2, 'nombre_sede' => 'Sede Ayacucho'],
        ['id' => 3, 'nombre_sede' => 'Sede Centro'],
        ['id' => 4, 'nombre_sede' => 'Sede Poblado'],
        ['id' => 5, 'nombre_sede' => 'Sede Laureles'],
        ['id' => 6, 'nombre_sede' => 'Sede Belén'],
        ['id' => 7, 'nombre_sede' => 'Sede Robledo']
    ];
}

// =========================================================================
// ENDPOINTS AJAX (Detalle para Modales, Edición y Creación)
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Sesión expirada. Por favor recargue la página.']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    try {
        if ($action === 'get_paciente') {
            $id = intval($_GET['id'] ?? 0);
            $paciente = $pacienteModel->getById($id);
            if (!$paciente) {
                echo json_encode(['status' => 'error', 'message' => 'Paciente no encontrado.']);
                exit;
            }
            $historial = $pacienteModel->getHistorialIngresos($id);
            echo json_encode([
                'status'    => 'success',
                'paciente'  => $paciente,
                'historial' => $historial
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'update_paciente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'ID de paciente inválido.']);
                exit;
            }
            $pacienteModel->actualizarDatosPaciente($id, $_POST);
            echo json_encode(['status' => 'success', 'message' => 'Paciente actualizado exitosamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'create_paciente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $numDoc = trim($_POST['numero_documento'] ?? '');
            if (empty($numDoc)) {
                echo json_encode(['status' => 'error', 'message' => 'El número de identificación es obligatorio.']);
                exit;
            }
            $nuevoId = $pacienteModel->crearPacienteDirecto($_POST);
            echo json_encode([
                'status'  => 'success',
                'id'      => $nuevoId,
                'message' => 'Nuevo paciente registrado exitosamente en el censo.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// FILTRADO Y PAGINACIÓN NATIVA DIRECTA (100% CONFIABLE)
// =========================================================================
$busqueda  = trim($_GET['busqueda'] ?? $_GET['buscar_doc'] ?? '');
$tipo_doc  = trim($_GET['tipo_doc'] ?? '');
$eps       = trim($_GET['eps'] ?? '');
$sede      = trim($_GET['sede'] ?? '');
$pagina    = max(1, intval($_GET['pagina'] ?? 1));
$porPagina = in_array(intval($_GET['por_pagina'] ?? 25), [15, 25, 50, 100]) ? intval($_GET['por_pagina'] ?? 25) : 25;

$filtros = [
    'busqueda'       => $busqueda,
    'tipo_documento' => $tipo_doc,
    'eps_nombre'     => $eps,
    'sede_atencion'  => $sede
];

$kpis = $pacienteModel->getEstadisticas();
$resultado = $pacienteModel->listarPaginado($filtros, $pagina, $porPagina);
$pacientes = $resultado['data'];
$paginacion = $resultado['pagination'];

// Función auxiliar para construir URLs de paginación
if (!function_exists('getUrlPaginacionPacientes')) {
    function getUrlPaginacionPacientes($pageNum) {
        $params = $_GET;
        $params['page'] = 'pacientes';
        $params['pagina'] = $pageNum;
        return 'index.php?' . http_build_query($params);
    }
}

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="container-fluid px-4 py-3">
    <!-- Encabezado de Página -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white p-2 rounded-3 shadow-sm">
                    <i class="fa-solid fa-hospital-user fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0 text-dark">Gestión y Censo de Pacientes</h3>
                    <p class="text-muted small mb-0">Base de datos de afiliados, historias demográficas RIPS y SGSSS (+68.000 registros)</p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="index.php?page=importar_pacientes" class="btn btn-outline-primary fw-semibold shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Carga Masiva (Excel/CSV)
            </a>
            <button class="btn btn-primary fw-bold shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoPaciente">
                <i class="fa-solid fa-user-plus me-1"></i> Nuevo Paciente
            </button>
        </div>
    </div>

    <!-- Tarjetas de Métricas Rápidas (KPIs) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-4 border-primary">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Censo Total</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($kpis['total'], 0, ',', '.') ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-users text-primary me-1"></i>Registros globales</div>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-address-book"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-4 border-success">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pacientes Activos</div>
                        <div class="fs-4 fw-bold text-success"><?= number_format($kpis['activos'], 0, ',', '.') ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-check-circle text-success me-1"></i>Habilitados en SGSSS</div>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-4 border-info">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">EPS Principal</div>
                        <div class="fs-6 fw-bold text-truncate text-dark" style="max-width: 170px;" title="<?= htmlspecialchars($kpis['top_eps']) ?>">
                            <?= htmlspecialchars($kpis['top_eps']) ?>
                        </div>
                        <div class="small text-info fw-semibold"><?= number_format($kpis['top_eps_cant'], 0, ',', '.') ?> afiliados</div>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-4 border-warning">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Con Admisiones</div>
                        <div class="fs-4 fw-bold text-dark"><?= number_format($kpis['con_ingresos'], 0, ',', '.') ?></div>
                        <div class="small text-muted"><i class="fa-solid fa-receipt text-warning me-1"></i>Historial de tickets</div>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle fs-4">
                        <i class="fa-solid fa-file-medical"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel de Búsqueda, Filtros y Tabla -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
        <div class="card-body p-3 p-md-4">
            <!-- Barra Superior de Filtros con envío directo y confiable -->
            <form method="GET" action="index.php" class="row g-2 align-items-center mb-3" id="formFiltroPacientes">
                <input type="hidden" name="page" value="pacientes">

                <div class="col-12 col-md-5 col-lg-4">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="busqueda" id="filtro_busqueda" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por documento, nombre, celular o email..." autocomplete="off" value="<?= htmlspecialchars($busqueda) ?>" autofocus>
                        <button class="btn btn-primary fw-bold" type="submit" id="btn_buscar_pacientes" title="Buscar">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                        </button>
                        <?php if (!empty($busqueda) || !empty($tipo_doc) || !empty($eps) || !empty($sede)): ?>
                            <a href="index.php?page=pacientes" class="btn btn-outline-secondary" title="Limpiar todos los filtros">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <select name="tipo_doc" class="form-select shadow-sm" onchange="this.form.submit()">
                        <option value="">Tipo Documento (Todos)</option>
                        <?php foreach (TIPOS_DOCUMENTO as $code => $lbl): ?>
                            <option value="<?= $code ?>" <?= $tipo_doc === $code ? 'selected' : '' ?>><?= $code ?> - <?= explode('-', $lbl)[1] ?? $code ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-4 col-lg-3">
                    <select name="eps" class="form-select shadow-sm" onchange="this.form.submit()">
                        <option value="">Aseguradora / EPS (Todas)</option>
                        <?php foreach (EPS_COLOMBIA as $code => $lbl): ?>
                            <option value="<?= $code ?>" <?= $eps === $code ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <select name="sede" class="form-select shadow-sm" onchange="this.form.submit()">
                        <option value="">Sede de Ingreso (Todas)</option>
                        <?php foreach ($listaSedes as $s): ?>
                            <option value="<?= htmlspecialchars($s['nombre_sede']) ?>" <?= $sede === $s['nombre_sede'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nombre_sede']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-3 col-lg-1 text-end">
                    <select name="por_pagina" class="form-select shadow-sm" onchange="this.form.submit()">
                        <option value="15" <?= $porPagina === 15 ? 'selected' : '' ?>>15 / pág</option>
                        <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 / pág</option>
                        <option value="50" <?= $porPagina === 50 ? 'selected' : '' ?>>50 / pág</option>
                        <option value="100" <?= $porPagina === 100 ? 'selected' : '' ?>>100 / pág</option>
                    </select>
                </div>
            </form>

            <?php if (!empty($busqueda) || !empty($tipo_doc) || !empty($eps) || !empty($sede)): ?>
                <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center justify-content-between small">
                    <div>
                        <i class="fa-solid fa-filter me-1"></i> Filtro activo:
                        <?php if (!empty($busqueda)): ?>
                            <span class="badge bg-primary me-1">Texto: "<?= htmlspecialchars($busqueda) ?>"</span>
                        <?php endif; ?>
                        <?php if (!empty($tipo_doc)): ?>
                            <span class="badge bg-secondary me-1">Tipo Doc: <?= htmlspecialchars($tipo_doc) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($eps)): ?>
                            <span class="badge bg-secondary me-1">EPS: <?= htmlspecialchars(EPS_COLOMBIA[$eps] ?? $eps) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($sede)): ?>
                            <span class="badge bg-primary me-1"><i class="fa-solid fa-hospital me-1"></i>Sede Ingreso: <?= htmlspecialchars($sede) ?></span>
                        <?php endif; ?>
                        (<?= number_format($paginacion['total'], 0, ',', '.') ?> resultados encontrados)
                    </div>
                    <a href="index.php?page=pacientes" class="text-danger fw-bold text-decoration-none small">
                        <i class="fa-solid fa-xmark me-1"></i> Quitar filtros
                    </a>
                </div>
            <?php endif; ?>

            <!-- Tabla de Resultados -->
            <div class="table-responsive" style="min-height: 280px;">
                <table class="table table-hover align-middle mb-0" id="tabla_pacientes">
                    <thead class="table-light text-uppercase small text-muted">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Paciente / Documento</th>
                            <th>Contacto</th>
                            <th>Aseguradora (EPS) & Régimen</th>
                            <th>Ubicación & Sede Ingreso</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_pacientes">
                        <?php if (empty($pacientes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-slash fs-1 d-block mb-2 opacity-50"></i>
                                    No se encontraron pacientes que coincidan con los criterios de búsqueda.
                                    <?php if (!empty($busqueda) || !empty($sede) || !empty($eps) || !empty($tipo_doc)): ?>
                                        <div class="mt-3">
                                            <a href="index.php?page=pacientes" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-arrow-rotate-left me-1"></i> Ver todos los pacientes
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pacientes as $p): ?>
                                <?php
                                    $pNom = $p['primer_nombre'] ?? '';
                                    $pApe = $p['primer_apellido'] ?? '';
                                    $nombreCompleto = (!empty($p['nombres']) && !empty($p['apellidos'])) ? ($p['nombres'] . ' ' . $p['apellidos']) : ($pNom . ' ' . $pApe);
                                    $iniciales = (mb_substr($pNom, 0, 1) ?: 'P') . (mb_substr($pApe, 0, 1) ?: 'U');
                                    
                                    $epsCod = $p['eps_nombre'] ?? 'EPS040';
                                    $epsTexto = EPS_COLOMBIA[$epsCod] ?? $epsCod;
                                    $epsCorta = strpos($epsTexto, '-') !== false ? trim(explode('-', $epsTexto)[1]) : $epsTexto;

                                    $barrioNombre = BARRIOS_MEDELLIN[$p['barrio'] ?? ''] ?? ($p['barrio'] ?? 'El Poblado');
                                    
                                    // Sede de atención o sede de ingreso
                                    $sedeNombre = !empty($p['ultima_sede_ingreso']) ? $p['ultima_sede_ingreso'] : (SEDES_ATENCION[$p['sede_atencion'] ?? ''] ?? ($p['sede_atencion'] ?? 'Sede Principal'));

                                    $esActivo = ($p['estado'] === 'Activo' || empty($p['estado']));
                                    $celular = $p['numero_celular'] ?: ($p['telefono'] ?: 'Sin celular');
                                    $email = $p['email'] ?: 'Sin correo';
                                    $docLimpio = trim($p['numero_documento'] ?? '');
                                ?>
                                <tr>
                                    <td class="text-center text-muted fw-bold small"><?= $p['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; min-width: 38px; font-size: 0.85rem;">
                                                <?= strtoupper($iniciales) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark text-hover-primary cursor-pointer" onclick="verFichaPaciente(<?= $p['id'] ?>)">
                                                    <?= htmlspecialchars(strtoupper($nombreCompleto)) ?>
                                                </div>
                                                <div class="small text-muted d-flex align-items-center gap-1">
                                                    <span class="badge bg-light text-dark border"><?= $p['tipo_documento'] ?: 'CC' ?></span>
                                                    <span class="fw-semibold text-primary font-monospace"><?= htmlspecialchars($docLimpio) ?></span>
                                                    <?php if (!empty($p['sexo'])): ?>
                                                        <span class="badge bg-light text-muted border"><?= substr($p['sexo'], 0, 1) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column small">
                                            <span class="fw-semibold text-dark"><i class="fa-solid fa-phone text-muted me-1"></i><?= htmlspecialchars($celular) ?></span>
                                            <span class="text-muted"><i class="fa-solid fa-envelope text-muted me-1"></i><?= htmlspecialchars($email) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column small">
                                            <span class="fw-bold text-primary"><i class="fa-solid fa-shield-halved me-1"></i><?= htmlspecialchars($epsCorta) ?></span>
                                            <span class="text-muted"><?= htmlspecialchars(TIPOS_AFILIADO[$p['tipo_afiliado'] ?? ''] ?? ($p['tipo_afiliado'] ?? 'Contributivo')) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column small">
                                            <span class="text-dark"><i class="fa-solid fa-location-dot text-muted me-1"></i><?= htmlspecialchars($barrioNombre) ?></span>
                                            <span class="text-primary fw-semibold"><i class="fa-solid fa-hospital me-1"></i><?= htmlspecialchars($sedeNombre) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($esActivo): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i>Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="verFichaPaciente(<?= $p['id'] ?>)" title="Ver Ficha Completa">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="abrirModalEditar(<?= $p['id'] ?>)" title="Editar Paciente">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="index.php?page=ingreso&buscar_doc=<?= urlencode($docLimpio) ?>&tipo_doc=<?= urlencode($p['tipo_documento'] ?: 'CC') ?>" class="btn btn-outline-success" title="Crear Admisión / Ticket">
                                                <i class="fa-solid fa-ticket"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pie de Paginación -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-3 pt-3 border-top">
                <div class="small text-muted" id="info_paginacion">
                    Mostrando <strong><?= $paginacion['from'] ?></strong> a <strong><?= $paginacion['to'] ?></strong> de <strong><?= number_format($paginacion['total'], 0, ',', '.') ?></strong> pacientes registrados
                </div>
                <?php if ($paginacion['total_pages'] > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0 shadow-sm" id="ul_paginacion">
                            <!-- Botón Anterior -->
                            <li class="page-item <?= $paginacion['page'] <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $paginacion['page'] > 1 ? getUrlPaginacionPacientes($paginacion['page'] - 1) : '#' ?>"><i class="fa-solid fa-chevron-left"></i></a>
                            </li>

                            <?php
                                $maxVisible = 5;
                                $startPage = max(1, $paginacion['page'] - floor($maxVisible / 2));
                                $endPage = min($paginacion['total_pages'], $startPage + $maxVisible - 1);
                                if ($endPage - $startPage + 1 < $maxVisible) {
                                    $startPage = max(1, $endPage - $maxVisible + 1);
                                }
                            ?>

                            <?php if ($startPage > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= getUrlPaginacionPacientes(1) ?>">1</a></li>
                                <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i === $paginacion['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= getUrlPaginacionPacientes($i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $paginacion['total_pages']): ?>
                                <?php if ($endPage < $paginacion['total_pages'] - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?= getUrlPaginacionPacientes($paginacion['total_pages']) ?>"><?= $paginacion['total_pages'] ?></a></li>
                            <?php endif; ?>

                            <!-- Botón Siguiente -->
                            <li class="page-item <?= $paginacion['page'] >= $paginacion['total_pages'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $paginacion['page'] < $paginacion['total_pages'] ? getUrlPaginacionPacientes($paginacion['page'] + 1) : '#' ?>"><i class="fa-solid fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 1: VER FICHA COMPLETA DEL PACIENTE (CONSULTA)
     ========================================================================= -->
<div class="modal fade" id="modalVerPaciente" tabindex="-1" aria-labelledby="modalVerPacienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow d-flex flex-column" style="max-height: 90vh;">
            <div class="modal-header bg-primary text-white py-3 flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <div id="ver_avatar" class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 42px; height: 42px;">
                        P
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="ver_nombre_completo">Cargando paciente...</h5>
                        <div class="small text-white-50" id="ver_subtitulo_doc">Documento: ---</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-0 flex-grow-1" style="overflow-y: auto; max-height: calc(90vh - 130px);">
                <!-- Pestañas de la Ficha -->
                <ul class="nav nav-tabs nav-fill bg-light border-bottom px-3 pt-2" id="fichaTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="tab-demo-btn" data-bs-toggle="tab" data-bs-target="#tab-demo" type="button" role="tab">
                            <i class="fa-solid fa-id-card me-1 text-primary"></i> 1. Demográficos RIPS
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="tab-salud-btn" data-bs-toggle="tab" data-bs-target="#tab-salud" type="button" role="tab">
                            <i class="fa-solid fa-heart-pulse me-1 text-danger"></i> 2. Afiliación & Salud
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="tab-contacto-btn" data-bs-toggle="tab" data-bs-target="#tab-contacto" type="button" role="tab">
                            <i class="fa-solid fa-map-location-dot me-1 text-success"></i> 3. Contacto & Ubicación
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="tab-historial-btn" data-bs-toggle="tab" data-bs-target="#tab-historial" type="button" role="tab">
                            <i class="fa-solid fa-clock-rotate-left me-1 text-warning"></i> 4. Historial Admisiones
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="fichaTabsContent">
                    <!-- Tab 1: Demográficos -->
                    <div class="tab-pane fade show active" id="tab-demo" role="tabpanel">
                        <div class="row g-3" id="ver_grid_demo">
                            <!-- Datos demográficos -->
                        </div>
                    </div>

                    <!-- Tab 2: Salud -->
                    <div class="tab-pane fade" id="tab-salud" role="tabpanel">
                        <div class="row g-3" id="ver_grid_salud">
                            <!-- Datos de salud -->
                        </div>
                    </div>

                    <!-- Tab 3: Contacto -->
                    <div class="tab-pane fade" id="tab-contacto" role="tabpanel">
                        <div class="row g-3" id="ver_grid_contacto">
                            <!-- Datos de contacto -->
                        </div>
                    </div>

                    <!-- Tab 4: Historial de Admisiones -->
                    <div class="tab-pane fade" id="tab-historial" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th>Fecha Ingreso</th>
                                        <th>Turno / Ticket</th>
                                        <th>Sede Atención</th>
                                        <th>Prioridad</th>
                                        <th>Reclama</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="ver_tbody_historial">
                                    <!-- Filas de admisiones -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 flex-shrink-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-warning fw-semibold" id="btnEditarDesdeVer">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Editar este Paciente
                </button>
                <a href="#" id="btnTicketDesdeVer" class="btn btn-success fw-bold">
                    <i class="fa-solid fa-ticket me-1"></i> Crear Nueva Admisión
                </a>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: EDITAR PACIENTE (ACTUALIZACIÓN COMPLETA)
     ========================================================================= -->
<div class="modal fade" id="modalEditarPaciente" tabindex="-1" aria-labelledby="modalEditarPacienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="formEditarPaciente" class="modal-content border-0 shadow d-flex flex-column" style="max-height: 90vh;">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="modal-header bg-warning text-dark py-3 flex-shrink-0">
                <h5 class="modal-title fw-bold" id="modalEditarPacienteLabel">
                    <i class="fa-solid fa-user-pen me-2"></i> Editar Información de Paciente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4 flex-grow-1" style="overflow-y: auto; max-height: calc(90vh - 130px);">
                <!-- Sección 1: Identificación y Nombres -->
                <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-id-card me-1"></i> 1. Identificación y Nombres Oficiales
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipo Documento <span class="text-danger">*</span></label>
                        <select name="tipo_documento" id="edit_tipo_documento" class="form-select form-select-sm" required>
                            <?php foreach (TIPOS_DOCUMENTO as $code => $label): ?>
                                <option value="<?= $code ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Número Documento <span class="text-danger">*</span></label>
                        <input type="text" name="numero_documento" id="edit_numero_documento" class="form-control form-control-sm fw-bold" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Ciudad Expedición</label>
                        <select name="ciudad_expedicion" id="edit_ciudad_expedicion" class="form-select form-select-sm">
                            <?php foreach (MUNICIPIOS_ANTIOQUIA as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Estado Paciente</label>
                        <select name="estado" id="edit_estado" class="form-select form-select-sm fw-bold">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Primer Apellido <span class="text-danger">*</span></label>
                        <input type="text" name="primer_apellido" id="edit_primer_apellido" class="form-control form-control-sm text-uppercase" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Segundo Apellido</label>
                        <input type="text" name="segundo_apellido" id="edit_segundo_apellido" class="form-control form-control-sm text-uppercase">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Primer Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="primer_nombre" id="edit_primer_nombre" class="form-control form-control-sm text-uppercase" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Segundo Nombre</label>
                        <input type="text" name="segundo_nombre" id="edit_segundo_nombre" class="form-control form-control-sm text-uppercase">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Fecha Nacimiento <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_nacimiento" id="edit_fecha_nacimiento" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Sexo Biológico <span class="text-danger">*</span></label>
                        <select name="sexo" id="edit_sexo" class="form-select form-select-sm" required>
                            <option value="Masculino">Masculino</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Indeterminado">Indeterminado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Estado Civil</label>
                        <select name="estado_civil" id="edit_estado_civil" class="form-select form-select-sm">
                            <?php foreach (ESTADOS_CIVILES as $ec => $lbl): ?>
                                <option value="<?= $ec ?>"><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Grupo Sanguíneo</label>
                        <select name="grupo_sanguineo" id="edit_grupo_sanguineo" class="form-select form-select-sm">
                            <?php foreach (GRUPOS_SANGUINEOS as $gs): ?>
                                <option value="<?= $gs ?>"><?= $gs ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sección 2: Afiliación SGSSS y Salud -->
                <h6 class="fw-bold text-danger border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-shield-halved me-1"></i> 2. Afiliación al Sistema de Salud y Cobertura
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Aseguradora / EPS <span class="text-danger">*</span></label>
                        <select name="eps_nombre" id="edit_eps_nombre" class="form-select form-select-sm fw-bold" required>
                            <?php foreach (EPS_COLOMBIA as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipo de Afiliado</label>
                        <select name="tipo_afiliado" id="edit_tipo_afiliado" class="form-select form-select-sm">
                            <?php foreach (TIPOS_AFILIADO as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Nivel Socioeconómico</label>
                        <select name="nivel_socioeconomico" id="edit_nivel_socioeconomico" class="form-select form-select-sm">
                            <?php foreach (NIVELES_SOCIOECONOMICOS as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Estrato Socioeconómico</label>
                        <select name="estrato_socioeconomico" id="edit_estrato_socioeconomico" class="form-select form-select-sm">
                            <?php foreach (ESTRATOS_SOCIOECONOMICOS as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Sede de Ingreso / Atención</label>
                        <select name="sede_atencion" id="edit_sede_atencion" class="form-select form-select-sm fw-bold">
                            <?php foreach ($listaSedes as $s): ?>
                                <option value="<?= htmlspecialchars($s['nombre_sede']) ?>"><?= htmlspecialchars($s['nombre_sede']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Grupo Poblacional</label>
                        <select name="grupo_poblacional" id="edit_grupo_poblacional" class="form-select form-select-sm">
                            <?php foreach (GRUPOS_POBLACIONALES as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Grupo Étnico</label>
                        <select name="grupo_etnico" id="edit_grupo_etnico" class="form-select form-select-sm">
                            <?php foreach (GRUPOS_ETNICOS as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipo Discapacidad</label>
                        <select name="tipo_discapacidad" id="edit_tipo_discapacidad" class="form-select form-select-sm">
                            <?php foreach (TIPOS_DISCAPACIDAD as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Escolaridad</label>
                        <select name="tipo_escolaridad" id="edit_tipo_escolaridad" class="form-select form-select-sm">
                            <?php foreach (TIPOS_ESCOLARIDAD as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Ocupación / Oficio</label>
                        <select name="ocupacion" id="edit_ocupacion" class="form-select form-select-sm">
                            <?php foreach (OCUPACIONES as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sección 3: Contacto y Ubicación -->
                <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-location-dot me-1"></i> 3. Ubicación, Residencia y Contacto
                </h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Dirección Residencia <span class="text-danger">*</span></label>
                        <input type="text" name="direccion_residencia" id="edit_direccion_residencia" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Zona</label>
                        <select name="zona" id="edit_zona" class="form-select form-select-sm">
                            <option value="U">U - Urbana</option>
                            <option value="R">R - Rural</option>
                            <option value="S/N">S/N - Sin Determinar</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Barrio / Comuna</label>
                        <select name="barrio" id="edit_barrio" class="form-select form-select-sm">
                            <?php foreach (BARRIOS_MEDELLIN as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Ciudad Residencia</label>
                        <select name="ciudad_residencia" id="edit_ciudad_residencia" class="form-select form-select-sm">
                            <?php foreach (MUNICIPIOS_ANTIOQUIA as $cod => $nom): ?>
                                <option value="<?= $cod ?>"><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Número Celular / Móvil <span class="text-danger">*</span></label>
                        <input type="text" name="numero_celular" id="edit_numero_celular" class="form-control form-control-sm fw-bold" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Otro Teléfono Fijo</label>
                        <input type="text" name="otro_telefono" id="edit_otro_telefono" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Correo Electrónico (Email)</label>
                        <input type="email" name="email" id="edit_email" class="form-control form-control-sm">
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 flex-shrink-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold px-4" id="btnGuardarEdicion">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: REGISTRAR NUEVO PACIENTE (CREACIÓN MANUAL)
     ========================================================================= -->
<div class="modal fade" id="modalNuevoPaciente" tabindex="-1" aria-labelledby="modalNuevoPacienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form id="formNuevoPaciente" class="modal-content border-0 shadow d-flex flex-column" style="max-height: 90vh;">
            <div class="modal-header bg-primary text-white py-3 flex-shrink-0">
                <h5 class="modal-title fw-bold" id="modalNuevoPacienteLabel">
                    <i class="fa-solid fa-user-plus me-2"></i> Registrar Nuevo Paciente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4 flex-grow-1" style="overflow-y: auto; max-height: calc(90vh - 130px);">
                <!-- Sección 1: Identificación y Nombres -->
                <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-id-card me-1"></i> 1. Identificación y Nombres Oficiales
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipo Documento <span class="text-danger">*</span></label>
                        <select name="tipo_documento" class="form-select form-select-sm" required>
                            <?php foreach (TIPOS_DOCUMENTO as $code => $label): ?>
                                <option value="<?= $code ?>" <?= $code === 'CC' ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Número Documento <span class="text-danger">*</span></label>
                        <input type="text" name="numero_documento" class="form-control form-control-sm fw-bold" placeholder="Ej: 1036780004" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Ciudad Expedición</label>
                        <select name="ciudad_expedicion" class="form-select form-select-sm">
                            <?php foreach (MUNICIPIOS_ANTIOQUIA as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '05001' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Estado</label>
                        <select name="estado" class="form-select form-select-sm fw-bold">
                            <option value="Activo" selected>Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Primer Apellido <span class="text-danger">*</span></label>
                        <input type="text" name="primer_apellido" class="form-control form-control-sm text-uppercase" placeholder="Primer Apellido" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Segundo Apellido</label>
                        <input type="text" name="segundo_apellido" class="form-control form-control-sm text-uppercase" placeholder="Segundo Apellido">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Primer Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="primer_nombre" class="form-control form-control-sm text-uppercase" placeholder="Primer Nombre" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Segundo Nombre</label>
                        <input type="text" name="segundo_nombre" class="form-control form-control-sm text-uppercase" placeholder="Segundo Nombre">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Fecha Nacimiento <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_nacimiento" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Sexo Biológico <span class="text-danger">*</span></label>
                        <select name="sexo" class="form-select form-select-sm" required>
                            <option value="Masculino" selected>Masculino</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Indeterminado">Indeterminado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Estado Civil</label>
                        <select name="estado_civil" class="form-select form-select-sm">
                            <?php foreach (ESTADOS_CIVILES as $ec => $lbl): ?>
                                <option value="<?= $ec ?>" <?= $ec === 'Soltero' ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Grupo Sanguíneo</label>
                        <select name="grupo_sanguineo" class="form-select form-select-sm">
                            <?php foreach (GRUPOS_SANGUINEOS as $gs): ?>
                                <option value="<?= $gs ?>" <?= $gs === 'O+' ? 'selected' : '' ?>><?= $gs ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sección 2: Afiliación SGSSS y Salud -->
                <h6 class="fw-bold text-danger border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-shield-halved me-1"></i> 2. Afiliación al Sistema de Salud y Cobertura
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Aseguradora / EPS <span class="text-danger">*</span></label>
                        <select name="eps_nombre" class="form-select form-select-sm fw-bold" required>
                            <?php foreach (EPS_COLOMBIA as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === 'EPS040' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipo de Afiliado</label>
                        <select name="tipo_afiliado" class="form-select form-select-sm">
                            <?php foreach (TIPOS_AFILIADO as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '01' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Nivel Socioeconómico</label>
                        <select name="nivel_socioeconomico" class="form-select form-select-sm">
                            <?php foreach (NIVELES_SOCIOECONOMICOS as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '1' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Estrato Socioeconómico</label>
                        <select name="estrato_socioeconomico" class="form-select form-select-sm">
                            <?php foreach (ESTRATOS_SOCIOECONOMICOS as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '3' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Sede de Ingreso / Atención</label>
                        <select name="sede_atencion" class="form-select form-select-sm fw-bold">
                            <?php foreach ($listaSedes as $s): ?>
                                <option value="<?= htmlspecialchars($s['nombre_sede']) ?>"><?= htmlspecialchars($s['nombre_sede']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Grupo Poblacional</label>
                        <select name="grupo_poblacional" class="form-select form-select-sm">
                            <?php foreach (GRUPOS_POBLACIONALES as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '5' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Grupo Étnico</label>
                        <select name="grupo_etnico" class="form-select form-select-sm">
                            <?php foreach (GRUPOS_ETNICOS as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === 'S' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Tipo Discapacidad</label>
                        <select name="tipo_discapacidad" class="form-select form-select-sm">
                            <?php foreach (TIPOS_DISCAPACIDAD as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === 'N' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Escolaridad</label>
                        <select name="tipo_escolaridad" class="form-select form-select-sm">
                            <?php foreach (TIPOS_ESCOLARIDAD as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '13' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Ocupación / Oficio</label>
                        <select name="ocupacion" class="form-select form-select-sm">
                            <?php foreach (OCUPACIONES as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '0000' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Sección 3: Contacto y Ubicación -->
                <h6 class="fw-bold text-success border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-location-dot me-1"></i> 3. Ubicación, Residencia y Contacto
                </h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Dirección Residencia <span class="text-danger">*</span></label>
                        <input type="text" name="direccion_residencia" class="form-control form-control-sm" placeholder="Ej: Cra 45 # 50-20" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">Zona</label>
                        <select name="zona" class="form-select form-select-sm">
                            <option value="U" selected>U - Urbana</option>
                            <option value="R">R - Rural</option>
                            <option value="S/N">S/N - Sin Determinar</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Barrio / Comuna</label>
                        <select name="barrio" class="form-select form-select-sm">
                            <?php foreach (BARRIOS_MEDELLIN as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === 'B080' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Ciudad Residencia</label>
                        <select name="ciudad_residencia" class="form-select form-select-sm">
                            <?php foreach (MUNICIPIOS_ANTIOQUIA as $cod => $nom): ?>
                                <option value="<?= $cod ?>" <?= $cod === '05001' ? 'selected' : '' ?>><?= $nom ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Número Celular / Móvil <span class="text-danger">*</span></label>
                        <input type="text" name="numero_celular" class="form-control form-control-sm fw-bold" placeholder="Ej: 3001234567" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Otro Teléfono Fijo</label>
                        <input type="text" name="otro_telefono" class="form-control form-control-sm" placeholder="Opcional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Correo Electrónico (Email)</label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="correo@ejemplo.com">
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 flex-shrink-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" id="btnCrearPaciente">
                    <i class="fa-solid fa-user-check me-1"></i> Guardar y Registrar Paciente
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Diccionarios de referencia para renderizar etiquetas amigables
const CATALOGOS = {
    tipos_doc: <?= json_encode(TIPOS_DOCUMENTO, JSON_UNESCAPED_UNICODE) ?>,
    eps: <?= json_encode(EPS_COLOMBIA, JSON_UNESCAPED_UNICODE) ?>,
    sedes: <?= json_encode(array_column($listaSedes, 'nombre_sede', 'nombre_sede') + array_column($listaSedes, 'nombre_sede', 'id') + SEDES_ATENCION, JSON_UNESCAPED_UNICODE) ?>,
    barrios: <?= json_encode(BARRIOS_MEDELLIN, JSON_UNESCAPED_UNICODE) ?>,
    municipios: <?= json_encode(MUNICIPIOS_ANTIOQUIA, JSON_UNESCAPED_UNICODE) ?>,
    afiliados: <?= json_encode(TIPOS_AFILIADO, JSON_UNESCAPED_UNICODE) ?>,
    niveles: <?= json_encode(NIVELES_SOCIOECONOMICOS, JSON_UNESCAPED_UNICODE) ?>,
    estratos: <?= json_encode(ESTRATOS_SOCIOECONOMICOS, JSON_UNESCAPED_UNICODE) ?>,
    poblacionales: <?= json_encode(GRUPOS_POBLACIONALES, JSON_UNESCAPED_UNICODE) ?>,
    etnicos: <?= json_encode(GRUPOS_ETNICOS, JSON_UNESCAPED_UNICODE) ?>,
    discapacidades: <?= json_encode(TIPOS_DISCAPACIDAD, JSON_UNESCAPED_UNICODE) ?>,
    escolaridades: <?= json_encode(TIPOS_ESCOLARIDAD, JSON_UNESCAPED_UNICODE) ?>,
    ocupaciones: <?= json_encode(OCUPACIONES, JSON_UNESCAPED_UNICODE) ?>
};

// Consultar y abrir modal de visualización de ficha completa
async function verFichaPaciente(id) {
    try {
        const res = await fetch(`index.php?page=pacientes&ajax=1&action=get_paciente&id=${id}`);
        const rawText = await res.text();
        const jsonStart = rawText.indexOf('{');
        const jsonEnd = rawText.lastIndexOf('}');
        const json = JSON.parse(rawText.substring(jsonStart, jsonEnd + 1));

        if (json.status !== 'success') {
            alert(json.message);
            return;
        }

        const p = json.paciente;

        const pNom = p.primer_nombre || '';
        const pApe = p.primer_apellido || '';
        const nombreCompleto = (p.nombres && p.apellidos) ? `${p.nombres} ${p.apellidos}` : `${pNom} ${pApe}`;
        const iniciales = (pNom.charAt(0) || 'P') + (pApe.charAt(0) || 'U');

        document.getElementById('ver_avatar').textContent = iniciales.toUpperCase();
        document.getElementById('ver_nombre_completo').textContent = nombreCompleto.toUpperCase();
        document.getElementById('ver_subtitulo_doc').textContent = `Documento: ${p.tipo_documento || 'CC'} ${p.numero_documento} · Estado: ${p.estado || 'Activo'}`;

        // Tab 1: Demográficos
        document.getElementById('ver_grid_demo').innerHTML = `
            <div class="col-md-4"><label class="text-muted small fw-bold">Tipo Documento</label><div class="fw-semibold">${p.tipo_documento || 'CC'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Número Documento</label><div class="fw-bold font-monospace text-primary">${p.numero_documento}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Ciudad Expedición</label><div class="fw-semibold">${CATALOGOS.municipios[p.ciudad_expedicion] || p.ciudad_expedicion || '05001'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Primer Apellido</label><div class="fw-semibold">${p.primer_apellido || '---'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Segundo Apellido</label><div class="fw-semibold">${p.segundo_apellido || '---'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Primer Nombre</label><div class="fw-semibold">${p.primer_nombre || '---'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Segundo Nombre</label><div class="fw-semibold">${p.segundo_nombre || '---'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Fecha Nacimiento</label><div class="fw-semibold">${p.fecha_nacimiento || 'No registrada'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Sexo Biológico</label><div class="fw-semibold">${p.sexo || 'Masculino'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Estado Civil</label><div class="fw-semibold">${p.estado_civil || 'Soltero'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Grupo Sanguíneo</label><div class="fw-semibold">${p.grupo_sanguineo || 'O+'}</div></div>
        `;

        // Tab 2: Salud
        const epsTxt = CATALOGOS.eps[p.eps_nombre] || p.eps_nombre || 'SAVIA SALUD EPS';
        const sedeTxt = CATALOGOS.sedes[p.sede_atencion] || p.sede_atencion || 'Sede Principal';
        document.getElementById('ver_grid_salud').innerHTML = `
            <div class="col-md-6"><label class="text-muted small fw-bold">Aseguradora / EPS</label><div class="fw-bold text-primary">${epsTxt}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Tipo Afiliado</label><div class="fw-semibold">${CATALOGOS.afiliados[p.tipo_afiliado] || p.tipo_afiliado || 'Contributivo Cotizante'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Nivel Socioeconómico</label><div class="fw-semibold">${CATALOGOS.niveles[p.nivel_socioeconomico] || p.nivel_socioeconomico || 'CATEGORIA A'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Estrato</label><div class="fw-semibold">${CATALOGOS.estratos[p.estrato_socioeconomico] || 'Estrato ' + p.estrato_socioeconomico}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Sede de Ingreso / Atención</label><div class="fw-semibold text-primary">${sedeTxt}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Grupo Poblacional</label><div class="fw-semibold">${CATALOGOS.poblacionales[p.grupo_poblacional] || p.grupo_poblacional || 'Otro'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Grupo Étnico</label><div class="fw-semibold">${CATALOGOS.etnicos[p.grupo_etnico] || p.grupo_etnico || 'No Aplica'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Discapacidad</label><div class="fw-semibold">${CATALOGOS.discapacidades[p.tipo_discapacidad] || p.tipo_discapacidad || 'No Aplica'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Escolaridad</label><div class="fw-semibold">${CATALOGOS.escolaridades[p.tipo_escolaridad] || p.tipo_escolaridad || 'NA'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Ocupación</label><div class="fw-semibold">${CATALOGOS.ocupaciones[p.ocupacion] || p.ocupacion || 'Empleado'}</div></div>
        `;

        // Tab 3: Contacto
        document.getElementById('ver_grid_contacto').innerHTML = `
            <div class="col-md-4"><label class="text-muted small fw-bold">Dirección Residencia</label><div class="fw-semibold">${p.direccion_residencia || 'No registrada'}</div></div>
            <div class="col-md-2"><label class="text-muted small fw-bold">Zona</label><div class="fw-semibold">${p.zona === 'U' ? 'Urbana' : (p.zona === 'R' ? 'Rural' : 'S/N')}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Barrio / Comuna</label><div class="fw-semibold">${CATALOGOS.barrios[p.barrio] || p.barrio || 'El Poblado'}</div></div>
            <div class="col-md-3"><label class="text-muted small fw-bold">Ciudad Residencia</label><div class="fw-semibold">${CATALOGOS.municipios[p.ciudad_residencia] || p.ciudad_residencia || '05001'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Celular Principal</label><div class="fw-bold text-dark font-monospace">${p.numero_celular || p.telefono || 'Sin celular'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Otro Teléfono</label><div class="fw-semibold">${p.otro_telefono || '---'}</div></div>
            <div class="col-md-4"><label class="text-muted small fw-bold">Correo Electrónico</label><div class="fw-semibold">${p.email || 'Sin correo'}</div></div>
        `;

        // Tab 4: Historial Admisiones
        const histTbody = document.getElementById('ver_tbody_historial');
        if (!json.historial || json.historial.length === 0) {
            histTbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">Este paciente aún no tiene admisiones registradas.</td></tr>`;
        } else {
            let hHtml = '';
            json.historial.forEach(h => {
                hHtml += `
                    <tr>
                        <td class="fw-semibold">${h.fecha_ingreso}</td>
                        <td><span class="badge bg-primary">${h.ticket_numero}</span></td>
                        <td class="fw-semibold text-primary"><i class="fa-solid fa-hospital me-1"></i>${h.sede_nombre || 'Sede Principal'}</td>
                        <td><span class="badge bg-light text-dark border">${h.prioridad || 'Normal'}</span></td>
                        <td>${h.persona_reclama || 'Paciente'}</td>
                        <td><span class="badge bg-success bg-opacity-10 text-success border border-success">${h.estado_tramite}</span></td>
                        <td class="text-end">
                            <a href="index.php?page=imprimir_ticket&id=${h.id}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reimprimir Tiquete">
                                <i class="fa-solid fa-print"></i>
                            </a>
                        </td>
                    </tr>
                `;
            });
            histTbody.innerHTML = hHtml;
        }

        // Configurar botones de acción en footer
        document.getElementById('btnEditarDesdeVer').onclick = () => {
            bootstrap.Modal.getInstance(document.getElementById('modalVerPaciente')).hide();
            abrirModalEditar(p.id);
        };
        document.getElementById('btnTicketDesdeVer').href = `index.php?page=ingreso&buscar_doc=${encodeURIComponent(String(p.numero_documento).trim())}&tipo_doc=${encodeURIComponent(p.tipo_documento || 'CC')}`;

        // Abrir Modal
        const modalEl = document.getElementById('modalVerPaciente');
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    } catch (err) {
        console.error('Error al ver ficha:', err);
        alert('Error de conexión al cargar la ficha del paciente.');
    }
}

// Abrir y poblar modal de edición
async function abrirModalEditar(id) {
    try {
        const res = await fetch(`index.php?page=pacientes&ajax=1&action=get_paciente&id=${id}`);
        const rawText = await res.text();
        const jsonStart = rawText.indexOf('{');
        const jsonEnd = rawText.lastIndexOf('}');
        const json = JSON.parse(rawText.substring(jsonStart, jsonEnd + 1));

        if (json.status !== 'success') {
            alert(json.message);
            return;
        }

        const p = json.paciente;
        document.getElementById('edit_id').value = p.id;
        document.getElementById('edit_tipo_documento').value = p.tipo_documento || 'CC';
        document.getElementById('edit_numero_documento').value = String(p.numero_documento || '').trim();
        document.getElementById('edit_ciudad_expedicion').value = p.ciudad_expedicion || '05001';
        document.getElementById('edit_estado').value = p.estado || 'Activo';

        document.getElementById('edit_primer_apellido').value = p.primer_apellido || '';
        document.getElementById('edit_segundo_apellido').value = p.segundo_apellido || '';
        document.getElementById('edit_primer_nombre').value = p.primer_nombre || '';
        document.getElementById('edit_segundo_nombre').value = p.segundo_nombre || '';

        document.getElementById('edit_fecha_nacimiento').value = p.fecha_nacimiento || '';
        document.getElementById('edit_sexo').value = p.sexo || 'Masculino';
        document.getElementById('edit_estado_civil').value = p.estado_civil || 'Soltero';
        document.getElementById('edit_grupo_sanguineo').value = p.grupo_sanguineo || 'O+';

        document.getElementById('edit_eps_nombre').value = p.eps_nombre || 'EPS040';
        document.getElementById('edit_tipo_afiliado').value = p.tipo_afiliado || '01';
        document.getElementById('edit_nivel_socioeconomico').value = p.nivel_socioeconomico || '1';
        document.getElementById('edit_estrato_socioeconomico').value = p.estrato_socioeconomico || '3';
        document.getElementById('edit_sede_atencion').value = p.sede_atencion || 'Sede Prado';
        document.getElementById('edit_grupo_poblacional').value = p.grupo_poblacional || '5';
        document.getElementById('edit_grupo_etnico').value = p.grupo_etnico || 'S';
        document.getElementById('edit_tipo_discapacidad').value = p.tipo_discapacidad || 'N';
        document.getElementById('edit_tipo_escolaridad').value = p.tipo_escolaridad || '13';
        document.getElementById('edit_ocupacion').value = p.ocupacion || '0000';

        document.getElementById('edit_direccion_residencia').value = p.direccion_residencia || '';
        document.getElementById('edit_zona').value = p.zona || 'U';
        document.getElementById('edit_barrio').value = p.barrio || 'B080';
        document.getElementById('edit_ciudad_residencia').value = p.ciudad_residencia || '05001';
        document.getElementById('edit_numero_celular').value = p.numero_celular || p.telefono || '';
        document.getElementById('edit_otro_telefono').value = p.otro_telefono || '';
        document.getElementById('edit_email').value = p.email || '';

        const modalEl = document.getElementById('modalEditarPaciente');
        (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).show();
    } catch (err) {
        console.error('Error al abrir modal editar:', err);
        alert('Error de conexión al cargar datos de edición.');
    }
}

// Guardar cambios del formulario de edición
document.getElementById('formEditarPaciente').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnGuardarEdicion');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

    const formData = new FormData(e.target);

    try {
        const res = await fetch('index.php?page=pacientes&ajax=1&action=update_paciente', {
            method: 'POST',
            body: formData
        });
        const rawText = await res.text();
        const jsonStart = rawText.indexOf('{');
        const jsonEnd = rawText.lastIndexOf('}');
        const json = JSON.parse(rawText.substring(jsonStart, jsonEnd + 1));

        btn.disabled = false;
        btn.innerHTML = originalText;

        if (json.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('modalEditarPaciente')).hide();
            mostrarToastExito(json.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            alert('Error: ' + json.message);
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = originalText;
        console.error('Error al guardar edición:', err);
        alert('Error de comunicación con el servidor.');
    }
});

// Guardar nuevo paciente desde modal de creación
document.getElementById('formNuevoPaciente').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnCrearPaciente');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registrando...';

    const formData = new FormData(e.target);

    try {
        const res = await fetch('index.php?page=pacientes&ajax=1&action=create_paciente', {
            method: 'POST',
            body: formData
        });
        const rawText = await res.text();
        const jsonStart = rawText.indexOf('{');
        const jsonEnd = rawText.lastIndexOf('}');
        const json = JSON.parse(rawText.substring(jsonStart, jsonEnd + 1));

        btn.disabled = false;
        btn.innerHTML = originalText;

        if (json.status === 'success') {
            e.target.reset();
            bootstrap.Modal.getInstance(document.getElementById('modalNuevoPaciente')).hide();
            mostrarToastExito(json.message);
            setTimeout(() => window.location.reload(), 800);
        } else {
            alert('Error: ' + json.message);
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = originalText;
        console.error('Error al crear paciente:', err);
        alert('Error de comunicación con el servidor.');
    }
});

// Mensajes Toast flotantes modernos
function mostrarToastExito(mensaje) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 p-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast show align-items-center text-white bg-success border-0 shadow-lg" role="alert">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check fs-5"></i>
                    <div class="fw-semibold small">${escapeHtml(mensaje)}</div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s';
        setTimeout(() => toast.remove(), 500);
    }, 3500);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
