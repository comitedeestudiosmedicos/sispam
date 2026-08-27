<?php
// config/qrystalos.php

return [
    'base_url' => $_ENV['QRYSTALOS_BASE_URL'] ?? 'https://api-test.sispam.com',
    'auth_user' => $_ENV['QRYSTALOS_AUTH_USER'] ?? '',
    'auth_pass' => $_ENV['QRYSTALOS_AUTH_PASS'] ?? '',
    'usuario_auditoria' => $_ENV['QRYSTALOS_USUARIO_AUDITORIA'] ?? 'INTEGRACION',
    
    'catalogs' => [
        'id_sede' => $_ENV['QRYSTALOS_ID_SEDE'] ?? '29',
        'id_administradora' => $_ENV['QRYSTALOS_ID_ADMINISTRADORA'] ?? '0100000010',
        'id_plan' => $_ENV['QRYSTALOS_ID_PLAN'] ?? 'TARC26',
        'ciudad' => $_ENV['QRYSTALOS_CIUDAD_DIVIPOLA'] ?? '05001',
        'id_barrio' => $_ENV['QRYSTALOS_ID_BARRIO'] ?? '05001001',
    ],

    // Valores por defecto para campos obligatorios que no vienen en el CSV actual
    'defaults' => [
        'estado_civil' => 'Soltero',
        'grupo_pob' => '5',
        'grupo_etnico' => 'N',
        'tipo_discapacidad' => 'N',
        'escolaridad' => 'NA',
        'zona' => 'U',
        'nivel_socioec' => '2',
        'tipo_usuario' => 'C',
        'estado' => 'Activo',
        'procedencia' => 'SISPAM_APP' // Identifica que viene de nuestro proyecto SISPAM
    ]
];