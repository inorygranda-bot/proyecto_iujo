
let nivelActual = "";
let idActual = null;
let empresaActual = "";
let deptoActual = "";
let idEmpleadoActual = null;

let fechaActual = new Date();
let fechaGlobal = null;

let datosSistema = {};

const CAL_ID_GLOBAL = "_";

function asegurarCalendarios() {
    if (!datosSistema.calendarios) {
        datosSistema.calendarios = {};
    }
    if (!datosSistema.calendarios.general) {
        datosSistema.calendarios.general = {};
    }
    if (!datosSistema.calendarios.empresas) {
        datosSistema.calendarios.empresas = {};
    }
    if (!datosSistema.calendarios.departamentos) {
        datosSistema.calendarios.departamentos = {};
    }
    if (!datosSistema.calendarios.empleados) {
        datosSistema.calendarios.empleados = {};
    }
    if (!datosSistema.calendarios.horarios) {
        datosSistema.calendarios.horarios = {
            general: {},
            empresas: {},
            departamentos: {},
            empleados: {}
        };
    }
    if (!datosSistema.calendarios.horarios.empleados) {
        datosSistema.calendarios.horarios.empleados = {};
    }

    const capas = ["general", "empresas", "departamentos", "empleados"];

    if (!datosSistema.calendarios.horariosFecha) {
        datosSistema.calendarios.horariosFecha = {};
    }
    if (!datosSistema.calendarios.horariosSemana) {
        datosSistema.calendarios.horariosSemana = {};
    }
    if (!datosSistema.calendarios.feriadosSemana) {
        datosSistema.calendarios.feriadosSemana = {};
    }

    capas.forEach(function (k) {
        if (!datosSistema.calendarios.horariosFecha[k]) {
            datosSistema.calendarios.horariosFecha[k] = {};
        }
        if (!datosSistema.calendarios.horariosSemana[k]) {
            datosSistema.calendarios.horariosSemana[k] = {};
        }
        if (!datosSistema.calendarios.feriadosSemana[k]) {
            datosSistema.calendarios.feriadosSemana[k] = {};
        }
    });
}

async function cargarDatosCalendarioDesdeBD() {
    try {
        var respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                accion: "obtener_datos_sistema"
            })
        });

        var resultado = await respuesta.json();

        if (resultado && resultado.ok && resultado.data && resultado.data.datos) {
            datosSistema = resultado.data.datos;
            
            // Convertir arrays a objetos para empresas/departamentos/empleados
            // Porque el backend envía arrays [] pero necesitamos objetos {}
            
            console.log("DEBUG JS: Datos cargados desde BD, antes de convertir:", JSON.parse(JSON.stringify(datosSistema.calendarios.horarios)));
            
            // Convertir horarios.empresas a objeto si es un array

            if (datosSistema.calendarios && datosSistema.calendarios.horarios) {
                ["empresas", "departamentos", "empleados"].forEach(function (capa) {
                    if (datosSistema.calendarios.horarios[capa] && Array.isArray(datosSistema.calendarios.horarios[capa])) {
                        console.log("DEBUG JS: Convertir " + capa + " de array a objeto");
                        var arrayOriginal = datosSistema.calendarios.horarios[capa];
                        var objetoNuevo = {};
                        // Copiar todas las propiedades del array al nuevo objeto
                        for (var key in arrayOriginal) {
                            if (arrayOriginal.hasOwnProperty(key)) {
                                objetoNuevo[key] = arrayOriginal[key];
                            }
                        }
                        datosSistema.calendarios.horarios[capa] = objetoNuevo;
                    }
                });
            }
            
            console.log("DEBUG JS: Datos después de convertir:", JSON.parse(JSON.stringify(datosSistema.calendarios.horarios)));
        } else {
            var mensaje = resultado && resultado.mensaje ? resultado.mensaje : "Respuesta inesperada del servidor.";
            console.error("Error al cargar datos del sistema:", mensaje);
        }
    } catch (error) {
        console.error("Error de conexión al cargar datos del calendario:", error);
    }

    asegurarCalendarios();
}

async function guardarDatosCalendario() {
    console.log("DEBUG JS: Inicia guardarDatosCalendario");
    asegurarCalendarios();

    var datosParaEnviar = JSON.parse(JSON.stringify(datosSistema));
    
    // Forzar que sean objetos
    if (datosParaEnviar.calendarios && datosParaEnviar.calendarios.horarios) {
        ["empresas", "departamentos", "empleados"].forEach(function (capa) {
            // Si es un array, convertirlo a objeto
            if (Array.isArray(datosParaEnviar.calendarios.horarios[capa])) {
                console.log("DEBUG JS: Convertir " + capa + " de array a objeto antes de enviar");
                var arrayOriginal = datosParaEnviar.calendarios.horarios[capa];
                var objetoNuevo = {};
                for (var key in arrayOriginal) {
                    if (arrayOriginal.hasOwnProperty(key)) {
                        objetoNuevo[key] = arrayOriginal[key];
                    }
                }
                datosParaEnviar.calendarios.horarios[capa] = objetoNuevo;
            }
        });
    }

    var datosAEnviar = JSON.stringify(datosParaEnviar);
    console.log("DEBUG JS: datosSistema a enviar (JSON):", datosAEnviar);
    console.log("DEBUG JS: datosParaEnviar.calendarios.horarios.empresas:", datosParaEnviar.calendarios.horarios.empresas);
    console.log("DEBUG JS: datosParaEnviar.calendarios.horarios.departamentos:", datosParaEnviar.calendarios.horarios.departamentos);
    console.log("DEBUG JS: datosParaEnviar.calendarios.horarios.empleados:", datosParaEnviar.calendarios.horarios.empleados);

    try {
        var respuesta = await fetch("Controlador/API/gestion_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                accion: "guardar_datos_sistema",
                datos: datosAEnviar
            })
        });

        var resultado = await respuesta.json();
        console.log("DEBUG JS: Respuesta del servidor:", resultado);

        if (!resultado || !resultado.ok) {
            throw new Error(resultado && resultado.mensaje ? resultado.mensaje : "No se pudo guardar en base de datos.");
        }
    } catch (error) {
        console.error("Error al guardar calendario:", error);
    }
}

function calIdEntidadActiva() {
    if (nivelActual === "general") return CAL_ID_GLOBAL;
    if (nivelActual === "empresas") return empresaActual || CAL_ID_GLOBAL;
    if (nivelActual === "departamentos") return deptoActual || CAL_ID_GLOBAL;
    if (nivelActual === "empleados") return idEmpleadoActual || CAL_ID_GLOBAL;
    return CAL_ID_GLOBAL;
}

function calCapaNivel() {
    if (nivelActual === "general") return "general";
    if (nivelActual === "empresas") return "empresas";
    if (nivelActual === "departamentos") return "departamentos";
    if (nivelActual === "empleados") return "empleados";
    return "general";
}

function keyHorarioActual() {
    if (nivelActual === "general") return { tipo: "general", id: "general" };
    if (nivelActual === "empresas") return { tipo: "empresas", id: empresaActual };
    if (nivelActual === "departamentos") return { tipo: "departamentos", id: deptoActual };
    if (nivelActual === "empleados") return { tipo: "empleados", id: idEmpleadoActual };
    return { tipo: "general", id: "general" };
}

function calRellenarSelectContexto(origen) {
    var selE = document.getElementById("CalSelEmpresa");
    var selD = document.getElementById("CalSelDepto");
    var selEm = document.getElementById("CalSelEmpleado");

    if (!selE || !selD || !selEm) {
        return;
    }

    if (!origen || origen === "inicial") {

        selE.innerHTML = '<option value="">— Seleccione una Empresa —</option>';

        var empresas = (Array.isArray(datosSistema.empresas)) ? datosSistema.empresas : [];

        if (empresas.length === 0) {

            selE.innerHTML += '<option value="" disabled>No hay empresas registradas</option>';
        } else {

            for (var i = 0; i < empresas.length; i++) {
                var emp = empresas[i];
                var opt = document.createElement("option");
                opt.value = emp.nombre;
                opt.textContent = emp.nombre;
                selE.appendChild(opt);
            }
        }

        selD.innerHTML = '<option value="">— Elija departamento —</option>';
        selD.disabled = true;

        selEm.innerHTML = '<option value="">— Elija empleado —</option>';
        selEm.disabled = true;

        var btnAplicar = document.querySelector(".ctx-aplicar");
        if (btnAplicar) {
            btnAplicar.disabled = true;
        }

        return;
    }

    if (origen === "empresa") {
        var empSeleccionada = selE.value;

        selD.innerHTML = '<option value="">— Elija departamento —</option>';
        selEm.innerHTML = '<option value="">— Elija empleado —</option>';
        selEm.disabled = true;

        if (empSeleccionada !== "") {
            selD.disabled = false;

            var todosLosDeptos = (Array.isArray(datosSistema.departamentos)) ? datosSistema.departamentos : [];

            var filtrados = [];
            for (var j = 0; j < todosLosDeptos.length; j++) {
                var depto = todosLosDeptos[j];
                var nombreEmpresaDepto = (depto.empresa || "").trim().toLowerCase();
                var nombreEmpresaSel = empSeleccionada.trim().toLowerCase();

                if (nombreEmpresaDepto === nombreEmpresaSel) {
                    filtrados.push(depto);
                }
            }

            if (filtrados.length === 0) {
                selD.innerHTML += '<option value="" disabled>No hay departamentos para esta empresa</option>';
            } else {
                for (var k = 0; k < filtrados.length; k++) {
                    var d = filtrados[k];
                    var optDepto = document.createElement("option");
                    optDepto.value = d.nombre;
                    optDepto.textContent = d.nombre;
                    selD.appendChild(optDepto);
                }
            }
        } else {
            selD.disabled = true;
        }
    }

    if (origen === "depto") {
        var empActual = selE.value;
        var deptoSeleccionado = selD.value;

        selEm.innerHTML = '<option value="">— Elija empleado —</option>';

        if (deptoSeleccionado !== "") {
            selEm.disabled = false;

            var todosLosEmpleados = (Array.isArray(datosSistema.empleados)) ? datosSistema.empleados : [];

            var filtradosEm = [];
            for (var m = 0; m < todosLosEmpleados.length; m++) {
                var empleado = todosLosEmpleados[m];
                var empEmpleado = (empleado.empresa || "").trim().toLowerCase();
                var deptoEmpleado = (empleado.depto || "").trim().toLowerCase();
                var empSel = empActual.trim().toLowerCase();
                var deptoSel = deptoSeleccionado.trim().toLowerCase();

                if (empEmpleado === empSel && deptoEmpleado === deptoSel) {
                    filtradosEm.push(empleado);
                }
            }

            if (filtradosEm.length === 0) {
                selEm.innerHTML += '<option value="" disabled>No hay empleados en este departamento</option>';
            } else {
                for (var n = 0; n < filtradosEm.length; n++) {
                    var e = filtradosEm[n];
                    var textoEmpleado = (e.nombres || "") + " " + (e.apellidos || "");
                    var optEmpleado = document.createElement("option");
                    optEmpleado.value = e.cedula || "";
                    optEmpleado.textContent = textoEmpleado.trim();
                    selEm.appendChild(optEmpleado);
                }
            }
        } else {
            selEm.disabled = true;
        }
    }

    // Habilitar/deshabilitar botón Aplicar según haya empresa seleccionada
    var btnAplicarFinal = document.querySelector(".ctx-aplicar");
    if (btnAplicarFinal) {
        btnAplicarFinal.disabled = (selE.value === "");
    }
}

function calAplicarContextoDesdeSelects() {
    var elEmp = document.getElementById("CalSelEmpresa");
    var elDep = document.getElementById("CalSelDepto");
    var elCed = document.getElementById("CalSelEmpleado");

    var emp = (elEmp && elEmp.value) ? elEmp.value : "";
    var dep = (elDep && elDep.value) ? elDep.value : "";
    var ced = (elCed && elCed.value) ? elCed.value : "";

    if (emp === "") {
        return;
    }

    empresaActual = emp;
    deptoActual = dep;
    idEmpleadoActual = ced || null;

    if (ced !== "") {

        // Buscar nombre del empleado

        var empleadosArr = (Array.isArray(datosSistema.empleados)) ? datosSistema.empleados : [];
        var empleadoEncontrado = null;
        for (var a = 0; a < empleadosArr.length; a++) {
            if (empleadosArr[a].cedula === ced) {
                empleadoEncontrado = empleadosArr[a];
                break;
            }
        }
        var nom = empleadoEncontrado
            ? ((empleadoEncontrado.nombres || "") + " " + (empleadoEncontrado.apellidos || "")).trim()
            : ced;
        verCalendarioDeEmpleado(ced, nom, dep, emp);
    } else if (dep !== "") {
        verCalendarioDepartamento(dep, emp);
    } else {
        verCalendarioEmpresa(emp);
    }
}

function obtenerHorarioActualParaInputs(fecha) {
    asegurarCalendarios();

    var fs = fecha.toISOString().split("T")[0];
    var dow = String(fecha.getDay());
    var capa = calCapaNivel();
    var idEnt = calIdEntidadActiva();

    var h = null;

    // 1. Buscar horario específico para esa fecha
    if (datosSistema.calendarios.horariosFecha[capa] && datosSistema.calendarios.horariosFecha[capa][idEnt]) {
        h = datosSistema.calendarios.horariosFecha[capa][idEnt][fs];
    }

    // 2. Buscar horario para ese día de la semana
    if (!h) {
        if (datosSistema.calendarios.horariosSemana[capa] && datosSistema.calendarios.horariosSemana[capa][idEnt]) {
            h = datosSistema.calendarios.horariosSemana[capa][idEnt][dow];
        }
    }

    // 3. Buscar horario de la capa actual
    if (!h) {
        var clave = keyHorarioActual();
        var hCapas = datosSistema.calendarios.horarios;
        if (clave.tipo === "general") {
            h = hCapas.general;
        } else {
            h = (hCapas[clave.tipo] && hCapas[clave.tipo][clave.id]) ? hCapas[clave.tipo][clave.id] : null;
            if (!h || !h.desdeM) {
                h = hCapas.general;
            }
        }
    }

    return h || { desdeM: "", hastaM: "", desdeT: "", hastaT: "" };
}

function cargarHorarioActual() {
    var hCapas = datosSistema.calendarios.horarios;
    var r = null;

    if (nivelActual === "empleados" && idEmpleadoActual) {
        r = hCapas.empleados[idEmpleadoActual];
    } else if (nivelActual === "departamentos" && deptoActual) {
        r = hCapas.departamentos[deptoActual];
    } else if (nivelActual === "empresas" && empresaActual) {
        r = hCapas.empresas[empresaActual];
    }

    if (!r || !r.desdeM) {
        r = hCapas.general;
    }

    var inputDesdeM = document.getElementById("HoraDesdeM");
    var inputHastaM = document.getElementById("HoraHastaM");
    var inputDesdeT = document.getElementById("HoraDesdeT");
    var inputHastaT = document.getElementById("HoraHastaT");

    if (inputDesdeM) inputDesdeM.value = (r && r.desdeM) ? r.desdeM : "";
    if (inputHastaM) inputHastaM.value = (r && r.hastaM) ? r.hastaM : "";
    if (inputDesdeT) inputDesdeT.value = (r && r.desdeT) ? r.desdeT : "";
    if (inputHastaT) inputHastaT.value = (r && r.hastaT) ? r.hastaT : "";
}

async function guardarHorarioActual(e) {
    console.log("DEBUG JS: Inicia guardarHorarioActual");
    e.preventDefault();

    var h = {
        desdeM: document.getElementById("HoraDesdeM").value,
        hastaM: document.getElementById("HoraHastaM").value,
        desdeT: document.getElementById("HoraDesdeT").value,
        hastaT: document.getElementById("HoraHastaT").value
    };
    console.log("DEBUG JS: Datos del horario a guardar:", h);

    if (!h.desdeM || !h.hastaM) {
        return alert("El turno de la mañana es obligatorio.");
    }

    var clave = keyHorarioActual();
    console.log("DEBUG JS: clave (tipo y id):", clave);
    console.log("DEBUG JS: nivelActual:", nivelActual);
    console.log("DEBUG JS: empresaActual:", empresaActual);
    console.log("DEBUG JS: deptoActual:", deptoActual);
    console.log("DEBUG JS: idEmpleadoActual:", idEmpleadoActual);

    var capa = datosSistema.calendarios.horarios;
    console.log("DEBUG JS: capa (datosSistema.calendarios.horarios) ANTES de guardar:", JSON.parse(JSON.stringify(capa)));

    if (clave.tipo === "general") {
        capa.general = h;
        console.log("DEBUG JS: Guardado en capa.general");
    } else if (clave.tipo === "empresas") {
        // Asegurar que exista el objeto empresas
        if (!capa.empresas) capa.empresas = {};
        capa.empresas[clave.id] = h;
        console.log("DEBUG JS: Guardado en capa.empresas['" + clave.id + "']");
    } else if (clave.tipo === "departamentos") {
        // Asegurar que exista el objeto departamentos
        if (!capa.departamentos) capa.departamentos = {};
        capa.departamentos[clave.id] = h;
        console.log("DEBUG JS: Guardado en capa.departamentos['" + clave.id + "']");
    } else if (clave.tipo === "empleados") {
        // Asegurar que exista el objeto empleados
        if (!capa.empleados) capa.empleados = {};
        capa.empleados[clave.id] = h;
        console.log("DEBUG JS: Guardado en capa.empleados['" + clave.id + "']");
    }

    console.log("DEBUG JS: capa DESPUÉS de guardar:", JSON.parse(JSON.stringify(capa)));
    console.log("DEBUG JS: datosSistema completo para enviar:", JSON.parse(JSON.stringify(datosSistema)));

    if (typeof window.registrarAuditoria === "function") {
        window.registrarAuditoria(
            "Guardó Horario",
            "Horario " + clave.tipo + " para ID: " + clave.id + " (Mañana: " + h.desdeM + "-" + h.hastaM + ", Tarde: " + h.desdeT + "-" + h.hastaT + ")"
        );
    }

    await guardarDatosCalendario();
    renderizarCalendario();
    alert("Horarios guardados correctamente.");
}


function normalizarFeriadoValor(v) {
    if (v === null || v === undefined) return null;
    if (typeof v === "string") return { motivo: v, laboral: false };
    if (typeof v === "object" && v.hasOwnProperty("motivo")) {
        return { motivo: String(v.motivo), laboral: !!v.laboral };
    }
    return null;
}

function leerFeriadoEnCapa(capa, idEnt, fs, dowStr) {
    var c = datosSistema.calendarios;

    if (capa === "general") {
        var porFecha = c.general[fs];
        var n1 = normalizarFeriadoValor(porFecha);
        if (n1) return { motivo: n1.motivo, laboral: n1.laboral, capa: "general", idEnt: CAL_ID_GLOBAL, origen: "fecha" };

        var sem = (c.feriadosSemana && c.feriadosSemana.general && c.feriadosSemana.general[CAL_ID_GLOBAL])
            ? c.feriadosSemana.general[CAL_ID_GLOBAL][dowStr]
            : null;
        var n2 = normalizarFeriadoValor(sem);
        if (n2) return { motivo: n2.motivo, laboral: n2.laboral, capa: "general", idEnt: CAL_ID_GLOBAL, origen: "semana" };

        return null;
    }

    var mapa = c[capa];
    if (!mapa || !idEnt) return null;

    var porFechaCapa = (mapa[idEnt]) ? mapa[idEnt][fs] : null;
    var n1c = normalizarFeriadoValor(porFechaCapa);
    if (n1c) return { motivo: n1c.motivo, laboral: n1c.laboral, capa: capa, idEnt: idEnt, origen: "fecha" };

    var semCapa = (c.feriadosSemana && c.feriadosSemana[capa] && c.feriadosSemana[capa][idEnt])
        ? c.feriadosSemana[capa][idEnt][dowStr]
        : null;
    var n2c = normalizarFeriadoValor(semCapa);
    if (n2c) return { motivo: n2c.motivo, laboral: n2c.laboral, capa: capa, idEnt: idEnt, origen: "semana" };

    return null;
}

function obtenerEstadoDia(fecha) {
    var fs = fecha.toISOString().split("T")[0];
    var dowStr = String(fecha.getDay());

    var hallado = null;

    if (idEmpleadoActual) {
        hallado = leerFeriadoEnCapa("empleados", idEmpleadoActual, fs, dowStr);
    }
    if (!hallado && deptoActual) {
        hallado = leerFeriadoEnCapa("departamentos", deptoActual, fs, dowStr);
    }
    if (!hallado && empresaActual) {
        hallado = leerFeriadoEnCapa("empresas", empresaActual, fs, dowStr);
    }
    if (!hallado) {
        hallado = leerFeriadoEnCapa("general", CAL_ID_GLOBAL, fs, dowStr);
    }

    if (hallado) {
        return {
            tipo: hallado.capa,
            motivo: hallado.motivo,
            laboral: hallado.laboral,
            origen: hallado.origen,
            capa: hallado.capa,
            idEnt: hallado.idEnt
        };
    }

    return { tipo: "laboral", motivo: "", laboral: true, origen: null, capa: null, idEnt: null };
}

function textoHorarioParaDia(fecha) {
    asegurarCalendarios();
    var fs = fecha.toISOString().split("T")[0];
    var dow = String(fecha.getDay());
    var capa = calCapaNivel();
    var idEnt = calIdEntidadActiva();

    var h = null;

    if (datosSistema.calendarios.horariosFecha[capa] && datosSistema.calendarios.horariosFecha[capa][idEnt]) {
        h = datosSistema.calendarios.horariosFecha[capa][idEnt][fs];
    }
    if (!h && datosSistema.calendarios.horariosSemana[capa] && datosSistema.calendarios.horariosSemana[capa][idEnt]) {
        h = datosSistema.calendarios.horariosSemana[capa][idEnt][dow];
    }
    if (!h) {
        var clave = keyHorarioActual();
        var hCapas = datosSistema.calendarios.horarios;
        if (clave.tipo === "general") {
            h = hCapas.general;
        } else {
            h = (hCapas[clave.tipo] && hCapas[clave.tipo][clave.id]) ? hCapas[clave.tipo][clave.id] : hCapas.general;
        }
    }

    if (h && h.desdeM && h.hastaM) {
        var texto = h.desdeM + "-" + h.hastaM;
        if (h.desdeT && h.hastaT) {
            texto += "<br>" + h.desdeT + "-" + h.hastaT;
        }
        return texto;
    }

    return "";
}

function guardarFeriadoEnCapa(capa, idEnt, fs, dowStr, motivo, laboral, soloEstaFecha) {
    asegurarCalendarios();
    var val = { motivo: motivo, laboral: laboral };

    if (!datosSistema.calendarios.feriadosSemana[capa]) {
        datosSistema.calendarios.feriadosSemana[capa] = {};
    }
    if (!datosSistema.calendarios.feriadosSemana[capa][idEnt]) {
        datosSistema.calendarios.feriadosSemana[capa][idEnt] = {};
    }

    if (soloEstaFecha) {
        if (capa === "general") {
            datosSistema.calendarios.general[fs] = val;
        } else {
            if (!datosSistema.calendarios[capa]) {
                datosSistema.calendarios[capa] = {};
            }
            if (!datosSistema.calendarios[capa][idEnt]) {
                datosSistema.calendarios[capa][idEnt] = {};
            }
            datosSistema.calendarios[capa][idEnt][fs] = val;
        }
    } else {
        datosSistema.calendarios.feriadosSemana[capa][idEnt][dowStr] = val;
    }
}

function eliminarFeriadoActivo(fecha, estado) {
    asegurarCalendarios();
    var fs = fecha.toISOString().split("T")[0];
    var dowStr = String(fecha.getDay());
    var capaActual = calCapaNivel();
    var idEntActual = calIdEntidadActiva();

    if (estado.capa !== capaActual || String(estado.idEnt) !== String(idEntActual)) {
        guardarFeriadoEnCapa(capaActual, idEntActual, fs, dowStr, "", true, true);
    } else {
        if (estado.motivo === "" && estado.laboral === true && estado.origen !== "semana") {
            // Override local, no hacer nada extra
        } else {
            if (estado.origen === "semana") {
                if (datosSistema.calendarios.feriadosSemana[capaActual] && datosSistema.calendarios.feriadosSemana[capaActual][idEntActual]) {
                    delete datosSistema.calendarios.feriadosSemana[capaActual][idEntActual][dowStr];
                }
            } else if (capaActual === "general") {
                delete datosSistema.calendarios.general[fs];
            } else if (datosSistema.calendarios[capaActual] && datosSistema.calendarios[capaActual][idEntActual]) {
                delete datosSistema.calendarios[capaActual][idEntActual][fs];
            }
        }
    }

    guardarDatosCalendario();
    renderizarCalendario();
}

function actualizarFeriadoExistente(fecha, estado, motivo, laboral) {
    asegurarCalendarios();
    var fs = fecha.toISOString().split("T")[0];
    var dowStr = String(fecha.getDay());
    var capa = estado.capa;
    var idEnt = estado.idEnt;
    var val = { motivo: String(motivo).trim(), laboral: !!laboral };

    if (estado.origen === "semana") {
        if (!datosSistema.calendarios.feriadosSemana[capa]) {
            datosSistema.calendarios.feriadosSemana[capa] = {};
        }
        if (!datosSistema.calendarios.feriadosSemana[capa][idEnt]) {
            datosSistema.calendarios.feriadosSemana[capa][idEnt] = {};
        }
        datosSistema.calendarios.feriadosSemana[capa][idEnt][dowStr] = val;
    } else if (capa === "general") {
        datosSistema.calendarios.general[fs] = val;
    } else {
        if (!datosSistema.calendarios[capa]) {
            datosSistema.calendarios[capa] = {};
        }
        if (!datosSistema.calendarios[capa][idEnt]) {
            datosSistema.calendarios[capa][idEnt] = {};
        }
        datosSistema.calendarios[capa][idEnt][fs] = val;
    }

    guardarDatosCalendario();
    renderizarCalendario();
}

function guardarHorarioDia(fecha, soloEstaFecha) {
    asegurarCalendarios();
    var capa = calCapaNivel();
    var idEnt = calIdEntidadActiva();
    var fs = fecha.toISOString().split("T")[0];
    var dow = String(fecha.getDay());

    var nuevoHorario = {
        desdeM: document.getElementById("m1").value,
        hastaM: document.getElementById("m2").value,
        desdeT: document.getElementById("t1").value,
        hastaT: document.getElementById("t2").value
    };

    if (!nuevoHorario.desdeM || !nuevoHorario.hastaM) {
        return alert("El turno de la mañana es obligatorio para asignar un horario.");
    }

    if (!datosSistema.calendarios.horariosFecha[capa][idEnt]) {
        datosSistema.calendarios.horariosFecha[capa][idEnt] = {};
    }
    if (!datosSistema.calendarios.horariosSemana[capa][idEnt]) {
        datosSistema.calendarios.horariosSemana[capa][idEnt] = {};
    }

    if (soloEstaFecha) {
        datosSistema.calendarios.horariosFecha[capa][idEnt][fs] = nuevoHorario;
    } else {
        datosSistema.calendarios.horariosSemana[capa][idEnt][dow] = nuevoHorario;
    }

    guardarDatosCalendario();
    renderizarCalendario();
}

function eliminarHorarioDia(fecha) {
    asegurarCalendarios();
    var capa = calCapaNivel();
    var idEnt = calIdEntidadActiva();
    var fs = fecha.toISOString().split("T")[0];
    var dowStr = String(fecha.getDay());

    if (datosSistema.calendarios.horariosFecha[capa] && datosSistema.calendarios.horariosFecha[capa][idEnt]) {
        delete datosSistema.calendarios.horariosFecha[capa][idEnt][fs];
    }
    if (datosSistema.calendarios.horariosSemana[capa] && datosSistema.calendarios.horariosSemana[capa][idEnt]) {
        delete datosSistema.calendarios.horariosSemana[capa][idEnt][dowStr];
    }

    guardarDatosCalendario();
    renderizarCalendario();
}

function abrirEditorDia(fecha, estado) {
    fechaGlobal = fecha;
    var fs = fecha.toISOString().split("T")[0];
    var modal = document.getElementById("editor");
    var btnEliminar = document.getElementById("borrarEvento");

    document.getElementById("fecha").textContent = fs;
    document.getElementById("motivo").value = estado.motivo || "";
    document.getElementById("laboral").checked = estado.laboral;

    if (estado.origen || (estado.motivo && estado.motivo.trim() !== "")) {
        btnEliminar.style.display = "block";
    } else {
        btnEliminar.style.display = "none";
    }

    btnEliminar.onclick = function () {
        eliminarFeriadoActivo(fecha, estado);
        cerrar();
    };

    var h = obtenerHorarioActualParaInputs(fecha);
    document.getElementById("m1").value = h.desdeM || "";
    document.getElementById("m2").value = h.hastaM || "";
    document.getElementById("t1").value = h.desdeT || "";
    document.getElementById("t2").value = h.hastaT || "";

    document.getElementById("guardarDia").onclick = function () {
        procesarGuardado(true);
    };
    document.getElementById("guardarSiempre").onclick = function () {
        procesarGuardado(false);
    };

    modal.showModal();
}

function cerrar() {
    document.getElementById("editor").close();
}

async function procesarGuardado(esUnico) {
    var mot = document.getElementById("motivo").value;
    var lab = document.getElementById("laboral").checked;
    var fs = fechaGlobal.toISOString().split("T")[0];
    var dow = String(fechaGlobal.getDay());

    var capaDestino = calCapaNivel();
    var idEntDestino = calIdEntidadActiva();

    guardarFeriadoEnCapa(capaDestino, idEntDestino, fs, dow, mot, lab, esUnico);

    var h1 = document.getElementById("m1").value;
    var h2 = document.getElementById("m2").value;

    if (h1 && h2) {
        var nuevo = {
            desdeM: h1,
            hastaM: h2,
            desdeT: document.getElementById("t1").value,
            hastaT: document.getElementById("t2").value
        };

        if (esUnico) {
            if (!datosSistema.calendarios.horariosFecha[capaDestino][idEntDestino]) {
                datosSistema.calendarios.horariosFecha[capaDestino][idEntDestino] = {};
            }
            datosSistema.calendarios.horariosFecha[capaDestino][idEntDestino][fs] = nuevo;
        } else {
            if (!datosSistema.calendarios.horariosSemana[capaDestino][idEntDestino]) {
                datosSistema.calendarios.horariosSemana[capaDestino][idEntDestino] = {};
            }
            datosSistema.calendarios.horariosSemana[capaDestino][idEntDestino][dow] = nuevo;
        }
    }

    await guardarDatosCalendario();
    renderizarCalendario();
    cerrar();
}

function renderizarCalendario() {
    var cont = document.getElementById("CuadriculaCalendario");
    if (!cont) return;

    if (!nivelActual || nivelActual === "") {
        cont.innerHTML = "<p style='padding:20px; text-align:center; color: #666;'>Seleccione una empresa para ver el calendario.</p>";
        return;
    }

    var mes = fechaActual.getMonth();
    var ano = fechaActual.getFullYear();

    var nombresMeses = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    var etiqueta = document.getElementById("TextoMesAno");
    if (etiqueta) {
        etiqueta.textContent = nombresMeses[mes] + " " + ano;
    }

    cont.innerHTML = "";

    var primerDiaSemana = new Date(ano, mes, 1).getDay();
    var blancos = (primerDiaSemana === 0) ? 6 : primerDiaSemana - 1;

    for (var i = 0; i < blancos; i++) {
        var celdaVacia = document.createElement("article");
        celdaVacia.className = "DiaCalendario vacio";
        cont.appendChild(celdaVacia);
    }

    var ultimoDiaMes = new Date(ano, mes + 1, 0).getDate();

    for (var d = 1; d <= ultimoDiaMes; d++) {
        var fechaDia = new Date(ano, mes, d);
        var estado = obtenerEstadoDia(fechaDia);
        var celda = document.createElement("article");
        celda.className = "DiaCalendario";

        if (estado.motivo && estado.motivo.trim() !== "") {
            celda.classList.add(estado.laboral ? "feriado-laboral" : "feriado");
        }

        var horarioTexto = "";
        if (estado.tipo === "laboral" || estado.laboral === true) {
            horarioTexto = textoHorarioParaDia(fechaDia);
        }

        var subInfo = horarioTexto ? '<small class="dia-horario-extra">' + horarioTexto + '</small>' : "";
        celda.innerHTML = "<time>" + d + "</time><p>" + (estado.motivo || "") + "</p>" + subInfo;

        celda.onclick = (function (dia, mesRef, anoRef, estadoRef) {
            return function (e) {
                e.preventDefault();
                abrirEditorDia(new Date(anoRef, mesRef, dia), estadoRef);
            };
        })(d, mes, ano, estado);

        cont.appendChild(celda);
    }

    cargarHorarioActual();
}

/*
 * ============================================================
 * NAVEGACIÓN Y VISTAS
 * ============================================================
 */

function mostrarCalendario() {
    var vista = document.getElementById("Calendario");
    if (!vista) return;

    var consulta = document.getElementById("Consulta");
    if (consulta) {
        consulta.style.setProperty("display", "none", "important");
    }

    vista.style.display = "block";
    calRellenarSelectContexto("inicial");
    renderizarCalendario();
}

function cerrarCalendario() {
    var vistaConsulta = document.getElementById("Consulta");
    var vistaCalendario = document.getElementById("Calendario");

    if (vistaConsulta && vistaCalendario) {
        vistaCalendario.style.display = "none";
        vistaConsulta.style.display = "flex";
        if (typeof renderizarListaEmpresas === "function") {
            renderizarListaEmpresas();
        }
        return;
    }

    window.location.href = "index.php";
}

function cambiarMes(delta) {
    fechaActual.setMonth(fechaActual.getMonth() + delta);
    renderizarCalendario();
}

function verCalendarioGeneral() {
    nivelActual = "general";
    idActual = null;
    empresaActual = "";
    deptoActual = "";
    idEmpleadoActual = null;
    document.getElementById("EtiquetaPrincipal").textContent = "Calendario maestro";
    document.getElementById("EtiquetaSubtitulo").textContent = "Nivel: Global";
    mostrarCalendario();
}

function verCalendarioEmpresa(nombreEmpresa) {
    nivelActual = "empresas";
    idActual = nombreEmpresa;
    empresaActual = nombreEmpresa;
    deptoActual = "";
    idEmpleadoActual = null;
    document.getElementById("EtiquetaPrincipal").textContent = "Calendario de: " + nombreEmpresa;
    document.getElementById("EtiquetaSubtitulo").textContent = "Nivel: Empresa";
    mostrarCalendario();
}

function verCalendarioDepartamento(nombreDepto, nombreEmpresa) {
    nivelActual = "departamentos";
    idActual = nombreDepto;
    deptoActual = nombreDepto;
    empresaActual = nombreEmpresa;
    idEmpleadoActual = null;
    document.getElementById("EtiquetaPrincipal").textContent = "Calendario de: " + nombreDepto;
    document.getElementById("EtiquetaSubtitulo").textContent = "Nivel: Departamento (" + nombreEmpresa + ")";
    mostrarCalendario();
}

function verCalendarioDeEmpleado(cedula, nombreCompleto, nombreDepto, nombreEmpresa) {
    nivelActual = "empleados";
    idActual = cedula;
    idEmpleadoActual = cedula;
    deptoActual = nombreDepto;
    empresaActual = nombreEmpresa;
    document.getElementById("EtiquetaPrincipal").textContent = "Calendario de: " + nombreCompleto;
    document.getElementById("EtiquetaSubtitulo").textContent = "Nivel: Empleado (" + nombreDepto + ")";
    mostrarCalendario();
}

async function sincronizarConGoogle() {
    // Validar que haya una empresa seleccionada
    if (!empresaActual || nivelActual === "" || nivelActual === "general") {
        return alert("Por favor, seleccione primero una empresa en '¿Para quién programa?' y pulse 'Aplicar selección'.");
    }

    var btn = document.getElementById("BtnSincronizarGoogle");
    if (!btn) return;

    var textoOriginal = btn.textContent;
    btn.textContent = "Sincronizando...";
    btn.disabled = true;

    try {
        // Usar ruta ABSOLUTA desde la raíz del proyecto
        var url = "Controlador/Integraciones/sincronizar_google.php?nivel=" + encodeURIComponent(nivelActual) + "&id=" + encodeURIComponent(calIdEntidadActiva());

        var respuesta = await fetch(url);
        var resultado = await respuesta.json();

        if (resultado.success === true) {
            // Actualizar los datos locales con los eventos de Google
            actualizarDatosLocalesDesdeGoogle(resultado.eventos);
            alert("¡Sincronización exitosa! Se cargaron " + (resultado.total_eventos || resultado.eventos.length || 0) + " feriados desde Google Calendar.");
            renderizarCalendario();
        } else {
            var mensaje = resultado.message || "Error desconocido al sincronizar.";
            alert("Error al sincronizar: " + mensaje);
        }
    } catch (error) {
        console.error("Error en la sincronización con Google:", error);
        alert("No se pudo conectar con el servidor de sincronización. Revise su conexión a internet.");
    } finally {
        btn.textContent = textoOriginal;
        btn.disabled = false;
    }
}

function actualizarDatosLocalesDesdeGoogle(eventos) {
    asegurarCalendarios();
    var capa = calCapaNivel();
    var idEnt = calIdEntidadActiva();

    // Limpiar eventos anteriores de esta capa
    if (capa === "general") {
        datosSistema.calendarios.general = {};
    } else {
        if (!datosSistema.calendarios[capa]) {
            datosSistema.calendarios[capa] = {};
        }
        datosSistema.calendarios[capa][idEnt] = {};
    }

    // Insertar los nuevos eventos
    for (var i = 0; i < eventos.length; i++) {
        var ev = eventos[i];
        var val = {
            motivo: ev.summary || "Sin nombre",
            laboral: ev.laboral || false
        };

        if (capa === "general") {
            datosSistema.calendarios.general[ev.fecha] = val;
        } else {
            datosSistema.calendarios[capa][idEnt][ev.fecha] = val;
        }
    }

    guardarDatosCalendario();
}


document.addEventListener("DOMContentLoaded", async function () {

    // 1. Cargar datos desde la base de datos
    await cargarDatosCalendarioDesdeBD();

    // 2. Asegurar la estructura de calendarios
    asegurarCalendarios();

    // 3. Resetear estado
    nivelActual = "";
    empresaActual = "";
    deptoActual = "";
    idActual = null;
    idEmpleadoActual = null;

    // 4. Configurar eventos de los selects
    var selE = document.getElementById("CalSelEmpresa");
    var selD = document.getElementById("CalSelDepto");
    var selEm = document.getElementById("CalSelEmpleado");

    if (selE) {
        selE.onchange = function () {
            calRellenarSelectContexto("empresa");
        };
    }

    if (selD) {
        selD.onchange = function () {
            calRellenarSelectContexto("depto");
        };
    }

    // 5. Llenar los selects con los datos cargados
    calRellenarSelectContexto("inicial");

    // 6. Configurar el formulario de horario
    var formHorario = document.getElementById("FormHorarioLaboral");
    if (formHorario) {
        formHorario.addEventListener("submit", guardarHorarioActual);
    }

    // 7. Si estamos en la página de Horarios, mostrar el calendario
    var esPaginaHorarios = window.location.href.indexOf("p=horarios") !== -1;

    if (esPaginaHorarios) {
        var vistaCalendario = document.getElementById("Calendario");
        if (vistaCalendario) {
            vistaCalendario.style.display = "block";
        }
        document.getElementById("EtiquetaPrincipal").textContent = "Gestión de Asistencia";
        document.getElementById("EtiquetaSubtitulo").textContent = "Seleccione una empresa para comenzar";
    }
});
