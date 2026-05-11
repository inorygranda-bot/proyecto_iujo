/**
 * @file Auditorias.js
 * @description Script encargado de la lógica frontend para la visualización de la sección de Auditorías.
 *              Este archivo se encarga de leer los registros de auditoría almacenados localmente
 *              y presentarlos en una tabla dentro de la interfaz de usuario.
 *
 * @version 1.0.0
 * @date 2026-04-26
 * @author Gemini
 */

/**
 * Evento que se dispara una vez que el documento HTML ha sido completamente cargado y parseado.
 * Es crucial para asegurar que todos los elementos HTML estén disponibles en el DOM
 * antes de intentar acceder a ellos o manipularlos con JavaScript.
 */
document.addEventListener("DOMContentLoaded", () => {
    // console.log("Auditorias.js cargado."); // Mensaje de depuración para confirmar que el script se ha cargado.

    /**
     * Función principal para cargar y renderizar los registros de auditoría en la tabla.
     * Los registros se leen desde la tabla `auditorias` mediante la API.
     * y construye dinámicamente las filas de la tabla de auditorías.
     */
    async function cargarAuditorias() {
        // Obtiene la referencia al elemento <tbody> de la tabla donde se mostrarán las auditorías.
        // Su `id` es "CuerpoTablaAuditoria", un identificador claro y en español.
        const cuerpoTablaAuditoria = document.getElementById("CuerpoTablaAuditoria");

        // Inicializa un array vacío para almacenar los registros de auditoría.
        let auditorias = [];
        try {
            const respuesta = await fetch("Controlador/API/gestion_api.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                body: new URLSearchParams({ accion: "obtener_auditorias" }),
            });
            const resultado = await respuesta.json();
            if (resultado?.ok && Array.isArray(resultado?.data?.auditorias)) {
                auditorias = resultado.data.auditorias;
            }
        } catch (e) {
            console.error("Error al leer auditorías desde BD en Auditorias.js:", e);
        }

        // Verifica si el elemento del cuerpo de la tabla existe en el DOM.
        if (cuerpoTablaAuditoria) {
            // Si no hay registros de auditoría (el array está vacío),
            // se muestra un mensaje indicando que no hay datos.
            if (auditorias.length === 0) {
                // Se inserta una fila única que abarca todas las columnas (`colspan="4"`)
                // con un mensaje amigable para el usuario.
                // La clase `TablaAuditoria__vacio` se usa para aplicar estilos específicos a este mensaje.
                cuerpoTablaAuditoria.innerHTML = '<tr><td colspan="4" class="TablaAuditoria__vacio">No hay registros de auditoría.</td></tr>';
            } else {
                // Si hay registros, se ordenan cronológicamente de forma descendente (los más recientes primero).
                // `new Date(b.fecha) - new Date(a.fecha)` realiza una resta de objetos Date,
                // lo que permite ordenar del más nuevo al más antiguo.
                auditorias.sort((a, b) => new Date(b.fecha) - new Date(a.fecha));

                // Se mapea cada objeto de auditoría a una cadena HTML que representa una fila de tabla (`<tr>`).
                // Cada `<td>` corresponde a una propiedad del objeto `auditoria`:
                // `auditoria.usuario`: Nombre del usuario que realizó la acción.
                // `auditoria.accion`: Descripción breve de la acción realizada (ej. "Creó Empresa").
                // `auditoria.detalle`: Detalles adicionales de la acción (ej. "Empresa: X (RIF: Y)").
                // `auditoria.fecha`: Fecha y hora en que se registró la acción.
                // Finalmente, `.join('')` une todas las cadenas HTML de las filas en una sola cadena.
                cuerpoTablaAuditoria.innerHTML = auditorias.map(auditoria => `
                    <tr>
                        <td>${auditoria.usuario}</td>
                        <td>${auditoria.accion}</td>
                        <td>${auditoria.detalle}</td>
                        <td>${auditoria.fecha}</td>
                    </tr>
                `).join('');
            }
        }
    }

    // Llama a la función `cargarAuditorias` inmediatamente después de que el DOM esté listo
    // para mostrar los registros al cargar la página de auditorías.
    cargarAuditorias();
});
