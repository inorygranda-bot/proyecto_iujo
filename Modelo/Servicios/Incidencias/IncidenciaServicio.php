<?php
// ============================================================================
// SERVICIO: IncidenciaServicio (COMPLETO - PDF BONITO Y CSV CON ESTILO)
// ============================================================================

declare(strict_types=1);

class IncidenciaServicio
{
    private IncidenciaModelo $modelo;
    private array $errores;
    private array $debugMessages;

    public function __construct(PDO $conexion)
    {
        $this->modelo = new IncidenciaModelo($conexion);
        $this->errores = [];
        $this->debugMessages = [];
    }

    // ========================================================================
    // MÉTODOS PARA MANEJO DE ERRORES
    // ========================================================================

    public function obtenerErrores(): array
    {
        return $this->errores;
    }

    private function limpiarErrores(): void
    {
        $this->errores = [];
        $this->debugMessages = [];
    }

    private function agregarError(string $mensaje): void
    {
        $this->errores[] = $mensaje;
        $this->_addDebugMessage("ERROR: " . $mensaje); // También loguear errores a debug
    }

    private function _addDebugMessage(string $mensaje): void
    {
        $this->debugMessages[] = $mensaje;
    }

    // ========================================================================
    // UTILIDADES: CONVERSIÓN DE FECHA Y HORA
    // ========================================================================

    private function convertirFecha(string $fechaEntrada): ?string
    {
        $partes = explode('/', $fechaEntrada);

        if (count($partes) === 3) {
            list($dia, $mes, $anio) = $partes;

            if (strlen($anio) === 2) {
                $anio = '20' . $anio;
            }

            $fechaValida = checkdate((int)$mes, (int)$dia, (int)$anio);

            if ($fechaValida) {
                return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            }
        }

        $this->agregarError("Fecha invalida: {$fechaEntrada}. Formato esperado: DD/MM/AA.");
        return null;
    }

    private function convertirHora(string $horaEntrada): ?string
    {
        $partes = explode(':', $horaEntrada);
        if (count($partes) === 2) {
            $h = (int)$partes[0];
            $m = (int)$partes[1];

            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                return sprintf('%02d:%02d:00', $h, $m);
            }
        }

        $this->agregarError("Hora invalida: {$horaEntrada}. Formato esperado: H:MM o HH:MM.");
        return null;
    }

    // ========================================================================
    // IMPORTACIÓN DE TXT
    // ========================================================================

    public function importarTXT(string $rutaArchivo): array
    {
        $this->limpiarErrores();

        $contenido = file_get_contents($rutaArchivo);
        $lineas = explode("\n", $contenido);

        $grupoPorEmpleadoFecha = [];
        $totalRegistros = 0;

        foreach ($lineas as $numLinea => $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            $totalRegistros++;
            $partes = preg_split('/\s+/', $linea);

            if (count($partes) < 4) {
                $this->agregarError("Linea " . ($numLinea + 1) . ": Formato invalido. Se requieren 4 campos.");
                continue;
            }

            $codigo = $partes[0];
            $fechaStr = $partes[1];
            $horaStr = $partes[2];
            $empresa = $partes[3] ?? '';

            $this->_addDebugMessage("Linea TXT " . ($numLinea + 1) . ": Codigo: {$codigo}, FechaStr: {$fechaStr}, HoraStr: {$horaStr}");

            $empleado = $this->modelo->obtenerEmpleadoPorCodigo($codigo);

            $this->_addDebugMessage("Linea TXT " . ($numLinea + 1) . ": Empleado obtenido: " . print_r($empleado, true));

            if (!$empleado) {
                $this->agregarError("Linea " . ($numLinea + 1) . ": El empleado con codigo {$codigo} no existe.");
                continue;
            }

            $nombreEmpresaBD = $empleado['nombre_empresa'] ?? '';
            if ($nombreEmpresaBD && strtolower($empresa) !== strtolower($nombreEmpresaBD)) {
                $this->agregarError("Linea " . ($numLinea + 1) . ": El empleado {$codigo} pertenece a {$nombreEmpresaBD}, no a {$empresa}.");
                continue;
            }

            $fechaSQL = $this->convertirFecha($fechaStr);
            $horaSQL = $this->convertirHora($horaStr);

            if (!$fechaSQL || !$horaSQL) continue;

            $clave = $empleado['id_empleado'] . '_' . $fechaSQL;
            if (!isset($grupoPorEmpleadoFecha[$clave])) {
                $grupoPorEmpleadoFecha[$clave] = [
                    'id_empleado' => $empleado['id_empleado'],
                    'fecha' => $fechaSQL,
                    'horas' => []
                ];
            }

            $grupoPorEmpleadoFecha[$clave]['horas'][] = $horaSQL;
        }

        $importados = 0;

        foreach ($grupoPorEmpleadoFecha as $datos) {
            $idEmpleado = $datos['id_empleado'];
            $fecha = $datos['fecha'];
            $horas = $datos['horas'];

            $this->_addDebugMessage("Procesando grupo: Empleado ID: {$idEmpleado}, Fecha: {$fecha}, Horas: " . implode(', ', $horas));

            if (count($horas) !== 4) {
                $this->agregarError("Empleado ID {$idEmpleado} en fecha {$fecha}: Se requieren 4 marcajes, se encontraron " . count($horas) . ".");
                continue;
            }

            sort($horas);

            $hLlegada = $horas[0];
            $hLlegadaAlmuerzo = $horas[1];
            $hSalidaAlmuerzo = $horas[2];
            $hSalida = $horas[3];

            $existe = $this->modelo->obtenerAsistenciaPorEmpleadoYFecha($idEmpleado, $fecha);
            $this->_addDebugMessage("obtenerAsistenciaPorEmpleadoYFecha para ID {$idEmpleado} y Fecha {$fecha}: " . print_r($existe, true));

            if ($existe) {
                    $this->_addDebugMessage("Llamando a actualizarAsistencia para ID {$idEmpleado} y Fecha {$fecha}");
                    $resultadoActualizacion = $this->modelo->actualizarAsistencia(
                        $existe['id_asistencia'],
                        $hLlegada,
                        $hLlegadaAlmuerzo,
                        $hSalidaAlmuerzo,
                        $hSalida
                    );
                    $this->_addDebugMessage("Resultado de actualizarAsistencia: " . print_r($resultadoActualizacion, true));
                    if ($resultadoActualizacion === true) {
                        $importados++;
                    } else {
                        $this->_addDebugMessage("Error de actualizacion para Empleado ID {$idEmpleado} en fecha {$fecha}: " . $resultadoActualizacion);
                        $this->agregarError("Empleado ID {$idEmpleado} en fecha {$fecha}: " . $resultadoActualizacion);
                    }
                } else {
                    $this->_addDebugMessage("Llamando a crearAsistencia para ID {$idEmpleado} y Fecha {$fecha}");
                    $resultadoCreacion = $this->modelo->crearAsistencia(
                        $idEmpleado,
                        $fecha,
                        $hLlegada,
                        $hLlegadaAlmuerzo,
                        $hSalidaAlmuerzo,
                        $hSalida
                    );
                    $this->_addDebugMessage("Resultado de crearAsistencia: " . print_r($resultadoCreacion, true));
                    if (is_int($resultadoCreacion) && $resultadoCreacion > 0) {
                        $importados++;
                    } else {
                        $this->_addDebugMessage("Error de creacion para Empleado ID {$idEmpleado} en fecha {$fecha}: " . $resultadoCreacion);
                        $this->agregarError("Empleado ID {$idEmpleado} en fecha {$fecha}: " . $resultadoCreacion);
                    }
            }
        }

        return [
            'total_registros' => $totalRegistros,
            'importados_correctamente' => $importados,
            'errores' => $this->obtenerErrores(),
            'debug_messages' => $this->debugMessages
        ];
    }

    // ========================================================================
    // OBTENER DATOS
    // ========================================================================

    public function obtenerTiposIncidencia(): array
    {
        return $this->modelo->obtenerTiposIncidencia();
    }

    public function crearTipoIncidencia(string $nombre, ?string $descripcion = null, bool $esDescontable = true): int
    {
        $this->limpiarErrores();

        if (empty(trim($nombre))) {
            $this->agregarError('El nombre de la incidencia es obligatorio.');
            return 0;
        }

        return $this->modelo->crearTipoIncidencia($nombre, $descripcion, $esDescontable);
    }

    public function actualizarTipoIncidencia(
        int $idTipoIncidencia,
        string $nombre,
        ?string $descripcion = null,
        bool $esDescontable = true
    ): bool {
        $this->limpiarErrores();

        if (empty(trim($nombre))) {
            $this->agregarError('El nombre de la incidencia es obligatorio.');
            return false;
        }

        if ($idTipoIncidencia <= 0) {
            $this->agregarError('ID de tipo de incidencia inválido.');
            return false;
        }

        return $this->modelo->actualizarTipoIncidencia($idTipoIncidencia, $nombre, $descripcion, $esDescontable);
    }

    public function obtenerAsistencias(string $fechaInicio, string $fechaFin): array
    {
        return $this->modelo->obtenerAsistenciasPorRangoFechas($fechaInicio, $fechaFin);
    }

    public function obtenerEmpleadosActivos(): array
    {
        return $this->modelo->obtenerEmpleadosActivos();
    }

    // ========================================================================
    // DETECCIÓN DE INASISTENCIAS
    // ========================================================================

    public function detectarInasistencias(string $fechaInicio, string $fechaFin): array
    {
        error_log('detectarInasistencias - Fecha inicio: ' . $fechaInicio);
        error_log('detectarInasistencias - Fecha fin: ' . $fechaFin);

        $empleados = $this->modelo->obtenerEmpleadosActivos();
        $asistencias = $this->modelo->obtenerAsistenciasPorRangoFechas($fechaInicio, $fechaFin);
        $incidencias = $this->modelo->obtenerIncidenciasPorRangoFechas($fechaInicio, $fechaFin);

        $asistenciasPorEmpleadoFecha = [];
        foreach ($asistencias as $a) {
            $clave = $a['id_empleado'] . '_' . $a['fecha'];
            $asistenciasPorEmpleadoFecha[$clave] = true;
        }

        $incidenciasPorEmpleadoFecha = [];
        foreach ($incidencias as $i) {
            $clave = $i['id_empleado'] . '_' . $i['fecha'];
            $incidenciasPorEmpleadoFecha[$clave] = $i;
        }

        $inasistencias = [];

        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        
        $intervalo = new DateInterval('P1D');
        $periodo = new DatePeriod($inicio, $intervalo, $fin->modify('+1 day'));

        foreach ($empleados as $empleado) {
            foreach ($periodo as $fecha) {
                $fechaStr = $fecha->format('Y-m-d');
                $clave = $empleado['id_empleado'] . '_' . $fechaStr;

                if (!isset($asistenciasPorEmpleadoFecha[$clave])) {
                    $tieneIncidencia = isset($incidenciasPorEmpleadoFecha[$clave]);
                    $inasistencias[] = [
                        'id_empleado' => $empleado['id_empleado'],
                        'codigo_empleado' => $empleado['codigo_empleado'],
                        'nombre' => $empleado['nombre'],
                        'apellido' => $empleado['apellido'],
                        'nombre_empresa' => $empleado['nombre_empresa'],
                        'fecha' => $fechaStr,
                        'tiene_incidencia' => $tieneIncidencia,
                        'incidencia' => $tieneIncidencia ? $incidenciasPorEmpleadoFecha[$clave] : null
                    ];
                }
            }
        }

        error_log('detectarInasistencias - Cantidad de inasistencias detectadas: ' . count($inasistencias));
        return $inasistencias;
    }

    // ========================================================================
    // GESTIÓN DE INCIDENCIAS (ASIGNACIÓN)
    // ========================================================================

    public function crearIncidencia(
        int $idEmpleado,
        int $idTipoIncidencia,
        string $fecha,
        ?string $hora = null,
        ?string $observaciones = null,
        ?int $idUsuarioRegistra = null
    ): int {
        $this->limpiarErrores();

        if ($idEmpleado <= 0) {
            $this->agregarError('El empleado es obligatorio.');
            return 0;
        }

        if ($idTipoIncidencia <= 0) {
            $this->agregarError('El tipo de incidencia es obligatorio.');
            return 0;
        }

        if (empty($fecha)) {
            $this->agregarError('La fecha es obligatoria.');
            return 0;
        }

        return $this->modelo->crearIncidencia(
            $idEmpleado,
            $idTipoIncidencia,
            $fecha,
            $hora,
            $observaciones,
            $idUsuarioRegistra
        );
    }

    public function obtenerIncidencias(string $fechaInicio, string $fechaFin): array
    {
        return $this->modelo->obtenerIncidenciasPorRangoFechas($fechaInicio, $fechaFin);
    }

    public function eliminarIncidencia(int $idIncidencia): bool
    {
        return $this->modelo->eliminarIncidencia($idIncidencia);
    }

    public function obtenerEstadisticasAsistencias(?int $mes = null, ?int $anio = null): array
    {
        $mes = $mes ?? (int)date('n');
        $anio = $anio ?? (int)date('Y');
        $fechaInicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        $empleados = $this->modelo->obtenerEmpleadosConHorarioEntrada();
        $asistencias = $this->modelo->obtenerAsistenciasPorRangoFechas($fechaInicio, $fechaFin);
        $incidencias = $this->modelo->obtenerIncidenciasPorRangoFechas($fechaInicio, $fechaFin);

        $asistenciasPorClave = [];
        foreach ($asistencias as $a) {
            $asistenciasPorClave[$a['id_empleado'] . '_' . $a['fecha']] = $a;
        }

        $incidenciasPorClave = [];
        foreach ($incidencias as $i) {
            $incidenciasPorClave[$i['id_empleado'] . '_' . $i['fecha']] = true;
        }

        $nombresDia = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
        $mensual = [
            'asistencia' => 0,
            'inasistencia' => 0,
            'justificadas' => 0,
            'retardos' => 0,
        ];
        $porDiaSemana = [];
        foreach ($nombresDia as $nombreDia) {
            $porDiaSemana[$nombreDia] = [
                'asistencia' => 0,
                'inasistencia' => 0,
                'justificadas' => 0,
                'retardos' => 0,
            ];
        }

        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        $periodo = new DatePeriod($inicio, new DateInterval('P1D'), $fin->modify('+1 day'));

        foreach ($empleados as $empleado) {
            $hEntradaEsperada = (string)($empleado['h_entrada'] ?? '08:00');

            foreach ($periodo as $fecha) {
                $fechaStr = $fecha->format('Y-m-d');
                $indiceDia = (int)$fecha->format('N') - 1;
                $nombreDia = $nombresDia[$indiceDia];
                $clave = $empleado['id_empleado'] . '_' . $fechaStr;
                $categoria = 'inasistencia';

                if (isset($asistenciasPorClave[$clave])) {
                    $registro = $asistenciasPorClave[$clave];
                    $categoria = $this->clasificarAsistenciaDia($registro['h_llegada'] ?? null, $hEntradaEsperada);
                } elseif (isset($incidenciasPorClave[$clave])) {
                    $categoria = 'justificadas';
                }

                $mensual[$categoria]++;
                $porDiaSemana[$nombreDia][$categoria]++;
            }
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return [
            'periodo' => [
                'mes' => $mes,
                'anio' => $anio,
                'etiqueta' => ($meses[$mes] ?? (string)$mes) . ' ' . $anio,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ],
            'mensual' => $mensual,
            'por_dia_semana' => $porDiaSemana,
        ];
    }

    private function clasificarAsistenciaDia(?string $hLlegada, string $hEntradaEsperada): string
    {
        if ($hLlegada === null || trim($hLlegada) === '') {
            return 'asistencia';
        }

        $llegadaMin = $this->horaAMinutos($hLlegada);
        $entradaMin = $this->horaAMinutos($hEntradaEsperada);

        if ($llegadaMin === null || $entradaMin === null) {
            return 'asistencia';
        }

        return ($llegadaMin > $entradaMin + 5) ? 'retardos' : 'asistencia';
    }

    private function horaAMinutos(string $hora): ?int
    {
        $hora = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $coincidencias)) {
            return ((int)$coincidencias[1]) * 60 + (int)$coincidencias[2];
        }
        return null;
    }

    private function obtenerDatosReporte(string $fechaInicio, string $fechaFin): array
    {
        $inasistencias = $this->detectarInasistencias($fechaInicio, $fechaFin);

        $justificadas = 0;
        foreach ($inasistencias as $fila) {
            if (!empty($fila['tiene_incidencia'])) {
                $justificadas++;
            }
        }

        $total = count($inasistencias);

        return [
            'inasistencias' => $inasistencias,
            'resumen' => [
                'total' => $total,
                'justificadas' => $justificadas,
                'sin_justificar' => $total - $justificadas,
                'periodo_inicio' => $this->formatearFecha($fechaInicio),
                'periodo_fin' => $this->formatearFecha($fechaFin),
                'generado' => date('d/m/Y H:i:s'),
            ],
        ];
    }

    public function generarReporteTXT(string $fechaInicio, string $fechaFin): string
    {
        $datos = $this->obtenerDatosReporte($fechaInicio, $fechaFin);
        $inasistencias = $datos['inasistencias'];
        $resumen = $datos['resumen'];

        $ancho = 88;
        $linea = str_repeat('═', $ancho);

        $contenido = $linea . "\n";
        $contenido .= $this->centrarTexto('REPORTE DE INASISTENCIAS', $ancho) . "\n";
        $contenido .= $this->centrarTexto('Sistema de Control de Asistencia', $ancho) . "\n";
        $contenido .= $linea . "\n\n";

        $contenido .= '  Período        : ' . $resumen['periodo_inicio'] . ' — ' . $resumen['periodo_fin'] . "\n";
        $contenido .= '  Generado       : ' . $resumen['generado'] . "\n";
        $contenido .= '  Total registros: ' . $resumen['total'] . "\n";
        $contenido .= '  Justificadas   : ' . $resumen['justificadas'] . "\n";
        $contenido .= '  Sin justificar : ' . $resumen['sin_justificar'] . "\n\n";

        if (empty($inasistencias)) {
            $contenido .= "  No hay inasistencias registradas en este período.\n";
            return "\xEF\xBB\xBF" . $contenido;
        }

        $contenido .= '┌────────┬──────────────────────────────┬────────────────────┬────────────┬────────────┬──────────────────────┐' . "\n";
        $contenido .= '│ CÓDIGO │ EMPLEADO                     │ EMPRESA            │ FECHA      │ JUSTIFIC.  │ TIPO INCIDENCIA      │' . "\n";
        $contenido .= '├────────┼──────────────────────────────┼────────────────────┼────────────┼────────────┼──────────────────────┤' . "\n";

        foreach ($inasistencias as $i) {
            $nombreCompleto = trim(($i['nombre'] ?? '') . ' ' . ($i['apellido'] ?? ''));
            $empresa = (string)($i['nombre_empresa'] ?? '—');
            $justificada = !empty($i['tiene_incidencia']) ? 'SÍ' : 'NO';
            $tipo = $i['incidencia']['nombre_tipo'] ?? '—';

            $contenido .= '│ ' . $this->ajustarTexto((string)$i['codigo_empleado'], 6) . ' │ '
                . $this->ajustarTexto($nombreCompleto, 28) . ' │ '
                . $this->ajustarTexto($empresa, 18) . ' │ '
                . $this->ajustarTexto($this->formatearFecha($i['fecha']), 10) . ' │ '
                . $this->ajustarTexto($justificada, 10) . ' │ '
                . $this->ajustarTexto($tipo, 20) . ' │' . "\n";
        }

        $contenido .= '└────────┴──────────────────────────────┴────────────────────┴────────────┴────────────┴──────────────────────┘' . "\n";

        return "\xEF\xBB\xBF" . $contenido;
    }

    public function generarReporteExcel(string $fechaInicio, string $fechaFin): string
    {
        $datos = $this->obtenerDatosReporte($fechaInicio, $fechaFin);
        $inasistencias = $datos['inasistencias'];
        $resumen = $datos['resumen'];

        $filas = '';
        $indice = 0;

        foreach ($inasistencias as $i) {
            $nombreCompleto = trim(($i['nombre'] ?? '') . ' ' . ($i['apellido'] ?? ''));
            $justificada = !empty($i['tiene_incidencia']) ? 'Sí' : 'No';
            $tieneIncidencia = !empty($i['tiene_incidencia']) ? 'Sí' : 'No';
            $tipo = $i['incidencia']['nombre_tipo'] ?? '';
            $descripcion = $i['incidencia']['observaciones'] ?? '';
            $estiloFila = ($indice % 2 === 0) ? 'FilaPar' : 'FilaImpar';
            $estiloJust = !empty($i['tiene_incidencia']) ? 'JustificadoSi' : 'JustificadoNo';

            $filas .= '<Row ss:StyleID="' . $estiloFila . '">'
                . $this->celdaExcel((string)$i['codigo_empleado'])
                . $this->celdaExcel($nombreCompleto)
                . $this->celdaExcel((string)($i['nombre_empresa'] ?? ''))
                . $this->celdaExcel($this->formatearFecha($i['fecha']))
                . $this->celdaExcel($tieneIncidencia)
                . $this->celdaExcel($justificada, $estiloJust)
                . $this->celdaExcel($tipo)
                . $this->celdaExcel($descripcion)
                . '</Row>';
            $indice++;
        }

        if ($filas === '') {
            $filas = '<Row><Cell ss:MergeAcross="7"><Data ss:Type="String">No hay inasistencias en este período.</Data></Cell></Row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<?mso-application progid="Excel.Sheet"?>'
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            . '<Styles>'
            . '<Style ss:ID="Titulo"><Font ss:Bold="1" ss:Size="16" ss:Color="#1F3A5F"/>'
            . '<Alignment ss:Horizontal="Center"/></Style>'
            . '<Style ss:ID="Subtitulo"><Font ss:Size="11" ss:Color="#5D6D7E"/>'
            . '<Alignment ss:Horizontal="Center"/></Style>'
            . '<Style ss:ID="Resumen"><Font ss:Bold="1" ss:Size="10" ss:Color="#2C3E50"/>'
            . '<Interior ss:Color="#EBF5FB" ss:Pattern="Solid"/></Style>'
            . '<Style ss:ID="Encabezado"><Font ss:Bold="1" ss:Color="#FFFFFF"/>'
            . '<Interior ss:Color="#2980B9" ss:Pattern="Solid"/>'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>'
            . '<Style ss:ID="FilaPar"><Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
            . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E8E8"/></Borders></Style>'
            . '<Style ss:ID="FilaImpar"><Interior ss:Color="#F4F6F7" ss:Pattern="Solid"/>'
            . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E5E8E8"/></Borders></Style>'
            . '<Style ss:ID="JustificadoSi"><Font ss:Bold="1" ss:Color="#1E8449"/>'
            . '<Alignment ss:Horizontal="Center"/></Style>'
            . '<Style ss:ID="JustificadoNo"><Font ss:Bold="1" ss:Color="#C0392B"/>'
            . '<Alignment ss:Horizontal="Center"/></Style>'
            . '</Styles>'
            . '<Worksheet ss:Name="Inasistencias">'
            . '<Table>'
            . '<Column ss:Width="70"/>'
            . '<Column ss:Width="180"/>'
            . '<Column ss:Width="140"/>'
            . '<Column ss:Width="90"/>'
            . '<Column ss:Width="90"/>'
            . '<Column ss:Width="90"/>'
            . '<Column ss:Width="130"/>'
            . '<Column ss:Width="200"/>'
            . '<Row><Cell ss:MergeAcross="7" ss:StyleID="Titulo"><Data ss:Type="String">REPORTE DE INASISTENCIAS</Data></Cell></Row>'
            . '<Row><Cell ss:MergeAcross="7" ss:StyleID="Subtitulo"><Data ss:Type="String">Período: '
            . $this->escaparXml((string)$resumen['periodo_inicio']) . ' — '
            . $this->escaparXml((string)$resumen['periodo_fin'])
            . '</Data></Cell></Row>'
            . '<Row><Cell ss:MergeAcross="7" ss:StyleID="Subtitulo"><Data ss:Type="String">Generado: '
            . $this->escaparXml((string)$resumen['generado'])
            . '</Data></Cell></Row>'
            . '<Row/>'
            . '<Row ss:StyleID="Resumen"><Cell><Data ss:Type="String">Total</Data></Cell>'
            . '<Cell><Data ss:Type="Number">' . (int)$resumen['total'] . '</Data></Cell></Row>'
            . '<Row ss:StyleID="Resumen"><Cell><Data ss:Type="String">Justificadas</Data></Cell>'
            . '<Cell><Data ss:Type="Number">' . (int)$resumen['justificadas'] . '</Data></Cell></Row>'
            . '<Row ss:StyleID="Resumen"><Cell><Data ss:Type="String">Sin justificar</Data></Cell>'
            . '<Cell><Data ss:Type="Number">' . (int)$resumen['sin_justificar'] . '</Data></Cell></Row>'
            . '<Row/>'
            . '<Row ss:StyleID="Encabezado">'
            . $this->celdaExcel('Código', 'Encabezado')
            . $this->celdaExcel('Empleado', 'Encabezado')
            . $this->celdaExcel('Empresa', 'Encabezado')
            . $this->celdaExcel('Fecha', 'Encabezado')
            . $this->celdaExcel('Tiene incidencia', 'Encabezado')
            . $this->celdaExcel('Justificada', 'Encabezado')
            . $this->celdaExcel('Tipo', 'Encabezado')
            . $this->celdaExcel('Descripción', 'Encabezado')
            . '</Row>'
            . $filas
            . '</Table></Worksheet></Workbook>';
    }

    /** @deprecated Use generarReporteExcel; kept for csv format alias */
    public function generarReporteCSV(string $fechaInicio, string $fechaFin): string
    {
        $datos = $this->obtenerDatosReporte($fechaInicio, $fechaFin);
        $inasistencias = $datos['inasistencias'];

        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, [
            'Codigo',
            'Empleado',
            'Empresa',
            'Fecha',
            'Tiene Incidencia',
            'Justificada',
            'Tipo Incidencia',
            'Descripcion',
        ], ';');

        foreach ($inasistencias as $i) {
            $nombreCompleto = trim(($i['nombre'] ?? '') . ' ' . ($i['apellido'] ?? ''));
            $justificada = !empty($i['tiene_incidencia']) ? 'Si' : 'No';
            $tieneIncidencia = !empty($i['tiene_incidencia']) ? 'Si' : 'No';
            $tipo = $i['incidencia']['nombre_tipo'] ?? '';
            $descripcion = $i['incidencia']['observaciones'] ?? '';

            fputcsv($salida, [
                $i['codigo_empleado'],
                $nombreCompleto,
                $i['nombre_empresa'] ?? '',
                $this->formatearFecha($i['fecha']),
                $tieneIncidencia,
                $justificada,
                $tipo,
                $descripcion,
            ], ';');
        }

        rewind($salida);
        return stream_get_contents($salida) ?: '';
    }

    public function generarReportePDF(string $fechaInicio, string $fechaFin): string
    {
        require_once __DIR__ . '/../Reportes/GeneradorPdfInasistencias.php';

        $datos = $this->obtenerDatosReporte($fechaInicio, $fechaFin);
        $filas = [];

        foreach ($datos['inasistencias'] as $fila) {
            $fila['fecha_fmt'] = $this->formatearFecha($fila['fecha']);
            $filas[] = $fila;
        }

        return (new GeneradorPdfInasistencias())->generar($datos['resumen'], $filas);
    }

    private function formatearFecha(string $fecha): string
    {
        $dt = new DateTime($fecha);
        return $dt->format('d/m/Y');
    }

    private function centrarTexto(string $texto, int $ancho): string
    {
        $longitud = mb_strlen($texto, 'UTF-8');
        if ($longitud >= $ancho) {
            return mb_substr($texto, 0, $ancho, 'UTF-8');
        }
        $relleno = (int)floor(($ancho - $longitud) / 2);
        return str_repeat(' ', $relleno) . $texto;
    }

    private function ajustarTexto(string $texto, int $ancho): string
    {
        $texto = mb_substr($texto, 0, $ancho, 'UTF-8');
        return str_pad($texto, $ancho, ' ', STR_PAD_RIGHT);
    }

    private function truncarTexto(string $texto, int $maximo): string
    {
        if (strlen($texto) <= $maximo) {
            return $texto;
        }
        return substr($texto, 0, $maximo - 3) . '...';
    }

    private function escaparXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function celdaExcel(string $valor, string $estilo = ''): string
    {
        $atributoEstilo = $estilo !== '' ? ' ss:StyleID="' . $estilo . '"' : '';
        return '<Cell' . $atributoEstilo . '><Data ss:Type="String">'
            . $this->escaparXml($valor)
            . '</Data></Cell>';
    }
}
