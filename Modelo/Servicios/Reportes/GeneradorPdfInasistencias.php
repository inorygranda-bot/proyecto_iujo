<?php
declare(strict_types=1);

/**
 * Genera PDF de inasistencias sin librerias externas ni carpeta font.
 * Usa fuente Helvetica integrada en el estandar PDF.
 */
class GeneradorPdfInasistencias
{
    private const ANCHO = 842;
    private const ALTO = 595;

    /**
     * @param array<string, int|string> $resumen
     * @param array<int, array<string, mixed>> $filas
     */
    public function generar(array $resumen, array $filas): string
    {
        $lineas = [];
        $y = self::ALTO - 40;

        $lineas[] = $this->texto(220, $y, 'REPORTE DE INASISTENCIAS', 16, true);
        $y -= 22;
        $lineas[] = $this->texto(
            160,
            $y,
            'Periodo: ' . $resumen['periodo_inicio'] . ' - ' . $resumen['periodo_fin'],
            11,
            false
        );
        $y -= 16;
        $lineas[] = $this->texto(200, $y, 'Generado: ' . $resumen['generado'], 9, false);
        $y -= 24;

        $lineas[] = $this->texto(40, $y, 'Total: ' . $resumen['total'], 9, true);
        $lineas[] = $this->texto(200, $y, 'Justificadas: ' . $resumen['justificadas'], 9, true);
        $lineas[] = $this->texto(380, $y, 'Sin justificar: ' . $resumen['sin_justificar'], 9, true);
        $y -= 28;

        $columnas = [
            ['x' => 30, 'w' => 45, 'titulo' => 'Codigo'],
            ['x' => 80, 'w' => 150, 'titulo' => 'Empleado'],
            ['x' => 235, 'w' => 120, 'titulo' => 'Empresa'],
            ['x' => 360, 'w' => 65, 'titulo' => 'Fecha'],
            ['x' => 430, 'w' => 55, 'titulo' => 'Incid.'],
            ['x' => 490, 'w' => 60, 'titulo' => 'Justif.'],
            ['x' => 555, 'w' => 260, 'titulo' => 'Tipo'],
        ];

        foreach ($columnas as $col) {
            $lineas[] = $this->rect($col['x'], $y - 14, $col['w'], 16, true);
            $lineas[] = $this->texto($col['x'] + 3, $y - 11, $col['titulo'], 8, true);
        }
        $y -= 18;

        if ($filas === []) {
            $lineas[] = $this->rect(30, $y - 14, 785, 16, false);
            $lineas[] = $this->texto(300, $y - 11, 'No hay inasistencias en este periodo.', 9, false);
            return $this->ensamblarPdf(implode("\n", $lineas));
        }

        foreach ($filas as $fila) {
            if ($y < 50) {
                break;
            }

            $nombre = trim(($fila['nombre'] ?? '') . ' ' . ($fila['apellido'] ?? ''));
            $empresa = (string)($fila['nombre_empresa'] ?? '-');
            $tieneInc = !empty($fila['tiene_incidencia']) ? 'Si' : 'No';
            $tipo = $fila['incidencia']['nombre_tipo'] ?? '-';
            $fecha = $fila['fecha_fmt'] ?? '';

            $valores = [
                (string)($fila['codigo_empleado'] ?? ''),
                $this->truncar($nombre, 28),
                $this->truncar($empresa, 22),
                $fecha,
                $tieneInc,
                $tieneInc,
                $this->truncar($tipo, 38),
            ];

            foreach ($columnas as $i => $col) {
                $lineas[] = $this->rect($col['x'], $y - 14, $col['w'], 16, false);
                $lineas[] = $this->texto($col['x'] + 3, $y - 11, $valores[$i], 8, false);
            }
            $y -= 16;
        }

        return $this->ensamblarPdf(implode("\n", $lineas));
    }

    private function texto(float $x, float $y, string $texto, int $tamano, bool $negrita): string
    {
        $fuente = $negrita ? '/F2' : '/F1';
        $seguro = $this->escapar($texto);

        return "BT\n{$fuente} {$tamano} Tf\n{$x} {$y} Td\n({$seguro}) Tj\nET";
    }

    private function rect(float $x, float $y, float $w, float $h, bool $relleno): string
    {
        $op = $relleno ? 'f' : 'S';
        return "{$x} {$y} {$w} {$h} re\n{$op}";
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = preg_replace('/[^\x20-\x7E]/', '', $texto) ?? '';
        if (strlen($texto) <= $max) {
            return $texto;
        }
        return substr($texto, 0, $max - 3) . '...';
    }

    private function escapar(string $texto): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function ensamblarPdf(string $stream): string
    {
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
