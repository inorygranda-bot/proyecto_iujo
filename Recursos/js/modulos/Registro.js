
let datosSistema = {};

function parseDatosGestionSeguro() {
    return {};
}

datosSistema = parseDatosGestionSeguro();

function normalizarDatosRegistro() {
    // Normalización de la estructura de datos para evitar errores por datos mal formados o incompletos.
    if (!datosSistema || typeof datosSistema !== "object") datosSistema = {};
    if (!Array.isArray(datosSistema.empresas)) datosSistema.empresas = [];
    if (!Array.isArray(datosSistema.departamentos)) datosSistema.departamentos = [];
    if (!Array.isArray(datosSistema.empleados)) datosSistema.empleados = [];
    if (!Array.isArray(datosSistema.usuarios)) datosSistema.usuarios = [];
    if (!datosSistema.calendarios || typeof datosSistema.calendarios !== "object") datosSistema.calendarios = {};
    if (!datosSistema.calendarios.general) datosSistema.calendarios.general = {};
    if (!datosSistema.calendarios.empresas) datosSistema.calendarios.empresas = {};
    if (!datosSistema.calendarios.departamentos) datosSistema.calendarios.departamentos = {};
    if (!datosSistema.calendarios.empleados) datosSistema.calendarios.empleados = {};
    if (!datosSistema.calendarios.horarios || typeof datosSistema.calendarios.horarios !== "object") {
        datosSistema.calendarios.horarios = { 
            // Estructura por defecto
            general: null, 
            empresas: {}, 
            departamentos: {},
            empleados: {} 
        };
    }
}

normalizarDatosRegistro();

function guardarEnLocal() {
    normalizarDatosRegistro();
}

async function cargarDatosSistemaDesdeBD() {
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && resultado?.data?.datos) {
        datosSistema = resultado.data.datos;
    }
    normalizarDatosRegistro();
}

async function guardarDatosSistemaEnBD() {
    normalizarDatosRegistro();
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "guardar_datos_sistema",
            datos: JSON.stringify(datosSistema),
        }),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) {
        throw new Error(resultado?.mensaje || "No se pudo guardar datos del sistema en BD.");
    }
}

async function guardarEmpresaEnBD(empresa) {
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "crear_empresa",
            nombre: empresa.nombre,
            rif: empresa.rif,
            causa: empresa.causa || "",
        }),
    });

    const resultado = await respuesta.json();
    if (!resultado.ok) {
        throw new Error(resultado.mensaje || "No se pudo guardar la empresa en la base de datos.");
    }
}

const validarFormatoRif = (rif) => /^[JjVvGgEe]-[0-9]{8}-[0-9]{1}$/.test(rif);

function IrAPaso(paso) {
    document.querySelectorAll("#Registro .Tarjeta").forEach((tarjeta) => {
        tarjeta.style.display = "none";
    });

    if (paso === 1) {
        document.getElementById("CajaEmpresa").style.display = "block";
    } else if (paso === "EXITO") {
        document.getElementById("CajaExito").style.display = "block";
    }
}

document.getElementById("FormularioEmpresa")?.addEventListener("submit", async function (e) {
    e.preventDefault();

    await cargarDatosSistemaDesdeBD();
    normalizarDatosRegistro();

    const rif = document.getElementById("RifEmpresa").value.trim().toUpperCase();
    const nombre = document.getElementById("NombreEmpresa").value.trim();

    if (datosSistema.empresas.find((emp) => emp.rif === rif)) {
        return alert("Error: El RIF " + rif + " ya está registrado");
    }

    if (!validarFormatoRif(rif)) return alert("Formato de RIF incorrecto.");

    const nuevaEmpresa = {
        nombre: nombre,
        rif: rif,
        causa: document.getElementById("ObjetivoEmpresa").value.trim(),
    };

   if (!datosSistema.calendarios.empresas) datosSistema.calendarios.empresas = {};
    datosSistema.calendarios.empresas[rif] = {}; 

    if (!datosSistema.calendarios.horarios.empresas) datosSistema.calendarios.horarios.empresas = {};
    datosSistema.calendarios.horarios.empresas[rif] = null;


    // ------------------------------

    try {
        await guardarEmpresaEnBD(nuevaEmpresa);
        datosSistema.empresaActual = nuevaEmpresa;
        datosSistema.empresas.push(nuevaEmpresa);
        if (typeof window.registrarAuditoria === "function") {
            window.registrarAuditoria("Creó Empresa", `Empresa: ${nuevaEmpresa.nombre} (RIF: ${nuevaEmpresa.rif})`);
        }
        await guardarDatosSistemaEnBD();
        IrAPaso("EXITO");
    } catch (error) {
        alert(error.message || "Error guardando empresa en base de datos.");
    }
});

/**
 * Vuelve al menú principal con confirmación (evita salidas accidentales).
 *
 * Nota: redirige a `index.php` sin `?p=inicio`; el PHP por defecto ya muestra inicio.
 */
function VolverAlInicio() {
    if (confirm("¿Desea salir? Se perderán los datos que no hayan sido guardados definitivamente.")) {
        window.location.href = "index.php";
    }
}

IrAPaso(1);
document.addEventListener("DOMContentLoaded", () => {
    cargarDatosSistemaDesdeBD().catch((error) => {
        console.error("No se pudo cargar datos del sistema en Registro:", error);
    });
});
