function reportesAsegurarIncidencias(datos) {
    // Si no existe el array de incidencias, lo inicializamos como vacío para evitar errores posteriores.
    if (!Array.isArray(datos.incidencias)) datos.incidencias = [];
}

function reportesGuardar(datos) {
    // Antes de guardar, se asegura que la estructura de datos es correcta para evitar guardar basura.
    reportesAsegurarIncidencias(datos);
    // Guardamos solo el objeto de datos necesario, sin información extra que pueda haber en la respuesta.
    fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        // Content-Type adecuado para enviar datos en formato URL-encoded, que es lo que el backend espera.
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        // Enviamos solo el objeto de datos necesario, 
        //  a JSON para que el backend lo procese correctamente.
        body: new URLSearchParams({
            accion: "guardar_datos_sistema",
            datos: JSON.stringify(datos),
        }),
    }).catch((error) => {
        console.error("No se pudo guardar incidencias en BD:", error);
    });
}

async function reportesCargarDatosBD() {
    try {
        // Realizamos una petición al backend para obtener los datos del sistema, 
        // que incluyen las incidencias.
        // await es una forma de esperar la respuesta de la promesa que devuelve fetch,
        // lo que permite escribir código asíncrono de manera más legible.
        const respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
        });
        const resultado = await respuesta.json();
        if (resultado?.ok && resultado?.data?.datos) return resultado.data.datos;
    } catch (error) {
        console.error("No se pudo cargar incidencias desde BD:", error);
    }
    return {};
}

async function reportesRenderizarTabla() {
    // Cargamos los datos desde la BD para asegurar tener 
    // la información más actualizada antes de renderizar.
    const datos = await reportesCargarDatosBD();
    // Antes de renderizar, nos aseguramos de que la estructura de datos es correcta para evitar errores.
    reportesAsegurarIncidencias(datos);
    const tbody = document.getElementById("CuerpoListaIncidencias");
    if (!tbody) return;

    tbody.innerHTML = "";
    if (!datos.incidencias.length) {
        tbody.innerHTML =
            '<tr><td colspan="4" class="ReportesTabla__vacio">Aún no hay incidencias. Cree la primera arriba.</td></tr>';
        return;
    }

    datos.incidencias.forEach((inc) => {
        const tr = document.createElement("tr");
        const desc = inc.descuenta ? "Sí" : "No";
        const horas = typeof inc.horasJustificadas === "number" ? inc.horasJustificadas : Number(inc.horasJustificadas);
        const horasTxt = Number.isFinite(horas) ? horas.toString().replace(".", ",") : String(inc.horasJustificadas);
        tr.innerHTML = `
            <td><strong>${escapeHtml(inc.nombre)}</strong></td>
            <td>${desc}</td>
            <td>${horasTxt} h</td>
            <td><button type="button" class="ReportesBtnEliminar" data-id="${escapeHtml(inc.id)}">Eliminar</button></td>`;
        const btn = tr.querySelector("button");
        btn.addEventListener("click", () => reportesEliminarIncidencia(inc.id));
        tbody.appendChild(tr);
    });
}

function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
}

async function reportesEliminarIncidencia(id) {
    if (!confirm("¿Eliminar esta incidencia del catálogo?")) return;
    const datos = await reportesCargarDatosBD();
    reportesAsegurarIncidencias(datos);
    datos.incidencias = datos.incidencias.filter((x) => x.id !== id);
    reportesGuardar(datos);
    reportesRenderizarTabla();
}

async function reportesOnSubmitIncidencia(ev) {
    ev.preventDefault();
    const nombre = document.getElementById("IncNombre").value.trim();
    const radioDesc = document.querySelector('input[name="descuenta"]:checked');
    const horasRaw = document.getElementById("IncHoras").value.trim().replace(",", ".");

    if (!nombre) return alert("Indique el nombre de la incidencia.");
    if (!radioDesc) return alert("Indique si se descuenta o no.");
    const horas = parseFloat(horasRaw);
    if (!Number.isFinite(horas) || horas < 0 || horas > 24) {
        return alert("Las horas justificadas deben ser un número entre 0 y 24.");
    }

    const datos = await reportesCargarDatosBD();
    reportesAsegurarIncidencias(datos);

    datos.incidencias.push({
        id: "inc_" + Date.now() + "_" + Math.random().toString(36).slice(2, 9),
        nombre,
        descuenta: radioDesc.value === "si",
        horasJustificadas: horas,
        creadoEn: new Date().toISOString(),
    });

    reportesGuardar(datos);
    document.getElementById("FormIncidencia").reset();
    reportesRenderizarTabla();
    alert("Incidencia guardada correctamente.");
}

function reportesCambiarPestana(pestanaActiva) {
    // Remover clase activa de todas las pestañas
    document.querySelectorAll('.ReportesPestaña').forEach(btn => btn.classList.remove('ReportesPestaña--activa'));
    // Agregar clase activa a la pestaña seleccionada
    pestanaActiva.classList.add('ReportesPestaña--activa');
    
    // Ocultar todos los paneles
    document.querySelectorAll('.ReportesPanel').forEach(panel => panel.hidden = true);
    // Mostrar el panel correspondiente
    const panelId = pestanaActiva.getAttribute('data-panel');
    const panel = document.getElementById(panelId);
    if (panel) panel.hidden = false;
}

function reportesOnSubmitNomina(ev) {
    ev.preventDefault();
    const form = ev.target;
    const formato = form.formato.value;
    window.open(`Controlador/Reportes/generador_nomina.php?formato=${formato}`, '_blank');
}

function volverMenuReportes() {
    window.location.href = "index.php?p=inicio";
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("FormIncidencia")?.addEventListener("submit", reportesOnSubmitIncidencia);
    document.getElementById("FormNomina")?.addEventListener("submit", reportesOnSubmitNomina);
    
    // Manejar clicks en pestañas
    document.querySelectorAll('.ReportesPestaña').forEach(btn => {
        btn.addEventListener('click', () => reportesCambiarPestana(btn));
    });
    
    reportesRenderizarTabla();
});
