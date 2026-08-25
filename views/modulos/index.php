<?php
require_once __DIR__ . '/../../config/app.php';
check_role(['Administrador']);

require_once __DIR__ . '/../../models/ModuloEntrega.php';

$modModel = new ModuloEntrega();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if (!empty($nombre)) {
            if ($modModel->create($nombre, $descripcion)) {
                $mensaje = "Módulo / Ventanilla <strong>" . htmlspecialchars($nombre) . "</strong> registrada exitosamente.";
            } else {
                $error = 'Error al registrar la ventanilla. Es posible que el nombre ya exista.';
            }
        } else {
            $error = 'El nombre de la ventanilla o módulo es obligatorio.';
        }
    } else if ($_POST['action'] === 'editar') {
        $id          = intval($_POST['id'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado      = $_POST['estado'] ?? 'ACTIVO';

        if ($id && !empty($nombre)) {
            if ($modModel->update($id, $nombre, $descripcion, $estado)) {
                $mensaje = "Ventanilla / Módulo actualizado correctamente.";
            } else {
                $error = 'No se pudo actualizar el módulo.';
            }
        }
    } else if ($_POST['action'] === 'toggle_estado') {
        $id = intval($_POST['id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
        $modModel->updateEstado($id, $nuevo_estado);
        $mensaje = 'Estado de la ventanilla actualizado.';
    }
}

$modulos = $modModel->getAll();
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-door-open me-2"></i> Gestión de Módulos & Ventanillas de Entrega</h4>
        <p class="text-muted small">Crea, modifica nombres y activa/desactiva los módulos de entrega usados para la asignación y Turnero TV.</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearModulo">
            <i class="fa-solid fa-plus me-1"></i> Crear Nueva Ventanilla / Módulo
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small"><i class="fa-solid fa-circle-check me-1"></i> <?= $mensaje ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card card-glass border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Nombre del Módulo / Ventanilla</th>
                        <th>Descripción / Propósito</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                        <th class="text-end pe-3">Acciones de Edición</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modulos as $m): ?>
                    <tr>
                        <td class="ps-3 fw-bold">#<?= $m['id'] ?></td>
                        <td class="fw-bold text-primary fs-5">
                            <i class="fa-solid fa-door-closed me-2 text-info"></i> <?= htmlspecialchars($m['nombre']) ?>
                        </td>
                        <td><?= htmlspecialchars($m['descripcion'] ?: 'Sin descripción') ?></td>
                        <td>
                            <?php if ($m['estado'] === 'ACTIVO'): ?>
                                <span class="badge bg-success">Activo (Habilitado para Turnero)</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo (Fuera de Servicio)</span>
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
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Crear Módulo -->
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
                        <label class="form-label fw-semibold">Nombre de la Ventanilla / Módulo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Ej: MÓDULO 5 o VENTANILLA PRIORITARIA">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción o Comentario</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Ej: Atención rápida o fórmulas de alto costo">
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

<!-- Modal Editar Módulo -->
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
                            <option value="ACTIVO">ACTIVO (En servicio)</option>
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

<script>
function abrirModalEditarModulo(m) {
    document.getElementById('edit_mod_id').value = m.id;
    document.getElementById('edit_mod_nombre').value = m.nombre;
    document.getElementById('edit_mod_descripcion').value = m.descripcion || '';
    document.getElementById('edit_mod_estado').value = m.estado;

    const modal = new bootstrap.Modal(document.getElementById('modalEditarModulo'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
