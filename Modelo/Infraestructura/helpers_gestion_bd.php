<?php
// ===================================================================================================================
// Descripción: Este archivo contiene funciones de ayuda para la gestión de la base de datos, incluyendo:
//              - Verificación de existencia de tablas, columnas y constraints.
//              - Migraciones de esquema para asegurar la estructura de la base de datos.
//              - Funciones para leer y guardar configuraciones JSON en la base de datos.
//              - Funciones para asegurar la existencia de permisos y normalizar datos del sistema.
// ===================================================================================================================

declare(strict_types=1); // Forzar el uso de tipos estrictos para mejorar la calidad del código.

// ===================================================================================================================
// Funciones de Verificación de Esquema de Base de Datos
// Estas funciones permiten comprobar la existencia de elementos específicos en el esquema de la base de datos.
// ===================================================================================================================

/**
 * Verifica si una tabla existe en la base de datos actual.
 *
 * @param PDO $conexion Conexión PDO a la base de datos.
 * @param string $nombre Nombre de la tabla a verificar.
 * @return bool Retorna true si la tabla existe, false en caso contrario.
 */
function tablaExiste(PDO $conexion, string $nombre): bool
{
    // Preparar una consulta para buscar la tabla en information_schema.
    $q = $conexion->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $q->execute(['t' => $nombre]); // Ejecutar la consulta con el nombre de la tabla.
    return (int)$q->fetchColumn() > 0; // Retornar true si se encuentra al menos una fila (la tabla existe).
}

/**
 * Verifica si una columna existe en una tabla específica de la base de datos actual.
 *
 * @param PDO $conexion Conexión PDO a la base de datos.
 * @param string $tabla Nombre de la tabla.
 * @param string $columna Nombre de la columna a verificar.
 * @return bool Retorna true si la columna existe, false en caso contrario.
 */
function columnaExiste(PDO $conexion, string $tabla, string $columna): bool
{
    // Preparar una consulta para buscar la columna en information_schema.
    $q = $conexion->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :tabla AND COLUMN_NAME = :col'
    );
    // Ejecutar la consulta con el nombre de la tabla y la columna.
    $q->execute(['tabla' => $tabla, 'col' => $columna]);
    return (int)$q->fetchColumn() > 0; // Retornar true si se encuentra al menos una fila (la columna existe).
}

/**
 * Verifica si una foreign key constraint existe en la base de datos actual.
 *
 * @param PDO $conexion Conexión PDO a la base de datos.
 * @param string $constraintNombre Nombre de la constraint a verificar.
 * @return bool Retorna true si la constraint existe, false en caso contrario.
 */
function constraintExiste(PDO $conexion, string $constraintNombre): bool
{
    // Preparar una consulta para buscar la constraint en information_schema.
    $q = $conexion->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND CONSTRAINT_NAME = :c'
    );
    $q->execute(['c' => $constraintNombre]); // Ejecutar la consulta con el nombre de la constraint.
    return (int)$q->fetchColumn() > 0; // Retornar true si se encuentra al menos una fila (la constraint existe).
}

// ===================================================================================================================
// Función: asegurarTablaConfiguracionApp()
// Descripción: Asegura que la tabla `configuracion_app` exista en la base de datos.
//              Si no existe, la crea con las columnas `clave` y `valor_json`.
// ===================================================================================================================
function asegurarTablaConfiguracionApp(PDO $conexion): void
{
    // Verificar si la tabla ya existe para evitar errores al intentar crearla de nuevo.
    if (tablaExiste($conexion, 'configuracion_app')) {
        return;
    }
    // Si la tabla no existe, ejecutar la consulta para crearla.
    $conexion->exec(
        'CREATE TABLE configuracion_app (
            clave VARCHAR(64) NOT NULL,
            valor_json LONGTEXT NOT NULL,
            PRIMARY KEY (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

// ===================================================================================================================
// Función: migrarEsquemaAplicacionOpcional()
// Descripción: Realiza migraciones de esquema de base de datos de forma opcional.
//              Comprueba la existencia de tablas y columnas y las crea/modifica si es necesario.
//              Esto permite que la aplicación se adapte a cambios en el esquema con el tiempo.
// ===================================================================================================================
function migrarEsquemaAplicacionOpcional(PDO $conexion): void
{
    // Asegurar que la tabla de configuración de la aplicación exista antes de otras migraciones.
    asegurarTablaConfiguracionApp($conexion);

    // Migración: Añadir columna `supervisor_nombre` a la tabla `departamento`.
    if (!columnaExiste($conexion, 'departamento', 'supervisor_nombre')) {
        $conexion->exec(
            'ALTER TABLE departamento
             ADD COLUMN supervisor_nombre VARCHAR(200) NULL DEFAULT NULL
             AFTER causa'
        );
    }

    // Migración: Añadir columna `id_departamento` a la tabla `Empleados` y su foreign key.
    if (!columnaExiste($conexion, 'Empleados', 'id_departamento')) {
        $conexion->exec(
            'ALTER TABLE Empleados
             ADD COLUMN id_departamento INT(10) NULL DEFAULT NULL
             AFTER id_horario'
        );
        // Asegurar que la foreign key `fk_empleado_depto` exista.
        if (!constraintExiste($conexion, 'fk_empleado_depto')) {
            try {
                $conexion->exec(
                    'ALTER TABLE Empleados
                     ADD CONSTRAINT fk_empleado_depto FOREIGN KEY (id_departamento)
                     REFERENCES departamento(id_departamento)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                // Registrar cualquier error durante la creación de la FK.
                error_log('Migración FK empleado->depto: ' . $e->getMessage());
            }
        }
    }

    // Migración: Añadir columna `jefe_inmediato` a la tabla `Empleados`.
    if (!columnaExiste($conexion, 'Empleados', 'jefe_inmediato')) {
        $conexion->exec(
            'ALTER TABLE Empleados
             ADD COLUMN jefe_inmediato VARCHAR(200) NULL DEFAULT NULL
             AFTER id_departamento'
        );
    }

    // Migración: Asegurar que la tabla `horarios` exista.
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

    // Migración: Asegurar columnas `h_entrada_tarde` y `h_salida_tarde` en la tabla `horarios`.
    if (tablaExiste($conexion, 'horarios')) {
        if (!columnaExiste($conexion, 'horarios', 'h_entrada_tarde')) {
            $conexion->exec('ALTER TABLE horarios ADD COLUMN h_entrada_tarde VARCHAR(5) NULL AFTER h_salida;');
        }
        if (!columnaExiste($conexion, 'horarios', 'h_salida_tarde')) {
            $conexion->exec('ALTER TABLE horarios ADD COLUMN h_salida_tarde VARCHAR(5) NULL AFTER h_entrada_tarde;');
        }
    }

    // Migración: Asegurar columna `id_horario` en la tabla `Empresa` y su foreign key.
    if (!columnaExiste($conexion, 'Empresa', 'id_horario')) {
        $conexion->exec('ALTER TABLE Empresa ADD COLUMN id_horario INT NULL AFTER causa;');
        // Asegurar que la foreign key `fk_empresa_horario` exista.
        if (!constraintExiste($conexion, 'fk_empresa_horario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE Empresa
                     ADD CONSTRAINT fk_empresa_horario FOREIGN KEY (id_horario)
                     REFERENCES horarios(id_horarios)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                // Registrar cualquier error durante la creación de la FK.
                error_log('Migración FK empresa->horario: ' . $e->getMessage());
            }
        }
    }

    // Migración: Añadir columna `empresas_asignadas` a la tabla `usuarios`.
    if (!columnaExiste($conexion, 'usuarios', 'empresas_asignadas')) {
        $conexion->exec('ALTER TABLE usuarios ADD COLUMN empresas_asignadas LONGTEXT NULL AFTER id_rol;');
    }

    // Migración: Asegurar columna `id_horario` en la tabla `departamento` y su foreign key.
    if (!columnaExiste($conexion, 'departamento', 'id_horario')) {
        $conexion->exec('ALTER TABLE departamento ADD COLUMN id_horario INT NULL AFTER supervisor_nombre;');
        // Asegurar que la foreign key `fk_departamento_horario` exista.
        if (!constraintExiste($conexion, 'fk_departamento_horario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE departamento
                     ADD CONSTRAINT fk_departamento_horario FOREIGN KEY (id_horario)
                     REFERENCES horarios(id_horarios)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                // Registrar cualquier error durante la creación de la FK.
                error_log('Migración FK departamento->horario: ' . $e->getMessage());
            }
        }
    }

    // Migración: Asegurar la foreign key `fk_empleado_horario` en la tabla `Empleados`.
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
                // Registrar cualquier error durante la creación de la FK.
                error_log('Migración FK empleado->horario: ' . $e->getMessage());
            }
        }
    }

    // Migración: Asegurar que la tabla `auditorias` exista.
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
        // Si la tabla `usuarios` existe y la foreign key `fk_auditoria_usuario` no, crearla.
        if (tablaExiste($conexion, 'usuarios') && !constraintExiste($conexion, 'fk_auditoria_usuario')) {
            try {
                $conexion->exec(
                    'ALTER TABLE auditorias
                     ADD CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario)
                     REFERENCES usuarios(id_usuario)
                     ON DELETE CASCADE ON UPDATE CASCADE'
                );
            } catch (Throwable $e) {
                // Registrar cualquier error durante la creación de la FK.
                error_log('Migración FK auditoria->usuario: ' . $e->getMessage());
            }
        }
    }

    // Asegurar que exista un horario general por defecto en la tabla `horarios`.
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

// ===================================================================================================================
// Funciones de Gestión de Configuración JSON
// Estas funciones permiten almacenar y recuperar datos estructurados (JSON) en la tabla `configuracion_app`.
// ===================================================================================================================

/**
 * Lee una configuración JSON de la tabla `configuracion_app`.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @param string $clave Clave de la configuración a leer.
 * @param array $fallback Valor de retorno por defecto si la clave no se encuentra o el JSON es inválido.
 * @return array Retorna la configuración como un array asociativo, o el valor de fallback.
 */
function leerJsonConfig(PDO $conexion, string $clave, array $fallback = []): array
{
    // Preparar y ejecutar la consulta para obtener el valor JSON.
    $st = $conexion->prepare('SELECT valor_json FROM configuracion_app WHERE clave = :c LIMIT 1');
    $st->execute(['c' => $clave]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    
    // Si no se encuentra la fila, retornar el valor de fallback.
    if (!$fila) {
        return $fallback;
    }
    
    // Decodificar el valor JSON. El segundo parámetro `true` lo convierte a array asociativo.
    $j = json_decode((string)$fila['valor_json'], true);
    // Si la decodificación es exitosa y el resultado es un array, retornarlo; de lo contrario, retornar fallback.
    return is_array($j) ? $j : $fallback;
}

/**
 * Guarda una configuración en formato JSON en la tabla configuracion_app.
 * Si la clave ya existe, actualiza su valor; si no, la inserta.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @param string $clave Clave de la configuración.
 * @param array $valor Datos a guardar (serán serializados a JSON).
 * @return void
 * @throws RuntimeException Si no se puede serializar el JSON.
 */
function guardarJsonConfig(PDO $conexion, string $clave, array $valor): void
{
    // Codificar el array de valor a formato JSON.
    $json = json_encode($valor, JSON_UNESCAPED_UNICODE); // JSON_UNESCAPED_UNICODE para evitar que los caracteres UTF-8 se escapen.
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar JSON.');
    }
    // Preparar y ejecutar la consulta para insertar o actualizar la configuración.
    // `ON DUPLICATE KEY UPDATE` permite insertar si no existe o actualizar si la clave ya está presente.
    $st = $conexion->prepare(
        'INSERT INTO configuracion_app (clave, valor_json) VALUES (:c, :v)
         ON DUPLICATE KEY UPDATE valor_json = VALUES(valor_json)'
    );
    $st->execute(['c' => $clave, 'v' => $json]);
}

// ===================================================================================================================
// Funciones de Gestión de Permisos
// ===================================================================================================================

/**
 * Siembra permisos reutilizados por pantallas JS (nombre = clave del módulo).
 * Asegura que existan entradas de permisos para los módulos de la aplicación.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @return void
 */
function asegurarPermisosModulos(PDO $conexion): void
{
    // Definir los módulos de la aplicación que requieren permisos.
    $modulos = ['registro', 'consulta', 'horarios', 'reportes', 'gestion', 'auditorias'];
    foreach ($modulos as $m) {
        // Verificar si el permiso para el módulo ya existe.
        $st = $conexion->prepare('SELECT id_permisos FROM permisos WHERE nombre_permisos = :n LIMIT 1');
        $st->execute(['n' => $m]);
        // Si el permiso no existe, insertarlo en la tabla `permisos`.
        if (!$st->fetch()) {
            $ins = $conexion->prepare('INSERT INTO permisos (nombre_permisos, descripcion) VALUES (:n, :d)');
            $ins->execute(['n' => $m, 'd' => 'Acceso al módulo ' . $m]);
        }
    }
}

// ===================================================================================================================
// Funciones de Normalización y Construcción de Datos del Sistema
// Estas funciones ayudan a estructurar y preparar los datos para ser utilizados por la aplicación.
// ===================================================================================================================

/**
 * Normaliza la estructura de un array de datos del sistema, asegurando que existan ciertas claves
 * y que los tipos de datos sean consistentes (ej. arrays en lugar de stdClass).
 *
 * @param array<string, mixed> $datos Array de datos del sistema a normalizar.
 * @return array Retorna el array de datos normalizado.
 */
function normalizarDatosSistema(array $datos): array
{
    $resultado = $datos;

    // Asegurar que las colecciones principales sean arrays.
    foreach (['empresas', 'departamentos', 'empleados', 'usuarios', 'incidencias'] as $coleccion) {
        if (!isset($resultado[$coleccion]) || !is_array($resultado[$coleccion])) {
            $resultado[$coleccion] = [];
        }
    }

    // Asegurar la estructura de 'calendarios'.
    if (!isset($resultado['calendarios']) || !is_array($resultado['calendarios'])) {
        $resultado['calendarios'] = [];
    }

    $calendarios = $resultado['calendarios'];
    // Asegurar que las capas de calendario (general, empresas, etc.) sean arrays.
    foreach (['general', 'empresas', 'departamentos', 'empleados'] as $capa) {
        // Convertir objetos stdClass a arrays si es necesario (manejo de datos deserializados de JSON).
        if (isset($calendarios[$capa]) && $calendarios[$capa] instanceof \stdClass) {
            $calendarios[$capa] = (array)$calendarios[$capa];
        }
        if (!isset($calendarios[$capa]) || !is_array($calendarios[$capa])) {
            $calendarios[$capa] = [];
        }
    }

    // Asegurar la estructura de 'horarios' dentro de 'calendarios'.
    if (!isset($calendarios['horarios']) || !is_array($calendarios['horarios'])) {
        $calendarios['horarios'] = [];
    }

    // Convertir 'horarios' de stdClass a array si es necesario.
    if ($calendarios['horarios'] instanceof \stdClass) {
        $calendarios['horarios'] = (array)$calendarios['horarios'];
    }

    // Asegurar la estructura del horario 'general'.
    if (!isset($calendarios['horarios']['general']) || !is_array($calendarios['horarios']['general'])) {
        $calendarios['horarios']['general'] = [
            'desdeM' => '',
            'hastaM' => '',
            'desdeT' => '',
            'hastaT' => '',
        ];
    }

    // Normalizar las capas de horarios (empresas, departamentos, empleados).
    foreach (['empresas', 'departamentos', 'empleados'] as $capaHorario) {
        // Si es un objeto stdClass, convertirlo a array.
        if (isset($calendarios['horarios'][$capaHorario]) && $calendarios['horarios'][$capaHorario] instanceof \stdClass) {
            error_log("normalizarDatosSistema: Convertir {$capaHorario} de stdClass a array");
            $calendarios['horarios'][$capaHorario] = (array)$calendarios['horarios'][$capaHorario];
        }
        
        // Si no existe o es un array secuencial, inicializarlo como array asociativo vacío.
        if (!isset($calendarios['horarios'][$capaHorario]) || !is_array($calendarios['horarios'][$capaHorario])) {
            $calendarios['horarios'][$capaHorario] = [];
        }
    }

    // Asegurar la estructura de otras claves de calendario.
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
 * Construye un array completo de datos del sistema a partir de la base de datos relacional
 * y configuraciones JSON.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @return array Retorna un array con todos los datos del sistema estructurados.
 */
function construirDatosSistemaDesdeRelacional(PDO $conexion): array
{
    // ========================================================================
    // PASO 1: Cargar datos relacionales (Empresas, Departamentos, Empleados)
    // ========================================================================

    // Cargar lista de empresas desde la tabla `Empresa`.
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

    // Cargar lista de departamentos desde la tabla `departamento`.
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

    // Cargar lista de empleados desde la tabla `Empleados`.
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

    // Cargar configuraciones de calendarios desde la tabla `configuracion_app`.
    $calendarios = leerJsonConfig($conexion, 'calendarios', []);

    // ========================================================================
    // PASO 3: Cargar horarios desde la tabla relacional 'horarios'
    // ========================================================================

    // Estructura base para almacenar los horarios cargados desde la base de datos relacional.
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

    // Cargar el horario general (si existe).
    $stGeneral = $conexion->prepare('SELECT id_horarios FROM horarios WHERE nombre_horario = :n LIMIT 1');
    $stGeneral->execute(['n' => 'general']);
    $idHorarioGeneral = $stGeneral->fetchColumn();
    if ($idHorarioGeneral !== false) {
        // Suponiendo que existe una función `obtenerHorarioPorId` que devuelve el horario en formato array.
        $h = obtenerHorarioPorId($conexion, (int)$idHorarioGeneral);
        if ($h) {
            $horariosCargados['general'] = $h;
        }
    }

    // Cargar horarios específicos de empresas.
    $stEmpresas = $conexion->query('SELECT nombre, id_horario FROM Empresa');
    foreach ($stEmpresas->fetchAll(PDO::FETCH_ASSOC) as $emp) {
        if ($emp['id_horario']) {
            $h = obtenerHorarioPorId($conexion, (int)$emp['id_horario']);
            if ($h) {
                $horariosCargados['empresas'][$emp['nombre']] = $h;
            }
        }
    }

    // Cargar horarios específicos de departamentos.
    $stDepartamentos = $conexion->query('SELECT nombre_departamento, id_horario FROM departamento');
    foreach ($stDepartamentos->fetchAll(PDO::FETCH_ASSOC) as $dep) {
        if ($dep['id_horario']) {
            $h = obtenerHorarioPorId($conexion, (int)$dep['id_horario']);
            if ($h) {
                $horariosCargados['departamentos'][$dep['nombre_departamento']] = $h;
            }
        }
    }

    // Cargar horarios específicos de empleados.
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

    // Fusionar los horarios cargados desde la base de datos relacional con los que puedan existir en la configuración JSON.
    if (!isset($calendarios['horarios']) || !is_array($calendarios['horarios'])) {
        $calendarios['horarios'] = [];
    }
    $calendarios['horarios'] = array_replace_recursive($calendarios['horarios'], $horariosCargados);

    // Si después de la fusión no hay datos de calendarios, se usa una estructura normalizada por defecto.
    if ($calendarios === []) {
        $calendarios = normalizarDatosSistema([])['calendarios'] ?? [];
    }

    // Cargar configuraciones de incidencias desde la tabla `configuracion_app`.
    $incidencias = leerJsonConfig($conexion, 'incidencias', []);

    // ========================================================================
    // PASO 5: Componer la estructura final y normalizarla
    // ========================================================================

    // Construir el array final de datos del sistema.
    $compuesto = [
        'empresas' => $empresas,
        'departamentos' => $departamentos,
        'empleados' => $empleados,
        // Aquí irían otros datos relacionales como usuarios, si se cargaran directamente aquí.
        'calendarios' => $calendarios,
        'incidencias' => $incidencias,
    ];

    // Normalizar la estructura completa de los datos del sistema para asegurar consistencia.
    return normalizarDatosSistema($compuesto);
}

/**
 * Obtiene los detalles de un horario específico por su ID.
 * Esta función es un helper para `construirDatosSistemaDesdeRelacional`.
 *
 * @param PDO $conexion Conexión a la base de datos.
 * @param int $idHorario ID del horario a buscar.
 * @return array|null Retorna un array con los detalles del horario o null si no se encuentra.
 */
function obtenerHorarioPorId(PDO $conexion, int $idHorario): ?array
{
    $st = $conexion->prepare(
        'SELECT h_entrada AS desdeM, h_salida AS hastaM, h_entrada_tarde AS desdeT, h_salida_tarde AS hastaT
         FROM horarios WHERE id_horarios = :id LIMIT 1'
    );
    $st->execute(['id' => $idHorario]);
    $horario = $st->fetch(PDO::FETCH_ASSOC);
    
    // Si no se encuentra el horario, retornar null; de lo contrario, retornar el array.
    return $horario ?: null;
}
