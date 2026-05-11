<section id="Consulta" class="Principal">


    <header class="CabeceraConsulta">
        <section class="CabeceraConsulta__texto">
            <h2 class="CabeceraConsulta__titulo">Gestión de directorio</h2>
            <p class="CabeceraConsulta__sub">Empresas, departamentos y personal en un solo lugar.</p>
        </section>
        <nav class="CabeceraConsulta__acciones" aria-label="Acciones rápidas">
            <button type="button" class="BtnAccionRapida BtnAccionRapida--depto" id="BtnAbrirModalDepto" onclick="abrirModalDepartamento()">
                <i class="fas fa-sitemap" aria-hidden="true"></i>
                <span>Crear departamento</span>
            </button>
            <button type="button" class="BtnAccionRapida BtnAccionRapida--emp" id="BtnAbrirModalEmp" onclick="abrirModalEmpleado()">
                <i class="fas fa-user-plus" aria-hidden="true"></i>
                <span>Crear empleado</span>
            </button>
        </nav>
    </header>

    <section class="ConsultaLayout">
        <aside class="PanelIzquierdo">
            <header class="PanelIzquierdo__head">
                <h3>Directorio</h3>
                <p>Seleccione una empresa</p>
            </header>

            <section class="buscador">
                <label for="buscarEmpresa">Buscar empresa:</label>
                <input type="search" id="buscarEmpresa" placeholder="Nombre de empresa">
                <ul id="resultadosEmpresa" class="resultados" hidden></ul>

                <label for="buscarDepartamento" hidden>Buscar departamento:</label>
                <input type="search" id="buscarDepartamento" placeholder="Nombre de departamento" hidden>
                <ul id="resultadosDepartamento" class="resultados" hidden></ul>

                <label for="buscarEmpleado" hidden>Buscar empleado:</label>
                <input type="search" id="buscarEmpleado" placeholder="Nombre, cédula o código" hidden>
                <ul id="resultadosEmpleado" class="resultados" hidden></ul>
            </section>

            <nav id="ListaEmpresas" class="ListaEmpresas" aria-label="Lista de empresas">
            </nav>
        </aside>

        <article class="PanelDerecho">
            <header class="BarraNavegacion">
                <nav class="RutaNavegacion" aria-label="Ruta">
                    <span id="NombreEmpresa">Empresa: —</span>
                    <span id="SeparadorRuta" hidden> &gt; </span>
                    <span id="NombreDepto" hidden>Depto: —</span>
                </nav>

                <nav class="BotonesAccion">
                    <!-- Solo visible al entrar en empresa o departamento: vuelve un paso dentro de Consulta (no es el historial del navegador). -->
                    <a href="#" id="EnlaceVolverConsulta" class="EnlaceVolverConsulta" hidden onclick="volverConsultaInterno(event)">← Volver</a>
                   <button type="button" class="BotonVolverConsulta" onclick="VolverInicio()"><i class="fas fa-arrow-left"></i> Volver al menú</button>
                </nav>
            </header>

            <main class="AreaTabla">
                <table class="TablaDatos">
                    <thead id="CabeceraTabla">
                    </thead>
                    <tbody id="CuerpoTabla">
                        <tr>
                            <td colspan="4" class="TablaDatos__vacio">Seleccione una empresa en el panel izquierdo.</td>
                        </tr>
                    </tbody>
                </table>
            </main>

            <footer id="InfoGerencia" class="PieDetalles" style="display: none;">
                <section class="ContenedorGerente">
                    <p>
                        <strong>Gerente del área:</strong>
                        <span id="NombreGerente" class="GerenteLink" onclick="abrirSeleccionGerente()">Sin asignar</span>
                    </p>

                    <aside id="SelectorGerente" class="SelectorFlotante" hidden>
                        <h4>Seleccionar gerente</h4>
                        <ul id="ListaCandidatosGerente"></ul>
                        <button type="button" class="BotonSecundario" onclick="cerrarSelector()">Cancelar</button>
                    </aside>
                </section>
            </footer>
        </article>
    </section>

    <?php include __DIR__ . '/Departamentos.php'; ?>
    <?php include __DIR__ . '/Empleados.php'; ?>
    <?php include __DIR__ . '/Edicion.php'; ?>
</section>