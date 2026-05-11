<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Procesador Biometrico - Interfaz PHP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f0f0f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            background: white;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        input, button, textarea {
            padding: 10px;
            margin: 5px;
            font-size: 16px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .advertencia {
            color: orange;
            font-weight: bold;
        }
        textarea {
            width: 100%;
            height: 150px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>PROCESADOR BIOMETRICO - INTERFAZ PHP</h1>
    
    <?php
    $rutaDefault = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Recursos' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'herramientas' . DIRECTORY_SEPARATOR . 'OMEGA.TXT';
    $carpetaReportesDefault = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Recursos' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'herramientas' . DIRECTORY_SEPARATOR . 'reportes';

    class ProcesadorBiometrico {
        public $registrosCrudos = [];
        public $listaAsistencia = [];
        public $listaErrores = [];
        public $listaAdvertencias = [];
        private $carpetaSalida;

        public function __construct($carpetaSalida = './reportes') {
            $this->carpetaSalida = $carpetaSalida;
        }

        public function cargarArchivo($rutaArchivo) {
            try {
                $this->limpiarDatos();
                $this->validarArchivo($rutaArchivo);

                $contenido = file_get_contents($rutaArchivo);
                $lineas = preg_split('/\r?\n/', trim($contenido));

                $this->procesarLineas($lineas);
                $this->generarListaAsistencia();
                $this->crearReporte($rutaArchivo);
                
                return true;
            } catch (Exception $e) {
                $this->agregarError('ERROR CRITICO: ' . $e->getMessage());
                return false;
            }
        }

        private function validarArchivo($rutaArchivo) {
            if (!$rutaArchivo || !is_string($rutaArchivo)) {
                throw new Exception('Ruta de archivo invalida');
            }

            if (!file_exists($rutaArchivo)) {
                throw new Exception('Archivo no encontrado: ' . $rutaArchivo);
            }

            if (!is_readable($rutaArchivo)) {
                throw new Exception('No se puede leer el archivo: ' . $rutaArchivo);
            }

            $extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));
            if ($extension !== 'txt') {
                throw new Exception('Solo se aceptan archivos TXT');
            }
        }

        private function procesarLineas($lineas) {
            foreach ($lineas as $i => $linea) {
                $linea = trim($linea);
                if (empty($linea)) continue;

                $partes = preg_split('/\s+/', $linea);
                if (count($partes) !== 5) {
                    $this->agregarError('Linea ' . ($i + 1) . ': Deben ser 5 campos', $linea);
                    continue;
                }

                [$idBiometrico, $idEmpleado, $fecha, $hora, $departamentoStr] = $partes;
                
                try {
                    $this->validarCamposNuevos($idBiometrico, $idEmpleado, $fecha, $hora, $departamentoStr, $i + 1);
                    
                    $registro = [
                        'idBiometrico' => trim($idBiometrico),
                        'idEmpleado' => trim($idEmpleado),
                        'fecha' => trim($fecha),
                        'hora' => trim($hora),
                        'departamento' => (int)$departamentoStr,
                        'numeroLinea' => $i + 1
                    ];
                    $this->registrosCrudos[] = $registro;
                } catch (Exception $e) {
                    $this->agregarError('Linea ' . ($i + 1) . ': ' . $e->getMessage(), $linea);
                }
            }
        }

        private function validarCamposNuevos($idBiometrico, $idEmpleado, $fecha, $hora, $departamentoStr, $numeroLinea) {
            if (!preg_match('/^\d+$/', $idBiometrico)) throw new Exception('ID biometrico numero');
            if (!preg_match('/^\d+$/', $idEmpleado)) throw new Exception('ID empleado numero');
            if (!preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $fecha)) throw new Exception('Fecha xx/xx/xx');
            if (!preg_match('/^\d{2}:\d{2}$/', $hora)) throw new Exception('Hora xx:xx');
            
            $departamento = (int)$departamentoStr;
            if ($departamento < 1 || $departamento > 100) throw new Exception('Departamento 1-100');
        }

        private function generarListaAsistencia() {
            $agrupados = [];
            foreach ($this->registrosCrudos as $registro) {
                $clave = $registro['idEmpleado'] . '-' . $registro['fecha'];
                $agrupados[$clave][] = $registro;
            }

            foreach ($agrupados as $clave => $registros) {
                [$idEmpleado, $fecha] = explode('-', $clave);
                $departamento = $registros[0]['departamento'];
                $asistencia = $this->analizarAsistenciaDiaria($registros, $idEmpleado, $fecha, $departamento);
                $this->listaAsistencia[] = $asistencia;
            }
        }

        private function analizarAsistenciaDiaria($registros, $idEmpleado, $fecha, $departamento) {
            usort($registros, function($a, $b) {
                return $this->horaAMinutos($a['hora']) - $this->horaAMinutos($b['hora']);
            });

            $entrada = null;
            $salida = null;
            $marcasExtras = [];

            if (count($registros) === 1) {
                $entrada = $registros[0]['hora'];
                $this->agregarAdvertencia("Empleado $idEmpleado fecha $fecha: Solo entrada");
            } elseif (count($registros) === 2) {
                $entrada = $registros[0]['hora'];
                $salida = $registros[1]['hora'];
            } else {
                $entrada = $registros[0]['hora'];
                $salida = end($registros)['hora'];
                $this->agregarAdvertencia("Empleado $idEmpleado: " . count($registros) . " marcas $fecha");
            }

            $estado = $this->calcularEstado($entrada, $salida);
            return [
                'idEmpleado' => $idEmpleado,
                'fecha' => $fecha,
                'departamento' => $departamento,
                'entrada' => $entrada,
                'salida' => $salida,
                'estado' => $estado
            ];
        }

        private function horaAMinutos($hora) {
            [$h, $m] = explode(':', $hora);
            return (int)$h * 60 + (int)$m;
        }

        private function calcularEstado($entrada, $salida) {
            if (!$entrada && !$salida) return 'SIN_MARCAS';
            if ($entrada && !$salida) return 'SOLO_ENTRADA';
            if (!$entrada && $salida) return 'SOLO_SALIDA';
            return 'COMPLETA';
        }

        private function crearReporte($rutaArchivo) {
            if (!is_dir($this->carpetaSalida)) {
                mkdir($this->carpetaSalida, 0777, true);
            }
            // Reporte simplificado para demo
        }

        public function limpiarDatos() {
            $this->registrosCrudos = [];
            $this->listaAsistencia = [];
            $this->listaErrores = [];
            $this->listaAdvertencias = [];
        }

        public function agregarError($mensaje, $linea = '') {
            $error = $mensaje . ($linea ? ' | Linea: "' . $linea . '"' : '');
            $this->listaErrores[] = $error;
        }

        public function agregarAdvertencia($mensaje) {
            $this->listaAdvertencias[] = $mensaje;
        }

        public function obtenerResumen() {
            return [
                'totalRegistros' => count($this->registrosCrudos),
                'asistenciasValidas' => count($this->listaAsistencia),
                'totalErrores' => count($this->listaErrores),
                'totalAdvertencias' => count($this->listaAdvertencias)
            ];
        }

        public function obtenerListaAsistencia() {
            return $this->listaAsistencia;
        }

        public function buscarEmpleado($idEmpleado) {
            return array_filter($this->listaAsistencia, function($r) use ($idEmpleado) {
                return $r['idEmpleado'] === $idEmpleado;
            });
        }

        public function obtenerErrores() {
            return $this->listaErrores;
        }

        public function obtenerAdvertencias() {
            return $this->listaAdvertencias;
        }
    }

    // PROCESAR FORMULARIO
    $procesador = null;
    $mensaje = '';
    $rutaArchivoInput = $_POST['rutaArchivo'] ?? $rutaDefault;
    $carpetaReportesInput = $_POST['carpetaReportes'] ?? $carpetaReportesDefault;
    $idEmpleadoInput = $_POST['idEmpleado'] ?? '';

    if (($_POST['accion'] ?? '') || ($_POST['buscar'] ?? '')) {
        $procesador = new ProcesadorBiometrico($carpetaReportesInput);
        $resultado = $procesador->cargarArchivo($rutaArchivoInput);

        if (!$resultado) {
            $mensaje = 'Error en procesamiento';
        } else {
            $mensaje = 'Archivo procesado correctamente';

            if ($_POST['buscar'] ?? '') {
                $resultados = $procesador->buscarEmpleado(trim($idEmpleadoInput));
                $procesador->listaAsistencia = $resultados; // Filtrar tabla
                $mensaje = 'Busqueda completada';
            }
        }
    }
    ?>

    <h2>1. CONFIGURACION</h2>
    <form method="POST">
    <table>
        <tr>
            <td>Ruta del archivo TXT:</td>
            <td><input type="text" name="rutaArchivo" value="<?php echo htmlspecialchars($rutaArchivoInput); ?>" size="50"></td>
        </tr>
        <tr>
            <td>Carpeta de reportes:</td>
            <td><input type="text" name="carpetaReportes" value="<?php echo htmlspecialchars($carpetaReportesInput); ?>"></td>
        </tr>
    </table>
    
    <button type="submit" name="accion" value="procesar">CARGAR Y PROCESAR TXT</button>
    <?php if ($procesador): ?>
        <button type="submit" name="buscar" value="buscar">BUSCAR EMPLEADO</button>
        ID: <input type="text" name="idEmpleado" size="10" value="<?php echo htmlspecialchars($idEmpleadoInput); ?>">
    <?php endif; ?>
    </form>
    
    <?php if ($mensaje): ?>
        <p style="color: green; font-weight: bold;"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    
    <?php if ($procesador): ?>
    <h2>2. RESUMEN GENERAL</h2>
    <table id="tablaResumen">
        <?php 
        $resumen = $procesador->obtenerResumen();
        echo "<tr><td>Total Registros:</td><td>{$resumen['totalRegistros']}</td></tr>";
        echo "<tr><td>Asistencias:</td><td>{$resumen['asistenciasValidas']}</td></tr>";
        echo "<tr><td>Errores:</td><td class='error'>{$resumen['totalErrores']}</td></tr>";
        echo "<tr><td>Advertencias:</td><td class='advertencia'>{$resumen['totalAdvertencias']}</td></tr>";
        ?>
    </table>
    
    <h2>3. LISTA DE ASISTENCIA</h2>
    <table>
        <tr>
            <th>EMP_ID</th>
            <th>FECHA</th>
            <th>DEPT</th>
            <th>ENTRADA</th>
            <th>SALIDA</th>
            <th>ESTADO</th>
        </tr>
        <?php 
        $lista = $procesador->obtenerListaAsistencia();
        if (empty($lista)):
        ?>
            <tr><td colspan="6">No hay datos</td></tr>
        <?php else: 
            foreach ($lista as $registro):
        ?>
            <tr>
                <td><?= htmlspecialchars($registro['idEmpleado']) ?></td>
                <td><?= htmlspecialchars($registro['fecha']) ?></td>
                <td><?= htmlspecialchars($registro['departamento']) ?></td>
                <td><?= htmlspecialchars($registro['entrada'] ?? '') ?></td>
                <td><?= htmlspecialchars($registro['salida'] ?? '') ?></td>
                <td><?= htmlspecialchars($registro['estado']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
    
    <h2>4. ERRORES Y ADVERTENCIAS</h2>
    <textarea readonly><?php
        $errores = $procesador->obtenerErrores();
        $advertencias = $procesador->obtenerAdvertencias();
        if (!empty($errores)) {
            echo "ERRORES:\n";
            foreach ($errores as $error) {
                echo "- $error\n";
            }
            echo "\n";
        }
        if (!empty($advertencias)) {
            echo "ADVERTENCIAS:\n";
            foreach ($advertencias as $adv) {
                echo "- $adv\n";
            }
        }
    ?></textarea>
    
    <h2>5. REGISTROS CRUDOS</h2>
    <textarea readonly><?= json_encode($procesador->registrosCrudos, JSON_PRETTY_PRINT) ?></textarea>
    <?php endif; ?>

</body>
</html>