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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hackatek - Mercado</title>
  <link rel="stylesheet" href="../recursos/css/general.css">
  <!-- (Opcional) Leaflet CSS solo si se reutiliza el mapa aquí -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <style>
    /* Ajustes específicos para tablas/controles del mercado dentro del mismo look */
    form.controls label{display:flex;flex-direction:column;font-size:.85rem;font-weight:600;color:var(--green-4);background:linear-gradient(180deg,var(--green-1),white);padding:12px;border-radius:8px;border:1px solid rgba(47,143,68,0.08)}
    form.controls input{margin-top:4px;padding:6px 8px;border:1px solid #c3d8c6;border-radius:6px;font:inherit}
    form.controls .preview-grid{align-items:start}
    .hint{color:var(--muted);font-size:.65rem;line-height:1.2;margin-top:4px}
    .small{font-size:.75rem;color:var(--muted)}
    .group-title{margin:12px 0 4px;color:var(--green-4)}
    .badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.65rem;line-height:1.3;letter-spacing:.5px;text-transform:uppercase;background:#d8e9db;color:#2f6138}
    .badge.success{background:#2f8f44;color:#fff}
    .badge.neutral{background:#cfe9d6;color:#2f6138}
    .highlight{outline:2px solid #2f8f44}
    ul.stats{list-style:none;margin:0;padding:0;font-size:.72rem;color:var(--muted)}
    ul.stats li{margin:2px 0}
    .warning{color:#a65c00;background:#fff4e0;padding:12px 16px;border-radius:8px;font-size:.8rem;border:1px solid #f5d5a3}
  </style>
</head>
<body>
  <main class="container">
    <section class="hero">
      <h2>Mercado y rentabilidad</h2>
      <p class="lead">Analiza precios, costos logísticos y decide el mejor destino para tu producto.</p>
    </section>

    <section id="controles" class="card" aria-label="Parámetros de cálculo de mercado">
      <h3 style="margin-top:0">Parámetros de cálculo</h3>
      <form class="controls" method="get" action="mercado.php">
        <div class="preview-grid">
          <label>
            Capacidad camión (ton)
            <input type="number" step="0.1" min="0" max="40" name="capacidad_ton" value="<?= h($_GET['capacidad_ton'] ?? '0') ?>" required>
            <small class="hint">Rango sugerido: 0 < capacidad ≤ 40 ton.</small>
          </label>
          <label>
            Costo por km (MXN)
            <span aria-label="Ayuda: costo por km" title="Costo total por kilómetro por camión (combustible, operador, casetas, mantenimiento). Se prorratea por tonelada.">ℹ️</span>
            <input type="number" step="0.1" min="0" max="200" name="costo_km" value="<?= h($_GET['costo_km'] ?? '0') ?>">
            <small class="hint">Flete por ton = (distancia_km × costo_km) / capacidad_camión.</small>
          </label>
            <label>
            Costo aduana (MXN)
            <span aria-label="Ayuda: costo de aduana" title="Incluye trámites y aranceles por cruce internacional por camión. Se prorratea por tonelada según la capacidad ingresada.">ℹ️</span>
            <input type="number" step="0.1" min="0" max="100000" name="costo_aduana" value="<?= h($_GET['costo_aduana'] ?? '0') ?>" required>
            <small class="hint">Se prorratea por ton: aduana_por_ton = costo_aduana / capacidad_camión.</small>
          </label>
          <label>
            Costo hora espera (MXN)
            <span aria-label="Ayuda: costo de espera" title="Costo por hora de inmovilización del camión en frontera (operador, combustible en ralentí, oportunidad). Se prorratea por tonelada.">ℹ️</span>
            <input type="number" step="0.1" min="0" max="10000" name="costo_espera_hora" value="<?= h($_GET['costo_espera_hora'] ?? '0') ?>">
            <small class="hint">Espera por ton = ((min_espera/60) × costo_espera_hora) / capacidad_camión.</small>
          </label>
          <label title="Se usa para convertir precios cotizados por caja a MXN por tonelada.">
            Cajas por tonelada
            <input type="number" step="1" min="0" max="200" name="cajas_por_ton" value="<?= h($_GET['cajas_por_ton'] ?? '0') ?>" required>
            <small class="hint">Rango sugerido: 0 < cajas_por_ton ≤ 200 (sugerido 50).</small>
          </label>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-primary" type="submit">Actualizar</button>
          <button class="btn btn-primary" type="button" onclick="window.location.href='mercado.php'">Restablecer</button>
        </div>
      </form>
      <p class="small">Última actualización: <?= h($timestamp) ?></p>
    </section>

    <section id="recomendacion" aria-label="Recomendación principal">
      <h3>Recomendación</h3>
      <?php if (!empty($recomendacion) && !empty($mejor)) : ?>
        <article class="card highlight">
          <header class="card-header">
            <span class="badge success">Mejor opción</span>
            <strong><?= h(($mejor['modo'] ?? '')) === 'exportacion' ? 'Exportación' : 'Mercado nacional' ?></strong>
            <?php if (!empty($mejor['mercado'])): ?><span>— <?= h($mejor['mercado']) ?></span><?php endif; ?>
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
        <?php if (!empty($recomendacion)): ?>
          <p class="warning"><?= h($recomendacion) ?></p>
        <?php else: ?>
          <p class="warning">No hay datos suficientes para mostrar una recomendación.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section id="top4" aria-label="Top 4 opciones" class="preview-clima">
      <h3>Top 4 opciones</h3>
      <div class="modules">
        <?php if (!empty($top4)) : foreach ($top4 as $op): ?>
          <article class="card module-card<?= ($op === $mejor ? ' highlight' : '') ?>" tabindex="0">
            <header class="card-header">
              <span class="badge <?= !empty($op['disponibilidad']) ? 'success' : 'neutral' ?>">
                <?= !empty($op['disponibilidad']) ? 'Disponible' : 'Sin datos' ?>
              </span>
              <strong><?= h(($op['modo'] ?? '') === 'exportacion' ? 'Exportación' : 'Nacional') ?></strong>
              <?php if (!empty($op['mercado'])): ?><span>— <?= h($op['mercado']) ?></span><?php endif; ?>
              <?php if (!empty($op['municipio'])): ?><span>— <?= h($op['municipio']) ?></span><?php endif; ?>
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

    <section id="opciones" aria-label="Todas las opciones calculadas">
      <h3>Todas las opciones</h3>
      <div class="modules">
        <?php if (!empty($opciones)) : foreach ($opciones as $op): ?>
          <article class="card module-card<?= ($op === $mejor ? ' highlight' : '') ?>" tabindex="0">
            <header class="card-header">
              <span class="badge <?= !empty($op['disponibilidad']) ? 'success' : 'neutral' ?>">
                <?= !empty($op['disponibilidad']) ? 'Disponible' : 'Sin datos' ?>
              </span>
              <strong><?= h(($op['modo'] ?? '') === 'exportacion' ? 'Exportación' : 'Nacional') ?></strong>
              <?php if (!empty($op['mercado'])): ?><span>— <?= h($op['mercado']) ?></span><?php endif; ?>
              <?php if (!empty($op['municipio'])): ?><span>— <?= h($op['municipio']) ?></span><?php endif; ?>
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

    <section id="agrupado" aria-label="Desglose agrupado">
      <h3>Desglose por país / estado</h3>
      <?php if (!empty($agrupado)) : foreach ($agrupado as $grupo => $lista): ?>
        <h4 class="group-title"><?= h($grupo) ?></h4>
        <div class="modules">
          <?php foreach ($lista as $op): ?>
            <article class="card module-card<?= ($op === $mejor ? ' highlight' : '') ?>" tabindex="0">
              <header class="card-header">
                <span class="badge <?= !empty($op['disponibilidad']) ? 'success' : 'neutral' ?>">
                  <?= !empty($op['disponibilidad']) ? 'Disponible' : 'Sin datos' ?>
                </span>
                <strong><?= h(($op['modo'] ?? '') === 'exportacion' ? 'Exportación' : 'Nacional') ?></strong>
                <?php if (!empty($op['mercado'])): ?><span>— <?= h($op['mercado']) ?></span><?php endif; ?>
                <?php if (!empty($op['municipio'])): ?><span>— <?= h($op['municipio']) ?></span><?php endif; ?>
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

    <section id="notas" aria-label="Notas aclaratorias">
      <h3>Notas</h3>
      <ul class="stats">
        <?php if (!empty($notas['costo_aduana'])): ?><li><?= h($notas['costo_aduana']) ?></li><?php endif; ?>
        <?php if (!empty($notas['cajas_por_ton'])): ?><li><?= h($notas['cajas_por_ton']) ?></li><?php endif; ?>
      </ul>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <small>© <?= date('Y') ?> Hackatek - Proyecto de ejemplo</small>
    </div>
  </footer>

  <!-- Modal reutilizable (igual que index para consistencia futura) -->
  <div id="module-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="modal-title">
    <div class="modal-content">
      <button class="modal-close" aria-label="Cerrar">×</button>
      <h3 id="modal-title"></h3>
      <div id="modal-body"></div>
    </div>
  </div>

  <!-- Leaflet JS opcional (solo si se agrega un mapa en esta página) -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
  <?php
    // Reutilizar lógica de API key como en index.php
    $owmKey = '';
    $configPath = __DIR__ . '/../includes/owm_config.php';
    if (file_exists($configPath)) {
      $owmKey = include $configPath;
    } else {
      $owmKey = getenv('OWM_API_KEY') ?: '';
    }
  ?>
  <script>window.OWM_API_KEY = <?= json_encode($owmKey); ?>;</script>
  <script src="../recursos/js/class.js" defer></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form.controls');
      if (!form) return;
      const cap = form.elements['capacidad_ton'];
      const cajas = form.elements['cajas_por_ton'];
      const costoKm = form.elements['costo_km'];
      const costoAdu = form.elements['costo_aduana'];
      const costoEsp = form.elements['costo_espera_hora'];

      const clearValidity = (ev) => ev.target.setCustomValidity('');
      [cap, cajas, costoKm, costoAdu, costoEsp].forEach(el => el && el.addEventListener('input', clearValidity));

      form.addEventListener('submit', function(e) {
        let valid = true;
        const inRange = (val, opts) => {
          const n = parseFloat(val);
          if (isNaN(n)) return false;
          if (opts.gt0 && !(n > 0)) return false;
          if (typeof opts.min === 'number' && n < opts.min) return false;
          if (typeof opts.max === 'number' && n > opts.max) return false;
          return true;
        };

        if (cap && valid) {
          if (!inRange(cap.value, { gt0: true, max: 40 })) {
            cap.setCustomValidity('Capacidad debe ser > 0 y ≤ 40 ton');
            cap.reportValidity(); valid = false;
          }
        }
        if (cajas && valid) {
          if (!inRange(cajas.value, { gt0: true, max: 200 })) {
            cajas.setCustomValidity('Cajas por tonelada debe ser > 0 y ≤ 200');
            cajas.reportValidity(); valid = false;
          }
        }
        if (costoKm && valid) {
          if (!inRange(costoKm.value, { min: 0, max: 200 })) {
            costoKm.setCustomValidity('Costo por km debe estar entre 0 y 200 MXN');
            costoKm.reportValidity(); valid = false;
          }
        }
        if (costoAdu && valid) {
          if (!inRange(costoAdu.value, { min: 0, max: 100000 })) {
            costoAdu.setCustomValidity('Costo de aduana debe estar entre 0 y 100,000 MXN');
            costoAdu.reportValidity(); valid = false;
          }
        }
        if (costoEsp && valid) {
          if (!inRange(costoEsp.value, { min: 0, max: 10000 })) {
            costoEsp.setCustomValidity('Costo hora espera debe estar entre 0 y 10,000 MXN');
            costoEsp.reportValidity(); valid = false;
          }
        }
        if (!valid) {
          e.preventDefault();
        }
      });
    });
  </script>
</body>
</html>
