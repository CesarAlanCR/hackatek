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
body{font-family:Arial,sans-serif; margin:0; background:radial-gradient(circle at 28% 18%,#f0f9ff,#e3f2fd 55%,#e8f5e9 120%); color:#1e2d2f;}
.container{max-width:1180px; margin:0 auto; padding:24px 28px;}
/* Hero dinámico */
.hero-dynamic{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:30px;padding:32px 38px 28px;background:linear-gradient(135deg,#ffffff 0%, #f1f7fb 52%, #f1f8f3 100%);border:1px solid rgba(0,0,0,0.05);border-radius:20px;box-shadow:0 6px 20px rgba(33,56,66,0.08);position:relative;overflow:hidden;}
.hero-dynamic:before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 74% 22%,rgba(25,118,210,.15),transparent 60%);pointer-events:none;}
.hero-dynamic h1{margin:0 0 10px;font-size:clamp(1.6rem,3.4vw,2.5rem);letter-spacing:.6px;color:#0d3a52;}
.hero-dynamic .intro{margin:0;max-width:640px;line-height:1.45;font-size:clamp(.95rem,1.15vw,1.05rem);color:#455a64;font-weight:500;}
.badge-hero{display:inline-flex;align-items:center;gap:6px;background:#1976d2;color:#fff;padding:10px 16px;border-radius:40px;font-size:.75rem;font-weight:600;letter-spacing:.8px;text-transform:uppercase;box-shadow:0 4px 14px rgba(25,118,210,.3);}
/* Progress ring */
.ring-wrap{display:flex;align-items:center;gap:24px;justify-content:center;}
.progress-ring{width:120px;height:120px;position:relative;}
.progress-ring svg{transform:rotate(-90deg);}
.progress-ring .pr-bg{stroke:#e0e7ea;stroke-width:10;fill:none;}
.progress-ring .pr-val{stroke:url(#gradFrio);stroke-width:10;fill:none;stroke-linecap:round;transition:stroke-dashoffset 1.2s cubic-bezier(.2,.8,.25,1);}
.progress-ring .center-label{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:#0d3a52;letter-spacing:.5px;}
.panel{border:1px solid #d4dadc; padding:20px 22px; border-radius:14px; margin-bottom:26px; background:#ffffff; box-shadow:0 2px 4px rgba(0,0,0,0.05);} 
.panel h2{margin-top:0; font-size:20px;}
label{display:block; margin-top:14px; font-weight:600; font-size:14px;}
select,input[type=date]{padding:9px 12px; width:300px; border:1px solid #b7c3c7; border-radius:6px; font-size:14px; background:#fff;}
button, .btn{margin-top:18px; padding:10px 22px; background:#00695c; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; letter-spacing:.4px; text-decoration:none; display:inline-block;}
button:hover,.btn:hover{background:#004d40;}
.btn-secondary{background:#455a64;}
/* Métricas estilo tarjetas */
.metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin:4px 0 4px;}
.metric-card{position:relative;background:linear-gradient(180deg,#ffffff,#f3f8fa);border:1px solid rgba(25,118,210,0.10);border-radius:14px;padding:14px 16px 12px;box-shadow:0 4px 14px rgba(33,56,66,.08);overflow:hidden;isolation:isolate;transform:translateY(14px) scale(.96);opacity:0;transition:.55s cubic-bezier(.2,.8,.25,1);}
.metric-card.active{transform:translateY(0) scale(1);opacity:1;box-shadow:0 10px 30px rgba(33,56,66,.18);}
.metric-card h3{margin:0 0 6px;font-size:.82rem;letter-spacing:.5px;color:#0d3a52;font-weight:700;text-transform:uppercase;}
.metric-card .value{font-size:1.15rem;font-weight:700;color:#103b60;}
.metric-card small{display:block;margin-top:2px;font-size:.65rem;color:#546e7a;letter-spacing:.5px;}
.metric-card.window{background:linear-gradient(135deg,#fff7e6,#ffeec8);border-color:#ffcc80;}
.metric-card.shelf{background:linear-gradient(135deg,#e3f2fd,#ffffff);border-color:#90caf9;}
.metric-card.cold-ok{background:linear-gradient(135deg,#e8f5e9,#ffffff);border-color:#a5d6a7;}
.metric-card.cold-mid{background:linear-gradient(135deg,#fff9e1,#fff3cd);border-color:#ffe082;}
.metric-card.cold-low{background:linear-gradient(135deg,#fde0e0,#ffffff);border-color:#ff8a80;}
.alerta{background:#fff4e5; padding:10px 12px; border-left:4px solid #ff9800; margin:6px 0; border-radius:4px; font-size:13px;}
.ok{background:#e6f8ed; padding:10px 12px; border-left:4px solid #2e7d32; margin:6px 0; border-radius:4px; font-size:13px;}
.info{background:#e3f2fd; padding:10px 12px; border-left:4px solid #1976d2; margin:6px 0; border-radius:4px; font-size:13px;}
.chart-wrapper{display:flex; flex-wrap:wrap; gap:34px;}
.chart-box{flex:1 1 380px; min-width:320px; background:#f9fbfc; padding:18px; border-radius:14px; border:1px solid #dbe3e6; position:relative;}
.chart-box h4{margin:0 0 12px; font-size:15px; font-weight:600; letter-spacing:.4px;}
.explicacion{font-size:12.5px; color:#455a64; margin-top:10px;}
.progress-wrap{background:#eceff1; border-radius:8px; overflow:hidden; height:30px; position:relative;}
.progress-bar{height:100%; background:linear-gradient(90deg,#1565c0,#42a5f5); width:0%; transition:width 1s;} 
.progress-label{position:absolute; top:0; left:50%; transform:translateX(-50%); font-size:13px; font-weight:600; color:#073042; line-height:30px;}
.badge{display:inline-block; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; letter-spacing:.3px;}
.b-riesgo-alto{background:#d32f2f; color:#fff;}
.b-riesgo-medio{background:#f9a825; color:#212121;}
.b-riesgo-bajo{background:#2e7d32; color:#fff;}
.timeline{display:flex; align-items:center; gap:10px; margin:10px 0 4px; flex-wrap:wrap;}
.t-step{background:#ffffff; border:1px solid #b0bec5; padding:10px 12px; border-radius:10px; min-width:160px; position:relative;}
.t-step h5{margin:0 0 6px; font-size:13px; font-weight:700; letter-spacing:.4px; color:#37474f;}
.t-step p{margin:0; font-size:12.5px; color:#455a64;}
.connector{flex:1; height:4px; background:linear-gradient(90deg,#b0bec5,#90a4ae); border-radius:2px; min-width:40px; position:relative;}
.ventana-box{background:#fff3e0; border:1px solid #ffcc80;}
.anaquel-box{background:#e1f5fe; border:1px solid #81d4fa;}
.help-toggle{cursor:pointer; font-size:12.5px; color:#1565c0; text-decoration:underline; margin-top:6px;}
#ayudaConceptos{display:none; background:#ffffff; border:1px dashed #90a4ae; padding:12px 14px; border-radius:10px; margin-top:10px; font-size:12.5px;}
@media (max-width:680px){.chart-box{min-width:100%;}.timeline{flex-direction:column;}.connector{display:none;}}
</style>
</head>
<body>
<main class="container">
<section class="hero-dynamic">
    <div>
        <span class="badge-hero">MANZANA</span>
        <h1>Planificador Visual de Cosecha</h1>
        <p class="intro">Selecciona variedad y fecha de floración para estimar ventana óptima, vida de anaquel y estado de horas frío. (Indicador de % éxito removido a solicitud).</p>
    </div>
</section>
<div class="panel">
    <form method="POST">
        <label for="cultivo">Cultivo</label>
        <select name="cultivo" id="cultivo" disabled>
            <option value="manzana" selected>Manzana</option>
            <option value="frijol" disabled>Frijol (próximo)</option>
            <option value="trigo" disabled>Trigo (próximo)</option>
        </select>
        <small style="display:block;color:#555;margin-top:4px;">Solo manzana habilitada.</small>
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
    <button type="button" id="toggleDetallado" style="margin-top:16px;background:#1976d2;">Ver detalles avanzados</button>
        <p style="font-size:12px;color:#54666b;margin-top:6px;">Esta vista muestra sólo lo que necesitas para decidir logística y monitoreo diario.</p>
    <?php else: ?>
        <p>No se construyó el resumen esencial.</p>
    <?php endif; ?>
</div>
<div id="bloqueCompleto" style="display:none;">
<div class="panel">
    <h2>Resumen</h2>
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
    <p style="margin-top:10px;font-size:13px;color:#37474f;">Las horas frío se contabilizan (0°C–7°C). Si no había datos previos se estimó un bloque reciente (≈30 días). Un menor porcentaje retrasa la fecha objetivo para no comprometer calidad.</p>
    <h3>Alertas</h3>
    <?php if(count($resultado['alertas'])===0): ?>
        <div class="ok">Sin alertas relevantes.</div>
    <?php else: foreach($resultado['alertas'] as $a): ?>
        <div class="alerta"><?= htmlspecialchars($a) ?></div>
    <?php endforeach; endif; ?>
</div>
<div class="panel">
    <h2>Desglose Detallado</h2>
    <p style="font-size:13px;color:#455a64;">Cada métrica incluye una explicación corta y otra amplia para reforzar comprensión incluso sin experiencia técnica o agrícola.</p>
        <p style="font-size:12px;color:#37474f;background:#f1f8ff;border:1px solid #cfd8dc;padding:10px 12px;border-radius:10px;line-height:1.35;">
            <strong>Metodología Prob. Éxito (versión heurística 0.2):</strong> Partimos del % de horas frío cumplidas comparado con lo requerido, aplicamos penalización por días de ajuste (déficit térmico) y añadimos bonificación incremental si el cumplimiento es alto (≥95% sin ajuste). Ahora se incorpora un factor adicional de <em>chill portions</em> estimadas (heurística): rangos mayores de porciones aportan bonificación (ej. ≥40 → +4 pts). Futuras versiones integrarán: riesgo de heladas severas, anomalías de radiación, estrés hídrico y validación fenológica visual. <strong>Nota:</strong> Esta probabilidad no garantiza rendimiento, sino la consistencia temporal y de calidad prevista bajo supuestos estándar de manejo.
        </p>
    <?php if(isset($resultado['detalles']) && is_array($resultado['detalles'])): ?>
        <?php foreach($resultado['detalles'] as $clave => $info): ?>
            <div style="margin-bottom:18px;padding:14px 16px;border:1px solid #d0d7da;border-radius:10px;background:#fcfdfd;">
                <h3 style="margin:0 0 8px;font-size:15px;letter-spacing:.4px;">🔍 <?= htmlspecialchars($info['titulo']) ?></h3>
                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;">
                    <div style="min-width:180px;font-size:13px;line-height:1.3;">
                        <strong>Valor:</strong> <span style="color:#1a3d46;"><?= htmlspecialchars(is_scalar($info['valor']) ? (string)$info['valor'] : (is_array($info['valor'])? json_encode($info['valor']) : '')) ?></span>
                    </div>
                    <div style="flex:1;min-width:240px;font-size:12.5px;color:#2f4d56;">
                        <strong>Explicación corta:</strong><br/>
                        <?= htmlspecialchars($info['explicacion_corta']) ?><br/>
                        <strong style="display:block;margin-top:6px;">Explicación extensa:</strong>
                        <span style="display:block;margin-top:2px;"><?= htmlspecialchars($info['explicacion_extensa']) ?></span>
                        <span style="display:block;margin-top:6px;color:#455a64;font-style:italic;">Resumen: <?= htmlspecialchars($info['explicacion_corta']) ?>.</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No hay detalles disponibles.</p>
    <?php endif; ?>
    <?php if(isset($resultado['debug_calc'])): $dbg = $resultado['debug_calc']; ?>
        <div style="margin-top:12px;padding:12px;border:1px dashed #b0bec5;border-radius:8px;background:#fff;">
            <h4>Depuración cálculo probabilidad</h4>
            <pre style="font-size:13px;color:#263238;white-space:pre-wrap;"><?= htmlspecialchars(json_encode($dbg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
    <?php endif; ?>
    <p style="font-size:12px;color:#607d8b;margin-top:10px;">Fin del desglose. Puedes volver arriba para cambiar variedad o fecha y recalcular el conjunto completo de métricas y sus explicaciones repetitivas.</p>
</div>
<div class="panel">
    <h2>Visualizaciones simplificadas</h2>
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
</body>
</html>