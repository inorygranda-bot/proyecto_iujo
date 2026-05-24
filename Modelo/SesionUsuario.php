<?php
declare(strict_types=1);

final class SesionUsuario
{
    private string $usuario;
    private string $nombre;
    private string $rol;
    private array $permisos;
    private int $idUsuario;

    public function __construct(string $usuario, string $rol, array $permisos, int $idUsuario = 0)
    {
        $this->usuario = $usuario;
        $this->nombre = $usuario;
        $this->rol = $this->normalizarRol($rol);
        $this->permisos = array_values(array_unique($permisos));
        $this->idUsuario = $idUsuario;
    }

    public function toArray(): array
    {
        return [
            'usuario' => $this->usuario,
            'nombre' => $this->nombre,
            'rol' => $this->rol,
            'permisos' => $this->permisos,
            'id_usuario' => $this->idUsuario,
        ];
    }

    private function normalizarRol(string $rol): string
    {
        $rolLimpio = strtolower($rol);
        return (strpos($rolLimpio, 'admin') !== false) ? 'admin' : 'analista';
    }
}
