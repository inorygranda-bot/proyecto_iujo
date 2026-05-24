const URL_FORM_AUTENTICACION = "Controlador/Autenticacion/inicioSesion.php";
const URL_GESTION_API = "Controlador/API/gestion_api.php";

class InterfazSesionUI {
    constructor() {
        this.usuarioActivo = this.parseSesionPhpSeguro();
    }

    parseSesionPhpSeguro() {
        try {
            const tag = document.getElementById("__sesionPhp");
            if (!tag || !tag.textContent) return null;
            const parsed = JSON.parse(tag.textContent);
            return parsed && typeof parsed === "object" ? parsed : null;
        } catch (e) {
            console.warn("__sesionPhp no es JSON valido.", e);
            return null;
        }
    }

    verificarAcceso(modulo) {
        if (!this.usuarioActivo) return false;
        const rolTxt = String(this.usuarioActivo.rol || "");
        const esAdmin = rolTxt === "admin" || /admin/i.test(rolTxt);
        const tienePermiso = this.usuarioActivo.permisos?.includes(modulo);
        return esAdmin || tienePermiso;
    }

    mostrarInfoUsuario() {
        if (!this.usuarioActivo) return;

        const spanUser = document.getElementById("NombreUsuarioUI");
        const spanRol = document.getElementById("MensajeRol");

        if (spanUser) {
            const nombre = this.usuarioActivo.nombre || this.usuarioActivo.usuario || "Usuario";
            spanUser.textContent = nombre;
        }
        if (spanRol) {
            const rolLimpio = (this.usuarioActivo.rol || "").replace("rol_", "").toUpperCase();
            spanRol.innerHTML = `Usted ingreso como: <strong>${rolLimpio}</strong>`;
        }
    }

    protegerAccesoDirecto() {
        const params = new URLSearchParams(window.location.search);
        const moduloActual = params.get("p") || "inicio";
        const modulosRestringidos = ["registro", "consulta", "horarios", "reportes", "gestion"];

        if (modulosRestringidos.includes(moduloActual) && !this.verificarAcceso(moduloActual)) {
            if (this.usuarioActivo && this.usuarioActivo.rol !== "admin") {
                alert(`No tienes permisos para acceder al modulo "${moduloActual}".`);
            }
            window.location.href = "index.php?p=inicio";
        }
    }

    aplicarMenuSegunRol() {
        this.mostrarInfoUsuario();

        const menuMap = [
            { id: "EnlaceResgistroUI", modulo: "registro" },
            { selector: 'a[href*="p=consulta"]', modulo: "consulta" },
            { selector: 'a[href*="p=horarios"]', modulo: "horarios" },
            { selector: 'a[href*="p=reportes"]', modulo: "reportes" },
            { id: "EnlaceGestionUI", modulo: "gestion" }
        ];

        menuMap.forEach((item) => {
            let el = null;
            if (item.id) el = document.getElementById(item.id);
            if (!el && item.selector) el = document.querySelector(item.selector);

            if (el) {
                const visible = this.verificarAcceso(item.modulo);
                el.style.display = visible ? "" : "none";
                el.classList.toggle("sin-permiso", !visible);
            }
        });

        this.protegerAccesoDirecto();
    }
}

class ServicioAuditoria {
    constructor(usuarioActivo) {
        this.usuarioActivo = usuarioActivo;
    }

    async registrar(accion, detalle) {
        const sesion = window.usuarioActivo || this.usuarioActivo;
        if (!sesion) {
            console.warn("Auditoria: no hay sesion activa.");
            return false;
        }

        const params = {
            accion: "registrar_auditoria",
            accion_auditoria: accion,
            descripcion: detalle || "",
            usuario: sesion.usuario || "",
        };

        if (sesion.id_usuario) {
            params.id_usuario = String(sesion.id_usuario);
        }

        try {
            const respuesta = await fetch(URL_GESTION_API, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                body: new URLSearchParams(params),
            });
            const resultado = await respuesta.json();
            if (!resultado?.ok) {
                console.error("Auditoria no guardada:", resultado?.mensaje || "error desconocido");
                return false;
            }
            return true;
        } catch (err) {
            console.error("No se pudo registrar la auditoria en BD:", err);
            return false;
        }
    }
}

const interfazSesion = new InterfazSesionUI();
const servicioAuditoria = new ServicioAuditoria(interfazSesion.usuarioActivo);

function cerrarSesion(e) {
    if (e) e.preventDefault();
    window.location.href = "Controlador/Autenticacion/controlador_inicioSesion.php?salir=1";
}

if (!interfazSesion.usuarioActivo && !window.location.href.includes(URL_FORM_AUTENTICACION)) {
    window.location.href = URL_FORM_AUTENTICACION;
}

document.addEventListener("DOMContentLoaded", () => {
    interfazSesion.aplicarMenuSegunRol();
});

window.cerrarSesion = cerrarSesion;
window.verificarAcceso = (modulo) => interfazSesion.verificarAcceso(modulo);
window.usuarioActivo = interfazSesion.usuarioActivo;
window.registrarAuditoria = (accion, detalle) => servicioAuditoria.registrar(accion, detalle);
