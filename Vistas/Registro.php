<!-- Sección de Registro de Empresa -->
 
<section id="Registro" class="Principal">

    <header class="Encabezado">
        <h2 class="Titulo">Registro de Empresa</h2>
        <p class="Subtitulo">Registre únicamente los datos de la empresa. Departamentos, empleados y supervisores se gestionan desde la sección de consulta.</p>
    </header>

    <article id="CajaEmpresa" class="Tarjeta">
        <button type="button" class="BotonEsquinaVolver" onclick="volverMenuReportes()">Volver al Menú</button>

        <form id="FormularioEmpresa" autocomplete="off">
            <h3 class="Leyenda">Nueva Empresa</h3>

            <label for="NombreEmpresa">Nombre o Razón Social</label>
            <input type="text" id="NombreEmpresa" name="nombre" placeholder="Nombre de la empresa" required>

            <label for="RifEmpresa">RIF (Empresarial)</label>
            <input type="text" id="RifEmpresa" name="rif" placeholder="J-12345678-0" maxlength="14" required>

            <label for="ObjetivoEmpresa">Objetivo de la empresa</label>
            <textarea id="ObjetivoEmpresa" name="objetivo" placeholder="Describa brevemente el propósito (Opcional)"></textarea>

            <button type="submit" class="BotonSiguiente" id="BtnCrearEmpresa">
                <i class="fas fa-plus-circle"></i> Crear empresa
            </button>
        </form>
    </article>
    
    <article id="CajaExito" class="Tarjeta" style="display: none;">
        <div class="IconoExito" style="text-align: center; font-size: 3rem; color: #d1a7b9;">
            <i class="fas fa-check-double"></i>
        </div>
        <h3 class="Leyenda">¡Empresa creada!</h3>
        <p class="Subtitulo">Los datos se han almacenado correctamente. ¿Desea visualizar la empresa ahora?</p>

        <nav class="Botones Vertical">
            <a href="index.php?p=consulta" class="BotonSiguiente">Ir a consulta</a>
            <button type="button" class="BotonEnlace" onclick="location.reload()">Registrar otra</button>
        </nav>
    </article>

</section>