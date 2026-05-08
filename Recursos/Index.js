const URL_SALIR = "Login/controlador_login.php?salir=1";
const URL_LOGIN_FORM = "Login/login.php";
const URL_GESTION_API = "datos/gestion_api.php"; 

if (typeof window.registrarAuditoria !== "function") {
    window.registrarAuditoria = function() {};
}

function parseSesionPhpSeguro() {
    try {
        const tag = document.getElementById("__sesionPhp");
        if (!tag || !tag.textContent) return null;
        const parsed = JSON.parse(tag.textContent);
        return parsed && typeof parsed === "object" ? parsed : null;
    } catch (e) {
        console.warn("__sesionPhp no es JSON válido.", e);
        return null;
    }
}

let usuarioActivo = parseSesionPhpSeguro();

if (!usuarioActivo) {
    if (!window.location.href.includes("login.php")) {
        window.location.href = URL_LOGIN_FORM;
    }
}

function cerrarSesion(e) {
    if (e) e.preventDefault();
    if (confirm("¿Estás seguro de que deseas salir?")) {
        window.location.href = URL_SALIR;
    }
}

function verificarAcceso(modulo) {
    if (!usuarioActivo) return false;
    const rolTxt = String(usuarioActivo.rol || "").toLowerCase();
    const esAdmin = rolTxt === "admin" || rolTxt.includes("admin");
    const tienePermiso = usuarioActivo.permisos?.includes(modulo);
    return esAdmin || tienePermiso;
}

function mostrarInfoUsuario() {
    if (!usuarioActivo) return;
    
    const spanUser = document.getElementById("NombreUsuarioUI");
    const spanRol = document.getElementById("MensajeRol");
    
    if (spanUser) {
        spanUser.textContent = usuarioActivo.nombre || usuarioActivo.usuario || "Usuario";
    }
    if (spanRol) {
        const rolLimpio = (usuarioActivo.rol || "").replace("rol_", "").toUpperCase();
        spanRol.innerHTML = `Ingresaste como: <strong>${rolLimpio}</strong>`;
    }
}

function aplicarMenuSegunRol() {
    mostrarInfoUsuario();
    
    const menuMap = [
        { id: "EnlaceResgistroUI", modulo: "registro" },
        { selector: 'a[href*="p=consulta"]', modulo: "consulta" },
        { selector: 'a[href*="p=horarios"]', modulo: "horarios" },
        { selector: 'a[href*="p=reportes"]', modulo: "reportes" },
        { id: "EnlaceGestionUI", modulo: "gestion" },
        { selector: 'a[href*="p=auditorias"]', modulo: "auditorias" }
    ];

    menuMap.forEach(item => {
        let el = item.id ? document.getElementById(item.id) : document.querySelector(item.selector);
        
        if (el) {
            const visible = verificarAcceso(item.modulo);
            el.style.display = visible ? "" : "none";
        }
    });
    
    protegerAccesoDirecto();
}

function protegerAccesoDirecto() {
    const params = new URLSearchParams(window.location.search);
    const moduloActual = params.get("p") || "inicio";
    const modulosRestringidos = ["registro", "consulta", "horarios", "reportes", "gestion", "auditorias"];
    
    if (modulosRestringidos.includes(moduloActual) && !verificarAcceso(moduloActual)) {
        alert(`⚠️ No tienes permisos para acceder al módulo "${moduloActual}".`);
        window.location.href = "index.php?p=inicio";
    }
}

function registrarAuditoria(accion, detalle) {
    if (!usuarioActivo) return;

    const usuario = usuarioActivo.usuario || "Desconocido";

    fetch(URL_GESTION_API, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "registrar_auditoria",
            usuario: usuario,
            accion_auditoria: accion,
            detalle: detalle,
        }),
    }).catch(err => console.error("Error auditoría:", err));
}

document.addEventListener("DOMContentLoaded", aplicarMenuSegunRol);

window.cerrarSesion = cerrarSesion;
window.verificarAcceso = verificarAcceso;
window.registrarAuditoria = registrarAuditoria;
window.usuarioActivo = usuarioActivo;