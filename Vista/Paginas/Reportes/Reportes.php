<!-- ==========================================================================
     PÁGINA DE REPORTES E INCIDENCIAS (CORREGIDA)
     ========================================================================== -->

<section id="Reportes" class="Principal ReportesModulo">

    <header class="ReportesCabecera">
        <h2 class="ReportesTitulo">Gestion de Asistencias e Incidencias</h2>
        <p class="ReportesSub">Importa registros, detecta inasistencias, gestiona incidencias y genera reportes.</p>

        <nav class="ReportesOpciones" aria-label="Secciones">
            <button type="button" class="ReportesPestaña ReportesPestaña--activa" id="BtnPestañaImportar" data-panel="panelImportar">
                Importar TXT
            </button>
            <button type="button" class="ReportesPestaña" id="BtnPestañaAsistencias" data-panel="panelAsistencias">
                Asistencias
            </button>
            <button type="button" class="ReportesPestaña" id="BtnPestañaInasistencias" data-panel="panelInasistencias">
                Inasistencias
            </button>
            <button type="button" class="ReportesPestaña" id="BtnPestañaIncidencias" data-panel="panelIncidencias">
                Incidencias
            </button>
            <button type="button" class="ReportesPestaña" id="BtnPestañaReportes" data-panel="panelReportes">
                Reportes
            </button>
        </nav>
    </header>

    <!-- ======================================================================
         SECCION 1: IMPORTAR TXT DE ASISTENCIA
         ====================================================================== -->
    <article id="panelImportar" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaImportar">
        <header class="ReportesPanel__head">
            <h3>Importar Registros de Asistencia</h3>
            <p>Sube el archivo TXT generado por el biométrico.</p>
        </header>

        <section class="ReportesFormulario">
            <aside class="alert alert-info" role="note">
                <strong>Formato del archivo TXT:</strong>
                <code>codigo_empleado fecha(DD/MM/AA) hora(H:MM o HH:MM) nombre_empresa</code>
                <br><br>
                <strong>Ejemplo (4 líneas por empleado):</strong>
                <br><code>004 24/05/26 9:00 aassa</code>
                <br><code>004 24/05/26 12:00 aassa</code>
                <br><code>004 24/05/26 13:00 aassa</code>
                <br><code>004 24/05/26 17:00 aassa</code>
                <br><br>
                <strong>Validaciones:</strong>
                <ul>
                    <li>El empleado debe existir en la BD</li>
                    <li>El empleado debe pertenecer a la empresa del TXT</li>
                    <li>Se requieren EXACTAMENTE 4 marcajes por empleado y fecha</li>
                </ul>
            </aside>

            <fieldset>
                <legend>Archivo a importar</legend>
                <label for="input-archivo-txt">Selecciona el archivo TXT:</label>
                <input type="file" id="input-archivo-txt" accept=".txt" class="form-control mb-3">
            </fieldset>

            <footer>
                <button onclick="importarTXT()" class="ReportesBtn ReportesBtn--primario">
                    <i class="fas fa-upload"></i> Importar Archivo
                </button>
            </footer>
        </section>

        <aside id="resultado-importacion" hidden>
            <header>
                <h4>Resultado de la importación</h4>
            </header>
            <article id="resultado-importacion-contenido"></article>
        </aside>
    </article>

    <!-- ======================================================================
         SECCION 2: ASISTENCIAS GUARDADAS
         ====================================================================== -->
    <article id="panelAsistencias" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaAsistencias" hidden>
        <header class="ReportesPanel__head d-flex justify-content-between align-items-center">
            <section>
                <h3>Asistencias Guardadas</h3>
                <p>Veras aqui los registros importados y guardados en la tabla asistencia.</p>
            </section>
            <section class="d-flex gap-2">
                <label for="asistencias-fecha-inicio" class="align-self-center">Desde:</label>
                <input type="date" id="asistencias-fecha-inicio" class="form-control" style="width: 180px;">
                <label for="asistencias-fecha-fin" class="align-self-center">Hasta:</label>
                <input type="date" id="asistencias-fecha-fin" class="form-control" style="width: 180px;">
                <button onclick="cargarAsistencias()" class="ReportesBtn ReportesBtn--secundario">
                    Filtrar
                </button>
            </section>
        </header>

        <section class="ReportesListaWrap">
            <section class="ReportesTablaScroll">
                <table class="ReportesTabla">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Empleado</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Llegada</th>
                            <th>Salida Almuerzo</th>
                            <th>Llegada Almuerzo</th>
                            <th>Salida</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-asistencias">
                        <tr>
                            <td colspan="8" class="ReportesTabla__vacio">
                                No hay asistencias guardadas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </section>
    </article>

    <!-- ======================================================================
         SECCION 3: INASISTENCIAS
         ====================================================================== -->
    <article id="panelInasistencias" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaInasistencias" hidden>
        <header class="ReportesPanel__head d-flex justify-content-between align-items-center">
            <section>
                <h3>Inasistencias Detectadas</h3>
                <p>Veras aqui los empleados que no tienen registros de asistencia en el rango de fechas.</p>
            </section>
            <section class="d-flex gap-2">
                <label for="inasistencias-fecha-inicio" class="align-self-center">Desde:</label>
                <input type="date" id="inasistencias-fecha-inicio" class="form-control" style="width: 180px;">
                <label for="inasistencias-fecha-fin" class="align-self-center">Hasta:</label>
                <input type="date" id="inasistencias-fecha-fin" class="form-control" style="width: 180px;">
                <button onclick="cargarInasistencias()" class="ReportesBtn ReportesBtn--secundario">
                    Detectar Inasistencias
                </button>
            </section>
        </header>

        <section class="ReportesListaWrap">
            <section class="ReportesTablaScroll">
                <table class="ReportesTabla">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Empleado</th>
                            <th>Empresa</th>
                            <th>Fecha</th>
                            <th>Tiene Incidencia</th>
                            <th>Justificada</th>
                            <th>Tipo Incidencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-inasistencias">
                        <tr>
                            <td colspan="8" class="ReportesTabla__vacio">
                                No hay inasistencias para este rango de fechas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </section>
    </article>

    <!-- ======================================================================
         SECCION 4: TIPOS DE INCIDENCIAS
         ====================================================================== -->
    <article id="panelIncidencias" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaIncidencias" hidden>
        <header class="ReportesPanel__head d-flex justify-content-between align-items-center">
            <section>
                <h3>Tipos de Incidencias</h3>
                <p>Gestiona los tipos de incidencia para justificar inasistencias.</p>
            </section>
            <section>
                <button onclick="mostrarModalCrearIncidencia()" class="ReportesBtn ReportesBtn--primario">
                    Crear Tipo de Incidencia
                </button>
            </section>
        </header>

        <section class="ReportesListaWrap">
            <section class="ReportesTablaScroll">
                <table class="ReportesTabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripcion</th>
                            <th>Descontable</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-incidencias">
                        <tr>
                            <td colspan="4" class="ReportesTabla__vacio">
                                No hay tipos de incidencia registrados
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </section>
    </article>

    <!-- ======================================================================
         SECCION 5: REPORTES
         ====================================================================== -->
    <article id="panelReportes" class="ReportesPanel" role="tabpanel" aria-labelledby="BtnPestañaReportes" hidden>
        <header class="ReportesPanel__head">
            <h3>Generar Reportes de Inasistencias</h3>
            <p>Exporta el detalle de inasistencias del período seleccionado en TXT, Excel o PDF.</p>
        </header>

        <form id="FormNomina" class="ReportesFormulario" autocomplete="off">
            <fieldset>
                <legend>Datos del reporte</legend>
                <section class="row mb-3">
                    <section class="col-md-3">
                        <label for="reporte-fecha-inicio">Fecha Inicio:</label>
                        <input type="date" id="reporte-fecha-inicio" class="form-control" required>
                    </section>
                    <section class="col-md-3">
                        <label for="reporte-fecha-fin">Fecha Fin:</label>
                        <input type="date" id="reporte-fecha-fin" class="form-control" required>
                    </section>
                    <section class="col-md-3">
                        <label for="reporte-formato">Formato del Reporte:</label>
                        <select id="reporte-formato" class="form-control">
                            <option value="txt">TXT (texto formateado)</option>
                            <option value="excel">Excel (.xls con estilos)</option>
                            <option value="pdf">PDF (para imprimir)</option>
                        </select>
                    </section>
                    <section class="col-md-3 d-flex align-items-end">
                        <footer>
                            <button type="button" onclick="generarReporte()" class="ReportesBtn ReportesBtn--primario w-100">
                                    Generar Reporte
                                </button>
                        </footer>
                    </section>
                </section>
            </fieldset>
        </form>

        <aside class="alert alert-info" role="note">
                <strong>Formatos disponibles:</strong>
                <ul>
                    <li><strong>TXT:</strong> Tabla con bordes, resumen del período y codificación UTF-8</li>
                    <li><strong>Excel:</strong> Hoja con encabezados de color, filas alternadas y totales</li>
                    <li><strong>PDF:</strong> Formato horizontal listo para imprimir o compartir</li>
                </ul>
            </aside>
    </article>

    <!-- Modal: crear tipo de incidencia -->
    <section id="OverlayCrearIncidencia" class="Overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-titulo-incidencia">
        <article class="Modal">
            <header class="ModalCabecera">
                <h3 id="modal-titulo-incidencia">Crear Tipo de Incidencia</h3>
                <button type="button" onclick="cerrarModalCrearIncidencia()" aria-label="Cerrar">&times;</button>
            </header>
            <form id="FormCrearIncidencia" autocomplete="off">
                <section class="CuerpoModal">
                    <fieldset class="Campo">
                        <label for="incidencia-nombre">Nombre o descripcion</label>
                        <input type="text" id="incidencia-nombre" required>
                    </fieldset>
                    <fieldset class="Campo">
                        <label for="incidencia-descripcion">Descripcion adicional (opcional)</label>
                        <textarea id="incidencia-descripcion" rows="3"></textarea>
                    </fieldset>
                    <fieldset class="Campo">
                        <label class="CheckItem">
                            <input type="checkbox" id="incidencia-es-descontable" checked>
                            Es descontable
                        </label>
                    </fieldset>
                </section>
                <footer class="ModalPie">
                    <button type="button" class="BtnSecundario" onclick="cerrarModalCrearIncidencia()">Cancelar</button>
                    <button type="submit" class="BtnPrimario">Guardar</button>
                </footer>
            </form>
        </article>
    </section>

    <!-- Modal: editar tipo de incidencia -->
    <section id="OverlayEditarIncidencia" class="Overlay" style="display:none;" role="dialog" aria-modal="true">
        <article class="Modal">
            <header class="ModalCabecera">
                <h3>Editar Tipo de Incidencia</h3>
                <button type="button" onclick="cerrarModalEditarIncidencia()" aria-label="Cerrar">&times;</button>
            </header>
            <form id="FormEditarIncidencia" autocomplete="off">
                <section class="CuerpoModal">
                    <input type="hidden" id="editar-id-tipo">
                    <fieldset class="Campo">
                        <label for="editar-nombre">Nombre o descripcion</label>
                        <input type="text" id="editar-nombre" required>
                    </fieldset>
                    <fieldset class="Campo">
                        <label for="editar-descripcion">Descripcion adicional (opcional)</label>
                        <textarea id="editar-descripcion" rows="3"></textarea>
                    </fieldset>
                    <fieldset class="Campo">
                        <label class="CheckItem">
                            <input type="checkbox" id="editar-es-descontable">
                            Es descontable
                        </label>
                    </fieldset>
                </section>
                <footer class="ModalPie">
                    <button type="button" class="BtnSecundario" onclick="cerrarModalEditarIncidencia()">Cancelar</button>
                    <button type="submit" class="BtnPrimario">Guardar Cambios</button>
                </footer>
            </form>
        </article>
    </section>

    <!-- Modal: justificar inasistencia -->
    <section id="OverlayAsignarIncidencia" class="Overlay" style="display:none;" role="dialog" aria-modal="true">
        <article class="Modal">
            <header class="ModalCabecera">
                <h3>Justificar Inasistencia</h3>
                <button type="button" onclick="cerrarModalAsignarIncidencia()" aria-label="Cerrar">&times;</button>
            </header>
            <form id="FormAsignarIncidencia" autocomplete="off">
                <section class="CuerpoModal">
                    <input type="hidden" id="asignar-id-empleado">
                    <input type="hidden" id="asignar-fecha">
                    <fieldset class="Campo">
                        <label for="asignar-tipo-incidencia">Tipo de incidencia</label>
                        <select id="asignar-tipo-incidencia" required>
                            <option value="">Selecciona un tipo</option>
                        </select>
                    </fieldset>
                    <fieldset class="Campo">
                        <label for="asignar-observaciones">Observaciones (opcional)</label>
                        <textarea id="asignar-observaciones" rows="3"></textarea>
                    </fieldset>
                </section>
                <footer class="ModalPie">
                    <button type="button" class="BtnSecundario" onclick="cerrarModalAsignarIncidencia()">Cancelar</button>
                    <button type="submit" class="BtnPrimario">Justificar</button>
                </footer>
            </form>
        </article>
    </section>
</section>

<script src="Recursos/js/modulos/Incidencias.js?v=<?php echo time(); ?>"></script>
