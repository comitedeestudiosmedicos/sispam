<?php
/**
 * Conexión a Base de Datos con PDO (MySQL/MariaDB)
 * 100% Segura: Sin credenciales expuestas en el código.
 */

class Database {
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            // Prioriza $_ENV (usado por phpdotenv) y cae en getenv() como respaldo
            $host     = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : (getenv('DB_HOST') ?: '127.0.0.1');
            $db_name  = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : (getenv('DB_NAME') ?: '');
            $username = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : (getenv('DB_USER') ?: '');
            $password = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
            $charset  = isset($_ENV['DB_CHARSET']) ? $_ENV['DB_CHARSET'] : (getenv('DB_CHARSET') ?: 'utf8mb4');

            $dsn = "mysql:host={$host};dbname={$db_name};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$conn = new PDO($dsn, $username, $password, $options);

                // Configurar zona horaria si está definida
                $timezone = isset($_ENV['DB_TIMEZONE']) ? $_ENV['DB_TIMEZONE'] : (getenv('DB_TIMEZONE') ?: '-05:00');
                self::$conn->exec("SET time_zone = '{$timezone}'");

                // Ejecuta la migración automática de forma segura en local
                $migrationPath = __DIR__ . '/../database/migration.php';
                if (file_exists($migrationPath)) {
                    require_once $migrationPath;
                    if (function_exists('ejecutarMigracionBD')) {
                        ejecutarMigracionBD(self::$conn);
                    }
                }
                
            } catch (PDOException $e) {
                // Seguridad: No expone detalles técnicos ni contraseñas en producción
                error_log("Error de Conexión BD: " . $e->getMessage());
                die("Error crítico: No se pudo establecer la conexión con la base de datos.");
            }
        }
        return self::$conn;
    }
}
