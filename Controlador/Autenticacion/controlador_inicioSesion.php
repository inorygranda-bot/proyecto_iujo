<?php
declare(strict_types=1);

require_once __DIR__ . '/ControladorInicioSesion.php';

$controlador = new ControladorInicioSesion();
$controlador->manejarSolicitud();