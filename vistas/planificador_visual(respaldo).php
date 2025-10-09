<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Planificador Visual de Cosecha</title>
<link rel="stylesheet" href="../recursos/css/general.css">
<link rel="icon" type="image/png" href="logo.png">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Page card styles already in general.css */
.page-card{animation:scaleIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);}
@keyframes scaleIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}

/* Btn-back matching agua.php style */
.btn-back{
    background:rgba(124, 179, 66, 0.15);
    border:1px solid var(--border-hover);
    color:var(--accent);
    padding:10px 20px;
    border-radius:var(--radius);
    font-weight:600;
    text-decoration:none;
    transition:var(--transition-fast);
}
.btn-back:hover{
    background:var(--accent);
    color:white;
    transform:translateX(-4px);
}

/* Ensure no unwanted white shadow/overlay (fix for bad sheen) */
.btn-back{box-shadow:none;text-shadow:none;position:relative;z-index:3}
.btn-back::before,.btn-back::after{display:none !important;content:none !important}

/* Hero optimized for page-card structure */
.hero-dynamic{
    display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:30px;
    padding:24px;background:var(--bg-secondary);border-radius:var(--radius);
    position:relative;overflow:hidden;
}
.hero-dynamic .intro{margin:0;max-width:500px;line-height:1.45;color:var(--text-secondary);}
.badge-hero{
    display:inline-flex;align-items:center;gap:6px;background:var(--accent);color:white;
    padding:8px 14px;border-radius:20px;font-size:.75rem;font-weight:600;
    letter-spacing:.8px;text-transform:uppercase;
}

/* Progress ring */
.ring-wrap{display:flex;align-items:center;gap:24px;justify-content:center;}
.progress-ring{width:120px;height:120px;position:relative;}
.progress-ring svg{transform:rotate(-90deg);}
.progress-ring .pr-bg{stroke:var(--border);stroke-width:10;fill:none;}
.progress-ring .pr-val{stroke:var(--accent);stroke-width:10;fill:none;stroke-linecap:round;transition:stroke-dashoffset 1.2s cubic-bezier(.2,.8,.25,1);}
.progress-ring .center-label{
    position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    font-size:.85rem;font-weight:700;color:var(--text-primary);letter-spacing:.5px;
}

/* Form controls using general.css patterns */
.controls label{
    display:flex;flex-direction:column;font-size:.85rem;font-weight:600;
    color:var(--text-primary);padding:8px 0;
}
.controls input, .controls select{
    margin-top:4px;padding:10px 12px;
    border:1px solid var(--border);border-radius:var(--radius);
    background:var(--bg-secondary);color:var(--text-primary);
    transition:var(--transition-fast);
}
.controls input:focus, .controls select:focus{
    outline:none;border-color:var(--accent);box-shadow:0 0 0 2px var(--green-glow);
}

/* Form row layout */
.form-row{
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:20px;
    margin-bottom:16px;
}
@media (max-width:768px){
    .form-row{grid-template-columns:1fr;gap:12px;}
}

/* Buttons with dark theme integration */
.btn, button{
    margin-top:18px;padding:10px 22px;
    background:var(--accent);color:white;
    border:none;border-radius:var(--radius);cursor:pointer;
    font-weight:600;letter-spacing:.4px;text-decoration:none;
    display:inline-block;transition:var(--transition-fast);
    position:relative;overflow:hidden;
}
.btn:hover, button:hover{
    background:var(--accent-hover);
    transform:translateY(-1px);
    box-shadow:0 4px 12px var(--green-glow);
}
.btn-secondary{
    background:var(--bg-primary);
    border:1px solid var(--border);
    color:var(--text-primary);
}
.btn-secondary:hover{
    background:var(--bg-secondary);
    border-color:var(--accent);
    color:var(--accent);
}

/* Metrics grid */
.metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin:16px 0;}
.metric-card{
    background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);
    padding:14px 16px;transition:var(--transition);
    transform:translateY(14px) scale(.96);opacity:0;
}
.metric-card.active{transform:translateY(0) scale(1);opacity:1;}
.metric-card h3{margin:0 0 6px;font-size:.82rem;color:var(--accent);font-weight:700;text-transform:uppercase;}
.metric-card .value{font-size:1.15rem;font-weight:700;color:var(--text-primary);}
.metric-card small{display:block;margin-top:2px;font-size:.65rem;color:var(--text-muted);}

/* Alert styles using CSS variables */
.alerta{background:rgba(255,152,0,0.1);padding:10px 12px;border-left:4px solid #ff9800;margin:6px 0;border-radius:4px;font-size:13px;color:var(--text-primary);}
.ok{background:rgba(76,175,80,0.1);padding:10px 12px;border-left:4px solid #4caf50;margin:6px 0;border-radius:4px;font-size:13px;color:var(--text-primary);}
.info{background:rgba(33,150,243,0.1);padding:10px 12px;border-left:4px solid #2196f3;margin:6px 0;border-radius:4px;font-size:13px;color:var(--text-primary);}

/* Chart boxes */
.chart-wrapper{display:flex;flex-wrap:wrap;gap:24px;}
.chart-box{
    flex:1 1 380px;min-width:320px;background:var(--bg-secondary);
    padding:18px;border-radius:var(--radius);border:1px solid var(--border);
}
.chart-box h4{margin:0 0 12px;font-size:15px;font-weight:600;color:var(--text-primary);}

/* Progress bars */
.progress-wrap{background:var(--bg-secondary);border-radius:8px;overflow:hidden;height:30px;position:relative;}
.progress-bar{height:100%;background:var(--accent);width:0%;transition:width 1s;} 
.progress-label{
    position:absolute;top:0;left:50%;transform:translateX(-50%);
    font-size:13px;font-weight:600;color:var(--text-primary);line-height:30px;
}

/* Timeline */
.timeline{display:flex;align-items:center;gap:10px;margin:10px 0 4px;flex-wrap:wrap;}
.t-step{
    background:var(--bg-card);border:1px solid var(--border);
    padding:10px 12px;border-radius:10px;min-width:160px;
}
.t-step h5{margin:0 0 6px;font-size:13px;font-weight:700;color:var(--text-primary);}
.t-step p{margin:0;font-size:12.5px;color:var(--text-secondary);}
.connector{flex:1;height:4px;background:var(--border);border-radius:2px;min-width:40px;}

/* Badges */
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;}
.b-riesgo-alto{background:#d32f2f;color:#fff;}
.b-riesgo-medio{background:#f9a825;color:#212121;}
.b-riesgo-bajo{background:#2e7d32;color:#fff;}

.help-toggle{cursor:pointer;font-size:12.5px;color:var(--accent);text-decoration:underline;margin-top:6px;}
#ayudaConceptos{
    display:none;background:var(--bg-secondary);border:1px dashed var(--border);
    padding:12px 14px;border-radius:10px;margin-top:10px;font-size:12.5px;color:var(--text-secondary);
}

/* Additional dark theme improvements */
.metric-card.window{background:var(--bg-card);border-color:var(--accent);}
.metric-card.shelf{background:var(--bg-card);border-color:var(--green-3);}
.metric-card.cold-ok{background:var(--bg-card);border-color:var(--green-2);}
.metric-card.cold-mid{background:var(--bg-card);border-color:#ffe082;}
.metric-card.cold-low{background:var(--bg-card);border-color:#ff8a80;}

/* Table styles for dark theme */
table{background:var(--bg-secondary);color:var(--text-primary);border-radius:var(--radius);overflow:hidden;}
th, td{padding:8px 12px;border-bottom:1px solid var(--border);}
th{background:var(--bg-primary);color:var(--accent);font-weight:600;}

/* Small text elements */
small{color:var(--text-muted);}

/* Elementos clickeables */
.clickeable {
    cursor: pointer;
    transition: var(--transition-fast);
    position: relative;
}

.clickeable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px var(--green-glow);
}

.clickeable::after {
    content: "👁️ Click para detalles";
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-primary);
    color: var(--text-primary);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    border: 1px solid var(--border);
    z-index: 1000;
}

.clickeable:hover::after {
    opacity: 1;
}

/* Modal de detalles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 10000;
}

.modal-content {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    animation: modalAppear 0.3s ease;
}

@keyframes modalAppear {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    background: var(--bg-secondary);
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--accent);
    font-size: 1.2rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-muted);
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: var(--transition-fast);
}

.modal-close:hover {
    background: var(--border);
    color: var(--text-primary);
}

.modal-body {
    padding: 20px;
}

.detail-item {
    margin-bottom: 16px;
    padding: 12px;
    background: var(--bg-secondary);
    border-radius: var(--radius);
    border-left: 4px solid var(--accent);
}

.detail-item h4 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 14px;
}

.detail-item p {
    margin: 0;
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.detail-item ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
    font-size: 13px;
    color: var(--text-secondary);
}

.detail-item li {
    margin-bottom: 4px;
    line-height: 1.3;
}

.detail-item strong {
    color: var(--text-primary);
    font-weight: 600;
}
.explicacion{font-size:12.5px;color:var(--text-secondary);margin-top:10px;}

/* Special boxes */
.ventana-box{background:var(--bg-card);border:1px solid var(--accent);}
.anaquel-box{background:var(--bg-card);border:1px solid var(--green-3);}

/* Special buttons in content */
#toggleDetallado{
    background:var(--accent) !important;
    color:white !important;
    border:none;
    transition:var(--transition-fast);
}
#toggleDetallado:hover{
    background:var(--accent-hover) !important;
    transform:translateY(-1px);
    box-shadow:0 4px 12px var(--green-glow);
}

/* Grid layout for metric summaries */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:16px;
    margin:16px 0;
}
.dato{
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:12px;
    text-align:center;
}
.dato strong{
    display:block;
    font-size:1.2rem;
    color:var(--accent);
    margin-bottom:4px;
}
.dato span{
    font-size:0.85rem;
    color:var(--text-secondary);
}

@media (max-width:680px){
    .chart-box{min-width:100%;}
    .timeline{flex-direction:column;}
    .connector{display:none;}
}
</style>
</head>
<body>
<main class="container" style="padding:40px 0">
    
    <div class="page-card" style="margin-top:18px;">
        <div class="map-header" style="display:flex;align-items:center;gap:16px;padding:16px 20px;background:rgba(30,41,54,0.6);border-bottom:1px solid var(--border);">
            <a href="index.php" class="btn-back">← Volver</a>
            <h5 style="margin:0;color:var(--accent);font-size:1.4rem;font-weight:700;flex:1;text-align:center;letter-spacing:-0.5px;">Recomendación Inteligente (SAGRO-IA)</h5>
            <div style="width:100px"></div>
        </div>
        <section class="card">
            <?php if(!$dbOk): ?>
                <div class="alerta">No fue posible consultar la BD optilife para recomendaciones. <?= htmlspecialchars((string)$dbErr) ?></div>
            <?php else: ?>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px;">
                    Contexto: Clima <?= htmlspecialchars((string)($ctx['clima'] ?? '—')) ?> | Temp. Máxima <?= htmlspecialchars((string)($ctx['temp_max'] ?? '—')) ?>°C | Suelo <?= htmlspecialchars((string)($ctx['suelo'] ?? '—')) ?> | Estado <?= htmlspecialchars((string)($ctx['estado'] ?? '—')) ?> | Temporada <?= htmlspecialchars((string)($ctx['temporada'] ?? '—')) ?>.
                </div>
                <?php if(empty($recosTop)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">No se generaron recomendaciones con el contexto actual.</p>
                <?php else: ?>
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                    Se muestran <?= count($recosTop) ?> coincidencias únicas con score &gt; 0 (máx. 10).
                </p>
                <div class="metrics-grid">
                    <?php foreach($recosTop as $r): $p = $r['producto']; ?>
                        <div class="metric-card" style="transform:none;opacity:1;">
                            <h3><?= htmlspecialchars($p['nombre']) ?></h3>
                            <div class="value">Score: <?= htmlspecialchars((string)$r['score']) ?></div>
                            <small>Clima: <?= htmlspecialchars($p['clima']) ?> | Rango: <?= htmlspecialchars((string)$p['t_min']) ?>–<?= htmlspecialchars((string)$p['t_max']) ?>°C | Suelo: <?= htmlspecialchars($p['suelo']) ?> | Época: <?= htmlspecialchars($p['epoca_siembra']) ?></small>
                            <?php if(!empty($r['reasons'])): ?>
                                <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                                    <?php foreach($r['reasons'] as $reason): ?>
                                        <li><?= htmlspecialchars($reason) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
</head>
<body><canvas width="978" height="738" style="position: fixed; top: 0px; left: 0px; width: 100%; height: 100%; pointer-events: none; z-index: 0; opacity: 0.15;"></canvas>
<main class="container" style="padding:40px 0">
    <!-- Recomendación Inteligente basada en contexto -->
    <div class="page-card" style="margin-top:18px;">
        <div class="map-header" style="display:flex;align-items:center;gap:16px;padding:16px 20px;background:rgba(30,41,54,0.6);border-bottom:1px solid var(--border);">
            <a href="index.php" class="btn-back">← Volver</a>
            <h5 style="margin:0;color:var(--accent);font-size:1.4rem;font-weight:700;flex:1;text-align:center;letter-spacing:-0.5px;">Recomendación Inteligente (SAGRO-IA)</h5>
            <div style="width:100px"></div>
        </div>
        <section class="card in-view">
            <div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px;">
                Contexto: Clima 26 °C | Temp. Máxima 26°C | Suelo — | Estado Sonora | Temporada Otoño.
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                Se muestran 10 coincidencias únicas con score &gt; 0 (máx. 10).
            </p>
            <div class="metrics-grid">
                <div class="metric-card" style="transform:none;opacity:1;" data-product="papa">
                    <h3>Papa</h3>
                    <div class="value">Score: 8</div>
                    <small>Clima: Templado | Rango: 10–20°C | Suelo: Franco-arenoso | Época: Otoño</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Coincidencia exacta de época de siembra</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="uva">
                    <h3>Uva</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Árido-cálido | Rango: 10–35°C | Suelo: Xerosol | Época: Invierno</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="maiz">
                    <h3>Maíz grano</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Cálido | Rango: 25–30°C | Suelo: Yermosol | Época: Primavera-Verano</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="naranja">
                    <h3>Naranja</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Subtropical | Rango: 15–30°C | Suelo: Franco | Época: Primavera</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="nuez">
                    <h3>Nuez pecanera</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Templado-seco | Rango: 1–38°C | Suelo: Franco-arenoso | Época: Invierno</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="melon">
                    <h3>Melón</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Cálido | Rango: 18–28°C | Suelo: Arenoso | Época: Primavera</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="alfalfa">
                    <h3>Alfalfa verde</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Templado | Rango: 15–30°C | Suelo: Franco | Época: Primavera</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="sandia">
                    <h3>Sandía</h3>
                    <div class="value">Score: 6</div>
                    <small>Clima: Cálido | Rango: 20–28°C | Suelo: Arenoso | Época: Primavera</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                        <li>Temperatura dentro del rango ideal</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="esparrago">
                    <h3>Espárrago</h3>
                    <div class="value">Score: 5</div>
                    <small>Clima: Templado | Rango: 15–25°C | Suelo: Arenoso | Época: Primavera</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                    </ul>
                </div>
                <div class="metric-card" style="transform:none;opacity:1;" data-product="trigo">
                    <h3>Trigo grano</h3>
                    <div class="value">Score: 5</div>
                    <small>Clima: Seco-templado | Rango: 10–22°C | Suelo: Yermosol | Época: Invierno</small>
                    <ul style="margin:8px 0 0;padding-left:18px;color:var(--text-secondary);">
                        <li>Coincidencia exacta de estado</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
    
<!-- Modal de detalles -->
<div class="modal-overlay" id="modalDetalles">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Detalles del Cultivo</h3>
            <button class="modal-close" onclick="cerrarModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Contenido se carga dinámicamente -->
        </div>
    </div>
</div>

</main>

<script>
// Datos de cultivos para modales
const cultivosData = {
    papa: {
        nombre: "Papa",
        descripcion: "Tubérculo rico en carbohidratos, ideal para clima templado.",
        requerimientos: {
            temperatura: "10-20°C",
            suelo: "Franco-arenoso, bien drenado",
            ph: "5.5-6.5",
            precipitacion: "400-600mm anuales"
        },
        siembra: {
            epoca: "Otoño-Invierno",
            profundidad: "10-15 cm",
            distancia: "30-40 cm entre plantas"
        },
        cosecha: {
            tiempo: "90-120 días",
            indicadores: "Amarillamiento del follaje",
            rendimiento: "20-30 ton/ha"
        },
        cuidados: [
            "Riego regular sin encharcamiento",
            "Control de plagas como gusano de alambre",
            "Fertilización con fósforo y potasio",
            "Aporque para evitar verdeo de tubérculos"
        ]
    },
    uva: {
        nombre: "Uva",
        descripcion: "Fruto de vid, excelente para clima árido-cálido.",
        requerimientos: {
            temperatura: "10-35°C",
            suelo: "Xerosol, bien drenado",
            ph: "6.0-7.0",
            precipitacion: "300-800mm anuales"
        },
        siembra: {
            epoca: "Invierno (plantones)",
            profundidad: "Nivel del suelo",
            distancia: "2-3 m entre plantas"
        },
        cosecha: {
            tiempo: "2-3 años para primera cosecha",
            indicadores: "Color y dulzor del fruto",
            rendimiento: "15-25 ton/ha"
        },
        cuidados: [
            "Poda de formación y producción",
            "Sistema de conducción con espalderas",
            "Control de enfermedades fúngicas",
            "Riego por goteo recomendado"
        ]
    },
    maiz: {
        nombre: "Maíz grano",
        descripcion: "Cereal básico, adaptado a clima cálido.",
        requerimientos: {
            temperatura: "25-30°C",
            suelo: "Yermosol, fértil",
            ph: "6.0-7.5",
            precipitacion: "500-800mm anuales"
        },
        siembra: {
            epoca: "Primavera-Verano",
            profundidad: "3-5 cm",
            distancia: "20-25 cm entre plantas"
        },
        cosecha: {
            tiempo: "90-120 días",
            indicadores: "Grano seco y duro",
            rendimiento: "8-12 ton/ha"
        },
        cuidados: [
            "Fertilización con nitrógeno",
            "Control de gusano cogollero",
            "Riego en etapas críticas",
            "Deshierbe oportuno"
        ]
    },
    naranja: {
        nombre: "Naranja",
        descripcion: "Cítrico rico en vitamina C, para clima subtropical.",
        requerimientos: {
            temperatura: "15-30°C",
            suelo: "Franco, bien drenado",
            ph: "6.0-7.5",
            precipitacion: "800-1200mm anuales"
        },
        siembra: {
            epoca: "Primavera (injertos)",
            profundidad: "Nivel del cepellón",
            distancia: "5-6 m entre árboles"
        },
        cosecha: {
            tiempo: "3-5 años para producción",
            indicadores: "Color y firmeza del fruto",
            rendimiento: "20-40 ton/ha"
        },
        cuidados: [
            "Poda de limpieza anual",
            "Fertilización cítrica específica",
            "Control de mosca de la fruta",
            "Riego constante pero sin encharcamiento"
        ]
    },
    nuez: {
        nombre: "Nuez pecanera",
        descripcion: "Fruto seco de alto valor, para clima templado-seco.",
        requerimientos: {
            temperatura: "1-38°C",
            suelo: "Franco-arenoso, profundo",
            ph: "6.5-7.5",
            precipitacion: "600-1000mm anuales"
        },
        siembra: {
            epoca: "Invierno (injertos)",
            profundidad: "Nivel del injerto",
            distancia: "10-12 m entre árboles"
        },
        cosecha: {
            tiempo: "5-8 años para producción",
            indicadores: "Apertura del ruezno",
            rendimiento: "2-4 ton/ha"
        },
        cuidados: [
            "Poda de formación importante",
            "Riego abundante en verano",
            "Control de áfidos",
            "Fertilización rica en zinc"
        ]
    },
    melon: {
        nombre: "Melón",
        descripcion: "Fruta dulce y refrescante, ideal para clima cálido.",
        requerimientos: {
            temperatura: "18-28°C",
            suelo: "Arenoso, bien drenado",
            ph: "6.0-7.0",
            precipitacion: "300-500mm en ciclo"
        },
        siembra: {
            epoca: "Primavera",
            profundidad: "2-3 cm",
            distancia: "1-2 m entre plantas"
        },
        cosecha: {
            tiempo: "80-100 días",
            indicadores: "Aroma y desprendimiento fácil",
            rendimiento: "25-35 ton/ha"
        },
        cuidados: [
            "Riego por goteo preferible",
            "Acolchado plástico",
            "Control de trips y ácaros",
            "Tutorado en invernadero"
        ]
    },
    alfalfa: {
        nombre: "Alfalfa verde",
        descripcion: "Leguminosa forrajera, fijadora de nitrógeno.",
        requerimientos: {
            temperatura: "15-30°C",
            suelo: "Franco, bien drenado",
            ph: "7.0-8.0",
            precipitacion: "600-800mm anuales"
        },
        siembra: {
            epoca: "Primavera u otoño",
            profundidad: "1-2 cm",
            distancia: "Siembra al voleo"
        },
        cosecha: {
            tiempo: "60-70 días primer corte",
            indicadores: "10% de floración",
            rendimiento: "80-120 ton/ha verde"
        },
        cuidados: [
            "Inoculación con rizobios",
            "Cortes cada 30-40 días",
            "Control de malezas",
            "Riego después de cada corte"
        ]
    },
    sandia: {
        nombre: "Sandía",
        descripcion: "Fruta refrescante de gran tamaño, para clima cálido.",
        requerimientos: {
            temperatura: "20-28°C",
            suelo: "Arenoso, rico en materia orgánica",
            ph: "6.0-7.0",
            precipitacion: "400-600mm en ciclo"
        },
        siembra: {
            epoca: "Primavera",
            profundidad: "2-3 cm",
            distancia: "2-3 m entre plantas"
        },
        cosecha: {
            tiempo: "90-120 días",
            indicadores: "Sonido hueco al golpear",
            rendimiento: "30-50 ton/ha"
        },
        cuidados: [
            "Acolchado plástico recomendado",
            "Riego abundante hasta llenado",
            "Control de mosca blanca",
            "Soporte para frutos grandes"
        ]
    },
    esparrago: {
        nombre: "Espárrago",
        descripcion: "Hortaliza perenne de alto valor, para clima templado.",
        requerimientos: {
            temperatura: "15-25°C",
            suelo: "Arenoso, muy bien drenado",
            ph: "6.5-7.5",
            precipitacion: "400-600mm anuales"
        },
        siembra: {
            epoca: "Primavera (coronas)",
            profundidad: "20-25 cm",
            distancia: "30-40 cm entre plantas"
        },
        cosecha: {
            tiempo: "2-3 años para primera cosecha",
            indicadores: "Turiones de 15-20 cm",
            rendimiento: "8-12 ton/ha"
        },
        cuidados: [
            "Aporque anual con materia orgánica",
            "Corte diario en temporada",
            "Riego por goteo",
            "Fertilización rica en fósforo"
        ]
    },
    trigo: {
        nombre: "Trigo grano",
        descripcion: "Cereal básico mundial, adaptado a clima seco-templado.",
        requerimientos: {
            temperatura: "10-22°C",
            suelo: "Yermosol, fértil",
            ph: "6.0-7.5",
            precipitacion: "300-600mm anuales"
        },
        siembra: {
            epoca: "Invierno",
            profundidad: "3-5 cm",
            distancia: "Siembra en hileras"
        },
        cosecha: {
            tiempo: "120-150 días",
            indicadores: "Grano duro y dorado",
            rendimiento: "4-8 ton/ha"
        },
        cuidados: [
            "Fertilización nitrogenada fraccionada",
            "Control de roya y septoria",
            "Siembra en fecha oportuna",
            "Cosecha en punto óptimo"
        ]
    }
};

// Función para mostrar modal
function mostrarModal(producto) {
    const data = cultivosData[producto];
    if (!data) return;
    
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = `Detalles de ${data.nombre}`;
    
    modalBody.innerHTML = `
        <div class="detail-item">
            <h4>📋 Descripción</h4>
            <p>${data.descripcion}</p>
        </div>
        
        <div class="detail-item">
            <h4>🌡️ Requerimientos Climáticos y de Suelo</h4>
            <p><strong>Temperatura:</strong> ${data.requerimientos.temperatura}</p>
            <p><strong>Tipo de suelo:</strong> ${data.requerimientos.suelo}</p>
            <p><strong>pH óptimo:</strong> ${data.requerimientos.ph}</p>
            <p><strong>Precipitación:</strong> ${data.requerimientos.precipitacion}</p>
        </div>
        
        <div class="detail-item">
            <h4>🌱 Siembra</h4>
            <p><strong>Época de siembra:</strong> ${data.siembra.epoca}</p>
            <p><strong>Profundidad:</strong> ${data.siembra.profundidad}</p>
            <p><strong>Distancia:</strong> ${data.siembra.distancia}</p>
        </div>
        
        <div class="detail-item">
            <h4>🌾 Cosecha</h4>
            <p><strong>Tiempo a cosecha:</strong> ${data.cosecha.tiempo}</p>
            <p><strong>Indicadores:</strong> ${data.cosecha.indicadores}</p>
            <p><strong>Rendimiento esperado:</strong> ${data.cosecha.rendimiento}</p>
        </div>
        
        <div class="detail-item">
            <h4>🔧 Cuidados Especiales</h4>
            <ul>
                ${data.cuidados.map(cuidado => `<li>${cuidado}</li>`).join('')}
            </ul>
        </div>
    `;
    
    document.getElementById('modalDetalles').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Función para cerrar modal
function cerrarModal() {
    document.getElementById('modalDetalles').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Agregar click listeners a las tarjetas
    document.querySelectorAll('.metric-card').forEach(card => {
        card.addEventListener('click', function() {
            const producto = this.getAttribute('data-product');
            if (producto) {
                mostrarModal(producto);
            }
        });
    });
    
    // Cerrar modal al hacer click fuera
    document.getElementById('modalDetalles').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModal();
        }
    });
    
    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModal();
        }
    });
});
</script>

<script src="../recursos/js/animations.js" defer></script>
</body>
</html>