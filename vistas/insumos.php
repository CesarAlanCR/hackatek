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
  	<link rel="icon" type="image/png" href="logo.png">

  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Hackatek - Calculadora de Insumos</title>
  <link rel="stylesheet" href="../recursos/css/general.css">
  <style>
    /* Page header similar to chat/agua pages */
  .page-header{padding:18px 20px;border-bottom:1px solid var(--border);background:rgba(30,41,54,0.6);backdrop-filter:blur(8px);display:flex;align-items:center;gap:12px;margin-bottom:0;border-radius:12px 12px 0 0}
  .page-header h2{margin:0;color:var(--accent);font-size:1.2rem;font-weight:700;text-align:center;flex:1;transform:translateX(-62px);pointer-events:none}
  .btn-back{background:rgba(124, 179, 66, 0.12);border:1px solid var(--border);color:var(--accent);padding:8px 14px;border-radius:10px;font-weight:600;text-decoration:none;position:relative;z-index:3;transition:var(--transition-fast);box-shadow:none;text-shadow:none}
  .btn-back:hover{background:var(--accent);color:#fff;transform:translateX(-4px)}
  /* remove any pseudo-element overlay that creates a white sheen */
  .btn-back::before, .btn-back::after{display:none !important;content:none !important}

  /* container that groups header + card to match chat/agua layouts */
  .page-card{background:var(--bg-card);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-lg);border:1px solid var(--border);}
  .page-card .page-header{margin:0;padding:18px 20px}
  .page-card .card{border-radius:0 0 var(--radius-lg) var(--radius-lg);margin:0}

    /* Disable hover effects inside the grouped page-card so header+form look like a single panel */
    .page-card .card:hover{transform:none;box-shadow:var(--shadow);background:var(--bg-card);border-color:var(--border)}
    .page-card .card::before,.page-card .card::after{opacity:0}

  form.controls label{display:flex;flex-direction:column;font-size:.85rem;font-weight:600;color:var(--green-4);background:transparent;padding:8px 0;border-radius:6px;border:0}
  form.controls input, form.controls select{margin-top:4px;padding:6px 8px;border:1px solid #e5e7eb;border-radius:6px;font:inherit;background:#ffffff}
    .result-box{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px;justify-content:center}
  /* Pure white backgrounds for cards */
  .result{background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center}
    .result strong{color:#2f6138}
  /* Accent: white as well to keep consistent */
  .result.accent{background:#ffffff;border-color:#e5e7eb;color:#2f6138}
    .list-table{width:100%;border-collapse:collapse}
    .list-table th,.list-table td{padding:8px;border-bottom:1px solid #e7f2ea;text-align:left}
    .list-table th{color:#2f6138;font-weight:700}
    .right{text-align:right}
  </style>
</head>
<body>
  <main class="container" style="padding:40px 0">
    <div class="page-card">
      <div class="page-header">
        <a href="index.php" class="btn-back">← Volver</a>
        <h2>Calculadora de Insumos</h2>
      </div>
      <section class="card" aria-label="Calculadora">
      <form class="controls" id="calc-form" onsubmit="return false;">
        <div class="preview-grid">
          <?php
            // Agrupar por tipo
            $agr = [];
            foreach ($catalogo as $ins) {
              $t = $ins['tipo'] ?? 'otros';
              if (!isset($agr[$t])) $agr[$t] = [];
              $agr[$t][] = $ins;
            }
            // Etiquetas legibles y orden
            $labels = [
              'fertilizante' => 'Fertilizantes',
              'herbicida' => 'Herbicidas',
              'insecticida' => 'Insecticidas',
              'fungicida' => 'Fungicidas',
              'micronutriente' => 'Micronutrientes',
              'mejorador_suelo' => 'Mejoradores de suelo',
              'enmienda' => 'Enmiendas',
              'otros' => 'Otros'
            ];
            $orden = array_keys($labels);
            $tipos = array_unique(array_merge($orden, array_keys($agr)));
            foreach ($tipos as $tipo) {
              if (empty($agr[$tipo])) continue;
              $label = $labels[$tipo] ?? ucfirst(str_replace('_',' ', $tipo));
              $selectId = 'insumo_' . preg_replace('/[^a-z0-9_\-]/i','_', $tipo);
          ?>
          <label>
            <?= h($label) ?>
            <select name="<?= h($selectId) ?>" id="<?= h($selectId) ?>" class="insumo-select">
              <option value="">Selecciona…</option>
              <?php foreach ($agr[$tipo] as $ins): ?>
                <option value="<?= h($ins['id']) ?>"
                        data-dosis="<?= h($ins['dosis_por_hectarea']) ?>"
                        data-precio="<?= h($ins['precio_por_unidad']) ?>"
                        data-unidad="<?= h($ins['unidad_dosis']) ?>"
                        data-tipo="<?= h($ins['tipo']) ?>">
                  <?= h($ins['nombre']) ?> (<?= h($ins['unidad_dosis']) ?> @ <?= h(number_format((float)$ins['dosis_por_hectarea'], 2)) ?> | $<?= h(number_format((float)$ins['precio_por_unidad'], 2)) ?>/u)
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <?php } ?>
          <label>
            Área general (ha, opcional)
            <input type="number" step="0.01" min="0" name="area_ha" id="area_ha" placeholder="Ej. 5">
            <small class="hint">Esta área se aplica a todos los insumos seleccionados.</small>
          </label>
            
        </div>
        <div class="result-box" id="resultados" aria-live="polite">
          <div id="results-list" class="results-list" style="display:contents;"></div>
          <div class="result accent" id="subtotal-card" style="display:none">
            <div>Subtotal</div>
            <strong>$<span id="subtotal-costo">—</span> MXN</strong>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
          <button class="btn btn-primary" type="button" id="btn-limpiar-form">Limpiar</button>
        </div>
      </form>
    </section>
  </main>


  <script>
    // Cálculo instantáneo (informativo) con hasta dos insumos seleccionados
    const sel = (q) => document.querySelector(q);
    const fmt = (n) => new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);

  const area = sel('#area_ha');
    const resultsList = sel('#results-list');
    const subtotalCard = sel('#subtotal-card');
    const subtotalCostoEl = sel('#subtotal-costo');

    const insumoSelects = () => Array.from(document.querySelectorAll('.insumo-select'));

    function unidadBasica(u) {
      if (!u) return '';
      const idx = u.indexOf('/');
      return idx > 0 ? u.slice(0, idx) : u; // 'kg/ha' -> 'kg'
    }

    function getSelectedOptions(){
      // Considerar todos los insumos seleccionados (uno por categoría)
      const all = [];
      for (const s of insumoSelects()){
        const opt = s.options[s.selectedIndex];
        if (opt && s.selectedIndex > 0) all.push(opt);
      }
      return all;
    }

  // Sin áreas por insumo; solo se usa el área global

    // Sin mapeo de colores: todo se muestra en blanco

    function styleSelects(){
      // Mantener selects sin color (blancos) independientemente de la selección
      insumoSelects().forEach(s => {
        s.style.backgroundColor = '';
        s.style.borderColor = '';
      });
    }

    function buildCard(data){
      const { id, nombre, dosis, unidad, cantidad, costo } = data;
      const card = document.createElement('div');
      card.className = 'result accent insumo-card';
      // Mantener fondo y borde por CSS (blanco)
      card.innerHTML = `
        <div style="margin-bottom:6px"><strong>Insumo:</strong> ${nombre}</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;align-items:end">
          <div style="text-align:left">
            <div style="font-weight:700;color:#2f6138;font-size:.85rem">Dosis por hectárea</div>
            <div>${fmt(dosis)} ${unidad}</div>
          </div>
          <div style="text-align:left">
            <div style="font-weight:700;color:#2f6138;font-size:.85rem">Necesitas</div>
            <div>${isFinite(cantidad) ? fmt(cantidad) : '—'} ${isFinite(cantidad) ? unidadBasica(unidad) : ''}</div>
          </div>
          <div style="text-align:left">
            <div style="font-weight:700;color:#2f6138;font-size:.85rem">Costo total</div>
            <div>$${isFinite(costo) ? fmt(costo) : '—'} MXN</div>
          </div>
        </div>
      `;
      return card;
    }

    function renderResults(){
      // limpiar
      resultsList.innerHTML = '';
      subtotalCard.style.display = 'none';
      subtotalCostoEl.textContent = '—';

      const haGlobal = parseFloat(area.value);
      const opts = getSelectedOptions();
      if (opts.length === 0) return;

      let subtotal = 0;
      opts.forEach(opt => {
        const dosis = parseFloat(opt.dataset.dosis || '0') || 0;
        const precio = parseFloat(opt.dataset.precio || '0') || 0;
        const unidad = opt.dataset.unidad || '';
        const id = opt.value;
        const nombre = opt.textContent.split('(')[0].trim();
        const cantidad = (isFinite(haGlobal) && haGlobal > 0) ? (dosis * haGlobal) : NaN;
        const costo = isFinite(cantidad) ? (cantidad * precio) : NaN;
        if (isFinite(costo)) subtotal += costo;
        const card = buildCard({ id, nombre, dosis, unidad, cantidad, costo });
        resultsList.appendChild(card);
      });

      if (subtotal > 0){
        subtotalCostoEl.textContent = fmt(subtotal);
        subtotalCard.style.display = '';
      }
    }

    function handleSelectChange(){
      // Permitir múltiples selects; el render considerará todos
      renderResults();
      styleSelects();
    }

    ['change','input'].forEach(ev => {
      insumoSelects().forEach(el => el.addEventListener(ev, handleSelectChange));
      area.addEventListener(ev, renderResults);
    });
    document.addEventListener('DOMContentLoaded', () => { renderResults(); styleSelects(); });

    sel('#btn-limpiar-form')?.addEventListener('click', () => {
      area.value = '';
      insumoSelects().forEach(s => s.selectedIndex = 0);
      renderResults();
      styleSelects();
    });
  </script>
</body>
</html>
