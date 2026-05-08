<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Gestión</title>
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="assets/js/index.js"></script>
    
    <?php
    if ($p === 'registro') echo '<link rel="stylesheet" href="./Registro/Registro.css">';
    if ($p === 'consulta') {
        echo '<link rel="stylesheet" href="./Consulta/Consulta.css">';
        echo '<link rel="stylesheet" href="./Calendario/Calendario.css">';
    }
    if ($p === 'horarios') echo '<link rel="stylesheet" href="./Calendario/Calendario.css">';
    if ($p === 'gestion') echo '<link rel="stylesheet" href="./GestionesDeUsuario/GestionesdeUsuario.css">';
    if ($p === 'reportes') echo '<link rel="stylesheet" href="./Reportes/Reportes.css">';
    if ($p === 'auditorias') echo '<link rel="stylesheet" href="./Auditorias/Auditorias.css">';
    ?>

</head>
<body>
    <aside class="Sidebar">
        <nav class="SidebarMenu">
        <ul>
            <li><a href="index.php?p=inicio" class="<?php echo ($p==='inicio'?'active':''); ?>"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="index.php?p=registro" id="EnlaceResgistroUI" class="<?php echo ($p==='registro'?'active':''); ?>"><i class="fas fa-user-plus"></i> Registro</a></li>
            <li><a href="index.php?p=consulta" class="<?php echo ($p==='consulta'?'active':''); ?>"><i class="fas fa-search"></i> Consultas</a></li>
            <li><a href="index.php?p=horarios" class="<?php echo ($p==='horarios'?'active':''); ?>"><i class="fas fa-calendar-alt"></i> Horarios</a></li>
            <li><a href="index.php?p=reportes" class="<?php echo ($p==='reportes'?'active':''); ?>"><i class="fas fa-chart-bar"></i> Reportes</a></li>
            <li><a href="index.php?p=gestion" id="EnlaceGestionUI" style="display: none;" class="<?php echo ($p==='gestion'?'active':''); ?>"><i class="fas fa-user-shield"></i> Gestión</a></li>
            <li><a href="index.php?p=auditorias" class="<?php echo ($p==='auditorias'?'active':''); ?>"><i class="fas fa-clipboard-list"></i> Auditorías</a></li>
        </ul>
    </nav>
    </aside>

    <main class="MainContent">
        <section class="ContentArea">
        <?php
    $modulos_permitidos = ['inicio', 'registro', 'consulta', 'horarios', 'reportes', 'gestion', 'auditorias'];

    if (!in_array($pagina, $modulos_permitidos)) $pagina = 'inicio';
    
        switch ($p) {
            case 'inicio':
                echo '
                <article class="WelcomeBox">
                    <i class="fas fa-chart-line"></i>
                    <h2>Panel de Control</h2>
                    <p>Seleccione una opción del menú lateral para comenzar.<br>
                    Gestione empresas, personal y reportes desde una sola interfaz.</p>
                </article>';
                break;
            case 'registro': include './Registro/Registro.php'; break;
            case 'horarios': include './Calendario/Calendario.php'; break;
            case 'consulta':
                include './Calendario/Calendario.php';
                include './Consulta/Consulta.php';
                break;
            case 'gestion': include './GestionesDeUsuario/GestionesdeUsuario.php'; break;
            case 'reportes': include './Reportes/Reportes.php'; break;
            case 'auditorias': include './Auditorias/Auditorias.php'; break;
            default: echo "<h2>Módulo no encontrado</h2>";
        }
        ?>
    </section>
    </main>
</body>
</html>