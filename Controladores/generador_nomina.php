<?php

declare(strict_types=1);

require_once __DIR__ . '/../Modelos/conexionBD.php';

$formato = $_GET['formato'] ?? 'txt';

try {
    $conexion = obtenerConexionPdo();

    $sql = "SELECT e.nombre as nombre_empleado, e.apellido, e.codigo_empleado, 
                   d.nombre_departamento, emp.nombre as nombre_empresa
            FROM Empleados e
            LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
            LEFT JOIN Empresa emp ON d.id_empresa = emp.id_empresa";
            
    $stmt = $conexion->query($sql);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($formato === 'excel') {

        enviarHeaders('text/csv', 'nomina_empleados.csv');
        
        echo "\xEF\xBB\xBF"; 
        
        $salida = fopen('php://output', 'w');
        fputcsv($salida, ['Nombre', 'Apellido', 'Código', 'Departamento', 'Empresa']);
        
        foreach ($empleados as $emp) {
            fputcsv($salida, [
                $emp['nombre_empleado'],
                $emp['apellido'],
                $emp['codigo_empleado'],
                $emp['nombre_departamento'] ?? 'N/A',
                $emp['nombre_empresa'] ?? 'N/A'
            ]);
        }
        fclose($salida);

    } elseif ($formato === 'pdf') {

        header('Content-Type: text/html; charset=utf-8');
        echo "<h3>Vista de Impresión (PDF en desarrollo)</h3>";
        echo "<table border='1'><tr><th>Empleado</th><th>Código</th></tr>";
        foreach ($empleados as $emp) {
            echo "<tr><td>{$emp['nombre_empleado']} {$emp['apellido']}</td><td>{$emp['codigo_empleado']}</td></tr>";
        }
        echo "</table><script>window.print();</script>";

    } else {

        enviarHeaders('text/plain', 'nomina_empleados.txt');
        
        echo "NOMINA DE EMPLEADOS - SISTEMA ASISTENCIA\n";
        echo "Fecha: " . date('d-m-Y H:i') . "\n";
        echo str_repeat("=", 40) . "\n\n";
        
        foreach ($empleados as $emp) {
            $nombreFull = str_pad($emp['nombre_empleado'] . " " . $emp['apellido'], 25);
            echo "E: {$nombreFull} | Cód: {$emp['codigo_empleado']}\n";
            echo "Dep: " . ($emp['nombre_departamento'] ?? 'N/A') . "\n";
            echo str_repeat("-", 40) . "\n";
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "Error crítico: " . $e->getMessage();
}

function enviarHeaders(string $tipo, string $nombreArchivo): void {
    header("Content-Type: $tipo; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}