<?php
// vistas/insumos.php
// Calculadora informativa de Insumos y Costos (solo muestra cantidad y costo estimado)

declare(strict_types=1);

$catalogo = include __DIR__ . '/../includes/insumos_data.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hackatek - Calculadora de Insumos</title>
  <link rel="stylesheet" href="../recursos/css/general.css">
  <style>
    form.controls label{display:flex;flex-direction:column;font-size:.85rem;font-weight:600;color:var(--green-4);background:linear-gradient(180deg,var(--green-1),white);padding:12px;border-radius:8px;border:1px solid rgba(47,143,68,0.08)}
    form.controls input, form.controls select{margin-top:4px;padding:6px 8px;border:1px solid #c3d8c6;border-radius:6px;font:inherit}
    .result-box{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px}
    .result{background:white;border:1px solid #e0eee3;border-radius:8px;padding:12px}
    .result strong{color:#2f6138}
    .list-table{width:100%;border-collapse:collapse}
    .list-table th,.list-table td{padding:8px;border-bottom:1px solid #e7f2ea;text-align:left}
    .list-table th{color:#2f6138;font-weight:700}
    .right{text-align:right}
  </style>
</head>
<body>
  <main class="container">
    <section class="hero">
      <h2>Calculadora de Insumos y Costos</h2>
      <p class="lead">Selecciona un insumo, ingresa las hectáreas de tu parcela y consulta la cantidad requerida y el costo estimado. Esta es una página informativa; no realiza compras.</p>
    </section>

    <section class="card" aria-label="Calculadora">
  <form class="controls" id="calc-form" onsubmit="return false;">
        <div class="preview-grid">
          <label>
            Insumo
            <select name="insumo_id" id="insumo_id" required>
              <option value="">Selecciona…</option>
              <?php foreach ($catalogo as $ins): ?>
                <option value="<?= h($ins['id']) ?>" data-dosis="<?= h($ins['dosis_por_hectarea']) ?>" data-precio="<?= h($ins['precio_por_unidad']) ?>" data-unidad="<?= h($ins['unidad_dosis']) ?>">
                  <?= h($ins['nombre']) ?> (<?= h($ins['unidad_dosis']) ?> @ <?= h(number_format((float)$ins['dosis_por_hectarea'], 2)) ?> | $<?= h(number_format((float)$ins['precio_por_unidad'], 2)) ?>/u)
                </option>
              <?php endforeach; ?>
            </select>
            <small class="hint">La dosis y el precio son sugeridos; puedes ajustarlos.</small>
          </label>
          <label>
            Área del terreno (ha)
            <input type="number" step="0.01" min="0" name="area_ha" id="area_ha" placeholder="Ej. 5" required>
          </label>
          <label>
            Dosis por hectárea (unidad indicada)
            <input type="number" step="0.01" min="0" name="dosis_custom" id="dosis_custom" placeholder="Usar sugerida" aria-describedby="unidad-dosis">
            <small id="unidad-dosis" class="hint">Unidad: <span id="unidad_label">—</span>. Deja vacío para usar la dosis sugerida del catálogo.</small>
          </label>
          <label>
            Precio por unidad (MXN)
            <input type="number" step="0.01" min="0" name="precio_custom" id="precio_custom" placeholder="Usar precio catálogo">
            <small class="hint">Deja vacío para usar el precio promedio del catálogo.</small>
          </label>
        </div>
        <div class="result-box" id="resultados" aria-live="polite">
          <div class="result"><div>Necesitas</div><strong id="res_cantidad">—</strong> <span id="res_unidad"></span></div>
          <div class="result"><div>Costo estimado</div><strong>$<span id="res_costo">—</span> MXN</strong></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-primary" type="button" id="btn-limpiar-form">Limpiar</button>
        </div>
      </form>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <small>© <?= date('Y') ?> Hackatek - Proyecto de ejemplo</small>
    </div>
  </footer>

  <script>
    // Lógica de cálculo instantáneo en el cliente
    const sel = (q) => document.querySelector(q);
    const fmt = (n) => new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

    const insumo = sel('#insumo_id');
    const area = sel('#area_ha');
    const dosis = sel('#dosis_custom');
    const precio = sel('#precio_custom');
    const unidadLabel = sel('#unidad_label');
    const resCant = sel('#res_cantidad');
    const resUnidad = sel('#res_unidad');
    const resCosto = sel('#res_costo');

    function valoresBase() {
      const opt = insumo.options[insumo.selectedIndex];
      const base = {
        dosis: parseFloat(opt?.dataset?.dosis || '0') || 0,
        precio: parseFloat(opt?.dataset?.precio || '0') || 0,
        unidad: opt?.dataset?.unidad || ''
      };
      return base;
    }

    function recalcular() {
      const base = valoresBase();
      unidadLabel.textContent = base.unidad || '—';
      resUnidad.textContent = base.unidad || '';

      const ha = parseFloat(area.value);
      if (!isFinite(ha) || ha <= 0 || !insumo.value) {
        resCant.textContent = '—';
        resCosto.textContent = '—';
        return;
      }
      const d = parseFloat(dosis.value) > 0 ? parseFloat(dosis.value) : base.dosis;
      const p = parseFloat(precio.value) > 0 ? parseFloat(precio.value) : base.precio;
      const cantidad = d * ha; // en unidad
      const costo = cantidad * p;
      resCant.textContent = fmt(cantidad);
      resCosto.textContent = fmt(costo);
    }

    ['change','input'].forEach(ev => {
      [insumo, area, dosis, precio].forEach(el => el && el.addEventListener(ev, recalcular));
    });
    document.addEventListener('DOMContentLoaded', recalcular);

    sel('#btn-limpiar-form')?.addEventListener('click', () => {
      area.value = '';
      dosis.value = '';
      precio.value = '';
      insumo.selectedIndex = 0;
      recalcular();
    });
  </script>
</body>
</html>
