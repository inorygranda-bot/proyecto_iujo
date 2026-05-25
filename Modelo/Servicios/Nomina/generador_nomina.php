<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../Infraestructura/conexionBD.php';
require_once __DIR__ . '/GeneradorPdfNomina.php'; // Incluimos la nueva clase para generar PDF

$formato = $_GET['formato'] ?? 'txt';

try {
    $conexion = obtenerConexionPdo();
    $stmt = $conexion->query("SELECT e.nombre as nombre_empleado, e.apellido, e.codigo_empleado, d.nombre_departamento, emp.nombre as nombre_empresa
                              FROM Empleados e
                              LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
                              LEFT JOIN Empresa emp ON d.id_empresa = emp.id_empresa");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($formato === 'pdf') {
        $generadorPdf = new GeneradorPdfNomina();
        $pdfContent = $generadorPdf->generar($empleados);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="nomina.pdf"');
        echo $pdfContent;
    } elseif ($formato === 'excel') {
        // Generar CSV simple como Excel
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nomina.csv"');
        echo "Nombre,Apellido,Codigo,Departamento,Empresa\n";
        foreach ($empleados as $emp) {
            echo $emp['nombre_empleado'] . "," . $emp['apellido'] . "," . $emp['codigo_empleado'] . "," . ($emp['nombre_departamento'] ?? 'N/A') . "," . ($emp['nombre_empresa'] ?? 'N/A') . "\n";
        }
    } else {
        // Generar TXT
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="nomina.txt"');
        echo "Nomina de Empleados\n";
        echo "==================\n\n";
        foreach ($empleados as $emp) {
            echo "Nombre: " . $emp['nombre_empleado'] . " " . $emp['apellido'] . "\n";
            echo "Codigo: " . $emp['codigo_empleado'] . "\n";
            echo "Departamento: " . ($emp['nombre_departamento'] ?? 'N/A') . "\n";
            echo "Empresa: " . ($emp['nombre_empresa'] ?? 'N/A') . "\n";
            echo "------------------------\n";
        }
    }
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error al generar nomina: " . $e->getMessage();
}
?>