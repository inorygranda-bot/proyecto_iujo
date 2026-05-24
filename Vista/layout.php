<?php

// Declara que se reciben estrictamente un tipo entero, si recibe un string da error vvs

declare(strict_types=1);

$p = 'inicio';
$sesionClienteJson = '{}';
$basePath = __DIR__ . '/..';

if (isset($viewData) && is_array($viewData)) {
    if (isset($viewData['modulo'])) {
        $p = (string)$viewData['modulo'];
    }
    if (isset($viewData['sesionClienteJson'])) {
        $sesionClienteJson = (string)$viewData['sesionClienteJson'];
    }
    if (isset($viewData['basePath'])) {
        $basePath = (string)$viewData['basePath'];
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestion</title>       

    <script type="application/json" id="__sesionPhp"><?php echo $sesionClienteJson; ?></script>
    <script src="./Recursos/js/aplicacion.js?v=1"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./Recursos/css/aplicacion.css?v=1">

<!-- Los if para los estilos (para no cargar todo si no es necesario) -->
    <?php
    if ($p === 'registro') echo '<link rel="stylesheet" href="./Recursos/css/modulos/Registro.css">';
    if ($p === 'consulta') {
        echo '<link rel="stylesheet" href="./Recursos/css/modulos/Consulta.css">';
        echo '<link rel="stylesheet" href="./Recursos/css/modulos/Calendario.css">';
    }
    if ($p === 'horarios') echo '<link rel="stylesheet" href="./Recursos/css/modulos/Calendario.css">';
    if ($p === 'gestion') echo '<link rel="stylesheet" href="./Recursos/css/modulos/GestionesDeUsuario.css">';
    if ($p === 'reportes') {
        echo '<link rel="stylesheet" href="./Recursos/css/modulos/Reportes.css">';
        echo '<link rel="stylesheet" href="./Recursos/css/modulos/GestionesDeUsuario.css">';
    }
    if ($p === 'inicio') {
        echo '<link rel="stylesheet" href="./Recursos/css/modulos/GestionesDeUsuario.css">';
        echo '<link rel="stylesheet" href="./Recursos/css/modulos/Inicio.css">';
    }
    if ($p === 'auditorias') echo '<link rel="stylesheet" href="./Recursos/css/modulos/Auditorias.css">';
    ?>

</head>

<body>

<aside class="Sidebar">
    <header class="SidebarLogo">
        <img src="./Recursos/img/logo_sistema.png" alt="Logo">
        <h2>SISTEMA</h2>
    </header>

    <nav class="SidebarMenu">
        <ul>
            <li><a href="index.php?p=inicio" class="<?php echo ($p === 'inicio' ? 'active' : ''); ?>"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="index.php?p=registro" id="EnlaceResgistroUI" class="<?php echo ($p === 'registro' ? 'active' : ''); ?>"><i class="fas fa-user-plus"></i> Registro</a></li>
            <li><a href="index.php?p=consulta" class="<?php echo ($p === 'consulta' ? 'active' : ''); ?>"><i class="fas fa-search"></i> Consultas</a></li>
            <li><a href="index.php?p=horarios" class="<?php echo ($p === 'horarios' ? 'active' : ''); ?>"><i class="fas fa-calendar-alt"></i> Horarios</a></li>
            <li><a href="index.php?p=reportes" class="<?php echo ($p === 'reportes' ? 'active' : ''); ?>"><i class="fas fa-chart-bar"></i> Reportes</a></li>
            <li><a href="index.php?p=gestion" id="EnlaceGestionUI" style="display:none;" class="<?php echo ($p === 'gestion' ? 'active' : ''); ?>"><i class="fas fa-user-shield"></i> Gestion</a></li>
            <li><a href="index.php?p=auditorias" class="<?php echo ($p === 'auditorias' ? 'active' : ''); ?>"><i class="fas fa-clipboard-list"></i> Auditorias</a></li>
        </ul>
    </nav>

    <footer class="SidebarFooter">
        <a href="#" onclick="cerrarSesion(event)"><i class="fas fa-sign-out-alt"></i> Cerrar Sesion</a>
    </footer>
</aside>

<main class="MainContent">
    <header class="TopHeader">
        <h1>Bienvenido al Sistema de Gestion</h1>
        <section class="UserProfile">
            <span id="MensajeRol">Cargando sesion...</span>
            <strong id="NombreUsuarioUI"></strong>
        </section>
    </header>
    <section class="ContentArea">
        <?php require $basePath . '/Vista/modulos/' . $p . '.php'; ?>
    </section>
</main>

<!-- lo mismo de los css pero para los js -->
<?php
if ($p === 'registro') echo '<script src="./Recursos/js/modulos/Registro.js"></script>';
if ($p === 'horarios' || $p === 'consulta') {
    echo '<script src="./Recursos/js/modulos/Calendario.js?v=2"></script>';
    if ($p === 'consulta') echo '<script src="./Recursos/js/modulos/Consulta.js?v=2"></script>';
}
if ($p === 'gestion') echo '<script src="./Recursos/js/modulos/GestionesDeUsuario.js"></script>';
if ($p === 'reportes') echo '<script src="./Recursos/js/modulos/Reportes.js"></script>';
if ($p === 'auditorias') echo '<script src="./Recursos/js/modulos/Auditorias.js"></script>';
if ($p === 'inicio') {
    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    echo '<script src="./Recursos/js/modulos/Inicio.js"></script>';
}
?>
</body>
</html>
