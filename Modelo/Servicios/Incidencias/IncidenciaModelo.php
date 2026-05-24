<?php
// ============================================================================
// MODELO: IncidenciaModelo (ACTUALIZADO - TIPOS DE INCIDENCIA Y ASIGNACIÓN)
// ============================================================================

declare(strict_types=1);

class IncidenciaModelo
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // ========================================================================
    // MÉTODOS PARA TIPOS DE INCIDENCIA
    // ========================================================================

    public function obtenerTiposIncidencia(): array
    {
        $stmt = $this->conexion->query(
            'SELECT id_tipo_incidencia, nombre_tipo, descripcion, es_descontable
             FROM tipos_incidencia
             WHERE activo = 1
             ORDER BY nombre_tipo ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearTipoIncidencia(string $nombre, ?string $descripcion = null, bool $esDescontable = true): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO tipos_incidencia (nombre_tipo, descripcion, es_descontable, activo)
             VALUES (:nombre, :descripcion, :es_descontable, 1)'
        );
        $stmt->execute([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_descontable' => $esDescontable ? 1 : 0
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function eliminarTipoIncidencia(int $idTipoIncidencia): bool
    {
        $stmt = $this->conexion->prepare(
            'UPDATE tipos_incidencia SET activo = 0 WHERE id_tipo_incidencia = :id'
        );
        return $stmt->execute(['id' => $idTipoIncidencia]);
    }

    public function actualizarTipoIncidencia(
        int $idTipoIncidencia,
        string $nombre,
        ?string $descripcion = null,
        bool $esDescontable = true
    ): bool {
        $stmt = $this->conexion->prepare(
            'UPDATE tipos_incidencia
             SET nombre_tipo = :nombre,
                 descripcion = :descripcion,
                 es_descontable = :es_descontable
             WHERE id_tipo_incidencia = :id'
        );
        return $stmt->execute([
            'id' => $idTipoIncidencia,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_descontable' => $esDescontable ? 1 : 0
        ]);
    }

    // ========================================================================
    // MÉTODOS PARA EMPLEADOS Y EMPRESAS
    // ========================================================================

    public function obtenerEmpleadoPorCodigo(string $codigoEmpleado): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT e.id_empleado, e.codigo_empleado, e.nombre, e.apellido,
                    d.id_departamento, d.nombre_departamento,
                    emp.id_empresa, emp.nombre AS nombre_empresa
             FROM Empleados e
             LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
             LEFT JOIN empresa emp ON d.id_empresa = emp.id_empresa
             WHERE e.codigo_empleado = :codigo
             LIMIT 1'
        );
        $stmt->execute(['codigo' => $codigoEmpleado]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    public function obtenerEmpleadosActivos(): array
    {
        $stmt = $this->conexion->query(
            'SELECT e.id_empleado, e.codigo_empleado, e.nombre, e.apellido,
                    emp.nombre AS nombre_empresa
             FROM Empleados e
             LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
             LEFT JOIN empresa emp ON d.id_empresa = emp.id_empresa
             ORDER BY e.apellido ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Empleados con la hora de entrada esperada (empleado > departamento > empresa > general).
     */
    public function obtenerEmpleadosConHorarioEntrada(): array
    {
        $stmt = $this->conexion->query(
            'SELECT e.id_empleado,
                    COALESCE(he.h_entrada, hd.h_entrada, hemp.h_entrada, hg.h_entrada, \'08:00\') AS h_entrada
             FROM Empleados e
             LEFT JOIN horarios he ON e.id_horario = he.id_horarios
             LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
             LEFT JOIN horarios hd ON d.id_horario = hd.id_horarios
             LEFT JOIN empresa emp ON d.id_empresa = emp.id_empresa
             LEFT JOIN horarios hemp ON emp.id_horario = hemp.id_horarios
             LEFT JOIN horarios hg ON hg.nombre_horario = \'general\'
             ORDER BY e.apellido ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ========================================================================
    // MÉTODOS PARA ASISTENCIA
    // ========================================================================

    public function obtenerAsistenciaPorEmpleadoYFecha(int $idEmpleado, string $fecha): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_asistencia, id_empleado, fecha,
                    h_llegada, h_llegada_almuerzo, h_salida_almuerzo, h_salida
             FROM asistencia
             WHERE id_empleado = :id_empleado AND fecha = :fecha
             LIMIT 1'
        );
        $stmt->execute([
            'id_empleado' => $idEmpleado,
            'fecha' => $fecha
        ]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    public function crearAsistencia(
        int $idEmpleado,
        string $fecha,
        ?string $hLlegada = null,
        ?string $hLlegadaAlmuerzo = null,
        ?string $hSalidaAlmuerzo = null,
        ?string $hSalida = null
    ): int {
        $stmt = $this->conexion->prepare(
            'INSERT INTO asistencia
             (id_empleado, fecha, h_llegada, h_llegada_almuerzo, h_salida_almuerzo, h_salida)
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
        ?string $hLlegada = null,
        ?string $hLlegadaAlmuerzo = null,
        ?string $hSalidaAlmuerzo = null,
        ?string $hSalida = null
    ): bool {
        $stmt = $this->conexion->prepare(
            'UPDATE asistencia
             SET h_llegada = COALESCE(:h_llegada, h_llegada),
                 h_llegada_almuerzo = COALESCE(:h_llegada_almuerzo, h_llegada_almuerzo),
                 h_salida_almuerzo = COALESCE(:h_salida_almuerzo, h_salida_almuerzo),
                 h_salida = COALESCE(:h_salida, h_salida)
             WHERE id_asistencia = :id_asistencia'
        );
        return $stmt->execute([
            'id_asistencia' => $idAsistencia,
            'h_llegada' => $hLlegada,
            'h_llegada_almuerzo' => $hLlegadaAlmuerzo,
            'h_salida_almuerzo' => $hSalidaAlmuerzo,
            'h_salida' => $hSalida
        ]);
    }

    public function obtenerAsistenciasPorRangoFechas(string $fechaInicio, string $fechaFin): array
    {
        error_log('obtenerAsistenciasPorRangoFechas - Fecha inicio: ' . $fechaInicio);
        error_log('obtenerAsistenciasPorRangoFechas - Fecha fin: ' . $fechaFin);

        $stmt = $this->conexion->prepare(
            'SELECT a.id_asistencia, a.id_empleado, a.fecha,
                    a.h_llegada, a.h_llegada_almuerzo, a.h_salida_almuerzo, a.h_salida,
                    e.nombre, e.apellido, e.codigo_empleado,
                    emp.nombre AS nombre_empresa
             FROM asistencia a
             JOIN Empleados e ON a.id_empleado = e.id_empleado
             LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
             LEFT JOIN empresa emp ON d.id_empresa = emp.id_empresa
             WHERE a.fecha BETWEEN :fecha_inicio AND :fecha_fin
             ORDER BY a.fecha DESC, e.apellido ASC'
        );
        $stmt->execute([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);
        $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        error_log('obtenerAsistenciasPorRangoFechas - Cantidad de asistencias: ' . count($resultado));
        return $resultado;
    }

    // ========================================================================
    // MÉTODOS PARA INCIDENCIAS (ASIGNADAS A INASISTENCIAS)
    // ========================================================================

    public function crearIncidencia(
        int $idEmpleado,
        int $idTipoIncidencia,
        string $fecha,
        ?string $hora = null,
        ?string $observaciones = null,
        ?int $idUsuarioRegistra = null
    ): int {
        $stmt = $this->conexion->prepare(
            'INSERT INTO incidencias
             (id_empleado, id_tipo_incidencia, fecha, hora, observaciones, es_justificada, id_usuario_registra)
             VALUES (:id_empleado, :id_tipo, :fecha, :hora, :observaciones, 1, :id_usuario)'
        );
        $stmt->execute([
            'id_empleado' => $idEmpleado,
            'id_tipo' => $idTipoIncidencia,
            'fecha' => $fecha,
            'hora' => $hora,
            'observaciones' => $observaciones,
            'id_usuario' => $idUsuarioRegistra
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function obtenerIncidenciasPorRangoFechas(string $fechaInicio, string $fechaFin): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT i.id_incidencia, i.id_empleado, i.id_tipo_incidencia,
                    i.fecha, i.hora, i.observaciones, i.es_justificada,
                    i.creado_en,
                    e.nombre, e.apellido, e.codigo_empleado,
                    ti.nombre_tipo, ti.es_descontable
             FROM incidencias i
             JOIN Empleados e ON i.id_empleado = e.id_empleado
             JOIN tipos_incidencia ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
             WHERE i.fecha BETWEEN :fecha_inicio AND :fecha_fin
             ORDER BY i.fecha DESC, e.apellido ASC'
        );
        $stmt->execute([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerIncidenciaPorId(int $idIncidencia): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT i.id_incidencia, i.id_empleado, i.id_tipo_incidencia,
                    i.fecha, i.hora, i.observaciones, i.es_justificada,
                    e.nombre, e.apellido, ti.nombre_tipo
             FROM incidencias i
             JOIN Empleados e ON i.id_empleado = e.id_empleado
             JOIN tipos_incidencia ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
             WHERE i.id_incidencia = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $idIncidencia]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    public function eliminarIncidencia(int $idIncidencia): bool
    {
        $stmt = $this->conexion->prepare(
            'DELETE FROM incidencias WHERE id_incidencia = :id'
        );
        return $stmt->execute(['id' => $idIncidencia]);
    }
}
