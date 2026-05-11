<!-- Por ahora solo se manejan incidencias pq SANTIAGO no termino temprano -->

<section id="Reportes" class="Principal ReportesModulo">

    <header class="ReportesCabecera">

        <h2 class="ReportesTitulo">Generar reportes</h2>
        <p class="ReportesSub">Defina tipos de datos para generar reportes del sistema.</p>

        <nav class="ReportesOpciones" aria-label="Tipos de reporte">
            <button type="button" class="ReportesPestaña ReportesPestaña--activa" id="BtnPestañaIncidencias" data-panel="panelIncidencias">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                Incidencias
            </button>
            <button type="button" class="ReportesPestaña" id="BtnPestañaNomina" data-panel="panelNomina">
                <i class="fas fa-file-alt" aria-hidden="true"></i>
                Nómina
            </button>
<!-- Aqui se van a agregar mas botones para las demas funciones QUE NO SE HICIERON POR SANTIAGO -->
        </nav> 

        </header>

    <article id="panelIncidencias" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaIncidencias">
        <header class="ReportesPanel__head">
            <h3>Incidencias</h3>
            <p>Las incidencias describen situaciones que pueden afectar la asistencia. Por favor indicar si es paga o no</p>
        </header>

        <form id="FormIncidencia" class="ReportesFormulario" autocomplete="off">
            <label for="IncNombre">Nombre de la incidencia</label>
            <input type="text" id="IncNombre" name="nombre" required maxlength="160" placeholder="Ej. Permiso médico, Capacitación externa…">

            <fieldset class="ReportesFieldset">
                <legend>¿Se descuenta del tiempo laboral?</legend>
                <p class="ReportesAyuda">Si marca <strong>Sí</strong>, esas horas no cuentan como tiempo trabajado efectivo (no remunerado).</p>
                <label class="ReportesRadio">
                    <input type="radio" name="descuenta" value="si" required>
                    Sí, se descuenta
                </label>
                <label class="ReportesRadio">
                    <input type="radio" name="descuenta" value="no" required>
                    No, no se descuenta
                </label>
            </fieldset>

            <label for="IncHoras">Horas laborales justificadas</label>
            <p class="ReportesAyuda">Indique la duración en horas (puede usar decimales, ej. <code>2,5</code> para dos horas y media).</p>
            <input type="number" id="IncHoras" name="horasJustificadas" min="0" max="24" step="0.25" required placeholder="0">

            <footer class="ReportesFormulario__acciones">
                <button type="button" class="ReportesBtn ReportesBtn--secundario" onclick="volverMenuReportes()">Volver al menú</button>
                <button type="submit" class="ReportesBtn ReportesBtn--primario">Guardar incidencia</button>
            </footer>
        </form>

        <section class="ReportesListaWrap" aria-labelledby="TituloListaIncidencias">
            <h4 id="TituloListaIncidencias">Incidencias registradas</h4>
            <div class="ReportesTablaScroll">
                <table class="ReportesTabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>¿Descuenta?</th>
                            <th>Horas justificadas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="CuerpoListaIncidencias">
                        <tr>
                            <td colspan="4" class="ReportesTabla__vacio">Aún no hay incidencias. Cree la primera arriba.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </article>

    <article id="panelNomina" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaNomina" hidden>
        <header class="ReportesPanel__head">
            <h3>Nómina</h3>
            <p>Generar archivo de nómina en diferentes formatos.</p>
        </header>

        <form id="FormNomina" class="ReportesFormulario" autocomplete="off">
            <fieldset class="ReportesFieldset">
                <legend>Formato del archivo</legend>
                <label class="ReportesRadio">
                    <input type="radio" name="formato" value="txt" required checked>
                    TXT
                </label>
                <label class="ReportesRadio">
                    <input type="radio" name="formato" value="pdf" required>
                    PDF
                </label>
                <label class="ReportesRadio">
                    <input type="radio" name="formato" value="excel" required>
                    Excel
                </label>
            </fieldset>

            <footer class="ReportesFormulario__acciones">
                <button type="button" class="ReportesBtn ReportesBtn--secundario" onclick="volverMenuReportes()">Volver al menú</button>
                <button type="submit" class="ReportesBtn ReportesBtn--primario">Generar archivo</button>
            </footer>
        </form>
    </article>
</section>
