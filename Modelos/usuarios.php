<?php
class Usuario {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Esta es la función que antes estaba en helpers, ahora es un MÉTODO de la clase
    public function obtenerModulosUsuario($usuarioLogin) {
        // Aquí pegas la lógica que tiene tu función original
        // Pero en lugar de recibir $pdo por fuera, usas $this->pdo
        $sql = "SELECT modulo FROM permisos_usuarios WHERE usuario = :usuario";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario' => $usuarioLogin]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN); 
    }

    public function obtenerPermisos($usuarioLogin, $rol) {
        $modsCompletos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion'];
        $rol = strtolower($rol);

        if (strpos($rol, 'admin') !== false) {
            return $modsCompletos;
        }

        try {
            // Ahora llamas al método interno de esta misma clase
            $permisos = $this->obtenerModulosUsuario($usuarioLogin);
            
            if (empty($permisos) && strpos($rol, 'analista') !== false) {
                return ['consulta', 'horarios', 'reportes'];
            }
            return $permisos;
        } catch (Throwable $e) {
            return [];
        }
    }
}