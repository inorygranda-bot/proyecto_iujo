<section id="SeccionGestiones" class="Principal">
    <header class="EncabezadoGestion">
        <section>
            <h2 class="Titulo">Usuarios y Accesos</h2>
            <p class="Subtitulo">Administra las cuentas de usuario y define permisos por rol.</p>
        </section>
        <nav class="BotonesHeader">
            <button type="button" class="BtnCrear" onclick="window.abrirListaRoles()">
                <i class="fas fa-shield-alt"></i> Gestionar Roles
            </button>
            <button type="button" class="BtnCrear" onclick="window.abrirModalUsuario()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </nav>
    </header>

    <article class="TarjetaGestion">
        <header class="TarjetaCabecera">
            <h3 class="Leyenda">Usuarios Registrados</h3>
            <input type="text" id="BuscadorUsuarios" placeholder="🔍 Buscar usuario..." onkeyup="window.filtrarUsuarios()" class="InputBuscador">
        </header>
        <table id="TablaUsuarios">
            <thead>
                <tr>
                    <th>Nombre Completo</th>
                    <th>Usuario</th>
                    <th>Rol Asignado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </article>
</section>

<section id="OverlayListaRoles" class="Overlay" style="display:none;">
    <article class="Modal ModalAncho">
        <header class="ModalCabecera">
            <h3>Roles del Sistema</h3>
            <button type="button" onclick="window.cerrarModales()">&times;</button>
        </header>
        <section class="CuerpoModal">
            <nav class="AccionTabla">
                <button type="button" class="BtnCrear" onclick="window.abrirModalRol()">
                    <i class="fas fa-plus"></i> Crear Nuevo Rol
                </button>
            </nav>
            <table id="TablaRoles">
                <thead>
                    <tr>
                        <th>Nombre del Rol</th>
                        <th>Módulos Permitidos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </section>
    </article>
</section>

<section id="OverlayRol" class="Overlay" style="display:none;">
    <article class="Modal">
        <header class="ModalCabecera">
            <h3 id="TituloModalRol">Nuevo Rol</h3>
            <button type="button" onclick="window.cerrarModales()">&times;</button>
        </header>
        <form id="FormRol" onsubmit="window.guardarRol(event)">
            <input type="hidden" id="R_Id">
            <fieldset class="Campo">
                <label for="R_Nombre">Nombre del Rol</label>
                <input type="text" id="R_Nombre" required placeholder="Ej. Supervisor de Planta">
            </fieldset>
            <fieldset class="Campo">
                <legend>Módulos Permitidos</legend>
                <section class="GridPermisos" id="GridModulosRol"></section>
            </fieldset>
            <footer class="ModalPie">
                <button type="button" class="BtnSecundario" onclick="window.cerrarModales()">Cancelar</button>
                <button type="submit" class="BtnPrimario">Guardar Rol</button>
            </footer>
        </form>
    </article>
</section>

<section id="OverlayUsuario" class="Overlay ModalAncho" style="display:none;">
    <article class="Modal">
        <header class="ModalCabecera">
            <h3 id="TituloModalUsuario">Nuevo Usuario</h3>
            <button type="button" onclick="window.cerrarModales()">&times;</button>
        </header>
        <form id="FormUsuario" onsubmit="window.guardarUsuario(event)">
            <input type="hidden" id="U_EditarLogin">
            
            <section class="FilaDoble">
                <fieldset class="Campo">
                    <label for="U_Nombre">Nombre Completo</label>
                    <input type="text" id="U_Nombre" required placeholder="Nombre del trabajador">
                </fieldset>
                <fieldset class="Campo">
                    <label for="U_Login">Usuario de Acceso</label>
                    <input type="text" id="U_Login" required placeholder="Ej. usuario.login">
                </fieldset>
            </section>
            
            <section class="FilaDoble">
                <fieldset class="Campo">
                    <label for="U_Clave">Contraseña</label>
                    <input type="password" id="U_Clave" placeholder="Dejar en blanco para no cambiar">
                </fieldset>
                <fieldset class="Campo">
                    <label for="U_Estado">Estado</label>
                    <select id="U_Estado">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </fieldset>
            </section>
            
            <fieldset class="Campo">
                <label for="U_Rol">Rol del Sistema</label>
                <select id="U_Rol" required>
                    <option value="">Seleccione un rol...</option>
                </select>
            </fieldset>
            
            <fieldset class="Campo">
                <legend>Empresas Asignadas</legend>
                <p class="TextoAyuda">Seleccione las empresas a las que este usuario tendrá acceso.</p>
                <section class="GridPermisos GridScroll" id="GridEmpresasUsuario"></section>
            </fieldset>
            
            <footer class="ModalPie">
                <button type="button" class="BtnSecundario" onclick="window.cerrarModales()">Cancelar</button>
                <button type="submit" class="BtnPrimario">Guardar Usuario</button>
            </footer>
        </form>
    </article>
</section>