<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Configuración de la API de Google Calendar
$apiKey = 'AIzaSyCCchbbPcQVRODZj4hZP86UyAhidKAOq6g';
$calendarId = 'es.ve#holiday@group.v.calendar.google.com'; // Calendario de feriados de Venezuela

// Obtener parámetros de la solicitud
$nivel = trim((string)($_GET['nivel'] ?? ''));     // Nivel (general, empresas, departamentos, empleados)
$idEntidad = trim((string)($_GET['id'] ?? ''));   // ID de la entidad

// Definir el rango de fechas para buscar feriados (año actual y siguiente)
$anoActual = (int) date('Y');
$timeMin = $anoActual . '-01-01T00:00:00Z';
$timeMax = ($anoActual + 1) . '-01-01T00:00:00Z';

// Construir la URL de la API de Google Calendar
$url = 'https://www.googleapis.com/calendar/v3/calendars/'
    . urlencode($calendarId)
    . '/events'
    . '?key=' . urlencode($apiKey)
    . '&timeMin=' . urlencode($timeMin)
    . '&timeMax=' . urlencode($timeMax)
    . '&singleEvents=true'
    . '&orderBy=startTime'
    . '&maxResults=2500';

// Verificar que la extensión cURL esté habilitada
if (!function_exists('curl_init')) {
    echo json_encode([
        'success' => false,
        'message' => 'El servidor no tiene habilitada la extensión cURL. Active php_curl en php.ini.',
    ]);
    exit;
}

// Inicializar la sesión cURL
$ch = curl_init();

// Configurar las opciones de cURL
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,    // Devolver la respuesta en lugar de imprimirla
    CURLOPT_TIMEOUT => 25,              // Tiempo máximo de espera para la respuesta
    CURLOPT_CONNECTTIMEOUT => 10,       // Tiempo máximo de espera para la conexión

    // Configuraciones SSL (desactivadas para facilitar la conexión en entornos de desarrollo)
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,

    // Cabeceras HTTP
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: SistemaGestionAsistencias/1.0',
    ],

    CURLOPT_FAILONERROR => false,       // No fallar automáticamente en errores HTTP
    CURLOPT_FOLLOWLOCATION => true,      // Seguir redirecciones
    CURLOPT_MAXREDIRS => 3,               // Máximo número de redirecciones
]);

// Ejecutar la petición a Google Calendar
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorCurl = curl_error($ch);
curl_close($ch);

// Error en la conexión cURL
if ($response === false || $response === '') {
    $mensajeError = $errorCurl !== ''
        ? 'Error de conexión cURL: ' . $errorCurl
        : 'No se recibió respuesta del servidor de Google Calendar.';
    echo json_encode([
        'success' => false,
        'message' => $mensajeError,
    ]);
    exit;
}

// Error en el código HTTP de respuesta
if ($httpCode !== 200) {
    $mensajeHttp = 'Google Calendar respondió con código HTTP ' . $httpCode . '. ';
    if ($httpCode === 403) {
        $mensajeHttp .= 'La API Key no tiene permiso o está restringida. Verifique en Google Cloud Console.';
    } elseif ($httpCode === 404) {
        $mensajeHttp .= 'Calendario no encontrado. Verifique el ID del calendario.';
    } else {
        $mensajeHttp .= 'Revise la conexión a internet y la API Key.';
    }
    echo json_encode([
        'success' => false,
        'message' => $mensajeHttp,
        'http_code' => $httpCode,
    ]);
    exit;
}

// Decodificar la respuesta JSON
$data = json_decode($response, true);

// Verificar que la respuesta sea un array válido
if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Calendar devolvió una respuesta no válida (no es JSON).',
    ]);
    exit;
}

// Verificar si hay un error en la respuesta de Google
if (isset($data['error'])) {
    $mensajeGoogle = $data['error']['message'] ?? 'Error desconocido de Google.';
    $codigoGoogle = $data['error']['code'] ?? 0;
    echo json_encode([
        'success' => false,
        'message' => 'Error de Google Calendar (' . $codigoGoogle . '): ' . $mensajeGoogle,
    ]);
    exit;
}

// Verificar que existan eventos en la respuesta
if (!isset($data['items']) || !is_array($data['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Calendar no devolvió eventos (posiblemente el calendario está vacío).',
    ]);
    exit;
}

$eventosFinales = [];

// Recorrer cada evento y formatearlo para el sistema
foreach ($data['items'] as $item) {
    $fechaRaw = '';
    // Obtener la fecha del evento (puede ser date o dateTime)
    if (isset($item['start']['date'])) {
        $fechaRaw = $item['start']['date'];
    } elseif (isset($item['start']['dateTime'])) {
        $fechaRaw = $item['start']['dateTime'];
    }

    // Saltar eventos sin fecha
    if ($fechaRaw === '') {
        continue; 
    }

    // Extraer solo la parte de fecha (YYYY-MM-DD)
    $fechaFormateada = substr($fechaRaw, 0, 10);

    // Validar que la fecha tenga el formato correcto
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFormateada)) {
        continue;
    }

    // Agregar el evento al array final
    $eventosFinales[] = [
        'fecha' => $fechaFormateada,
        'summary' => $item['summary'] ?? 'Sin nombre',  // Nombre del feriado
        'laboral' => false,                              // Los feriados no son laborales por defecto
    ];
}

echo json_encode([
    'success' => true,
    'eventos' => $eventosFinales,
    'total_eventos' => count($eventosFinales),
    'nivel_recibido' => $nivel,
    'id_entidad_recibido' => $idEntidad,
]);