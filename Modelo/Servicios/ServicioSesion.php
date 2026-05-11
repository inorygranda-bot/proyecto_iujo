<?php
declare(strict_types=1);

require_once __DIR__ . '/../Infraestructura/conexionBD.php';
require_once __DIR__ . '/../Infraestructura/helpers_gestion_bd.php';
require_once __DIR__ . '/../SesionUsuario.php';

final class ServicioSesion
{
    /** @var string[] */
    private array $modsCompletos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion'];

    public function estaAutenticado(): bool
    {
        return isset($_SESSION['usuario']);
    }

    public function construirSesionUsuario(): SesionUsuario
    {
        $usuarioLogin = (string)($_SESSION['usuario'] ?? '');
        $nombreSesionRol = strtolower((string)($_SESSION['rol'] ?? ''));
        $permisosModulos = $this->resolverPermisos($usuarioLogin, $nombreSesionRol);

        return new SesionUsuario($usuarioLogin, $nombreSesionRol, $permisosModulos);
    }

    /**
     * @return string[]
     */
    private function resolverPermisos(string $usuarioLogin, string $nombreSesionRol): array
    {
        $migrarOk = false;
        $permisosModulos = [];

        try {
            $pdoSesion = obtenerConexionPdo();
            migrarEsquemaAplicacionOpcional($pdoSesion);
            $migrarOk = true;
        } catch (Throwable $e) {
            error_log('ServicioSesion migracion BD: ' . $e->getMessage());
        }

        if ($migrarOk && isset($pdoSesion)) {
            try {
                $permisosModulos = obtenerModulosUsuario($pdoSesion, $usuarioLogin);
            } catch (Throwable $e) {
                $permisosModulos = [];
            }
        }

        if ($nombreSesionRol === 'admin' || strpos($nombreSesionRol, 'admin') !== false) {
            return $this->modsCompletos;
        }

        if (($nombreSesionRol === 'analista' || strpos($nombreSesionRol, 'analista') !== false) && $permisosModulos === []) {
            return ['consulta', 'horarios', 'reportes'];
        }

        return $permisosModulos;
    }
}
