<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Modelo/Infraestructura/conexionBD.php';

final class ControladorInicioSesion
{
    private const MENSAJE_ERROR_INICIO_SESION = 'Credenciales incorrectas o acceso denegado.';

    public function manejarSolicitud(): void
    {
        session_start();

        if (isset($_GET['salir'])) {
            $this->cerrarSesion();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: inicioSesion.php');
            exit();
        }

        $usuario = trim((string)($_POST['usuario_login'] ?? ''));
        $password = (string)($_POST['password_login'] ?? '');

        if ($usuario === '' || $password === '') {
            $this->responderJson(false, 'Debes completar usuario y contrasena.');
        }

        if ($usuario === 'admin' && $password === 'admin123') {
            $this->crearSesionUsuario('administrador', 'admin');
            $this->responderJson(true, 'Acceso correcto.', '../../index.php', [
                'usuario' => 'administrador',
                'rol' => 'admin',
            ]);
        }

        if ($usuario === 'analista' && $password === '123') {
            $this->crearSesionUsuario('analista de prueba', 'analista');
            $this->responderJson(true, 'Acceso correcto.', '../../index.php', [
                'usuario' => 'analista de prueba',
                'rol' => 'analista',
            ]);
        }

        $this->autenticarContraBaseDatos($usuario, $password);
    }

    private function cerrarSesion(): void
    {
        session_unset();
        session_destroy();
        header('Location: inicioSesion.php');
        exit();
    }

    private function autenticarContraBaseDatos(string $usuario, string $password): void
    {
        try {
            $conexion = obtenerConexionPdo();
            $sql = 'SELECT u.usuario, u.contraseña, u.es_activo, r.nombre_rol
                    FROM usuarios u
                    INNER JOIN roles r ON r.id_rol = u.id_rol
                    WHERE u.usuario = :usuario
                    LIMIT 1';

            $consulta = $conexion->prepare($sql);
            $consulta->execute(['usuario' => $usuario]);
            $usuarioDb = $consulta->fetch();

            if (!$usuarioDb) {
                $this->responderJson(false, self::MENSAJE_ERROR_INICIO_SESION);
            }

            $passwordDb = (string)$usuarioDb['contraseña'];
            $passwordHashInfo = password_get_info($passwordDb);
            $esHashValido = !empty($passwordHashInfo['algo']);
            $passwordValida = password_verify($password, $passwordDb) || hash_equals($passwordDb, $password);

            if (!$passwordValida) {
                $this->responderJson(false, self::MENSAJE_ERROR_INICIO_SESION);
            }

            if (!$esHashValido || password_needs_rehash($passwordDb, PASSWORD_DEFAULT)) {
                $this->actualizarHashPassword($conexion, $usuario, $password);
            }

            $estadoAcceso = (int)($usuarioDb['es_activo'] ?? 1);
            if ($estadoAcceso !== 1) {
                $this->responderJson(false, 'Este usuario esta deshabilitado o inactivo.');
            }

            $rol = (string)($usuarioDb['nombre_rol'] ?? 'usuario');
            $this->actualizarUltimaConexion($conexion, $usuario);
            $this->crearSesionUsuario($usuario, $rol);

            $this->responderJson(true, 'Acceso correcto.', '../../index.php', [
                'usuario' => $usuario,
                'rol' => $rol,
            ]);
        } catch (Throwable $error) {
            error_log('Error en inicio de sesion: ' . $error->getMessage());
            $this->responderJson(false, 'Error al conectar con la base de datos. Verifica tabla y credenciales.');
        }
    }

    private function crearSesionUsuario(string $usuario, string $rol): void
    {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['rol'] = $rol;
    }

    private function actualizarHashPassword(PDO $conexion, string $usuario, string $passwordPlano): void
    {
        $nuevoHash = password_hash($passwordPlano, PASSWORD_DEFAULT);
        $actualizar = $conexion->prepare('UPDATE usuarios SET contraseña = :password WHERE usuario = :usuario');
        $actualizar->execute([
            'password' => $nuevoHash,
            'usuario' => $usuario,
        ]);
    }

    private function actualizarUltimaConexion(PDO $conexion, string $usuario): void
    {
        $actualizar = $conexion->prepare('UPDATE usuarios SET ult_conexion = NOW() WHERE usuario = :usuario');
        $actualizar->execute(['usuario' => $usuario]);
    }

    private function responderJson(bool $ok, string $mensaje, string $redirect = '', array $data = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'mensaje' => $mensaje,
            'redirect' => $redirect,
            'data' => $data,
        ]);
        exit();
    }
}
