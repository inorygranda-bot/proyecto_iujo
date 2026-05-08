<?php
class ReporteModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function guardarIncidencia($datos) {
        $sql = "INSERT INTO incidencias (nombre, descuenta, horas_justificadas) 
                VALUES (:nombre, :descuenta, :horas)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'nombre'    => $datos['nombre'],
            'descuenta' => $datos['descuenta'],
            'horas'     => $datos['horasJustificadas']
        ]);
    }

    public function listarIncidencias() {
        $sql = "SELECT * FROM incidencias ORDER BY id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}