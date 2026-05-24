/**
 * Gestión de Usuarios y Roles
 * Datos en tablas `roles`, `roles_permisos`, `permisos` y `usuarios`.
 */

/* Lectura de datos */

let datosGestionCache = { usuarios: [], empresas: [], departamentos: [] };
let rolesCache = [];
let usuariosBdCache = [];

async function cargarDatosGestionBD() {
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && resultado?.data?.datos) {
        datosGestionCache = resultado.data.datos;
    }
    return datosGestionCache;
}

async function cargarRolesBD() {
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "obtener_roles_sistema" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && Array.isArray(resultado?.data?.roles)) {
        rolesCache = resultado.data.roles;
    }
    return rolesCache;
}

async function cargarUsuariosBD() {
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "listar_usuarios" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && Array.isArray(resultado?.data?.usuarios)) {
        usuariosBdCache = resultado.data.usuarios.map((u) => {
            const empresas = u.empresas_asignadas;
            if (Array.isArray(empresas)) {
                u.empresas_asignadas = empresas.map(String);
            } else if (typeof empresas === 'string' && empresas.trim() !== '') {
                try {
                    const parsed = JSON.parse(empresas);
                    u.empresas_asignadas = Array.isArray(parsed) ? parsed.map(String) : [];
                } catch (e) {
                    u.empresas_asignadas = [];
                }
            } else {
                u.empresas_asignadas = [];
            }
            return u;
        });
    }
    return usuariosBdCache;
}

/* Control de ventanas y modales */

window.cerrarModales = function() {
    // Limpia formularios y oculta todos los overlays activos
    document.querySelectorAll(".Overlay").forEach(el => el.style.display = "none");
    document.querySelectorAll("form").forEach(f => f.reset());
};

window.abrirListaRoles = async function() {
    const overlay = document.getElementById("OverlayListaRoles");
    if (overlay) {
        await renderizarRoles();
        overlay.style.display = "flex"; // Se usa flex para el centrado del CSS
    }
};

window.abrirModalRol = async function(id = null) {
    const roles = await cargarRolesBD();
    const rol = id ? roles.find((r) => String(r.id) === String(id)) : null;
    const overlay = document.getElementById("OverlayRol");
    
    if (!overlay) return;

    document.getElementById("R_Id").value = id || "";
    document.getElementById("R_Nombre").value = rol?.nombre || "";
    document.getElementById("TituloModalRol").textContent = rol ? "Editar Rol" : "Nuevo Rol";

    // Módulos disponibles en el sistema para asignar permisos
    const modulos = ["registro", "consulta", "horarios", "reportes", "gestion", "auditorias"];
    const grid = document.getElementById("GridModulosRol");
    if (grid) {
        grid.innerHTML = modulos.map(m => `
            <label class="CheckItem">
                <input type="checkbox" value="${m}" ${rol?.permisos?.includes(m) ? "checked" : ""}>
                <span>${m.charAt(0).toUpperCase() + m.slice(1)}</span>
            </label>
        `).join("");
    }

    overlay.style.display = "flex"; 
};

window.abrirModalUsuario = async function(login = null) {
    try {
        const datos = await cargarDatosGestionBD();
        const usuarios = await cargarUsuariosBD();
        const empresas = Array.isArray(datos.empresas) ? datos.empresas : (Array.isArray(window.datosSistema?.empresas) ? window.datosSistema.empresas : []);
        const roles = await cargarRolesBD();
        const user = login ? usuarios.find(u => u.usuario === login) : null;

        const overlay = document.getElementById("OverlayUsuario");
        if (!overlay) return;

        // Carga de datos en los campos del formulario
        document.getElementById("U_EditarLogin").value = user?.usuario || "";
        document.getElementById("U_Nombre").value = user?.usuario || "";
        document.getElementById("U_Login").value = user?.usuario || "";
        document.getElementById("U_Clave").value = "";
        document.getElementById("U_Estado").value = String(user?.es_activo ?? "1");
        document.getElementById("TituloModalUsuario").textContent = user ? "Editar Usuario" : "Nuevo Usuario";

        const uRol = document.getElementById("U_Rol");
        if (uRol) {
            uRol.innerHTML = '<option value="">Selecciona un rol...</option>' + 
                roles.map(r => `<option value="${r.id}" ${String(user?.id_rol) === String(r.id) ? "selected" : ""}>${r.nombre}</option>`).join("");
        }

        const gridEmp = document.getElementById("GridEmpresasUsuario");
        if (gridEmp) {
            const asignadasRaw = user?.empresas_asignadas;
            let asignadas = [];

            if (Array.isArray(asignadasRaw)) {
                asignadas = asignadasRaw.map(String);
            } else if (typeof asignadasRaw === 'string' && asignadasRaw.trim() !== '') {
                try {
                    const parsed = JSON.parse(asignadasRaw);
                    asignadas = Array.isArray(parsed) ? parsed.map(String) : [];
                } catch (e) {
                    asignadas = [];
                }
            }

            const asignadasSet = new Set(asignadas.map((rif) => String(rif || '').trim().toUpperCase()));
            const empresasList = Array.isArray(empresas) ? empresas : [];

            console.debug('abrirModalUsuario: empresas cargadas', empresasList.length, 'asignadas', asignadasSet.size);

            gridEmp.innerHTML = empresasList.length ? empresasList.map(e => {
                const rif = String(e.rif || '').trim();
                const checked = rif && asignadasSet.has(rif.toUpperCase()) ? 'checked' : '';
                return `
                    <label class="CheckItem">
                        <input type="checkbox" name="empresas_asignadas[]" value="${rif}" ${checked}>
                        <span>${e.nombre || 'Empresa sin nombre'} (${rif})</span>
                    </label>
                `;
            }).join("") : '<p class="TextoGris">No hay empresas registradas aún.</p>';
        }

        overlay.style.display = "flex"; 
    } catch (error) {
        console.error("Error al abrir modal de usuario:", error);
    }
};

/* Renderizado de las tablas de datos */

async function renderizarRoles() {
    const tbody = document.querySelector("#TablaRoles tbody");
    if (!tbody) return;
    const roles = await cargarRolesBD();

    tbody.innerHTML = roles.length ? roles.map(r => `
        <tr>
            <td><strong>${r.nombre}</strong></td>
            <td><small>${r.permisos?.join(", ") || "Ninguno"}</small></td>
            <td class="Acciones">
                <button onclick="window.abrirModalRol('${r.id}')" title="Editar"><i class="fas fa-edit"></i></button>
                <button class="Eliminar" onclick="window.eliminarRol('${r.id}')" title="Eliminar"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join("") : '<tr><td colspan="3" class="VacioTabla">No hay roles definidos.</td></tr>';
}

async function renderizarUsuarios() {
    const tbody = document.querySelector("#TablaUsuarios tbody");
    if (!tbody) return;
    const usuarios = await cargarUsuariosBD();

    tbody.innerHTML = usuarios.length ? usuarios.map(u => {
        return `
            <tr>
                <td><strong>${u.usuario}</strong></td>
                <td>${u.usuario}</td>
                <td><span class="Badge">${u.nombre_rol || "Sin rol"}</span></td>
                <td><span class="Estado ${String(u.es_activo) === '1' ? 'activo' : 'inactivo'}">${String(u.es_activo) === '1' ? 'Activo' : 'Inactivo'}</span></td>
                <td class="Acciones">
                    <button onclick="window.abrirModalUsuario('${u.usuario}')" title="Editar"><i class="fas fa-edit"></i></button>
                    <button class="Eliminar" onclick="window.eliminarUsuario('${u.usuario}')" title="Eliminar"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
    }).join("") : '<tr><td colspan="5" class="VacioTabla">No hay usuarios registrados.</td></tr>';
}

/* Procesamiento de guardado */

window.guardarRol = async function(e) {
    e.preventDefault();
    const id = document.getElementById("R_Id").value.trim();
    const nombre = document.getElementById("R_Nombre").value.trim();
    const permisos = Array.from(document.querySelectorAll("#GridModulosRol input:checked")).map((cb) => cb.value);

    if (!nombre || !permisos.length) return alert("Ingrese el nombre y al menos un permiso.");

    try {
        const respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "persistir_rol",
                id_rol: id,
                nombre,
                permisos: JSON.stringify(permisos),
            }),
        });
        const resultado = await respuesta.json();
        if (!resultado?.ok) {
            return alert(resultado?.mensaje || "No se pudo guardar el rol.");
        }

        if (id) {
            window.registrarAuditoria("Editó Rol", `Rol: ${nombre} (ID: ${id})`);
        } else {
            window.registrarAuditoria("Creó Rol", `Rol: ${nombre}`);
        }

        const overlayRol = document.getElementById("OverlayRol");
        if (overlayRol) overlayRol.style.display = "none";

        await renderizarRoles();
        await renderizarUsuarios();
    } catch (err) {
        console.error(err);
        alert("Error de red al guardar el rol.");
    }
};

window.guardarUsuario = async function(e) {
    e.preventDefault();
    const usuarios = await cargarUsuariosBD();

    const editar = document.getElementById("U_EditarLogin").value;
    const login = document.getElementById("U_Login").value.trim();
    const clave = document.getElementById("U_Clave").value;
    const rol = document.getElementById("U_Rol").value;
    const estado = document.getElementById("U_Estado").value;
    const rolSelect = document.getElementById("U_Rol");
    const rolNombre = rolSelect?.options[rolSelect.selectedIndex]?.text || '';

    if (usuarios.find(u => u.usuario.toLowerCase() === login.toLowerCase() && u.usuario !== editar)) {
        return alert("El nombre de usuario ya está en uso.");
    }

    const empresasAsignadas = Array.from(document.querySelectorAll("#GridEmpresasUsuario input[type=checkbox]:checked"))
        .map((cb) => String(cb.value || '').trim())
        .filter(Boolean);

    const payload = {
        accion: editar ? "actualizar_usuario" : "crear_usuario",
        usuario: login,
        password: clave,
        id_rol: rol,
        rol: rolNombre,
        estado,
        empresas_asignadas: JSON.stringify(empresasAsignadas),
    };

    if (editar) {
        payload.usuario_original = editar;
    } else {
        if (!clave) return alert("La contraseña es obligatoria para nuevos usuarios.");
    }

    console.debug("Guardar usuario payload:", payload);
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams(payload),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) {
        return alert(resultado?.mensaje || "No se pudo guardar el usuario en base de datos.");
    }

    window.registrarAuditoria(editar ? "Editó Usuario" : "Creó Usuario", `Usuario: ${login}`);
    window.cerrarModales();
    await renderizarUsuarios();
};

/* Funciones de borrado */

window.eliminarRol = async function(id) {
    if (!confirm("¿Seguro que desea eliminar este rol? Los usuarios asociados podrían perder sus permisos.")) return;

    const roles = await cargarRolesBD();
    const rolEliminado = roles.find((r) => String(r.id) === String(id));

    try {
        const respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "eliminar_rol_por_id",
                id_rol: String(id),
            }),
        });
        const resultado = await respuesta.json();
        if (!resultado?.ok) {
            return alert(resultado?.mensaje || "No se pudo eliminar el rol.");
        }
        if (rolEliminado) {
            window.registrarAuditoria("Eliminó Rol", `Rol: ${rolEliminado.nombre} (ID: ${id})`);
        }
        await renderizarRoles();
        await renderizarUsuarios();
    } catch (err) {
        console.error(err);
        alert("Error de red al eliminar el rol.");
    }
};

window.eliminarUsuario = async function(login) {
    if (!confirm(`¿Desea eliminar definitivamente al usuario ${login}?`)) return;
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "eliminar_usuario",
            usuario: login,
        }),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) {
        return alert(resultado?.mensaje || "No se pudo eliminar usuario en base de datos.");
    }
    window.registrarAuditoria("Eliminó Usuario", `Usuario: ${login}`);
    await renderizarUsuarios();
};

/* Buscador y carga inicial */

window.filtrarUsuarios = function() {
    const texto = document.getElementById("BuscadorUsuarios")?.value.toLowerCase() || "";
    const filas = document.querySelectorAll("#TablaUsuarios tbody tr");
    filas.forEach(f => {
        if (f.querySelector(".VacioTabla")) return;
        f.style.display = f.textContent.toLowerCase().includes(texto) ? "" : "none";
    });
};

document.addEventListener("DOMContentLoaded", () => {
    renderizarRoles();
    renderizarUsuarios();
});