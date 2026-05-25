<?php
// ===================================================================================================================
// Descripción: Este archivo contiene las funciones para establecer y gestionar las conexiones a la base de datos.
//              Define constantes de configuración para la conexión y proporciona dos métodos:
//              uno para obtener una conexión usando la extensión MySQLi y otro usando la extensión PDO.
// ===================================================================================================================

declare(strict_types=1); // Forzar el uso de tipos estrictos para mejorar la calidad del código.

// ===================================================================================================================
// Constantes de Configuración de la Base de Datos
// Estas constantes definen los parámetros necesarios para conectar a la base de datos MySQL.
// ===================================================================================================================
const DB_HOST = 'localhost';        // Host de la base de datos (ej. 'localhost' o una IP).
const DB_PORT = 3306;               // Puerto de la base de datos MySQL.
const DB_NAME = 'gestion_asistencias'; // Nombre de la base de datos.
const DB_USER = 'root';             // Usuario de la base de datos.
const DB_PASS = '';                 // Contraseña del usuario de la base de datos.
const DB_CHARSET = 'utf8mb4';       // Conjunto de caracteres a usar para la conexión.

// ===================================================================================================================
// Función: obtenerConexionMysqli()
// Descripción: Proporciona una instancia de conexión a la base de datos utilizando la extensión MySQLi.
//              Implementa el patrón Singleton para asegurar que solo exista una conexión activa.
// ===================================================================================================================
function obtenerConexionMysqli(): mysqli
{
    static $conexion = null; // Variable estática para almacenar la instancia de conexión (patrón Singleton).

    // Si ya existe una conexión MySQLi activa, retornarla directamente.
    if ($conexion instanceof mysqli) {
        return $conexion;
    }

    // Configurar MySQLi para reportar errores como excepciones, facilitando el manejo de errores.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        // Intentar establecer la conexión con los parámetros definidos.
        $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        // Establecer el conjunto de caracteres para la conexión.
        $conexion->set_charset(DB_CHARSET);
        return $conexion;
    } catch (Throwable $error) {
        // En caso de error de conexión, registrar el error y lanzar una excepción.
        error_log('Error de conexión MySQLi: ' . $error->getMessage());
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.',
            0,
            $error
        );
    }
}

// ===================================================================================================================
// Función: obtenerConexionPdo()
// Descripción: Proporciona una instancia de conexión a la base de datos utilizando la extensión PDO.
//              Implementa el patrón Singleton para asegurar que solo exista una conexión activa.
// ===================================================================================================================
function obtenerConexionPdo(): PDO
{
    static $conexion = null; // Variable estática para almacenar la instancia de conexión (patrón Singleton).

    // Si ya existe una conexión PDO activa, retornarla directamente.
    if ($conexion instanceof PDO) {
        return $conexion;
    }

    // Construir el DSN (Data Source Name) para la conexión PDO.
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        // Intentar establecer la conexión PDO con los parámetros y opciones definidos.
        $conexion = new PDO(
            $dsn,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,     // Configurar PDO para lanzar excepciones en caso de errores.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Establecer el modo de fetch predeterminado a array asociativo.
                PDO::ATTR_EMULATE_PREPARES => false,                // Deshabilitar la emulación de prepared statements para mayor seguridad y rendimiento.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'", // Asegurar la codificación correcta.
            ]
        );

        return $conexion;
    } catch (Throwable $error) {
        // En caso de error de conexión, registrar el error y lanzar una excepción.
        error_log('Error de conexión PDO: ' . $error->getMessage());
        throw new RuntimeException(
            'No se pudo establecer la conexión con la base de datos.',
            0,
            $error
        );
    }
}
