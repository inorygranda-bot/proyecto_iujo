<?php
declare(strict_types=1);
session_start();

// 1. Cargamos primero los archivos que contienen las funciones de BD
require_once __DIR__ . '/datos/conexionBD.php';
require_once __DIR__ . '/datos/helpers_gestion_bd.php';

// 2. Cargamos el controlador
require_once __DIR__ . '/controllers/MainController.php';

// 3. Ejecutamos
$app = new MainController();
$app->index();