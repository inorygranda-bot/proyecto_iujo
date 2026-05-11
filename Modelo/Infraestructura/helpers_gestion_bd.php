<?php
declare(strict_types=1);

/**
 * Migración opcional para que la app encaje en el modelo relacional definido por el proyecto.
 */

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

/**
 * Tabla liviana sólo para calendarios e incidencias (no datos maestros de empresas/empleados).
 */
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

/**
 * Liga empleados a departamento y permite gerente nominal en departamento sin romper FK actuales.
 */
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
}

/** @return array<string, mixed> */
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
    $modulos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion'];
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
        if (!isset($calendarios[$capa]) || !is_array($calendarios[$capa])) {
            $calendarios[$capa] = [];
        }
    }

    if (!isset($calendarios['horarios']) || !is_array($calendarios['horarios'])) {
        $calendarios['horarios'] = [];
    }

    if (!isset($calendarios['horarios']['general']) || !is_array($calendarios['horarios']['general'])) {
        $calendarios['horarios']['general'] = [
            'desdeM' => '',
            'hastaM' => '',
            'desdeT' => '',
            'hastaT' => '',
        ];
    }

    foreach (['empresas', 'departamentos', 'empleados'] as $capaHorario) {
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

/**
 * Construye la estructura que consume el frontend a partir del modelo relacional.
 *
 * @return array<string, mixed>
 */
function construirDatosSistemaDesdeRelacional(PDO $conexion): array
{
    $empresas = [];
    foreach (
        $conexion->query(
            'SELECT nombre, rif_empresa AS rif, COALESCE(causa, \'\') AS causa
             FROM Empresa ORDER BY nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC)
        ?: []
        as $e
    ) {
        $empresas[] = [
            'nombre' => $e['nombre'],
            'rif' => $e['rif'],
            'causa' => $e['causa'],
        ];
    }

    $departamentos = [];
    $sqlDept = 'SELECT d.nombre_departamento AS nombre, e.nombre AS empresa,
                       COALESCE(d.causa, \'\') AS causa,
                       COALESCE(d.supervisor_nombre, \'Sin asignar\') AS supervisor
                FROM departamento d
                INNER JOIN Empresa e ON e.id_empresa = d.id_empresa
                ORDER BY e.nombre ASC, d.nombre_departamento ASC';
    foreach (($conexion->query($sqlDept)->fetchAll(PDO::FETCH_ASSOC) ?: []) as $d) {
        $departamentos[] = [
            'nombre' => $d['nombre'],
            'empresa' => $d['empresa'],
            'causa' => $d['causa'],
            'supervisor' => $d['supervisor'],
        ];
    }

    $empleados = [];
    $sqlEmp = 'SELECT em.codigo_empleado AS codigo, em.nombre AS nombres, em.apellido AS apellidos,
                     em.cedula_empleado AS cedula, em.rif_empleado AS rif, em.cargo,
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
            'empresa' => $em['empresa'],
            'depto' => $em['depto'],
            'jefe' => $em['jefe'],
        ];
    }

    $calendarios = leerJsonConfig($conexion, 'calendarios', []);
    if ($calendarios === []) {
        $calendarios = normalizarDatosSistema([])['calendarios'] ?? [];
    }

    $incidencias = leerJsonConfig($conexion, 'incidencias', []);

    /**
     * @var array<string, mixed> $compuesto
     */
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

/**
 * Lista de permisos (nombre_permisos) del usuario desde roles_permisos.
 *
 * @return list<string>
 */
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

    /** @var list<string> */
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
