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
    .result-box{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px;justify-content:center}
    .result{background:white;border:1px solid #e0eee3;border-radius:8px;padding:12px;text-align:center}
    .result strong{color:#2f6138}
  /* Fondo azul aún más claro para resaltar suavemente */
  .result.accent{background:#f0f8ff;border-color:#cfe8ff;color:#2f6138}
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
                <option value="<?= h($ins['id']) ?>" data-dosis="<?= h($ins['dosis_por_hectarea']) ?>" data-precio="<?= h($ins['precio_por_unidad']) ?>" data-unidad="<?= h($ins['unidad_dosis']) ?>" data-tipo="<?= h($ins['tipo']) ?>">
                  <?= h($ins['nombre']) ?> (<?= h($ins['unidad_dosis']) ?> @ <?= h(number_format((float)$ins['dosis_por_hectarea'], 2)) ?> | $<?= h(number_format((float)$ins['precio_por_unidad'], 2)) ?>/u)
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Área del terreno (ha)
            <input type="number" step="0.01" min="0" name="area_ha" id="area_ha" placeholder="Ej. 5" required>
          </label>
        </div>
        <div class="result-box" id="resultados" aria-live="polite">
          <div class="result accent">
            <div>Dosis por hectárea</div>
            <strong><span id="res_dosis_val">—</span> <span id="res_dosis_unit">—</span></strong>
          </div>
          <div class="result accent">
            <div>Necesitas</div>
            <strong id="res_cantidad">—</strong> <span id="res_unidad_total"></span>
          </div>
          <div class="result accent">
            <div>Costo total</div>
            <strong>$<span id="res_costo">—</span> MXN</strong>
          </div>
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
    // Cálculo instantáneo (informativo)
    const sel = (q) => document.querySelector(q);
    const fmt = (n) => new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

    const insumo = sel('#insumo_id');
    const area = sel('#area_ha');
    const resCant = sel('#res_cantidad');
    const resUnidadTotal = sel('#res_unidad_total');
  const resCosto = sel('#res_costo');
    const resDosisVal = sel('#res_dosis_val');
    const resDosisUnit = sel('#res_dosis_unit');

    function valoresBase() {
      const opt = insumo.options[insumo.selectedIndex];
      return {
        dosis: parseFloat(opt?.dataset?.dosis || '0') || 0,
        precio: parseFloat(opt?.dataset?.precio || '0') || 0,
        unidad: opt?.dataset?.unidad || '',
        tipo: opt?.dataset?.tipo || ''
      };
    }

    function setAccentColors(tipo) {
      const map = {
        'fertilizante': { bg: '#eaf6ee', border: '#ccebd8' },
        'herbicida': { bg: '#ffecec', border: '#ffbaba' },
        'insecticida': { bg: '#fff8e1', border: '#ffe0a3' },
        'fungicida': { bg: '#f3e8ff', border: '#dcc4ff' },
        'micronutriente': { bg: '#e6f4ff', border: '#cfe8ff' },
        'mejorador_suelo': { bg: '#f5efe6', border: '#e3d6c6' },
        'enmienda': { bg: '#f2f2e9', border: '#ddd6c2' },
      };
      const colors = map[tipo] || { bg: '#f0f8ff', border: '#cfe8ff' };
      document.querySelectorAll('.result.accent').forEach(el => {
        el.style.backgroundColor = colors.bg;
        el.style.borderColor = colors.border;
      });
    }

    function unidadBasica(u) {
      if (!u) return '';
      const idx = u.indexOf('/');
      return idx > 0 ? u.slice(0, idx) : u; // 'kg/ha' -> 'kg'
    }

    function recalcular() {
  const base = valoresBase();
      const ha = parseFloat(area.value);

  // Aplicar color según tipo de insumo
  setAccentColors(base.tipo);

      // Mostrar dosis por hectárea si hay insumo seleccionado
      if (insumo.value) {
        resDosisVal.textContent = fmt(base.dosis);
        resDosisUnit.textContent = base.unidad || '—';
      } else {
        resDosisVal.textContent = '—';
        resDosisUnit.textContent = '—';
      }

      if (!isFinite(ha) || ha <= 0 || !insumo.value) {
        resCant.textContent = '—';
        resUnidadTotal.textContent = '';
        resCosto.textContent = '—';
        return;
      }
      const cantidad = base.dosis * ha; // en unidad base (kg o L)
      const costo = cantidad * base.precio;
      resCant.textContent = fmt(cantidad);
      resUnidadTotal.textContent = unidadBasica(base.unidad);
      resCosto.textContent = fmt(costo);
    }

    ['change','input'].forEach(ev => {
      [insumo, area].forEach(el => el && el.addEventListener(ev, recalcular));
    });
    document.addEventListener('DOMContentLoaded', recalcular);

    sel('#btn-limpiar-form')?.addEventListener('click', () => {
      area.value = '';
      insumo.selectedIndex = 0;
      recalcular();
    });
  </script>
</body>
</html>
