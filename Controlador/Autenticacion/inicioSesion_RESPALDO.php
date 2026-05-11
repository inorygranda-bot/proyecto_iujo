<?php
session_start();

if (isset($_SESSION['usuario'])) {
    header("location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso al Sistema</title>
    <link rel="stylesheet" href="../../Recursos/css/inicioSesion.css?v=1">
</head>
<body>
    <header>
        <h1>INICIO DE SESIÓN</h1>
    </header>

    <?php 
    if (isset($_SESSION['error'])) {
        echo '<p style="color: red;">' . $_SESSION['error'] . '</p>';
        unset($_SESSION['error']);
    }
    ?>

    <form action="controlador_inicioSesion.php" method="POST">
        <label>Usuario:</label><br>
        <input type="text" name="usuario" required><br><br>
        
        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>
        
        <button type="submit">ENTRAR</button>
    </form>
</body>
</html>