<?php

declare(strict_types=1);

class IncidenciaModelo
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }
    
    public function obtenerEmpleadoPorCodigo(string $codigoEmpleado): array|false
    {
        $stmt = $this->conexion->prepare(
            'SELECT e.id_empleado, e.codigo_empleado, e.nombre, e.apellido, em.nombre as nombre_empresa
             FROM Empleados e
             JOIN departamento d ON e.id_departamento = d.id_departamento
             JOIN Empresa em ON d.id_empresa = em.id_empresa
             WHERE e.codigo_empleado = :codigo LIMIT 1'
        );
        $stmt->execute(['codigo' => $codigoEmpleado]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerAsistenciaPorEmpleadoYFecha(int $idEmpleado, string $fecha): array|false
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_asistencia, h_llegada, h_llegada_almuerzo, h_salida_almuerzo, h_salida
             FROM asistencia
             WHERE id_empleado = :id_empleado AND fecha = :fecha LIMIT 1'
        );
        $stmt->execute([
            'id_empleado' => $idEmpleado,
            'fecha' => $fecha
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crearAsistencia(
        int $idEmpleado,
        string $fecha,
        string $hLlegada,
        string $hLlegadaAlmuerzo,
        string $hSalidaAlmuerzo,
        string $hSalida
    ): int {
        $stmt = $this->conexion->prepare(
            'INSERT INTO asistencia (id_empleado, fecha, h_llegada, h_llegada_almuerzo, h_salida_almuerzo, h_salida)
             VALUES (:id_empleado, :fecha, :h_llegada, :h_llegada_almuerzo, :h_salida_almuerzo, :h_salida)'
        );
        $stmt->execute([
            'id_empleado' => $idEmpleado,
            'fecha' => $fecha,
            'h_llegada' => $hLlegada,
            'h_llegada_almuerzo' => $hLlegadaAlmuerzo,
            'h_salida_almuerzo' => $hSalidaAlmuerzo,
            'h_salida' => $hSalida
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function actualizarAsistencia(
        int $idAsistencia,
        string $hLlegada,
        string $hLlegadaAlmuerzo,
        string $hSalidaAlmuerzo,
        string $hSalida
    ): bool {
        $stmt = $this->conexion->prepare(
            'UPDATE asistencia
             SET h_llegada = :h_llegada,
                 h_llegada_almuerzo = :h_llegada_almuerzo,
                 h_salida_almuerzo = :h_salida_almuerzo,
                 h_salida = :h_salida
             WHERE id_asistencia = :id_asistencia'
        );
        return $stmt->execute([
            'h_llegada' => $hLlegada,
            'h_llegada_almuerzo' => $hLlegadaAlmuerzo,
            'h_salida_almuerzo' => $hSalidaAlmuerzo,
            'h_salida' => $hSalida,
            'id_asistencia' => $idAsistencia
        ]);
    }
    
    public function obtenerTiposIncidencia(): array
    {
        $stmt = $this->conexion->query('SELECT id_tipo_incidencia, nombre_tipo, es_descontable FROM tipo_incidencias');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearTipoIncidencia(string $nombre, ?string $descripcion = null, bool $esDescontable = true): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO tipo_incidencias (nombre_tipo, descripcion, es_descontable)
             VALUES (:nombre_tipo, :descripcion, :es_descontable)'
        );
        $stmt->execute([
            'nombre_tipo' => $nombre,
            'descripcion' => $descripcion,
            'es_descontable' => (int)$esDescontable
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function actualizarTipoIncidencia(int $idTipoIncidencia, string $nombre, ?string $descripcion = null, bool $esDescontable = true): bool
    {
        $stmt = $this->conexion->prepare(
            'UPDATE tipo_incidencias
             SET nombre_tipo = :nombre_tipo,
                 descripcion = :descripcion,
                 es_descontable = :es_descontable
             WHERE id_tipo_incidencia = :id_tipo_incidencia'
        );
        return $stmt->execute([
            'nombre_tipo' => $nombre,
            'descripcion' => $descripcion,
            'es_descontable' => (int)$esDescontable,
            'id_tipo_incidencia' => $idTipoIncidencia
        ]);
    }

    public function obtenerAsistenciasPorRangoFechas(string $fechaInicio, string $fechaFin): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT a.id_asistencia, a.id_empleado, a.fecha, a.h_llegada, a.h_llegada_almuerzo, a.h_salida_almuerzo, a.h_salida,
                    e.codigo_empleado, e.nombre, e.apellido, em.nombre as nombre_empresa
             FROM asistencia a
             JOIN Empleados e ON a.id_empleado = e.id_empleado
             JOIN departamento d ON e.id_departamento = d.id_departamento
             JOIN Empresa em ON d.id_empresa = em.id_empresa
             WHERE a.fecha BETWEEN :fech-inicio AND :fecha_fin
             ORDER BY a.fecha, e.nombre, e.apellido'
        );
        $stmt->execute([
            'fech-inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerEmpleadosActivos(): array
    {
        $stmt = $this->conexion->query(
            'SELECT e.id_empleado, e.codigo_empleado, e.nombre, e.apellido, em.nombre as nombre_empresa
             FROM Empleados e
             JOIN departamento d ON e.id_departamento = d.id_departamento
             JOIN Empresa em ON d.id_empresa = em.id_empresa
             WHERE e.es_activo = 1 ORDER BY e.nombre, e.apellido'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerIncidenciasPorRangoFechas(string $fechaInicio, string $fechaFin): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT i.id_incidencia, i.id_empleado, i.fecha, i.hora, i.observaciones,
                    ti.nombre_tipo, ti.es_descontable
             FROM incidencias i
             JOIN tipo_incidencias ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
             WHERE i.fecha BETWEEN :fech-inicio AND :fecha_fin
             ORDER BY i.fecha, i.id_empleado'
        );
        $stmt->execute([
            'fech-inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearIncidencia(
        int $idEmpleado,
        int $idTipoIncidencia,
        string $fecha,
        ?string $hora = null,
        ?string $observaciones = null,
        ?int $idUsuarioRegistra = null
    ): int {
        $stmt = $this->conexion->prepare(
            'INSERT INTO incidencias (id_empleado, id_tipo_incidencia, fecha, hora, observaciones, id_usuario_registra)
             VALUES (:id_empleado, :id_tipo_incidencia, :fecha, :hora, :observaciones, :id_usuario_registra)'
        );
        $stmt->execute([
            'id_empleado' => $idEmpleado,
            'id_tipo_incidencia' => $idTipoIncidencia,
            'fecha' => $fecha,
            'hora' => $hora,
            'observaciones' => $observaciones,
            'id_usuario_registra' => $idUsuarioRegistra
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function eliminarIncidencia(int $idIncidencia): bool
    {
        $stmt = $this->conexion->prepare('DELETE FROM incidencias WHERE id_incidencia = :id_incidencia');
        return $stmt->execute(['id_incidencia' => $idIncidencia]);
    }

    public function obtenerEmpleadosConHorarioEntrada(): array
    {
        $stmt = $this->conexion->query(
            'SELECT e.id_empleado, e.codigo_empleado, e.nombre, e.apellido, h.h_entrada
             FROM Empleados e
             LEFT JOIN horarios h ON e.id_horario = h.id_horarios
             WHERE e.es_activo = 1 ORDER BY e.nombre, e.apellido'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
