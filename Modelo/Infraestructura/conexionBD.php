<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'gestion_asistencias';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function obtenerConexionMysqli(): mysqli
{
    static $conexion = null;

    if ($conexion instanceof mysqli) {
        return $conexion;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $conexion->set_charset(DB_CHARSET);
        return $conexion;
    } catch (Throwable $error) {
        error_log('Error de conexión MySQLi: ' . $error->getMessage());
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.',
            0,
            $error
        );
    }
}

function obtenerConexionPdo(): PDO
{
    static $conexion = null;

    if ($conexion instanceof PDO) {
        return $conexion;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $conexion = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $conexion;
    } catch (Throwable $error) {
        error_log('Error de conexión PDO: ' . $error->getMessage());
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.',
            0,
            $error
        );
    }
}
