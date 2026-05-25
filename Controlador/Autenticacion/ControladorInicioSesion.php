<?php
// ===================================================================================================================
// Descripción: Controlador para manejar la lógica de inicio y cierre de sesión de usuarios.
//              Se encarga de la autenticación de credenciales, la creación de sesiones y el registro de auditorías
//              relacionadas con el acceso al sistema.
// ===================================================================================================================

declare(strict_types=1); // Forzar el uso de tipos estrictos para mejorar la calidad del código.

// Incluir archivos necesarios para la conexión a la base de datos y los servicios de auditoría.
require_once __DIR__ . '/../../Modelo/Infraestructura/conexionBD.php';
require_once __DIR__ . '/../../Modelo/Infraestructura/helpers_gestion_bd.php';
require_once __DIR__ . '/../../Modelo/Servicios/Auditorias/AuditoriaServicio.php';

final class ControladorInicioSesion
{
    // Constante para el mensaje de error genérico de inicio de sesión.
    private const MENSAJE_ERROR_INICIO_SESION = 'Credenciales incorrectas o acceso denegado.';

    // ===============================================================================================================
    // Método principal para manejar la solicitud de inicio de sesión.
    // ===============================================================================================================
    public function manejarSolicitud(): void
    {
        // Iniciar o reanudar la sesión PHP.
        session_start();

        // Verificar si se ha solicitado cerrar la sesión.
        if (isset($_GET['salir'])) {
            $this->cerrarSesion(); // Llamar al método para cerrar la sesión.
        }

        // Si la solicitud no es de tipo POST, redirigir al usuario a la página de inicio de sesión.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: inicioSesion.php');
            exit(); // Terminar la ejecución.
        }

        // Obtener y sanear el nombre de usuario y la contraseña de los datos POST.
        $usuario = trim((string)($_POST['usuario_login'] ?? ''));
        $password = (string)($_POST['password_login'] ?? '');

        // Validar que el usuario y la contraseña no estén vacíos.
        if ($usuario === '' || $password === '') {
            $this->responderJson(false, 'Debes completar usuario y contrasena.');
        }

        // --- Autenticación de usuarios de prueba (hardcodeados) ---
        // Esta sección permite el acceso con credenciales fijas para roles específicos.
        if ($usuario === 'admin' && $password === 'admin123') {
            $this->crearSesionUsuario('administrador', 'admin'); // Crear sesión para el administrador.
            $this->responderJson(true, 'Acceso correcto.', '../../index.php', [
                'usuario' => 'administrador',
                'rol' => 'admin',
            ]);
        }

        if ($usuario === 'analista' && $password === '123') {
            $this->crearSesionUsuario('analista de prueba', 'analista'); // Crear sesión para el analista.
            $this->responderJson(true, 'Acceso correcto.', '../../index.php', [
                'usuario' => 'analista de prueba',
                'rol' => 'analista',
            ]);
        }
        // -----------------------------------------------------------

        // Si no son usuarios hardcodeados, intentar autenticar contra la base de datos.
        $this->autenticarContraBaseDatos($usuario, $password);
    }

    // ===============================================================================================================
    // Método para cerrar la sesión del usuario.
    // ===============================================================================================================
    private function cerrarSesion(): void
    {
        // Establecer conexión y servicio de auditoría para registrar el cierre de sesión.
        $conexion = obtenerConexionPdo();
        migrarEsquemaAplicacionOpcional($conexion);
        $servicioAuditoria = new AuditoriaServicio($conexion);
        // Registrar el cierre de sesión si el usuario estaba logueado.
        if (isset($_SESSION['usuario'])) {
            $servicioAuditoria->registrarPorLogin((string)$_SESSION['usuario'], 'Cierre de Sesión', 'El usuario cerró sesión.');
        }

        // Destruir todas las variables de sesión y la sesión misma.
        session_unset();
        session_destroy();
        // Redirigir al usuario a la página de inicio de sesión.
        header('Location: inicioSesion.php');
        exit(); // Terminar la ejecución.
    }

    // ===============================================================================================================
    // Método para autenticar al usuario contra la base de datos.
    // ===============================================================================================================
    private function autenticarContraBaseDatos(string $usuario, string $password): void
    {
        try {
            // Obtener conexión y servicios necesarios.
            $conexion = obtenerConexionPdo();
            migrarEsquemaAplicacionOpcional($conexion);
            $servicioAuditoria = new AuditoriaServicio($conexion);

            // Consulta SQL para obtener los datos del usuario y su rol.
            $sql = 'SELECT u.usuario, u.contraseña, u.es_activo, r.nombre_rol
                    FROM usuarios u
                    INNER JOIN roles r ON r.id_rol = u.id_rol
                    WHERE u.usuario = :usuario
                    LIMIT 1';

            // Preparar y ejecutar la consulta.
            $consulta = $conexion->prepare($sql);
            $consulta->execute(['usuario' => $usuario]);
            $usuarioDb = $consulta->fetch();

            // Si el usuario no existe en la base de datos.
            if (!$usuarioDb) {
                $servicioAuditoria->registrarPorLogin($usuario, 'Intento Fallido de Sesión', 'Credenciales incorrectas.');
                $this->responderJson(false, self::MENSAJE_ERROR_INICIO_SESION);
            }

            // Verificar la contraseña.
            $passwordDb = (string)$usuarioDb['contraseña'];
            $passwordHashInfo = password_get_info($passwordDb);
            // Determinar si la contraseña almacenada es un hash válido.
            $esHashValido = !empty($passwordHashInfo['algo']);
            // Verificar la contraseña, permitiendo la comparación directa si no es un hash válido.
            $passwordValida = password_verify($password, $passwordDb) || hash_equals($passwordDb, $password);

            // Si la contraseña no es válida.
            if (!$passwordValida) {
                $servicioAuditoria->registrarPorLogin($usuario, 'Intento Fallido de Sesión', 'Contraseña incorrecta.');
                $this->responderJson(false, self::MENSAJE_ERROR_INICIO_SESION);
            }

            // Si la contraseña no es un hash válido o necesita ser rehasheada, actualizarla.
            if (!$esHashValido || password_needs_rehash($passwordDb, PASSWORD_DEFAULT)) {
                $this->actualizarHashPassword($conexion, $usuario, $password);
            }

            // Verificar el estado de la cuenta del usuario.
            $estadoAcceso = (int)($usuarioDb['es_activo'] ?? 1);
            if ($estadoAcceso !== 1) {
                $servicioAuditoria->registrarPorLogin($usuario, 'Intento Fallido de Sesión', 'Usuario deshabilitado o inactivo.');
                $this->responderJson(false, 'Este usuario esta deshabilitado o inactivo.');
            }

            // Obtener el rol del usuario.
            $rol = (string)($usuarioDb['nombre_rol'] ?? 'usuario');
            
            // Actualizar la última conexión del usuario y crear su sesión.
            $this->actualizarUltimaConexion($conexion, $usuario);
            $this->crearSesionUsuario($usuario, $rol);

            // Registrar el inicio de sesión exitoso en la auditoría.
            $servicioAuditoria->registrarPorLogin($usuario, 'Inicio de Sesión Exitoso', 'El usuario inició sesión correctamente.');
            // Responder con éxito y redirigir al usuario.
            $this->responderJson(true, 'Acceso correcto.', '../../index.php', [
                'usuario' => $usuario,
                'rol' => $rol,
            ]);
        } catch (Throwable $error) {
            // Capturar y registrar cualquier error inesperado durante la autenticación.
            error_log('Error en inicio de sesion: ' . $error->getMessage());
            // En caso de error general, registrar como intento fallido y responder con un mensaje de error.
            $servicioAuditoria->registrarPorLogin($usuario, 'Error en Inicio de Sesión', 'Error interno del servidor: ' . $error->getMessage());
            $this->responderJson(false, 'Error al conectar con la base de datos. Verifica tabla y credenciales.');
        }
    }

    // ===============================================================================================================
    // Método para crear las variables de sesión para el usuario autenticado.
    // ===============================================================================================================
    private function crearSesionUsuario(string $usuario, string $rol): void
    {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['rol'] = $rol;
    }

    // ===============================================================================================================
    // Método para actualizar el hash de la contraseña de un usuario en la base de datos si es necesario.
    // Esto asegura que todas las contraseñas usen el algoritmo de hash más seguro.
    // ===============================================================================================================
    private function actualizarHashPassword(PDO $conexion, string $usuario, string $passwordPlano): void
    {
        $nuevoHash = password_hash($passwordPlano, PASSWORD_DEFAULT);
        $actualizar = $conexion->prepare('UPDATE usuarios SET contraseña = :password WHERE usuario = :usuario');
        $actualizar->execute([
            'password' => $nuevoHash,
            'usuario' => $usuario,
        ]);
    }

    // ===============================================================================================================
    // Método para actualizar la marca de tiempo de la última conexión del usuario.
    // ===============================================================================================================
    private function actualizarUltimaConexion(PDO $conexion, string $usuario): void
    {
        $actualizar = $conexion->prepare('UPDATE usuarios SET ult_conexion = NOW() WHERE usuario = :usuario');
        $actualizar->execute(['usuario' => $usuario]);
    }

    // ===============================================================================================================
    // Método para estandarizar las respuestas JSON de la API.
    // ===============================================================================================================
    private function responderJson(bool $ok, string $mensaje, string $redirect = '', array $data = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'mensaje' => $mensaje,
            'redirect' => $redirect,
            'data' => $data,
        ]);
        exit(); // Terminar la ejecución después de enviar la respuesta JSON.
    }
}
