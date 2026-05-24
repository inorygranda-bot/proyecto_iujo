// Graficas de asistencias en el panel de inicio

var graficaTortaInicio = null;
var graficaBarrasInicio = null;

// Paleta alineada con index.css (--navy-blue, verdes/rojos del modulo reportes)
var coloresCategorias = {
    asistencia: '#27ae60',
    inasistencia: '#ef4444',
    justificadas: '#2c5f8d',
    retardos: '#3d7ab3'
};

var etiquetasCategorias = {
    asistencia: 'Asistencia',
    inasistencia: 'Inasistencia',
    justificadas: 'Justificadas',
    retardos: 'Retardos'
};

document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('BtnActualizarGraficas');
    if (btn) {
        btn.addEventListener('click', cargarGraficasInicio);
    }
    cargarGraficasInicio();
});

async function cargarGraficasInicio() {
    var mes = parseInt(document.getElementById('inicio-filtro-mes').value, 10);
    var anio = parseInt(document.getElementById('inicio-filtro-anio').value, 10);

    var formData = new FormData();
    formData.append('accion', 'obtener_estadisticas_asistencias');
    formData.append('mes', mes);
    formData.append('anio', anio);

    try {
        var respuesta = await fetch('Controlador/API/gestion_api.php', {
            method: 'POST',
            body: formData
        });
        var resultado = await respuesta.json();

        if (!resultado.ok || !resultado.data) {
            alert('No se pudieron cargar las estadisticas.');
            return;
        }

        renderizarPanelInicio(resultado.data);
    } catch (error) {
        console.error('Error al cargar graficas de inicio:', error);
        alert('Error al cargar las graficas.');
    }
}

function renderizarPanelInicio(datos) {
    var periodo = datos.periodo || {};
    var mensual = datos.mensual || {};
    var porDia = datos.por_dia_semana || {};

    document.getElementById('inicio-periodo-etiqueta').textContent =
        'Periodo: ' + (periodo.etiqueta || '');

    renderizarResumenChips(mensual);
    renderizarGraficaTorta(mensual);
    renderizarGraficaBarras(porDia);
}

function renderizarResumenChips(mensual) {
    var contenedor = document.getElementById('PanelInicioResumen');
    var claves = ['asistencia', 'inasistencia', 'justificadas', 'retardos'];

    contenedor.innerHTML = claves.map(function (clave) {
        return '<article class="PanelInicio__chip PanelInicio__chip--' + clave + '">' +
            '<strong>' + (mensual[clave] || 0) + '</strong>' +
            '<span>' + etiquetasCategorias[clave] + '</span>' +
            '</article>';
    }).join('');
}

function renderizarGraficaTorta(mensual) {
    var canvas = document.getElementById('GraficaTortaMensual');
    var claves = ['asistencia', 'inasistencia', 'justificadas', 'retardos'];
    var valores = claves.map(function (k) { return mensual[k] || 0; });
    var etiquetas = claves.map(function (k) { return etiquetasCategorias[k]; });
    var colores = claves.map(function (k) { return coloresCategorias[k]; });

    if (graficaTortaInicio) {
        graficaTortaInicio.destroy();
    }

    graficaTortaInicio = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: etiquetas,
            datasets: [{
                data: valores,
                backgroundColor: colores,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                title: { display: false }
            }
        }
    });
}

function renderizarGraficaBarras(porDia) {
    var canvas = document.getElementById('GraficaBarrasSemana');
    var dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
    var claves = ['asistencia', 'inasistencia', 'justificadas', 'retardos'];

    var datasets = claves.map(function (clave) {
        return {
            label: etiquetasCategorias[clave],
            data: dias.map(function (dia) {
                return (porDia[dia] && porDia[dia][clave]) ? porDia[dia][clave] : 0;
            }),
            backgroundColor: coloresCategorias[clave],
            borderRadius: 6
        };
    });

    if (graficaBarrasInicio) {
        graficaBarrasInicio.destroy();
    }

    graficaBarrasInicio = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: dias,
            datasets: datasets
        },
        options: {
            responsive: true,
            scales: {
                x: { stacked: false },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
