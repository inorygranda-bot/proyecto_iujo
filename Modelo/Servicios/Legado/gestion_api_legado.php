<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../Infraestructura/conexionBD.php';
require_once __DIR__ . '/../../Infraestructura/helpers_gestion_bd.php';
require_once __DIR__ . '/../Auditorias/AuditoriaServicio.php';

function registrarAuditoriaDesdeSesion(PDO $conexion, string $accion, string $descripcion): void
{
    if (!isset($_SESSION['usuario']) || trim((string)$_SESSION['usuario']) === '') {
        return;
    }

    try {
        $servicio = new AuditoriaServicio($conexion);
        $servicio->registrarPorLogin((string)$_SESSION['usuario'], $accion, $descripcion);
    } catch (Throwable $e) {
        error_log('registrarAuditoriaDesdeSesion: ' . $e->getMessage());
    }
}

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

function persistirHorario(
    PDO $conexion,
    string $nombre,
    array $horarioData,
    ?int $idExistente = null
): int {
    // Obtener y limpiar los datos del horario
    $h_entrada = trim($horarioData['desdeM'] ?? '');
    $h_salida = trim($horarioData['hastaM'] ?? '');
    $h_entrada_tarde = trim($horarioData['desdeT'] ?? '') !== '' ? trim($horarioData['desdeT']) : null;
    $h_salida_tarde = trim($horarioData['hastaT'] ?? '') !== '' ? trim($horarioData['hastaT']) : null;

    // Si la hora tiene segundos (ej: 08:00:00), quitarlos para guardar solo HH:MM
    if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $h_entrada, $matches)) {
        $h_entrada = $matches[1];
    }
    if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $h_salida, $matches)) {
        $h_salida = $matches[1];
    }
    if ($h_entrada_tarde && preg_match('/^(\d{2}:\d{2}):\d{2}$/', $h_entrada_tarde, $matches)) {
        $h_entrada_tarde = $matches[1];
    }
    if ($h_salida_tarde && preg_match('/^(\d{2}:\d{2}):\d{2}$/', $h_salida_tarde, $matches)) {
        $h_salida_tarde = $matches[1];
    }

    // Registro de depuración: ver qué datos estamos recibiendo y limpiando
    error_log("persistirHorario: nombre={$nombre}, idExistente=" . ($idExistente ?? 'null') . 
              ", datos limpios: h_entrada={$h_entrada}, h_salida={$h_salida}, h_entrada_tarde=" . ($h_entrada_tarde ?? 'NULL') . ", h_salida_tarde=" . ($h_salida_tarde ?? 'NULL'));

    // Verificar si el idExistente realmente existe en la tabla horarios
    $existe = false;
    if ($idExistente) {
        $st = $conexion->prepare('SELECT id_horarios FROM horarios WHERE id_horarios = :id LIMIT 1');
        $st->execute(['id' => $idExistente]);
        $existe = $st->fetch() !== false;
        error_log("persistirHorario: idExistente={$idExistente}, existe en BD=" . ($existe ? 'SI' : 'NO'));
    }

    if ($existe) {
        // Actualizar horario existente
        error_log("persistirHorario: ACTUALIZANDO horario ID={$idExistente} en BD");
        $stmt = $conexion->prepare(
            'UPDATE horarios
             SET nombre_horario = :nombre,
                 h_entrada = :h_entrada,
                 h_salida = :h_salida,
                 h_entrada_tarde = :h_entrada_tarde,
                 h_salida_tarde = :h_salida_tarde
             WHERE id_horarios = :id'
        );
        $resultado = $stmt->execute([
            'nombre' => $nombre,
            'h_entrada' => $h_entrada,
            'h_salida' => $h_salida,
            'h_entrada_tarde' => $h_entrada_tarde,
            'h_salida_tarde' => $h_salida_tarde,
            'id' => $idExistente,
        ]);
        error_log("persistirHorario: UPDATE finalizado, resultado=" . ($resultado ? 'OK' : 'ERROR') . ", filas afectadas=" . $stmt->rowCount());
        return $idExistente;
    } else {
        // Crear nuevo horario
        error_log("persistirHorario: CREANDO nuevo horario en BD para {$nombre}");
        $stmt = $conexion->prepare(
            'INSERT INTO horarios (nombre_horario, h_entrada, h_salida, h_entrada_tarde, h_salida_tarde)
             VALUES (:nombre, :h_entrada, :h_salida, :h_entrada_tarde, :h_salida_tarde)'
        );
        $resultado = $stmt->execute([
            'nombre' => $nombre,
            'h_entrada' => $h_entrada,
            'h_salida' => $h_salida,
            'h_entrada_tarde' => $h_entrada_tarde,
            'h_salida_tarde' => $h_salida_tarde,
        ]);
        $nuevoId = (int)$conexion->lastInsertId();
        error_log("persistirHorario: INSERT finalizado, resultado=" . ($resultado ? 'OK' : 'ERROR') . ", nuevo ID generado=" . $nuevoId);
        return $nuevoId;
    }
}

function asignarHorarioAEntidad(
    PDO $conexion,
    string $tipoEntidad,
    string|int $idEntidad,
    ?int $idHorario
): void {
    // Registro de depuración
    error_log("asignarHorarioAEntidad: tipoEntidad={$tipoEntidad}, idEntidad={$idEntidad}, idHorario=" . ($idHorario ?? 'null'));

    switch ($tipoEntidad) {
        case 'empresas':
            // Asignar horario a una empresa (buscada por nombre)
            $stmt = $conexion->prepare('UPDATE Empresa SET id_horario = :id_h WHERE nombre = :id_e');
            $resultado = $stmt->execute(['id_h' => $idHorario, 'id_e' => $idEntidad]);
            error_log("asignarHorarioAEntidad (empresas): resultado=" . ($resultado ? 'OK' : 'ERROR') . ", filas afectadas=" . $stmt->rowCount());
            break;
        case 'departamentos':
            // Asignar horario a un departamento (buscado por nombre)
            $stmt = $conexion->prepare('UPDATE departamento SET id_horario = :id_h WHERE nombre_departamento = :id_e');
            $resultado = $stmt->execute(['id_h' => $idHorario, 'id_e' => $idEntidad]);
            error_log("asignarHorarioAEntidad (departamentos): resultado=" . ($resultado ? 'OK' : 'ERROR') . ", filas afectadas=" . $stmt->rowCount());
            break;
        case 'empleados':
            // Asignar horario a un empleado (buscado por cédula)
            $stmt = $conexion->prepare('UPDATE Empleados SET id_horario = :id_h WHERE cedula_empleado = :id_e');
            $resultado = $stmt->execute(['id_h' => $idHorario, 'id_e' => $idEntidad]);
            error_log("asignarHorarioAEntidad (empleados): resultado=" . ($resultado ? 'OK' : 'ERROR') . ", filas afectadas=" . $stmt->rowCount());
            break;
    }
}

function postInt(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

/**
 * Guarda los permisos asignados a un rol en la tabla `roles_permisos`.
 * Primero elimina todos los permisos existentes para el rol y luego inserta los nuevos.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @param int $idRol ID del rol.
 * @param array $permisosArr Array de nombres de permisos a asignar.
 * @return void
 */
function guardarRolesPermisosParaRol(PDO $conexion, int $idRol, array $permisosArr): void
{
    // Eliminar todos los permisos existentes para este rol para evitar duplicados y manejar eliminaciones.
    $conexion->prepare('DELETE FROM roles_permisos WHERE id_rol = :id_rol')
        ->execute(['id_rol' => $idRol]);

    // Si hay nuevos permisos para asignar, proceder con la inserción.
    if (!empty($permisosArr)) {
        $sql = 'INSERT INTO roles_permisos (id_rol, id_permisos) VALUES ';
        $values = [];
        $params = [];
        // Iterar sobre cada nombre de permiso para encontrar su ID y construir la consulta de inserción.
        foreach ($permisosArr as $idx => $permisoNombre) {
            // Obtener el id_permisos a partir del nombre_permisos de la tabla `permisos`.
            $st = $conexion->prepare('SELECT id_permisos FROM permisos WHERE nombre_permisos = :nombre LIMIT 1');
            $st->execute(['nombre' => $permisoNombre]);
            $idPermisos = $st->fetchColumn();

            // Si el ID del permiso se encuentra, añadirlo a la lista para la inserción masiva.
            if ($idPermisos) {
                $values[] = '(:id_rol_' . $idx . ', :id_permisos_' . $idx . ')';
                $params['id_rol_' . $idx] = $idRol;
                $params['id_permisos_' . $idx] = (int)$idPermisos;
            }
        }
        // Ejecutar la inserción si hay permisos válidos.
        if (!empty($values)) {
            $sql .= implode(', ', $values);
            $conexion->prepare($sql)->execute($params);
        }
    }
}

/**
 * Obtiene una lista de roles con sus permisos asociados desde la base de datos.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @return array Retorna un array de roles, cada uno incluyendo su ID, nombre y un array de nombres de permisos.
 */
function obtenerRolesClienteDesdeBd(PDO $conexion): array
{
    $roles = [];
    // Consultar todos los roles ordenados alfabéticamente.
    $stmtRoles = $conexion->query('SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol ASC');
    // Iterar sobre cada rol para obtener sus permisos.
    while ($rol = $stmtRoles->fetch(PDO::FETCH_ASSOC)) {
        $idRol = (int)$rol['id_rol'];
        // Consultar los nombres de los permisos asociados a este rol.
        $stmtPermisos = $conexion->prepare(
            'SELECT p.nombre_permisos FROM roles_permisos rp
             INNER JOIN permisos p ON p.id_permisos = rp.id_permisos
             WHERE rp.id_rol = :id_rol'
        );
        $stmtPermisos->execute(['id_rol' => $idRol]);
        // Obtener solo los nombres de los permisos como un array simple.
        $permisos = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN, 0);

        // Construir la estructura del rol con sus permisos.
        $roles[] = [
            'id_rol' => $idRol,
            'nombre_rol' => $rol['nombre_rol'],
            'permisos' => $permisos,
        ];
    }
    return $roles; // Retornar la lista de roles con sus permisos.
}

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

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Creo Empresa',
                "Empresa: {$nombre} (RIF: {$rif})"
            );

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

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Creó Departamento',
                "Departamento: {$nombreDepto} en Empresa: {$empresaNombre}"
            );

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
            // LUIS NO AGREGO EL COSO DE ES_SUPERVISOR Y POR ESO NO AGARRABA, FOKIU LUIS PT 2
            $esSupervisor = postInt('es_supervisor');

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
                 -- Aqui colocabas el valor de es_supervisor directamente como 0, por eso no lo guardaba -->
                 VALUES (:es_supervisor, :codigo, :cedula, :rif, :nombre, :apellido, :cargo,
                 NULL, :id_dep, \'Sin asignar\')'
            );
            $insertar->execute([
                'es_supervisor' => $esSupervisor, // Aqui tampoco tenias es_supervisor
                'codigo' => $codigo,
                'cedula' => $cedula,
                'rif' => $rif,
                'nombre' => $nombres,
                'apellido' => $apellidos,
                'cargo' => $cargo,
                'id_dep' => $deptoUb['id_departamento'],
            ]);

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Creó Empleado',
                "Empleado: {$nombres} {$apellidos} (Cédula: {$cedula}, Código: {$codigo})
                en Departamento: {$nombreDepto} de Empresa: {$nombreEmpresa}"
            );

            responder(true, 'Empleado registrado en base de datos.');
            break;

        case 'crear_usuario':
            $usuario = post('usuario');
            $password = post('password');
            $rol = post('rol');
            $rolId = postInt('id_rol');
            $empresasAsignadas = postJsonArray('empresas_asignadas');
            error_log('crear_usuario: empresas_asignadas=' . json_encode($empresasAsignadas, JSON_UNESCAPED_UNICODE));

            if ($usuario === '' || $password === '' || ($rolId <= 0 && $rol === '')) {
                responder(false, 'Usuario, contraseña y rol son obligatorios.');
            }

            $existe = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE usuario = :usuario LIMIT 1');
            $existe->execute(['usuario' => $usuario]);
            if ($existe->fetch()) {
                responder(false, 'El nombre de usuario ya existe.');
            }

            if ($rolId > 0) {
                $validRol = $conexion->prepare('SELECT id_rol FROM roles WHERE id_rol = :id LIMIT 1');
                $validRol->execute(['id' => $rolId]);
                if (!$validRol->fetch()) {
                    responder(false, 'Rol seleccionado no existe.');
                }
                $idRol = $rolId;
            } else {
                $idRol = obtenerIdRolInterno($conexion, $rol);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insertar = $conexion->prepare(
                'INSERT INTO usuarios (usuario, id_rol, contraseña, es_activo, ult_conexion, empresas_asignadas)
                 VALUES (:usuario, :id_rol, :contrasena, 1, NOW(), :empresas_asignadas)'
            );
            $insertar->execute([
                'usuario' => $usuario,
                'id_rol' => $idRol,
                'contrasena' => $hash,
                'empresas_asignadas' => json_encode(array_values($empresasAsignadas), JSON_UNESCAPED_UNICODE),
            ]);

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Creó Usuario',
                "Usuario: {$usuario} con Rol: {$rol}"
            );

            responder(true, 'Usuario registrado en base de datos.');
            break;

        case 'listar_usuarios':
            $consulta = $conexion->query(
                'SELECT u.id_usuario, u.usuario, u.id_rol, u.empresas_asignadas, u.es_activo, r.nombre_rol
                 FROM usuarios u
                 LEFT JOIN roles r ON r.id_rol = u.id_rol
                 ORDER BY u.usuario ASC'
            );
            $usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);
            foreach ($usuarios as &$usuario) {
                $usuario['empresas_asignadas'] = json_decode((string)($usuario['empresas_asignadas'] ?? '[]'), true);
                if (!is_array($usuario['empresas_asignadas'])) {
                    $usuario['empresas_asignadas'] = [];
                }
            }
            unset($usuario);
            responder(true, 'Usuarios cargados desde base de datos.', [
                'usuarios' => $usuarios,
            ]);
            break;

        case 'actualizar_usuario':
            $usuarioOriginal = post('usuario_original');
            $usuarioNuevo = post('usuario');
            $password = post('password');
            $rol = post('rol');
            $rolId = postInt('id_rol');
            $estado = postInt('estado', 1) === 1 ? 1 : 0;
            $empresasAsignadas = postJsonArray('empresas_asignadas');
            error_log('actualizar_usuario: empresas_asignadas=' . json_encode($empresasAsignadas, JSON_UNESCAPED_UNICODE));

            if ($usuarioOriginal === '' || $usuarioNuevo === '' || ($rolId <= 0 && $rol === '')) {
                responder(false, 'Usuario original, usuario nuevo y rol son obligatorios.');
            }

            if ($rolId > 0) {
                $validRol = $conexion->prepare('SELECT id_rol FROM roles WHERE id_rol = :id LIMIT 1');
                $validRol->execute(['id' => $rolId]);
                if (!$validRol->fetch()) {
                    responder(false, 'Rol seleccionado no existe.');
                }
                $idRol = $rolId;
            } else {
                $idRol = obtenerIdRolInterno($conexion, $rol);
            }

            $params = [
                'usuario_nuevo' => $usuarioNuevo,
                'id_rol' => $idRol,
                'es_activo' => $estado,
                'empresas_asignadas' => json_encode(array_values($empresasAsignadas), JSON_UNESCAPED_UNICODE),
                'usuario_original' => $usuarioOriginal,
            ];

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $actualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET usuario = :usuario_nuevo,
                         id_rol = :id_rol,
                         es_activo = :es_activo,
                         contraseña = :contrasena,
                         empresas_asignadas = :empresas_asignadas
                     WHERE usuario = :usuario_original'
                );
                $params['contrasena'] = $hash;
            } else {
                $actualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET usuario = :usuario_nuevo,
                         id_rol = :id_rol,
                         es_activo = :es_activo,
                         empresas_asignadas = :empresas_asignadas
                     WHERE usuario = :usuario_original'
                );
            }

            $actualizar->execute($params);
            if ($actualizar->rowCount() === 0) {
                responder(false, 'No se encontró el usuario en base de datos.');
            }

            $descripcionAuditoria = "Usuario {$usuarioOriginal} actualizado a {$usuarioNuevo}";
            if ($password !== '') {
                $descripcionAuditoria .= " (contraseña cambiada)";
            }
            $descripcionAuditoria .= ", Rol: {$rol}, Estado: " . ($estado === 1 ? 'Activo' : 'Inactivo');

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Actualizó Usuario',
                $descripcionAuditoria
            );

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
            registrarAuditoriaDesdeSesion(
                $conexion,
                'Eliminó Usuario',
                "Usuario: {$usuario}"
            );
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
            registrarAuditoriaDesdeSesion(
                $conexion,
                'Cambió Estado Usuario',
                "Usuario: {$usuario}, Nuevo estado: " . ($estado === 1 ? 'Activo' : 'Inactivo')
            );
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
            registrarAuditoriaDesdeSesion(
                $conexion,
                'Actualizó Empresa',
                "Empresa: {$nombreAnterior} a {$nombre} (RIF: {$rif})
                , Causa: {$causa}"
            );
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
            registrarAuditoriaDesdeSesion(
                $conexion,
                'Actualizó Departamento',
                "Departamento: {$nomAnt} (Empresa: {$empAnt}) a {$nombreNuevo} (Empresa: {$empNueva}), Causa: {$causa}"
            );
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
            // Se optiene el valor de es_supervisor
            $esSupervisor = postInt('es_supervisor');

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
                     jefe_inmediato = :jefe,
                     es_supervisor = :es_supervisor 
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
                    'es_supervisor' => $esSupervisor, 
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

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Actualizó Empleado',
                "Empleado: {$nombres} {$apellidos} (Cédula Original: {$cedulaOrig}, Nuevo Código: {$codigo})"
            );

            responder(true, 'Empleado actualizado.');
            break;

        case 'actualizar_gerente_depto':
            $empNombre = post('empresa');
            $deptoNombre = post('departamento');
            $nombreSupervisor = post('supervisor');
            $cedulaSupervisor = post('cedula_supervisor'); //Obtener la cédula del supervisor

            if ($cedulaSupervisor === '') {
                responder(false, 'Cédula del supervisor es obligatoria.');
            }

            $row = buscarDepartamentoPorNombres($conexion, $empNombre, $deptoNombre);
            if (!$row) {
                responder(false, 'Departamento no encontrado.');
            }

            $idDepartamento = $row['id_departamento'];

            // Obtener el supervisor actual del departamento para desmarcarlo si es diferente
            $stCurrentSupervisor = $conexion->prepare(
                'SELECT supervisor_nombre FROM departamento WHERE id_departamento = :id LIMIT 1'
            );
            $stCurrentSupervisor->execute(['id' => $idDepartamento]);
            $currentSupervisorName = $stCurrentSupervisor->fetchColumn();

            //Si hay un supervisor actual y es diferente al nuevo, desmarcarlo como supervisor
            if ($currentSupervisorName && $currentSupervisorName !== $nombreSupervisor) {
                $stOldSupervisor = $conexion->prepare(
                    'UPDATE Empleados SET es_supervisor = 0 WHERE nombre = :nom AND apellido = :ape LIMIT 1'
                );
                $names = explode(' ', $currentSupervisorName, 2);
                if (count($names) === 2) {
                    $stOldSupervisor->execute([
                        'nom' => $names[0],
                        'ape' => $names[1],
                    ]);
                }
            }

            // Actualizar el departamento con el nuevo supervisor
            $up = $conexion->prepare(
                'UPDATE departamento SET supervisor_nombre = :s WHERE id_departamento = :id LIMIT 1'
            );
            $up->execute(['s' => $nombreSupervisor, 'id' => $idDepartamento]);

            // Actualizar jefe_inmediato para los empleados en ese departamento
            $upEmp = $conexion->prepare(
                'UPDATE Empleados SET jefe_inmediato = :j
                 WHERE id_departamento = :id'
            );
            $upEmp->execute(['j' => $nombreSupervisor, 'id' => $idDepartamento]);

            // Marcar al nuevo supervisor como es_supervisor = 1
            $upNewSupervisor = $conexion->prepare(
                'UPDATE Empleados SET es_supervisor = 1 WHERE cedula_empleado = :cedula LIMIT 1'
            );
            $upNewSupervisor->execute(['cedula' => $cedulaSupervisor]);

            responder(true, 'Gerente asignado en base de datos.');
            registrarAuditoriaDesdeSesion(
                $conexion,
                'Asignó Gerente Departamento',
                "Departamento: {$deptoNombre} (Empresa: {$empNombre}), Supervisor: {$nombreSupervisor}"
            );
            break;

        case 'obtener_datos_sistema':
            $datos = construirDatosSistemaDesdeRelacional($conexion);

            $usuarioActivo = [
                'usuario' => '',
                'rol' => '',
                'empresas_asignadas' => [],
            ];

            $sessionUsuario = trim((string)($_SESSION['usuario'] ?? ''));
            $sessionRol = trim((string)($_SESSION['rol'] ?? ''));
            if ($sessionUsuario !== '') {
                $stmt = $conexion->prepare(
                    'SELECT u.usuario, COALESCE(r.nombre_rol, \'\') AS nombre_rol, COALESCE(u.empresas_asignadas, \'[]\') AS empresas_asignadas
                     FROM usuarios u
                     LEFT JOIN roles r ON r.id_rol = u.id_rol
                     WHERE u.usuario = :usuario
                     LIMIT 1'
                );
                $stmt->execute(['usuario' => $sessionUsuario]);
                $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($fila) {
                    $empresasAsignadas = json_decode((string)$fila['empresas_asignadas'], true);
                    if (!is_array($empresasAsignadas)) {
                        $empresasAsignadas = [];
                    }
                    $usuarioActivo = [
                        'usuario' => (string)$fila['usuario'],
                        'rol' => strtolower(trim((string)$fila['nombre_rol'])),
                        'empresas_asignadas' => array_values(array_map('strval', $empresasAsignadas)),
                    ];
                } else {
                    $usuarioActivo = [
                        'usuario' => $sessionUsuario,
                        'rol' => strtolower($sessionRol),
                        'empresas_asignadas' => [],
                    ];
                }
            }

            // Log para depuración: verificar qué datos se están devolviendo
            error_log('Datos del sistema devueltos: ' . json_encode($datos));
            responder(true, 'Datos cargados desde tablas relacionales.', [
                'datos' => $datos,
                'usuario_activo' => $usuarioActivo,
            ]);
            break;

        case 'guardar_datos_sistema':
            $conexion->beginTransaction();
            error_log("guardar_datos_sistema: Transacción iniciada");

            try {
                // Registro de depuración: ver datos brutos recibidos
                error_log("guardar_datos_sistema: datos brutos=" . ($_POST['datos'] ?? 'NO HAY DATOS'));
                
                // Obtener y normalizar los datos enviados desde el frontend
                $entrada = postJsonArray('datos');
                error_log("guardar_datos_sistema: entrada después de postJsonArray=" . json_encode($entrada));
                
                $entrada = normalizarDatosSistema($entrada);
                error_log("guardar_datos_sistema: entrada después de normalizar=" . json_encode($entrada));

                // Extraer la sección de calendarios
                $calendarios = $entrada['calendarios'] ?? [];
                // Extraer la sección de horarios dentro de calendarios
                $horarios = $calendarios['horarios'] ?? [];
                error_log("guardar_datos_sistema: horarios a guardar=" . json_encode($horarios));

                error_log("guardar_datos_sistema: INICIO guardado de horario general");
                // Buscar si ya existe un horario con nombre 'general'
                $st = $conexion->prepare('SELECT id_horarios FROM horarios WHERE nombre_horario = :n LIMIT 1');
                $st->execute(['n' => 'general']);
                $idHorarioGeneral = $st->fetchColumn();
                error_log("guardar_datos_sistema: idHorarioGeneral encontrado=" . ($idHorarioGeneral !== false ? $idHorarioGeneral : 'NO ENCONTRADO'));
                
                // Persistir el horario (actualizar si existe, crear si no)
                $idHorarioGeneral = persistirHorario(
                    $conexion,
                    'general',
                    $horarios['general'],
                    $idHorarioGeneral !== false ? (int)$idHorarioGeneral : null
                );
                error_log("guardar_datos_sistema: FIN guardado de horario general, ID final=" . $idHorarioGeneral);

                // Guardar horarios

                foreach (['empresas', 'departamentos', 'empleados'] as $tipoEntidad) {
                    error_log("guardar_datos_sistema: Procesando tipoEntidad=" . $tipoEntidad);
                    if (isset($horarios[$tipoEntidad]) && is_array($horarios[$tipoEntidad])) {
                        error_log("guardar_datos_sistema: Hay " . count($horarios[$tipoEntidad]) . " horarios para {$tipoEntidad}");
                        foreach ($horarios[$tipoEntidad] as $idEntidad => $horarioData) {
                            error_log("guardar_datos_sistema: Procesando {$tipoEntidad} {$idEntidad}, horarioData=" . json_encode($horarioData));
                            // Verificar que los datos del horario sean válidos
                            if (is_array($horarioData) && !empty($horarioData['desdeM'])) {
                                // Obtener el id_horario actualmente asignado a la entidad (si existe)
                                $idExistente = null;
                                if ($tipoEntidad === 'empresas') {
                                    $st = $conexion->prepare('SELECT id_horario FROM Empresa WHERE nombre = :id_e LIMIT 1');
                                    $st->execute(['id_e' => $idEntidad]);
                                    $idExistente = $st->fetchColumn();
                                } elseif ($tipoEntidad === 'departamentos') {
                                    $st = $conexion->prepare('SELECT id_horario FROM departamento WHERE nombre_departamento = :id_e LIMIT 1');
                                    $st->execute(['id_e' => $idEntidad]);
                                    $idExistente = $st->fetchColumn();
                                } elseif ($tipoEntidad === 'empleados') {
                                    $st = $conexion->prepare('SELECT id_horario FROM Empleados WHERE cedula_empleado = :id_e LIMIT 1');
                                    $st->execute(['id_e' => $idEntidad]);
                                    $idExistente = $st->fetchColumn();
                                }
                                error_log("guardar_datos_sistema: idExistente para {$tipoEntidad} {$idEntidad} es " . ($idExistente !== false ? $idExistente : 'NO HAY'));

                                // Persistir el horario en la tabla 'horarios'
                                $idHorarioGuardado = persistirHorario(
                                    $conexion,
                                    "{$tipoEntidad}-{$idEntidad}", // Nombre único para el horario
                                    $horarioData,
                                    $idExistente !== false ? (int)$idExistente : null
                                );

                                // Asignar el horario guardado a la entidad correspondiente
                                asignarHorarioAEntidad($conexion, $tipoEntidad, $idEntidad, $idHorarioGuardado);
                            } else {
                                // Si el horario está vacío, desasignarlo de la entidad
                                error_log("guardar_datos_sistema: Desasignando horario para {$tipoEntidad} {$idEntidad} (datos inválidos)");
                                asignarHorarioAEntidad($conexion, $tipoEntidad, $idEntidad, null);
                            }
                        }
                    } else {
                        error_log("guardar_datos_sistema: No hay horarios para {$tipoEntidad} (o no es un array)");
                    }
                }

                $calendariosParaJson = $calendarios;
                unset($calendariosParaJson['horarios']); // Excluir horarios porque ya los guardamos relacionalmente

                // Guardar toda la información de calendarios (excepto horarios) en formato JSON
                guardarJsonConfig($conexion, 'calendarios', $calendariosParaJson);

                // Guardar la sección de incidencias también en formato JSON
                guardarJsonConfig($conexion, 'incidencias', $entrada['incidencias'] ?? []);

                $conexion->commit();
                error_log("guardar_datos_sistema: Transacción confirmada (commit)");

                registrarAuditoriaDesdeSesion(
                    $conexion,
                    'Guardó Datos del Sistema',
                    'Se actualizaron múltiples configuraciones del sistema (horarios, calendarios, etc.)'
                );

                // Responder con éxito
                responder(true, 'Preferencias guardadas en base de datos.');
            } catch (Throwable $e) {
                $conexion->rollBack();
                error_log("guardar_datos_sistema: Error en transacción, rollback ejecutado: " . $e->getMessage() . " - " . $e->getTraceAsString());
                throw $e; 
            }
            break;

        case 'obtener_roles_sistema':
            $roles = obtenerRolesClienteDesdeBd($conexion);
            responder(true, 'Roles cargados desde base de datos.', [
                'roles' => $roles,
            ]);
            break;

        case 'importar_txt_asistencia':
            // Importar archivo TXT de asistencia a la tabla asistencia
            require_once __DIR__ . '/../Incidencias/IncidenciaServicio.php';
            $servicio = new IncidenciaServicio($conexion);

            $contenido = '';

            // Recuperar el texto enviado desde el FormData del JS
            if (isset($_POST['es_texto_directo']) && $_POST['es_texto_directo'] === 'true') {
                $contenido = $_POST['contenido_archivo'] ?? '';
            } 

            if (trim($contenido) === '') {
                responder(false, "El servidor recibió datos vacíos. Asegúrate de que el archivo contenga texto de asistencia.");
            }

            // Crear el archivo físico temporal en el entorno de PHP para el servicio
            $directorioTemporal = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
            $rutaTemporal = $directorioTemporal . DIRECTORY_SEPARATOR . 'asistencia_procesar_' . uniqid() . '.txt';
            
            if (file_put_contents($rutaTemporal, $contenido) === false) {
                responder(false, 'Error interno: No se pudo escribir el archivo temporal en el servidor.');
            }

            clearstatcache(true, $rutaTemporal);

            // Iniciar transacción de Base de Datos
            $conexion->beginTransaction();

            try {
                // Se ejecuta el método que lee e inserta en la tabla de asistencias
                $resultado = $servicio->importarTXT($rutaTemporal);
                
                $conexion->commit();

                if (file_exists($rutaTemporal)) {
                    @unlink($rutaTemporal);
                }
                
                responder(true, 'Importación completada con éxito.', $resultado);

            } catch (Throwable $e) {
                $conexion->rollBack();
                if (file_exists($rutaTemporal)) {
                    @unlink($rutaTemporal);
                }
                error_log("Error crítico en importar_txt_asistencia: " . $e->getMessage());
                responder(false, 'Error interno al procesar las líneas del archivo: ' . $e->getMessage());
            }
            break;

        case 'obtener_asistencias':

            // Obtener asistencias por rango de fechas

            require_once __DIR__ . '/../Incidencias/IncidenciaServicio.php';
            $servicio = new IncidenciaServicio($conexion);

            $fechaInicio = post('fech-inicio');
            $fechaFin = post('fecha_fin');

            if ($fechaInicio === '' || $fechaFin === '') {
                responder(false, 'Se requieren las fechas de inicio y fin.');
            }

            $asistencias = $servicio->obtenerAsistencias($fechaInicio, $fechaFin);
            responder(true, 'Asistencias cargadas.', ['asistencias' => $asistencias]);
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
                registrarAuditoriaDesdeSesion(
                    $conexion,
                    'Actualizó Rol',
                    "Rol ID: {$idRol}, Nuevo nombre: {$nombreRol}, Permisos: " . implode(', ', $permisosArr)
                );
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
                registrarAuditoriaDesdeSesion(
                    $conexion,
                    'Creó Rol',
                    "Rol ID: {$nuevoId}, Nombre: {$nombreRol}, Permisos: " . implode(', ', $permisosArr)
                );
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

            registrarAuditoriaDesdeSesion(
                $conexion,
                'Eliminó Rol',
                "Rol ID: {$idRol}"
            );

            responder(true, 'Rol eliminado.');
            break;

        case 'guardar_roles_sistema':
            responder(false, 'Use la acción persistir_rol o eliminar_rol_por_id.');
            break;

        default:
            responder(false, 'Acción no reconocida.');
    }
} catch (Throwable $error) {
    error_log('Error en gestion_api.php: ' . $error->getMessage());
    
    responder(false, 'Error de base de datos: ' . $error->getMessage());
}
