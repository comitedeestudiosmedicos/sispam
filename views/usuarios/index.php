<?php
require_once __DIR__ . '/../../config/app.php';
check_role(['Administrador']);

require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../models/Empresa.php';

$usuarioModel = new Usuario();
$empresaModel = new Empresa();

$mensaje = '';
$error = '';
$subtab = $_GET['subtab'] ?? 'usuarios';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear') {
        $rol_id     = intval($_POST['rol_id'] ?? 0);
        $empresa_id = intval($_POST['empresa_id'] ?? 1);
        $sede_id    = intval($_POST['sede_id'] ?? 1);
        $nombre     = trim($_POST['nombre_completo'] ?? '');
        $user       = trim($_POST['usuario'] ?? '');
        $pass       = trim($_POST['password'] ?? '');

        if ($rol_id && !empty($nombre) && !empty($user) && !empty($pass)) {
            if ($usuarioModel->create($rol_id, $nombre, $user, $pass, $empresa_id, $sede_id)) {
                registrar_log_auditoria('USUARIOS', 'CREAR_USUARIO', null, "Nuevo usuario registrado: {$user} ({$nombre})");
                $mensaje = 'Usuario registrado exitosamente con asignación de empresa y sede.';
            } else {
                $error = 'Error al registrar usuario. Es posible que el nombre de usuario ya exista.';
            }
        } else {
            $error = 'Por favor complete todos los campos obligatorios.';
        }
    } else if ($_POST['action'] === 'editar') {
        $id         = intval($_POST['id'] ?? 0);
        $rol_id     = intval($_POST['rol_id'] ?? 0);
        $empresa_id = intval($_POST['empresa_id'] ?? 1);
        $sede_id    = intval($_POST['sede_id'] ?? 1);
        $nombre     = trim($_POST['nombre_completo'] ?? '');
        $user       = trim($_POST['usuario'] ?? '');
        $estado     = $_POST['estado'] ?? 'ACTIVO';
        $pass       = trim($_POST['new_password'] ?? '');

        if ($id && $rol_id && !empty($nombre) && !empty($user)) {
            if ($usuarioModel->update($id, $rol_id, $nombre, $user, $estado, $pass, $empresa_id, $sede_id)) {
                registrar_log_auditoria('USUARIOS', 'EDITAR_USUARIO', $id, "Datos del usuario #{$id} ({$user}) actualizados. Estado: {$estado}");
                $mensaje = 'Datos del usuario, empresa y sede actualizados correctamente.';
            } else {
                $error = 'No se pudo actualizar los datos del usuario.';
            }
        }
    } else if ($_POST['action'] === 'toggle_estado') {
        $id = intval($_POST['id'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO';
        $usuarioModel->updateEstado($id, $nuevo_estado);
        registrar_log_auditoria('USUARIOS', 'CAMBIAR_ESTADO_USUARIO', $id, "Estado del usuario #{$id} cambiado a {$nuevo_estado}");
        $mensaje = 'Estado de usuario actualizado.';
    } else if ($_POST['action'] === 'guardar_matriz_permisos') {
        $matriz = $_POST['permisos_matriz'] ?? [];
        $roles_list = $usuarioModel->getRoles();
        
        foreach ($roles_list as $r) {
            $r_id = $r['id'];
            $permisos_seleccionados = $matriz[$r_id] ?? ['dashboard'];
            $usuarioModel->updatePermisosRol($r_id, $permisos_seleccionados);
        }

        // Actualizar los permisos en la sesión del usuario actual si es administrador
        $_SESSION['permisos'] = $usuarioModel->getPermisosRol($_SESSION['rol_id']);
        registrar_log_auditoria('USUARIOS', 'GUARDAR_MATRIZ_PERMISOS', null, "Matriz de permisos de módulos actualizada por el administrador.");
        $mensaje = 'Matriz de permisos de módulos actualizada correctamente para todos los roles.';
        $subtab = 'permisos';
    } else if ($_POST['action'] === 'crear_usuarios_prueba') {
        $usuarioModel->asegurarRolesYUsuariosPrueba();
        registrar_log_auditoria('USUARIOS', 'RESTABLECER_USUARIOS_PRUEBA', null, "Roles y usuarios de prueba restablecidos.");
        $mensaje = 'Se generaron/restablecieron exitosamente los roles y usuarios de prueba para todos los módulos (Clave por defecto: admin123).';
    }
}

$usuarios = $usuarioModel->getAll();
$roles    = $usuarioModel->getRoles();
$empresas = $empresaModel->getTodasEmpresas();
$sedes    = $empresaModel->getTodasSedes();

// Módulos disponibles para asignación dinámica de permisos
$modulos_disponibles = [
    'ingreso'                  => 'Admisión / Ingreso de Pacientes',
    'expedientes'              => 'Consulta de Expedientes',
    'transcripcion'            => 'Transcripción & Verificación de Stock',
    'monitoreo'                => 'Monitoreo & Verificación Técnica (Farmacéutico)',
    'alistamiento'             => 'Alistamiento de Medicamentos (Picking)',
    'supervision_alistamiento' => 'Supervisión de Alistamiento & Auditoría',
    'entrega'                  => 'Factura & Entrega con Firma Digital',
    'reportes'                 => 'Reportes & Analítica SLA',
    'empresa'                  => 'Parametrización de la Empresa',
    'usuarios'                 => 'Gestión de Usuarios y Permisos',
    'auditoria'                => 'Log de Auditoría & Trazabilidad'
];

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold text-primary mb-1"><i class="fa-solid fa-users-gear me-2"></i> Gestión de Usuarios, Empresa, Sede y Permisos</h4>
        <p class="text-muted small mb-0">Crea, edita datos de usuarios, asigna empresa/sede de atención y gestiona la matriz de permisos.</p>
    </div>
    <div class="col-md-5 text-md-end d-flex justify-content-md-end gap-2 flex-wrap mt-2 mt-md-0">
        <form method="POST" action="" class="d-inline">
            <input type="hidden" name="action" value="crear_usuarios_prueba">
            <button type="submit" class="btn btn-outline-success fw-bold shadow-sm" title="Generar automáticamente usuarios y roles de prueba para todos los módulos">
                <i class="fa-solid fa-user-shield me-1"></i> Restablecer Usuarios de Prueba
            </button>
        </form>

        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
            <i class="fa-solid fa-user-plus me-1"></i> Registrar Nuevo Usuario
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Navegación por Pestañas (Usuarios / Permisos) -->
<ul class="nav nav-tabs mb-4 fw-bold">
    <li class="nav-item">
        <a class="nav-link <?= $subtab === 'usuarios' ? 'active text-primary border-bottom border-3 border-primary' : 'text-muted' ?>" href="index.php?page=usuarios&subtab=usuarios">
            <i class="fa-solid fa-users me-1"></i> Lista de Usuarios
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $subtab === 'permisos' ? 'active text-primary border-bottom border-3 border-primary' : 'text-muted' ?>" href="index.php?page=usuarios&subtab=permisos">
            <i class="fa-solid fa-key me-1"></i> Matriz de Permisos por Módulo
        </a>
    </li>
</ul>

<?php if ($subtab === 'usuarios'): ?>
<!-- PESTAÑA 1: LISTADO Y EDICIÓN DE USUARIOS -->
<div class="card card-glass border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Nombre Completo</th>
                        <th>Usuario (Login)</th>
                        <th>Empresa / Sede Asignada</th>
                        <th>Perfil / Rol</th>
                        <th>Estado</th>
                        <th class="text-end pe-3">Acciones de Edición</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="ps-3 fw-bold">#<?= $u['id'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($u['nombre_completo']) ?></td>
                        <td><code><?= htmlspecialchars($u['usuario']) ?></code></td>
                        <td>
                            <div class="fw-semibold text-dark small"><i class="fa-solid fa-building me-1 text-primary"></i> <?= htmlspecialchars($u['empresa_nombre'] ?? 'Empresa Principal') ?></div>
                            <small class="text-muted"><i class="fa-solid fa-hospital-user me-1 text-success"></i> <?= htmlspecialchars($u['sede_nombre'] ?? 'Sede General') ?></small>
                        </td>
                        <td><span class="badge bg-primary fs-6"><?= htmlspecialchars($u['rol_nombre']) ?></span></td>
                        <td>
                            <?php if ($u['estado'] === 'ACTIVO'): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <!-- Botón Editar Usuario -->
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold me-1" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar Datos, Empresa, Sede y Contraseña">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </button>

                            <!-- Cambiar Estado -->
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="toggle_estado">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $u['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' ?>">
                                <button type="submit" class="btn btn-sm <?= $u['estado'] === 'ACTIVO' ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="Cambiar Estado">
                                    <i class="fa-solid <?= $u['estado'] === 'ACTIVO' ? 'fa-user-xmark' : 'fa-user-check' ?>"></i>
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

<?php else: ?>

<!-- PESTAÑA 2: MATRIZ DE PERMISOS DINÁMICOS POR ROL -->
<div class="card card-glass border-0 shadow-sm p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
        <div>
            <h5 class="fw-bold text-primary mb-1"><i class="fa-solid fa-sliders me-2"></i> Asignación de Permisos por Perfil / Rol</h5>
            <p class="text-muted small mb-0">Marca los módulos a los que cada perfil tendrá acceso en el menú y las funciones del sistema.</p>
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="action" value="guardar_matriz_permisos">
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark text-center">
                    <tr>
                        <th style="width: 250px;">Módulo del Sistema</th>
                        <?php foreach ($roles as $r): ?>
                            <th><?= htmlspecialchars($r['nombre']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modulos_disponibles as $mod_key => $mod_label): ?>
                    <tr>
                        <td class="fw-bold text-dark bg-light ps-3">
                            <i class="fa-solid fa-cube text-primary me-2"></i> <?= htmlspecialchars($mod_label) ?>
                        </td>
                        <?php foreach ($roles as $r): ?>
                        <?php 
                            $permisos_rol = $usuarioModel->getPermisosRol($r['id']);
                            $is_checked = in_array($mod_key, $permisos_rol) || $r['nombre'] === 'Administrador';
                            $is_admin = ($r['nombre'] === 'Administrador');
                        ?>
                        <td class="text-center">
                            <div class="form-check d-inline-block">
                                <input class="form-check-input" type="checkbox" name="permisos_matriz[<?= $r['id'] ?>][]" value="<?= $mod_key ?>" <?= $is_checked ? 'checked' : '' ?> <?= $is_admin ? 'disabled checked' : '' ?> style="transform: scale(1.3); cursor: pointer;">
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-3">
            <button type="submit" class="btn btn-success btn-lg fw-bold px-4 shadow">
                <i class="fa-solid fa-floppy-disk me-2"></i> Guardar Matriz de Permisos
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card-glass">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Registrar Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Empresa <span class="text-danger">*</span></label>
                            <select name="empresa_id" class="form-select" required>
                                <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sede de Atención Asignada <span class="text-danger">*</span></label>
                            <select name="sede_id" class="form-select" required>
                                <?php foreach ($sedes as $sd): ?>
                                    <option value="<?= $sd['id'] ?>"><?= htmlspecialchars($sd['nombre_sede']) ?> (<?= htmlspecialchars($sd['empresa_nombre']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_completo" class="form-control" required placeholder="Ej: Carlos Mendoza">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre de Usuario (Login) <span class="text-danger">*</span></label>
                            <input type="text" name="usuario" class="form-control" required placeholder="Ej: cmendoza">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Perfil / Rol <span class="text-danger">*</span></label>
                            <select name="rol_id" class="form-select" required>
                                <option value="">-- Seleccionar Perfil --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?> - <?= htmlspecialchars($r['descripcion']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-save me-1"></i> Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content card-glass">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen me-2 text-warning"></i> Editar Datos de Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Empresa <span class="text-danger">*</span></label>
                            <select name="empresa_id" id="edit_empresa_id" class="form-select" required>
                                <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sede de Atención Asignada <span class="text-danger">*</span></label>
                            <select name="sede_id" id="edit_sede_id" class="form-select" required>
                                <?php foreach ($sedes as $sd): ?>
                                    <option value="<?= $sd['id'] ?>"><?= htmlspecialchars($sd['nombre_sede']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_completo" id="edit_nombre_completo" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre de Usuario (Login) <span class="text-danger">*</span></label>
                            <input type="text" name="usuario" id="edit_usuario" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Perfil / Rol <span class="text-danger">*</span></label>
                            <select name="rol_id" id="edit_rol_id" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estado de Cuenta</label>
                            <select name="estado" id="edit_estado" class="form-select">
                                <option value="ACTIVO">ACTIVO</option>
                                <option value="INACTIVO">INACTIVO</option>
                            </select>
                        </div>
                        <div class="col-md-12 p-3 bg-light rounded border">
                            <label class="form-label fw-semibold text-primary mb-1"><i class="fa-solid fa-key me-1"></i> Cambiar Contraseña (Opcional)</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Dejar en blanco para mantener contraseña actual">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalEditar(u) {
    document.getElementById('edit_id').value = u.id;
    document.getElementById('edit_nombre_completo').value = u.nombre_completo;
    document.getElementById('edit_usuario').value = u.usuario;
    document.getElementById('edit_rol_id').value = u.rol_id;
    document.getElementById('edit_estado').value = u.estado;
    if (document.getElementById('edit_empresa_id')) document.getElementById('edit_empresa_id').value = u.empresa_id || 1;
    if (document.getElementById('edit_sede_id')) document.getElementById('edit_sede_id').value = u.sede_id || 1;

    const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
