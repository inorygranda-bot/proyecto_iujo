<?php
session_start(); // inicio la sesion para que el sistema me reconozca en todas las paginas

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // aqui agarro lo que el usuario escribio en el formulario de login
    $user = $_POST['usuario'];
    $pass = $_POST['password'];

    // este es el acceso manual para probar que el login funciona sin la base de datos q no ha hecho el Luis
    if ($user === "admin" && $pass === "admin123") {
        // si mete los datos de admin le doy permiso de administrador
        $_SESSION['usuario'] = "administrador";
        $_SESSION['rol'] = "admin";
        header("location: ../index.php"); // lo mando al inicio
        exit();
    } else if ($user === "analista" && $pass === "123") {
        // si mete estos datos entra como analista de una vez
        $_SESSION['usuario'] = "analista de prueba";
        $_SESSION['rol'] = "analista";
        header("location: ../index.php");
        exit();
    } else {
        // si se equivoca por bobo lo devuelve al login y le manda el error para el mensaje
        $_SESSION['error'] = "Credenciales incorrectas o acceso denegado.";
        header("location: inicioSesion.php");
        exit();
    }
}

// esto es para cuando le de al boton de salir
if (isset($_GET['salir'])) {
    session_destroy(); // borro la sesion para que nadie más entre
    header("location: inicioSesion.php"); // lo mando de vuelta al inicio de sesion
    exit();
}
?>