<?php
// ===================================================================================================================
// Descripción: Modelo para la tabla `auditorias`.
//              Contiene los métodos para interactuar directamente con la base de datos para registrar
//              y consultar los registros de auditoría.
// ===================================================================================================================

declare(strict_types=1); // Forzar el uso de tipos estrictos para mejorar la calidad del código.

/**
 * Clase `AuditoriaModelo`.
 * Proporciona métodos para el acceso a datos (CRUD) de la tabla `auditorias` en la base de datos.
 */
class AuditoriaModelo
{
    private PDO $conexion; // Almacena la instancia de la conexión a la base de datos PDO.

    /**
     * Constructor de la clase AuditoriaModelo.
     *
     * @param PDO $conexion La conexión a la base de datos (inyectada).
     */
    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    /**
     * Registra una nueva entrada de auditoría en la base de datos.
     *
     * @param int $idUsuario El ID del usuario que realiza la acción.
     * @param string $accion Una breve descripción de la acción realizada (ej. 'Inicio de Sesión').
     * @param string $descripcion Una descripción más detallada de la acción.
     * @return int El ID de la auditoría recién insertada.
     */
    public function registrar(int $idUsuario, string $accion, string $descripcion): int
    {
        // Preparar la consulta SQL para insertar un nuevo registro de auditoría.
        // `NOW()` registra la fecha y hora actual automáticamente.
        $stmt = $this->conexion->prepare(
            'INSERT INTO auditorias (id_usuario, accion, descripcion, fecha_hora)
             VALUES (:id_usuario, :accion, :descripcion, NOW())'
        );
        // Ejecutar la consulta con los parámetros proporcionados.
        $stmt->execute([
            'id_usuario' => $idUsuario,
            'accion' => $accion,
            'descripcion' => $descripcion,
        ]);

        // Obtener el ID del último registro insertado.
        $lastId = (int)$this->conexion->lastInsertId();
        // Registrar un mensaje en el log de errores para propósitos de depuración.
        error_log("AuditoriaModelo::registrar - Intento de registro. idUsuario: {$idUsuario}, accion: {$accion}, descripcion: {$descripcion}. ID generado: {$lastId}");
        return $lastId;
    }

    /**
     * Obtiene todos los registros de auditoría de la base de datos, incluyendo el nombre de usuario asociado.
     *
     * @return array Un array de arrays asociativos, cada uno representando un registro de auditoría.
     */
    public function obtenerTodas(): array
    {
        // Consulta SQL para seleccionar todas las auditorías.
        // Utiliza `COALESCE` para mostrar el login del usuario o 'ID ' + id_usuario si el usuario no existe.
        // `DATE_FORMAT` formatea la fecha y hora para una mejor lectura.
        // `LEFT JOIN` con la tabla `usuarios` para obtener el nombre de usuario.
        // Ordena los resultados por fecha y hora en orden descendente.
        $stmt = $this->conexion->query(
            'SELECT a.id_auditoria,
                    COALESCE(u.usuario, CONCAT("ID ", a.id_usuario)) AS usuario,
                    a.accion,
                    COALESCE(a.descripcion, "") AS detalle,
                    DATE_FORMAT(a.fecha_hora, "%Y-%m-%d %H:%i:%s") AS fecha
             FROM auditorias a
             LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
             ORDER BY a.fecha_hora DESC'
        );

        // Retornar todos los resultados como un array asociativo, o un array vacío si no hay resultados.
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene el ID de un usuario a partir de su nombre de usuario (login).
     *
     * @param string $login El nombre de usuario (login) a buscar.
     * @return int|null Retorna el ID del usuario si se encuentra, o null si no existe.
     */
    public function obtenerIdUsuarioPorLogin(string $login): ?int
    {
        // Preparar la consulta SQL para buscar el ID de usuario por su login.
        $stmt = $this->conexion->prepare(
            'SELECT id_usuario FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        // Ejecutar la consulta con el login proporcionado.
        $stmt->execute(['usuario' => $login]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retornar el ID del usuario como entero si se encuentra, de lo contrario, retornar null.
        return $fila ? (int)$fila['id_usuario'] : null;
    }
}
