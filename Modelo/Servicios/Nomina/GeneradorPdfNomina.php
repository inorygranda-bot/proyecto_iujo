<?php
declare(strict_types=1);

final class GeneradorPdfNomina
{
    private const ANCHO = 842; // A4 horizontal
    private const ALTO = 595; // A4 horizontal

    public function generar(array $empleados): string
    {
        $lineas = [];
        $y = self::ALTO - 40; // Posición inicial Y

        // Título del reporte
        $lineas[] = $this->texto(280, $y, 'NÓMINA DE EMPLEADOS', 16, true);
        $y -= 22;
        $lineas[] = $this->texto(300, $y, 'Fecha de Generación: ' . date('Y-m-d H:i:s'), 10, false);
        $y -= 30;

        // Encabezados de la tabla
        $columnas = [
            ['x' => 30, 'w' => 80, 'titulo' => 'Código'],
            ['x' => 115, 'w' => 120, 'titulo' => 'Nombre'],
            ['x' => 240, 'w' => 120, 'titulo' => 'Apellido'],
            ['x' => 365, 'w' => 150, 'titulo' => 'Departamento'],
            ['x' => 520, 'w' => 150, 'titulo' => 'Empresa'],
        ];

        foreach ($columnas as $col) {
            $lineas[] = $this->rect($col['x'], $y - 14, $col['w'], 16, true);
            $lineas[] = $this->texto($col['x'] + 3, $y - 11, $col['titulo'], 8, true);
        }
        $y -= 18;

        // Datos de los empleados
        if (empty($empleados)) {
            $lineas[] = $this->rect(30, $y - 14, 785, 16, false);
            $lineas[] = $this->texto(300, $y - 11, 'No hay empleados para mostrar en la nómina.', 9, false);
        } else {
            foreach ($empleados as $emp) {
                if ($y < 50) { // Si queda poco espacio, añadir una nueva página (lógica simplificada)
                    // En una implementación más robusta, se gestionaría la paginación aquí.
                    break;
                }

                $valores = [
                    (string)($emp['codigo_empleado'] ?? ''),
                    $this->truncar((string)($emp['nombre_empleado'] ?? ''), 18),
                    $this->truncar((string)($emp['apellido'] ?? ''), 18),
                    $this->truncar((string)($emp['nombre_departamento'] ?? 'N/A'), 25),
                    $this->truncar((string)($emp['nombre_empresa'] ?? 'N/A'), 25),
                ];

                foreach ($columnas as $i => $col) {
                    $lineas[] = $this->rect($col['x'], $y - 14, $col['w'], 16, false);
                    $lineas[] = $this->texto($col['x'] + 3, $y - 11, $valores[$i], 8, false);
                }
                $y -= 16;
            }
        }

        return $this->ensamblarPdf(implode("\n", $lineas));
    }

    private function texto(float $x, float $y, string $texto, int $tamano, bool $negrita): string
    {
        $fuente = $negrita ? '/F2' : '/F1'; // F1: Helvetica, F2: Helvetica-Bold
        $seguro = $this->escapar($texto);

        return "BT\n{$fuente} {$tamano} Tf\n{$x} {$y} Td\n({$seguro}) Tj\nET";
    }

    private function rect(float $x, float $y, float $w, float $h, bool $relleno): string
    {
        $op = $relleno ? 'f' : 'S'; // f: fill (rellenar), S: stroke (solo borde)
        return "{$x} {$y} {$w} {$h} re\n{$op}";
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = preg_replace('/[^\x20-\x7E]/', '', $texto) ?? ''; // Eliminar caracteres no ASCII
        if (strlen($texto) <= $max) {
            return $texto;
        }
        return substr($texto, 0, $max - 3) . '...';
    }

    private function escapar(string $texto): string
    {
        // Escapar caracteres especiales para PDF
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function ensamblarPdf(string $stream): string
    {
        // Estructura básica de un PDF, similar a GeneradorPdfInasistencias
        $objetos = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::ANCHO . ' ' . self::ALTO . '] '
                . '/Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >> endobj' . "\n",
            '4 0 obj << /Length ' . strlen($stream) . " >> stream\n{$stream}\nendstream endobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
            "6 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objetos as $objeto) {
            $offsets[] = strlen($pdf);
            $pdf .= $objeto;
        }

        $xrefPos = strlen($pdf);
        $xref = "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        return $pdf . $xref . "trailer << /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";
    }
}
