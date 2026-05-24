<!-- HOLA PERRITOS cuando hagan su html dentro del php no van a colocar lo de Doctype ni head ni main

 eso ya esta en el index php y los demas php que se hacen solo se pegan en ese -->

<!-- Tambien hay una extension llamada PHP Intelephense que ayuda a escribir php mas rapido y a ordenar el codigo

  si es para ordenar le dan primero cntrl + shift + p y luego escriben format document with y le dan enter y seleccionan el que les sale -->

<section id="Registro" class="Principal">


 <section class="Tarjeta-Intro">
        <h2>Registro de empresa</h2>
        <p>Registre únicamente los datos de la empresa. Departamentos, empleados y supervisores se gestionan desde la sección de consulta.</p>
    </section>

    <article id="CajaEmpresa" class="Tarjeta">



        <button type="button" class="BotonEsquinaVolver" onclick="VolverAlInicio()">Volver al Menú</button>



        <form id="FormularioEmpresa" autocomplete="off">

            <h3 class="Leyenda">Nueva Empresa</h3>



            <label for="NombreEmpresa">Nombre o Razón Social</label>

            <input type="text" id="NombreEmpresa" placeholder="Nombre de la empresa" required>



            <label for="RifEmpresa">RIF (Empresarial)</label>

            <input type="text" id="RifEmpresa" name="rif_empresa_manual" placeholder="J-12345678-0" maxlength="20" autocomplete="off" spellcheck="false" required> <!-- Le agrandé el tamaño para ver si es por esto que no se guarda en la BD -->

            <label for="ObjetivoEmpresa">Objetivo de la empresa</label>

            <textarea id="ObjetivoEmpresa" placeholder="Opcional"></textarea>


            <button type="submit" class="BotonSiguiente">Crear empresa</button>

        </form>

    </article>

    <!-- Tarjeta tras guardar la empresa en MySQL -->

    <article id="CajaExito" class="Tarjeta">

        <h3 class="Leyenda">Empresa creada</h3>

        <p class="Subtitulo">¿Desea visualizar la empresa en la página de gestión?</p>

        <nav class="Botones Vertical">

            <a href="index.php?p=consulta" class="BotonSiguiente">Ir a consulta</a>
            <!-- Agregue este boton, pero no supe que estilo ponerle asi que le puse el mismo del otro, nose Emily si se lo vas a cambiar jiji -->
            <a href="index.php?p=inicio" class="BotonSiguiente">Volver al menú</a>

        </nav>

    </article>



</section>

