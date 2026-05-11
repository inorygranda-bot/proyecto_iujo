<!-- Fragmento: modal nuevo empleado (lógica en Consulta.js) -->
<dialog id="ModalEmpleado" class="ModalConsulta" aria-labelledby="TituloModalEmp">
    <header class="ModalConsulta__cabecera">
        <h3 id="TituloModalEmp" class="ModalConsulta__titulo">
            <i class="fas fa-user-tie" aria-hidden="true"></i> Nuevo empleado
        </h3>
        <button type="button" class="ModalConsulta__cerrar" onclick="cerrarModalEmpleado()" aria-label="Cerrar">&times;</button>
    </header>
    <p class="ModalConsulta__ayuda">Misma estructura que antes en registro: empresa, departamento, datos personales y código único.</p>
    <form id="FormNuevoEmpleado" class="FormModalConsulta" autocomplete="off">
        <label for="SelectEmpresaEmp">Empresa</label>
        <select id="SelectEmpresaEmp" required>
            <option value="">— Elija empresa —</option>
        </select>

        <label for="SelectDeptoEmp">Departamento</label>
        <select id="SelectDeptoEmp" required disabled>
            <option value="">— Primero elija empresa —</option>
        </select>

        <section class="FormModalConsulta__fila">
            <article>
                <label for="EmpNombres">Nombres</label>
                <input type="text" id="EmpNombres" required maxlength="80">
            </article>
            <article>
                <label for="EmpApellidos">Apellidos</label>
                <input type="text" id="EmpApellidos" required maxlength="80">
            </article>
        </section>

        <section class="FormModalConsulta__fila FormModalConsulta__fila--cedula">
            <article>
                <label for="EmpPrefijoCedula">Cédula</label>
                <section class="RifCedulaFila">
                    <select id="EmpPrefijoCedula">
                        <option value="V">V</option>
                        <option value="E">E</option>
                    </select>
                    <input type="text" id="EmpCedulaNumero" placeholder="12345678" inputmode="numeric" required>
                </section>
            </article>
            <article>
                <label for="EmpRif">RIF personal</label>
                <input type="text" id="EmpRif" name="rif_personal_manual" placeholder="V-12345678-0" maxlength="14" autocomplete="off" spellcheck="false">
            </article>
        </section>

        <label for="EmpCodigo">Código de empleado</label>
        <input type="text" id="EmpCodigo" placeholder="Ej. EMP-001" required maxlength="32">

        <label for="EmpCargo">Puesto / cargo</label>
        <input type="text" id="EmpCargo" placeholder="Ej. Analista" required maxlength="120">

        <footer class="ModalConsulta__acciones">
            <button type="button" class="BotonSecundario" onclick="cerrarModalEmpleado()">Cancelar</button>
            <button type="submit" class="BotonPrincipal">Guardar empleado</button>
        </footer>
    </form>
</dialog>

