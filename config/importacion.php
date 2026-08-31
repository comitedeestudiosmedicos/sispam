<?php
/**
 * Configuración del módulo de importación de pacientes.
 *
 * Qrystalos permanece desactivado hasta completar el onboarding.
 */
return [
    'batch_size' => 1000,
    'max_errors_qrystalos' => 5,

    'qrystalos' => [
        'enabled' => false,
    ],
];
