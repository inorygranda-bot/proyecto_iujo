let datosSistema = {
    empresas: [],
    calendarios: { horarios: { empresas: {} }, empresas: {} }
};

function normalizarDatosRegistro() {
    if (!datosSistema || typeof datosSistema !== "object") datosSistema = {};
    
    const colecciones = ['empresas', 'departamentos', 'empleados', 'usuarios'];
    colecciones.forEach(col => {
        if (!Array.isArray(datosSistema[col])) datosSistema[col] = [];
    });

    if (!datosSistema.calendarios) datosSistema.calendarios = {};
    const subCats = ['general', 'empresas', 'departamentos', 'empleados', 'horarios'];
    subCats.forEach(cat => {
        if (!datosSistema.calendarios[cat]) {
            datosSistema.calendarios[cat] = (cat === 'horarios') 
                ? { general: null, empresas: {}, departamentos: {}, empleados: {} } 
                : {};
        }
    });
}

async function apiPost(accion, extraData = {}) {
    const body = new URLSearchParams({ accion, ...extraData });
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body
    });
    return await respuesta.json();
}

async function cargarDatosSistemaDesdeBD() {
    try {
        const resultado = await apiPost("obtener_datos_sistema");
        if (resultado?.ok && resultado?.data?.datos) {
            datosSistema = resultado.data.datos;
        }
        normalizarDatosRegistro();
    } catch (error) {
        console.error("Error cargando datos:", error);
    }
}

async function guardarEmpresaEnBD(empresa) {
    const resultado = await apiPost("crear_empresa", {
        nombre: empresa.nombre,
        rif: empresa.rif,
        causa: empresa.causa || ""
    });

    if (!resultado.ok) throw new Error(resultado.mensaje || "Error en BD.");
    return resultado;
}

const validarFormatoRif = (rif) => /^[JjVvGgEe]-[0-9]{8}-[0-9]{1}$/.test(rif);

function IrAPaso(paso) {
    document.querySelectorAll("#Registro .Tarjeta").forEach(t => t.style.display = "none");
    
    if (paso === 1) {
        const caja = document.getElementById("CajaEmpresa");
        if (caja) caja.style.display = "block";
    } else if (paso === "EXITO") {
        const exito = document.getElementById("CajaExito");
        if (exito) exito.style.display = "block";
    }
}

const formEmpresa = document.getElementById("FormularioEmpresa");
if (formEmpresa) {
    formEmpresa.addEventListener("submit", async function (e) {
        e.preventDefault();

        const rif = document.getElementById("RifEmpresa").value.trim().toUpperCase();
        const nombre = document.getElementById("NombreEmpresa").value.trim();
        const objetivo = document.getElementById("ObjetivoEmpresa").value.trim();

        if (!validarFormatoRif(rif)) return alert("Formato de RIF incorrecto (Ej: J-12345678-0).");
        
        if (datosSistema.empresas.find(emp => emp.rif === rif)) {
            return alert(`El RIF ${rif} ya está registrado.`);
        }

        const nuevaEmpresa = { nombre, rif, causa: objetivo };

        try {
            await guardarEmpresaEnBD(nuevaEmpresa);

            datosSistema.empresas.push(nuevaEmpresa);
            
            if (window.registrarAuditoria) {
                window.registrarAuditoria("REGISTRO", `Empresa: ${nombre} (${rif})`);
            }

            await apiPost("guardar_datos_sistema", { datos: JSON.stringify(datosSistema) });

            IrAPaso("EXITO");
        } catch (error) {
            alert("Error: " + error.message);
        }
    });
}

function VolverAlInicio() {
    if (confirm("¿Desea salir? Se perderán los cambios no guardados.")) {
        window.location.href = "index.php";
    }
}

document.addEventListener("DOMContentLoaded", () => {
    cargarDatosSistemaDesdeBD();
    IrAPaso(1);
});