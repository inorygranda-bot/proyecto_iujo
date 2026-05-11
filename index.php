<?php
declare(strict_types=1);

// __DIR__ Es una constante de PHP que indica la ruta absoluta de la carpeta donde se encuentra el archivo actual. 
// Esto asegura que PHP no se pierda al buscar el archivo, sin importar desde dónde se ejecute.

// Busca el archivo donde está definida la clase AppControlador.

require_once __DIR__ . '/Controlador/Sistema/AppControlador.php';

// Aquí se crea un objeto de la clase AppControlador.

//Probablemente, el controlador usa esta ruta para saber dónde están las vistas, 

$controlador = new AppControlador(__DIR__);
$controlador->mostrarAplicacion();