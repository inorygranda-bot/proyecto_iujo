<?php
$pCalendario = $p ?? ($_GET["p"] ?? "inicio");
?>

<section id="Calendario" class="Principal" style="display: none;">

    <header class="cabecera-flexible">
        <nav class="grupo-textos">
            <h2 id="EtiquetaPrincipal">Calendario</h2>
            <p id="EtiquetaSubtitulo">Gestión de horario laboral</p>
        </nav>

        <nav class="grupo-botones">
            <button type="button" id="BtnSincronizarGoogle" class="boton-volver" onclick="sincronizarConGoogle()">
                Sincronizar con Google
            </button>
            <button type="button" class="boton-volver" onclick="cerrarCalendario()">
                <?php echo $pCalendario === "consulta" ? "Volver a consulta" : "Volver al menú"; ?>
            </button>
        </nav>
    </header>

    <main class="cuerpo-principal">

        <section class="FilaDoble">
            
            <!-- Tarjeta 1: Contexto -->
            <section class="bloque-contexto-calendario">
                <h3 class="bloque-contexto-calendario__titulo">¿Para quién programa?</h3>
                <p class="bloque-contexto-calendario__txt">Elija empresa, departamento y empleado (opcional) y pulse <strong>Aplicar</strong>.</p>
                <section class="bloque-contexto-calendario__grid">
                    <label class="ctx-label">Empresa
                        <select id="CalSelEmpresa"></select>
                    </label>
                    <label class="ctx-label">Departamento
                        <select id="CalSelDepto" disabled></select>
                    </label>
                    <label class="ctx-label">Empleado
                        <select id="CalSelEmpleado" disabled></select>
                    </label>
                    <button type="button" class="ctx-aplicar" onclick="calAplicarContextoDesdeSelects()">Aplicar selección</button>
                </section>
            </section>

            <!-- Tarjeta 2: Horario -->
            <section class="bloque-horario">
                <h3>Horario laboral</h3>
                <p>Valores por defecto del nivel. <strong>Tocando un día</strong> del calendario puede guardar horario solo para esa fecha o para todos los mismos días de la semana.</p>
                <form id="FormHorarioLaboral" class="fila-horario">
                    <label>Mañana:</label>
                    <input type="time" id="HoraDesdeM" value="08:00"> a
                    <input type="time" id="HoraHastaM" value="12:00">
                    <label>Tarde:</label>
                    <input type="time" id="HoraDesdeT" value="14:00"> a
                    <input type="time" id="HoraHastaT" value="18:00">
                    <button type="submit" class="flecha">Actualizar</button>
                </form>
            </section>

        </section>

        <!-- Navegación y Rejilla (quedan fuera de la FilaDoble para ocupar todo el ancho) -->
        <nav class="selector-fecha">
            <button onclick="cambiarMes(-1)" class="flecha">Anterior</button>
            <h3 id="TextoMesAno">Enero 2026</h3>
            <button onclick="cambiarMes(1)" class="flecha">Siguiente</button>
        </nav>

        <section class="contenedor-rejilla">
            <header class="fila-dias">
                <abbr title="Lunes">Lun</abbr>
                <abbr title="Martes">Mar</abbr>
                <abbr title="Miércoles">Mie</abbr>
                <abbr title="Jueves">Jue</abbr>
                <abbr title="Viernes">Vie</abbr>
                <abbr title="Sábado">Sab</abbr>
                <abbr title="Domingo">Dom</abbr>
            </header>

            <section id="CuadriculaCalendario" class="tabla-dias"></section>
        </section>

    </main>

    <footer class="pie-leyenda">
        <p><span class="marca laboral"></span> Día de Trabajo</p>
        <p><span class="marca feriado"></span> Día Libre (Rojo)</p>
        <p><small>* Los días marcados se sincronizan desde Google Calendar. Puedes tocar un día para ver detalles o realizar ajustes manuales locales.</small></p>
    </footer>

    <dialog id="editor" class="capa">
        <article class="ventana">
            <header class="cabecera">
                <h3 id="fecha">Editar Día</h3>
                <button onclick="cerrar()" class="equis">&times;</button>
            </header>
            
            <main class="cuerpo">
                <section class="bloque">
                    <h4>Evento</h4>
                    <input type="text" id="motivo" placeholder="Ej: Feriado">
                    <label class="opcion">
                        <input type="checkbox" id="laboral"> ¿Es laborable?
                    </label>
                    <button id="borrarEvento" class="rojo" style="display:none;">Eliminar Evento</button>
                </section>

                <hr>

                <section class="bloque">
                    <h4>Horario</h4>
                    <section class="horas">
                        <input type="time" id="m1"> a <input type="time" id="m2">
                        <input type="time" id="t1"> a <input type="time" id="t2">
                    </section>
                </section>
            </main>

            <footer class="base">
                <button onclick="cerrar()" class="gris">Cerrar</button>
                <button id="guardarDia" class="azul">Solo hoy</button>
                <button id="guardarSiempre" class="verde">Siempre</button>
            </footer>
        </article>
    </dialog>

</section>
