<?php
// ===================================================================================================================
// Descripción: Servicio que encapsula la lógica de negocio para la gestión de auditorías.
//              Se encarga de la validación de datos, la interacción con el modelo de auditoría
//              y la gestión de errores relacionados con las operaciones de auditoría.
// ===================================================================================================================

declare(strict_types=1); // Forzar el uso de tipos estrictos para mejorar la calidad del código.

// Incluir el modelo de auditoría necesario para interactuar con la base de datos.
require_once __DIR__ . '/AuditoriaModelo.php';

/**
 * Clase `AuditoriaServicio`.
 * Implementa las reglas de negocio para las auditorías del sistema.
 * Proporciona métodos para listar y registrar eventos de auditoría.
 */
class AuditoriaServicio
{
    private AuditoriaModelo $modelo; // Instancia del modelo de auditoría para el acceso a datos.
    private array $errores = [];     // Almacena los errores de validación o de lógica de negocio.

    /**
     * Constructor de la clase AuditoriaServicio.
     *
     * @param PDO $conexion La conexión a la base de datos PDO, que se pasa al modelo.
     */
    public function __construct(PDO $conexion)
    {
        // Instanciar el modelo de auditoría, inyectando la conexión a la base de datos.
        $this->modelo = new AuditoriaModelo($conexion);
    }

    /**
     * Obtiene los errores de validación o de negocio acumulados durante las operaciones.
     *
     * @return array Un array de cadenas, donde cada cadena es un mensaje de error.
     */
    public function obtenerErrores(): array
    {
        return $this->errores;
    }

    /**
     * Lista todos los registros de auditoría disponibles en el sistema.
     *
     * @return array Un array de arrays asociativos, cada uno representando un registro de auditoría.
     */
    public function listar(): array
    {
        // Delegar la obtención de todas las auditorías al modelo.
        return $this->modelo->obtenerTodas();
    }

    /**
     * Registra una nueva acción de auditoría con un ID de usuario específico.
     * Realiza validaciones básicas antes de intentar registrar la auditoría.
     *
     * @param int $idUsuario El ID del usuario que realiza la acción.
     * @param string $accion Una breve descripción de la acción realizada.
     * @param string $descripcion Una descripción más detallada de la acción.
     * @return bool Retorna true si la auditoría se registró exitosamente, false en caso de error.
     */
    public function registrar(int $idUsuario, string $accion, string $descripcion): bool
    {
        $this->errores = []; // Limpiar errores anteriores.
        $accion = trim($accion); // Eliminar espacios en blanco de la acción.
        $descripcion = trim($descripcion); // Eliminar espacios en blanco de la descripción.

        // Validar que el ID de usuario sea válido.
        if ($idUsuario <= 0) {
            $this->errores[] = 'Usuario de sesion invalido.';
            return false;
        }
        // Validar que la acción no esté vacía.
        if ($accion === '') {
            $this->errores[] = 'La accion es obligatoria.';
            return false;
        }
        // Validar que la descripción no esté vacía.
        if ($descripcion === '') {
            $this->errores[] = 'La descripcion es obligatoria.';
            return false;
        }

        // Si todas las validaciones son exitosas, delegar el registro al modelo.
        $this->modelo->registrar($idUsuario, $accion, $descripcion);
        return true;
    }

    /**
     * Registra una nueva acción de auditoría buscando el ID de usuario por su login (nombre de usuario).
     * Es útil cuando solo se dispone del nombre de usuario en lugar de su ID.
     *
     * @param string $login El nombre de usuario (login) que realiza la acción.
     * @param string $accion Una breve descripción de la acción realizada.
     * @param string $descripcion Una descripción más detallada de la acción.
     * @return bool Retorna true si la auditoría se registró exitosamente, false en caso de error.
     */
    public function registrarPorLogin(string $login, string $accion, string $descripcion): bool
    {
        // Registrar un mensaje en el log de errores para propósitos de depuración.
        error_log("AuditoriaServicio::registrarPorLogin - login: {$login}, accion: {$accion}, descripcion: {$descripcion}");

        // Intentar obtener el ID del usuario a partir de su login.
        $idUsuario = $this->modelo->obtenerIdUsuarioPorLogin($login);
        
        // Si el usuario no se encuentra, agregar un error y retornar false.
        if ($idUsuario === null) {
            $this->errores[] = 'No se encontro el usuario: ' . $login;
            return false;
        }

        // Si se encuentra el ID de usuario, delegar el registro al método `registrar`.
        return $this->registrar($idUsuario, $accion, $descripcion);
    }
}
