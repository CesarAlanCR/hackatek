<?php
// vistas/mercado.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/market_analyzer.php';

// Ejecuta el análisis (toma en cuenta parámetros GET como capacidad_ton, costo_km, etc.)
$resultado = analizar_mercado_completo();
$mejor = $resultado['mejor_opcion'] ?? [];
$opciones = $resultado['todas_las_opciones'] ?? [];
$recomendacion = $resultado['recomendacion'] ?? '';
$timestamp = $resultado['timestamp'] ?? date('c');
// Nuevas estructuras del analizador
$top4 = $resultado['top4'] ?? [];
$agrupado = $resultado['agrupado'] ?? [];
$notas = $resultado['notas'] ?? [];

// Helpers
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OptiLife - Análisis de mercado</title>
  <link rel="stylesheet" href="../recursos/css/general.css">
</head>
<body>
  <!-- Header eliminado para mantener el mismo diseño que index.php -->

  <main class="container">
    <section id="controles">
      <h2>Parámetros de cálculo</h2>
      <form class="controls" method="get" action="mercado.php">
        <div class="preview-grid">
          <label>
            Capacidad camión (ton)
            <input type="number" step="0.1" name="capacidad_ton" value="<?= h($_GET['capacidad_ton'] ?? '20') ?>">
          </label>
          <label>
            Costo por km (MXN)
            <span aria-label="Ayuda: costo por km" title="Costo total por kilómetro por camión (combustible, operador, casetas, mantenimiento). Se prorratea por tonelada.">ℹ️</span>
            <input type="number" step="0.1" name="costo_km" value="<?= h($_GET['costo_km'] ?? '35') ?>">
            <small class="hint">Flete por ton = (distancia_km × costo_km) / capacidad_camión.</small>
          </label>
          <label>
            Costo aduana (MXN)
            <span aria-label="Ayuda: costo de aduana" title="Incluye trámites y aranceles por cruce internacional por camión. Se prorratea por tonelada según la capacidad ingresada.">ℹ️</span>
            <input type="number" step="0.1" min="0" name="costo_aduana" value="<?= h($_GET['costo_aduana'] ?? '5000') ?>" required>
            <small class="hint">Se prorratea por tonelada: aduana_por_ton = costo_aduana / capacidad_camión.</small>
          </label>
          <label>
            Costo hora espera (MXN)
            <span aria-label="Ayuda: costo de espera" title="Costo por hora de inmovilización del camión en frontera (operador, combustible en ralentí, oportunidad). Se prorratea por tonelada.">ℹ️</span>
            <input type="number" step="0.1" name="costo_espera_hora" value="<?= h($_GET['costo_espera_hora'] ?? '400') ?>">
            <small class="hint">Espera por ton = ((min_espera/60) × costo_espera_hora) / capacidad_camión.</small>
          </label>
          <label title="Se usa para convertir precios cotizados por caja a MXN por tonelada.">
            Cajas por tonelada
            <input type="number" step="1" min="1" name="cajas_por_ton" value="<?= h($_GET['cajas_por_ton'] ?? '50') ?>" required>
          </label>
        </div>
        <div style="margin-top: 12px;">
          <button class="btn btn-primary" type="submit">Actualizar</button>
          <a class="btn btn-primary" href="mercado.php">Restablecer</a>
        </div>
      </form>
      <p class="small">Última actualización: <?= h($timestamp) ?></p>
    </section>

    <section id="top4">
      <h2>Top 4 opciones</h2>
      <div class="modules">
        <?php if (!empty($top4)) : foreach ($top4 as $op): ?>
          <article class="card module-card<?= ($op === $mejor ? ' highlight' : '') ?>">
            <header class="card-header">
              <span class="badge <?= !empty($op['disponibilidad']) ? 'success' : 'neutral' ?>">
                <?= !empty($op['disponibilidad']) ? 'Disponible' : 'Sin datos' ?>
              </span>
              <strong><?= h(($op['modo'] ?? '') === 'exportacion' ? 'Exportación' : 'Nacional') ?></strong>
              <?php if (!empty($op['mercado'])): ?>
                <span>— <?= h($op['mercado']) ?></span>
              <?php endif; ?>
              <?php if (!empty($op['municipio'])): ?>
                <span>— <?= h($op['municipio']) ?></span>
              <?php endif; ?>
            </header>
            <div class="card-body">
              <ul class="stats">
                <li><strong>Ganancia neta:</strong> $<?= h(number_format((float)($op['ganancia_neta_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <li><strong>Ingreso bruto:</strong> $<?= h(number_format((float)($op['ingreso_bruto_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <li><strong>Flete:</strong> $<?= h(number_format((float)($op['costos']['flete_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <?php if (($op['modo'] ?? '') === 'exportacion'): ?>
                  <li><strong>Aduana:</strong> $<?= h(number_format((float)($op['costos']['aduana_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <li><strong>Espera:</strong> $<?= h(number_format((float)($op['costos']['espera_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <li><strong>Tipo de cambio:</strong> <?= h(number_format((float)($op['tipo_cambio_usd_mxn'] ?? 0), 4)) ?></li>
                <?php endif; ?>
                <li><strong>Distancia:</strong> <?= h(number_format((float)($op['distancia_km'] ?? 0), 2)) ?> km</li>
              </ul>
            </div>
          </article>
        <?php endforeach; else: ?>
          <p>No hay opciones para mostrar.</p>
        <?php endif; ?>
      </div>
    </section>

    <section id="recomendacion">
      <h2>Recomendación</h2>
      <?php if (!empty($recomendacion) && !empty($mejor)) : ?>
        <article class="card highlight">
          <header class="card-header">
            <span class="badge success">Mejor opción</span>
            <strong><?= h(($mejor['modo'] ?? '')) === 'exportacion' ? 'Exportación' : 'Mercado nacional' ?></strong>
            <?php if (!empty($mejor['mercado'])): ?>
              <span>— <?= h($mejor['mercado']) ?></span>
            <?php endif; ?>
          </header>
          <div class="card-body">
            <p><?= h($recomendacion) ?></p>
            <ul class="stats">
              <li><strong>Ganancia neta:</strong> $<?= h(number_format((float)($mejor['ganancia_neta_mxn'] ?? 0), 2)) ?> MXN/ton</li>
              <li><strong>Ingreso bruto:</strong> $<?= h(number_format((float)($mejor['ingreso_bruto_mxn'] ?? 0), 2)) ?> MXN/ton</li>
              <li><strong>Flete:</strong> $<?= h(number_format((float)($mejor['costos']['flete_mxn'] ?? 0), 2)) ?> MXN/ton</li>
              <?php if (($mejor['modo'] ?? '') === 'exportacion'): ?>
                <li><strong>Aduana:</strong> $<?= h(number_format((float)($mejor['costos']['aduana_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <li><strong>Espera:</strong> $<?= h(number_format((float)($mejor['costos']['espera_mxn'] ?? 0), 2)) ?> MXN/ton</li>
              <?php endif; ?>
              <li><strong>Distancia:</strong> <?= h(number_format((float)($mejor['distancia_km'] ?? 0), 2)) ?> km</li>
            </ul>
          </div>
        </article>
      <?php else: ?>
        <p class="warning">No hay datos suficientes para mostrar una recomendación.</p>
      <?php endif; ?>
    </section>

    <section id="opciones">
      <h2>Todas las opciones</h2>
      <div class="modules">
        <?php if (!empty($opciones)) : foreach ($opciones as $op): ?>
          <article class="card module-card<?= ($op === $mejor ? ' highlight' : '') ?>">
            <header class="card-header">
              <span class="badge <?= !empty($op['disponibilidad']) ? 'success' : 'neutral' ?>">
                <?= !empty($op['disponibilidad']) ? 'Disponible' : 'Sin datos' ?>
              </span>
              <strong><?= h(($op['modo'] ?? '') === 'exportacion' ? 'Exportación' : 'Nacional') ?></strong>
              <?php if (!empty($op['mercado'])): ?>
                <span>— <?= h($op['mercado']) ?></span>
              <?php endif; ?>
              <?php if (!empty($op['municipio'])): ?>
                <span>— <?= h($op['municipio']) ?></span>
              <?php endif; ?>
            </header>
            <div class="card-body">
              <ul class="stats">
                <li><strong>Ganancia neta:</strong> $<?= h(number_format((float)($op['ganancia_neta_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <li><strong>Ingreso bruto:</strong> $<?= h(number_format((float)($op['ingreso_bruto_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <li><strong>Flete:</strong> $<?= h(number_format((float)($op['costos']['flete_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                <?php if (($op['modo'] ?? '') === 'exportacion'): ?>
                  <li><strong>Aduana:</strong> $<?= h(number_format((float)($op['costos']['aduana_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <li><strong>Espera:</strong> $<?= h(number_format((float)($op['costos']['espera_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <li><strong>Tipo de cambio:</strong> <?= h(number_format((float)($op['tipo_cambio_usd_mxn'] ?? 0), 4)) ?></li>
                <?php endif; ?>
                <li><strong>Distancia:</strong> <?= h(number_format((float)($op['distancia_km'] ?? 0), 2)) ?> km</li>
              </ul>
            </div>
          </article>
        <?php endforeach; else: ?>
          <p>No hay opciones disponibles.</p>
        <?php endif; ?>
      </div>
    </section>
    <section id="agrupado">
      <h2>Desglose por país/estado</h2>
      <?php if (!empty($agrupado)) : foreach ($agrupado as $grupo => $lista): ?>
        <h3 class="group-title"><?= h($grupo) ?></h3>
        <div class="modules">
          <?php foreach ($lista as $op): ?>
            <article class="card module-card<?= ($op === $mejor ? ' highlight' : '') ?>">
              <header class="card-header">
                <span class="badge <?= !empty($op['disponibilidad']) ? 'success' : 'neutral' ?>">
                  <?= !empty($op['disponibilidad']) ? 'Disponible' : 'Sin datos' ?>
                </span>
                <strong><?= h(($op['modo'] ?? '') === 'exportacion' ? 'Exportación' : 'Nacional') ?></strong>
                <?php if (!empty($op['mercado'])): ?>
                  <span>— <?= h($op['mercado']) ?></span>
                <?php endif; ?>
                <?php if (!empty($op['municipio'])): ?>
                  <span>— <?= h($op['municipio']) ?></span>
                <?php endif; ?>
              </header>
              <div class="card-body">
                <ul class="stats">
                  <li><strong>Ganancia neta:</strong> $<?= h(number_format((float)($op['ganancia_neta_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <li><strong>Ingreso bruto:</strong> $<?= h(number_format((float)($op['ingreso_bruto_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <li><strong>Flete:</strong> $<?= h(number_format((float)($op['costos']['flete_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                  <?php if (($op['modo'] ?? '') === 'exportacion'): ?>
                    <li><strong>Aduana:</strong> $<?= h(number_format((float)($op['costos']['aduana_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                    <li><strong>Espera:</strong> $<?= h(number_format((float)($op['costos']['espera_mxn'] ?? 0), 2)) ?> MXN/ton</li>
                    <li><strong>Tipo de cambio:</strong> <?= h(number_format((float)($op['tipo_cambio_usd_mxn'] ?? 0), 4)) ?></li>
                  <?php endif; ?>
                  <li><strong>Distancia:</strong> <?= h(number_format((float)($op['distancia_km'] ?? 0), 2)) ?> km</li>
                </ul>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endforeach; else: ?>
        <p>No hay información agrupada para mostrar.</p>
      <?php endif; ?>
    </section>

    <section id="notas">
      <h2>Notas</h2>
      <ul>
        <?php if (!empty($notas['costo_aduana'])): ?>
          <li><?= h($notas['costo_aduana']) ?></li>
        <?php endif; ?>
        <?php if (!empty($notas['cajas_por_ton'])): ?>
          <li><?= h($notas['cajas_por_ton']) ?></li>
        <?php endif; ?>
      </ul>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <small>© <?= date('Y') ?> Hackatek - Proyecto de ejemplo</small>
    </div>
  </footer>

  <script src="../recursos/js/class.js"></script>
</body>
</html>
