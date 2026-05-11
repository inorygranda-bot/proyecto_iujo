<?php
declare(strict_types=1);

session_start();

if (!empty($_SESSION['usuario'])) {
    header('Location: ../../index.php');
    exit();
}

require_once __DIR__ . '/../../Vista/autenticacion/inicioSesion.php';