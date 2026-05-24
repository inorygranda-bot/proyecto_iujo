<?php
declare(strict_types=1);

require_once __DIR__ . '/AuditoriaModelo.php';

/**
 * Reglas de negocio para auditorias del sistema.
 */
class AuditoriaServicio
{
    private AuditoriaModelo $modelo;
    private array $errores = [];

    public function __construct(PDO $conexion)
    {
        $this->modelo = new AuditoriaModelo($conexion);
    }

    public function obtenerErrores(): array
    {
        return $this->errores;
    }

    public function listar(): array
    {
        return $this->modelo->obtenerTodas();
    }

    public function registrar(int $idUsuario, string $accion, string $descripcion): bool
    {
        $this->errores = [];
        $accion = trim($accion);
        $descripcion = trim($descripcion);

        if ($idUsuario <= 0) {
            $this->errores[] = 'Usuario de sesion invalido.';
            return false;
        }
        if ($accion === '') {
            $this->errores[] = 'La accion es obligatoria.';
            return false;
        }
        if ($descripcion === '') {
            $this->errores[] = 'La descripcion es obligatoria.';
            return false;
        }

        $this->modelo->registrar($idUsuario, $accion, $descripcion);
        return true;
    }

    public function registrarPorLogin(string $login, string $accion, string $descripcion): bool
    {
        $idUsuario = $this->modelo->obtenerIdUsuarioPorLogin($login);
        if ($idUsuario === null) {
            $this->errores[] = 'No se encontro el usuario: ' . $login;
            return false;
        }

        return $this->registrar($idUsuario, $accion, $descripcion);
    }
}
