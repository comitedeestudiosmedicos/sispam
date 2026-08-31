<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Paciente.php';

$tipo_doc = trim($_GET['tipo_doc'] ?? $_GET['tipo_documento'] ?? 'CC');
$num_doc  = trim($_GET['num_doc'] ?? $_GET['numero_documento'] ?? '');

if (empty($num_doc)) {
    echo json_encode(['status' => 'error', 'message' => 'Número de documento requerido']);
    exit;
}

$pacienteModel = new Paciente();
$paciente = $pacienteModel->getByDocumento($tipo_doc, $num_doc);

$nombrePaciente = $paciente ? trim(($paciente['primer_nombre'] ?? $paciente['nombres'] ?? '') . ' ' . ($paciente['primer_apellido'] ?? $paciente['apellidos'] ?? '')) : "Paciente {$num_doc}";

$db = Database::getConnection();
$cleanDoc = preg_replace('/[^\w\-]/', '', $num_doc);

$stmt = $db->prepare("
    SELECT i.id, i.ticket_numero, i.estado_tramite, i.created_at, i.updated_at
    FROM ingresos i
    LEFT JOIN pacientes p ON i.paciente_id = p.id
    WHERE (p.numero_documento = :num_doc1 OR REPLACE(REPLACE(p.numero_documento, '.', ''), ' ', '') = :clean_doc1)
       OR (i.ticket_numero LIKE :tk_pattern)
    ORDER BY i.id DESC LIMIT 10
");
$stmt->execute([
    ':num_doc1'    => $num_doc,
    ':clean_doc1'  => $cleanDoc,
    ':tk_pattern'  => '%' . $cleanDoc . '%'
]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

$atenciones = [];
$incompletas = 0;
$alertaDuplicado = null;

foreach ($historial as $h) {
    $fecha = date('d/m/Y h:i A', strtotime($h['created_at']));
    $estado = $h['estado_tramite'] ?? 'PENDIENTE';
    $clase = 'success';
    $etiqueta = $estado;
    $faltantes = [];

    if (in_array($estado, ['PENDIENTE', 'TRANSCRITO_PENDIENTE', 'RECHAZADO'])) {
        $clase = 'danger';
        $incompletas++;
    } elseif ($estado === 'EN_PROCESO' || $estado === 'ALISTADO' || $estado === 'ESPERA_ENTREGA') {
        $clase = 'warning';
    }

    $atenciones[] = [
        'id'        => $h['id'],
        'fecha'     => $fecha,
        'turno'     => $h['ticket_numero'] ?? ('ID-' . $h['id']),
        'estado'    => $estado,
        'etiqueta'  => $etiqueta,
        'clase'     => $clase,
        'faltantes' => $faltantes
    ];
}

echo json_encode([
    'status'           => 'success',
    'paciente'         => [
        'nombre'    => $nombrePaciente,
        'documento' => $num_doc
    ],
    'atenciones'       => $atenciones,
    'total'            => count($atenciones),
    'incompletas'      => $incompletas,
    'alerta_duplicado' => $alertaDuplicado
], JSON_UNESCAPED_UNICODE);
