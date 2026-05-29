<?php
// ===================================================================================================================
// Descripción: Controlador principal para la API.
//              Maneja las solicitudes relacionadas con incidencias, asistencias y auditorías.
//              También delega solicitudes a una API legada para otras funcionalidades del sistema.
// ===================================================================================================================

declare(strict_types=1); // Forzar el uso de tipos estrictos para mejorar la calidad del código.

// Iniciar o reanudar la sesión PHP si aún no está activa.
// Esto es necesario para acceder a variables de sesión como el usuario logueado.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener la acción solicitada desde los datos POST.
// Si no se proporciona 'accion', se inicializa como una cadena vacía.
$accion = $_POST['accion'] ?? '';

// ===================================================================================================================
// Definición de acciones
// Se definen dos listas de acciones: una para incidencias/asistencias y otra para auditorías.
// Esto permite organizar y enrutar las solicitudes de la API de manera clara.
// ===================================================================================================================

// Acciones relacionadas con la gestión de incidencias y asistencias.
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

// Acciones relacionadas con la gestión de auditorías.
$accionesAuditorias = [
    'obtener_auditorias',
    'registrar_auditoria',
];

// ===================================================================================================================
// Manejo de acciones de Auditoría
// Este bloque procesa las solicitudes que corresponden a las acciones de auditoría.
// ===================================================================================================================
if (in_array($accion, $accionesAuditorias, true)) {
    // Incluir archivos necesarios para la conexión a la base de datos y los servicios de auditoría.
    require_once __DIR__ . '/../../Modelo/Infraestructura/conexionBD.php';
    require_once __DIR__ . '/../../Modelo/Infraestructura/helpers_gestion_bd.php';
    require_once __DIR__ . '/../../Modelo/Servicios/Auditorias/AuditoriaModelo.php';
    require_once __DIR__ . '/../../Modelo/Servicios/Auditorias/AuditoriaServicio.php';

    // Establecer la cabecera Content-Type para indicar que la respuesta será JSON.
    header('Content-Type: application/json; charset=utf-8');

    try {
        // Obtener una conexión PDO a la base de datos.
        $pdo = obtenerConexionPdo();
        // Asegurar que el esquema de la aplicación esté actualizado (ejecuta migraciones opcionales).
        migrarEsquemaAplicacionOpcional($pdo);
        // Instanciar el servicio de auditoría con la conexión PDO.
        $servicioAuditoria = new AuditoriaServicio($pdo);

        // Si la acción es 'obtener_auditorias', listar todos los registros de auditoría.
        if ($accion === 'obtener_auditorias') {
            echo json_encode([
                'ok' => true,
                'mensaje' => 'Auditorias cargadas.',
                'data' => ['auditorias' => $servicioAuditoria->listar()],
            ]);
            exit; // Terminar la ejecución después de responder.
        }

        // Para la acción 'registrar_auditoria':
        // Obtener el ID de usuario y la descripción de la auditoría desde los datos POST.
        $idUsuario = (int)($_POST['id_usuario'] ?? 0);
        $accionTexto = trim((string)($_POST['accion_auditoria'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? $_POST['detalle'] ?? ''));

        // Registrar la auditoría. Si se proporciona un id_usuario válido, usarlo.
        // De lo contrario, intentar registrar por login de usuario.
        if ($idUsuario > 0) {
            $ok = $servicioAuditoria->registrar($idUsuario, $accionTexto, $descripcion);
        } else {
            $login = trim((string)($_POST['usuario'] ?? ''));
            $ok = $login !== ''
                ? $servicioAuditoria->registrarPorLogin($login, $accionTexto, $descripcion)
                : false;
        }

        // Responder con el resultado del registro de la auditoría.
        if ($ok) {
            echo json_encode(['ok' => true, 'mensaje' => 'Auditoria registrada.']);
        } else {
            echo json_encode([
                'ok' => false,
                'mensaje' => implode(' ', $servicioAuditoria->obtenerErrores()) ?: 'No se pudo registrar la auditoria.',
            ]);
        }
    } catch (Throwable $e) {
        // Capturar cualquier excepción durante el procesamiento de auditorías.
        error_log('Error auditorias API: ' . $e->getMessage()); // Registrar el error en el log.
        echo json_encode(['ok' => false, 'mensaje' => 'Error al procesar auditoria.']);
    }
    exit; // Terminar la ejecución después de procesar la solicitud de auditoría.
}

// ===================================================================================================================
// Delegación a la API legada
// Si la acción solicitada NO es una acción de incidencias, se delega el control a un script de API legada.
// Esto permite mantener compatibilidad con funcionalidades antiguas o no migradas.
// ===================================================================================================================
if (!in_array($accion, $accionesIncidencias, true)) {
    require_once __DIR__ . '/../../Modelo/Servicios/Legado/gestion_api_legado.php';
    return; // Terminar la ejecución del script actual y ceder el control al script legado.
}

// ===================================================================================================================
// Manejo de acciones de Incidencias y Asistencias
// Este bloque procesa las solicitudes que corresponden a las acciones de incidencias y asistencias.
// ===================================================================================================================

// Incluir archivos necesarios para la conexión a la base de datos y los servicios de incidencias y auditoría.
require_once __DIR__ . '/../../Modelo/Infraestructura/conexionBD.php';
require_once __DIR__ . '/../../Modelo/Servicios/Incidencias/IncidenciaModelo.php';
require_once __DIR__ . '/../../Modelo/Servicios/Incidencias/IncidenciaServicio.php';
require_once __DIR__ . '/../../Modelo/Servicios/Auditorias/AuditoriaServicio.php'; // Se incluye para registrar acciones.

// Obtener una conexión PDO a la base de datos.
$pdo = obtenerConexionPdo();
// Instanciar el servicio de incidencias y el servicio de auditoría.
$servicio = new IncidenciaServicio($pdo);
$servicioAuditoria = new AuditoriaServicio($pdo);

// Estructura switch para manejar las diferentes acciones de incidencias/asistencias.
switch ($accion) {
    // Acción: importar_txt_asistencia
    // Permite importar datos de asistencia desde un archivo de texto.
    case 'importar_txt_asistencia':
        header('Content-Type: application/json');
        ini_set('display_errors', '1'); // TEMPORAL: Mostrar errores en pantalla
        error_reporting(E_ALL); // TEMPORAL: Reportar todos los errores

        try {
            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode([
                    'ok' => false,
                    'mensaje' => 'Error al subir el archivo.'
                ]);
                exit;
            }

            $rutaTemporal = $_FILES['archivo']['tmp_name'];
            $resultado = $servicio->importarTXT($rutaTemporal);

            $servicioAuditoria->registrarPorLogin(
                (string)($_SESSION['usuario'] ?? 'desconocido'),
                'Importó Asistencias TXT',
                'Se importaron ' . ($resultado['importados_correctamente'] ?? 0) . ' registros de asistencia.'
            );

            echo json_encode([
                    'ok' => true,
                    'data' => $resultado
                ]);
        } catch (Throwable $e) {
            error_log('Error fatal en importar_txt_asistencia: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Error interno del servidor al importar asistencias: ' . $e->getMessage(),
                'detalle' => $e->getFile() . ':' . $e->getLine()
            ]);
        }
        exit;

    // Acción: obtener_tipos_incidencia
    // Obtiene y devuelve todos los tipos de incidencia configurados en el sistema.
    case 'obtener_tipos_incidencia':
        header('Content-Type: application/json'); // La respuesta será JSON.
        $tipos = $servicio->obtenerTiposIncidencia(); // Obtener tipos de incidencia del servicio.
        echo json_encode([
                'ok' => true,
                'data' => [
                    'tipos' => $tipos
                ]
            ]);
            exit; // Terminar la ejecución.

    // Acción: crear_tipo_incidencia
    // Permite crear un nuevo tipo de incidencia.
    case 'crear_tipo_incidencia':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener y sanear los parámetros de entrada.
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? null;
        // Determinar si el tipo de incidencia es descontable.
        $esDescontable = isset($_POST['es_descontable'])
            && ($_POST['es_descontable'] === 'true' || $_POST['es_descontable'] === '1');

        // Intentar crear el tipo de incidencia.
        $idTipo = $servicio->crearTipoIncidencia($nombre, $descripcion, $esDescontable);
        $errores = $servicio->obtenerErrores(); // Obtener posibles errores del servicio.

        // Responder según el éxito o fracaso de la operación.
        if ($idTipo > 0) {
            // Registrar la acción de auditoría.
            $servicioAuditoria->registrarPorLogin(
                (string)($_SESSION['usuario'] ?? 'desconocido'),
                'Creó Tipo de Incidencia',
                "Tipo: {$nombre} (Descontable: " . ($esDescontable ? 'Sí' : 'No') . ")"
            );
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
            exit; // Terminar la ejecución.

    // Acción: actualizar_tipo_incidencia
    // Permite actualizar un tipo de incidencia existente.
    case 'actualizar_tipo_incidencia':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener y sanear los parámetros de entrada.
        $idTipo = (int)($_POST['id_tipo_incidencia'] ?? 0);
        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? null;
        // Determinar si el tipo de incidencia es descontable.
        $esDescontable = isset($_POST['es_descontable'])
            && ($_POST['es_descontable'] === 'true' || $_POST['es_descontable'] === '1');

        // Intentar actualizar el tipo de incidencia.
        $resultado = $servicio->actualizarTipoIncidencia($idTipo, $nombre, $descripcion, $esDescontable);
        $errores = $servicio->obtenerErrores(); // Obtener posibles errores del servicio.

        // Responder según el éxito o fracaso de la operación.
        if ($resultado) {
            // Registrar la acción de auditoría.
            $servicioAuditoria->registrarPorLogin(
                (string)($_SESSION['usuario'] ?? 'desconocido'),
                'Actualizó Tipo de Incidencia',
                "ID: {$idTipo}, Nombre: {$nombre} (Descontable: " . ($esDescontable ? 'Sí' : 'No') . ")"
            );
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
            exit; // Terminar la ejecución.

    // Acción: obtener_asistencias
    // Obtiene un rango de registros de asistencias.
    case 'obtener_asistencias':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener las fechas de inicio y fin.
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        // Validar que las fechas no estén vacías.
        if (empty($fechaInicio) || empty($fechaFin)) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Fechas obligatorias.'
            ]);
            exit; // Terminar la ejecución.
        }

        // Obtener las asistencias del servicio.
        $asistencias = $servicio->obtenerAsistencias($fechaInicio, $fechaFin);
        echo json_encode([
            'ok' => true,
            'data' => ['asistencias' => $asistencias]
        ]);
        exit; // Terminar la ejecución.

    // Acción: detectar_inasistencias
    // Detecta inasistencias dentro de un rango de fechas.
    case 'detectar_inasistencias':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener las fechas de inicio y fin.
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';  

        // Validar que las fechas no estén vacías.
        if (empty($fechaInicio) || empty($fechaFin)) {
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Fechas obligatorias.'
            ]);
            exit; // Terminar la ejecución.
        }

        // Detectar las inasistencias del servicio.
        $inasistencias = $servicio->detectarInasistencias($fechaInicio, $fechaFin);
        echo json_encode([
            'ok' => true,
            'data' => ['inasistencias' => $inasistencias]
        ]);
        exit; // Terminar la ejecución.

    // Acción: crear_incidencia
    // Permite crear una nueva incidencia para un empleado.
    case 'crear_incidencia':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener y sanear los parámetros de entrada.
        $idEmpleado = (int)($_POST['id_empleado'] ?? 0);
        $idTipoIncidencia = (int)($_POST['id_tipo_incidencia'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? null;
        $observaciones = $_POST['observaciones'] ?? null;

        // Intentar crear la incidencia.
        $idIncidencia = $servicio->crearIncidencia($idEmpleado, $idTipoIncidencia, $fecha, $hora, $observaciones);
        $errores = $servicio->obtenerErrores(); // Obtener posibles errores del servicio.

        // Responder según el éxito o fracaso de la operación.
        if ($idIncidencia > 0) {
            // Registrar la acción de auditoría.
            $servicioAuditoria->registrarPorLogin(
                (string)($_SESSION['usuario'] ?? 'desconocido'),
                'Creó Incidencia',
                "Incidencia ID: {$idIncidencia}, Empleado ID: {$idEmpleado}, Tipo: {$idTipoIncidencia}, Fecha: {$fecha}"
            );
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
            exit; // Terminar la ejecución.

    // Acción: obtener_incidencias
    // Obtiene un rango de incidencias registradas.
    case 'obtener_incidencias':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener las fechas de inicio y fin.
        $fechaInicio = $_POST['fech-inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        // Si las fechas están vacías, establecer un rango por defecto (último mes).
        if (empty($fechaInicio) || empty($fechaFin)) {
            $hoy = date('Y-m-d');
            $haceUnMes = date('Y-m-d', strtotime('-1 month'));
            $fechaInicio = $haceUnMes;
            $fechaFin = $hoy;
        }

        // Obtener las incidencias del servicio.
        $incidencias = $servicio->obtenerIncidencias($fechaInicio, $fechaFin);
        echo json_encode([
            'ok' => true,
            'data' => ['incidencias' => $incidencias]
        ]);
        exit; // Terminar la ejecución.

    // Acción: eliminar_incidencia
    // Permite eliminar una incidencia por su ID.
    case 'eliminar_incidencia':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener el ID de la incidencia.
        $idIncidencia = (int)($_POST['id_incidencia'] ?? 0);
        // Intentar eliminar la incidencia.
        $resultado = $servicio->eliminarIncidencia($idIncidencia);

        // Responder con el resultado de la operación.
        echo json_encode([
            'ok' => $resultado,
            'mensaje' => $resultado ? 'Incidencia eliminada correctamente.' : 'Error al eliminar la incidencia.'
        ]);
        if ($resultado) {
            // Registrar la acción de auditoría si la eliminación fue exitosa.
            $servicioAuditoria->registrarPorLogin(
                (string)($_SESSION['usuario'] ?? 'desconocido'),
                'Eliminó Incidencia',
                "Incidencia ID: {$idIncidencia}"
            );
        }
        exit; // Terminar la ejecución.

    // Acción: obtener_estadisticas_asistencias
    // Obtiene estadísticas de asistencias por mes y año.
    case 'obtener_estadisticas_asistencias':
        header('Content-Type: application/json'); // La respuesta será JSON.
        // Obtener el mes y el año (opcionales).
        $mes = isset($_POST['mes']) ? (int)$_POST['mes'] : null;
        $anio = isset($_POST['anio']) ? (int)$_POST['anio'] : null;

        // Obtener las estadísticas del servicio.
        $estadisticas = $servicio->obtenerEstadisticasAsistencias($mes, $anio);
        echo json_encode([
            'ok' => true,
            'data' => $estadisticas
        ]);
        exit; // Terminar la ejecución.

    // Acción: generar_reporte_incidencias
    // Genera un reporte de incidencias en diferentes formatos (TXT, Excel, CSV, PDF).
    case 'generar_reporte_incidencias':
        // Obtener el formato y las fechas del reporte.
        $formato = $_POST['formato'] ?? 'txt';
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin = $_POST['fecha_fin'] ?? '';

        // Validar que las fechas no estén vacías.
        if (empty($fechaInicio) || empty($fechaFin)) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Fechas obligatorias.'
            ]);
            exit; // Terminar la ejecución.
        }

        // Generar un nombre de archivo único para el reporte.
        $nombreArchivo = 'reporte_inasistencias_' . date('Ymd_His');

        // Procesar la generación del reporte según el formato solicitado.
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
            // Limpiar el buffer de salida antes de generar el PDF para evitar corrupción.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // Generar el contenido del PDF.
            $contenidoPdf = $servicio->generarReportePDF($fechaInicio, $fechaFin);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.pdf"');
            header('Content-Length: ' . strlen($contenidoPdf)); // Establecer la longitud del contenido.
            header('Cache-Control: private, max-age=0, must-revalidate'); // Control de caché.
            echo $contenidoPdf; // Enviar el contenido del PDF.
            exit; // Terminar la ejecución.
        } else {
            // Formato no válido.
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'mensaje' => 'Formato invalido.'
            ]);
            exit; // Terminar la ejecución.
        }
        // Registrar la acción de auditoría.
        $servicioAuditoria->registrarPorLogin(
            (string)($_SESSION['usuario'] ?? 'desconocido'),
            'Generó Reporte de Incidencias',
            "Formato: {$formato}, Fecha Inicio: {$fechaInicio}, Fecha Fin: {$fechaFin}"
        );
        break; // Salir del switch.

    // Acción por defecto: se ejecuta si la acción solicitada no coincide con ningún caso.
    default:
        // Registrar la acción de auditoría para acciones no válidas.
        $servicioAuditoria->registrarPorLogin(
            (string)($_SESSION['usuario'] ?? 'desconocido'),
            'Acción API No Válida',
            "Se intentó ejecutar la acción API no reconocida: {$accion}"
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'mensaje' => 'Accion no valida.'
        ]);
        exit; // Terminar la ejecución.
}
