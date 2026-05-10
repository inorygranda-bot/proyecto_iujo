<?php
require_once __DIR__ . '/../Modelos/conexionBD.php';
require_once __DIR__ . '/../Modelos/helpers_gestion_bd.php';
require_once __DIR__ . '/../Modelos/usuarios.php';

class MainController {
    
    public function index() {
        if (!isset($_SESSION['usuario'])) {
            header('Location: Login/login.php');
            exit();
        }

        try {
            $pdo = ConexionBaseDatos::obtenerConexionPDO();
        } catch (Throwable $e) {
            die("Error: No se pudo conectar a la base de datos. " . $e->getMessage());
        }

        $userModel = new Usuario($pdo);
        $usuarioLogin = (string)$_SESSION['usuario'];
        $rol = (string)($_SESSION['rol'] ?? '');
        
        $p = $_GET['p'] ?? 'inicio';
        $permisos = $userModel->obtenerPermisos($usuarioLogin, $rol);
        $sesionJson = $this->generarSesionJson($usuarioLogin, $rol, $permisos);

        require_once __DIR__ . '/../Vistas/layout.php';
    }

    private function generarSesionJson($u, $r, $permisos) {
        return json_encode([
            'usuario' => $u,
            'rol' => (strpos(strtolower($r), 'admin') !== false) ? 'admin' : 'analista',
            'permisos' => array_values(array_unique($permisos)),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
}