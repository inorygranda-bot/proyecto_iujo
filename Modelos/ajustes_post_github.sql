USE `gestion_asistencias`;

START TRANSACTION;

-- Roles base necesarios para login y gestión de usuarios
INSERT INTO `roles` (`nombre_rol`)
SELECT 'administrador'
WHERE NOT EXISTS (
    SELECT 1 FROM `roles` WHERE LOWER(`nombre_rol`) = 'administrador'
);

INSERT INTO `roles` (`nombre_rol`)
SELECT 'analista'
WHERE NOT EXISTS (
    SELECT 1 FROM `roles` WHERE LOWER(`nombre_rol`) = 'analista'
);

-- Usuario administrador base (solo si no existe)
INSERT INTO `usuarios` (`usuario`, `id_rol`, `contraseña`, `es_activo`, `ult_conexion`)
SELECT 'admin', r.`id_rol`, 'admin123', 1, NOW()
FROM `roles` r
WHERE LOWER(r.`nombre_rol`) = 'administrador'
  AND NOT EXISTS (
      SELECT 1 FROM `usuarios` u WHERE u.`usuario` = 'admin'
  )
LIMIT 1;

COMMIT;
