<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../Infraestructura/conexionBD.php';
require_once __DIR__ . '/../../Infraestructura/helpers_gestion_bd.php';

function responder(bool $ok, string $mensaje, array $data = []): void
{
    echo json_encode([
        'ok' => $ok,
        'mensaje' => $mensaje,
        'data' => $data,
    ]);
    exit();
}

function post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function postJsonArray(string $key): array
{
    $raw = $_POST[$key] ?? '[]';
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function postInt(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

/**
 * Obtiene id_rol por el nombre tal como viene del formulario (coincide con `roles.nombre_rol`).
 * Si no existe, crea un rol nuevo con ese nombre (no fuerza «analista»).
 */
function obtenerIdRolInterno(PDO $conexion, string $rolCliente): int
{
    $rolCliente = trim($rolCliente);
    if ($rolCliente === '') {
        throw new InvalidArgumentException('Rol vacío.');
    }

    $porNombre = $conexion->prepare(
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
        $alt = $conexion->prepare(
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
        $alt = $conexion->prepare(
            'SELECT id_rol FROM roles WHERE LOWER(nombre_rol) IN (\'analista\',\'usuario\')
             ORDER BY id_rol ASC LIMIT 1'
        );
        $alt->execute();
        $row = $alt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id_rol'];
        }
    }

    $insertar = $conexion->prepare('INSERT INTO roles (nombre_rol) VALUES (:nombre_rol)');
    $insertar->execute(['nombre_rol' => $rolCliente]);

    return (int)$conexion->lastInsertId();
}

/**
 * IDs de empresa y departamento por nombres.
 *
 * @return array{id_empresa:int, id_departamento:int}|null
 */
function buscarDepartamentoPorNombres(
    PDO $conexion,
    string $nombreEmpresa,
    string $nombreDepartamento
): ?array {
    $q = $conexion->prepare(
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método HTTP no permitido.');
}

$accion = post('accion');
if ($accion === '') {
    responder(false, 'Acción requerida.');
}

try {
    $conexion = obtenerConexionPdo();
    migrarEsquemaAplicacionOpcional($conexion);
    asegurarPermisosModulos($conexion);

    switch ($accion) {
        case 'crear_empresa':
            $nombre = post('nombre');
            $rif = strtoupper(post('rif'));
            $causa = post('causa');

            if ($nombre === '' || $rif === '') {
                responder(false, 'Nombre y RIF son obligatorios.');
            }

            $validar = $conexion->prepare('SELECT id_empresa FROM Empresa WHERE rif_empresa = :rif LIMIT 1');
            $validar->execute(['rif' => $rif]);
            if ($validar->fetch()) {
                responder(false, 'El RIF de la empresa ya existe.');
            }

            $insertar = $conexion->prepare(
                'INSERT INTO Empresa (rif_empresa, nombre, causa, id_horario)
                 VALUES (:rif_empresa, :nombre, :causa, NULL)'
            );
            $insertar->execute([
                'rif_empresa' => $rif,
                'nombre' => $nombre,
                'causa' => $causa,
            ]);

            responder(true, 'Empresa registrada en base de datos.');
            break;

        case 'crear_departamento':
            $empresaNombre = post('empresa');
            $nombreDepto = post('nombre');
            $causa = post('causa');

            if ($empresaNombre === '' || $nombreDepto === '') {
                responder(false, 'Empresa y nombre del departamento son obligatorios.');
            }

            $empresaQuery = $conexion->prepare('SELECT id_empresa FROM Empresa WHERE nombre = :nombre LIMIT 1');
            $empresaQuery->execute(['nombre' => $empresaNombre]);
            $empresa = $empresaQuery->fetch();
            if (!$empresa) {
                responder(false, 'La empresa seleccionada no existe en base de datos.');
            }

            $idEmpresa = (int)$empresa['id_empresa'];

            $existe = $conexion->prepare(
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
                responder(false, 'El departamento ya existe en la empresa seleccionada.');
            }

            $insertar = $conexion->prepare(
                'INSERT INTO departamento (nombre_departamento, causa, id_empresa, id_usuario, id_horario, supervisor_nombre)
                 VALUES (:nombre_departamento, :causa, :id_empresa, NULL, NULL, \'Sin asignar\')'
            );
            $insertar->execute([
                'nombre_departamento' => $nombreDepto,
                'causa' => $causa,
                'id_empresa' => $idEmpresa,
            ]);

            responder(true, 'Departamento registrado en base de datos.');
            break;

        case 'crear_empleado':
            $codigo = strtoupper(post('codigo'));
            $cedula = strtoupper(post('cedula'));
            $rif = strtoupper(post('rif'));
            $nombres = post('nombres');
            $apellidos = post('apellidos');
            $cargo = post('cargo');
            $nombreEmpresa = post('empresa');
            $nombreDepto = post('departamento');

            if ($codigo === '' || $cedula === '' || $rif === ''
                || $nombres === '' || $apellidos === '' || $cargo === ''
                || $nombreEmpresa === '' || $nombreDepto === ''
            ) {
                responder(false, 'Todos los datos de empleado, empresa y departamento son obligatorios.');
            }

            $deptoUb = buscarDepartamentoPorNombres($conexion, $nombreEmpresa, $nombreDepto);
            if (!$deptoUb) {
                responder(false, 'No existe el departamento indicado dentro de esa empresa.');
            }

            $existe = $conexion->prepare(
                'SELECT id_empleado FROM Empleados WHERE cedula_empleado = :cedula LIMIT 1'
            );
            $existe->execute(['cedula' => $cedula]);
            if ($existe->fetch()) {
                responder(false, 'La cédula del empleado ya existe.');
            }

            $insertar = $conexion->prepare(
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

            responder(true, 'Empleado registrado en base de datos.');
            break;

        case 'crear_usuario':
            $usuario = post('usuario');
            $password = post('password');
            $rol = post('rol');

            if ($usuario === '' || $password === '' || $rol === '') {
                responder(false, 'Usuario, contraseña y rol son obligatorios.');
            }

            $existe = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE usuario = :usuario LIMIT 1');
            $existe->execute(['usuario' => $usuario]);
            if ($existe->fetch()) {
                responder(false, 'El nombre de usuario ya existe.');
            }

            $idRol = obtenerIdRolInterno($conexion, $rol);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insertar = $conexion->prepare(
                'INSERT INTO usuarios (usuario, id_rol, contraseña, es_activo, ult_conexion)
                 VALUES (:usuario, :id_rol, :contrasena, 1, NOW())'
            );
            $insertar->execute([
                'usuario' => $usuario,
                'id_rol' => $idRol,
                'contrasena' => $hash,
            ]);

            responder(true, 'Usuario registrado en base de datos.');
            break;

        case 'listar_usuarios':
            $consulta = $conexion->query(
                'SELECT u.id_usuario, u.usuario, u.id_rol, u.es_activo, r.nombre_rol
                 FROM usuarios u
                 LEFT JOIN roles r ON r.id_rol = u.id_rol
                 ORDER BY u.usuario ASC'
            );
            $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);
            responder(true, 'Usuarios cargados desde base de datos.', [
                'usuarios' => $usuarios,
            ]);
            break;

        case 'actualizar_usuario':
            $usuarioOriginal = post('usuario_original');
            $usuarioNuevo = post('usuario');
            $password = post('password');
            $rol = post('rol');
            $estado = postInt('estado', 1) === 1 ? 1 : 0;

            if ($usuarioOriginal === '' || $usuarioNuevo === '' || $rol === '') {
                responder(false, 'Usuario original, usuario nuevo y rol son obligatorios.');
            }

            $idRol = obtenerIdRolInterno($conexion, $rol);
            $params = [
                'usuario_nuevo' => $usuarioNuevo,
                'id_rol' => $idRol,
                'es_activo' => $estado,
                'usuario_original' => $usuarioOriginal,
            ];

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $actualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET usuario = :usuario_nuevo,
                         id_rol = :id_rol,
                         es_activo = :es_activo,
                         contraseña = :contrasena
                     WHERE usuario = :usuario_original'
                );
                $params['contrasena'] = $hash;
            } else {
                $actualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET usuario = :usuario_nuevo,
                         id_rol = :id_rol,
                         es_activo = :es_activo
                     WHERE usuario = :usuario_original'
                );
            }

            $actualizar->execute($params);
            if ($actualizar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }

            responder(true, 'Usuario actualizado en base de datos.');
            break;

        case 'eliminar_usuario':
            $usuario = post('usuario');
            if ($usuario === '') {
                responder(false, 'Usuario requerido.');
            }

            $eliminar = $conexion->prepare('DELETE FROM usuarios WHERE usuario = :usuario');
            $eliminar->execute(['usuario' => $usuario]);
            if ($eliminar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }
            responder(true, 'Usuario eliminado en base de datos.');
            break;

        case 'cambiar_estado_usuario':
            $usuario = post('usuario');
            $estado = (int)post('estado', '1');
            $estado = $estado === 1 ? 1 : 0;

            if ($usuario === '') {
                responder(false, 'Usuario requerido.');
            }

            $actualizar = $conexion->prepare(
                'UPDATE usuarios
                 SET es_activo = :es_activo
                 WHERE usuario = :usuario'
            );
            $actualizar->execute([
                'es_activo' => $estado,
                'usuario' => $usuario,
            ]);

            if ($actualizar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }

            responder(true, 'Estado de usuario actualizado en base de datos.');
            break;

        case 'actualizar_empresa':
            $nombreAnterior = post('nombre_anterior');
            $nombre = post('nombre');
            $rif = strtoupper(post('rif'));
            $causa = post('causa');
            if ($nombreAnterior === '' || $nombre === '' || $rif === '') {
                responder(false, 'Datos de empresa incompletos.');
            }
            $upd = $conexion->prepare(
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
                responder(false, 'No se encontró la empresa a actualizar.');
            }
            responder(true, 'Empresa actualizada en base de datos.');
            break;

        case 'actualizar_departamento':
            $empAnt = post('empresa_anterior');
            $nomAnt = post('nombre_anterior');
            $empNueva = post('empresa');
            $nombreNuevo = post('nombre');
            $causa = post('causa');
            if ($empAnt === '' || $nomAnt === '' || $empNueva === '' || $nombreNuevo === '') {
                responder(false, 'Datos incompletos para actualizar departamento.');
            }

            $ubicacion = buscarDepartamentoPorNombres($conexion, $empAnt, $nomAnt);
            if (!$ubicacion) {
                responder(false, 'No se encontró el departamento a actualizar.');
            }

            $empDest = $conexion->prepare('SELECT id_empresa FROM Empresa WHERE nombre = :n LIMIT 1');
            $empDest->execute(['n' => $empNueva]);
            $filEmp = $empDest->fetch(PDO::FETCH_ASSOC);
            if (!$filEmp) {
                responder(false, 'La empresa destino no existe.');
            }

            $idEmpNueva = (int)$filEmp['id_empresa'];

            $dup = $conexion->prepare(
                'SELECT id_departamento FROM departamento WHERE id_empresa = :e AND nombre_departamento = :nd
                 AND id_departamento <> :idc LIMIT 1'
            );
            $dup->execute([
                'e' => $idEmpNueva,
                'nd' => $nombreNuevo,
                'idc' => $ubicacion['id_departamento'],
            ]);
            if ($dup->fetch()) {
                responder(false, 'Ya existe un departamento con ese nombre en la empresa seleccionada.');
            }

            $upd = $conexion->prepare(
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

            responder(true, 'Departamento actualizado.');
            break;

        case 'actualizar_empleado':
            $cedulaOrig = strtoupper(post('cedula_original'));
            $nombreEmpresa = post('empresa');
            $nombreDepto = post('departamento');
            $codigo = strtoupper(post('codigo'));
            $rif = strtoupper(post('rif'));
            $nombres = post('nombres');
            $apellidos = post('apellidos');
            $cargo = post('cargo');
            $jefe = post('jefe');

            if (
                $cedulaOrig === '' || $nombreEmpresa === '' || $nombreDepto === ''
                || $codigo === '' || $rif === ''
                || $nombres === '' || $apellidos === '' || $cargo === ''
            ) {
                responder(false, 'Datos de empleado incompletos.');
            }

            $deptoUb = buscarDepartamentoPorNombres($conexion, $nombreEmpresa, $nombreDepto);
            if (!$deptoUb) {
                responder(false, 'Departamento o empresa inválidos para el empleado.');
            }

            $updCorrect = $conexion->prepare(
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
                    responder(false, 'Código duplicado o conflicto en datos.');
                }
                throw $e;
            }

            if ($updCorrect->rowCount() === 0) {
                responder(false, 'No se encontró el empleado a actualizar.');
            }

            responder(true, 'Empleado actualizado.');
            break;

        case 'actualizar_gerente_depto':
            $empNombre = post('empresa');
            $deptoNombre = post('departamento');
            $nombreSupervisor = post('supervisor');

            $row = buscarDepartamentoPorNombres($conexion, $empNombre, $deptoNombre);
            if (!$row) {
                responder(false, 'Departamento no encontrado.');
            }

            $up = $conexion->prepare(
                'UPDATE departamento SET supervisor_nombre = :s WHERE id_departamento = :id LIMIT 1'
            );
            $up->execute(['s' => $nombreSupervisor, 'id' => $row['id_departamento']]);

            $upEmp = $conexion->prepare(
                'UPDATE Empleados SET jefe_inmediato = :j
                 WHERE id_departamento = :id'
            );
            $upEmp->execute(['j' => $nombreSupervisor, 'id' => $row['id_departamento']]);

            responder(true, 'Gerente asignado en base de datos.');
            break;

        case 'obtener_datos_sistema':
            $datos = construirDatosSistemaDesdeRelacional($conexion);
            responder(true, 'Datos cargados desde tablas relacionales.', [
                'datos' => $datos,
            ]);
            break;

        case 'guardar_datos_sistema':
            $entrada = postJsonArray('datos');
            $entrada = normalizarDatosSistema($entrada);
            guardarJsonConfig($conexion, 'calendarios', $entrada['calendarios'] ?? []);
            guardarJsonConfig($conexion, 'incidencias', $entrada['incidencias'] ?? []);
            responder(true, 'Preferencias guardadas en base de datos.');
            break;

        case 'obtener_roles_sistema':
            $roles = obtenerRolesClienteDesdeBd($conexion);
            responder(true, 'Roles cargados desde base de datos.', [
                'roles' => $roles,
            ]);
            break;

        case 'persistir_rol':
            $idRolStr = post('id_rol');
            $nombreRol = post('nombre');
            $permisosArr = postJsonArray('permisos');

            if ($nombreRol === '') {
                responder(false, 'Nombre de rol requerido.');
            }

            if ($idRolStr !== '' && ctype_digit($idRolStr)) {
                $idRol = (int)$idRolStr;
                $ex = $conexion->prepare('SELECT id_rol FROM roles WHERE id_rol = :id LIMIT 1');
                $ex->execute(['id' => $idRol]);
                if (!$ex->fetch()) {
                    responder(false, 'Rol no encontrado.');
                }
                $dup = $conexion->prepare(
                    'SELECT id_rol FROM roles WHERE LOWER(nombre_rol) = LOWER(:n) AND id_rol <> :id LIMIT 1'
                );
                $dup->execute(['n' => $nombreRol, 'id' => $idRol]);
                if ($dup->fetch()) {
                    responder(false, 'Ya existe otro rol con ese nombre.');
                }
                $conexion->prepare('UPDATE roles SET nombre_rol = :n WHERE id_rol = :id')
                    ->execute(['n' => $nombreRol, 'id' => $idRol]);
                guardarRolesPermisosParaRol($conexion, $idRol, $permisosArr);
                responder(true, 'Rol actualizado.', ['id_rol' => $idRol]);
            } else {
                $dup = $conexion->prepare('SELECT id_rol FROM roles WHERE LOWER(nombre_rol) = LOWER(:n) LIMIT 1');
                $dup->execute(['n' => $nombreRol]);
                if ($dup->fetch()) {
                    responder(false, 'Ya existe un rol con ese nombre.');
                }
                $conexion->prepare('INSERT INTO roles (nombre_rol) VALUES (:n)')->execute(['n' => $nombreRol]);
                $nuevoId = (int)$conexion->lastInsertId();
                guardarRolesPermisosParaRol($conexion, $nuevoId, $permisosArr);
                responder(true, 'Rol creado.', ['id_rol' => $nuevoId]);
            }
            break;

        case 'eliminar_rol_por_id':
            $idRol = postInt('id_rol');
            if ($idRol <= 0) {
                responder(false, 'ID de rol inválido.');
            }

            $enUso = $conexion->prepare('SELECT COUNT(*) FROM usuarios WHERE id_rol = :id');
            $enUso->execute(['id' => $idRol]);
            if ((int)$enUso->fetchColumn() > 0) {
                responder(false, 'No se puede eliminar: hay usuarios asignados a este rol.');
            }

            $conexion->prepare('DELETE FROM roles WHERE id_rol = :id')->execute(['id' => $idRol]);

            responder(true, 'Rol eliminado.');
            break;

        case 'guardar_roles_sistema':
            responder(false, 'Use la acción persistir_rol o eliminar_rol_por_id.');
            break;

        case 'obtener_auditorias':
            $consultaAud = $conexion->query(
                'SELECT u.usuario AS usuario, a.accion, COALESCE(a.descripcion, \'\') AS detalle,
                        DATE_FORMAT(a.fecha_hora, \'%Y-%m-%d %H:%i:%s\') AS fecha
                 FROM auditorias a
                 INNER JOIN usuarios u ON u.id_usuario = a.id_usuario
                 ORDER BY a.fecha_hora DESC'
            );

            /** @var list<array<string, string>> */
            $auditorias = $consultaAud->fetchAll(PDO::FETCH_ASSOC) ?: [];

            responder(true, 'Auditorías cargadas desde base de datos.', [
                'auditorias' => $auditorias,
            ]);
            break;

        case 'registrar_auditoria':
            $usuario = post('usuario');
            $accionAuditoria = post('accion_auditoria');
            $detalle = post('detalle');

            if ($usuario === '' || $accionAuditoria === '') {
                responder(false, 'Usuario y acción son obligatorios.');
            }

            $idUser = idUsuarioPorLogin($conexion, $usuario);
            if ($idUser === null) {
                responder(false, 'No se encontró el usuario para registrar auditoría.');
            }

            $ins = $conexion->prepare(
                'INSERT INTO auditorias (id_usuario, accion, descripcion, fecha_hora)
                 VALUES (:id, :acc, :descr, NOW())'
            );
            $ins->execute([
                'id' => $idUser,
                'acc' => $accionAuditoria,
                'descr' => $detalle,
            ]);

            responder(true, 'Auditoría registrada.');
            break;

        default:
            responder(false, 'Acción no reconocida.');
    }
} catch (Throwable $error) {
    error_log('Error en gestion_api.php: ' . $error->getMessage());
    responder(false, 'Error de base de datos. Revise registros.');
}
