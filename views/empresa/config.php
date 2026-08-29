<?php
require_once __DIR__ . '/../../config/app.php';
check_role('empresa');

require_once __DIR__ . '/../../models/Empresa.php';

$empresaModel = new Empresa();
$mensaje = '';
$error = '';
$tab = $_GET['tab'] ?? 'general';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'guardar_config_general') {
        $data = [
            'razon_social' => trim($_POST['razon_social'] ?? ''),
            'nit'          => trim($_POST['nit'] ?? ''),
            'direccion'    => trim($_POST['direccion'] ?? ''),
            'telefono'     => trim($_POST['telefono'] ?? ''),
            'email'        => trim($_POST['email'] ?? ''),
            'pie_tiquete'  => trim($_POST['pie_tiquete'] ?? ''),
            'video_turnero_url' => trim($_POST['video_turnero_url'] ?? ''),
            'marquesina_turnero' => trim($_POST['marquesina_turnero'] ?? ''),
            'hora_apertura_atencion' => trim($_POST['hora_apertura_atencion'] ?? '07:00:00'),
            'hora_apertura_festivos' => trim($_POST['hora_apertura_festivos'] ?? '08:00:00'),
            'logo_url' => ''
        ];

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg'])) {
                $dir = BASE_DIR . '/assets/img/';
                if (!file_exists($dir)) mkdir($dir, 0755, true);
                $logoPath = $dir . 'logo_empresa.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                    $data['logo_url'] = 'assets/img/logo_empresa.' . $ext;
                }
            } else {
                $error = 'Formato de imagen de logo no válido (Use PNG, JPG o SVG).';
            }
        }

        if (empty($error)) {
            if ($empresaModel->updateConfig($data)) {
                registrar_log_auditoria('EMPRESA', 'ACTUALIZAR_CONFIGURACION', 1, "Configuración de parámetros de la empresa, horarios y turneros actualizada.");
                $mensaje = 'Parámetros de la empresa y horarios actualizados correctamente.';
            } else {
                $error = 'No se pudo guardar la configuración.';
            }
        }
        $tab = 'general';

    } else if ($action === 'crear_empresa') {
        $razon = trim($_POST['razon_social'] ?? '');
        $nit   = trim($_POST['nit'] ?? '');
        $dir   = trim($_POST['direccion'] ?? '');
        $tel   = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!empty($razon) && !empty($nit)) {
            if ($empresaModel->crearEmpresa($razon, $nit, $dir, $tel, $email)) {
                $mensaje = 'Nueva empresa registrada exitosamente.';
            } else {
                $error = 'Error al registrar la empresa.';
            }
        } else {
            $error = 'Razón Social y NIT son campos obligatorios.';
        }
        $tab = 'empresas';

    } else if ($action === 'editar_empresa') {
        $id    = intval($_POST['id'] ?? 0);
        $razon = trim($_POST['razon_social'] ?? '');
        $nit   = trim($_POST['nit'] ?? '');
        $dir   = trim($_POST['direccion'] ?? '');
        $tel   = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $est   = $_POST['estado'] ?? 'Activo';

        if ($id && !empty($razon) && !empty($nit)) {
            if ($empresaModel->actualizarEmpresa($id, $razon, $nit, $dir, $tel, $email, $est)) {
                $mensaje = 'Datos de la empresa actualizados correctamente.';
            } else {
                $error = 'No se pudo actualizar los datos de la empresa.';
            }
        }
        $tab = 'empresas';

    } else if ($action === 'crear_sede') {
        $emp_id = intval($_POST['empresa_id'] ?? 1);
        $nombre = trim($_POST['nombre_sede'] ?? '');
        $codigo = trim($_POST['codigo_sede'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? 'MEDELLIN');
        $dir    = trim($_POST['direccion'] ?? '');
        $tel    = trim($_POST['telefono'] ?? '');
        $hora_ap   = trim($_POST['hora_apertura_atencion'] ?? '07:00:00');
        $hora_fest = trim($_POST['hora_apertura_festivos'] ?? '08:00:00');

        if ($emp_id && !empty($nombre)) {
            if ($empresaModel->crearSede($emp_id, $nombre, $codigo, $ciudad, $dir, $tel, $hora_ap, $hora_fest)) {
                $mensaje = 'Sede de atención creada exitosamente con horarios de atención diferenciados.';
            } else {
                $error = 'Error al registrar la sede.';
            }
        } else {
            $error = 'Debe seleccionar una empresa e ingresar el nombre de la sede.';
        }
        $tab = 'sedes';

    } else if ($action === 'editar_sede') {
        $id     = intval($_POST['id'] ?? 0);
        $emp_id = intval($_POST['empresa_id'] ?? 1);
        $nombre = trim($_POST['nombre_sede'] ?? '');
        $codigo = trim($_POST['codigo_sede'] ?? '');
        $ciudad = trim($_POST['ciudad'] ?? 'MEDELLIN');
        $dir    = trim($_POST['direccion'] ?? '');
        $tel    = trim($_POST['telefono'] ?? '');
        $est    = $_POST['estado'] ?? 'Activo';
        $hora_ap   = trim($_POST['hora_apertura_atencion'] ?? '07:00:00');
        $hora_fest = trim($_POST['hora_apertura_festivos'] ?? '08:00:00');

        if ($id && $emp_id && !empty($nombre)) {
            if ($empresaModel->actualizarSede($id, $emp_id, $nombre, $codigo, $ciudad, $dir, $tel, $est, $hora_ap, $hora_fest)) {
                $mensaje = 'Sede de atención actualizada correctamente.';
            } else {
                $error = 'No se pudo actualizar los datos de la sede.';
            }
        }
        $tab = 'sedes';
    }
}

$config   = $empresaModel->getConfig();
$empresas = $empresaModel->getTodasEmpresas();
$sedes    = $empresaModel->getTodasSedes();

require_once __DIR__ . '/../layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold text-primary mb-1">
            <i class="fa-solid fa-building me-2"></i> Administrador Multi-Empresa & Multi-Sede
        </h4>
        <p class="text-muted small">Gestiona las razones sociales corporativas, las sedes de atención farmacéutica y la configuración de turneros TV.</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show small"><i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- NAVEGACIÓN POR PESTAÑAS -->
<ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3">
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'general' ? 'active bg-primary' : 'bg-white border text-dark' ?>" href="index.php?page=empresa_config&tab=general">
            <i class="fa-solid fa-gears me-1"></i> Parámetros de la Empresa & Horarios
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'empresas' ? 'active bg-primary' : 'bg-white border text-dark' ?>" href="index.php?page=empresa_config&tab=empresas">
            <i class="fa-solid fa-building me-1"></i> Empresas / Razones Sociales
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'sedes' ? 'active bg-primary' : 'bg-white border text-dark' ?>" href="index.php?page=empresa_config&tab=sedes">
            <i class="fa-solid fa-hospital-user me-1"></i> Sedes de Atención Farmacéutica
        </a>
    </li>
</ul>

<?php if ($tab === 'general'): ?>
<!-- PESTAÑA 1: PARÁMETROS GENERALES -->
<div class="card card-glass border-0 shadow-sm p-4">
    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i> Datos Generales y Horarios de Atención SLA</h5>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="guardar_config_general">
        
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Razón Social Principal</label>
                <input type="text" name="razon_social" class="form-control" value="<?= htmlspecialchars($config['razon_social']) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">NIT / RUT</label>
                <input type="text" name="nit" class="form-control" value="<?= htmlspecialchars($config['nit']) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Dirección Sede Principal</label>
                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($config['direccion']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Teléfono Contacto</label>
                <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($config['telefono']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Correo Electrónico</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($config['email']) ?>">
            </div>

            <!-- Horarios Oficiales de Apertura Diferenciados -->
            <div class="col-md-6">
                <label class="form-label fw-semibold text-primary">
                    <i class="fa-solid fa-calendar-week me-1"></i> Hora Apertura Lunes a Viernes (Inicio SLA)
                </label>
                <input type="time" name="hora_apertura_atencion" class="form-control" value="<?= htmlspecialchars(substr($config['hora_apertura_atencion'] ?? '07:00:00', 0, 5)) ?>" required>
                <div class="form-text small">Días hábiles regulares de lunes a viernes. Los tiquetes previos iniciarán su conteo SLA a partir de esta hora.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold text-warning">
                    <i class="fa-solid fa-calendar-day me-1"></i> Hora Apertura Sábados, Domingos y Festivos (Inicio SLA)
                </label>
                <input type="time" name="hora_apertura_festivos" class="form-control" value="<?= htmlspecialchars(substr($config['hora_apertura_festivos'] ?? '08:00:00', 0, 5)) ?>" required>
                <div class="form-text small">Fines de semana y días feriados oficiales en Colombia. Conteo SLA iniciará a esta hora.</div>
            </div>

            <hr class="my-4">

            <h5 class="fw-bold text-dark"><i class="fa-solid fa-ticket me-2 text-primary"></i> Personalización de Tiquete e Impresión</h5>

            <div class="col-md-8">
                <label class="form-label fw-semibold">Pie de Página en Tiquete Impreso</label>
                <textarea name="pie_tiquete" class="form-control" rows="3"><?= htmlspecialchars($config['pie_tiquete']) ?></textarea>
                <div class="form-text">Texto legal o recordatorios impresos al final del ticket térmico.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Logo Institucional</label>
                <input type="file" name="logo" class="form-control" accept="image/*">
                <?php if (!empty($config['logo_url']) && file_exists(BASE_DIR . '/' . $config['logo_url'])): ?>
                    <div class="mt-2 text-center p-2 bg-light rounded">
                        <img src="<?= $config['logo_url'] ?>" alt="Logo" style="max-height: 60px;">
                    </div>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <h5 class="fw-bold text-dark"><i class="fa-solid fa-tv me-2 text-info"></i> Configuración de Turneros TV (Pantallas)</h5>

            <div class="col-md-6">
                <label class="form-label fw-semibold">URL del Video Institucional (MP4 / Web)</label>
                <input type="url" name="video_turnero_url" class="form-control" value="<?= htmlspecialchars($config['video_turnero_url']) ?>">
                <div class="form-text">Video reproducido en bucle en las pantallas del Turnero.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Marquesina Informativa (Texto en movimiento)</label>
                <input type="text" name="marquesina_turnero" class="form-control" value="<?= htmlspecialchars($config['marquesina_turnero']) ?>">
                <div class="form-text">Mensaje continuo en la parte inferior de las pantallas.</div>
            </div>

            <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Guardar Cambios
                </button>
            </div>
        </div>
    </form>
</div>

<?php elseif ($tab === 'empresas'): ?>
<!-- PESTAÑA 2: GESTIÓN DE EMPRESAS -->
<div class="card card-glass border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building me-2 text-primary"></i> Empresas Registradas</h5>
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearEmpresa">
            <i class="fa-solid fa-plus me-1"></i> Registrar Nueva Empresa
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Razón Social</th>
                        <th>NIT</th>
                        <th>Dirección</th>
                        <th>Teléfono / Email</th>
                        <th>Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas as $emp): ?>
                    <tr>
                        <td class="ps-3 fw-bold">#<?= $emp['id'] ?></td>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($emp['razon_social']) ?></td>
                        <td><code><?= htmlspecialchars($emp['nit']) ?></code></td>
                        <td><?= htmlspecialchars($emp['direccion'] ?? 'N/A') ?></td>
                        <td>
                            <small class="d-block"><?= htmlspecialchars($emp['telefono'] ?? '') ?></small>
                            <small class="text-muted"><?= htmlspecialchars($emp['email'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="badge <?= $emp['estado'] === 'Activo' ? 'bg-success' : 'bg-secondary' ?>"><?= $emp['estado'] ?></span>
                        </td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="abrirModalEditarEmpresa(<?= htmlspecialchars(json_encode($emp)) ?>)">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'sedes'): ?>
<!-- PESTAÑA 3: GESTIÓN DE SEDES -->
<div class="card card-glass border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-hospital-user me-2 text-success"></i> Sedes de Atención Farmacéutica</h5>
        <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearSede">
            <i class="fa-solid fa-plus me-1"></i> Registrar Nueva Sede
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Nombre Sede</th>
                        <th>Código Sede</th>
                        <th>Empresa Pertenece</th>
                        <th>Ciudad / Dirección</th>
                        <th>Teléfono</th>
                        <th>Hora Apertura SLA</th>
                        <th>Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sedes as $sd): ?>
                    <tr>
                        <td class="ps-3 fw-bold">#<?= $sd['id'] ?></td>
                        <td class="fw-bold text-dark"><i class="fa-solid fa-building-circle-check text-success me-1"></i> <?= htmlspecialchars($sd['nombre_sede']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($sd['codigo_sede'] ?? 'N/A') ?></span></td>
                        <td><span class="fw-semibold text-primary"><?= htmlspecialchars($sd['empresa_nombre']) ?></span></td>
                        <td>
                            <div class="fw-bold small"><?= htmlspecialchars($sd['ciudad']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($sd['direccion'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars($sd['telefono'] ?? '') ?></td>
                        <td>
                            <span class="badge bg-light text-primary border border-primary fw-bold">
                                <i class="fa-solid fa-clock me-1"></i> <?= date('h:i A', strtotime($sd['hora_apertura_atencion'] ?? '07:20:00')) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $sd['estado'] === 'Activo' ? 'bg-success' : 'bg-secondary' ?>"><?= $sd['estado'] ?></span>
                        </td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="abrirModalEditarSede(<?= htmlspecialchars(json_encode($sd)) ?>)">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL CREAR EMPRESA -->
<div class="modal fade" id="modalCrearEmpresa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-building me-2"></i> Registrar Nueva Empresa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear_empresa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required placeholder="Ej: Farmacéutica del Norte S.A.S.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">NIT / RUT <span class="text-danger">*</span></label>
                        <input type="text" name="nit" class="form-control" required placeholder="Ej: 901.123.456-7">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="direccion" class="form-control" placeholder="Ej: Calle 50 # 40-20">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" placeholder="Ej: 6043221100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" placeholder="contacto@empresa.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fa-solid fa-save me-1"></i> Guardar Empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR EMPRESA -->
<div class="modal fade" id="modalEditarEmpresa" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-building-circle-gear me-2 text-warning"></i> Editar Empresa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar_empresa">
                <input type="hidden" name="id" id="edit_empresa_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Razón Social <span class="text-danger">*</span></label>
                        <input type="text" name="razon_social" id="edit_empresa_razon" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">NIT / RUT <span class="text-danger">*</span></label>
                        <input type="text" name="nit" id="edit_empresa_nit" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="direccion" id="edit_empresa_direccion" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" name="telefono" id="edit_empresa_telefono" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" name="email" id="edit_empresa_email" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estado</label>
                        <select name="estado" id="edit_empresa_estado" class="form-select">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CREAR SEDE -->
<div class="modal fade" id="modalCrearSede" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-hospital-user me-2"></i> Registrar Nueva Sede</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear_sede">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Empresa a la que pertenece <span class="text-danger">*</span></label>
                        <select name="empresa_id" class="form-select" required>
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Nombre de la Sede <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_sede" class="form-control" required placeholder="Ej: Sede Laureles">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Código Sede</label>
                            <input type="text" name="codigo_sede" class="form-control" placeholder="Ej: LRL">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" value="MEDELLIN">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="direccion" class="form-control" placeholder="Ej: Circular 4 # 73-12">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Teléfono Contacto</label>
                        <input type="text" name="telefono" class="form-control" placeholder="Ej: 6044440033">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-primary"><i class="fa-solid fa-calendar-week me-1"></i> Apertura Lun-Vie (SLA)</label>
                            <input type="time" name="hora_apertura_atencion" class="form-control" value="07:00" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-warning"><i class="fa-solid fa-calendar-day me-1"></i> Apertura Sáb-Dom-Festivos (SLA)</label>
                            <input type="time" name="hora_apertura_festivos" class="form-control" value="08:00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="fa-solid fa-save me-1"></i> Guardar Sede</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR SEDE -->
<div class="modal fade" id="modalEditarSede" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content card-glass">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i> Editar Sede de Atención</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar_sede">
                <input type="hidden" name="id" id="edit_sede_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Empresa a la que pertenece <span class="text-danger">*</span></label>
                        <select name="empresa_id" id="edit_sede_empresa_id" class="form-select" required>
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['razon_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Nombre de la Sede <span class="text-danger">*</span></label>
                            <input type="text" name="nombre_sede" id="edit_sede_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Código Sede</label>
                            <input type="text" name="codigo_sede" id="edit_sede_codigo" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ciudad</label>
                        <input type="text" name="ciudad" id="edit_sede_ciudad" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Dirección</label>
                        <input type="text" name="direccion" id="edit_sede_direccion" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Teléfono Contacto</label>
                        <input type="text" name="telefono" id="edit_sede_telefono" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-primary"><i class="fa-solid fa-calendar-week me-1"></i> Apertura Lun-Vie (SLA)</label>
                            <input type="time" name="hora_apertura_atencion" id="edit_sede_hora_apertura" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold text-warning"><i class="fa-solid fa-calendar-day me-1"></i> Apertura Sáb-Dom-Festivos (SLA)</label>
                            <input type="time" name="hora_apertura_festivos" id="edit_sede_hora_festivos" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Estado</label>
                        <select name="estado" id="edit_sede_estado" class="form-select">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Actualizar Sede</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalEditarEmpresa(e) {
    document.getElementById('edit_empresa_id').value = e.id;
    document.getElementById('edit_empresa_razon').value = e.razon_social;
    document.getElementById('edit_empresa_nit').value = e.nit;
    document.getElementById('edit_empresa_direccion').value = e.direccion || '';
    document.getElementById('edit_empresa_telefono').value = e.telefono || '';
    document.getElementById('edit_empresa_email').value = e.email || '';
    document.getElementById('edit_empresa_estado').value = e.estado || 'Activo';

    const modal = new bootstrap.Modal(document.getElementById('modalEditarEmpresa'));
    modal.show();
}

function abrirModalEditarSede(s) {
    document.getElementById('edit_sede_id').value = s.id;
    document.getElementById('edit_sede_empresa_id').value = s.empresa_id;
    document.getElementById('edit_sede_nombre').value = s.nombre_sede;
    document.getElementById('edit_sede_codigo').value = s.codigo_sede || '';
    document.getElementById('edit_sede_ciudad').value = s.ciudad || 'MEDELLIN';
    document.getElementById('edit_sede_direccion').value = s.direccion || '';
    document.getElementById('edit_sede_telefono').value = s.telefono || '';
    document.getElementById('edit_sede_hora_apertura').value = (s.hora_apertura_atencion || '07:00:00').substring(0,5);
    document.getElementById('edit_sede_hora_festivos').value = (s.hora_apertura_festivos || '08:00:00').substring(0,5);
    document.getElementById('edit_sede_estado').value = s.estado || 'Activo';

    const modal = new bootstrap.Modal(document.getElementById('modalEditarSede'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
