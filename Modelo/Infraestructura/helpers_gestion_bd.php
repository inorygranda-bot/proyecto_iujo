<?php
declare(strict_types=1);

function tablaExiste(PDO $conexion, string $nombre): bool
{
    $q = $conexion->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $q->execute(['t' => $nombre]);
    return (int)$q->fetchColumn() > 0;
}

function columnaExiste(PDO $conexion, string $tabla, string $columna): bool
{
    $q = $conexion->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla AND COLUMN_NAME = :col'
    );
    $q->execute(['tabla' => $tabla, 'col' => $columna]);
    return (int)$q->fetchColumn() > 0;
}

function constraintExiste(PDO $conexion, string $constraintNombre): bool
{
    $q = $conexion->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND CONSTRAINT_NAME = :c'
    );
    $q->execute(['c' => $constraintNombre]);
    return (int)$q->fetchColumn() > 0;
}

function asegurarTablaConfiguracionApp(PDO $conexion): void
{
    if (tablaExiste($conexion, 'configuracion_app')) {
        return;
    }
    $conexion->exec(
        'CREATE TABLE configuracion_app (
            clave VARCHAR(64) NOT NULL,
            valor_json LONGTEXT NOT NULL,
            PRIMARY KEY (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migrarEsquemaAplicacionOpcional(PDO $conexion): void
{
    asegurarTablaConfiguracionApp($conexion);

    if (!columnaExiste($conexion, 'departamento', 'supervisor_nombre')) {
        $conexion->exec(
            'ALTER TABLE departamento
             ADD COLUMN supervisor_nombre VARCHAR(200) NULL DEFAULT NULL
             AFTER causa'
        );
    }

    if (!columnaExiste($conexion, 'Empleados', 'id_departamento')) {
        $conexion->exec(
            'ALTER TABLE Empleados
             ADD COLUMN id_departamento INT(10) NULL DEFAULT NULL
             AFTER id_horario'
        );
        if (!constraintExiste($conexion, 'fk_empleado_depto')) {
            try {
                $conexion->exec(
                    'ALTER TABLE Empleados
                     ADD CONSTRAINT fk_empleado_depto FOREIGN KEY (id_departamento)
                     REFERENCES departamento(id_departamento)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                error_log('Migración FK empleado->depto: ' . $e->getMessage());
            }
        }
    }

    if (!columnaExiste($conexion, 'Empleados', 'jefe_inmediato')) {
        $conexion->exec(
            'ALTER TABLE Empleados
             ADD COLUMN jefe_inmediato VARCHAR(200) NULL DEFAULT NULL
             AFTER id_departamento'
        );
    }

    // Asegurar tabla de horarios
    if (!tablaExiste($conexion, 'horarios')) {
        $conexion->exec(
            'CREATE TABLE horarios (
                id_horarios INT AUTO_INCREMENT PRIMARY KEY,
                nombre_horario VARCHAR(255) NOT NULL,
                h_entrada VARCHAR(5) NOT NULL,
                h_salida VARCHAR(5) NOT NULL,
                h_entrada_tarde VARCHAR(5) NULL,
                h_salida_tarde VARCHAR(5) NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    // Asegurar columnas h_entrada_tarde y h_salida_tarde en la tabla horarios
    if (tablaExiste($conexion, 'horarios')) {
        if (!columnaExiste($conexion, 'horarios', 'h_entrada_tarde')) {
            $conexion->exec('ALTER TABLE horarios ADD COLUMN h_entrada_tarde VARCHAR(5) NULL AFTER h_salida;');
        }
        if (!columnaExiste($conexion, 'horarios', 'h_salida_tarde')) {
            $conexion->exec('ALTER TABLE horarios ADD COLUMN h_salida_tarde VARCHAR(5) NULL AFTER h_entrada_tarde;');
        }
    }

    // Asegurar columna id_horario en Empresa
    if (!columnaExiste($conexion, 'Empresa', 'id_horario')) {
        $conexion->exec('ALTER TABLE Empresa ADD COLUMN id_horario INT NULL AFTER causa;');
        if (!constraintExiste($conexion, 'fk_empresa_horario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE Empresa
                     ADD CONSTRAINT fk_empresa_horario FOREIGN KEY (id_horario)
                     REFERENCES horarios(id_horarios)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                error_log('Migración FK empresa->horario: ' . $e->getMessage());
            }
        }
    }

    // Asegurar columna id_horario en departamento
    if (!columnaExiste($conexion, 'departamento', 'id_horario')) {
        $conexion->exec('ALTER TABLE departamento ADD COLUMN id_horario INT NULL AFTER supervisor_nombre;');
        if (!constraintExiste($conexion, 'fk_departamento_horario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE departamento
                     ADD CONSTRAINT fk_departamento_horario FOREIGN KEY (id_horario)
                     REFERENCES horarios(id_horarios)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                error_log('Migración FK departamento->horario: ' . $e->getMessage());
            }
        }
    }

    // Asegurar columna id_horario en Empleados (ya existe, pero verificar FK)
    // Asumiendo que ya existe la columna id_horario en Empleados por el problema original
    if (columnaExiste($conexion, 'Empleados', 'id_horario')) {
        if (!constraintExiste($conexion, 'fk_empleado_horario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE Empleados
                     ADD CONSTRAINT fk_empleado_horario FOREIGN KEY (id_horario)
                     REFERENCES horarios(id_horarios)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                error_log('Migración FK empleado->horario: ' . $e->getMessage());
            }
        }
    }

    if (!tablaExiste($conexion, 'auditorias')) {
        $conexion->exec(
            'CREATE TABLE auditorias (
                id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                accion VARCHAR(255) NOT NULL,
                descripcion TEXT NOT NULL,
                fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_auditoria_fecha (fecha_hora),
                INDEX idx_auditoria_usuario (id_usuario)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        if (tablaExiste($conexion, 'usuarios') && !constraintExiste($conexion, 'fk_auditoria_usuario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE auditorias
                     ADD CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario)
                     REFERENCES usuarios(id_usuario)
                     ON DELETE CASCADE ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                error_log('Migración FK auditoria->usuario: ' . $e->getMessage());
            }
        }
    }

    // Asegurar que exista un horario general por defecto
    $st = $conexion->prepare('SELECT COUNT(*) FROM horarios WHERE nombre_horario = :n');
    $st->execute(['n' => 'general']);
    if ((int)$st->fetchColumn() === 0) {
        $ins = $conexion->prepare(
            'INSERT INTO horarios (nombre_horario, h_entrada, h_salida, h_entrada_tarde, h_salida_tarde)
             VALUES (:nombre, :he, :hs, :het, :hst)'
        );
        $ins->execute([
            'nombre' => 'general',
            'he' => '08:00',
            'hs' => '12:00',
            'het' => '13:00',
            'hst' => '17:00',
        ]);
    }
}

function leerJsonConfig(PDO $conexion, string $clave, array $fallback = []): array
{
    $st = $conexion->prepare('SELECT valor_json FROM configuracion_app WHERE clave = :c LIMIT 1');
    $st->execute(['c' => $clave]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        return $fallback;
    }
    $j = json_decode((string)$fila['valor_json'], true);
    return is_array($j) ? $j : $fallback;
}

/**
 * Guarda una configuración en formato JSON en la tabla configuracion_app.
 * Si la clave ya existe, actualiza su valor; si no, la inserta.
 *
 * @param PDO $conexion Conexión a la base de datos
 * @param string $clave Clave de la configuración
 * @param array $valor Datos a guardar (serán serializados a JSON)
 * @return void
 * @throws RuntimeException Si no se puede serializar el JSON
 */
function guardarJsonConfig(PDO $conexion, string $clave, array $valor): void
{
    $json = json_encode($valor, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar JSON.');
    }
    $st = $conexion->prepare(
        'INSERT INTO configuracion_app (clave, valor_json) VALUES (:c, :v)
         ON DUPLICATE KEY UPDATE valor_json = VALUES(valor_json)'
    );
    $st->execute(['c' => $clave, 'v' => $json]);
}

/**
 * Siembra permisos reutilizados por pantallas JS (nombre = clave del módulo).
 */
function asegurarPermisosModulos(PDO $conexion): void
{
    $modulos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion', 'auditorias'];
    foreach ($modulos as $m) {
        $st = $conexion->prepare('SELECT id_permisos FROM permisos WHERE nombre_permisos = :n LIMIT 1');
        $st->execute(['n' => $m]);
        if (!$st->fetch()) {
            $ins = $conexion->prepare('INSERT INTO permisos (nombre_permisos, descripcion) VALUES (:n, :d)');
            $ins->execute(['n' => $m, 'd' => 'Acceso al módulo ' . $m]);
        }
    }
}

/** @param array<string, mixed> $datos */
function normalizarDatosSistema(array $datos): array
{
    $resultado = $datos;

    foreach (['empresas', 'departamentos', 'empleados', 'usuarios', 'incidencias'] as $coleccion) {
        if (!isset($resultado[$coleccion]) || !is_array($resultado[$coleccion])) {
            $resultado[$coleccion] = [];
        }
    }

    if (!isset($resultado['calendarios']) || !is_array($resultado['calendarios'])) {
        $resultado['calendarios'] = [];
    }

    $calendarios = $resultado['calendarios'];
    foreach (['general', 'empresas', 'departamentos', 'empleados'] as $capa) {
        // Convertir objetos stdClass a arrays si es necesario
        if (isset($calendarios[$capa]) && $calendarios[$capa] instanceof \stdClass) {
            $calendarios[$capa] = (array)$calendarios[$capa];
        }
        if (!isset($calendarios[$capa]) || !is_array($calendarios[$capa])) {
            $calendarios[$capa] = [];
        }
    }

    if (!isset($calendarios['horarios']) || !is_array($calendarios['horarios'])) {
        $calendarios['horarios'] = [];
    }

    // Convertir horarios de stdClass a array si es necesario
    if ($calendarios['horarios'] instanceof \stdClass) {
        $calendarios['horarios'] = (array)$calendarios['horarios'];
    }

    if (!isset($calendarios['horarios']['general']) || !is_array($calendarios['horarios']['general'])) {
        $calendarios['horarios']['general'] = [
            'desdeM' => '',
            'hastaM' => '',
            'desdeT' => '',
            'hastaT' => '',
        ];
    }

    // Convertir objetos stdClass a arrays asociativos
    
    foreach (['empresas', 'departamentos', 'empleados'] as $capaHorario) {
        // Si es un objeto stdClass, convertirlo a array
        if (isset($calendarios['horarios'][$capaHorario]) && $calendarios['horarios'][$capaHorario] instanceof \stdClass) {
            error_log("normalizarDatosSistema: Convertir {$capaHorario} de stdClass a array");
            $calendarios['horarios'][$capaHorario] = (array)$calendarios['horarios'][$capaHorario];
        }
        
        // Si no existe o es un array secuencial, inicializarlo como array asociativo vacío
        if (!isset($calendarios['horarios'][$capaHorario]) || !is_array($calendarios['horarios'][$capaHorario])) {
            $calendarios['horarios'][$capaHorario] = [];
        }
    }

    foreach (['horariosFecha', 'horariosSemana', 'feriadosSemana'] as $claveCalendario) {
        if (!isset($calendarios[$claveCalendario]) || !is_array($calendarios[$claveCalendario])) {
            $calendarios[$claveCalendario] = [];
        }

        foreach (['general', 'empresas', 'departamentos', 'empleados'] as $capa) {
            if (
                !isset($calendarios[$claveCalendario][$capa])
                || !is_array($calendarios[$claveCalendario][$capa])
            ) {
                $calendarios[$claveCalendario][$capa] = [];
            }
        }
    }

    $resultado['calendarios'] = $calendarios;
    return $resultado;
}

function construirDatosSistemaDesdeRelacional(PDO $conexion): array
{
    // Cargar lista de empresas
    $empresas = [];
    foreach (
        $conexion->query(
            'SELECT nombre, rif_empresa AS rif, COALESCE(causa, \'\') AS causa, id_horario
             FROM Empresa ORDER BY nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC)
        ?: []
        as $e
    ) {
        $empresas[] = [
            'nombre' => $e['nombre'],
            'rif' => $e['rif'],
            'causa' => $e['causa'],
            'id_horario' => $e['id_horario'],
        ];
    }

    // Cargar lista de departamentos
    $departamentos = [];
    $sqlDept = 'SELECT d.nombre_departamento AS nombre, e.nombre AS empresa,
                       COALESCE(d.causa, \'\') AS causa,
                       COALESCE(d.supervisor_nombre, \'Sin asignar\') AS supervisor,
                       d.id_horario
                FROM departamento d
                INNER JOIN Empresa e ON e.id_empresa = d.id_empresa
                ORDER BY e.nombre ASC, d.nombre_departamento ASC';
    foreach (($conexion->query($sqlDept)->fetchAll(PDO::FETCH_ASSOC) ?: []) as $d) {
        $departamentos[] = [
            'nombre' => $d['nombre'],
            'empresa' => $d['empresa'],
            'causa' => $d['causa'],
            'supervisor' => $d['supervisor'],
            'id_horario' => $d['id_horario'],
        ];
    }

    // Cargar lista de empleados
    $empleados = [];
    $sqlEmp = 'SELECT em.codigo_empleado AS codigo, em.nombre AS nombres, em.apellido AS apellidos,
                     em.cedula_empleado AS cedula, em.rif_empleado AS rif, em.cargo,
                     em.es_supervisor AS es_supervisor,
                     em.id_horario,
                     COALESCE(e.nombre, \'\') AS empresa,
                     COALESCE(d.nombre_departamento, \'\') AS depto,
                     COALESCE(em.jefe_inmediato, \'Sin asignar\') AS jefe
               FROM Empleados em
               LEFT JOIN departamento d ON d.id_departamento = em.id_departamento
               LEFT JOIN Empresa e ON e.id_empresa = d.id_empresa
               ORDER BY em.apellido ASC, em.nombre ASC';
    foreach (($conexion->query($sqlEmp)->fetchAll(PDO::FETCH_ASSOC) ?: []) as $em) {
        $empleados[] = [
            'codigo' => $em['codigo'],
            'nombres' => $em['nombres'],
            'apellidos' => $em['apellidos'],
            'cedula' => $em['cedula'],
            'rif' => $em['rif'],
            'cargo' => $em['cargo'],
            'id_horario' => $em['id_horario'],
            'empresa' => $em['empresa'],
            'depto' => $em['depto'],
            'jefe' => $em['jefe'],
        ];
    }

    // ========================================================================
    // PASO 2: Cargar datos de configuración en JSON (calendarios, incidencias)
    // ========================================================================

    // Cargar calendarios desde la configuración JSON
    $calendarios = leerJsonConfig($conexion, 'calendarios', []);

    // ========================================================================
    // PASO 3: Cargar horarios desde la tabla relacional 'horarios'
    // ========================================================================

    // Estructura base para los horarios
    $horariosCargados = [
        'general' => [
            'desdeM' => '',
            'hastaM' => '',
            'desdeT' => '',
            'hastaT' => '',
        ],
        'empresas' => [],
        'departamentos' => [],
        'empleados' => [],
    ];

    // Cargar horario general
    $stGeneral = $conexion->prepare('SELECT id_horarios FROM horarios WHERE nombre_horario = :n LIMIT 1');
    $stGeneral->execute(['n' => 'general']);
    $idHorarioGeneral = $stGeneral->fetchColumn();
    if ($idHorarioGeneral !== false) {
        $h = obtenerHorarioPorId($conexion, (int)$idHorarioGeneral);
        if ($h) {
            $horariosCargados['general'] = $h;
        }
    }

    // Cargar horarios de empresas
    $stEmpresas = $conexion->query('SELECT nombre, id_horario FROM Empresa');
    foreach ($stEmpresas->fetchAll(PDO::FETCH_ASSOC) as $emp) {
        if ($emp['id_horario']) {
            $h = obtenerHorarioPorId($conexion, (int)$emp['id_horario']);
            if ($h) {
                $horariosCargados['empresas'][$emp['nombre']] = $h;
            }
        }
    }

    // Cargar horarios de departamentos
    $stDepartamentos = $conexion->query('SELECT nombre_departamento, id_horario FROM departamento');
    foreach ($stDepartamentos->fetchAll(PDO::FETCH_ASSOC) as $dep) {
        if ($dep['id_horario']) {
            $h = obtenerHorarioPorId($conexion, (int)$dep['id_horario']);
            if ($h) {
                $horariosCargados['departamentos'][$dep['nombre_departamento']] = $h;
            }
        }
    }

    // Cargar horarios de empleados
    $stEmpleados = $conexion->query('SELECT cedula_empleado, id_horario FROM Empleados');
    foreach ($stEmpleados->fetchAll(PDO::FETCH_ASSOC) as $empl) {
        if ($empl['id_horario']) {
            $h = obtenerHorarioPorId($conexion, (int)$empl['id_horario']);
            if ($h) {
                $horariosCargados['empleados'][$empl['cedula_empleado']] = $h;
            }
        }
    }

    // ========================================================================
    // PASO 4: Fusionar datos relacionales con datos JSON
    // ========================================================================

    // Fusionar los horarios cargados desde la BD relacional con los del JSON
    if (!isset($calendarios['horarios']) || !is_array($calendarios['horarios'])) {
        $calendarios['horarios'] = [];
    }
    $calendarios['horarios'] = array_replace_recursive($calendarios['horarios'], $horariosCargados);

    // Si no hay calendarios, usar la estructura normalizada por defecto
    if ($calendarios === []) {
        $calendarios = normalizarDatosSistema([])['calendarios'] ?? [];
    }

    // Cargar incidencias desde la configuración JSON
    $incidencias = leerJsonConfig($conexion, 'incidencias', []);

    // ========================================================================
    // PASO 5: Componer la estructura final y normalizarla
    // ========================================================================

    $compuesto = [
        'empresas' => $empresas,
        'departamentos' => $departamentos,
        'empleados' => $empleados,
        'usuarios' => [],
        'incidencias' => $incidencias,
        'calendarios' => $calendarios,
    ];

    return normalizarDatosSistema($compuesto);
}

/**
 * Obtiene lista de roles con permisos para la UI de Gestión de usuarios.
 *
 * @return list<array{id:string,nombre:string,permisos:list<string>}>
 */
function obtenerRolesClienteDesdeBd(PDO $conexion): array
{
    asegurarPermisosModulos($conexion);

    $sql = 'SELECT r.id_rol AS id, r.nombre_rol AS nombre,
                   GROUP_CONCAT(p.nombre_permisos ORDER BY p.nombre_permisos SEPARATOR \',\') AS perm_csv
            FROM roles r
            LEFT JOIN roles_permisos rp ON rp.id_rol = r.id_rol
            LEFT JOIN permisos p ON p.id_permisos = rp.id_permisos
            GROUP BY r.id_rol, r.nombre_rol
            ORDER BY r.nombre_rol ASC';

    /** @var list<array<string, mixed>> $filas */
    $filas = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];

    foreach ($filas as $row) {
        $permCsv = (string)($row['perm_csv'] ?? '');
        $mods = [];
        if ($permCsv !== '') {
            foreach (explode(',', $permCsv) as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $mods[] = $p;
                }
            }
        }
        $out[] = [
            'id' => (string)$row['id'],
            'nombre' => (string)$row['nombre'],
            'permisos' => $mods,
        ];
    }

    return $out;
}

function idPermisoPorNombre(PDO $conexion, string $nombre): ?int
{
    $st = $conexion->prepare('SELECT id_permisos FROM permisos WHERE nombre_permisos = :n LIMIT 1');
    $st->execute(['n' => $nombre]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f) {
        return null;
    }
    return (int)$f['id_permisos'];
}

/** @param list<string> $modulosPermitidos */
function guardarRolesPermisosParaRol(PDO $conexion, int $idRol, array $modulosPermitidos): void
{
    asegurarPermisosModulos($conexion);
    $conexion->prepare('DELETE FROM roles_permisos WHERE id_rol = :idr')->execute(['idr' => $idRol]);

    $insRp = $conexion->prepare('INSERT INTO roles_permisos (id_rol, id_permisos) VALUES (:r, :p)');
    foreach ($modulosPermitidos as $mod) {
        $mod = trim((string)$mod);
        if ($mod === '') {
            continue;
        }
        $idp = idPermisoPorNombre($conexion, $mod);
        if ($idp === null) {
            continue;
        }
        $insRp->execute(['r' => $idRol, 'p' => $idp]);
    }
}

function idUsuarioPorLogin(PDO $conexion, string $usuario): ?int
{
    $st = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE usuario = :u LIMIT 1');
    $st->execute(['u' => $usuario]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    return $f ? (int)$f['id_usuario'] : null;
}

function obtenerHorarioPorId(PDO $conexion, int $idHorario): ?array
{
    $stmt = $conexion->prepare(
        'SELECT h_entrada, h_salida, h_entrada_tarde, h_salida_tarde
         FROM horarios WHERE id_horarios = :id LIMIT 1'
    );
    $stmt->execute(['id' => $idHorario]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        return null;
    }

    // Convertir los nombres de columnas de la BD al formato del frontend
    return [
        'desdeM' => $fila['h_entrada'],      // Hora de entrada de la mañana
        'hastaM' => $fila['h_salida'],      // Hora de salida de la mañana
        'desdeT' => $fila['h_entrada_tarde'],// Hora de entrada de la tarde
        'hastaT' => $fila['h_salida_tarde'],// Hora de salida de la tarde
    ];
}

function obtenerModulosUsuario(PDO $conexion, string $usuarioLogin): array
{
    asegurarPermisosModulos($conexion);

    $sql = 'SELECT DISTINCT p.nombre_permisos
            FROM usuarios u
            INNER JOIN roles_permisos rp ON rp.id_rol = u.id_rol
            INNER JOIN permisos p ON p.id_permisos = rp.id_permisos
            WHERE u.usuario = :u';

    $st = $conexion->prepare($sql);
    $st->execute(['u' => $usuarioLogin]);

    $out = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $out[] = (string)$row['nombre_permisos'];
    }
    return $out;
}

function rolEsAdministrador(string $nombreRolNombre): bool
{
    return (bool)preg_match('/admin/i', $nombreRolNombre);
}
