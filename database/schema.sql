-- Base de Datos para Sistema de Gestión Farmacéutica
-- Compatible con MySQL / MariaDB (XAMPP y Hostinger VPS)

CREATE DATABASE IF NOT EXISTS `farmacia_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `farmacia_db`;

-- 1. Tabla de Configuración de la Empresa / Sede
CREATE TABLE IF NOT EXISTS `empresa_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `razon_social` VARCHAR(150) NOT NULL DEFAULT 'Farmacia y Servicios Médicos S.A.S.',
    `nit` VARCHAR(30) NOT NULL DEFAULT '900.123.456-7',
    `direccion` VARCHAR(200) DEFAULT 'Calle 100 # 15-20, Bogotá, Colombia',
    `telefono` VARCHAR(50) DEFAULT '(601) 745-8000',
    `email` VARCHAR(100) DEFAULT 'contacto@farmaciasalud.com.co',
    `logo_url` VARCHAR(255) DEFAULT 'assets/img/logo_default.png',
    `pie_tiquete` TEXT,
    `video_turnero_url` VARCHAR(255) DEFAULT 'https://www.w3schools.com/html/mov_bbb.mp4',
    `marquesina_turnero` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla de Roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(50) NOT NULL UNIQUE,
    `descripcion` VARCHAR(150),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla de Usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rol_id` INT NOT NULL,
    `nombre_completo` VARCHAR(120) NOT NULL,
    `usuario` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `estado` ENUM('ACTIVO', 'INACTIVO') DEFAULT 'ACTIVO',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla de Pacientes
CREATE TABLE IF NOT EXISTS `pacientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo_documento` ENUM('CC','CE','PA','TI','RC','PEP','PPT','NV') NOT NULL DEFAULT 'CC',
    `numero_documento` VARCHAR(30) NOT NULL UNIQUE,
    `nombres` VARCHAR(80) NOT NULL,
    `apellidos` VARCHAR(80) NOT NULL,
    `eps_nombre` VARCHAR(100) NOT NULL,
    `telefono` VARCHAR(30),
    `email` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla de Ingresos y Trámites
CREATE TABLE IF NOT EXISTS `ingresos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_numero` VARCHAR(30) NOT NULL UNIQUE,
    `paciente_id` INT NOT NULL,
    `orientador_id` INT NOT NULL,
    `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado_tramite` ENUM('INGRESADO', 'EN_TRANSCRIPCION', 'TRANSCRITO_COMPLETO', 'TRANSCRITO_PENDIENTE', 'SIN_STOCK', 'ALISTADO', 'ENTREGADO', 'CANCELADO') DEFAULT 'INGRESADO',
    `locked_by_user_id` INT NULL,
    `locked_at` DATETIME NULL,
    `modulo_entrega_asignado` VARCHAR(50) NULL,
    `observaciones_pendientes` TEXT NULL,
    `pdf_transcripcion_url` VARCHAR(255) NULL,
    `acta_entrega_url` VARCHAR(255) NULL,
    `firma_paciente_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`paciente_id`) REFERENCES `pacientes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`orientador_id`) REFERENCES `usuarios`(`id`),
    FOREIGN KEY (`locked_by_user_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabla de Documentos Adjuntos del Ingreso
CREATE TABLE IF NOT EXISTS `ingreso_documentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ingreso_id` INT NOT NULL,
    `tipo_documento` ENUM('CEDULA', 'AUTORIZACION', 'ORDEN_MEDICA', 'HISTORIA_CLINICA', 'OTRO') NOT NULL,
    `ruta_archivo` VARCHAR(255) NOT NULL,
    `nombre_original` VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabla de Notificaciones Internas en Tiempo Real
CREATE TABLE IF NOT EXISTS `notificaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ingreso_id` INT NOT NULL,
    `usuario_destino_id` INT NOT NULL,
    `mensaje` TEXT NOT NULL,
    `leido` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`usuario_destino_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar Datos Semilla (Roles)
INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Control total del sistema, usuarios y parametrización'),
(2, 'Orientador', 'Ingreso de pacientes, escaneo de documentos y entrega de tiquetes'),
(3, 'Transcripcion', 'Verificación de stock, trascripción de órdenes y estado de inventario'),
(4, 'Alistamiento', 'Preparación de medicamentos (Picking) y asignación a ventanilla'),
(5, 'Entrega', 'Facturación, entrega final de medicamentos y firma del acta'),
(6, 'Regente', 'Auditoría médica, supervisión de inventario y calidad')
ON DUPLICATE KEY UPDATE `nombre`=`nombre`;

-- Insertar Datos Semilla (Configuración inicial)
INSERT INTO `empresa_config` (`id`, `razon_social`, `nit`, `direccion`, `telefono`, `email`, `pie_tiquete`, `marquesina_turnero`) VALUES
(1, 'Dispensación Médica de Colombia S.A.S.', '900.987.654-3', 'Av. El Dorado # 68C-15, Bogotá', '(601) 321-9900', 'servicioalcliente@discolombia.com.co', 'Gracias por su visita. Por favor conserve este tiquete hasta finalizar su proceso en la ventanilla de entrega.', '¡Bienvenido! Recuerde tener a la mano su documento de identidad original y orden médica vigente.')
ON DUPLICATE KEY UPDATE `razon_social`=`razon_social`;

-- Insertar Usuario Admin Predeterminado (Password: admin123)
INSERT INTO `usuarios` (`id`, `rol_id`, `nombre_completo`, `usuario`, `password_hash`, `estado`) VALUES
(1, 1, 'Administrador del Sistema', 'admin', '$2y$10$ocQKpWYJHSW70Mez67ZVrOzJ/70QLBkmxdh9M7vJR3GAM8pQF6hQ2', 'ACTIVO'),
(2, 2, 'Carlos Mendoza (Orientador)', 'orientador', '$2y$10$ocQKpWYJHSW70Mez67ZVrOzJ/70QLBkmxdh9M7vJR3GAM8pQF6hQ2', 'ACTIVO'),
(3, 3, 'Dra. María Gómez (Transcripción)', 'transcriptor', '$2y$10$ocQKpWYJHSW70Mez67ZVrOzJ/70QLBkmxdh9M7vJR3GAM8pQF6hQ2', 'ACTIVO'),
(4, 4, 'Jorge Ramírez (Aux. Farmacia)', 'alistador', '$2y$10$ocQKpWYJHSW70Mez67ZVrOzJ/70QLBkmxdh9M7vJR3GAM8pQF6hQ2', 'ACTIVO'),
(5, 5, 'Laura Torres (Facturador/Entrega)', 'entregador', '$2y$10$ocQKpWYJHSW70Mez67ZVrOzJ/70QLBkmxdh9M7vJR3GAM8pQF6hQ2', 'ACTIVO')
ON DUPLICATE KEY UPDATE `password_hash`='$2y$10$ocQKpWYJHSW70Mez67ZVrOzJ/70QLBkmxdh9M7vJR3GAM8pQF6hQ2';

-- Insertar Pacientes de Ejemplo
INSERT INTO `pacientes` (`id`, `tipo_documento`, `numero_documento`, `nombres`, `apellidos`, `eps_nombre`, `telefono`, `email`) VALUES
(1, 'CC', '88197902', 'Juan Pablo', 'Gómez Rodríguez', 'Sura EPS', '3109876543', 'juan.gomez@gmail.com'),
(2, 'CC', '1018432901', 'Ana María', 'Martínez Silva', 'Sanitas EPS', '3201234567', 'ana.martinez@hotmail.com'),
(3, 'CC', '52431980', 'Claudia Patricia', 'López Vargas', 'Nueva EPS', '3157654321', 'claudia.lopez@yahoo.com')
ON DUPLICATE KEY UPDATE `numero_documento`=`numero_documento`;
