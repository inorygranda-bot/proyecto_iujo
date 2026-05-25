<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_POST['accion'] = 'obtener_datos_sistema';
session_start();
$_SESSION['usuario'] = 'daniel';
$_SESSION['rol'] = 'analista';
ob_start();
require 'Controlador/API/gestion_api.php';
$output = ob_get_clean();
echo $output;
