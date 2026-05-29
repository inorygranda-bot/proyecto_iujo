// ============================================================================
// MÓDULO DE INCIDENCIAS (ACTUALIZADO - TODAS LAS FUNCIONES)
// ============================================================================

var datosSistemaIncidencias = {
    tiposIncidencia: [],
    empleados: [],
    asistencias: [],
    inasistencias: [],
    incidencias: []
};

document.addEventListener('DOMContentLoaded', async () => {
        console.log('Módulo Incidencias inicializado');

        inicializarPestañas();
        inicializarFormularioCrearIncidencia();
        inicializarFormularioEditarIncidencia();
        inicializarFormularioAsignarIncidencia();
        await Promise.all([
            cargarTiposIncidencia(),
            cargarDatosSistema()
        ]);

        var hoy = new Date().toISOString().split('T')[0];
        var haceUnMes = new Date();
        haceUnMes.setMonth(haceUnMes.getMonth() - 1);
        var haceUnMesStr = haceUnMes.toISOString().split('T')[0];

        document.getElementById('asistencias-fecha-inicio').value = haceUnMesStr;
        document.getElementById('asistencias-fecha-fin').value = hoy;
        document.getElementById('inasistencias-fecha-inicio').value = haceUnMesStr;
        document.getElementById('inasistencias-fecha-fin').value = hoy;
        document.getElementById('reporte-fecha-inicio').value = haceUnMesStr;
        document.getElementById('reporte-fecha-fin').value = hoy;

        // Lógica para el input de archivo personalizado
        const fileInput = document.getElementById('input-archivo-txt-hidden');
        const customButton = document.getElementById('custom-file-button');
        const fileNameSpan = document.getElementById('file-name');

        if (fileInput && customButton && fileNameSpan) {
            customButton.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    fileNameSpan.textContent = fileInput.files[0].name;
                } else {
                    fileNameSpan.textContent = 'Ningún archivo seleccionado';
                }
            });
        }
    });

// ============================================================================
// PESTAÑAS DE NAVEGACIÓN
// ============================================================================

function inicializarPestañas() {
    const botones = document.querySelectorAll('.ReportesPestaña');
    botones.forEach(boton => {
        boton.addEventListener('click', () => {
            cambiarPestaña(boton);
        });
    });
}

function cambiarPestaña(botonActivo) {
    document.querySelectorAll('.ReportesPestaña').forEach(boton => {
        boton.classList.remove('ReportesPestaña--activa');
    });
    botonActivo.classList.add('ReportesPestaña--activa');

    document.querySelectorAll('.ReportesPanel').forEach(panel => {
        panel.hidden = true;
    });

    const panelId = botonActivo.getAttribute('data-panel');
    const panel = document.getElementById(panelId);
    if (panel) {
        panel.hidden = false;
    }

    if (panelId === 'panelAsistencias') {
        cargarAsistencias();
    } else if (panelId === 'panelInasistencias') {
        cargarInasistencias();
    } else if (panelId === 'panelIncidencias') {
        cargarTiposIncidencia();
    }
}

// ============================================================================
// IMPORTACIÓN DE TXT
// ============================================================================

async function importarTXT() {
    const inputArchivo = document.getElementById('input-archivo-txt-hidden');
    const archivo = inputArchivo.files[0];

    if (!archivo) {
        alert('Por favor, selecciona un archivo TXT.');
        return;
    }

    var formData = new FormData();
    formData.append('accion', 'importar_txt_asistencia');
    formData.append('archivo', archivo);

    try {
        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        mostrarResultadoImportacion(resultado);
        inputArchivo.value = '';
    } catch (error) {
        console.error('Error al importar TXT:', error);
        alert('Error al importar el archivo.');
    }
}

function mostrarResultadoImportacion(resultado) {
        console.log('Respuesta de la API (importarTXT):', resultado);
        const contenedor = document.getElementById('resultado-importacion');
        const contenido = document.getElementById('resultado-importacion-contenido');

    var html = '';
    html += '<p><strong>Total registros leídos:</strong> ' + resultado.data.total_registros + '</p>';
    html += '<p><strong>Asistencias guardadas correctamente:</strong> ' + resultado.data.importados_correctamente + '</p>';

    if (resultado.data.errores && resultado.data.errores.length > 0) {
        html += '<section class="alert alert-danger mt-3">';
        html += '<strong>Errores encontrados:</strong>';
        html += '<ul>';
        resultado.data.errores.forEach(error => {
            html += '<li>' + error + '</li>';
        });
        html += '</ul>';
        html += '</section>';
    } else if (resultado.ok) { // Si no hay errores y la operación fue ok
        html += '<section class="alert alert-success mt-3">';
        html += '<strong>Importación exitosa:</strong>';
        html += '<p>Todos los registros fueron procesados sin errores.</p>';
        html += '</section>';
    }

    contenido.innerHTML = html;
    contenedor.hidden = false;
}

// ============================================================================
// CARGA DE DATOS
// ============================================================================

async function cargarTiposIncidencia() {
    try {
        var formData = new FormData();
        formData.append('accion', 'obtener_tipos_incidencia');

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        if (resultado.ok && resultado.data.tipos) {
            datosSistemaIncidencias.tiposIncidencia = resultado.data.tipos;
            renderizarTablaTiposIncidencia();

            var selectTipo = document.getElementById('asignar-tipo-incidencia');
            if (selectTipo) {
                selectTipo.innerHTML = '<option value="">Selecciona un tipo de incidencia</option>';
                datosSistemaIncidencias.tiposIncidencia.forEach(tipo => {
                    selectTipo.innerHTML += `<option value="${tipo.id_tipo_incidencia}">${tipo.nombre_tipo}</option>`;
                });
            }
        }
    } catch (error) {
        console.error('Error al cargar tipos de incidencia:', error);
    }
}

async function cargarDatosSistema() {
    try {
        var formData = new FormData();
        formData.append('accion', 'obtener_datos_sistema');

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        if (resultado.ok && resultado.data) {
            var datos = resultado.data.datos || resultado.data;
            datosSistemaIncidencias.empleados = datos.empleados || [];
        }
    } catch (error) {
        console.error('Error al cargar datos del sistema:', error);
    }
}

// ============================================================================
// ASISTENCIAS
// ============================================================================

async function cargarAsistencias() {
    var fechaInicio = document.getElementById('asistencias-fecha-inicio').value;
    var fechaFin = document.getElementById('asistencias-fecha-fin').value;

    if (!fechaInicio || !fechaFin) {
        alert('Por favor, selecciona un rango de fechas.');
        return;
    }

    try {
        var formData = new FormData();
        formData.append('accion', 'obtener_asistencias');
        formData.append('fecha_inicio', fechaInicio);
        formData.append('fecha_fin', fechaFin);

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        console.log('Respuesta de la API (cargarAsistencias):', resultado);
        if (resultado.ok && resultado.data.asistencias) {
            datosSistemaIncidencias.asistencias = resultado.data.asistencias;
            renderizarTablaAsistencias();
        }
    } catch (error) {
        console.error('Error al cargar asistencias:', error);
        alert('Error al cargar asistencias.');
    }
}

function renderizarTablaAsistencias() {
    const tbody = document.getElementById('tabla-asistencias');

    if (!datosSistemaIncidencias.asistencias || datosSistemaIncidencias.asistencias.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="ReportesTabla__vacio">
                    No hay asistencias guardadas para este rango de fechas
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = datosSistemaIncidencias.asistencias.map(a => {
        return `
            <tr>
                <td>${a.codigo_empleado}</td>
                <td>${a.nombre} ${a.apellido}</td>
                <td>${a.nombre_empresa || '—'}</td>
                <td>${formatearFecha(a.fecha)}</td>
                <td>${a.h_llegada || '—'}</td>
                <td>${a.h_llegada_almuerzo || '—'}</td>
                <td>${a.h_salida_almuerzo || '—'}</td>
                <td>${a.h_salida || '—'}</td>
            </tr>
        `;
    }).join('');
}

// ============================================================================
// INASISTENCIAS
// ============================================================================

async function cargarInasistencias() {
    var fechaInicio = document.getElementById('inasistencias-fecha-inicio').value;
    var fechaFin = document.getElementById('inasistencias-fecha-fin').value;

    console.log('Fecha inicio:', fechaInicio);
    console.log('Fecha fin:', fechaFin);

    if (!fechaInicio || !fechaFin) {
        alert('Por favor, selecciona un rango de fechas.');
        return;
    }

    if (fechaInicio > fechaFin) {
        alert('La fecha de inicio no puede ser mayor que la fecha de fin.');
        return;
    }

    try {
        var formData = new FormData();
        formData.append('accion', 'detectar_inasistencias');
        formData.append('fecha_inicio', fechaInicio);
        formData.append('fecha_fin', fechaFin);

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        console.log('Respuesta de la API (cargarInasistencias):', resultado);
        if (resultado.ok && resultado.data.inasistencias) {
            datosSistemaIncidencias.inasistencias = resultado.data.inasistencias;
            renderizarTablaInasistencias();
        }
    } catch (error) {
        console.error('Error al cargar inasistencias:', error);
        alert('Error al detectar inasistencias.');
    }
}

function renderizarTablaInasistencias() {
    const tbody = document.getElementById('tabla-inasistencias');

    if (!datosSistemaIncidencias.inasistencias || datosSistemaIncidencias.inasistencias.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="ReportesTabla__vacio">
                    No hay inasistencias para este rango de fechas
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = datosSistemaIncidencias.inasistencias.map(i => {
        const tieneIncidencia = i.tiene_incidencia ? 'SI' : 'NO';
        const justificada = i.incidencia ? 'SI' : 'NO';
        const tipoIncidencia = i.incidencia ? i.incidencia.nombre_tipo : '—';

        var botones = '';
        if (!i.tiene_incidencia) {
            botones = `<button onclick="mostrarModalAsignarIncidencia(${i.id_empleado}, '${i.fecha}')" 
                        class="ReportesBtn ReportesBtn--primario">
                        Justificar
                    </button>`;
        } else {
            botones = `<button onclick="eliminarIncidencia(${i.incidencia.id_incidencia})" 
                        class="ReportesBtn ReportesBtn--peligro">
                        Eliminar
                    </button>`;
        }

        return `
            <tr>
                <td>${i.codigo_empleado}</td>
                <td>${i.nombre} ${i.apellido}</td>
                <td>${i.nombre_empresa || '—'}</td>
                <td>${formatearFecha(i.fecha)}</td>
                <td>${tieneIncidencia}</td>
                <td>${justificada}</td>
                <td>${tipoIncidencia}</td>
                <td>${botones}</td>
            </tr>
        `;
    }).join('');
}

// ============================================================================
// TIPOS DE INCIDENCIAS
// ============================================================================

function inicializarFormularioCrearIncidencia() {
    const form = document.getElementById('FormCrearIncidencia');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await guardarTipoIncidencia();
        });
    }
}

function inicializarFormularioEditarIncidencia() {
    const form = document.getElementById('FormEditarIncidencia');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await guardarEdicionTipoIncidencia();
        });
    }
}

function abrirOverlay(idOverlay) {
    var overlay = document.getElementById(idOverlay);
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

function cerrarOverlay(idOverlay) {
    var overlay = document.getElementById(idOverlay);
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function mostrarModalCrearIncidencia() {
    document.getElementById('incidencia-nombre').value = '';
    document.getElementById('incidencia-descripcion').value = '';
    document.getElementById('incidencia-es-descontable').checked = true;
    abrirOverlay('OverlayCrearIncidencia');
}

function cerrarModalCrearIncidencia() {
    cerrarOverlay('OverlayCrearIncidencia');
}

async function guardarTipoIncidencia() {
    const nombre = document.getElementById('incidencia-nombre').value;
    const descripcion = document.getElementById('incidencia-descripcion').value || null;
    const esDescontable = document.getElementById('incidencia-es-descontable').checked;

    if (!nombre) {
        alert('Por favor, completa el nombre de la incidencia.');
        return;
    }

    try {
        var formData = new FormData();
        formData.append('accion', 'crear_tipo_incidencia');
        formData.append('nombre', nombre);
        if (descripcion) formData.append('descripcion', descripcion);
        formData.append('es_descontable', esDescontable ? '1' : '0');

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        if (resultado.ok) {
            alert('Tipo de incidencia creado correctamente.');
            cerrarModalCrearIncidencia();
            await cargarTiposIncidencia();
        } else {
            alert('Error: ' + (resultado.mensaje || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error al guardar tipo de incidencia:', error);
        alert('Error al guardar el tipo de incidencia.');
    }
}

function mostrarModalEditarIncidencia(idTipoIncidencia) {
    const idBuscar = parseInt(idTipoIncidencia, 10);
    const tipo = datosSistemaIncidencias.tiposIncidencia.find(function (t) {
        return parseInt(t.id_tipo_incidencia, 10) === idBuscar;
    });
    if (!tipo) return;

    document.getElementById('editar-id-tipo').value = tipo.id_tipo_incidencia;
    document.getElementById('editar-nombre').value = tipo.nombre_tipo;
    document.getElementById('editar-descripcion').value = tipo.descripcion || '';
    document.getElementById('editar-es-descontable').checked = tipo.es_descontable === 1 || tipo.es_descontable === true;

    abrirOverlay('OverlayEditarIncidencia');
}

function cerrarModalEditarIncidencia() {
    cerrarOverlay('OverlayEditarIncidencia');
}

async function guardarEdicionTipoIncidencia() {
    const idTipo = parseInt(document.getElementById('editar-id-tipo').value);
    const nombre = document.getElementById('editar-nombre').value;
    const descripcion = document.getElementById('editar-descripcion').value || null;
    const esDescontable = document.getElementById('editar-es-descontable').checked;

    if (!nombre) {
        alert('Por favor, completa el nombre de la incidencia.');
        return;
    }

    try {
        var formData = new FormData();
        formData.append('accion', 'actualizar_tipo_incidencia');
        formData.append('id_tipo_incidencia', idTipo);
        formData.append('nombre', nombre);
        if (descripcion) formData.append('descripcion', descripcion);
        formData.append('es_descontable', esDescontable ? '1' : '0');

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        if (resultado.ok) {
            alert('Tipo de incidencia actualizado correctamente.');
            cerrarModalEditarIncidencia();
            await cargarTiposIncidencia();
        } else {
            alert('Error: ' + (resultado.mensaje || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error al actualizar tipo de incidencia:', error);
        alert('Error al actualizar el tipo de incidencia.');
    }
}

function renderizarTablaTiposIncidencia() {
    const tbody = document.getElementById('tabla-incidencias');

    if (!datosSistemaIncidencias.tiposIncidencia || datosSistemaIncidencias.tiposIncidencia.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="ReportesTabla__vacio">
                    No hay tipos de incidencia registrados
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = datosSistemaIncidencias.tiposIncidencia.map(t => {
        const esDescontable = t.es_descontable === 1 || t.es_descontable === true ? 'SI' : 'NO';
        return `
            <tr>
                <td>${t.nombre_tipo}</td>
                <td>${t.descripcion || '—'}</td>
                <td>${esDescontable}</td>
                <td>
                    <button onclick="mostrarModalEditarIncidencia(${t.id_tipo_incidencia})" 
                            class="ReportesBtn ReportesBtn--secundario" style="margin-right: 8px;">
                        Editar
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// ============================================================================
// ASIGNAR INCIDENCIA A INASISTENCIA
// ============================================================================

function inicializarFormularioAsignarIncidencia() {
    const form = document.getElementById('FormAsignarIncidencia');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await asignarIncidencia();
        });
    }
}

function mostrarModalAsignarIncidencia(idEmpleado, fecha) {
    document.getElementById('asignar-id-empleado').value = idEmpleado;
    document.getElementById('asignar-fecha').value = fecha;
    document.getElementById('asignar-tipo-incidencia').value = '';
    document.getElementById('asignar-observaciones').value = '';

    abrirOverlay('OverlayAsignarIncidencia');
}

function cerrarModalAsignarIncidencia() {
    cerrarOverlay('OverlayAsignarIncidencia');
}

async function asignarIncidencia() {
    const idEmpleado = parseInt(document.getElementById('asignar-id-empleado').value);
    const fecha = document.getElementById('asignar-fecha').value;
    const idTipoIncidencia = parseInt(document.getElementById('asignar-tipo-incidencia').value);
    const observaciones = document.getElementById('asignar-observaciones').value || null;

    if (!idEmpleado || !idTipoIncidencia || !fecha) {
        alert('Por favor, completa los campos obligatorios.');
        return;
    }

    try {
        var formData = new FormData();
        formData.append('accion', 'crear_incidencia');
        formData.append('id_empleado', idEmpleado);
        formData.append('id_tipo_incidencia', idTipoIncidencia);
        formData.append('fecha', fecha);
        if (observaciones) formData.append('observaciones', observaciones);

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        if (resultado.ok) {
            alert('Inasistencia justificada correctamente.');
            cerrarModalAsignarIncidencia();
            await cargarInasistencias();
        } else {
            alert('Error: ' + (resultado.mensaje || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error al asignar incidencia:', error);
        alert('Error al justificar la inasistencia.');
    }
}

async function eliminarIncidencia(idIncidencia) {
    if (!confirm('¿Estás seguro de que quieres eliminar esta incidencia?')) {
        return;
    }

    try {
        var formData = new FormData();
        formData.append('accion', 'eliminar_incidencia');
        formData.append('id_incidencia', idIncidencia);

        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var resultado = await respuesta.json();
        if (resultado.ok) {
            alert('Incidencia eliminada correctamente.');
            await cargarInasistencias();
        } else {
            alert('Error: ' + (resultado.mensaje || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error al eliminar incidencia:', error);
        alert('Error al eliminar la incidencia.');
    }
}

// ============================================================================
// REPORTES
// ============================================================================

async function generarReporte() {
    var fechaInicio = document.getElementById('reporte-fecha-inicio').value;
    var fechaFin = document.getElementById('reporte-fecha-fin').value;
    var formato = document.getElementById('reporte-formato').value;

    if (!fechaInicio || !fechaFin) {
        alert('Por favor, selecciona un rango de fechas.');
        return;
    }

    var formData = new FormData();
    formData.append('accion', 'generar_reporte_incidencias');
    formData.append('formato', formato);
    formData.append('fecha_inicio', fechaInicio);
    formData.append('fecha_fin', fechaFin);

    try {
        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });

        var tipoContenido = respuesta.headers.get('Content-Type') || '';

        if (tipoContenido.indexOf('application/json') !== -1) {
            var errorJson = await respuesta.json();
            alert('Error: ' + (errorJson.mensaje || 'No se pudo generar el reporte.'));
            return;
        }

        if (!respuesta.ok) {
            alert('Error al generar el reporte. Código: ' + respuesta.status);
            return;
        }

        var contentDisposition = respuesta.headers.get('Content-Disposition');
        var nombreArchivo = 'reporte_inasistencias';

        if (contentDisposition) {
            var matches = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (matches && matches[1]) {
                nombreArchivo = matches[1].replace(/['"]/g, '');
            }
        } else {
            var extensiones = { txt: '.txt', excel: '.xls', csv: '.csv', pdf: '.pdf' };
            nombreArchivo += extensiones[formato] || '';
        }

        var blob = await respuesta.blob();
        var url = window.URL.createObjectURL(blob);
        var enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = nombreArchivo;
        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error al generar reporte:', error);
        alert('Error al generar el reporte.');
    }
}

// ============================================================================
// UTILIDADES
// ============================================================================

function formatearFecha(fechaStr) {
    if (!fechaStr) return '—';

    var fecha = new Date(fechaStr + 'T00:00:00');
    var dia = String(fecha.getDate()).padStart(2, '0');
    var mes = String(fecha.getMonth() + 1).padStart(2, '0');
    var anio = fecha.getFullYear();

    return dia + '/' + mes + '/' + anio;
}
