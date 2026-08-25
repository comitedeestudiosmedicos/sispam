<?php
/**
 * Conexión a Base de Datos con PDO (MySQL/MariaDB)
 * Compatible con XAMPP (Local) y VPS Hostinger (Producción)
 */

class Database {
    private static $host = 'localhost';
    private static $db_name = 'u113076213_farmacia_db';
    private static $username = 'u113076213_farmacia';
    private static $password = 'Mente21++';
    private static $charset = 'utf8mb4';
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            // Permitir sobreescritura con variables de entorno o archivo .env en VPS
            $host = getenv('DB_HOST') ?: self::$host;
            $db_name = getenv('DB_NAME') ?: self::$db_name;
            $username = getenv('DB_USER') ?: self::$username;
            $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::$password;

            $dsn = "mysql:host={$host};dbname={$db_name};charset=" . self::$charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'"
            ];

            try {
                self::$conn = new PDO($dsn, $username, $password, $options);
                self::$conn->exec("SET time_zone = '-05:00'");

                require_once __DIR__ . '/../database/migration.php';
                ejecutarMigracionBD(self::$conn);
            } catch (PDOException $e) {
                // Manejo seguro de errores sin exponer credenciales
                error_log("Error de Conexión BD: " . $e->getMessage());
                die("<div style='font-family:sans-serif; padding:20px; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px;'>
                    <h3>⚠️ Error de Conexión a la Base de Datos</h3>
                    <p>No se pudo conectar a MySQL. Asegúrese de que XAMPP (MySQL) esté iniciado y que la base de datos <code>farmacia_db</code> exista.</p>
                    <p><small>Detalle técnico: " . htmlspecialchars($e->getMessage()) . "</small></p>
                </div>");
            }
        }
        return self::$conn;
    }
}
