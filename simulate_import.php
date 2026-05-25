<?php

$_SERVER['REQUEST_METHOD'] = 'POST'; // Simula una solicitud POST
$_POST['accion'] = 'importar_txt_asistencia';
$_POST['es_texto_directo'] = 'true';
$_POST['contenido_archivo'] = file_get_contents('c:\\xampp\\htdocs\\Proyecto_IUJO\\test_asistencia.txt');

// Removido session_start() para evitar conflictos de encabezado en la simulación CLI.

require_once 'c:\\xampp\\htdocs\\Proyecto_IUJO\\Modelo\\Servicios\\Legado\\gestion_api_legado.php';

?>