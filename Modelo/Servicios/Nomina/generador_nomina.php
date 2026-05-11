<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Infraestructura/conexionBD.php';

$formato = $_GET['formato'] ?? 'txt';

try {
    $conexion = obtenerConexionPdo();
    $stmt = $conexion->query("SELECT e.nombre as nombre_empleado, e.apellido, e.codigo_empleado, d.nombre_departamento, emp.nombre as nombre_empresa
                              FROM Empleados e
                              LEFT JOIN departamento d ON e.id_departamento = d.id_departamento
                              LEFT JOIN Empresa emp ON d.id_empresa = emp.id_empresa");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($formato === 'pdf') {
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="nomina.pdf"');
        echo "%PDF-1.4\n";
        echo "1 0 obj\n";
        echo "<<\n";
        echo "/Type /Catalog\n";
        echo "/Pages 2 0 R\n";
        echo ">>\n";
        echo "endobj\n";
        echo "2 0 obj\n";
        echo "<<\n";
        echo "/Type /Pages\n";
        echo "/Kids [3 0 R]\n";
        echo "/Count 1\n";
        echo ">>\n";
        echo "endobj\n";
        echo "3 0 obj\n";
        echo "<<\n";
        echo "/Type /Page\n";
        echo "/Parent 2 0 R\n";
        echo "/MediaBox [0 0 612 792]\n";
        echo "/Contents 4 0 R\n";
        echo "/Resources << /Font << /F1 5 0 R >> >>\n";
        echo ">>\n";
        echo "endobj\n";
        echo "4 0 obj\n";
        echo "<<\n";
        echo "/Length 200\n";
        echo ">>\n";
        echo "stream\n";
        echo "BT\n";
        echo "/F1 12 Tf\n";
        echo "50 750 Td\n";
        echo "(Nomina de Empleados) Tj\n";
        echo "ET\n";
        echo "endstream\n";
        echo "endobj\n";
        echo "5 0 obj\n";
        echo "<<\n";
        echo "/Type /Font\n";
        echo "/Subtype /Type1\n";
        echo "/BaseFont /Helvetica\n";
        echo ">>\n";
        echo "endobj\n";
        echo "xref\n";
        echo "0 6\n";
        echo "0000000000 65535 f \n";
        echo "0000000009 00000 n \n";
        echo "0000000058 00000 n \n";
        echo "0000000115 00000 n \n";
        echo "0000000274 00000 n \n";
        echo "0000000410 00000 n \n";
        echo "trailer\n";
        echo "<<\n";
        echo "/Size 6\n";
        echo "/Root 1 0 R\n";
        echo ">>\n";
        echo "startxref\n";
        echo "456\n";
        echo "%%EOF\n";
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