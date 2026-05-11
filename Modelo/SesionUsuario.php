<?php
declare(strict_types=1);

final class SesionUsuario
{
    private string $usuario;
    private string $nombre;
    private string $rol;
    private array $permisos;

    public function __construct(string $usuario, string $rol, array $permisos)
    {
        $this->usuario = $usuario;
        $this->nombre = $usuario;
        $this->rol = $this->normalizarRol($rol);
        $this->permisos = array_values(array_unique($permisos));
    }

    public function toArray(): array
    {
        return [
            'usuario' => $this->usuario,
            'nombre' => $this->nombre,
            'rol' => $this->rol,
            'permisos' => $this->permisos,
        ];
    }

    private function normalizarRol(string $rol): string
    {
        $rolLimpio = strtolower($rol);
        return (strpos($rolLimpio, 'admin') !== false) ? 'admin' : 'analista';
    }
}
