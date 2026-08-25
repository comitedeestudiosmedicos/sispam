<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/Empresa.php';

$id = intval($_GET['id'] ?? 0);
$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$ingreso = $ingresoModel->getById($id);
$config  = $empresaModel->getConfig();

if (!$ingreso) {
    die("Registro de alistamiento no encontrado.");
}

function limpiarAccentosTicket($string) {
    if (empty($string)) return '';
    return strtr($string, [
        'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'N',
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n',
        'Ü'=>'U', 'ü'=>'u', '¿'=>'', '¡'=>''
    ]);
}

// Si la solicitud proviene de la App RawBT externa (sin cookies de sesión)
if (isset($_GET['rawbt']) && $_GET['rawbt'] == '1') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"><title>Print Alistamiento</title></head>
    <body style="font-family: monospace;">
    <center>
    <h3><?= htmlspecialchars(limpiarAccentosTicket($config['razon_social'])) ?></h3>
    NIT: <?= htmlspecialchars($config['nit']) ?><br>
    --------------------------------<br>
    <b>TIQUETE DE ALISTAMIENTO</b><br>
    <h2><?= htmlspecialchars($ingreso['ticket_numero']) ?></h2>
    --------------------------------<br>
    </center>
    <b>Fecha:</b> <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?><br>
    <b>Paciente:</b> <?= htmlspecialchars(limpiarAccentosTicket($ingreso['nombres'] . ' ' . $ingreso['apellidos'])) ?><br>
    <b>Documento:</b> <?= htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento']) ?><br>
    <b>EPS:</b> <?= htmlspecialchars(limpiarAccentosTicket($ingreso['eps_nombre'])) ?><br>
    <b>Estado:</b> EN ALISTAMIENTO<br>
    --------------------------------<br>
    <center>
    <small>SISPAM - Sistema de Gestion Farmaceutica</small>
    </center>
    </body>
    </html>
    <?php
    exit;
}

// Verificar autenticación para navegación de usuario
check_auth();

// URL limpia del tiquete para la App RawBT
$clean_host_url = BASE_URL . "index.php?page=imprimir_ticket_alistamiento&id=" . $ingreso['id'] . "&rawbt=1";
$rawbt_direct_uri = "rawbt:" . base64_encode($clean_host_url);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiquete Alistamiento - <?= htmlspecialchars($ingreso['ticket_numero']) ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000000;
            background-color: #ffffff;
            width: 72mm;
            margin: 0 auto;
            padding: 4mm 2mm;
        }
        .ticket-container {
            width: 100%;
            margin: 0 auto;
            border: 1px dashed #000000;
            padding: 10px 5px;
        }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000000; margin: 8px 0; }
        .ticket-num { font-size: 24px; font-weight: bold; margin: 5px 0; }
        .btn-print {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: #0d6efd;
            color: #ffffff;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            border: none;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-rawbt {
            background-color: #ff9800;
            color: #000000;
        }
        @media print {
            html, body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
            .no-print { display: none !important; }
            .ticket-container { border: none; padding: 2mm 0; }
        }
    </style>
</head>
<body>

<div style="max-width: 340px; margin: 0 auto;">
    <a href="<?= $rawbt_intent ?>" class="btn-print btn-rawbt no-print">📱 IMPRIMIR EN TABLET CON RAWBT (BLUETOOTH / OTG)</a>
    <button class="btn-print no-print" onclick="window.print()">🖨️ IMPRIMIR NATIVO (WIFI / RED / PC)</button>
</div>

<div class="ticket-container">
    <div class="text-center">
        <div class="text-bold"><?= htmlspecialchars($config['razon_social']) ?></div>
        <div>NIT: <?= htmlspecialchars($config['nit']) ?></div>
        <div><?= htmlspecialchars($ingreso['sede_nombre'] ?? 'SEDE PRINCIPAL') ?></div>
    </div>

    <div class="divider"></div>

    <div class="text-center">
        <small>TIQUETE DE ALISTAMIENTO</small>
        <div class="ticket-num"><?= htmlspecialchars($ingreso['ticket_numero']) ?></div>
        <div><strong>Prioridad:</strong> <?= htmlspecialchars($ingreso['prioridad'] ?? 'NORMAL') ?></div>
    </div>

    <div class="divider"></div>

    <div>
        <div><strong>PACIENTE:</strong></div>
        <div class="text-bold"><?= htmlspecialchars($ingreso['nombres'] . ' ' . $ingreso['apellidos']) ?></div>
        <div><strong>DOC:</strong> <?= htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento']) ?></div>
        <div><strong>EPS:</strong> <?= htmlspecialchars($ingreso['eps_nombre']) ?></div>
    </div>

    <div class="divider"></div>

    <div>
        <div><strong>VENTANILLA ASIGNADA:</strong></div>
        <div class="text-bold text-center" style="font-size: 16px;"><?= htmlspecialchars($ingreso['modulo_entrega_asignado'] ?? 'Ventanilla General') ?></div>
    </div>

    <?php if (!empty($ingreso['faltantes_alistamiento'])): ?>
    <div class="divider"></div>
    <div>
        <div><strong>⚠️ NOVEDADES / FALTANTES:</strong></div>
        <small><?= nl2br(htmlspecialchars($ingreso['faltantes_alistamiento'])) ?></small>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="text-center" style="font-size: 11px;">
        <div>Fecha Ingreso: <?= date('d/m/Y H:i', strtotime($ingreso['fecha_ingreso'])) ?></div>
        <div>Fecha Alistado: <?= date('d/m/Y H:i') ?></div>
        <div style="margin-top: 8px;">Por favor espere a ser llamado en pantalla TV.</div>
    </div>
</div>

<script>
function imprimirRawBTTablet() {
    const ticketText = 
        "--------------------------------\n" +
        "   <?= addslashes(htmlspecialchars(limpiarAccentosTicket($config['razon_social']))) ?>\n" +
        "   NIT: <?= addslashes(htmlspecialchars($config['nit'])) ?>\n" +
        "--------------------------------\n" +
        "     TIQUETE ALISTAMIENTO\n" +
        "         <?= addslashes(htmlspecialchars($ingreso['ticket_numero'])) ?>\n" +
        "--------------------------------\n" +
        "Fecha: <?= date('d/m/Y H:i', strtotime($ingreso['fecha_ingreso'])) ?>\n" +
        "Paciente: <?= addslashes(htmlspecialchars(limpiarAccentosTicket($ingreso['nombres'] . ' ' . $ingreso['apellidos']))) ?>\n" +
        "Documento: <?= addslashes(htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento'])) ?>\n" +
        "EPS: <?= addslashes(htmlspecialchars(limpiarAccentosTicket($ingreso['eps_nombre']))) ?>\n" +
        "Ventanilla: <?= addslashes(htmlspecialchars(limpiarAccentosTicket($ingreso['modulo_entrega_asignado'] ?? 'Ventanilla General'))) ?>\n" +
        "--------------------------------\n" +
        "SISPAM - Gestion Farmaceutica\n\n\n\n";

    try {
        const base64Data = btoa(unescape(encodeURIComponent(ticketText)));
        const rawbtBase64Uri = 'rawbt:base64,' + base64Data;

        fetch('http://127.0.0.1:40213/print?base64=' + base64Data, { mode: 'no-cors' })
            .then(function() {
                console.log("Enviado por socket local a RawBT");
            })
            .catch(function() {
                window.location.href = rawbtBase64Uri;
            });

        setTimeout(function() {
            window.location.href = rawbtBase64Uri;
        }, 150);
    } catch (e) {
        window.location.href = 'rawbt:' + <?= json_encode($clean_host_url) ?>;
    }
}

window.onload = function() {
    if (window.location.search.includes('auto_print=1') || window.innerWidth > 900) {
        window.print();
    }
};
</script>
</body>
</html>
