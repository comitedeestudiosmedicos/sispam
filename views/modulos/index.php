<?php
require_once __DIR__ . '/../../config/app.php';
check_role(['modulos', 'empresa']);

require_once __DIR__ . '/../../models/ModuloEntrega.php';
require_once __DIR__ . '/../../models/Empresa.php';

$modModel     = new ModuloEntrega();
$empresaModel = new Empresa();

$sedes_disponibles = $empresaModel->getTodasSedes();
$sede_filtro       = $_GET['sede_id'] ?? '';
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear') {
        $sede_id     = intval($_POST['sede_id'] ?? 1);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (!empty($nombre) && $sede_id > 0) {
            if ($modModel->create($nombre, $descripcion, $sede_id)) {
                $mensaje = "Módulo / Ventanilla <strong>" . htmlspecialchars($nombre) . "</strong> registrada exitosamente.";
            } else {
                $error = 'Error al registrar la ventanilla. Es posible que el nombre ya exista en la sede seleccionada.';
            }
        } else {
            $error = 'Debe seleccionar una sede e ingresar el nombre de la ventanilla.';
        }
    } else if ($_POST['action'] === 'editar') {
        $id          = intval($_POST['id'] ?? 0);
        $sede_id     = intval($_POST['sede_id'] ?? 1);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado      = $_POST['estado'] ?? 'ACTIVO';

        if ($id && !empty($nombre) && $sede_id > 0) {
            if ($modModel->update($id, $nombre, $descripcion, $estado, $sede_id)) {
                $mensaje = "Ventanilla / Módulo actualizado correctamente.";
            } else {
                $error = 'No se pudo actualizar el módulo. Verifique que no exista otro con el mismo nombre en la sede.';
            }
        }
    } else if ($_POST['action'] === 'toggle_estado') {
        $id = intval($_POST['id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
        $modModel->updateEstado($id, $nuevo_estado);
        $mensaje = 'Estado de la ventanilla actualizado.';
    } else if ($_POST['action'] === 'poblar_sede') {
        $sede_poblar = intval($_POST['sede_id_poblar'] ?? 0);
        if ($sede_poblar > 0) {
            $modModel->crearModulosPorDefectoParaSede($sede_poblar);
            $mensaje = 'Módulos estándar (1, 2, 3 y Preferencial) generados exitosamente para la sede.';
        } else {
            $error = 'Seleccione una sede válida para generar los módulos.';
        }
    }
}

$modulos = $modModel->getAll($sede_filtro);
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold text-primary mb-1">
            <i class="fa-solid fa-door-open me-2"></i> Gestión de Módulos & Ventanillas de Entrega por Sede
        </h4>
        <p class="text-muted small mb-0">Configuración de ventanillas físicas de entrega y módulos de atención asignados a cada sede para Turneros TV.</p>
    </div>
    <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end mt-3 mt-md-0">
        <button class="btn btn-outline-secondary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPoblarSede" title="Crear rápidamente módulos estándar para una sede">
            <i class="fa-solid fa-wand-magic-sparkles me-1 text-warning"></i> Generar Estándar
        </button>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearModulo">
            <i class="fa-solid fa-plus me-1"></i> Nueva Ventanilla
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small shadow-sm"><i class="fa-solid fa-circle-check me-1"></i> <?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small shadow-sm"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- FILTROS Y BUSCADOR EN VIVO -->
<div class="card card-glass border-0 shadow-sm mb-3 p-3">
    <form method="GET" action="" class="row g-2 align-items-center">
        <input type="hidden" name="page" value="modulos">

        <!-- Buscador en tiempo real -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" id="buscadorModulos" class="form-control border-start-0 ps-0" placeholder="Buscar por ventanilla, sede o descripción..." autocomplete="off">
            </div>
        </div>

        <!-- Filtro por Sede -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted small"><i class="fa-solid fa-location-dot text-warning me-1"></i> Sede</span>
                <select name="sede_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Todas las Sedes --</option>
                    <?php foreach ($sedes_disponibles as $sd): ?>
                        <option value="<?= $sd['id'] ?>" <?= $sede_filtro == $sd['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sd['nombre_sede']) ?> (<?= htmlspecialchars($sd['empresa_nombre']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Filtro por Estado -->
        <div class="col-md-2">
            <select id="filtroEstadoMod" class="form-select">
                <option value="">-- Todos los Estados --</option>
                <option value="ACTIVO">Activos (En servicio)</option>
                <option value="INACTIVO">Inactivos</option>
            </select>
        </div>

        <!-- Contador Dinámico -->
        <div class="col-md-2 text-md-end text-muted small">
            <span id="badgeTotalModulos" class="badge bg-light text-dark border p-2 w-100 shadow-sm">
                <i class="fa-solid fa-door-closed text-primary me-1"></i> <span id="contadorVisibleModulos"><?= count($modulos) ?></span> ventanillas
            </span>
        </div>
    </form>
</div>

<div class="card card-glass border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaModulos">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Sede de Atención</th>
                        <th>Nombre del Módulo / Ventanilla</th>
                        <th>Descripción / Propósito</th>
                        <th>Estado de Servicio</th>
                        <th>Fecha Creación</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyModulos">
                    <?php foreach ($modulos as $m): ?>
                    <?php 
                        $searchCorpus = strtolower($m['id'] . ' ' . $m['nombre'] . ' ' . ($m['descripcion'] ?? '') . ' ' . ($m['nombre_sede'] ?? '') . ' ' . ($m['empresa_nombre'] ?? '') . ' ' . $m['estado']);
                    ?>
                    <tr class="fila-modulo" data-search="<?= htmlspecialchars($searchCorpus) ?>" data-estado="<?= htmlspecialchars($m['estado']) ?>">
                        <td class="ps-3 fw-bold text-muted">#<?= $m['id'] ?></td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-location-dot text-warning me-1"></i> <?= htmlspecialchars($m['nombre_sede'] ?? 'Sede Principal') ?>
                            </span>
                            <div class="small text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($m['empresa_nombre'] ?? '') ?></div>
                        </td>
                        <td class="fw-bold text-primary fs-6">
                            <i class="fa-solid fa-door-closed me-2 text-info"></i> <?= htmlspecialchars($m['nombre']) ?>
                        </td>
                        <td><?= htmlspecialchars($m['descripcion'] ?: 'Sin descripción') ?></td>
                        <td>
                            <?php if ($m['estado'] === 'ACTIVO'): ?>
                                <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Activo (En Turnero)</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fa-solid fa-circle-xmark me-1"></i> Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold me-1" onclick="abrirModalEditarModulo(<?= htmlspecialchars(json_encode($m)) ?>)">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </button>

                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="toggle_estado">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $m['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>">
                                <button type="submit" class="btn btn-sm <?= $m['estado'] === 'ACTIVO' ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="Cambiar Estado">
                                    <i class="fa-solid <?= $m['estado'] === 'ACTIVO' ? 'fa-power-off' : 'fa-check' ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr id="noResultsModulosRow" style="display: none;">
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-door-closed fs-3 d-block mb-2 text-secondary"></i>
                            No se encontraron módulos o ventanillas que coincidan con la búsqueda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR MÓDULO -->
<div class="modal fade" id="modalCrearModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-door-open me-2"></i> Crear Nuevo Módulo / Ventanilla</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sede a la que pertenece <span class="text-danger">*</span></label>
                        <select name="sede_id" class="form-select" required>
                            <?php foreach ($sedes_disponibles as $sd): ?>
                                <option value="<?= $sd['id'] ?>" <?= $sede_filtro == $sd['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sd['nombre_sede']) ?> (<?= htmlspecialchars($sd['empresa_nombre']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Ventanilla / Módulo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Ej: MÓDULO 1 o VENTANILLA PREFERENCIAL">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción o Propósito</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Ej: Ventanilla general o atención prioritaria">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Ventanilla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR MÓDULO -->
<div class="modal fade" id="modalEditarModulo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i> Editar Módulo de Entrega</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="edit_mod_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sede a la que pertenece <span class="text-danger">*</span></label>
                        <select name="sede_id" id="edit_mod_sede_id" class="form-select" required>
                            <?php foreach ($sedes_disponibles as $sd): ?>
                                <option value="<?= $sd['id'] ?>">
                                    <?= htmlspecialchars($sd['nombre_sede']) ?> (<?= htmlspecialchars($sd['empresa_nombre']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre de la Ventanilla / Módulo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="edit_mod_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <input type="text" name="descripcion" id="edit_mod_descripcion" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estado de Servicio</label>
                        <select name="estado" id="edit_mod_estado" class="form-select">
                            <option value="ACTIVO">ACTIVO (En servicio y disponible en Turnero)</option>
                            <option value="INACTIVO">INACTIVO (Fuera de servicio / cerrado)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Módulo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL POBLAR MÓDULOS ESTÁNDAR PARA UNA SEDE -->
<div class="modal fade" id="modalPoblarSede" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-wand-magic-sparkles me-2"></i> Generar Módulos Estándar para Sede</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="poblar_sede">
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Esta opción crea automáticamente los módulos predeterminados (<strong>MÓDULO 1, MÓDULO 2, MÓDULO 3 y VENTANILLA PREFERENCIAL</strong>) para la sede seleccionada si aún no los tiene.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Seleccione la Sede <span class="text-danger">*</span></label>
                        <select name="sede_id_poblar" class="form-select" required>
                            <?php foreach ($sedes_disponibles as $sd): ?>
                                <option value="<?= $sd['id'] ?>"><?= htmlspecialchars($sd['nombre_sede']) ?> (<?= htmlspecialchars($sd['empresa_nombre']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fa-solid fa-check me-1"></i> Generar Módulos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalEditarModulo(m) {
    document.getElementById('edit_mod_id').value = m.id;
    document.getElementById('edit_mod_sede_id').value = m.sede_id || 1;
    document.getElementById('edit_mod_nombre').value = m.nombre;
    document.getElementById('edit_mod_descripcion').value = m.descripcion || '';
    document.getElementById('edit_mod_estado').value = m.estado;

    const modal = new bootstrap.Modal(document.getElementById('modalEditarModulo'));
    modal.show();
}

// Filtrado instantáneo en el cliente
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('buscadorModulos');
    const statusSelect = document.getElementById('filtroEstadoMod');
    const rows = Array.from(document.querySelectorAll('.fila-modulo'));
    const totalCount = document.getElementById('contadorVisibleModulos');
    const noResultsRow = document.getElementById('noResultsModulosRow');

    function filtrarFilas() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const estado = statusSelect ? statusSelect.value : '';
        let visibles = 0;

        rows.forEach(r => {
            const corpus = r.getAttribute('data-search') || '';
            const rowEstado = r.getAttribute('data-estado') || '';

            const matchQuery = !query || corpus.includes(query);
            const matchEstado = !estado || rowEstado === estado;

            if (matchQuery && matchEstado) {
                r.style.display = '';
                visibles++;
            } else {
                r.style.display = 'none';
            }
        });

        if (totalCount) totalCount.textContent = visibles;
        if (noResultsRow) noResultsRow.style.display = (visibles === 0) ? '' : 'none';
    }

    if (searchInput) searchInput.addEventListener('input', filtrarFilas);
    if (statusSelect) statusSelect.addEventListener('change', filtrarFilas);
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
