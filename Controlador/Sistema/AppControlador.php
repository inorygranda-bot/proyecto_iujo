<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Modelo/Servicios/ServicioSesion.php';

final class AppControlador
{
    private string $basePath;
    private ServicioSesion $servicioSesion;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->servicioSesion = new ServicioSesion();
    }

    public function mostrarAplicacion(): void
    {
        session_start();

        if (!$this->servicioSesion->estaAutenticado()) {
            header('Location: Controlador/Autenticacion/inicioSesion.php');
            exit();
        }

        $modulo = $this->resolverModulo((string)($_GET['p'] ?? 'inicio'));
        $sesion = $this->servicioSesion->construirSesionUsuario();
        $sesionClienteJson = json_encode(
            $sesion->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );

        $viewData = [
            'modulo' => $modulo,
            'sesionClienteJson' => $sesionClienteJson ?: '{}',
            'basePath' => $this->basePath,
        ];

        require $this->basePath . '/Vista/layout.php';
    }

    private function resolverModulo(string $modulo): string
    {
        $modulosPermitidos = ['inicio', 'registro', 'consulta', 'horarios', 'reportes', 'gestion', 'auditorias'];
        return in_array($modulo, $modulosPermitidos, true) ? $modulo : 'inicio';
    }
}
