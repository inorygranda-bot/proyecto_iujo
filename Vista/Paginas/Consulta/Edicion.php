<!-- Modales de edición -->
<dialog id="ModalEditarEmpresa" class="ModalConsulta" aria-labelledby="TituloEditarEmpresa">
    <header class="ModalConsulta__cabecera">
        <h3 id="TituloEditarEmpresa" class="ModalConsulta__titulo"><i class="fas fa-building"></i> Editar empresa</h3>
        <button type="button" class="ModalConsulta__cerrar" onclick="cerrarModal('ModalEditarEmpresa')" aria-label="Cerrar">&times;</button>
    </header>
    <form id="FormEditarEmpresa" class="FormModalConsulta" autocomplete="off">
        <input type="hidden" id="EditEmpresaAnterior">
        <label for="EditEmpresaNombre">Nombre</label>
        <input type="text" id="EditEmpresaNombre" required maxlength="120">
        <label for="EditEmpresaRif">RIF</label>
        <input type="text" id="EditEmpresaRif" name="edit_rif_empresa_manual" required maxlength="14" autocomplete="off" spellcheck="false">
        <label for="EditEmpresaObjetivo">Objetivo</label>
        <textarea id="EditEmpresaObjetivo" rows="3"></textarea>
        <footer class="ModalConsulta__acciones">
            <button type="button" class="BotonSecundario" onclick="cerrarModal('ModalEditarEmpresa')">Cancelar</button>
            <button type="submit" class="BotonPrincipal">Guardar cambios</button>
        </footer>
    </form>
</dialog>

<dialog id="ModalEditarDepartamento" class="ModalConsulta" aria-labelledby="TituloEditarDepartamento">
    <header class="ModalConsulta__cabecera">
        <h3 id="TituloEditarDepartamento" class="ModalConsulta__titulo"><i class="fas fa-sitemap"></i> Editar departamento</h3>
        <button type="button" class="ModalConsulta__cerrar" onclick="cerrarModal('ModalEditarDepartamento')" aria-label="Cerrar">&times;</button>
    </header>
    <form id="FormEditarDepartamento" class="FormModalConsulta" autocomplete="off">
        <input type="hidden" id="EditDeptoEmpresaAnterior">
        <input type="hidden" id="EditDeptoNombreAnterior">
        <label for="EditDeptoEmpresa">Empresa</label>
        <select id="EditDeptoEmpresa" required></select>
        <label for="EditDeptoNombre">Nombre</label>
        <input type="text" id="EditDeptoNombre" required maxlength="120">
        <label for="EditDeptoObjetivo">Objetivo</label>
        <textarea id="EditDeptoObjetivo" rows="3"></textarea>
        <footer class="ModalConsulta__acciones">
            <button type="button" class="BotonSecundario" onclick="cerrarModal('ModalEditarDepartamento')">Cancelar</button>
            <button type="submit" class="BotonPrincipal">Guardar cambios</button>
        </footer>
    </form>
</dialog>

<dialog id="ModalEditarEmpleado" class="ModalConsulta" aria-labelledby="TituloEditarEmpleado">
    <header class="ModalConsulta__cabecera">
        <h3 id="TituloEditarEmpleado" class="ModalConsulta__titulo"><i class="fas fa-user-edit"></i> Editar empleado</h3>
        <button type="button" class="ModalConsulta__cerrar" onclick="cerrarModal('ModalEditarEmpleado')" aria-label="Cerrar">&times;</button>
    </header>
    <form id="FormEditarEmpleado" class="FormModalConsulta" autocomplete="off">
        <input type="hidden" id="EditEmpCedulaOriginal">
        <label for="EditEmpEmpresa">Empresa</label>
        <select id="EditEmpEmpresa" required></select>
        <label for="EditEmpDepto">Departamento</label>
        <select id="EditEmpDepto" required></select>
        <section class="FormModalConsulta__fila">
            <article>
                <label for="EditEmpNombres">Nombres</label>
                <input type="text" id="EditEmpNombres" required>
            </article>
            <article>
                <label for="EditEmpApellidos">Apellidos</label>
                <input type="text" id="EditEmpApellidos" required>
            </article>
        </section>
        <label for="EditEmpCodigo">Código</label>
        <input type="text" id="EditEmpCodigo" required>
        <label for="EditEmpCargo">Cargo</label>
        <input type="text" id="EditEmpCargo" required>
        <label for="EditEmpRif">RIF</label>
        <input type="text" id="EditEmpRif" name="edit_rif_personal_manual" required maxlength="14" autocomplete="off" spellcheck="false">
        <label for="EditEmpJefe">Jefe inmediato</label>
        <select id="EditEmpJefe"></select>
        <footer class="ModalConsulta__acciones">
            <button type="button" class="BotonSecundario" onclick="cerrarModal('ModalEditarEmpleado')">Cancelar</button>
            <button type="submit" class="BotonPrincipal">Guardar cambios</button>
        </footer>
    </form>
</dialog>
