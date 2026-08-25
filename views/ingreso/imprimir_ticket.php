<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../models/Ingreso.php';
require_once __DIR__ . '/../../models/Empresa.php';

$id = intval($_GET['id'] ?? 0);
$ingresoModel = new Ingreso();
$empresaModel = new Empresa();

$ingreso = $ingresoModel->getById($id);
$config = $empresaModel->getConfig();

if (!$ingreso) {
    die("Ingreso no encontrado.");
}

function limpiarAccentosTicket($string) {
    if (empty($string)) return '';
    return strtr($string, [
        'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U', 'Ñ'=>'N',
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n',
        'Ü'=>'U', 'ü'=>'u', '¿'=>'', '¡'=>''
    ]);
}

// Si la solicitud proviene de la App RawBT externa (sin cookie de sesión)
if (isset($_GET['rawbt']) && $_GET['rawbt'] == '1') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"><title>Print</title></head>
    <body style="font-family: monospace;">
    <center>
    <h3><?= htmlspecialchars(limpiarAccentosTicket($config['razon_social'])) ?></h3>
    NIT: <?= htmlspecialchars($config['nit']) ?><br>
    <?= htmlspecialchars(limpiarAccentosTicket($config['direccion'])) ?><br>
    Tel: <?= htmlspecialchars($config['telefono']) ?><br>
    --------------------------------<br>
    <b>TIQUETE DE TURNO</b><br>
    <h2><?= htmlspecialchars($ingreso['ticket_numero']) ?></h2>
    --------------------------------<br>
    </center>
    <b>Fecha:</b> <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?><br>
    <b>Paciente:</b> <?= htmlspecialchars(limpiarAccentosTicket($ingreso['nombres'] . ' ' . $ingreso['apellidos'])) ?><br>
    <b>Documento:</b> <?= htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento']) ?><br>
    <b>EPS:</b> <?= htmlspecialchars(limpiarAccentosTicket($ingreso['eps_nombre'])) ?><br>
    <b>Orientador:</b> <?= htmlspecialchars(limpiarAccentosTicket($ingreso['orientador_nombre'])) ?><br>
    <?php if (!empty($ingreso['prioridad']) && $ingreso['prioridad'] !== 'NORMAL'): ?>
        <center>
        --------------------------------<br>
        <b>*** ATENCION PREFERENCIAL ***</b><br>
        <?= strip_tags(limpiarAccentosTicket(get_prioridad_badge($ingreso['prioridad']))) ?><br>
        </center>
    <?php endif; ?>
    --------------------------------<br>
    <center>
    <?= htmlspecialchars(limpiarAccentosTicket($config['pie_tiquete'])) ?><br>
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
$clean_host_url = BASE_URL . "index.php?page=imprimir_ticket&id=" . $ingreso['id'] . "&rawbt=1";
$rawbt_direct_uri = "rawbt:" . $clean_host_url;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tiquete <?= htmlspecialchars($ingreso['ticket_numero']) ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 72mm;
            margin: 0 auto;
            padding: 4mm 2mm;
            color: #000;
            background: #fff;
        }
        .header, .footer {
            text-align: center;
        }
        .header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: bold;
        }
        .ticket-num {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            border: 2px dashed #000;
            padding: 8px 4px;
            margin: 10px 0;
        }
        .info-row {
            margin-bottom: 4px;
            font-size: 12px;
            line-height: 1.3;
        }
        .bold {
            font-weight: bold;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .no-print-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: #0d6efd;
            color: #fff;
            text-align: center;
            border: none;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 10px;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-rawbt {
            background: #ff9800;
            color: #000;
        }
        @media print {
            html, body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
            .no-print-btn, .btn-rawbt, .no-print-btn-container {
                display: none !important;
            }
            #ticket-print-area {
                width: 72mm;
                margin: 0 auto;
                padding: 2mm 0;
            }
        }
    </style>
</head>
<body>

<div style="max-width: 340px; margin: 0 auto;" class="no-print-btn-container">
    <button type="button" onclick="imprimirRawBTTablet()" class="no-print-btn btn-rawbt">
        📱 IMPRIMIR EN TABLET (RAWBT / BLUETOOTH)
    </button>
    <button type="button" onclick="window.print()" class="no-print-btn">
        🖨️ IMPRIMIR NATIVO (WIFI / RED / PC)
    </button>
</div>

<div id="ticket-print-area">
    <div class="header">
        <h3><?= htmlspecialchars($config['razon_social']) ?></h3>
        <div>NIT: <?= htmlspecialchars($config['nit']) ?></div>
        <div><?= htmlspecialchars($config['direccion']) ?></div>
        <div>Tel: <?= htmlspecialchars($config['telefono']) ?></div>
    </div>

    <div class="divider"></div>

    <div class="ticket-num">
        TIQUETE DE TURNO<br>
        <?= htmlspecialchars($ingreso['ticket_numero']) ?>
    </div>

    <div class="info-row"><span class="bold">Fecha/Hora:</span> <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?></div>
    <div class="info-row"><span class="bold">Paciente:</span> <?= htmlspecialchars($ingreso['nombres'] . ' ' . $ingreso['apellidos']) ?></div>
    <div class="info-row"><span class="bold">Documento:</span> <?= htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento']) ?></div>
    <div class="info-row"><span class="bold">EPS:</span> <?= htmlspecialchars($ingreso['eps_nombre']) ?></div>
    <div class="info-row"><span class="bold">Orientador:</span> <?= htmlspecialchars($ingreso['orientador_nombre']) ?></div>
    
    <?php if (!empty($ingreso['prioridad']) && $ingreso['prioridad'] !== 'NORMAL'): ?>
        <div style="border: 2px dashed #000; padding: 6px; text-align: center; font-weight: bold; margin: 10px 0; background-color: #f8f9fa;">
            *** ATENCIÓN PREFERENCIAL / PRIORITARIA ***<br>
            <?= strip_tags(get_prioridad_badge($ingreso['prioridad'])) ?>
            <?= !empty($ingreso['prioridad_observacion']) ? '<br><small>Obs: ' . htmlspecialchars($ingreso['prioridad_observacion']) . '</small>' : '' ?>
        </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="footer">
        <p><?= nl2br(htmlspecialchars($config['pie_tiquete'])) ?></p>
        <small>SISPAM - Sistema de Gestión Farmacéutica</small>
    </div>
</div>

<script>
function imprimirRawBTTablet() {
    // Generar el texto formateado directamente desde el navegador de la tableta
    const ticketText = 
        "--------------------------------\n" +
        "   <?= addslashes(htmlspecialchars(limpiarAccentosTicket($config['razon_social']))) ?>\n" +
        "   NIT: <?= addslashes(htmlspecialchars($config['nit'])) ?>\n" +
        "   <?= addslashes(htmlspecialchars(limpiarAccentosTicket($config['direccion']))) ?>\n" +
        "   Tel: <?= addslashes(htmlspecialchars($config['telefono'])) ?>\n" +
        "--------------------------------\n" +
        "       TIQUETE DE TURNO\n" +
        "         <?= addslashes(htmlspecialchars($ingreso['ticket_numero'])) ?>\n" +
        "--------------------------------\n" +
        "Fecha: <?= date('d/m/Y h:i A', strtotime($ingreso['fecha_ingreso'])) ?>\n" +
        "Paciente: <?= addslashes(htmlspecialchars(limpiarAccentosTicket($ingreso['nombres'] . ' ' . $ingreso['apellidos']))) ?>\n" +
        "Documento: <?= addslashes(htmlspecialchars($ingreso['tipo_documento'] . ' ' . $ingreso['numero_documento'])) ?>\n" +
        "EPS: <?= addslashes(htmlspecialchars(limpiarAccentosTicket($ingreso['eps_nombre']))) ?>\n" +
        "Orientador: <?= addslashes(htmlspecialchars(limpiarAccentosTicket($ingreso['orientador_nombre']))) ?>\n" +
        "--------------------------------\n" +
        "<?= addslashes(htmlspecialchars(limpiarAccentosTicket($config['pie_tiquete']))) ?>\n" +
        "SISPAM - Gestion Farmaceutica\n\n\n\n";

    try {
        // Enviar contenido formateado base64 a RawBT (sin solicitar sesión HTTP externa)
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
        window.location.href = <?= json_encode($rawbt_direct_uri) ?>;
    }
}

window.onload = function() {
    if (window.innerWidth > 900) {
        window.print();
    }
};
</script>
</body>
</html>
