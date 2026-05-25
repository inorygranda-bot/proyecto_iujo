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

        // Esto limpia el nombre en el segundo campo si no quieres duplicarlo
        if (spanUser) {
            spanUser.textContent = ""; 
        }

        if (spanRol) {
            
            const nombre = this.usuarioActivo.usuario || "Usuario";
            spanRol.innerHTML = `Usted ingresó como: <strong>${nombre}</strong>`;
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

    registrar(accion, detalle) {
        if (!this.usuarioActivo) {
            return;
        }

        const usuario = this.usuarioActivo.usuario || "Desconocido";
        fetch(URL_GESTION_API, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "registrar_auditoria",
                usuario: usuario,
                accion_auditoria: accion,
                detalle: detalle,
            }),
        }).catch((err) => {
            console.error("No se pudo registrar la auditoria en BD:", err);
        });
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

document.addEventListener("DOMContentLoaded", () => {

    const btnHamburguesa = document.createElement("button");
    btnHamburguesa.className = "BtnMenu";
    btnHamburguesa.innerHTML = '<i class="fas fa-bars"></i>';
    
   
    const header = document.querySelector(".TopHeader");
    const h1 = header.querySelector("h1");
    header.insertBefore(btnHamburguesa, h1);

  
    const sidebar = document.querySelector(".Sidebar");
    const mainContent = document.querySelector(".MainContent");

    btnHamburguesa.addEventListener("click", () => {
        sidebar.classList.toggle("oculto");
        mainContent.classList.toggle("expandido");
    });
});