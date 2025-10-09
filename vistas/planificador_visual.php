<?php
// vistas/planificador_visual.php
require_once __DIR__ . '/../includes/planificador_manager.php';
require_once __DIR__ . '/../includes/catalogo_manzanos.php';
require_once __DIR__ . '/../includes/clima_persistencia.php';

// Coordenadas por defecto (puedes cambiar a las del huerto/usuario)
$default_lat = 28.4069;
$default_lon = -106.8666;

catalogo_inicializar();
clima_inicializar_tablas();

$mensaje = '';
$resultado = null;
$variedades = planificador_listar_variedades();
 $horas_frio_actual = null;
// Obtener la última horas_frio para contexto; si no existe intentar calcular período reciente (últimos 30 días)
$db = conectar_bd();
$q = $db->query('SELECT horas_frio, fecha_corte FROM horas_frio_acumuladas ORDER BY fecha_corte DESC LIMIT 1');
if ($q && $row = $q->fetch_assoc()) { $horas_frio_actual = (int)$row['horas_frio']; }
$db->close();
if ($horas_frio_actual === null) {
    // Fallback rápido: ingerir últimas 720 horas (~30 días) usando la ingesta extendida y coordenadas por defecto
    $lat = $default_lat; $lon = $default_lon;
    $hoy = date('Y-m-d');
    $inicio = date('Y-m-d', strtotime('-30 days'));
    // Ingesta extendida (incluye forecast) y cálculo
    $insertados = clima_ingestar_open_meteo_extendido($lat, $lon, 30, 16, 'America/Mexico_City');
    $calculadas = clima_calcular_acumulado($lat, $lon, $inicio.' 00:00:00', $hoy.' 23:59:59');
    // Guardar acumulado provisional con fecha corte hoy y temporada año actual
    $temporada = date('Y');
    clima_guardar_acumulado($lat, $lon, $temporada, $calculadas, $hoy);
    $horas_frio_actual = $calculadas;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cultivo = $_POST['cultivo'] ?? 'manzana';
    $variedad_id = isset($_POST['variedad_id']) ? (int)$_POST['variedad_id'] : 0;
    $fecha_floracion = trim($_POST['fecha_floracion'] ?? '');
    if ($cultivo !== 'manzana') {
        $mensaje = 'Solo se encuentra habilitado el cultivo manzana.';
    } elseif ($variedad_id > 0 && $fecha_floracion) {
        $v = planificador_obtener_variedad($variedad_id);
        if ($v) {
            if (($_POST['accion'] ?? '') === 'actualizar_clima') {
                // Recalcular horas frío usando ingesta extendida y coordenadas por defecto
                $lat = $default_lat; $lon = $default_lon;
                $inicio = date('Y-m-d', strtotime('-30 days'));
                $fin = date('Y-m-d');
                clima_ingestar_open_meteo_extendido($lat, $lon, 30, 16, 'America/Mexico_City');
                $horas_frio_actual = clima_calcular_acumulado($lat, $lon, $inicio.' 00:00:00', $fin.' 23:59:59');
                clima_guardar_acumulado($lat, $lon, date('Y'), $horas_frio_actual, $fin);
                $mensaje = 'Clima actualizado (extendido) y horas frío recalculadas.';
            }
            $resultado = planificador_calcular($v, $fecha_floracion, $horas_frio_actual);
            // Calcular chill portions sobre datos reales usando coordenadas actuales
            try {
                $fechaFlorDt = new DateTime($fecha_floracion);
                $anioFlor = (int)$fechaFlorDt->format('Y');
                $temporadaDesde = ($anioFlor - 1) . '-07-01';
                $chill_real = clima_calcular_chill_portions_heuristica($default_lat, $default_lon, $temporadaDesde, $fechaFlorDt->format('Y-m-d'));
            } catch (Throwable $e) {
                $chill_real = null;
            }
        } else {
            $mensaje = 'Variedad no encontrada.';
        }
    } else {
        $mensaje = 'Completa todos los campos.';
    }
}

// --- Recomendación Inteligente (SAGRO-IA) basada en contexto del índice y BD optilife ---
// Lee contexto desde querystring (proveniente de index) y calcula recomendaciones
// Asunciones: BD optilife accessible en localhost (XAMPP) con usuario root sin contraseña

// Helpers para normalizar strings y coincidencias parciales
function norm($s){
    $s = mb_strtolower((string)$s, 'UTF-8');
    $s = preg_replace('/[áÁ]/u','a',$s);
    $s = preg_replace('/[éÉ]/u','e',$s);
    $s = preg_replace('/[íÍ]/u','i',$s);
    $s = preg_replace('/[óÓ]/u','o',$s);
    $s = preg_replace('/[úÚ]/u','u',$s);
    $s = preg_replace('/[ñÑ]/u','n',$s);
    return trim($s);
}
function contains_norm($hay,$needle){
    $hay = norm($hay); $needle = norm($needle);
    if($needle==='') return false; return (strpos($hay,$needle) !== false);
}
function temporada_actual(){
    $m = (int)date('n');
    if($m===12 || $m<=2) return 'Invierno';
    if($m>=3 && $m<=5) return 'Primavera';
    if($m>=6 && $m<=8) return 'Verano';
    return 'Otoño';
}

// Obtener contexto
$ctx = [
    'clima' => $_GET['clima'] ?? null,
    'lat' => isset($_GET['lat']) ? (float)$_GET['lat'] : null,
    'lon' => isset($_GET['lon']) ? (float)$_GET['lon'] : null,
    'temp_max' => isset($_GET['tmax']) ? (float)$_GET['tmax'] : null,
    'suelo' => $_GET['suelo'] ?? null,
    'estado' => $_GET['estado'] ?? null,
    'temporada' => $_GET['temporada'] ?? null,
];
if(!$ctx['temporada']) $ctx['temporada'] = temporada_actual();

// Conexión BD optilife y obtención de productos
$recos = [];
$dbOk = false; $dbErr = null;
try{
    $cn = @new mysqli('127.0.0.1','root','', 'optilife');
    if($cn && !$cn->connect_error){
        $dbOk = true;
        $sql = "SELECT id,nombre,clima,t_min,t_max,epoca_siembra,dias_germinacion,dias_caducidad,recomendaciones,suelo,estados FROM productos";
        if($rs = $cn->query($sql)){
            while($row = $rs->fetch_assoc()){
                    // Nuevo scoring ponderado: Estado (5), Época (3), Suelo (1), Temperatura (1). Máximo 10 puntos.
                    $score = 0; $reasons = [];

                    // Estado (comparación exacta por token)
                    if($ctx['estado'] && !empty($row['estados'])){
                        $estTokens = array_filter(array_map('trim', preg_split('/[,;|]/', $row['estados'])));
                        $estMatch = false; $ctxEstadoNorm = norm($ctx['estado']);
                        foreach($estTokens as $token){
                            if(norm($token) === $ctxEstadoNorm){ $estMatch = true; break; }
                        }
                        if($estMatch){ $score += 5; $reasons[] = 'Coincidencia exacta de estado'; }
                    }

                    // Época de siembra (coincidencia exacta por token)
                    if($ctx['temporada'] && !empty($row['epoca_siembra'])){
                        $epiTokens = array_filter(array_map('trim', preg_split('/[,;|]/', $row['epoca_siembra'])));
                        $tempMatch = false; $ctxTempNorm = norm($ctx['temporada']);
                        foreach($epiTokens as $token){
                            if(norm($token) === $ctxTempNorm){ $tempMatch = true; break; }
                        }
                        if($tempMatch){ $score += 3; $reasons[] = 'Coincidencia exacta de época de siembra'; }
                    }

                    // Suelo (coincidencia exacta por token)
                    if($ctx['suelo'] && !empty($row['suelo'])){
                        $soilTokens = array_filter(array_map('trim', preg_split('/[,;|]/', $row['suelo'])));
                        $soilMatch = false; $ctxSoilNorm = norm($ctx['suelo']);
                        foreach($soilTokens as $token){
                            if(norm($token) === $ctxSoilNorm){ $soilMatch = true; break; }
                        }
                        if($soilMatch){ $score += 1; $reasons[] = 'Coincidencia exacta de tipo de suelo'; }
                    }

                    // Temperatura (rango ideal)
                    $tmin = is_numeric($row['t_min']) ? (float)$row['t_min'] : null;
                    $tmax = is_numeric($row['t_max']) ? (float)$row['t_max'] : null;
                    if($ctx['temp_max']!==null && $tmin!==null && $tmax!==null && $ctx['temp_max'] >= $tmin && $ctx['temp_max'] <= $tmax){
                        $score += 1; $reasons[] = 'Temperatura dentro del rango ideal'; }

                    // Guardar (máximo 10 puntos)
                    $score = min($score, 10);
                    $recos[] = [
                        'score' => $score,
                        'producto' => $row,
                        'reasons' => $reasons
                    ];
            }
            // Deduplicar por nombre (quedarse con mayor score)
            $dedup = [];
            foreach($recos as $item){
                $nameKey = norm($item['producto']['nombre']);
                if(!isset($dedup[$nameKey]) || $item['score'] > $dedup[$nameKey]['score']){
                    $dedup[$nameKey] = $item;
                }
            }
            // Filtrar recomendaciones con score > 0
            $recos = array_values(array_filter($dedup, function($item){ return $item['score'] > 0; }));

            $rs->free();
        }
        $cn->close();
    } else {
        $dbErr = $cn ? $cn->connect_error : 'No se pudo conectar a MySQL';
    }
}catch(Throwable $e){ $dbErr = $e->getMessage(); }

// Ordenar por score desc y tomar top N
usort($recos, function($a,$b){ return $b['score'] <=> $a['score']; });
$recosTop = array_slice($recos, 0, 10);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Planificador Visual de Cosecha</title>
<link rel="stylesheet" href="../recursos/css/general.css" />
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
    <!-- Recomendación Inteligente basada en contexto -->
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
        </section>
    </div>
    <?php if($resultado): ?>
    <?php $rAgri = $resultado['resumen_agricultor'] ?? null; ?>
    <?php $chill_real_display = $chill_real ?? null; ?>
    <div class="page-card" id="panelEsencial">
        <div class="page-header">
            <h2>Vista Esencial para el Agricultor</h2>
        </div>
        <section class="card">
            <?php if($rAgri): ?>
                <div class="metrics-grid" id="metricsGrid">
            <div class="metric-card" id="mcVariedad">
                <h3>VARIEDAD</h3>
                <div class="value"><?= htmlspecialchars($rAgri['variedad']) ?></div>
                <small>Selección actual</small>
            </div>
            <div class="metric-card" id="mcFloracion">
                <h3>FLORACIÓN</h3>
                <div class="value"><?= htmlspecialchars($rAgri['floracion']) ?></div>
                <small>Fecha base ciclo</small>
            </div>
            <div class="metric-card window" id="mcVentana">
                <h3>VENTANA COSECHA</h3>
                <div class="value" style="font-size:.95rem;"><?= htmlspecialchars($rAgri['ventana']['inicio']) ?> → <?= htmlspecialchars($rAgri['ventana']['fin']) ?></div>
                <small>Inicio ↔ Fin recomendados</small>
            </div>
            <div class="metric-card shelf" id="mcCosecha">
                <h3>COSECHA ESTIMADA</h3>
                <div class="value"><?= htmlspecialchars($rAgri['cosecha_estimada']) ?></div>
                <small>Fecha objetivo</small>
            </div>
            
            <?php $estado = $rAgri['estado_frio']; $estadoClase='cold-ok'; if($estado==='bajo'){ $estadoClase='cold-low'; } elseif($estado==='medio'){ $estadoClase='cold-mid'; } elseif($estado==='estable'){ $estadoClase='cold-ok'; } else { $estadoClase=''; } ?>
            
        </div>
        <div style="margin-top:14px;font-size:13px;color:var(--text-secondary);">
            <strong>Recomendación principal:</strong> <?= htmlspecialchars($rAgri['recomendacion']) ?><br/>
            <?php if(!empty($rAgri['alerta_clave'])): ?>
                <strong>Alerta destacada:</strong> <?= htmlspecialchars($rAgri['alerta_clave']) ?>
            <?php endif; ?>
        </div>
        <button type="button" id="toggleDetallado" style="margin-top:16px;background:#1976d2;">Ver detalles avanzados</button>
            <p style="font-size:12px;color:var(--text-muted);margin-top:6px;">Esta vista muestra sólo lo que necesitas para decidir logística y monitoreo diario.</p>
            <?php else: ?>
                <p>No se construyó el resumen esencial.</p>
            <?php endif; ?>
        </section>
    </div>
    <div id="bloqueCompleto" style="display:none;">
    <div class="page-card">
        <div class="page-header">
            <h2>Resumen</h2>
        </div>
        <section class="card">
            <div class="grid">
        <div class="dato"><strong><?= htmlspecialchars($resultado['nombre_variedad']) ?></strong><span>Variedad</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['fecha_floracion']) ?></strong><span>Fecha Floración</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['dias_flor_cosecha']) ?></strong><span>Días Flor-Cosecha</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['fecha_cosecha_estimada']) ?></strong><span>Fecha Estimada</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['ventana_inicio']) ?></strong><span>Ventana Inicio</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['ventana_fin']) ?></strong><span>Ventana Fin</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['vida_anaquel_dias']) ?></strong><span>Vida Anaquel (días)</span></div>
        <div class="dato"><strong><?= htmlspecialchars($resultado['fecha_limite_anaquel']) ?></strong><span>Límite Anaquel</span></div>
        <div class="dato"><strong><?= htmlspecialchars((string)$resultado['ajuste_aplicado_dias']) ?></strong><span>Ajuste por frío</span></div>
        <div class="dato"><strong><?= htmlspecialchars((string)$resultado['horas_frio_acumuladas']) ?></strong><span>Horas Frío Acum.</span></div>
        <div class="dato"><strong><?= htmlspecialchars((string)$resultado['horas_frio_requeridas']) ?></strong><span>Horas Frío Req.</span></div>
    <div class="dato"><strong><?= htmlspecialchars((string)$resultado['porcentaje_frio']) ?>%</strong><span>Avance frío</span></div>
    </div>
    <?php
        // Interpretación amigable
        $porc = (float)$resultado['porcentaje_frio'];
        $ajuste = (int)$resultado['ajuste_aplicado_dias'];
        if ($porc < 80) { $estadoFrio = 'b-riesgo-alto'; $textoFrio = 'El frío acumulado es bajo (' . $porc . '%). Se aplicó un ajuste de +' . $ajuste . ' días para evitar cosecha prematura.'; }
        elseif ($porc < 90) { $estadoFrio = 'b-riesgo-medio'; $textoFrio = 'El frío va cerca del objetivo (' . $porc . '%). Ajuste ligero de +' . $ajuste . ' días.'; }
        else { $estadoFrio = 'b-riesgo-bajo'; $textoFrio = 'Objetivo de frío prácticamente cumplido (' . $porc . '%). Fecha estimada estable.'; }
    ?>
    <div class="info"><span class="badge <?= $estadoFrio ?>">Frío <?= htmlspecialchars((string)$resultado['porcentaje_frio']) ?>%</span> <?= htmlspecialchars($textoFrio) ?></div>
    <p style="margin-top:10px;font-size:13px;color:var(--text-secondary);">Las horas frío se contabilizan (0°C–7°C). Si no había datos previos se estimó un bloque reciente (≈30 días). Un menor porcentaje retrasa la fecha objetivo para no comprometer calidad.</p>
    <h3>Alertas</h3>
    <?php if(count($resultado['alertas'])===0): ?>
        <div class="ok">Sin alertas relevantes.</div>
    <?php else: foreach($resultado['alertas'] as $a): ?>
        <div class="alerta"><?= htmlspecialchars($a) ?></div>
    <?php endforeach; endif; ?>
        </section>
    </div>
    <div class="page-card">
        <div class="page-header">
            <h2>Desglose Detallado</h2>
        </div>
        <section class="card">
            <p style="font-size:13px;color:var(--text-secondary);">Cada métrica incluye una explicación corta y otra amplia para reforzar comprensión incluso sin experiencia técnica o agrícola.</p>
            <p style="font-size:12px;color:var(--text-secondary);background:var(--bg-secondary);border:1px solid var(--border);padding:10px 12px;border-radius:10px;line-height:1.35;">
                <strong>Metodología Prob. Éxito (versión heurística 0.2):</strong> Partimos del % de horas frío cumplidas comparado con lo requerido, aplicamos penalización por días de ajuste (déficit térmico) y añadimos bonificación incremental si el cumplimiento es alto (≥95% sin ajuste). Ahora se incorpora un factor adicional de <em>chill portions</em> estimadas (heurística): rangos mayores de porciones aportan bonificación (ej. ≥40 → +4 pts). Futuras versiones integrarán: riesgo de heladas severas, anomalías de radiación, estrés hídrico y validación fenológica visual. <strong>Nota:</strong> Esta probabilidad no garantiza rendimiento, sino la consistencia temporal y de calidad prevista bajo supuestos estándar de manejo.
            </p>
    <?php if(isset($resultado['detalles']) && is_array($resultado['detalles'])): ?>
        <?php foreach($resultado['detalles'] as $clave => $info): ?>
            <div style="margin-bottom:18px;padding:14px 16px;border:1px solid var(--border);border-radius:10px;background:var(--bg-secondary);">
                <h3 style="margin:0 0 8px;font-size:15px;letter-spacing:.4px;color:var(--text-primary);">🔍 <?= htmlspecialchars($info['titulo']) ?></h3>
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">
                    <div style="min-width:180px;font-size:13px;line-height:1.3;color:var(--text-primary);">
                        <strong>Valor:</strong> <span style="color:var(--accent);"><?= htmlspecialchars(is_scalar($info['valor']) ? (string)$info['valor'] : (is_array($info['valor'])? json_encode($info['valor']) : '')) ?></span>
                    </div>
                    <div style="flex:1;min-width:240px;font-size:12.5px;color:var(--text-secondary);">
                        <strong>Explicación corta:</strong><br/>
                        <?= htmlspecialchars($info['explicacion_corta']) ?><br/>
                        <strong style="display:block;margin-top:6px;">Explicación extensa:</strong>
                        <span style="display:block;margin-top:2px;"><?= htmlspecialchars($info['explicacion_extensa']) ?></span>
                        <span style="display:block;margin-top:6px;color:var(--text-muted);font-style:italic;">Resumen: <?= htmlspecialchars($info['explicacion_corta']) ?>.</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No hay detalles disponibles.</p>
    <?php endif; ?>
    <?php if(isset($resultado['debug_calc'])): $dbg = $resultado['debug_calc']; ?>
        <div style="margin-top:12px;padding:12px;border:1px dashed var(--border);border-radius:8px;background:var(--bg-secondary);">
            <h4 style="color:var(--text-primary);">Depuración cálculo probabilidad</h4>
            <pre style="font-size:13px;color:var(--text-secondary);white-space:pre-wrap;"><?= htmlspecialchars(json_encode($dbg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endif; ?>
    <p style="font-size:12px;color:var(--text-muted);margin-top:10px;">Fin del desglose. Puedes volver arriba para cambiar variedad o fecha y recalcular el conjunto completo de métricas y sus explicaciones repetitivas.</p>
        </section>
    </div>
    <div class="page-card">
        <div class="page-header">
            <h2>Visualizaciones simplificadas</h2>
        </div>
        <section class="card">
            <div class="chart-wrapper">
                <div class="chart-box" aria-label="Progreso de horas frío" role="group">
                    <h4>Avance de Horas Frío</h4>
                    <div class="progress-wrap" title="Porcentaje de horas frío acumuladas sobre lo requerido">
                        <div class="progress-bar" id="barFrio"></div>
                <div class="progress-label" id="labelFrio">0%</div>
            </div>
            <div class="explicacion">Necesarias: <strong><?= htmlspecialchars((string)$resultado['horas_frio_requeridas']) ?></strong> | Acumuladas: <strong><?= htmlspecialchars((string)$resultado['horas_frio_acumuladas']) ?></strong>. El avance ideal es ≥90% para una fecha de cosecha estable.</div>
        </div>
        <div class="chart-box" aria-label="Línea temporal" role="group">
            <h4>Fechas Clave</h4>
            <div class="timeline" id="timeline">
                <div class="t-step">
                    <h5>Floración</h5>
                    <p><?= htmlspecialchars($resultado['fecha_floracion']) ?></p>
                </div>
                <div class="connector"></div>
                <div class="t-step ventana-box">
                    <h5>Ventana Cosecha</h5>
                    <p><?= htmlspecialchars($resultado['ventana_inicio']) ?> → <?= htmlspecialchars($resultado['ventana_fin']) ?></p>
                </div>
                <div class="connector"></div>
                <div class="t-step anaquel-box">
                    <h5>Límite Anaquel</h5>
                    <p><?= htmlspecialchars($resultado['fecha_limite_anaquel']) ?></p>
                </div>
            </div>
            <div class="explicacion">La cosecha se recomienda dentro de la ventana para equilibrar desarrollo y firmeza. Después del límite de anaquel la calidad comercial disminuye rápidamente.</div>
        </div>
        <div class="chart-box" aria-label="Vida de anaquel" role="group">
            <h4>Vida de Anaquel Estimada</h4>
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="flex:1;">
                    <div class="progress-wrap" style="height:26px;" title="Días de vida de anaquel estimados">
                        <div class="progress-bar" id="barAnaquel" style="background:linear-gradient(90deg,#ff9800,#ffb74d);"></div>
                        <div class="progress-label" id="labelAnaquel">0 días</div>
                    </div>
                </div>
                <div style="min-width:80px;text-align:center;font-size:28px;font-weight:700;color:var(--accent);" id="valorAnaquel">0</div>
            </div>
            <div class="explicacion">Valor aproximado de conservación en condiciones estándar. Almacenes más fríos y manejo delicado pueden extender algunos días.</div>
        </div>
    </div>
        </section>
    </div>
    <div class="page-card" id="panelHelada" style="display:none;">
        <div class="page-header">
            <h2>Riesgo de Helada (SAGRO-IA)</h2>
        </div>
        <section class="card">
            <div id="heladaResumen"></div>
            <div id="heladaEventos"></div>
        </section>
    </div>
    </div><!-- cierre bloqueCompleto -->
    <div class="page-card">
        <div class="page-header">
            <h2>Predicción climática (próximos días)</h2>
        </div>
        <section class="card">
            <div id="prediccionBox">
                <p style="font-size:13px;color:var(--text-secondary);">Se muestran Tmin/Tmax diarias y una estimación simple de horas frío esperadas (0–7°C) basada en la predicción almacenada.</p>
                <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e0e0e0;"><th>Día</th><th>Tmin</th><th>Tmax</th><th>Horas frío prev.</th><th>Horas ≤1°C (48h)</th></tr>
                    </thead>
                    <tbody id="predRows"></tbody>
                </table>
            </div>
        </section>
    </div>
<script>
(function(){
    // Datos PHP -> JS
    const porcentajeFrio = <?= json_encode($resultado['porcentaje_frio'] ?? 0) ?>; // usado en barra de horas frío
    const horasReq = <?= json_encode($resultado['horas_frio_requeridas'] ?? 0) ?>;
    const horasAcum = <?= json_encode($resultado['horas_frio_acumuladas'] ?? 0) ?>;
    const vidaAnaquel = <?= json_encode($resultado['vida_anaquel_dias']) ?>;
    const fechaFlor = <?= json_encode($resultado['fecha_floracion']) ?>;
    const fechaEstimada = <?= json_encode($resultado['fecha_cosecha_estimada']) ?>;
    const variedadId = <?= json_encode($resultado['variedad_id']) ?>;

    // Barra progreso frío básica (mantener panel simplificado)
    const barFrio = document.getElementById('barFrio');
    const labelFrio = document.getElementById('labelFrio');
    if(barFrio && labelFrio){
        const porcVal = Math.min(100, Math.max(0, porcentajeFrio));
        barFrio.style.width = porcVal + '%';
        labelFrio.textContent = porcVal.toFixed(1) + '%';
        if (porcVal < 80) { barFrio.style.background = 'linear-gradient(90deg,#c62828,#ef5350)'; }
        else if (porcVal < 90) { barFrio.style.background = 'linear-gradient(90deg,#f9a825,#fff176)'; }
    }
    // Indicador de prob_exito removido: se elimina lógica de ring
    // Animar tarjetas métricas
    const metricCards = document.querySelectorAll('.metric-card');
    let delay=0; metricCards.forEach(mc=>{ setTimeout(()=> mc.classList.add('active'), delay); delay+=140; });
    
    // Control del select de cultivo
    const cultivoSelect = document.getElementById('cultivo');
    if(cultivoSelect){
        cultivoSelect.addEventListener('change', function(e){
            if(e.target.value !== 'manzana'){
                // Mostrar mensaje temporal
                const alertaTemp = document.createElement('div');
                alertaTemp.className = 'alerta';
                alertaTemp.textContent = 'Próximamente disponible. Por ahora solo manzana está habilitada.';
                alertaTemp.style.opacity = '0';
                alertaTemp.style.transition = 'opacity 0.3s ease';
                
                // Insertar después del formulario
                const form = e.target.closest('form');
                form.parentNode.insertBefore(alertaTemp, form.nextSibling);
                
                // Animar entrada
                setTimeout(() => alertaTemp.style.opacity = '1', 10);
                
                // Revertir selección a manzana
                e.target.value = 'manzana';
                
                // Remover mensaje después de 3 segundos
                setTimeout(() => {
                    alertaTemp.style.opacity = '0';
                    setTimeout(() => alertaTemp.remove(), 300);
                }, 3000);
            }
        });
    }
    // Toggle avanzado y animar timeline
    const btnToggle = document.getElementById('toggleDetallado');
    const bloque = document.getElementById('bloqueCompleto');
    if(btnToggle && bloque){
        btnToggle.addEventListener('click',()=>{
            const visible = bloque.style.display==='block';
            bloque.style.display = visible? 'none':'block';
            btnToggle.textContent = visible? 'Ver detalles avanzados':'Ocultar detalles avanzados';
            if(!visible){
                document.querySelectorAll('.t-step').forEach((st,i)=>{setTimeout(()=>st.classList.add('active'), i*160);});
            }
        });
    }

    // Barra vida anaquel proporcional max 60 días para escala visual (asunción simple)
    const barAnaquel = document.getElementById('barAnaquel');
    const labelAnaquel = document.getElementById('labelAnaquel');
    const valorAnaquel = document.getElementById('valorAnaquel');
    const maxRef = 60; // referencia escalar
    const porcentajeAnaquel = Math.min(100, (vidaAnaquel / maxRef) * 100);
    barAnaquel.style.width = porcentajeAnaquel + '%';
    labelAnaquel.textContent = vidaAnaquel + ' días';
    valorAnaquel.textContent = vidaAnaquel;

    // Fetch riesgo helada SAGRO-IA
    const panelHelada = document.getElementById('panelHelada');
    const resumenDiv = document.getElementById('heladaResumen');
    const eventosDiv = document.getElementById('heladaEventos');
    const url = `../sagro_ia.php?variedad=${variedadId}&inicio=${fechaFlor}&fin=${fechaEstimada}&etapa=floracion&huerto=Planificador`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            panelHelada.style.display = 'block';
            resumenDiv.innerHTML = `<p><strong>Riesgo:</strong> ${data.riesgo} | <strong>Temp. crítica:</strong> ${data.temperatura_critica_aprox.toFixed(1)}°C</p><p>${data.analisis}</p><p><em>${data.recomendacion}</em></p>`;
            if (data.eventos_riesgo && data.eventos_riesgo.length) {
                const lista = data.eventos_riesgo.map(ev => `<li>${ev.hora}: ${ev.temp}°C</li>`).join('');
                eventosDiv.innerHTML = `<h4>Horas cercanas a umbral</h4><ul style='margin:0;padding-left:18px;'>${lista}</ul>`;
            } else {
                eventosDiv.innerHTML = '<p>No se detectan horas críticas en el periodo.</p>';
            }
        })
        .catch(err => {
            panelHelada.style.display = 'block';
            resumenDiv.innerHTML = '<p>Error consultando SAGRO-IA.</p>';
            console.error(err);
        });

    // Predicción climática real: consumir endpoint prediccion_clima.php (agregados diarios)
    (function cargarPrediccion(){
        // Podemos ajustar parámetros (dias futuro/pasado) según necesidad del planificador.
        // Aquí: mostrar 5 días futuros sin pasado.
        fetch('../prediccion_clima.php?dias=5&pasado=0')
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
            .then(data => {
                if(!data || !Array.isArray(data.dias)) {
                    console.warn('Respuesta inesperada de prediccion_clima.php', data);
                    return;
                }
                const tbody = document.getElementById('predRows');
                if(!tbody) { console.error('Tabla de predicción no encontrada (#predRows)'); return; }
                if(!data.dias.length){
                    tbody.innerHTML = '<tr><td colspan="5">Sin datos climáticos disponibles para el rango solicitado.</td></tr>';
                    return;
                }
                // Construir filas reales: dia, Tmin, Tmax, Horas frío, Horas ≤1°C
                const filasHtml = data.dias.map(d => {
                    const tmin = d.tmin !== null && d.tmin !== undefined ? d.tmin+'°C' : '—';
                    const tmax = d.tmax !== null && d.tmax !== undefined ? d.tmax+'°C' : '—';
                    
                    return `<tr><td>${d.dia}</td><td>${tmin}</td><td>${tmax}</td><td>`;
                }).join('');
                tbody.innerHTML = filasHtml;
            })
            .catch(err => { console.error('Predicción climática falló', err); const tbody = document.getElementById('predRows'); if(tbody){tbody.innerHTML = '<tr><td colspan="5">Error cargando predicción.</td></tr>';}});
    })();
})();
</script>
<?php endif; ?>
</main>
<script src="../recursos/js/animations.js" defer></script>
</body>
</html>