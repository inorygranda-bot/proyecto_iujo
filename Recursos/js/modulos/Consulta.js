
window.datosSistema = window.datosSistema || null;

const DEFAULT_DATOS = {
    empresaActual: null,
    deptoEnCreacion: null,
    empresas: [],
    departamentos: [],
    empleados: [],
    usuarios: [],
    usuario_activo: {
        usuario: '',
        rol: '',
        empresas_asignadas: [],
    },
    calendarios: {
        general: {},
        empresas: {},
        departamentos: {},
        empleados: {},
        feriadosSemana: { general: {}, empresas: {}, departamentos: {}, empleados: {} },
        horarios: { 
            general: { desdeM: "", hastaM: "", desdeT: "", hastaT: "" }, 
            empresas: {}, departamentos: {}, empleados: {} 
        },
        horariosFecha: { general: {}, empresas: {}, departamentos: {}, empleados: {} },
        horariosSemana: { general: {}, empresas: {}, departamentos: {}, empleados: {} }
    },
};

let empresaSeleccionadaRuta = "";
let deptoSeleccionadoRuta = "";
let consultaVistaNivel = "inicio";
let refrescarVistaActual = null;

function parseDatosGestionSeguro() {
    return window.datosSistema && typeof window.datosSistema === "object"
        ? window.datosSistema
        : JSON.parse(JSON.stringify(DEFAULT_DATOS));
}

function recargarDatos() {
    window.datosSistema = parseDatosGestionSeguro();
    normalizarDatos();
}

async function recargarDatosDesdeBD() {
    try {
        const respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
        });
        const resultado = await respuesta.json();
        if (resultado?.ok && resultado?.data?.datos) {
            window.datosSistema = resultado.data.datos;
            if (resultado.data.usuario_activo && typeof resultado.data.usuario_activo === 'object') {
                window.datosSistema.usuario_activo = resultado.data.usuario_activo;
            } else {
                window.datosSistema.usuario_activo = JSON.parse(JSON.stringify(DEFAULT_DATOS.usuario_activo));
            }
            console.debug("Consulta: usuario_activo", window.datosSistema.usuario_activo);
        } else {
            window.datosSistema = JSON.parse(JSON.stringify(DEFAULT_DATOS));
        }
    } catch (error) {
        console.error("Error al cargar datos desde BD:", error);
        window.datosSistema = JSON.parse(JSON.stringify(DEFAULT_DATOS));
    }
    normalizarDatos();
}

function normalizarDatos() {
    const d = window.datosSistema;
    if (!d || typeof d !== "object") {
        window.datosSistema = JSON.parse(JSON.stringify(DEFAULT_DATOS));
        return;
    }

    ['empresas', 'departamentos', 'empleados', 'usuarios'].forEach(col => {
        if (!Array.isArray(d[col])) d[col] = [];
    });

    if (!d.calendarios || typeof d.calendarios !== "object") {
        d.calendarios = JSON.parse(JSON.stringify(DEFAULT_DATOS.calendarios));
    }

    if (!d.calendarios.feriadosSemana) d.calendarios.feriadosSemana = {};
    if (!d.calendarios.horariosFecha) d.calendarios.horariosFecha = {};
    if (!d.calendarios.horariosSemana) d.calendarios.horariosSemana = {};
    if (!d.calendarios.horarios) d.calendarios.horarios = {};

    const capas = ['general', 'empresas', 'departamentos', 'empleados'];
    capas.forEach(capa => {
        if (!d.calendarios[capa]) d.calendarios[capa] = {};
        if (!d.calendarios.feriadosSemana[capa]) d.calendarios.feriadosSemana[capa] = {};
        if (!d.calendarios.horariosFecha[capa]) d.calendarios.horariosFecha[capa] = {};
        if (!d.calendarios.horariosSemana[capa]) d.calendarios.horariosSemana[capa] = {};
    });

    if (!d.calendarios.horarios.general) {
        d.calendarios.horarios.general = { desdeM: "", hastaM: "", desdeT: "", hastaT: "" };
    }
    
    ['empresas', 'departamentos', 'empleados'].forEach(cat => {
        if (!d.calendarios.horarios[cat]) d.calendarios.horarios[cat] = {};
    });

    if (!d.usuario_activo || typeof d.usuario_activo !== 'object') {
        d.usuario_activo = {
            usuario: '',
            rol: '',
            empresas_asignadas: [],
        };
    }

    if (!Array.isArray(d.usuario_activo.empresas_asignadas)) {
        d.usuario_activo.empresas_asignadas = [];
    }

    if (typeof d.usuario_activo.usuario !== 'string') {
        d.usuario_activo.usuario = '';
    }

    if (typeof d.usuario_activo.rol !== 'string') {
        d.usuario_activo.rol = '';
    }

    filtrarDatosPorEmpresasAsignadas(d);
}

function esRolAdministrador(rol) {
    const valor = String(rol || '').trim().toLowerCase();
    return /(^|[^a-z0-9])(admin|administrador|superadmin|root)([^a-z0-9]|$)/i.test(valor);
}

function esUsuarioAdministrador(usuario) {
    const valor = String(usuario || '').trim().toLowerCase();
    return /(^|[^a-z0-9])(admin|administrador|superadmin|root)([0-9]*|[^a-z0-9]|$)/i.test(valor);
}

function normalizarRif(rif) {
    if (rif === null || rif === undefined) {
        return '';
    }
    const valor = String(rif || '').trim().toUpperCase();
    return valor.replace(/[^A-Z0-9]/g, '');
}

function filtrarDatosPorEmpresasAsignadas(datos) {
    const rolActivo = String(datos.usuario_activo.rol || '').trim().toLowerCase();
    const usuarioActivo = String(datos.usuario_activo.usuario || '').trim().toLowerCase();
    const esAdminUsuario = esUsuarioAdministrador(usuarioActivo);

    if (esRolAdministrador(rolActivo) || esAdminUsuario) {
        console.debug('Consulta: usuario admin detectado, no filtrar empresas', { rolActivo, usuarioActivo });
        return;
    }

    const asignadas = (datos.usuario_activo.empresas_asignadas || [])
        .map(normalizarRif)
        .filter(Boolean);
    if (!asignadas.length) {
        datos.empresas = [];
        datos.departamentos = [];
        datos.empleados = [];
        return;
    }

    const asignadasSet = new Set(asignadas);
    console.debug('Consulta: empresas asignadas del usuario', asignadas);
    datos.empresas = (datos.empresas || []).filter((e) => asignadasSet.has(normalizarRif(e.rif)));
    const nombresPermitidos = datos.empresas.map((e) => String(e.nombre || '').trim().toLowerCase());
    datos.departamentos = (datos.departamentos || []).filter((d) => nombresPermitidos.includes(String(d.empresa || '').trim().toLowerCase()));
    datos.empleados = (datos.empleados || []).filter((e) => nombresPermitidos.includes(String(e.empresa || '').trim().toLowerCase()));
}

async function sincronizarDatosConsulta() {
    await recargarDatosDesdeBD();
}

async function guardarEnBD(payload) {
    const respuesta = await fetch("Controlador/API/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams(payload),
    });

    const resultado = await respuesta.json();
    if (!resultado.ok) {
        throw new Error(resultado.mensaje || "No se pudo guardar en base de datos.");
    }
}

function esc(s) {
    return String(s || "").replace(/\\/g, "\\\\").replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function $(id) {
    return document.getElementById(id);
}

function cerrarModal(id) {
    const modal = $(id);
    if (modal && typeof modal.close === "function") modal.close();
    if (modal) modal.hidden = true;
}

function validarRifEmpresa(rif) {
    return /^[JjVvGgEe]-[0-9]{8}-[0-9]{1}$/.test(rif || "");
}

function validarRifPersonal(rif) {
    return /^[VvEe]-[0-9]{7,9}-[0-9]{1}$/.test(rif || "");
}

function llenarEmpresasEnSelect(id, selected = "") {
    const sel = $(id);
    if (!sel) return;
    sel.innerHTML = '<option value="">— Elija empresa —</option>';
    (window.datosSistema.empresas || []).forEach((e) => {
        const opt = document.createElement("option");
        opt.value = e.nombre || "";
        opt.textContent = e.nombre || "";
        sel.appendChild(opt);
    });
    if (selected) {
        const exists = [...sel.options].some(o => o.value === selected);
        if (exists) sel.value = selected;
    }
}

function llenarDeptosPorEmpresa(id, empresa, selected = "") {
    const sel = $(id);
    if (!sel) return;
    sel.innerHTML = '<option value="">— Elija departamento —</option>';
    
    if (!empresa) {
        sel.disabled = true;
        return;
    }
    
    const deptos = (window.datosSistema.departamentos || []).filter(
        d => String(d.empresa || "").toLowerCase() === String(empresa).toLowerCase()
    );
    
    if (!deptos.length) {
        sel.disabled = true;
        return;
    }
    
    sel.disabled = false;
    deptos.forEach((d) => {
        const opt = document.createElement("option");
        opt.value = d.nombre || "";
        opt.textContent = d.nombre || "";
        sel.appendChild(opt);
    });
    if (selected) {
        const exists = [...sel.options].some(o => o.value === selected);
        if (exists) sel.value = selected;
    }
}

function actualizarVisibilidadVolverInterno() {
    const enlace = $("EnlaceVolverConsulta");
    if (enlace) {
        enlace.style.display = (consultaVistaNivel === "empresa" || consultaVistaNivel === "depto") ? "inline-block" : "none";
    }
}

function mostrarVistaInicialConsulta() {
    consultaVistaNivel = "inicio";
    empresaSeleccionadaRuta = "";
    deptoSeleccionadoRuta = "";
    
    const nomEmp = $("NombreEmpresa");
    if (nomEmp) nomEmp.textContent = "Empresa: —";
    
    const nomDept = $("NombreDepto");
    if (nomDept) nomDept.hidden = true;
    
    const sep = $("SeparadorRuta");
    if (sep) sep.hidden = true;
    
    const cab = $("CabeceraTabla");
    const body = $("CuerpoTabla");
    if (cab) cab.innerHTML = "";
    if (body) body.innerHTML = '<tr><td colspan="3" class="TablaDatos__vacio">Seleccione una empresa en el panel izquierdo.</td></tr>';
    
    const infoGer = $("InfoGerencia");
    if (infoGer) infoGer.style.display = "none";
    
    refrescarVistaActual = null;
    actualizarVisibilidadVolverInterno();
}

function volverConsultaInterno(ev) {
    if (ev) ev.preventDefault();
    if (consultaVistaNivel === "depto" && empresaSeleccionadaRuta) {
        const emp = (window.datosSistema.empresas || []).find(x => x.nombre === empresaSeleccionadaRuta);
        if (emp) { seleccionarEmpresa(emp); return; }
    }
    if (consultaVistaNivel === "empresa") {
        mostrarVistaInicialConsulta();
    }
}

function renderizarListaEmpresas() {
    recargarDatos();
    const lista = $("ListaEmpresas");
    if (!lista) {
        console.warn("Elemento #ListaEmpresas no encontrado");
        return;
    }
    
    lista.innerHTML = "";
    
    const empresas = window.datosSistema.empresas || [];
    if (!empresas.length) {
        lista.innerHTML = '<p style="padding:10px; color:#666; text-align:center;">Ninguna empresa registrada.</p>';
        return;
    }
    
    empresas.forEach((emp) => {
        const bloque = document.createElement("article");
        bloque.className = "EmpresaListaFila";
        
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "BotonEmpresaLista";
        btn.textContent = emp.nombre || "Sin nombre";
        btn.addEventListener("click", () => seleccionarEmpresa(emp));
        
        const btnCal = document.createElement("button");
        btnCal.type = "button";
        btnCal.className = "BotonEmpresaLista";
        btnCal.textContent = "☷";
        btnCal.style.width = "44px";
        btnCal.title = "Ver calendario";
        btnCal.addEventListener("click", (e) => {
            e.stopPropagation();
            if (typeof window.verCalendarioEmpresa === "function") window.verCalendarioEmpresa(emp.nombre);
        });
        
        const btnEdit = document.createElement("button");
        btnEdit.type = "button";
        btnEdit.className = "BotonEmpresaLista";
        btnEdit.textContent = "✎";
        btnEdit.style.width = "44px";
        btnEdit.title = "Editar empresa";
        btnEdit.addEventListener("click", (e) => {
            e.stopPropagation();
            if (typeof window.abrirEditarEmpresa === "function") window.abrirEditarEmpresa(emp);
        });
        
        bloque.append(btn, btnCal, btnEdit);
        lista.appendChild(bloque);
    });
}

function seleccionarEmpresa(emp) {
    if (!emp || !emp.nombre) return;
    
    consultaVistaNivel = "empresa";
    empresaSeleccionadaRuta = String(emp.nombre).trim();
    
    const deptos = (window.datosSistema.departamentos || []).filter(d => 
        String(d.empresa || "").toLowerCase() === empresaSeleccionadaRuta.toLowerCase()
    );
    
    actualizarVisibilidadVolverInterno();
    
    const nomEmp = $("NombreEmpresa");
    if (nomEmp) nomEmp.textContent = `Empresa: ${empresaSeleccionadaRuta}`;
    
    const nomDept = $("NombreDepto");
    if (nomDept) nomDept.hidden = true;
    
    const sep = $("SeparadorRuta");
    if (sep) sep.hidden = true;
    
    const cab = $("CabeceraTabla");
    const body = $("CuerpoTabla");
    
    if (cab) cab.innerHTML = "<tr><th>Departamento</th><th>Nro. Empleados</th><th>Acciones</th></tr>";
    
    if (body) {
        body.innerHTML = "";
        if (!deptos.length) {
            body.innerHTML = `<tr><td colspan="3" class="TablaDatos__vacio">No hay departamentos para ${empresaSeleccionadaRuta}.</td></tr>`;
        } else {
            deptos.forEach((d) => {
                const nEmp = (window.datosSistema.empleados || []).filter(e =>
                    String(e.empresa || "").toLowerCase() === empresaSeleccionadaRuta.toLowerCase() &&
                    String(e.depto || "").toLowerCase() === String(d.nombre || "").toLowerCase()
                ).length;
                
               // Busca esta parte en tu código y cámbiala por esto:
const row = document.createElement("tr");
row.innerHTML = `
    <td><strong>${esc(d.nombre)}</strong></td>
    <td>${nEmp}</td>
    <td style="white-space: nowrap;">
        <button type="button" class="BotonAccion btn-con-tooltip" data-tooltip="VER PERSONAL" onclick="verDetalleDepto('${esc(empresaSeleccionadaRuta)}','${esc(d.nombre)}')">👤</button>
        <button type="button" class="BotonAccion btn-con-tooltip" data-tooltip="EDITAR" onclick="abrirEditarDepartamento('${esc(empresaSeleccionadaRuta)}','${esc(d.nombre)}')">✎</button>
        <button type="button" class="BotonAccion btn-con-tooltip" data-tooltip="CALENDARIO" style="background:#b71c1c; color:white;" onclick="verCalendarioDepartamento('${esc(d.nombre)}','${esc(empresaSeleccionadaRuta)}')">☷</button>
    </td>
`;
body.appendChild(row);
            });
        }
    }
    
    const infoGer = $("InfoGerencia");
    if (infoGer) infoGer.style.display = "none";
    
    refrescarVistaActual = () => {
        recargarDatos();
        const e = (window.datosSistema.empresas || []).find(x => x.nombre === empresaSeleccionadaRuta);
        if (e) seleccionarEmpresa(e);
    };
}

function verDetalleDepto(nombreEmp, nombreDepto) {
    consultaVistaNivel = "depto";
    actualizarVisibilidadVolverInterno();
    empresaSeleccionadaRuta = nombreEmp;
    deptoSeleccionadoRuta = nombreDepto;
    
    const sep = $("SeparadorRuta");
    if (sep) sep.hidden = false;
    
    const nomDept = $("NombreDepto");
    if (nomDept) {
        nomDept.hidden = false;
        nomDept.textContent = `Depto: ${nombreDepto}`;
    }
    
    const cab = $("CabeceraTabla");
    const body = $("CuerpoTabla");
    
    if (cab) cab.innerHTML = "<tr><th>Código</th><th>Nombre</th><th>Cédula/RIF</th><th>Cargo</th><th>Jefe inmediato</th><th>Acciones</th></tr>";
    
    if (body) {
        const empleados = (window.datosSistema.empleados || []).filter(e => 
            String(e.empresa) === String(nombreEmp) && String(e.depto) === String(nombreDepto)
        );
        
        body.innerHTML = "";
        if (!empleados.length) {
            body.innerHTML = '<tr><td colspan="6" class="TablaDatos__vacio">No hay empleados.</td></tr>';
        } else {
            empleados.forEach((emp) => {
               // Busca esta parte en tu código y cámbiala por esto:
const row = document.createElement("tr");
row.innerHTML = `
    <td>${esc(emp.codigo)}</td>
    <td>${esc(emp.nombres)} ${esc(emp.apellidos)}</td>
    <td><small>${esc(emp.cedula)}</small><br><small>${esc(emp.rif)}</small></td>
    <td>${esc(emp.cargo)}</td>
    <td>${esc(emp.jefe || "Sin asignar")}</td>
    <td style="white-space: nowrap;">
        <button type="button" class="BotonAccion btn-con-tooltip" data-tooltip="EDITAR" onclick="abrirEditarEmpleado('${esc(emp.cedula)}')">✎</button>
        <button type="button" class="BotonAccion btn-con-tooltip" data-tooltip="CALENDARIO" style="background:#b71c1c; color:white;" onclick="verCalendarioDeEmpleado('${esc(emp.cedula)}','${esc(emp.nombres)}','${esc(emp.depto)}','${esc(emp.empresa)}')">📅</button>
    </td>
`;
body.appendChild(row);
            });
        }
    }
    
    const depto = (window.datosSistema.departamentos || []).find(d => 
        d.empresa === nombreEmp && d.nombre === nombreDepto
    );
    
    const infoGer = $("InfoGerencia");
    if (infoGer) {
        infoGer.style.display = "block";
        const nomGer = $("NombreGerente");
        if (nomGer) nomGer.textContent = depto?.supervisor || "Sin asignar";
    }
    
    refrescarVistaActual = () => {
        recargarDatos();
        verDetalleDepto(nombreEmp, nombreDepto);
    };
}

function abrirSeleccionGerente() {
    const lista = $("ListaCandidatosGerente");
    if (!lista) return;
    
    const candidatos = (window.datosSistema.empleados || []).filter(e => 
        e.empresa === empresaSeleccionadaRuta && e.depto === deptoSeleccionadoRuta
    );
    
    lista.innerHTML = "";
    if (!candidatos.length) {
        lista.innerHTML = "<li>No hay empleados.</li>";
    } else {
        candidatos.forEach((c) => {
            const li = document.createElement("li");
            li.textContent = `${c.nombres} ${c.apellidos}`;
            li.style.cursor = "pointer";
            // Pasar la cédula del empleado al asignar como gerente.
            li.addEventListener("click", () => asignarGerente(`${c.nombres} ${c.apellidos}`, c.cedula));
            lista.appendChild(li);
        });
    }
    
    const selector = $("SelectorGerente");
    if (selector) selector.hidden = false;
}

function cerrarSelector() {
    const selector = $("SelectorGerente");
    if (selector) selector.hidden = true;
}

async function asignarGerente(nombreCompleto, cedulaSupervisor) {
    const deptoCheck = (window.datosSistema.departamentos || []).find(d =>
        d.empresa === empresaSeleccionadaRuta && d.nombre === deptoSeleccionadoRuta
    );
    if (!deptoCheck) return;

    try {
        const respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "actualizar_gerente_depto",
                empresa: empresaSeleccionadaRuta,
                departamento: deptoSeleccionadoRuta,
                supervisor: nombreCompleto,
                cedula_supervisor: cedulaSupervisor, //Enviar la cédula del supervisor al backend
            }),
        });
        const resultado = await respuesta.json();
        if (!resultado?.ok) {
            alert(resultado?.mensaje || "No se pudo asignar el gerente.");
            return;
        }
        await sincronizarDatosConsulta();
        cerrarSelector();

        const nomGer = $("NombreGerente");
        if (nomGer) nomGer.textContent = nombreCompleto;

        if (refrescarVistaActual) refrescarVistaActual();
    } catch (err) {
        console.error(err);
        alert("Error de red al guardar gerente.");
    }
}

function abrirModalDepartamento() {
    if (!(window.datosSistema.empresas || []).length) return alert("Primero cree una empresa.");
    llenarEmpresasEnSelect("SelectEmpresaDepto", empresaSeleccionadaRuta);
    const nom = $("NombreNuevoDepto");
    const obj = $("ObjetivoNuevoDepto");
    if (nom) nom.value = "";
    if (obj) obj.value = "";
    const modal = $("ModalDepartamento");
    if (modal) {
        modal.hidden = false;
        if (typeof modal.showModal === "function") modal.showModal();
    }
}

function abrirModalEmpleado() {
    if (!(window.datosSistema.empresas || []).length) return alert("Primero cree una empresa.");
    llenarEmpresasEnSelect("SelectEmpresaEmp", empresaSeleccionadaRuta);
    const selEmp = $("SelectEmpresaEmp");
    llenarDeptosPorEmpresa("SelectDeptoEmp", selEmp?.value || "", deptoSeleccionadoRuta);
    const form = $("FormNuevoEmpleado");
    if (form) form.reset();
    const modal = $("ModalEmpleado");
    if (modal) {
        modal.hidden = false;
        if (typeof modal.showModal === "function") modal.showModal();
    }
}

function abrirEditarEmpresa(emp) {
    if (!emp) return;
    const ant = $("EditEmpresaAnterior");
    const nom = $("EditEmpresaNombre");
    const rif = $("EditEmpresaRif");
    const obj = $("EditEmpresaObjetivo");
    if (ant) ant.value = emp.nombre || "";
    if (nom) nom.value = emp.nombre || "";
    if (rif) rif.value = emp.rif || "";
    if (obj) obj.value = emp.causa || "";
    const modal = $("ModalEditarEmpresa");
    if (modal) {
        modal.hidden = false;
        if (typeof modal.showModal === "function") modal.showModal();
    }
}

function abrirEditarDepartamento(nombreEmpresa, nombreDepto) {
    const d = (window.datosSistema.departamentos || []).find(x => 
        x.empresa === nombreEmpresa && x.nombre === nombreDepto
    );
    if (!d) return;
    
    const antEmp = $("EditDeptoEmpresaAnterior");
    const antNom = $("EditDeptoNombreAnterior");
    const selEmp = $("EditDeptoEmpresa");
    const nom = $("EditDeptoNombre");
    const obj = $("EditDeptoObjetivo");
    
    if (antEmp) antEmp.value = d.empresa || "";
    if (antNom) antNom.value = d.nombre || "";
    if (selEmp) llenarEmpresasEnSelect("EditDeptoEmpresa", d.empresa);
    if (nom) nom.value = d.nombre || "";
    if (obj) obj.value = d.causa || "";
    
    const modal = $("ModalEditarDepartamento");
    if (modal) {
        modal.hidden = false;
        if (typeof modal.showModal === "function") modal.showModal();
    }
}

function llenarJefesSelect(selectId, empresa, depto, excludeCedula, selected) {
    const sel = $(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="Sin asignar">Sin asignar</option>';
    (window.datosSistema.empleados || [])
        .filter(e => e.empresa === empresa && e.depto === depto && e.cedula !== excludeCedula)
        .forEach(e => {
            const opt = document.createElement("option");
            opt.value = `${e.nombres} ${e.apellidos}`;
            opt.textContent = `${e.nombres} ${e.apellidos}`;
            sel.appendChild(opt);
        });
    if (selected) {
        const exists = [...sel.options].some(o => o.value === selected);
        if (exists) sel.value = selected;
    }
}

function abrirEditarEmpleado(cedula) {
    const e = (window.datosSistema.empleados || []).find(x => x.cedula === cedula);
    if (!e) return;
    
    const cedOrig = $("EditEmpCedulaOriginal");
    if (cedOrig) cedOrig.value = e.cedula || "";
    
    llenarEmpresasEnSelect("EditEmpEmpresa", e.empresa);
    llenarDeptosPorEmpresa("EditEmpDepto", e.empresa, e.depto);
    
    const campos = {
        "EditEmpNombres": e.nombres,
        "EditEmpApellidos": e.apellidos,
        "EditEmpCodigo": e.codigo,
        "EditEmpCargo": e.cargo,
        "EditEmpRif": e.rif
    };
    Object.entries(campos).forEach(([id, val]) => {
        const el = $(id);
        if (el) el.value = val || "";
    });
    
    llenarJefesSelect("EditEmpJefe", e.empresa, e.depto, e.cedula, e.jefe || "Sin asignar");
    
    const modal = $("ModalEditarEmpleado");
    if (modal) {
        modal.hidden = false;
        if (typeof modal.showModal === "function") modal.showModal();
    }
}

// ========================================
// FUNCIONALIDAD DE BÚSQUEDA
// ========================================

const buscarEmpresaInput = $("buscarEmpresa");
const resultadosEmpresaUl = $("resultadosEmpresa");
const buscarDepartamentoInput = $("buscarDepartamento");
const resultadosDepartamentoUl = $("resultadosDepartamento");
const buscarEmpleadoInput = $("buscarEmpleado");
const resultadosEmpleadoUl = $("resultadosEmpleado");

function renderizarResultadosBusqueda(ulElement, resultados, tipo) {
    ulElement.innerHTML = "";
    if (resultados.length === 0) {
        ulElement.hidden = true;
        return;
    }
    ulElement.hidden = false;
    resultados.forEach(item => {
        const li = document.createElement("li");
        li.className = "resultado-item"; // Clase para estilo
        if (tipo === "empresa") {
            li.textContent = item.nombre;
            li.onclick = () => {
                seleccionarEmpresa(item);
                ulElement.hidden = true;
                buscarEmpresaInput.value = "";
            };
        } else if (tipo === "departamento") {
            li.textContent = item.nombre;
            li.onclick = () => {
                verDetalleDepto(item.empresa, item.nombre);
                ulElement.hidden = true;
                buscarDepartamentoInput.value = "";
            };
        } else if (tipo === "empleado") {
            li.textContent = `${item.nombres} ${item.apellidos} (${item.cedula})`;
            li.onclick = () => {
                if (typeof window.verCalendarioDeEmpleado === "function") {
                    window.verCalendarioDeEmpleado(item.cedula, item.nombres, item.depto, item.empresa);
                }
                ulElement.hidden = true;
                buscarEmpleadoInput.value = "";
            };
        }
        ulElement.appendChild(li);
    });
}

function manejarBusquedaEmpresa() {
    const termino = buscarEmpresaInput.value.toLowerCase();
    if (termino.length < 2) {
        resultadosEmpresaUl.hidden = true;
        resultadosEmpresaUl.innerHTML = "";
        return;
    }
    const resultados = (window.datosSistema.empresas || []).filter(emp => 
        emp.nombre.toLowerCase().includes(termino)
    );
    renderizarResultadosBusqueda(resultadosEmpresaUl, resultados, "empresa");
}

function manejarBusquedaDepartamento() {
    const termino = buscarDepartamentoInput.value.toLowerCase();
    if (termino.length < 2 || !empresaSeleccionadaRuta) {
        resultadosDepartamentoUl.hidden = true;
        resultadosDepartamentoUl.innerHTML = "";
        return;
    }
    const resultados = (window.datosSistema.departamentos || []).filter(depto => 
        depto.empresa === empresaSeleccionadaRuta && depto.nombre.toLowerCase().includes(termino)
    );
    renderizarResultadosBusqueda(resultadosDepartamentoUl, resultados, "departamento");
}

function manejarBusquedaEmpleado() {
    const termino = buscarEmpleadoInput.value.toLowerCase();
    if (termino.length < 2 || !deptoSeleccionadoRuta) {
        resultadosEmpleadoUl.hidden = true;
        resultadosEmpleadoUl.innerHTML = "";
        return;
    }
    const resultados = (window.datosSistema.empleados || []).filter(empleado => 
        empleado.empresa === empresaSeleccionadaRuta &&
        empleado.depto === deptoSeleccionadoRuta &&
        (
            empleado.nombres.toLowerCase().includes(termino) ||
            empleado.apellidos.toLowerCase().includes(termino) ||
            empleado.cedula.toLowerCase().includes(termino) ||
            empleado.codigo.toLowerCase().includes(termino)
        )
    );
    renderizarResultadosBusqueda(resultadosEmpleadoUl, resultados, "empleado");
}

buscarEmpresaInput.addEventListener("keyup", manejarBusquedaEmpresa);
buscarDepartamentoInput.addEventListener("keyup", manejarBusquedaDepartamento);
buscarEmpleadoInput.addEventListener("keyup", manejarBusquedaEmpleado);

// Funciones para controlar la visibilidad de los campos de búsqueda
function actualizarVisibilidadBuscadores() {
    const buscarEmpresaLabel = document.querySelector('label[for="buscarEmpresa"]');
    const buscarDepartamentoLabel = document.querySelector('label[for="buscarDepartamento"]');
    const buscarEmpleadoLabel = document.querySelector('label[for="buscarEmpleado"]');

    if (consultaVistaNivel === "inicio") {
        if (buscarEmpresaLabel) buscarEmpresaLabel.hidden = false;
        buscarEmpresaInput.hidden = false;

        if (buscarDepartamentoLabel) buscarDepartamentoLabel.hidden = true;
        buscarDepartamentoInput.hidden = true;

        if (buscarEmpleadoLabel) buscarEmpleadoLabel.hidden = true;
        buscarEmpleadoInput.hidden = true;

    } else if (consultaVistaNivel === "empresa") {
        if (buscarEmpresaLabel) buscarEmpresaLabel.hidden = false;
        buscarEmpresaInput.hidden = false;

        if (buscarDepartamentoLabel) buscarDepartamentoLabel.hidden = false;
        buscarDepartamentoInput.hidden = false;

        if (buscarEmpleadoLabel) buscarEmpleadoLabel.hidden = true;
        buscarEmpleadoInput.hidden = true;

    } else if (consultaVistaNivel === "depto") {
        if (buscarEmpresaLabel) buscarEmpresaLabel.hidden = true;
        buscarEmpresaInput.hidden = true;

        if (buscarDepartamentoLabel) buscarDepartamentoLabel.hidden = true;
        buscarDepartamentoInput.hidden = true;

        if (buscarEmpleadoLabel) buscarEmpleadoLabel.hidden = false;
        buscarEmpleadoInput.hidden = false;
    }
    // Always hide result lists when visibility is updated
    resultadosEmpresaUl.hidden = true;
    resultadosDepartamentoUl.hidden = true;
    resultadosEmpleadoUl.hidden = true;
}

// Asegurarse de llamar a esta función cuando la vista cambie
// Se llamará en mostrarVistaInicialConsulta, seleccionarEmpresa, verDetalleDepto
const originalMostrarVistaInicialConsulta = mostrarVistaInicialConsulta;
mostrarVistaInicialConsulta = function() {
    originalMostrarVistaInicialConsulta();
    actualizarVisibilidadBuscadores();
};

const originalSeleccionarEmpresa = seleccionarEmpresa;
seleccionarEmpresa = function(emp) {
    originalSeleccionarEmpresa(emp);
    actualizarVisibilidadBuscadores();
};

const originalVerDetalleDepto = verDetalleDepto;
verDetalleDepto = function(nombreEmp, nombreDepto) {
    originalVerDetalleDepto(nombreEmp, nombreDepto);
    actualizarVisibilidadBuscadores();
};


// ========================================
// EVENTOS DE FORMULARIOS
// ========================================

function initEventosFormularios() {
    const selEmpEmp = $("SelectEmpresaEmp");
    if (selEmpEmp) {
        selEmpEmp.addEventListener("change", function() {
            llenarDeptosPorEmpresa("SelectDeptoEmp", this.value, "");
        });
    }
    
    const selEditEmp = $("EditEmpEmpresa");
    if (selEditEmp) {
        selEditEmp.addEventListener("change", function() {
            llenarDeptosPorEmpresa("EditEmpDepto", this.value, "");
        });
    }
    
    const selEditDepto = $("EditEmpDepto");
    if (selEditDepto) {
        selEditDepto.addEventListener("change", function() {
            const emp = $("EditEmpEmpresa")?.value || "";
            const ced = $("EditEmpCedulaOriginal")?.value || "";
            llenarJefesSelect("EditEmpJefe", emp, this.value, ced, "Sin asignar");
        });
    }

    const formNuevoDepto = $("FormNuevoDepartamento");
    if (formNuevoDepto) {
        formNuevoDepto.addEventListener("submit", async (ev) => {
            ev.preventDefault();
            const empresaNombreSelect = $("SelectEmpresaDepto")?.value.trim() || "";
            const nombreDepto = $("NombreNuevoDepto")?.value.trim() || "";
            if (!empresaNombreSelect || !nombreDepto) return alert("Faltan datos.");
            
            const existe = (window.datosSistema.departamentos || []).some(d =>
                String(d.empresa || "").toLowerCase() === empresaNombreSelect.toLowerCase() &&
                String(d.nombre || "").toLowerCase() === nombreDepto.toLowerCase()
            );
            if (existe) return alert("El departamento ya existe en esta empresa.");
            
            const causaDepto = $("ObjetivoNuevoDepto")?.value.trim() || "";

            try {
                await guardarEnBD({
                    accion: "crear_departamento",
                    empresa: empresaNombreSelect,
                    nombre: nombreDepto,
                    causa: causaDepto,
                });

                window.registrarAuditoria(
                    "Creó Departamento",
                    `Departamento: ${nombreDepto} (Empresa: ${empresaNombreSelect})`
                );
                await sincronizarDatosConsulta();
                cerrarModal("ModalDepartamento");
                renderizarListaEmpresas();
                if (consultaVistaNivel === "empresa" && empresaSeleccionadaRuta === empresaNombreSelect) {
                    const empAct = (window.datosSistema.empresas || []).find(ex => ex.nombre === empresaNombreSelect);
                    if (empAct) seleccionarEmpresa(empAct);
                }
            } catch (error) {
                alert(error.message || "Error guardando departamento en base de datos.");
            }
        });
    }

    const formNuevoEmp = $("FormNuevoEmpleado");
    if (formNuevoEmp) {
        formNuevoEmp.addEventListener("submit", async (ev) => {
            ev.preventDefault();
            const empresa = $("SelectEmpresaEmp")?.value.trim() || "";
            const depto = $("SelectDeptoEmp")?.value.trim() || "";
            const codigo = ($("EmpCodigo")?.value || "").trim().toUpperCase();
            const cedulaNum = ($("EmpCedulaNumero")?.value || "").replace(/D/g, "");
            const prefijo = $("EmpPrefijoCedula")?.value || "";
            const cedula = prefijo + "-" + ($("EmpCedulaNumero")?.value || "").trim();
            const rif = ($("EmpRif")?.value || "").trim().toUpperCase();
            
            if (cedulaNum.length < 7) return alert("Cédula inválida.");
            if (!validarRifPersonal(rif)) return alert("RIF personal inválido.");
            if ((window.datosSistema.empleados || []).some(e => e.codigo === codigo || e.cedula === cedula || e.rif === rif)) {
                return alert("Código/cédula/RIF ya existe.");
            }
            
            const nombres = ($("EmpNombres")?.value || "").trim();
            const apellidos = ($("EmpApellidos")?.value || "").trim();
            const cargo = ($("EmpCargo")?.value || "").trim();

            try {
                await guardarEnBD({
                    accion: "crear_empleado",
                    codigo,
                    cedula,
                    rif,
                    nombres,
                    apellidos,
                    cargo,
                    empresa,
                    departamento: depto,
                    es_supervisor: 0, 
                });

                window.registrarAuditoria(
                    "Creó Empleado",
                    `Empleado: ${nombres} ${apellidos} (Cédula: ${cedula}, Código: ${codigo}) en Departamento: ${depto}, Empresa: ${empresa}`
                );
                await sincronizarDatosConsulta();
                cerrarModal("ModalEmpleado");
                renderizarListaEmpresas();
                if (refrescarVistaActual) refrescarVistaActual();
            } catch (error) {
                alert(error.message || "Error guardando empleado en base de datos.");
            }
        });
    }

    const formEditEmp = $("FormEditarEmpresa");
    if (formEditEmp) {
        formEditEmp.addEventListener("submit", async (ev) => {
            ev.preventDefault();
            const prev = $("EditEmpresaAnterior")?.value || "";
            const nombreNuevo = ($("EditEmpresaNombre")?.value || "").trim();
            const rifNuevo = ($("EditEmpresaRif")?.value || "").trim().toUpperCase();
            const causa = $("EditEmpresaObjetivo")?.value.trim() || "";

            if (!validarRifEmpresa(rifNuevo)) return alert("RIF de empresa inválido.");
            const empresaRef = (window.datosSistema.empresas || []).find((x) => x.nombre === prev);
            if (!empresaRef) return;

            try {
                await guardarEnBD({
                    accion: "actualizar_empresa",
                    nombre_anterior: prev,
                    nombre: nombreNuevo,
                    rif: rifNuevo,
                    causa,
                });

                window.registrarAuditoria(
                    "Editó Empresa",
                    `Empresa: ${prev} -> ${nombreNuevo} (RIF: ${rifNuevo})`
                );
                await sincronizarDatosConsulta();

                cerrarModal("ModalEditarEmpresa");
                renderizarListaEmpresas();
                empresaSeleccionadaRuta = nombreNuevo;
                if (refrescarVistaActual) refrescarVistaActual();
            } catch (error) {
                alert(error.message || "No se pudo actualizar la empresa.");
            }
        });
    }

    const formEditDepto = $("FormEditarDepartamento");
    if (formEditDepto) {
        formEditDepto.addEventListener("submit", async (ev) => {
            ev.preventDefault();
            const oldEmp = $("EditDeptoEmpresaAnterior")?.value || "";
            const oldName = $("EditDeptoNombreAnterior")?.value || "";
            const newEmp = $("EditDeptoEmpresa")?.value || "";
            const newName = ($("EditDeptoNombre")?.value || "").trim();
            const causa = $("EditDeptoObjetivo")?.value.trim() || "";

            if (!oldEmp || !oldName || !newEmp || !newName) {
                return alert("Datos de departamento incompletos.");
            }

            try {
                await guardarEnBD({
                    accion: "actualizar_departamento",
                    empresa_anterior: oldEmp,
                    nombre_anterior: oldName,
                    empresa: newEmp,
                    nombre: newName,
                    causa,
                });

                window.registrarAuditoria(
                    "Editó Departamento",
                    `Departamento: ${oldName} (Empresa: ${oldEmp}) -> ${newName} (Empresa: ${newEmp})`
                );
                await sincronizarDatosConsulta();

                cerrarModal("ModalEditarDepartamento");
                renderizarListaEmpresas();
                empresaSeleccionadaRuta = newEmp;
                if (refrescarVistaActual) refrescarVistaActual();
            } catch (error) {
                alert(error.message || "No se pudo actualizar el departamento.");
            }
        });
    }

    const formEditEmpleado = $("FormEditarEmpleado");
    if (formEditEmpleado) {
        formEditEmpleado.addEventListener("submit", async (ev) => {
            ev.preventDefault();
            const cedulaOriginal = $("EditEmpCedulaOriginal")?.value || "";
            const e = (window.datosSistema.empleados || []).find((x) => x.cedula === cedulaOriginal);
            if (!e) return;

            const rif = ($("EditEmpRif")?.value || "").trim().toUpperCase();
            if (!validarRifPersonal(rif)) return alert("RIF personal inválido.");

            const empresaNv = $("EditEmpEmpresa")?.value || "";
            const deptNv = $("EditEmpDepto")?.value || "";
            const nombresNv = ($("EditEmpNombres")?.value || "").trim();
            const apellidosNv = ($("EditEmpApellidos")?.value || "").trim();

            try {
                await guardarEnBD({
                    accion: "actualizar_empleado",
                    cedula_original: cedulaOriginal,
                    empresa: empresaNv,
                    departamento: deptNv,
                    codigo: ($("EditEmpCodigo")?.value || "").trim().toUpperCase(),
                    rif,
                    nombres: nombresNv,
                    apellidos: apellidosNv,
                    cargo: ($("EditEmpCargo")?.value || "").trim(),
                    jefe: $("EditEmpJefe")?.value || "Sin asignar",
                    es_supervisor: e.es_supervisor, //Enviar el estado de supervisor al backend
                });

                window.registrarAuditoria(
                    "Editó Empleado",
                    `Empleado: ${nombresNv} ${apellidosNv} — ${cedulaOriginal}`
                );
                await sincronizarDatosConsulta();

                cerrarModal("ModalEditarEmpleado");
                renderizarListaEmpresas();
                if (refrescarVistaActual) refrescarVistaActual();
            } catch (error) {
                alert(error.message || "No se pudo actualizar el empleado.");
            }
        });
    }
}

window.renderizarListaEmpresas = renderizarListaEmpresas;
window.seleccionarEmpresa = seleccionarEmpresa;
window.verDetalleDepto = verDetalleDepto;
window.abrirModalDepartamento = abrirModalDepartamento;
window.cerrarModalDepartamento = function() { cerrarModal("ModalDepartamento"); };
window.abrirModalEmpleado = abrirModalEmpleado;
window.cerrarModalEmpleado = function() { cerrarModal("ModalEmpleado"); };
window.abrirEditarEmpresa = abrirEditarEmpresa;
window.abrirEditarDepartamento = abrirEditarDepartamento;
window.abrirEditarEmpleado = abrirEditarEmpleado;
window.abrirSeleccionGerente = abrirSeleccionGerente;
window.cerrarSelector = cerrarSelector;
window.asignarGerente = asignarGerente;
window.volverConsultaInterno = volverConsultaInterno;
window.VolverInicio = function() {
    if (confirm("¿Desea volver al menú principal?")) window.location.href = "index.php?p=inicio";
};
window.obtenerHorarioEfectivo = function(idEmp, idDepto, idEmpld) {
    const h = window.datosSistema.calendarios.horarios || {};
    if (idEmpld && h.empleados?.[idEmpld] && h.empleados[idEmpld].desdeM !== "") return h.empleados[idEmpld];
    if (idDepto && h.departamentos?.[idDepto] && h.departamentos[idDepto].desdeM !== "") return h.departamentos[idDepto];
    if (idEmp && h.empresas?.[idEmp] && h.empresas[idEmp].desdeM !== "") return h.empresas[idEmp];
    return h.general || { desdeM: "", hastaM: "", desdeT: "", hastaT: "" };
};

async function initConsulta() {
    console.log("🔄 Iniciando Consulta.js...");
    await recargarDatosDesdeBD();
    initEventosFormularios();
    renderizarListaEmpresas();
    
    const btnVolver = $("EnlaceVolverConsulta");
    if (btnVolver) btnVolver.addEventListener("click", volverConsultaInterno);
    
    mostrarVistaInicialConsulta();
    console.log("🎉 Consulta.js inicializado correctamente");
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initConsulta);
} else {
    initConsulta();
}
