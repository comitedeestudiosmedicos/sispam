<?php
/**
 * Script de Migración Automática para Estructura RIPS, Multi-Empresa, Multi-Sede y Captura de Foto
 */

function ejecutarMigracionBD($conn) {
    try {
        // 1. Tabla 'locks' (Bloqueo de registros)
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS `locks` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `record_id` INT NOT NULL,
                    `user_id` INT NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_record` (`record_id`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (Exception $e) {}

        // 1b. Tabla 'empresas'
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `empresas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `razon_social` VARCHAR(150) NOT NULL,
                `nit` VARCHAR(50) NOT NULL,
                `direccion` VARCHAR(200) NULL,
                `telefono` VARCHAR(50) NULL,
                `email` VARCHAR(100) NULL,
                `logo_url` VARCHAR(255) NULL,
                `estado` ENUM('Activo','Inactivo') DEFAULT 'Activo',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insertar empresa por defecto si la tabla está vacía
        $stmtE = $conn->query("SELECT COUNT(*) FROM `empresas`");
        if ($stmtE->fetchColumn() == 0) {
            $conn->exec("
                INSERT INTO `empresas` (`id`, `razon_social`, `nit`, `direccion`, `telefono`, `email`, `estado`) 
                VALUES (1, 'Dispensación Médica de Colombia S.A.S.', '900.987.654-3', 'Carrera 43A # 1-50', '6044445566', 'contacto@dispensacionmedica.com.co', 'Activo')
            ");
        }

        // 2. Tabla 'sedes'
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `sedes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `empresa_id` INT NOT NULL,
                `nombre_sede` VARCHAR(100) NOT NULL,
                `codigo_sede` VARCHAR(20) NULL,
                `ciudad` VARCHAR(100) DEFAULT 'MEDELLIN',
                `direccion` VARCHAR(200) NULL,
                `telefono` VARCHAR(50) NULL,
                `hora_apertura_atencion` TIME DEFAULT '07:20:00',
                `estado` ENUM('Activo','Inactivo') DEFAULT 'Activo',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insertar sedes por defecto si está vacía
        $stmtS = $conn->query("SELECT COUNT(*) FROM `sedes`");
        if ($stmtS->fetchColumn() == 0) {
            $sedesIniciales = [
                ['Sede Prado', 'PRD', 'MEDELLIN', 'Calle 58 # 45-20', '6043228453'],
                ['Sede Ayacucho', 'AYC', 'MEDELLIN', 'Carrera 40 Nro. 49-24', '6043228453'],
                ['Sede Centro', 'CTR', 'MEDELLIN', 'Carrera 50 # 52-10', '6044440011'],
                ['Sede Poblado', 'PBL', 'MEDELLIN', 'Calle 10 # 42-15', '6044440022'],
                ['Sede Laureles', 'LRL', 'MEDELLIN', 'Circular 4 # 73-12', '6044440033'],
                ['Sede Belén', 'BLN', 'MEDELLIN', 'Carrera 76 # 32-05', '6044440044'],
                ['Sede Robledo', 'RBL', 'MEDELLIN', 'Calle 65 # 80-25', '6044440055']
            ];
            $stmtInsSede = $conn->prepare("INSERT INTO `sedes` (`empresa_id`, `nombre_sede`, `codigo_sede`, `ciudad`, `direccion`, `telefono`, `hora_apertura_atencion`, `estado`) VALUES (1, ?, ?, ?, ?, ?, '07:20:00', 'Activo')");
            foreach ($sedesIniciales as $s) {
                $stmtInsSede->execute($s);
            }
        }

        // Columna hora_apertura_atencion y hora_apertura_festivos en empresa_config
        try {
            $conn->exec("ALTER TABLE `empresa_config` ADD COLUMN `hora_apertura_atencion` TIME DEFAULT '07:00:00'");
        } catch (Exception $e) {}
        try {
            $conn->exec("ALTER TABLE `empresa_config` ADD COLUMN `hora_apertura_festivos` TIME DEFAULT '08:00:00'");
        } catch (Exception $e) {}

        // Columnas de horario en sedes (si ya existe la tabla)
        try {
            $conn->exec("ALTER TABLE `sedes` ADD COLUMN `hora_apertura_atencion` TIME DEFAULT '07:00:00'");
        } catch (Exception $e) {}
        try {
            $conn->exec("ALTER TABLE `sedes` ADD COLUMN `hora_apertura_festivos` TIME DEFAULT '08:00:00'");
        } catch (Exception $e) {}

        // 3. Columnas en la tabla 'usuarios'
        try {
            $columnasUsuarios = [
                'empresa_id' => "INT NULL DEFAULT 1 AFTER `rol_id`",
                'sede_id'    => "INT NULL DEFAULT 1 AFTER `empresa_id`"
            ];
            $stmtU = $conn->query("SHOW COLUMNS FROM `usuarios`");
            $colsExistentesU = $stmtU ? $stmtU->fetchAll(PDO::FETCH_COLUMN) : [];

            foreach ($columnasUsuarios as $colName => $colDef) {
                if (!in_array($colName, $colsExistentesU)) {
                    try { $conn->exec("ALTER TABLE `usuarios` ADD COLUMN `{$colName}` {$colDef}"); } catch (Exception $e) {}
                }
            }
        } catch (Exception $e) {}

        // 4. Columnas en la tabla 'pacientes'
        try {
            $columnasPacientes = [
                'fecha_nacimiento'              => "DATE NULL AFTER `numero_documento`",
                'ciudad_expedicion'             => "VARCHAR(100) DEFAULT 'MEDELLIN-ANT-05001'",
                'estado'                        => "ENUM('Activo','Inactivo') DEFAULT 'Activo'",
                'primer_apellido'               => "VARCHAR(80) NULL",
                'segundo_apellido'              => "VARCHAR(80) NULL",
                'primer_nombre'                 => "VARCHAR(80) NULL",
                'segundo_nombre'                => "VARCHAR(80) NULL",
                'pais_nacimiento'               => "VARCHAR(100) DEFAULT 'COLOMBIA'",
                'nacionalidad'                  => "VARCHAR(100) DEFAULT 'COLOMBIANA'",
                'ciudad_nacimiento'             => "VARCHAR(100) DEFAULT 'MEDELLIN-ANT-05001'",
                'sexo'                          => "ENUM('Masculino','Femenino','Indeterminado o Intersexual') DEFAULT 'Masculino'",
                'identidad_genero'              => "VARCHAR(80) NULL",
                'estado_civil'                  => "VARCHAR(50) DEFAULT 'Soltero(a)'",
                'grupo_sanguineo'               => "VARCHAR(10) DEFAULT 'O+'",
                'sede_atencion'                 => "VARCHAR(100) DEFAULT 'Sede Prado'",
                'contacto_emergencia_nombre'    => "VARCHAR(120) NULL",
                'contacto_emergencia_telefono'  => "VARCHAR(30) NULL",
                'contacto_emergencia_parentesco'=> "VARCHAR(50) NULL",
                'grupo_poblacional'             => "VARCHAR(100) DEFAULT 'Otro Grupo Poblacional'",
                'grupo_etnico'                  => "VARCHAR(100) DEFAULT 'No Aplica'",
                'comunidad_etnica'              => "VARCHAR(100) NULL",
                'tipo_discapacidad'             => "VARCHAR(100) DEFAULT 'No Aplica'",
                'tipo_escolaridad'              => "VARCHAR(100) DEFAULT 'NA'",
                'direccion_residencia'          => "VARCHAR(200) NULL",
                'indicativo_1'                  => "VARCHAR(10) DEFAULT '+57'",
                'numero_celular'                => "VARCHAR(30) NULL",
                'indicativo_2'                  => "VARCHAR(10) DEFAULT '+57'",
                'otro_telefono'                 => "VARCHAR(30) NULL",
                'ciudad_residencia'             => "VARCHAR(100) DEFAULT 'MEDELLIN-ANT-05001'",
                'zona'                          => "ENUM('Urbana','Rural') DEFAULT 'Urbana'",
                'barrio'                        => "VARCHAR(100) DEFAULT 'El Poblado'",
                'direccion_laboral'             => "VARCHAR(200) NULL",
                'telefono_laboral'              => "VARCHAR(30) NULL",
                'ocupacion'                     => "VARCHAR(100) DEFAULT 'Empleado'",
                'tipo_afiliado'                 => "VARCHAR(100) DEFAULT 'Contributivo Cotizante'",
                'nivel_socioeconomico'          => "VARCHAR(50) DEFAULT 'CATEGORIA A'",
                'estrato_socioeconomico'        => "TINYINT DEFAULT 3",
                'fecha_sgsss'                   => "DATE NULL",
                'fecha_afiliacion'              => "DATE NULL",
                'municipio_afiliacion'          => "VARCHAR(100) DEFAULT 'MEDELLIN-ANT-05001'",
                'ips_primaria'                  => "VARCHAR(255) DEFAULT '900294794 - COMITE DE ESTUDIOS MEDICOS SAS'",
                'ips_remite'                    => "VARCHAR(255) NULL",
                'empleador'                     => "VARCHAR(150) NULL"
            ];

            $stmtP = $conn->query("SHOW COLUMNS FROM `pacientes`");
            $colsExistentesP = $stmtP ? $stmtP->fetchAll(PDO::FETCH_COLUMN) : [];

            foreach ($columnasPacientes as $colName => $colDef) {
                if (!in_array($colName, $colsExistentesP)) {
                    try { $conn->exec("ALTER TABLE `pacientes` ADD COLUMN `{$colName}` {$colDef}"); } catch (Exception $e) {}
                }
            }
        } catch (Exception $e) {}

        // 5. Columnas en la tabla 'ingresos'
        try {
            $columnasIngresos = [
                'empresa_id'               => "INT NULL DEFAULT 1 AFTER `id`",
                'sede_id'                  => "INT NULL DEFAULT 1 AFTER `empresa_id`",
                'prioridad'                => "ENUM('NORMAL','TERCERA_EDAD','EMBARAZADA','DISCAPACIDAD','NIÑO_LACTANTE','OTRO_PREFERENCIAL') DEFAULT 'NORMAL'",
                'prioridad_observacion'    => "VARCHAR(255) NULL",
                'persona_reclama'          => "ENUM('PACIENTE_DIRECTO','TERCERO_ACUDIENTE') DEFAULT 'PACIENTE_DIRECTO'",
                'ips_remite'               => "VARCHAR(255) NULL AFTER `persona_reclama`",
                'pdf_alistamiento'         => "VARCHAR(255) NULL",
                'faltantes_alistamiento'   => "TEXT NULL",
                'alistado_por_user_id'     => "INT NULL",
                'fecha_alistado'           => "DATETIME NULL",
                'foto_paciente_url'        => "VARCHAR(255) NULL",
                'estado_verificacion'      => "ENUM('PENDIENTE','VERIFICADA','CON_ERRORES') DEFAULT 'PENDIENTE'",
                'verificado_por_user_id'  => "INT NULL",
                'fecha_verificacion'       => "DATETIME NULL",
                'observacion_verificacion' => "TEXT NULL",
                'pdf_formula_final_url'    => "VARCHAR(255) NULL",
                'contiene_mipres'          => "ENUM('SI','NO') NULL"
            ];

            $stmtI = $conn->query("SHOW COLUMNS FROM `ingresos`");
            $colsExistentesI = $stmtI ? $stmtI->fetchAll(PDO::FETCH_COLUMN) : [];

            foreach ($columnasIngresos as $colName => $colDef) {
                if (!in_array($colName, $colsExistentesI)) {
                    try { $conn->exec("ALTER TABLE `ingresos` ADD COLUMN `{$colName}` {$colDef}"); } catch (Exception $e) {}
                }
            }

            // Asegurar que estado_tramite sea VARCHAR(50) para admitir nuevos estados de flujo (ESPERA_ENTREGA, EN_ENTREGA)
            try {
                $conn->exec("ALTER TABLE `ingresos` MODIFY COLUMN `estado_tramite` VARCHAR(50) NOT NULL DEFAULT 'INGRESADO'");
            } catch (Exception $e) {}
        } catch (Exception $e) {}

        // 6. Sembrar Roles y Permisos para Monitor y Supervisor de Alistamiento
        try {
            $rolesAdicionales = [
                ['Monitor', '[\"dashboard\",\"monitoreo\",\"expedientes\"]'],
                ['Supervisor de Alistamiento', '[\"dashboard\",\"alistamiento\",\"expedientes\"]']
            ];

            foreach ($rolesAdicionales as $r) {
                $stmtCheckR = $conn->prepare("SELECT COUNT(*) FROM `roles` WHERE `nombre` = ?");
                $stmtCheckR->execute([$r[0]]);
                if ($stmtCheckR->fetchColumn() == 0) {
                    $conn->prepare("INSERT INTO `roles` (`nombre`, `permisos`) VALUES (?, ?)")->execute($r);
                }
            }
        } catch (Exception $e) {}

        // 7. Soporte Multi-Sede en 'modulos_entrega'
        try {
            $stmtColsM = $conn->query("SHOW COLUMNS FROM `modulos_entrega`");
            $colsExistentesM = $stmtColsM ? $stmtColsM->fetchAll(PDO::FETCH_COLUMN) : [];
            if (!in_array('sede_id', $colsExistentesM)) {
                try { $conn->exec("ALTER TABLE `modulos_entrega` ADD COLUMN `sede_id` INT NOT NULL DEFAULT 1 AFTER `id`"); } catch (Exception $e) {}
            }
            // Eliminar índice único antiguo sobre nombre y crear índice compuesto (sede_id, nombre)
            try { $conn->exec("ALTER TABLE `modulos_entrega` DROP INDEX `nombre`"); } catch (Exception $e) {}
            try { $conn->exec("ALTER TABLE `modulos_entrega` ADD UNIQUE KEY `idx_sede_modulo_nombre` (`sede_id`, `nombre`)"); } catch (Exception $e) {}
        } catch (Exception $e) {}

    } catch (Exception $e) {
        error_log("Error al ejecutar migración de la base de datos: " . $e->getMessage());
    }
}
