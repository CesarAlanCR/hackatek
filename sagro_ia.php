<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/conexion.php';
// Asegurar carga del módulo clima
if (file_exists(__DIR__ . '/includes/clima_manager.php')) {
  require_once __DIR__ . '/includes/clima_manager.php';
} else {
  echo json_encode(['error' => 'Falta archivo clima_manager.php']);
  exit;
}

// ------------------ Parámetros entrada ------------------
$variedad_id = isset($_GET['variedad']) ? (int)$_GET['variedad'] : 1;
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 28.40;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : -106.86;
$fecha_inicio = isset($_GET['inicio']) ? $_GET['inicio'] : date('Y-m-d');
$fecha_fin = isset($_GET['fin']) ? $_GET['fin'] : date('Y-m-d', strtotime('+5 days'));
$etapa_fenologica = isset($_GET['etapa']) ? trim($_GET['etapa']) : 'floracion'; // floracion|brotacion|fruto_cuajado
$nombre_huerto = isset($_GET['huerto']) ? trim($_GET['huerto']) : 'Huerto sin nombre';

// Temperatura crítica aproximada por etapa (ejemplo simplificado)
$umbrales_etapa = [
  'brotacion' => -3.0,
  'floracion' => -2.0,
  'fruto_cuajado' => -1.0
];
$temp_critica = $umbrales_etapa[$etapa_fenologica] ?? -2.0;

// ------------------ Obtener variedad ------------------
try {
  $db = conectar_bd();
} catch (Throwable $e) {
  echo json_encode(['error' => 'No hay conexión a BD', 'detalle' => $e->getMessage()]);
  exit;
}
$stmt = $db->prepare('SELECT id,nombre_variedad,resistencia_heladas FROM catalogo_manzanos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $variedad_id);
$stmt->execute();
$res = $stmt->get_result();
$variedad = $res->fetch_assoc();
$stmt->close();
if (!$variedad) {
  echo json_encode(['error' => 'Variedad no encontrada', 'id' => $variedad_id]);
  $db->close();
  exit;
}

// Factor ajuste por resistencia (baja requiere más cuidado)
$factor_resistencia = 1.0;
if ($variedad['resistencia_heladas'] === 'baja') $factor_resistencia = 1.2;
if ($variedad['resistencia_heladas'] === 'alta') $factor_resistencia = 0.85;
$temp_critica_ajustada = $temp_critica + ( ($factor_resistencia - 1) * 1.0 ); // pequeña compensación

// ------------------ Pronóstico clima ------------------
if (!function_exists('obtener_historico_clima')) {
  echo json_encode(['error' => 'Función obtener_historico_clima no disponible']);
  $db->close();
  exit;
}
$datos_clima = obtener_historico_clima($lat, $lon, $fecha_inicio, $fecha_fin); // reutilizamos histórico como proxy a forecast
$forecast = $datos_clima['hourly']['temperature_2m'] ?? [];
$hours = $datos_clima['hourly']['time'] ?? [];

$eventos_riesgo = [];
for ($i = 0; $i < count($forecast); $i++) {
  $t = (float)$forecast[$i];
  if ($t <= $temp_critica_ajustada + 0.5) { // margen de seguridad
    $eventos_riesgo[] = [
      'hora' => $hours[$i] ?? 'desconocida',
      'temp' => $t
    ];
  }
}

// ------------------ Evaluación de riesgo ------------------
$riesgo = 'bajo';
if (count($eventos_riesgo) > 0) {
  // contar horas cercanas a umbral
  $horas_cercanas = count($eventos_riesgo);
  if ($horas_cercanas >= 2) $riesgo = 'moderado';
  if ($horas_cercanas >= 4) $riesgo = 'alto';
}

// ------------------ Generar reporte NL ------------------
$nombre_variedad = $variedad['nombre_variedad'];
$primera_hora_riesgo = $eventos_riesgo[0]['hora'] ?? null;
$min_temp = null;
foreach ($eventos_riesgo as $ev) { if ($min_temp === null || $ev['temp'] < $min_temp) $min_temp = $ev['temp']; }

$parrafo_analisis = '';
if ($riesgo === 'bajo') {
  $parrafo_analisis = "No se detecta riesgo importante de helada en el periodo analizado.";
} else {
  $parrafo_analisis = "Se detectan condiciones cercanas al umbral crítico (" . number_format($temp_critica_ajustada,1) . "°C) para la etapa de {$etapa_fenologica}.";
}
$parrafo_recomendacion = '';
if ($riesgo === 'alto') {
  $parrafo_recomendacion = "Recomendación: Active sistemas de mitigación (aspersión / ventiladores) antes de la hora crítica estimada.";
} elseif ($riesgo === 'moderado') {
  $parrafo_recomendacion = "Monitorear de cerca durante la noche. Preparar sistemas de protección si desciende más la temperatura.";
} else {
  $parrafo_recomendacion = "Continuar monitoreo habitual; no se requieren acciones especiales.";
}

$reporte = [
  'asunto' => "Riesgo de helada para {$nombre_huerto}",
  'huerto' => $nombre_huerto,
  'variedad' => $nombre_variedad,
  'etapa_fenologica' => $etapa_fenologica,
  'riesgo' => $riesgo,
  'temperatura_critica_aprox' => $temp_critica_ajustada,
  'hora_primer_evento' => $primera_hora_riesgo,
  'temperatura_min_periodo' => $min_temp,
  'eventos_riesgo' => $eventos_riesgo,
  'analisis' => $parrafo_analisis,
  'recomendacion' => $parrafo_recomendacion,
  'periodo_consultado' => [$fecha_inicio, $fecha_fin]
];

// ------------------ Respuesta ------------------
echo json_encode($reporte, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$db->close();
