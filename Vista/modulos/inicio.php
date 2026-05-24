<?php
declare(strict_types=1);

$mesActual = (int)date('n');
$anioActual = (int)date('Y');
?>
<section id="PanelInicio" class="PanelInicio">
    <header class="PanelInicio__cabecera">
        <section>
            <h2><i class="fas fa-chart-pie"></i> Resumen de Asistencias</h2>
            <p>Visualizacion mensual de asistencias, inasistencias, justificadas y retardos.</p>
        </section>
        <section class="PanelInicio__filtros">
            <label for="inicio-filtro-mes">Mes</label>
            <select id="inicio-filtro-mes" class="PanelInicio__select">
                <?php
                $meses = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                ];
                foreach ($meses as $num => $nombre) {
                    $sel = $num === $mesActual ? ' selected' : '';
                    echo '<option value="' . $num . '"' . $sel . '>' . $nombre . '</option>';
                }
                ?>
            </select>
            <label for="inicio-filtro-anio">Anio</label>
            <input type="number" id="inicio-filtro-anio" class="PanelInicio__input" min="2020" max="2099" value="<?php echo $anioActual; ?>">
            <button type="button" id="BtnActualizarGraficas" class="BtnPrimario">Actualizar</button>
        </section>
    </header>

    <p id="inicio-periodo-etiqueta" class="PanelInicio__periodo">Cargando periodo...</p>

    <section class="PanelInicio__graficas">
        <article class="PanelInicio__tarjeta">
            <h3>Distribucion mensual</h3>
            <p class="PanelInicio__sub">Porcentaje del mes por categoria</p>
            <canvas id="GraficaTortaMensual" height="280" aria-label="Grafico de torta mensual"></canvas>
        </article>
        <article class="PanelInicio__tarjeta">
            <h3>Por dia de la semana</h3>
            <p class="PanelInicio__sub">Cantidad acumulada segun el dia</p>
            <canvas id="GraficaBarrasSemana" height="280" aria-label="Grafico de barras por dia"></canvas>
        </article>
    </section>

    <section class="PanelInicio__resumen" id="PanelInicioResumen" aria-live="polite"></section>
</section>
