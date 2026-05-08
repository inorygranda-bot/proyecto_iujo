const API_URL = "datos/gestion_api.php";

function reportesAsegurarIncidencias(datos) {
    if (!datos) return { incidencias: [] };
    if (!Array.isArray(datos.incidencias)) datos.incidencias = [];
    return datos;
}

async function reportesGuardar(datos) {
    const datosLimpios = reportesAsegurarIncidencias(datos);
    
    try {
        const response = await fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "guardar_datos_sistema",
                datos: JSON.stringify(datosLimpios),
            }),
        });
        
        if (window.registrarAuditoria) {
            window.registrarAuditoria("MODIFICACIÓN", "Se actualizó el catálogo de incidencias.");
        }
        
        return await response.json();
    } catch (error) {
        console.error("Error al guardar:", error);
    }
}

async function reportesCargarDatosBD() {
    try {
        const respuesta = await fetch(API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
        });
        const resultado = await respuesta.json();
        return resultado?.data?.datos ? resultado.data.datos : { incidencias: [] };
    } catch (error) {
        console.error("Error al cargar:", error);
        return { incidencias: [] };
    }
}

async function reportesRenderizarTabla() {
    let datos = await reportesCargarDatosBD();
    datos = reportesAsegurarIncidencias(datos);
    
    const tbody = document.getElementById("CuerpoListaIncidencias");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (datos.incidencias.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="ReportesTabla__vacio">No hay incidencias registradas.</td></tr>';
        return;
    }

    datos.incidencias.forEach((inc) => {
        const tr = document.createElement("tr");
        const descuentaTxt = inc.descuenta ? "Sí" : "No";
        const horas = parseFloat(inc.horasJustificadas) || 0;
        
        tr.innerHTML = `
            <td><strong>${escapeHtml(inc.nombre)}</strong></td>
            <td><span class="badge-${inc.descuenta ? 'danger' : 'success'}">${descuentaTxt}</span></td>
            <td>${horas.toString().replace(".", ",")} h</td>
            <td>
                <button type="button" class="ReportesBtn ReportesBtn--eliminar" onclick="reportesEliminarIncidencia('${inc.id}')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function reportesEliminarIncidencia(id) {
    if (!confirm("¿Desea eliminar esta incidencia?")) return;
    
    let datos = await reportesCargarDatosBD();
    datos = reportesAsegurarIncidencias(datos);
    
    const nombreInc = datos.incidencias.find(x => x.id === id)?.nombre || "Desconocida";
    datos.incidencias = datos.incidencias.filter((x) => x.id !== id);
    
    await reportesGuardar(datos);
    
    if (window.registrarAuditoria) {
        window.registrarAuditoria("ELIMINACIÓN", `Se eliminó la incidencia: ${nombreInc}`);
    }
    
    reportesRenderizarTabla();
}

async function reportesOnSubmitIncidencia(ev) {
    ev.preventDefault();
    const nombre = document.getElementById("IncNombre").value.trim();
    const radioDesc = document.querySelector('input[name="descuenta"]:checked');
    const horasRaw = document.getElementById("IncHoras").value.trim().replace(",", ".");

    if (!nombre || !radioDesc) return alert("Por favor complete todos los campos.");
    
    const horas = parseFloat(horasRaw);
    if (isNaN(horas) || horas < 0 || horas > 24) return alert("Horas inválidas.");

    let datos = await reportesCargarDatosBD();
    datos = reportesAsegurarIncidencias(datos);

    datos.incidencias.push({
        id: "inc_" + Date.now(),
        nombre,
        descuenta: radioDesc.value === "si",
        horasJustificadas: horas,
        creadoEn: new Date().toISOString(),
    });

    await reportesGuardar(datos);
    document.getElementById("FormIncidencia").reset();
    reportesRenderizarTabla();
}

function reportesCambiarPestana(pestanaActiva) {
    document.querySelectorAll('.ReportesPestaña').forEach(btn => btn.classList.remove('ReportesPestaña--activa'));
    pestanaActiva.classList.add('ReportesPestaña--activa');
    
    document.querySelectorAll('.ReportesPanel').forEach(panel => panel.hidden = true);
    const panel = document.getElementById(pestanaActiva.getAttribute('data-panel'));
    if (panel) panel.hidden = false;
}

function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("FormIncidencia")?.addEventListener("submit", reportesOnSubmitIncidencia);
    
    document.querySelectorAll('.ReportesPestaña').forEach(btn => {
        btn.addEventListener('click', () => reportesCambiarPestana(btn));
    });
    
    reportesRenderizarTabla();
});