<?php
// Incluir archivo de conexión
require_once '../includes/conexion.php';

// Recibir parámetros de ubicación del index.php
$clima = $_GET['clima'] ?? '26';
$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';
$tmax = $_GET['tmax'] ?? '26';
$suelo = $_GET['suelo'] ?? '';
$estado = $_GET['estado'] ?? 'Sonora';
$temporada = $_GET['temporada'] ?? 'Otoño';

// Extraer temperatura numérica del texto de clima
$temperatura = 26;
if (preg_match('/(\d+)/', $clima, $matches)) {
    $temperatura = intval($matches[1]);
} elseif (preg_match('/(\d+)/', $tmax, $matches)) {
    $temperatura = intval($matches[1]);
}

// Registrar suelo en GLOBALS para que esté disponible en la función
$GLOBALS['suelo'] = $suelo;

// Función para calcular score de compatibilidad
function calcularScore($cultivo, $temperatura, $estado, $temporada) {
    $score = 0;
    $reasons = [];

    // Score por estado (máximo 4 puntos)
    $estadosCultivo = explode(',', $cultivo['estados']);
    $estadosCultivo = array_map('trim', $estadosCultivo);
    if (in_array($estado, $estadosCultivo)) {
        $score += 4;
        $reasons[] = ['text' => 'Popular en tu estado', 'type' => 'positive'];
    } else {
        $reasons[] = ['text' => 'No popular en este estado', 'type' => 'negative'];
    }

    // Score por época de siembra (máximo 3 puntos)
    $epocas = explode('-', $cultivo['epoca_siembra']);
    $epocasPrincipales = array_map('trim', $epocas);
    if (in_array($temporada, $epocasPrincipales)) {
        $score += 3;
        $reasons[] = ['text' => 'Época perfecta para sembrar', 'type' => 'positive'];
    } else {
        $reasons[] = ['text' => 'Época no ideal para sembrar', 'type' => 'negative'];
    }

    // Score por tipo de suelo (máximo 2 puntos)
    if (!empty($cultivo['suelo']) && !empty($GLOBALS['suelo'])) {
        $sueloActual = strtolower(trim($GLOBALS['suelo']));
        $sueloCultivoArr = preg_split('/[-,]/', strtolower($cultivo['suelo']));
        $sueloCultivoArr = array_map('trim', $sueloCultivoArr);
        $coincideSuelo = false;
        foreach ($sueloCultivoArr as $sueloC) {
            // Coincidencia si uno contiene al otro
            if (strpos($sueloActual, $sueloC) !== false || strpos($sueloC, $sueloActual) !== false) {
                $coincideSuelo = true;
                break;
            }
        }
        if ($coincideSuelo) {
            $score += 2;
            $reasons[] = ['text' => 'Suelo ideal', 'type' => 'positive'];
        } else {
            $reasons[] = ['text' => 'Suelo no ideal', 'type' => 'negative'];
        }
    } else {
        // Si no hay info de suelo, marcar negativo
        $reasons[] = ['text' => 'Suelo no disponible', 'type' => 'negative'];
    }

    // Score por rango de clima (máximo 1 punto)
    if ($temperatura >= $cultivo['t_min'] && $temperatura <= $cultivo['t_max']) {
        $score += 1;
        $reasons[] = ['text' => 'Temperatura actual dentro del rango ideal', 'type' => 'positive'];
    } else {
        $reasons[] = ['text' => 'Temperatura actual fuera del rango ideal', 'type' => 'negative'];
    }

    return ['score' => $score, 'reasons' => $reasons];
}

try {
    // Conectar a la base de datos
    $mysqli = conectar_bd();
    
    // Obtener cultivos de la base de datos
    $query = "SELECT id, nombre, clima, t_min, t_max, epoca_siembra, dias_germinacion, dias_caducidad, suelo, estados, recomendaciones 
              FROM productos 
              ORDER BY nombre";
    
    $resultado = $mysqli->query($query);
    
    if (!$resultado) {
        throw new Exception("Error en la consulta: " . $mysqli->error);
    }
    
    // Generar recomendaciones basadas en ubicación
    $recomendaciones = [];
    
    while ($cultivo = $resultado->fetch_assoc()) {
        $scoreData = calcularScore($cultivo, $temperatura, $estado, $temporada);
        
        if ($scoreData['score'] > 0) {
            $recomendaciones[] = [
                'cultivo' => $cultivo,
                'score' => $scoreData['score'],
                'reasons' => $scoreData['reasons']
            ];
        }
    }
    
    // Ordenar por score descendente
    usort($recomendaciones, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Limitar a 10 mejores
    $recomendaciones = array_slice($recomendaciones, 0, 10);
    
    $mysqli->close();
    $conexionExitosa = true;
    $errorConexion = '';
    
} catch (Exception $e) {
    $conexionExitosa = false;
    $errorConexion = $e->getMessage();
    $recomendaciones = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Planificador Visual </title>
<link rel="stylesheet" href="../recursos/css/general.css">
<link rel="icon" type="image/png" href="logo.png">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Reason badges */
.reason-positive{color:var(--green-3);font-weight:700}
.reason-negative{color:#d9534f;font-weight:700}

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
    cursor:pointer;
    position:relative;
}
.metric-card:hover{
    transform:translateY(-2px) scale(1.02);
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
}
.metric-card:active{
    transform:translateY(0) scale(1);
}
.metric-card::after{
    content:'👆 Click para detalles';
    position:absolute;
    top:8px;
    right:12px;
    font-size:0.65rem;
    color:var(--text-muted);
    opacity:0;
    transition:opacity 0.2s ease;
}
.metric-card:hover::after{
    opacity:1;
}
.metric-card.active{transform:translateY(0) scale(1);opacity:1;}
.metric-card h3{margin:0 0 6px;font-size:.82rem;color:var(--accent);font-weight:700;text-transform:uppercase;}
.metric-card .value{font-size:1.15rem;font-weight:700;color:var(--text-primary);}
.metric-card small{display:block;margin-top:2px;font-size:.65rem;color:var(--text-muted);}

/* Alert styles using CSS variables */
.alerta{background:rgba(255,152,0,0.1);padding:10px 12px;border-left:4px solid #ff9800;margin:6px 0;border-radius:4px;font-size:13px;color:var(--text-primary);}

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

/* Estilos para la línea de tiempo del ciclo de cultivo */
.cycle-timeline {
    margin-top: 16px;
}

.timeline-container {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    gap: 0;
}

.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 0 0 auto;
}

.step-marker {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    border: 3px solid;
    background: var(--bg-card);
    position: relative;
    z-index: 2;
}

.germination-step {
    border-color: #4CAF50;
    background: linear-gradient(135deg, #4CAF50, #8BC34A);
}

.harvest-step {
    border-color: #FF9800;
    background: linear-gradient(135deg, #FF9800, #FFC107);
}

.preservation-step {
    border-color: #2196F3;
    background: linear-gradient(135deg, #2196F3, #03A9F4);
}

.step-icon {
    font-size: 20px;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3));
}

.step-content {
    text-align: center;
    max-width: 100px;
}

.step-content h6 {
    margin: 0 0 4px 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
}

.step-content p {
    margin: 0;
    font-size: 11px;
    color: var(--text-secondary);
    line-height: 1.3;
}

.timeline-connector {
    flex: 1;
    height: 3px;
    background: linear-gradient(90deg, var(--accent), var(--green-3));
    border-radius: 2px;
    margin: 0 15px;
    margin-bottom: 30px;
    position: relative;
    z-index: 1;
}

@media (max-width: 600px) {
    .timeline-container {
        flex-direction: column;
        gap: 20px;
    }
    
    .timeline-connector {
        width: 3px;
        height: 30px;
        margin: 0;
        background: linear-gradient(180deg, var(--accent), var(--green-3));
    }
    
    .step-content {
        max-width: 150px;
    }
}
.explicacion{font-size:12.5px;color:var(--text-secondary);margin-top:10px;}

/* Special boxes */
.ventana-box{background:var(--bg-card);border:1px solid var(--accent);}
/*.anaquel-box{background:var(--bg-card);border:1px solid var(--green-3);}*/

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

</head>
<body><canvas width="978" height="738" style="position: fixed; top: 0px; left: 0px; width: 100%; height: 100%; pointer-events: none; z-index: 0; opacity: 0.15;"></canvas>
<main class="container" style="padding:40px 0">
  
    <div class="page-card" style="margin-top:18px;">
        <section class="card in-view" style="padding:0;overflow:hidden;">
            <div class="map-header" style="display:flex;align-items:center;gap:16px;padding:16px 20px;background:rgba(30,41,54,0.6);">
                <a href="index.php" class="btn-back">← Volver</a>
                <h5 style="margin:0;color:var(--accent);font-size:1.4rem;font-weight:700;flex:1;text-align:center;letter-spacing:-0.5px;">Recomendación Inteligente</h5>
                <div style="width:100px"></div>
            </div>
            <div style="padding:18px;">
            <?php if (!$conexionExitosa): ?>
                <div class="alerta" style="background:rgba(220,38,38,0.1);color:#dc2626;padding:12px;border-radius:8px;margin-bottom:16px;">
                    <strong>Error de conexión:</strong> <?= htmlspecialchars($errorConexion) ?>
                </div>
            <?php else: ?>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px;">
                    Contexto: Clima <?= htmlspecialchars($temperatura) ?>°C | Temp. Máxima <?= htmlspecialchars($temperatura) ?>°C | Suelo <?= htmlspecialchars($suelo ?: '—') ?> | Estado <?= htmlspecialchars($estado) ?> | Temporada <?= htmlspecialchars($temporada) ?>.
                </div>
                
                <!-- (Bloque de explicación del score eliminado) -->
                
                <?php if (empty($recomendaciones)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">No se encontraron cultivos compatibles con las condiciones actuales.</p>
                <?php else: ?>
                    <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                        Se muestran <?= count($recomendaciones) ?> coincidencias únicas con score &gt; 0 (máx. 10).
                    </p>
                    <div class="metrics-grid">
                        <?php foreach ($recomendaciones as $index => $rec): 
                            $cultivo = $rec['cultivo'];
                            $score = $rec['score'];
                            $reasons = $rec['reasons'];
                            $key = strtolower(str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'], ['_', 'a', 'e', 'i', 'o', 'u', 'n'], $cultivo['nombre']));
                        ?>
                            <div class="metric-card" style="transform:none;opacity:1;" data-product="<?= htmlspecialchars($key) ?>">
                                <h3><?= htmlspecialchars($cultivo['nombre']) ?></h3>
                                <?php $percent = (int) round(($score / 10) * 100); ?>
                                <div class="value">Compatibilidad con tu zona: <?= htmlspecialchars($percent) ?>%</div>
                                <small>
                                    Clima: <?= htmlspecialchars($cultivo['clima']) ?> | 
                                    Rango: <?= htmlspecialchars($cultivo['t_min']) ?>–<?= htmlspecialchars($cultivo['t_max']) ?>°C | 
                                    Suelo: <?= htmlspecialchars($cultivo['suelo']) ?> | 
                                    Época: <?= htmlspecialchars($cultivo['epoca_siembra']) ?>
                                </small>
                                <?php if (!empty($reasons)): ?>
                                    <ul style="margin:8px 0 0;padding-left:18px;">
                                        <?php foreach ($reasons as $reason): 
                                            $text = htmlspecialchars(is_array($reason) ? $reason['text'] : $reason);
                                            $type = is_array($reason) && isset($reason['type']) ? $reason['type'] : 'positive';
                                            $class = $type === 'positive' ? 'reason-positive' : 'reason-negative';
                                        ?>
                                            <li class="<?= $class ?>"><?= $text ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
// Datos de cultivos para modales - generados dinámicamente desde BD
const cultivosData = {
    <?php if ($conexionExitosa && !empty($recomendaciones)): ?>
        <?php foreach ($recomendaciones as $index => $rec): 
            $cultivo = $rec['cultivo'];
            $key = strtolower(str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'], ['_', 'a', 'e', 'i', 'o', 'u', 'n'], $cultivo['nombre']));
            
            // Generar descripción básica si no existe
            $descripcion = $cultivo['recomendaciones'] ?? 'Cultivo recomendado para las condiciones de tu región.';
        ?>
    "<?= $key ?>": {
        nombre: <?= json_encode($cultivo['nombre']) ?>,
        descripcion: <?= json_encode($descripcion) ?>,
        requerimientos: {
            temperatura: <?= json_encode($cultivo['t_min'] . '-' . $cultivo['t_max'] . '°C') ?>,
            suelo: <?= json_encode($cultivo['suelo']) ?>,
            clima: <?= json_encode($cultivo['clima']) ?>,
            estados: <?= json_encode($cultivo['estados']) ?>
        },
        siembra: {
            epoca: <?= json_encode($cultivo['epoca_siembra']) ?>,
            informacion: "Consulta las recomendaciones específicas para tu región"
        },
        ciclo: {
            diasGerminacion: <?= json_encode($cultivo['dias_germinacion']) ?>,
            diasCaducidad: <?= json_encode($cultivo['dias_caducidad']) ?>
        },
        cuidados: [
            <?= json_encode($cultivo['recomendaciones'] ?? 'Seguir las prácticas agrícolas recomendadas para la región') ?>
        ]
    }<?= $index < count($recomendaciones) - 1 ? ',' : '' ?>
        <?php endforeach; ?>
    <?php else: ?>
    // No hay cultivos disponibles
    <?php endif; ?>
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
            <h4>📋 Recomendaciones</h4>
            <p>${data.descripcion}</p>
        </div>
        
        <div class="detail-item">
            <h4>🌡️ Requerimientos Climáticos y de Suelo</h4>
            <p><strong>Temperatura:</strong> ${data.requerimientos.temperatura}</p>
            <p><strong>Tipo de suelo:</strong> ${data.requerimientos.suelo}</p>
            <p><strong>Clima:</strong> ${data.requerimientos.clima}</p>
            <p><strong>Estados recomendados:</strong> ${data.requerimientos.estados}</p>
        </div>
        
        <div class="detail-item">
            <h4>🌱 Siembra</h4>
            <p><strong>Época de siembra:</strong> ${data.siembra.epoca}</p>
            <p><strong>Información adicional:</strong> ${data.siembra.informacion}</p>
        </div>
        
        <div class="detail-item">
            <h4>⏱️ Ciclo de Cultivo</h4>
            
            <div class="cycle-timeline">
                <div class="timeline-container">
                    <div class="timeline-step">
                        <div class="step-marker germination-step">
                            <span class="step-icon">🌱</span>
                        </div>
                        <div class="step-content">
                            <h6>Germinación</h6>
                            <p>0 - ${data.ciclo.diasGerminacion} días</p>
                        </div>
                    </div>
                    
                    <div class="timeline-connector"></div>
                    
                    <div class="timeline-step">
                        <div class="step-marker harvest-step">
                            <span class="step-icon">🌾</span>
                        </div>
                        <div class="step-content">
                            <h6>Cosecha</h6>
                            <p>Día ${data.ciclo.diasGerminacion}</p>
                        </div>
                    </div>
                    
                    <div class="timeline-connector"></div>
                    
                    <div class="timeline-step">
                        <div class="step-marker preservation-step">
                            <span class="step-icon">⏰</span>
                        </div>
                        <div class="step-content">
                            <h6>Caducidad</h6>
                            <p>${data.ciclo.diasCaducidad} días</p>
                        </div>
                    </div>
                </div>
            </div>
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