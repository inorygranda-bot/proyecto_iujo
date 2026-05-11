<!-- Fragmento: modal nuevo departamento (lógica en Consulta.js) -->

<dialog id="ModalDepartamento" class="ModalConsulta" aria-labelledby="TituloModalDepto">
    <header class="ModalConsulta__cabecera">

        <h3 id="TituloModalDepto" class="ModalConsulta__titulo">

            <i class="fas fa-sitemap" aria-hidden="true"></i> Nuevo departamento

        </h3>

        <button type="button" class="ModalConsulta__cerrar" onclick="cerrarModalDepartamento()" aria-label="Cerrar">&times;</button>

    </header>

    <p class="ModalConsulta__ayuda">Los datos se registran en la base de datos vinculados a la empresa seleccionada.</p>

    <form id="FormNuevoDepartamento" class="FormModalConsulta" autocomplete="off">

        <label for="SelectEmpresaDepto">Empresa</label>

        <select id="SelectEmpresaDepto" required>

            <option value="">— Elija empresa —</option>

        </select>



        <label for="NombreNuevoDepto">Nombre del departamento</label>

        <input type="text" id="NombreNuevoDepto" placeholder="Ej. Recursos Humanos" required maxlength="120">



        <label for="ObjetivoNuevoDepto">Objetivo (opcional)</label>

        <textarea id="ObjetivoNuevoDepto" rows="3" placeholder="Descripción breve del área"></textarea>



        <footer class="ModalConsulta__acciones">

            <button type="button" class="BotonSecundario" onclick="cerrarModalDepartamento()">Cancelar</button>

            <button type="submit" class="BotonPrincipal">Guardar departamento</button>

        </footer>

    </form>

</dialog>

