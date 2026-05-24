<?php
declare(strict_types=1);

require_once __DIR__ . '/../Infraestructura/conexionBD.php';
require_once __DIR__ . '/../Infraestructura/helpers_gestion_bd.php';
require_once __DIR__ . '/../SesionUsuario.php';

final class ServicioSesion
{
    /** @var string[] */
    private array $modsCompletos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion', 'auditorias'];

    public function estaAutenticado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public function construirSesionUsuario(): SesionUsuario
    {
        $usuarioLogin = (string)($_SESSION['usuario'] ?? '');
        $nombreSesionRol = strtolower((string)($_SESSION['rol'] ?? ''));
        $datos = $this->resolverDatosSesion($usuarioLogin, $nombreSesionRol);

        return new SesionUsuario(
            $usuarioLogin,
            $nombreSesionRol,
            $datos['permisos'],
            $datos['id_usuario']
        );
    }

    /**
     * @return array{permisos: string[], id_usuario: int}
     */
    private function resolverDatosSesion(string $usuarioLogin, string $nombreSesionRol): array
    {
        $permisosModulos = [];
        $idUsuario = 0;

        try {
            $pdoSesion = obtenerConexionPdo();
            migrarEsquemaAplicacionOpcional($pdoSesion);
            $permisosModulos = obtenerModulosUsuario($pdoSesion, $usuarioLogin);
            $idUsuario = $this->obtenerIdUsuario($pdoSesion, $usuarioLogin);
        } catch (Throwable $e) {
            error_log('ServicioSesion: ' . $e->getMessage());
        }

        if ($nombreSesionRol === 'admin' || strpos($nombreSesionRol, 'admin') !== false) {
            $permisosModulos = $this->modsCompletos;
        } elseif (
            ($nombreSesionRol === 'analista' || strpos($nombreSesionRol, 'analista') !== false)
            && $permisosModulos === []
        ) {
            $permisosModulos = ['consulta', 'horarios', 'reportes', 'auditorias'];
        }

        return [
            'permisos' => array_values(array_unique($permisosModulos)),
            'id_usuario' => $idUsuario,
        ];
    }

    private function obtenerIdUsuario(PDO $conexion, string $login): int
    {
        $stmt = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE usuario = :u LIMIT 1');
        $stmt->execute(['u' => $login]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int)$fila['id_usuario'] : 0;
    }
}
