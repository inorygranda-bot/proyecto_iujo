<?php
declare(strict_types=1);

/**
 * Acceso a datos de la tabla auditorias.
 */
class AuditoriaModelo
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function registrar(int $idUsuario, string $accion, string $descripcion): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO auditorias (id_usuario, accion, descripcion, fecha_hora)
             VALUES (:id_usuario, :accion, :descripcion, NOW())'
        );
        $stmt->execute([
            'id_usuario' => $idUsuario,
            'accion' => $accion,
            'descripcion' => $descripcion,
        ]);

        return (int)$this->conexion->lastInsertId();
    }

    public function obtenerTodas(): array
    {
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerIdUsuarioPorLogin(string $login): ?int
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_usuario FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $login]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int)$fila['id_usuario'] : null;
    }
}
