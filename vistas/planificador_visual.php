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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Planificador Visual de Cosecha</title>
<link rel="stylesheet" href="../recursos/css/general.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* Unificación con paleta oscura del index */
body{margin:0;color:var(--text-primary);} /* fondo y fuente ya definidos en general.css */
.container{max-width:1180px;margin:0 auto;padding:24px 28px;}
.hero-dynamic{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:30px;padding:42px 50px 38px;background:linear-gradient(135deg,var(--bg-card) 0%, var(--bg-secondary) 100%);border:1px solid var(--border);border-radius:var(--radius-xl);box-shadow:var(--shadow);position:relative;overflow:hidden;}
.hero-dynamic:before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 70% 28%,rgba(124,179,66,0.18),transparent 65%);pointer-events:none;}
.hero-dynamic h1{margin:0 0 14px;font-size:clamp(1.8rem,3.6vw,2.8rem);letter-spacing:-1px;background:linear-gradient(135deg,var(--accent) 0%, var(--green-2) 85%);background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:800;line-height:1.15;}
.hero-dynamic .intro{margin:0;max-width:640px;line-height:1.55;font-size:clamp(.95rem,1.08vw,1.05rem);color:var(--text-secondary);font-weight:500;}
.badge-hero{display:inline-flex;align-items:center;gap:6px;background:var(--accent);color:#fff;padding:10px 18px;border-radius:40px;font-size:.7rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;box-shadow:var(--shadow-glow);}
.panel{border:1px solid var(--border);padding:22px 24px;border-radius:var(--radius-lg);margin-bottom:30px;background:var(--bg-card);box-shadow:var(--shadow);position:relative;overflow:hidden;}
.panel h2{margin-top:0;font-size:1.15rem;letter-spacing:.5px;font-weight:700;background:linear-gradient(90deg,var(--accent),var(--green-2));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
label{display:block;margin-top:14px;font-weight:600;font-size:13px;color:var(--text-secondary);letter-spacing:.4px;}
select,input[type=date]{padding:10px 12px;width:300px;max-width:100%;border:1px solid var(--border);border-radius:8px;font-size:14px;background:var(--bg-secondary);color:var(--text-primary);outline:none;transition:var(--transition-fast);}
select:focus,input[type=date]:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(102,187,106,0.15);}
/* Métricas oscuras alineadas al index */
.metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px;margin:6px 0 6px;}
.metric-card{position:relative;background:var(--bg-card-hover);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px 14px;box-shadow:var(--shadow);overflow:hidden;isolation:isolate;transform:translateY(14px) scale(.96);opacity:0;transition:.6s cubic-bezier(.2,.8,.25,1);}
.metric-card.active{transform:translateY(0) scale(1);opacity:1;box-shadow:var(--shadow-glow);}
.metric-card:before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--green-3),var(--accent));opacity:.65;}
.metric-card h3{margin:0 0 6px;font-size:.7rem;letter-spacing:.55px;background:linear-gradient(120deg,var(--accent) 0%,var(--green-2) 90%);background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:800;text-transform:uppercase;}
.metric-card .value{font-size:1.05rem;font-weight:700;color:var(--accent);letter-spacing:.5px;}
.metric-card small{display:block;margin-top:4px;font-size:.6rem;color:var(--text-muted);letter-spacing:.5px;}
.metric-card.window{background:linear-gradient(145deg,var(--bg-card-hover) 0%, rgba(255,200,124,0.07) 100%);}
.metric-card.shelf{background:linear-gradient(145deg,var(--bg-card-hover) 0%, rgba(144,202,249,0.10) 100%);}
/* Alertas adaptadas */
.alerta{background:rgba(255,152,0,0.10);padding:10px 12px;border-left:4px solid #ff9800;margin:6px 0;border-radius:6px;font-size:12px;color:var(--text-secondary);}
.ok{background:rgba(46,125,50,0.15);padding:10px 12px;border-left:4px solid var(--accent);margin:6px 0;border-radius:6px;font-size:12px;color:var(--text-secondary);}
.info{background:rgba(25,118,210,0.15);padding:10px 12px;border-left:4px solid #1976d2;margin:6px 0;border-radius:6px;font-size:12px;color:var(--text-secondary);}
/* Timeline clara → oscura */
.timeline{display:flex;align-items:center;gap:12px;margin:12px 0 6px;flex-wrap:wrap;}
.t-step{background:var(--bg-card-hover);border:1px solid var(--border);padding:12px 14px;border-radius:12px;min-width:160px;position:relative;box-shadow:0 4px 14px rgba(0,0,0,0.25);}
.t-step h5{margin:0 0 6px;font-size:.68rem;font-weight:700;letter-spacing:.5px;color:var(--accent);}
.t-step p{margin:0;font-size:.7rem;color:var(--text-secondary);}
.connector{flex:1;height:4px;background:linear-gradient(90deg,var(--green-3),var(--accent));border-radius:2px;min-width:40px;position:relative;opacity:.55;}
.ventana-box{background:linear-gradient(135deg,var(--bg-card-hover),rgba(255,200,124,0.08));}
.anaquel-box{background:linear-gradient(135deg,var(--bg-card-hover),rgba(144,202,249,0.12));}
.help-toggle{cursor:pointer;font-size:.7rem;color:var(--accent);text-decoration:underline;margin-top:6px;}
#ayudaConceptos{display:none;background:var(--bg-card-hover);border:1px dashed var(--border);padding:14px 16px;border-radius:12px;margin-top:12px;font-size:.68rem;color:var(--text-secondary);}
/* Charts adaptados */
.chart-wrapper{display:flex;flex-wrap:wrap;gap:34px;}
.chart-box{flex:1 1 380px;min-width:320px;background:var(--bg-card-hover);padding:20px;border-radius:16px;border:1px solid var(--border);position:relative;box-shadow:var(--shadow);}
.chart-box h4{margin:0 0 12px;font-size:.78rem;font-weight:700;letter-spacing:.45px;color:var(--accent);}
.explicacion{font-size:.66rem;color:var(--text-secondary);margin-top:10px;}
.progress-wrap{background:rgba(255,255,255,0.06);border-radius:10px;overflow:hidden;height:30px;position:relative;}
.progress-bar{height:100%;background:linear-gradient(90deg,var(--green-4),var(--accent));width:0%;transition:width 1s;}
.progress-label{position:absolute;top:0;left:50%;transform:translateX(-50%);font-size:.65rem;font-weight:700;color:var(--text-primary);line-height:30px;letter-spacing:.4px;}
.badge{display:inline-block;padding:5px 12px;border-radius:24px;font-size:.55rem;font-weight:700;letter-spacing:.4px;background:var(--green-4);color:#fff;}
/* Toggle avanzado botón (reutiliza estilos .btn) */
#toggleDetallado{margin-top:18px;}
#toggleDetallado.btn-primary{box-shadow:0 4px 16px rgba(124,179,66,0.35);}
#toggleDetallado.btn-primary:hover{box-shadow:var(--shadow-glow);}
/* Avanzados (ya añadidos antes) */
.detalles-avanzados-wrapper{margin-top:28px;display:flex;flex-direction:column;gap:28px;}
.detalles-avanzados-wrapper .panel{background:linear-gradient(135deg,var(--bg-card) 0%, var(--bg-secondary) 100%);border:1px solid var(--border);color:var(--text-primary);box-shadow:var(--shadow);}
.detalles-avanzados-wrapper .panel h2, .detalles-avanzados-wrapper .panel h3, .detalles-avanzados-wrapper .panel h4{background:linear-gradient(90deg,var(--accent),var(--green-2));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:.4px;}
.fade-seq{opacity:0;transform:translateY(18px);transition:.6s cubic-bezier(.2,.8,.25,1);}
.fade-seq.active{opacity:1;transform:translateY(0);}
.detalle-card{background:rgba(255,255,255,0.03);border:1px solid var(--border);padding:16px 18px;border-radius:var(--radius);position:relative;overflow:hidden;transition:var(--transition-fast);}
.detalle-card:before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(124,179,66,0.14),transparent 70%);opacity:0;transition:opacity .4s ease;}
.detalle-card:hover:before{opacity:1;}
.detalle-card:hover{border-color:var(--border-hover);transform:translateY(-4px);}
.detalle-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;}
.detalle-meta{font-size:.6rem;color:var(--text-muted);margin-top:6px;}
.detalle-valor{font-weight:600;color:var(--accent);font-size:.8rem;}
.badge-frio{display:inline-block;padding:6px 12px;border-radius:24px;font-size:.55rem;font-weight:700;letter-spacing:.6px;background:var(--green-4);color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);}
.timeline-modern{display:flex;flex-wrap:wrap;gap:14px;margin-top:10px;}
.timeline-modern .t-item{flex:1 1 160px;min-width:160px;background:var(--bg-card-hover);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;position:relative;overflow:hidden;}
.visual-charts-flex{display:flex;flex-wrap:wrap;gap:24px;margin-top:10px;}
.visual-box{flex:1 1 300px;min-width:260px;background:var(--bg-card-hover);border:1px solid var(--border);border-radius:var(--radius-lg);padding:18px;position:relative;overflow:hidden;}
.visual-box h4{margin:0 0 10px;font-size:.75rem;letter-spacing:.5px;color:var(--accent);font-weight:700;}
.visual-box:before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,var(--green-4),var(--accent));opacity:.6;}
.visual-progress{height:24px;background:rgba(255,255,255,.06);border-radius:14px;overflow:hidden;position:relative;margin-top:4px;}
.visual-progress .bar{height:100%;width:0;background:linear-gradient(90deg,var(--green-4),var(--accent));transition:width .9s cubic-bezier(.2,.8,.25,1);}
.visual-progress .label{position:absolute;top:0;left:50%;transform:translateX(-50%);font-size:.55rem;font-weight:600;color:var(--text-primary);line-height:24px;}
@media (max-width:760px){.visual-box{min-width:100%;}.detalle-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mapa - Cuerpos de agua</title>
    <link rel="stylesheet" href="../recursos/css/general.css">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <style>
        .map-container{
            background:var(--bg-card);
            border-radius:var(--radius-lg);
            overflow:hidden;
            box-shadow:var(--shadow-lg);
            border:1px solid var(--border);
            animation:scaleIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes scaleIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}
        .map-header{
            padding:20px 24px;
            background:rgba(30, 41, 54, 0.6);
            backdrop-filter:blur(10px);
            border-bottom:1px solid var(--border);
            display:flex;
            align-items:center;
            gap:16px;
        }
        .map-header h5{
            margin:0;
            color:var(--accent);
            font-size:1.4rem;
            font-weight:700;
            flex:1;
            text-align:center;
            letter-spacing:-0.5px;
        }
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
        #map{width:100%;height:75vh;background:var(--bg-secondary)}
        .leaflet-popup-content-wrapper{
            background:var(--bg-card);
            color:var(--text-primary);
            border-radius:var(--radius);
            box-shadow:var(--shadow-lg);
        }
        .leaflet-popup-tip{background:var(--bg-card)}
        .c1, .c2{
            background:var(--accent);
            border:2px solid var(--bg-card);
            box-shadow:0 0 12px var(--green-glow);
            border-radius:50%;
        }
        .c2{background:var(--green-3)}
    </style>
</head>
<main class="container">
    <section class="hero" style="padding:50px 0 30px;">
        <h2 style="margin-bottom:18px;">Planificador Visual de Cosecha</h2>
            </section>
<div class="panel">
    <form method="POST">
        <label for="cultivo">Cultivo</label>
        <select name="cultivo" id="cultivo">
            <option value="manzana" selected>Manzana</option>
            <option value="aguacate" disabled>Aguacate (próximo)</option>
            <option value="alfalfa" disabled>Alfalfa (próximo)</option>
            <option value="avena" disabled>Avena (próximo)</option>
            <option value="calabaza" disabled>Calabaza (próximo)</option>
            <option value="cana_de_azucar" disabled>Caña de Azúcar (próximo)</option>
            <option value="cebolla" disabled>Cebolla (próximo)</option>
            <option value="chile_jalapeno" disabled>Chile Jalapeño (próximo)</option>
            <option value="frijol" disabled>Frijol (próximo)</option>
            <option value="maiz" disabled>Maíz (próximo)</option>
            <option value="papa" disabled>Papa (próximo)</option>
            <option value="tomate" disabled>Tomate (próximo)</option>
            <option value="trigo" disabled>Trigo (próximo)</option>
            <option value="uva" disabled>Uva (próximo)</option>
        </select>
        <small style="display:block;color:var(--text-muted);margin-top:4px;">Solo Manzana disponible por ahora. Los demás cultivos están en desarrollo.</small>
        <label for="variedad_id">Variedad</label>
        <select name="variedad_id" id="variedad_id" required>
            <option value="">-- Selecciona variedad --</option>
            <?php foreach($variedades as $v): ?>
                <option value="<?= htmlspecialchars((string)$v['id']) ?>" <?= (isset($variedad_id) && $variedad_id == $v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['nombre_variedad']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="fecha_floracion">Fecha Floración</label>
        <input type="date" name="fecha_floracion" id="fecha_floracion" value="<?= htmlspecialchars($_POST['fecha_floracion'] ?? '') ?>" required />
    <button type="submit" name="accion" value="calcular" class="btn">Ver estimación</button>
    <button type="submit" name="accion" value="actualizar_clima" class="btn btn-secondary">Actualizar clima</button>
    </form>
    <?php if($mensaje): ?><div class="alerta"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <div class="help-toggle" onclick="document.getElementById('ayudaConceptos').style.display = document.getElementById('ayudaConceptos').style.display==='none'?'block':'none';">¿Qué significan las horas frío y la ventana de cosecha?</div>
    <div id="ayudaConceptos">
        <strong>Horas frío:</strong> Son horas con temperatura baja (0°C a 7°C) necesarias para que el árbol cumpla su ciclo y la fruta alcance calidad óptima. <br/>
        <strong>Ventana de cosecha:</strong> Periodo recomendado para cortar la fruta: demasiado pronto falta desarrollo; demasiado tarde puede perder firmeza. <br/>
        <strong>Ajuste por frío:</strong> Si el frío acumulado es menor al esperado se retrasa un poco la fecha estimada. <br/>
        <em>Consejo:</em> Usa "Actualizar clima" si pasaron varias horas o días desde la última consulta.
    </div>
</div>
<?php if($resultado): ?>
<?php $rAgri = $resultado['resumen_agricultor'] ?? null; ?>
<?php $chill_real_display = $chill_real ?? null; ?>
<div class="panel" id="panelEsencial">
    <h2>Vista Esencial para el Agricultor</h2>
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
        <div style="margin-top:14px;font-size:13px;color:#37474f;">
            <strong>Recomendación principal:</strong> <?= htmlspecialchars($rAgri['recomendacion']) ?><br/>
            <?php if(!empty($rAgri['alerta_clave'])): ?>
                <strong>Alerta destacada:</strong> <?= htmlspecialchars($rAgri['alerta_clave']) ?>
            <?php endif; ?>
        </div>
    <button type="button" id="toggleDetallado" class="btn btn-primary" style="margin-top:16px;">Ver detalles avanzados</button>
        <p style="font-size:12px;color:#54666b;margin-top:6px;">Esta vista muestra sólo lo que necesitas para decidir logística y monitoreo diario.</p>
    <?php else: ?>
        <p>No se construyó el resumen esencial.</p>
    <?php endif; ?>
</div>
<div id="bloqueCompleto" style="display:none;">
<div class="panel fade-seq">
    <h2>Resumen</h2>
    <div class="detalle-grid">
        <div class="detalle-card"><strong>Variedad</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['nombre_variedad']) ?></div><div class="detalle-meta">Identificador</div></div>
        <div class="detalle-card"><strong>Floración</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['fecha_floracion']) ?></div><div class="detalle-meta">Fecha base</div></div>
        <div class="detalle-card"><strong>Días Flor-Cosecha</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['dias_flor_cosecha']) ?></div><div class="detalle-meta">Duración ciclo</div></div>
        <div class="detalle-card"><strong>Cosecha estimada</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['fecha_cosecha_estimada']) ?></div><div class="detalle-meta">Objetivo temporal</div></div>
        <div class="detalle-card"><strong>Ventana Inicio</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['ventana_inicio']) ?></div><div class="detalle-meta">Inicio recomend.</div></div>
        <div class="detalle-card"><strong>Ventana Fin</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['ventana_fin']) ?></div><div class="detalle-meta">Fin recomend.</div></div>
        <div class="detalle-card"><strong>Vida anaquel</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['vida_anaquel_dias']) ?> días</div><div class="detalle-meta">Conservación</div></div>
        <div class="detalle-card"><strong>Límite anaquel</strong><div class="detalle-valor"><?= htmlspecialchars($resultado['fecha_limite_anaquel']) ?></div><div class="detalle-meta">Fecha límite</div></div>
        <div class="detalle-card"><strong>Ajuste frío</strong><div class="detalle-valor"><?= htmlspecialchars((string)$resultado['ajuste_aplicado_dias']) ?> días</div><div class="detalle-meta">Días añadidos</div></div>
        <div class="detalle-card"><strong>Horas frío</strong><div class="detalle-valor"><?= htmlspecialchars((string)$resultado['horas_frio_acumuladas']) ?> / <?= htmlspecialchars((string)$resultado['horas_frio_requeridas']) ?></div><div class="detalle-meta">Acum / Req</div></div>
        <div class="detalle-card"><strong>Avance frío</strong><div class="detalle-valor"><?= htmlspecialchars((string)$resultado['porcentaje_frio']) ?>%</div><div class="detalle-meta">Progreso</div></div>
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
    <p style="margin-top:10px;font-size:.7rem;color:var(--text-secondary);">Las horas frío (0°C–7°C) se estimaron sobre últimos datos disponibles. Déficit térmico retrasa fecha para proteger calidad.</p>
    <h3>Alertas</h3>
    <?php if(count($resultado['alertas'])===0): ?>
        <div class="ok">Sin alertas relevantes.</div>
    <?php else: foreach($resultado['alertas'] as $a): ?>
        <div class="alerta"><?= htmlspecialchars($a) ?></div>
    <?php endforeach; endif; ?>
</div>
<div class="panel">
    <h2>Desglose Detallado</h2>
    <p style="font-size:.7rem;color:var(--text-secondary);">Cada métrica incluye explicación corta y extensa para comprensión sin experiencia técnica.</p>
        <p style="font-size:.65rem;color:var(--text-secondary);background:var(--bg-card-hover);border:1px solid var(--border);padding:10px 12px;border-radius:10px;line-height:1.35;">
            <strong>Metodología Prob. Éxito (versión heurística 0.2):</strong> Partimos del % de horas frío cumplidas comparado con lo requerido, aplicamos penalización por días de ajuste (déficit térmico) y añadimos bonificación incremental si el cumplimiento es alto (≥95% sin ajuste). Ahora se incorpora un factor adicional de <em>chill portions</em> estimadas (heurística): rangos mayores de porciones aportan bonificación (ej. ≥40 → +4 pts). Futuras versiones integrarán: riesgo de heladas severas, anomalías de radiación, estrés hídrico y validación fenológica visual. <strong>Nota:</strong> Esta probabilidad no garantiza rendimiento, sino la consistencia temporal y de calidad prevista bajo supuestos estándar de manejo.
        </p>
    <?php if(isset($resultado['detalles']) && is_array($resultado['detalles'])): ?>
        <?php foreach($resultado['detalles'] as $clave => $info): ?>
            <div class="detalle-card" style="margin-bottom:14px;">
                <h3 style="margin:0 0 6px;font-size:.75rem;letter-spacing:.4px;background:linear-gradient(90deg,var(--accent),var(--green-2));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;">🔍 <?= htmlspecialchars($info['titulo']) ?></h3>
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">
                    <div style="min-width:160px;font-size:.65rem;line-height:1.3;">
                        <strong>Valor:</strong> <span style="color:var(--green-2);"><?= htmlspecialchars(is_scalar($info['valor']) ? (string)$info['valor'] : (is_array($info['valor'])? json_encode($info['valor']) : '')) ?></span>
                    </div>
                    <div style="flex:1;min-width:220px;font-size:.62rem;color:var(--text-secondary);">
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
        <div style="margin-top:12px;padding:12px;border:1px dashed var(--border);border-radius:8px;background:var(--bg-card-hover);">
            <h4 style="margin:0 0 6px;font-size:.7rem;color:var(--accent);">Depuración cálculo probabilidad</h4>
            <pre style="font-size:.6rem;color:var(--text-secondary);white-space:pre-wrap;"><?= htmlspecialchars(json_encode($dbg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endif; ?>
    <p style="font-size:.6rem;color:var(--text-muted);margin-top:10px;">Fin del desglose. Cambia variedad o fecha para recomputar métricas.</p>
</div>
<div class="panel">
    <h2>Visualizaciones simplificadas</h2>
    <div class="chart-wrapper">
        <!-- Nuevo panel: Desglose de Parámetros Clave -->
        <div class="panel" style="flex:1 1 100%;background:var(--bg-card-hover);border:1px solid var(--border);margin-bottom:28px;">
            <h2 style="margin-top:0;font-size:1rem;">Desglose de Parámetros Clave</h2>
            <p style="font-size:.65rem;color:var(--text-secondary);margin-top:4px;">Visual comparativa de frío, ajuste aplicado y vida de anaquel para interpretación rápida.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:16px;">
                <div style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:14px;padding:14px 16px;position:relative;overflow:hidden;">
                    <h3 style="margin:0 0 8px;font-size:.7rem;letter-spacing:.5px;font-weight:700;background:linear-gradient(90deg,var(--accent),var(--green-2));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Horas Frío</h3>
                    <div style="font-size:.6rem;color:var(--text-muted);margin-bottom:6px;">Acumuladas vs requeridas</div>
                    <div class="visual-progress" style="height:26px;">
                        <div class="bar" id="barFrioAvz"></div>
                        <div class="label" id="labelFrioAvz">0%</div>
                    </div>
                    <small style="display:block;margin-top:6px;font-size:.55rem;color:var(--text-secondary);">Valor: <strong><?= htmlspecialchars((string)$resultado['horas_frio_acumuladas']) ?></strong> / <?= htmlspecialchars((string)$resultado['horas_frio_requeridas']) ?> (<?= htmlspecialchars((string)$resultado['porcentaje_frio']) ?>%)</small>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:14px;padding:14px 16px;position:relative;overflow:hidden;">
                    <h3 style="margin:0 0 8px;font-size:.7rem;letter-spacing:.5px;font-weight:700;background:linear-gradient(90deg,var(--accent),var(--green-2));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Ajuste por Frío</h3>
                    <div style="font-size:.6rem;color:var(--text-muted);margin-bottom:6px;">Días añadidos al ciclo</div>
                    <div class="visual-progress" style="height:26px;">
                        <div class="bar" id="barAjusteAvz" style="background:linear-gradient(90deg,#ffa726,#ffcc80);"></div>
                        <div class="label" id="labelAjusteAvz">0 días</div>
                    </div>
                    <small style="display:block;margin-top:6px;font-size:.55rem;color:var(--text-secondary);">Ajuste aplicado: <strong><?= htmlspecialchars((string)$resultado['ajuste_aplicado_dias']) ?></strong> días</small>
                </div>
                <div style="background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:14px;padding:14px 16px;position:relative;overflow:hidden;">
                    <h3 style="margin:0 0 8px;font-size:.7rem;letter-spacing:.5px;font-weight:700;background:linear-gradient(90deg,var(--accent),var(--green-2));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Vida de Anaquel</h3>
                    <div style="font-size:.6rem;color:var(--text-muted);margin-bottom:6px;">Duración estimada post-cosecha</div>
                    <div class="visual-progress" style="height:26px;">
                        <div class="bar" id="barAnaquelAvz" style="background:linear-gradient(90deg,#ff9800,#ffb74d);"></div>
                        <div class="label" id="labelAnaquelAvz">0 días</div>
                    </div>
                    <small style="display:block;margin-top:6px;font-size:.55rem;color:var(--text-secondary);">Vida estimada: <strong><?= htmlspecialchars((string)$resultado['vida_anaquel_dias']) ?></strong> días • Límite: <?= htmlspecialchars($resultado['fecha_limite_anaquel']) ?></small>
                </div>
            </div>
        </div>
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
                <div style="min-width:80px;text-align:center;font-size:28px;font-weight:700;color:#ff9800;" id="valorAnaquel">0</div>
            </div>
            <div class="explicacion">Valor aproximado de conservación en condiciones estándar. Almacenes más fríos y manejo delicado pueden extender algunos días.</div>
        </div>
    </div>
</div>
<div class="panel" id="panelHelada" style="display:none;">
    <h2>Riesgo de Helada (SAGRO-IA)</h2>
    <div id="heladaResumen"></div>
    <div id="heladaEventos"></div>
</div>
</div><!-- cierre bloqueCompleto -->
<div class="panel">
    <h2>Predicción climática (próximos días)</h2>
    <div id="prediccionBox">
        <p style="font-size:13px;color:#37474f;">Se muestran Tmin/Tmax diarias y una estimación simple de horas frío esperadas (0–7°C) basada en la predicción almacenada.</p>
        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #e0e0e0;"><th>Día</th><th>Tmin</th><th>Tmax</th><th>Horas frío prev.</th><th>Horas ≤1°C (48h)</th></tr>
            </thead>
            <tbody id="predRows"></tbody>
        </table>
    </div>
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
                // Animar barras avanzadas
                const barFrioAvz = document.getElementById('barFrioAvz');
                const labelFrioAvz = document.getElementById('labelFrioAvz');
                if(barFrioAvz && labelFrioAvz){
                    const porcVal = Math.min(100, Math.max(0, porcentajeFrio));
                    setTimeout(()=>{
                        barFrioAvz.style.width = porcVal + '%';
                        labelFrioAvz.textContent = porcVal.toFixed(1) + '%';
                        if (porcVal < 80) { barFrioAvz.style.background = 'linear-gradient(90deg,#c62828,#ef5350)'; }
                        else if (porcVal < 90) { barFrioAvz.style.background = 'linear-gradient(90deg,#f9a825,#fff176)'; }
                    }, 200);
                }
                const barAjusteAvz = document.getElementById('barAjusteAvz');
                const labelAjusteAvz = document.getElementById('labelAjusteAvz');
                if(barAjusteAvz && labelAjusteAvz){
                    const ajusteDias = <?= json_encode($resultado['ajuste_aplicado_dias'] ?? 0) ?>;
                    // Escala ajuste: suponemos 0-10 días como rango máximo típico
                    const ajustePct = Math.min(100, (ajusteDias/10)*100);
                    setTimeout(()=>{
                        barAjusteAvz.style.width = ajustePct + '%';
                        labelAjusteAvz.textContent = ajusteDias + ' días';
                        if(ajusteDias === 0){ barAjusteAvz.style.background='linear-gradient(90deg,var(--green-4),var(--accent))'; }
                    }, 260);
                }
                const barAnaquelAvz = document.getElementById('barAnaquelAvz');
                const labelAnaquelAvz = document.getElementById('labelAnaquelAvz');
                if(barAnaquelAvz && labelAnaquelAvz){
                    const vidaDias = vidaAnaquel || 0;
                    const maxRefAvz = 60; // misma referencia que panel simple
                    const anaquelPct = Math.min(100,(vidaDias/maxRefAvz)*100);
                    setTimeout(()=>{
                        barAnaquelAvz.style.width = anaquelPct + '%';
                        labelAnaquelAvz.textContent = vidaDias + ' días';
                    }, 300);
                }
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
<footer class="site-footer">
    <div class="container">
        <small>© <?= date('Y') ?> Hackatek - Proyecto de ejemplo</small>
    </div>
</footer>
</body>
</html>