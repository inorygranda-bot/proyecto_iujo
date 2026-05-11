<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$apiKey = 'AIzaSyCCchbbPcQVRODZj4hZP86UyAhidKAOq6g';
$calendarId = 'es.ve#holiday@group.v.calendar.google.com';

$nivel = trim((string)($_GET['nivel'] ?? ''));
$idEntidad = trim((string)($_GET['id'] ?? ''));

$anoActual = (int) date('Y');
$timeMin = $anoActual . '-01-01T00:00:00Z';
$timeMax = ($anoActual + 1) . '-01-01T00:00:00Z';

$url = 'https://www.googleapis.com/calendar/v3/calendars/'
    . urlencode($calendarId)
    . '/events'
    . '?key=' . urlencode($apiKey)
    . '&timeMin=' . urlencode($timeMin)
    . '&timeMax=' . urlencode($timeMax)
    . '&singleEvents=true'
    . '&orderBy=startTime'
    . '&maxResults=2500';

if (!function_exists('curl_init')) {
    echo json_encode([
        'success' => false,
        'message' => 'El servidor no tiene habilitada la extensión cURL. Active php_curl en php.ini.',
    ]);
    exit;
}

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_CONNECTTIMEOUT => 10,

    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,

    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: SistemaGestionAsistencias/1.0',
    ],

    CURLOPT_FAILONERROR => false,

    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
]);

// Ejecutar petición
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorCurl = curl_error($ch);
curl_close($ch);

// MANEJO DE ERRORES 

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

$data = json_decode($response, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Calendar devolvió una respuesta no válida (no es JSON).',
    ]);
    exit;
}

if (isset($data['error'])) {
    $mensajeGoogle = $data['error']['message'] ?? 'Error desconocido de Google.';
    $codigoGoogle = $data['error']['code'] ?? 0;
    echo json_encode([
        'success' => false,
        'message' => 'Error de Google Calendar (' . $codigoGoogle . '): ' . $mensajeGoogle,
    ]);
    exit;
}

if (!isset($data['items']) || !is_array($data['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Google Calendar no devolvió eventos (posiblemente el calendario está vacío).',
    ]);
    exit;
}

$eventosFinales = [];

foreach ($data['items'] as $item) {
    $fechaRaw = '';
    if (isset($item['start']['date'])) {
        $fechaRaw = $item['start']['date'];
    } elseif (isset($item['start']['dateTime'])) {
        $fechaRaw = $item['start']['dateTime'];
    }

    if ($fechaRaw === '') {
        continue; 
    }

    // solo YYYY-MM-DD
    $fechaFormateada = substr($fechaRaw, 0, 10);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFormateada)) {
        continue;
    }

    $eventosFinales[] = [
        'fecha' => $fechaFormateada,
        'summary' => $item['summary'] ?? 'Sin nombre',
        'laboral' => false,
    ];
}

// Respuesta exitosa

echo json_encode([
    'success' => true,
    'eventos' => $eventosFinales,
    'total_eventos' => count($eventosFinales),
    'nivel_recibido' => $nivel,
    'id_entidad_recibido' => $idEntidad,
]);