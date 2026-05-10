<?php
declare(strict_types=1);

/**
 * Clase para manejar conexiones a la base de datos MySQL.
 * Utiliza el patrón Singleton para mantener una instancia única.
 */
class ConexionBaseDatos
{
    private const HOST = 'localhost';
    private const PUERTO = 3306;
    private const NOMBRE_BD = 'gestion_asistencias';
    private const USUARIO = 'root';
    private const CONTRASENA = '';
    private const CHARSET = 'utf8mb4';

    private static ?mysqli $conexionMySQLi = null;
    private static ?PDO $conexionPDO = null;

    /**
     * Obtiene una instancia única de mysqli conectada.
     */
    public static function obtenerConexionMySQLi(): mysqli
    {
        if (self::$conexionMySQLi instanceof mysqli) {
            return self::$conexionMySQLi;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            self::$conexionMySQLi = new mysqli(self::HOST, self::USUARIO, self::CONTRASENA, self::NOMBRE_BD, self::PUERTO);
            self::$conexionMySQLi->set_charset(self::CHARSET);
            return self::$conexionMySQLi;
        } catch (Throwable $error) {
            error_log('Error de conexión MySQLi: ' . $error->getMessage());
            throw new RuntimeException(
                'No se pudo establecer la conexión con la base de datos.',
                0,
                $error
            );
        }
    }

    /**
     * Obtiene una instancia única de PDO conectada.
     */
    public static function obtenerConexionPDO(): PDO
    {
        if (self::$conexionPDO instanceof PDO) {
            return self::$conexionPDO;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::HOST,
            self::PUERTO,
            self::NOMBRE_BD,
            self::CHARSET
        );

        try {
            self::$conexionPDO = new PDO(
                $dsn,
                self::USUARIO,
                self::CONTRASENA,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return self::$conexionPDO;
        } catch (Throwable $error) {
            error_log('Error de conexión PDO: ' . $error->getMessage());
            throw new RuntimeException(
                'No se pudo establecer la conexión con la base de datos.',
                0,
                $error
            );
        }
    }
}
