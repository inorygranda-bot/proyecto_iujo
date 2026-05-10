<?php
declare(strict_types=1);

require_once __DIR__ . '/conexionBD.php';
require_once __DIR__ . '/helpers_gestion_bd.php';

/**
 * Clase base para APIs con métodos comunes.
 */
class ApiBase
{
    protected PDO $conexion;
    protected AyudanteBaseDatos $ayudante;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
        $this->ayudante = new AyudanteBaseDatos($conexion);
    }

    /**
     * Responde con JSON y termina la ejecución.
     */
    protected function responder(bool $ok, string $mensaje, array $data = []): void
    {
        echo json_encode([
            'ok' => $ok,
            'mensaje' => $mensaje,
            'data' => $data,
        ]);
        exit();
    }

    /**
     * Obtiene un valor de POST.
     */
    protected function post(string $key, string $default = ''): string
    {
        return trim((string)($_POST[$key] ?? $default));
    }

    /**
     * Obtiene un array JSON de POST.
     */
    protected function postJsonArray(string $key): array
    {
        $raw = $_POST[$key] ?? '[]';
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Obtiene un entero de POST.
     */
    protected function postInt(string $key, int $default = 0): int
    {
        return (int)($_POST[$key] ?? $default);
    }

    /**
     * Obtiene ID de rol por nombre.
     */
    protected function obtenerIdRolInterno(string $rolCliente): int
    {
        $rolCliente = trim($rolCliente);
        if ($rolCliente === '') {
            throw new InvalidArgumentException('Rol vacío.');
        }

        $porNombre = $this->conexion->prepare(
            'SELECT id_rol FROM roles WHERE LOWER(nombre_rol) = LOWER(:n) LIMIT 1'
        );
        $porNombre->execute(['n' => $rolCliente]);
        $fila = $porNombre->fetch(PDO::FETCH_ASSOC);
        if ($fila && isset($fila['id_rol'])) {
            return (int)$fila['id_rol'];
        }

        /** Compatibilidad con alias cortos si en BD sólo existe «administrador» / «analista». */
        $low = mb_strtolower($rolCliente, 'UTF-8');
        if ($low === 'admin') {
            $alt = $this->conexion->prepare(
                'SELECT id_rol FROM roles WHERE LOWER(nombre_rol) IN (\'admin\',\'administrador\')
                 ORDER BY id_rol ASC LIMIT 1'
            );
            $alt->execute();
            $row = $alt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row['id_rol'];
            }
        }
        if ($low === 'analista' || $low === 'usuario') {
            $alt = $this->conexion->prepare(
                'SELECT id_rol FROM roles WHERE LOWER(nombre_rol) IN (\'analista\',\'usuario\')
                 ORDER BY id_rol ASC LIMIT 1'
            );
            $alt->execute();
            $row = $alt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row['id_rol'];
            }
        }

        $insertar = $this->conexion->prepare('INSERT INTO roles (nombre_rol) VALUES (:nombre_rol)');
        $insertar->execute(['nombre_rol' => $rolCliente]);

        return (int)$this->conexion->lastInsertId();
    }

    /**
     * Busca departamento por nombres de empresa y departamento.
     */
    protected function buscarDepartamentoPorNombres(string $nombreEmpresa, string $nombreDepartamento): ?array
    {
        $q = $this->conexion->prepare(
            'SELECT d.id_departamento, d.id_empresa
             FROM departamento d
             INNER JOIN Empresa e ON e.id_empresa = d.id_empresa
             WHERE e.nombre = :ne AND d.nombre_departamento = :nd
             LIMIT 1'
        );
        $q->execute(['ne' => $nombreEmpresa, 'nd' => $nombreDepartamento]);
        $f = $q->fetch(PDO::FETCH_ASSOC);

        return $f
            ? ['id_departamento' => (int)$f['id_departamento'], 'id_empresa' => (int)$f['id_empresa']]
            : null;
    }
}

/**
 * Clase para gestionar las acciones de la API.
 */
class GestorApi extends ApiBase
{
    public function procesarAccion(string $accion): void
    {
        switch ($accion) {
            case 'crear_empresa':
                $this->crearEmpresa();
                break;
            case 'crear_departamento':
                $this->crearDepartamento();
                break;
            case 'crear_empleado':
                $this->crearEmpleado();
                break;
            case 'obtener_empleados':
                $this->obtenerEmpleados();
                break;
            case 'crear_usuario':
                $this->crearUsuario();
                break;
            case 'listar_usuarios':
                $this->listarUsuarios();
                break;
            case 'actualizar_usuario':
                $this->actualizarUsuario();
                break;
            case 'eliminar_usuario':
                $this->eliminarUsuario();
                break;
            case 'cambiar_estado_usuario':
                $this->cambiarEstadoUsuario();
                break;
            case 'actualizar_empresa':
                $this->actualizarEmpresa();
                break;
            case 'actualizar_departamento':
                $this->actualizarDepartamento();
                break;
            case 'actualizar_empleado':
                $this->actualizarEmpleado();
                break;
            case 'actualizar_gerente_depto':
                $this->actualizarGerenteDepto();
                break;
            case 'obtener_datos_sistema':
                $this->obtenerDatosSistema();
                break;
            case 'guardar_datos_sistema':
                $this->guardarDatosSistema();
                break;
            case 'obtener_roles_sistema':
                $this->obtenerRolesSistema();
                break;
            case 'persistir_rol':
                $this->persistirRol();
                break;
            case 'eliminar_rol_por_id':
                $this->eliminarRolPorId();
                break;
            case 'guardar_roles_sistema':
                $this->responder(false, 'Use la acción persistir_rol o eliminar_rol_por_id.');
                break;
            case 'obtener_auditorias':
                $this->obtenerAuditorias();
                break;
            case 'registrar_auditoria':
                $this->registrarAuditoria();
                break;
            case 'verificar_auditorias':
                $this->verificarAuditorias();
                break;
            default:
                $this->responder(false, 'Acción no reconocida.');
        }
    }

    private function crearEmpresa(): void
    {
        $nombre = $this->post('nombre');
        $rif = strtoupper($this->post('rif'));
        $causa = $this->post('causa');
        $usuario = $this->post('usuario');

        if ($nombre === '' || $rif === '') {
            $this->responder(false, 'Nombre y RIF son obligatorios.');
        }

        $validar = $this->conexion->prepare('SELECT id_empresa FROM Empresa WHERE rif_empresa = :rif LIMIT 1');
        $validar->execute(['rif' => $rif]);
        if ($validar->fetch()) {
            $this->responder(false, 'El RIF de la empresa ya existe.');
        }

        $insertar = $this->conexion->prepare(
            'INSERT INTO Empresa (rif_empresa, nombre, causa, id_horario)
             VALUES (:rif_empresa, :nombre, :causa, NULL)'
        );
        $insertar->execute([
            'rif_empresa' => $rif,
            'nombre' => $nombre,
            'causa' => $causa,
        ]);

        if ($usuario !== '') {
            $idUser = $this->ayudante->idUsuarioPorLogin($usuario);
            if ($idUser !== null) {
                $this->ayudante->registrarAuditoria($idUser, 'Creó Empresa', "Empresa: $nombre (RIF: $rif)");
            }
        }

        $this->responder(true, 'Empresa registrada en base de datos.');
    }

    private function crearDepartamento(): void
    {
        $empresaNombre = $this->post('empresa');
        $nombreDepto = $this->post('nombre');
        $causa = $this->post('causa');
        $usuario = $this->post('usuario');

        if ($empresaNombre === '' || $nombreDepto === '') {
            $this->responder(false, 'Empresa y nombre del departamento son obligatorios.');
        }

        $empresaQuery = $this->conexion->prepare('SELECT id_empresa FROM Empresa WHERE nombre = :nombre LIMIT 1');
        $empresaQuery->execute(['nombre' => $empresaNombre]);
        $empresa = $empresaQuery->fetch();
        if (!$empresa) {
            $this->responder(false, 'La empresa seleccionada no existe en base de datos.');
        }

        $idEmpresa = (int)$empresa['id_empresa'];

        $existe = $this->conexion->prepare(
            'SELECT id_departamento
             FROM departamento
             WHERE id_empresa = :id_empresa AND nombre_departamento = :nombre
             LIMIT 1'
        );
        $existe->execute([
            'id_empresa' => $idEmpresa,
            'nombre' => $nombreDepto,
        ]);
        if ($existe->fetch()) {
            $this->responder(false, 'El departamento ya existe en la empresa seleccionada.');
        }

        $insertar = $this->conexion->prepare(
            'INSERT INTO departamento (nombre_departamento, causa, id_empresa, id_usuario, id_horario, supervisor_nombre)
             VALUES (:nombre_departamento, :causa, :id_empresa, NULL, NULL, \'Sin asignar\')'
        );
        $insertar->execute([
            'nombre_departamento' => $nombreDepto,
            'causa' => $causa,
            'id_empresa' => $idEmpresa,
        ]);

        if ($usuario !== '') {
            $idUser = $this->ayudante->idUsuarioPorLogin($usuario);
            if ($idUser !== null) {
                $this->ayudante->registrarAuditoria($idUser, 'Creó Departamento', "Departamento: $nombreDepto en Empresa: $empresaNombre");
            }
        }

        $this->responder(true, 'Departamento registrado en base de datos.');
    }

    private function crearEmpleado(): void
    {
        $codigo = strtoupper($this->post('codigo'));
        $cedula = strtoupper($this->post('cedula'));
        $rif = strtoupper($this->post('rif'));
        $nombres = $this->post('nombres');
        $apellidos = $this->post('apellidos');
        $cargo = $this->post('cargo');
        $nombreEmpresa = $this->post('empresa');
        $nombreDepto = $this->post('departamento');
        $usuario = $this->post('usuario');

        if ($codigo === '' || $cedula === '' || $rif === ''
            || $nombres === '' || $apellidos === '' || $cargo === ''
            || $nombreEmpresa === '' || $nombreDepto === ''
        ) {
            $this->responder(false, 'Todos los datos de empleado, empresa y departamento son obligatorios.');
        }

        $deptoUb = $this->buscarDepartamentoPorNombres($nombreEmpresa, $nombreDepto);
        if (!$deptoUb) {
            $this->responder(false, 'No existe el departamento indicado dentro de esa empresa.');
        }

        $existe = $this->conexion->prepare(
            'SELECT id_empleado FROM Empleados WHERE cedula_empleado = :cedula LIMIT 1'
        );
        $existe->execute(['cedula' => $cedula]);
        if ($existe->fetch()) {
            $this->responder(false, 'La cédula del empleado ya existe.');
        }

        $insertar = $this->conexion->prepare(
            'INSERT INTO Empleados
            (es_supervisor, codigo_empleado, cedula_empleado, rif_empleado, nombre, apellido, cargo,
             id_horario, id_departamento, jefe_inmediato)
             VALUES (0, :codigo, :cedula, :rif, :nombre, :apellido, :cargo,
             NULL, :id_dep, \'Sin asignar\')'
        );
        $insertar->execute([
            'codigo' => $codigo,
            'cedula' => $cedula,
            'rif' => $rif,
            'nombre' => $nombres,
            'apellido' => $apellidos,
            'cargo' => $cargo,
            'id_dep' => $deptoUb['id_departamento'],
        ]);

        if ($usuario !== '') {
            $idUser = $this->ayudante->idUsuarioPorLogin($usuario);
            if ($idUser !== null) {
                $this->ayudante->registrarAuditoria($idUser, 'Creó Empleado', "Empleado: $nombres $apellidos (Cédula: $cedula)");
            }
        }

        $this->responder(true, 'Empleado registrado en base de datos.');
    }

    private function obtenerEmpleados(): void
    {
        $stmt = $this->conexion->query('SELECT id_empleado, nombre, apellido, cedula_empleado FROM Empleados ORDER BY nombre, apellido');
        $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->responder(true, 'Empleados obtenidos.', $empleados);
    }

    private function crearUsuario(): void
    {
        $usuario = $this->post('usuario');
        $password = $this->post('password');
        $rol = $this->post('rol');
        $usuarioActual = $this->post('usuario_actual');

        if ($usuario === '' || $password === '' || $rol === '') {
            $this->responder(false, 'Usuario, contraseña y rol son obligatorios.');
        }

        $existe = $this->conexion->prepare('SELECT id_usuario FROM usuarios WHERE usuario = :usuario LIMIT 1');
        $existe->execute(['usuario' => $usuario]);
        if ($existe->fetch()) {
            $this->responder(false, 'El nombre de usuario ya existe.');
        }

        $idRol = $this->obtenerIdRolInterno($rol);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $insertar = $this->conexion->prepare(
            'INSERT INTO usuarios (usuario, id_rol, contraseña, es_activo, ult_conexion)
             VALUES (:usuario, :id_rol, :contrasena, 1, NOW())'
        );
        $insertar->execute([
            'usuario' => $usuario,
            'id_rol' => $idRol,
            'contrasena' => $hash,
        ]);

        if ($usuarioActual !== '') {
            $idUser = $this->ayudante->idUsuarioPorLogin($usuarioActual);
            if ($idUser !== null) {
                $this->ayudante->registrarAuditoria($idUser, 'Creó Usuario', "Usuario: $usuario");
            }
        }

        $this->responder(true, 'Usuario registrado en base de datos.');
    }

    private function listarUsuarios(): void
    {
        $consulta = $this->conexion->query(
            'SELECT u.id_usuario, u.usuario, u.id_rol, u.es_activo, r.nombre_rol
             FROM usuarios u
             LEFT JOIN roles r ON r.id_rol = u.id_rol
             ORDER BY u.usuario ASC'
        );
        $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);
        $this->responder(true, 'Usuarios cargados desde base de datos.', [
            'usuarios' => $usuarios,
        ]);
    }

    private function actualizarUsuario(): void
    {
        $usuarioOriginal = $this->post('usuario_original');
        $usuarioNuevo = $this->post('usuario');
        $password = $this->post('password');
        $rol = $this->post('rol');
        $estado = $this->postInt('estado', 1) === 1 ? 1 : 0;
        $usuarioActual = $this->post('usuario_actual');

        if ($usuarioOriginal === '' || $usuarioNuevo === '' || $rol === '') {
            $this->responder(false, 'Usuario original, usuario nuevo y rol son obligatorios.');
        }

        $idRol = $this->obtenerIdRolInterno($rol);
        $params = [
            'usuario_nuevo' => $usuarioNuevo,
            'id_rol' => $idRol,
            'es_activo' => $estado,
            'usuario_original' => $usuarioOriginal,
        ];

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $actualizar = $this->conexion->prepare(
                'UPDATE usuarios
                 SET usuario = :usuario_nuevo,
                     id_rol = :id_rol,
                     es_activo = :es_activo,
                     contraseña = :contrasena
                 WHERE usuario = :usuario_original'
            );
            $params['contrasena'] = $hash;
        } else {
            $actualizar = $this->conexion->prepare(
                'UPDATE usuarios
                 SET usuario = :usuario_nuevo,
                     id_rol = :id_rol,
                     es_activo = :es_activo
                 WHERE usuario = :usuario_original'
            );
        }

        $actualizar->execute($params);
        if ($actualizar->rowCount() === 0) {
            $this->responder(false, 'No se encontró el usuario en base de datos.');
        }

        if ($usuarioActual !== '') {
            $idUser = $this->ayudante->idUsuarioPorLogin($usuarioActual);
            if ($idUser !== null) {
                $this->ayudante->registrarAuditoria($idUser, 'Actualizó Usuario', "Usuario: $usuarioNuevo");
            }
        }

        $this->responder(true, 'Usuario actualizado en base de datos.');
    }

    private function eliminarUsuario(): void
    {
        $usuario = $this->post('usuario');
        if ($usuario === '') {
            $this->responder(false, 'Usuario requerido.');
        }

        $eliminar = $this->conexion->prepare('DELETE FROM usuarios WHERE usuario = :usuario');
        $eliminar->execute(['usuario' => $usuario]);
        if ($eliminar->rowCount() === 0) {
            $this->responder(false, 'No se encontró el usuario en base de datos.');
        }
        $this->responder(true, 'Usuario eliminado en base de datos.');
    }

    private function cambiarEstadoUsuario(): void
    {
        $usuario = $this->post('usuario');
        $estado = (int)$this->post('estado', '1');
        $estado = $estado === 1 ? 1 : 0;

        if ($usuario === '') {
            $this->responder(false, 'Usuario requerido.');
        }

        $actualizar = $this->conexion->prepare(
            'UPDATE usuarios
             SET es_activo = :es_activo
             WHERE usuario = :usuario'
        );
        $actualizar->execute([
            'es_activo' => $estado,
            'usuario' => $usuario,
        ]);

        if ($actualizar->rowCount() === 0) {
            $this->responder(false, 'No se encontró el usuario en base de datos.');
        }

        $this->responder(true, 'Estado de usuario actualizado en base de datos.');
    }

    private function actualizarEmpresa(): void
    {
        $nombreAnterior = $this->post('nombre_anterior');
        $nombre = $this->post('nombre');
        $rif = strtoupper($this->post('rif'));
        $causa = $this->post('causa');
        if ($nombreAnterior === '' || $nombre === '' || $rif === '') {
            $this->responder(false, 'Datos de empresa incompletos.');
        }
        $upd = $this->conexion->prepare(
            'UPDATE Empresa SET nombre = :nombre, rif_empresa = :rif, causa = :causa
             WHERE nombre = :anterior LIMIT 1'
        );
        $upd->execute([
            'nombre' => $nombre,
            'rif' => $rif,
            'causa' => $causa,
            'anterior' => $nombreAnterior,
        ]);
        if ($upd->rowCount() === 0) {
            $this->responder(false, 'No se encontró la empresa a actualizar.');
        }
        $this->responder(true, 'Empresa actualizada en base de datos.');
    }

    private function actualizarDepartamento(): void
    {
        $empAnt = $this->post('empresa_anterior');
        $nomAnt = $this->post('nombre_anterior');
        $empNueva = $this->post('empresa');
        $nombreNuevo = $this->post('nombre');
        $causa = $this->post('causa');
        if ($empAnt === '' || $nomAnt === '' || $empNueva === '' || $nombreNuevo === '') {
            $this->responder(false, 'Datos incompletos para actualizar departamento.');
        }

        $ubicacion = $this->buscarDepartamentoPorNombres($empAnt, $nomAnt);
        if (!$ubicacion) {
            $this->responder(false, 'No se encontró el departamento a actualizar.');
        }

        $empDest = $this->conexion->prepare('SELECT id_empresa FROM Empresa WHERE nombre = :n LIMIT 1');
        $empDest->execute(['n' => $empNueva]);
        $filEmp = $empDest->fetch(PDO::FETCH_ASSOC);
        if (!$filEmp) {
            $this->responder(false, 'La empresa destino no existe.');
        }

        $idEmpNueva = (int)$filEmp['id_empresa'];

        $dup = $this->conexion->prepare(
            'SELECT id_departamento FROM departamento WHERE id_empresa = :e AND nombre_departamento = :nd
             AND id_departamento <> :idc LIMIT 1'
        );
        $dup->execute([
            'e' => $idEmpNueva,
            'nd' => $nombreNuevo,
            'idc' => $ubicacion['id_departamento'],
        ]);
        if ($dup->fetch()) {
            $this->responder(false, 'Ya existe un departamento con ese nombre en la empresa seleccionada.');
        }

        $upd = $this->conexion->prepare(
            'UPDATE departamento
             SET nombre_departamento = :nn, causa = :causa, id_empresa = :ide
             WHERE id_departamento = :idc LIMIT 1'
        );
        $upd->execute([
            'nn' => $nombreNuevo,
            'causa' => $causa,
            'ide' => $idEmpNueva,
            'idc' => $ubicacion['id_departamento'],
        ]);

        $this->responder(true, 'Departamento actualizado.');
    }

    private function actualizarEmpleado(): void
    {
        $cedulaOrig = strtoupper($this->post('cedula_original'));
        $nombreEmpresa = $this->post('empresa');
        $nombreDepto = $this->post('departamento');
        $codigo = strtoupper($this->post('codigo'));
        $rif = strtoupper($this->post('rif'));
        $nombres = $this->post('nombres');
        $apellidos = $this->post('apellidos');
        $cargo = $this->post('cargo');
        $jefe = $this->post('jefe');

        if (
            $cedulaOrig === '' || $nombreEmpresa === '' || $nombreDepto === ''
            || $codigo === '' || $rif === ''
            || $nombres === '' || $apellidos === '' || $cargo === ''
        ) {
            $this->responder(false, 'Datos de empleado incompletos.');
        }

        $deptoUb = $this->buscarDepartamentoPorNombres($nombreEmpresa, $nombreDepto);
        if (!$deptoUb) {
            $this->responder(false, 'Departamento o empresa inválidos para el empleado.');
        }

        $updCorrect = $this->conexion->prepare(
            'UPDATE Empleados SET
                 codigo_empleado = :cod,
                 rif_empleado = :rif,
                 nombre = :nom,
                 apellido = :ape,
                 cargo = :car,
                 id_departamento = :iddep,
                 jefe_inmediato = :jefe
             WHERE cedula_empleado = :cedo LIMIT 1'
        );
        try {
            $updCorrect->execute([
                'cod' => $codigo,
                'rif' => $rif,
                'nom' => $nombres,
                'ape' => $apellidos,
                'car' => $cargo,
                'iddep' => $deptoUb['id_departamento'],
                'jefe' => $jefe !== '' ? $jefe : 'Sin asignar',
                'cedo' => $cedulaOrig,
            ]);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                $this->responder(false, 'Código duplicado o conflicto en datos.');
            }
            throw $e;
        }

        if ($updCorrect->rowCount() === 0) {
            $this->responder(false, 'No se encontró el empleado a actualizar.');
        }

        $this->responder(true, 'Empleado actualizado.');
    }

    private function actualizarGerenteDepto(): void
    {
        $empNombre = $this->post('empresa');
        $deptoNombre = $this->post('departamento');
        $nombreSupervisor = $this->post('supervisor');

        $row = $this->buscarDepartamentoPorNombres($empNombre, $deptoNombre);
        if (!$row) {
            $this->responder(false, 'Departamento no encontrado.');
        }

        $up = $this->conexion->prepare(
            'UPDATE departamento SET supervisor_nombre = :s WHERE id_departamento = :id LIMIT 1'
        );
        $up->execute(['s' => $nombreSupervisor, 'id' => $row['id_departamento']]);

        $upEmp = $this->conexion->prepare(
            'UPDATE Empleados SET jefe_inmediato = :j
             WHERE id_departamento = :id'
        );
        $upEmp->execute(['j' => $nombreSupervisor, 'id' => $row['id_departamento']]);

        $this->responder(true, 'Gerente asignado en base de datos.');
    }

    private function obtenerDatosSistema(): void
    {
        $datos = $this->ayudante->construirDatosSistemaDesdeRelacional();
        $this->responder(true, 'Datos cargados desde tablas relacionales.', [
            'datos' => $datos,
        ]);
    }

    private function guardarDatosSistema(): void
    {
        $entrada = $this->postJsonArray('datos');
        $entrada = $this->ayudante->normalizarDatosSistema($entrada);
        $this->ayudante->guardarJsonConfig('calendarios', $entrada['calendarios'] ?? []);
        $this->ayudante->guardarJsonConfig('incidencias', $entrada['incidencias'] ?? []);
        $this->responder(true, 'Preferencias guardadas en base de datos.');
    }

    private function obtenerRolesSistema(): void
    {
        $roles = $this->ayudante->obtenerRolesClienteDesdeBd();
        $this->responder(true, 'Roles cargados desde base de datos.', [
            'roles' => $roles,
        ]);
    }

    private function persistirRol(): void
    {
        $idRolStr = $this->post('id_rol');
        $nombreRol = $this->post('nombre');
        $permisosArr = $this->postJsonArray('permisos');

        if ($nombreRol === '') {
            $this->responder(false, 'Nombre de rol requerido.');
        }

        if ($idRolStr !== '' && ctype_digit($idRolStr)) {
            $idRol = (int)$idRolStr;
            $ex = $this->conexion->prepare('SELECT id_rol FROM roles WHERE id_rol = :id LIMIT 1');
            $ex->execute(['id' => $idRol]);
            if (!$ex->fetch()) {
                $this->responder(false, 'Rol no encontrado.');
            }
            $dup = $this->conexion->prepare(
                'SELECT id_rol FROM roles WHERE LOWER(nombre_rol) = LOWER(:n) AND id_rol <> :id LIMIT 1'
            );
            $dup->execute(['n' => $nombreRol, 'id' => $idRol]);
            if ($dup->fetch()) {
                $this->responder(false, 'Ya existe otro rol con ese nombre.');
            }
            $this->conexion->prepare('UPDATE roles SET nombre_rol = :n WHERE id_rol = :id')
                ->execute(['n' => $nombreRol, 'id' => $idRol]);
            $this->ayudante->guardarRolesPermisosParaRol($idRol, $permisosArr);
            $this->responder(true, 'Rol actualizado.', ['id_rol' => $idRol]);
        } else {
            $dup = $this->conexion->prepare('SELECT id_rol FROM roles WHERE LOWER(nombre_rol) = LOWER(:n) LIMIT 1');
            $dup->execute(['n' => $nombreRol]);
            if ($dup->fetch()) {
                $this->responder(false, 'Ya existe un rol con ese nombre.');
            }
            $this->conexion->prepare('INSERT INTO roles (nombre_rol) VALUES (:n)')->execute(['n' => $nombreRol]);
            $nuevoId = (int)$this->conexion->lastInsertId();
            $this->ayudante->guardarRolesPermisosParaRol($nuevoId, $permisosArr);
            $this->responder(true, 'Rol creado.', ['id_rol' => $nuevoId]);
        }
    }

    private function eliminarRolPorId(): void
    {
        $idRol = $this->postInt('id_rol');
        if ($idRol <= 0) {
            $this->responder(false, 'ID de rol inválido.');
        }

        $enUso = $this->conexion->prepare('SELECT COUNT(*) FROM usuarios WHERE id_rol = :id');
        $enUso->execute(['id' => $idRol]);
        if ((int)$enUso->fetchColumn() > 0) {
            $this->responder(false, 'No se puede eliminar: hay usuarios asignados a este rol.');
        }

        $this->conexion->prepare('DELETE FROM roles WHERE id_rol = :id')->execute(['id' => $idRol]);

        $this->responder(true, 'Rol eliminado.');
    }

    private function obtenerAuditorias(): void
    {
        $consultaAud = $this->conexion->query(
            'SELECT u.usuario AS usuario, a.accion, COALESCE(a.descripcion, \'\') AS detalle,
                    DATE_FORMAT(a.fecha_hora, \'%Y-%m-%d %H:%i:%s\') AS fecha
             FROM auditorias a
             INNER JOIN usuarios u ON u.id_usuario = a.id_usuario
             ORDER BY a.fecha_hora DESC'
        );

        /** @var list<array<string, string>> */
        $auditorias = $consultaAud->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->responder(true, 'Auditorías cargadas desde base de datos.', [
            'auditorias' => $auditorias,
        ]);
    }

    private function registrarAuditoria(): void
    {
        $usuario = $this->post('usuario');
        $accionAuditoria = $this->post('accion_auditoria');
        $detalle = $this->post('detalle');

        if ($usuario === '' || $accionAuditoria === '') {
            $this->responder(false, 'Usuario y acción son obligatorios.');
        }

        $idUser = $this->ayudante->idUsuarioPorLogin($usuario);
        if ($idUser === null) {
            $this->responder(false, 'No se encontró el usuario para registrar auditoría.');
        }

        $ins = $this->conexion->prepare(
            'INSERT INTO auditorias (id_usuario, accion, descripcion, fecha_hora)
             VALUES (:id, :acc, :descr, NOW())'
        );
        $ins->execute([
            'id' => $idUser,
            'acc' => $accionAuditoria,
            'descr' => $detalle,
        ]);

        $this->responder(true, 'Auditoría registrada.');
    }

    private function verificarAuditorias(): void
    {
        $count = $this->conexion->query('SELECT COUNT(*) FROM auditorias')->fetchColumn();
        $exists = $this->ayudante->tablaExiste('auditorias');
        $this->responder(true, 'Verificación de auditorías.', [
            'tabla_existe' => $exists,
            'registros' => (int)$count,
        ]);
    }
}

// Código de ejecución
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método HTTP no permitido.']);
    exit();
}

$accion = trim((string)($_POST['accion'] ?? ''));
if ($accion === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Acción requerida.']);
    exit();
}

try {
    $conexion = ConexionBaseDatos::obtenerConexionPDO();
    $ayudante = new AyudanteBaseDatos($conexion);
    $ayudante->migrarEsquemaAplicacionOpcional();
    $ayudante->asegurarPermisosModulos();

    $gestor = new GestorApi($conexion);
    $gestor->procesarAccion($accion);
} catch (Throwable $error) {
    error_log('Error en gestion_api.php: ' . $error->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos. Revise registros.']);
    exit();
}
