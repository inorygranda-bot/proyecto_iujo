<?php
require_once __DIR__ . '/../Modelos/Reportes.php';

class ReportesController {
    private $modelo;

    public function __construct($pdo) {
        $this->modelo = new ReporteModel($pdo);
    }

    public function index() {
        $incidencias = $this->modelo->listarIncidencias();
        
        require_once __DIR__ . '/../Vistas/Reporte.php';
    }

    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resultado = $this->modelo->guardarIncidencia($_POST);
            echo json_encode(['success' => $resultado]);
            exit;
        }
    }
}