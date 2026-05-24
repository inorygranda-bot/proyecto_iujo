<?php
// ============================================================================
// API: incidencias/asistencias + delegación al API legado del resto del sistema
// ============================================================================

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accion = $_POST['accion'] ?? '';

$accionesIncidencias = [
    'importar_txt_asistencia',
    'obtener_tipos_incidencia',
    'crear_tipo_incidencia',
    'actualizar_tipo_incidencia',
    'obtener_asistencias',
    'detectar_inasistencias',
    'crear_incidencia',
    'obtener_incidencias',
    'eliminar_incidencia',
    'generar_reporte_incidencias',
    'obtener_estadisticas_asistencias',
];

$accionesAuditorias = [
    'obtener_auditorias',
    'registrar_auditoria',
];

if (in_array($accion, $accionesAuditorias, true)) {
    require_once __DIR__ . '/../../Modelo/Infraestructura/conexionBD.php';
    require_once __DIR__ . '/../../Modelo/Infraestructura/helpers_gestion_bd.php';
    require_once __DIR__ . '/../../Modelo/Servicios/Auditorias/AuditoriaModelo.php';
    require_once __DIR__ . '/../../Modelo/Servicios/Auditorias/AuditoriaServicio.php';

    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo = obtenerConexionPdo();
        migrarEsquemaAplicacionOpcional($pdo);
        $servicioAuditoria = new AuditoriaServicio($pdo);

        if ($accion === 'obtener_auditorias') {
            echo json_encode([
                'ok' => true,
                'mensaje' => 'Auditorias cargadas.',
                'data' => ['auditorias' => $servicioAuditoria->listar()],
            ]);
            exit;
        }

        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $accionTexto = trim((string)($_POST['accion_auditoria'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? $_POST['detalle'] ?? ''));

        if ($idUsuario > 0) {
            $ok = $servicioAuditoria->registrar($idUsuario, $accionTexto, $descripcion);
        } else {
            $login = trim((string)($_POST['usuario'] ?? ''));
            $ok = $login !== ''
                ? $servicioAuditoria->registrarPorLogin($login, $accionTexto, $descripcion)
                : false;
        }

        if ($ok) {
            echo json_encode(['ok' => true, 'mensaje' => 'Auditoria registrada.']);
        } else {
            echo json_encode([
                'ok' => false,
                'mensaje' => implode(' ', $servicioAuditoria->obtenerErrores()) ?: 'No se pudo registrar la auditoria.',
            ]);
        }
    } catch (Throwable $e) {
        error_log('Error auditorias API: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'mensaje' => 'Error al procesar auditoria.']);
    }
    exit;
}

if (!in_array($accion, $accionesIncidencias, true)) {
    require_once __DIR__ . '/../../Modelo/Servicios/Legado/gestion_api_legado.php';
    return;
}

require_once __DIR__ . '/../../Modelo/Infraestructura/conexionBD.php';
require_once __DIR__ . '/../../Modelo/Servicios/Incidencias/IncidenciaModelo.php';
require_once __DIR__ . '/../../Modelo/Servicios/Incidencias/IncidenciaServicio.php';

$pdo = obtenerConexionPdo();
$servicio = new IncidenciaServicio($pdo);

switch ($accion) {
    case 'importar_txt_asistencia':
        header('Content-Type: application/json');
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Error al subir el archivo.'
            ]);
            exit;
        }

        $rutaTemporal = $_FILES['archivo']['tmp_name'];
        $resultado = $servicio->importarTXT($rutaTemporal);

        echo json_encode([
            'ok' => true,
            'data' => $resultado
        ]);
        break;

    case 'obtener_tipos_incidencia':
        header('Content-Type: application/json');
        $tipos = $servicio->obtenerTiposIncidencia();
        echo json_encode([
            'ok' => true,
            'data' => [
                'tipos' => $tipos
            ]
        ]);
        break;

    case 'crear_tipo_incidencia':
        header('Content-Type: application/json');
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? null;
        $esDescontable = isset($_POST['es_descontable'])
            && ($_POST['es_descontable'] === 'true' || $_POST['es_descontable'] === '1');

        $idTipo = $servicio->crearTipoIncidencia($nombre, $descripcion, $esDescontable);
        $errores = $servicio->obtenerErrores();

        if ($idTipo > 0) {
            echo json_encode([
                'ok' => true,
                'mensaje' => 'Tipo de incidencia creado correctamente.',
                'data' => ['id_tipo_incidencia' => $idTipo]
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'mensaje' => implode(' ', $errores)
            ]);
        }
        break;

    case 'actualizar_tipo_incidencia':
        header('Content-Type: application/json');
        $idTipo = (int)($_POST['id_tipo_incidencia'] ?? 0);
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? null;
        $esDescontable = isset($_POST['es_descontable'])
            && ($_POST['es_descontable'] === 'true' || $_POST['es_descontable'] === '1');

        $resultado = $servicio->actualizarTipoIncidencia($idTipo, $nombre, $descripcion, $esDescontable);
        $errores = $servicio->obtenerErrores();

        if ($resultado) {
            echo json_encode([
                'ok' => true,
                'mensaje' => 'Tipo de incidencia actualizado correctamente.'
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'mensaje' => implode(' ', $errores)
            ]);
        }
        break;

    case 'obtener_asistencias':
        header('Content-Type: application/json');
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        if (empty($fechaInicio) || empty($fechaFin)) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Fechas obligatorias.'
            ]);
            exit;
        }

        $asistencias = $servicio->obtenerAsistencias($fechaInicio, $fechaFin);
        echo json_encode([
            'ok' => true,
            'data' => ['asistencias' => $asistencias]
        ]);
        break;

    case 'detectar_inasistencias':
        header('Content-Type: application/json');
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        if (empty($fechaInicio) || empty($fechaFin)) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Fechas obligatorias.'
            ]);
            exit;
        }

        $inasistencias = $servicio->detectarInasistencias($fechaInicio, $fechaFin);
        echo json_encode([
            'ok' => true,
            'data' => ['inasistencias' => $inasistencias]
        ]);
        break;

    case 'crear_incidencia':
        header('Content-Type: application/json');
        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $idTipoIncidencia = (int)($_POST['id_tipo_incidencia'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? null;
        $observaciones = $_POST['observaciones'] ?? null;

        $idIncidencia = $servicio->crearIncidencia($idEmpleado, $idTipoIncidencia, $fecha, $hora, $observaciones);
        $errores = $servicio->obtenerErrores();

        if ($idIncidencia > 0) {
            echo json_encode([
                'ok' => true,
                'mensaje' => 'Incidencia creada correctamente.',
                'data' => ['id_incidencia' => $idIncidencia]
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'mensaje' => implode(' ', $errores)
            ]);
        }
        break;

    case 'obtener_incidencias':
        header('Content-Type: application/json');
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        if (empty($fechaInicio) || empty($fechaFin)) {
            $hoy = date('Y-m-d');
            $haceUnMes = date('Y-m-d', strtotime('-1 month'));
            $fechaInicio = $haceUnMes;
            $fechaFin = $hoy;
        }

        $incidencias = $servicio->obtenerIncidencias($fechaInicio, $fechaFin);
        echo json_encode([
            'ok' => true,
            'data' => ['incidencias' => $incidencias]
        ]);
        break;

    case 'eliminar_incidencia':
        header('Content-Type: application/json');
        $idIncidencia = (int)($_POST['id_incidencia'] ?? 0);
        $resultado = $servicio->eliminarIncidencia($idIncidencia);

        echo json_encode([
            'ok' => $resultado,
            'mensaje' => $resultado ? 'Incidencia eliminada correctamente.' : 'Error al eliminar la incidencia.'
        ]);
        break;

    case 'obtener_estadisticas_asistencias':
        header('Content-Type: application/json');
        $mes = isset($_POST['mes']) ? (int)$_POST['mes'] : null;
        $anio = isset($_POST['anio']) ? (int)$_POST['anio'] : null;

        $estadisticas = $servicio->obtenerEstadisticasAsistencias($mes, $anio);
        echo json_encode([
            'ok' => true,
            'data' => $estadisticas
        ]);
        break;

    case 'generar_reporte_incidencias':
        $formato = $_POST['formato'] ?? 'txt';
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        if (empty($fechaInicio) || empty($fechaFin)) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Fechas obligatorias.'
            ]);
            exit;
        }

        $nombreArchivo = 'reporte_inasistencias_' . date('Ymd_His');

        if ($formato === 'txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.txt"');
            echo $servicio->generarReporteTXT($fechaInicio, $fechaFin);
        } elseif ($formato === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xls"');
            echo $servicio->generarReporteExcel($fechaInicio, $fechaFin);
        } elseif ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
            echo $servicio->generarReporteCSV($fechaInicio, $fechaFin);
        } elseif ($formato === 'pdf') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $contenidoPdf = $servicio->generarReportePDF($fechaInicio, $fechaFin);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.pdf"');
            header('Content-Length: ' . strlen($contenidoPdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $contenidoPdf;
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Formato invalido.'
            ]);
        }
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'mensaje' => 'Accion no valida.'
        ]);
        break;
}
