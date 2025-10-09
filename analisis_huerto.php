<?php
// analisis_huerto.php - Orquestador con persistencia de horas frío

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/conexion.php';
require_once __DIR__ . '/includes/clima_manager.php';
require_once __DIR__ . '/includes/clima_persistencia.php';

// Parámetros
$variedad_id = isset($_GET['variedad']) ? (int)$_GET['variedad'] : 1;
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 28.40;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : -106.86;
$inicio_invierno = isset($_GET['inv_inicio']) ? $_GET['inv_inicio'] : '2024-11-01';
$fin_invierno = isset($_GET['inv_fin']) ? $_GET['inv_fin'] : date('Y-m-d');
$fecha_floracion = isset($_GET['fecha_flor']) ? $_GET['fecha_flor'] : '2025-04-25';

// Temporada (simple: año inicio - año final)
$temporada = substr($inicio_invierno,0,4) . '-' . substr($fecha_floracion,0,4);
$fecha_corte = $fin_invierno;

// Inicializar tablas clima (si faltan)
clima_inicializar_tablas();

try {
  $db = conectar_bd();
} catch (Throwable $e) {
  echo json_encode(['error' => 'BD no disponible', 'detalle' => $e->getMessage()]);
  exit;
}

// Asegurar tabla catalogo_manzanos existe
$check = $db->query("SHOW TABLES LIKE 'catalogo_manzanos'");
if (!$check || $check->num_rows === 0) {
  $sqlPath = __DIR__ . '/includes/sql/catalogo_manzanos.sql';
  if (file_exists($sqlPath)) {
    $sqlCat = file_get_contents($sqlPath);
    if ($sqlCat !== false) {
      if (!$db->multi_query($sqlCat)) {
        error_log('No se pudo crear catalogo_manzanos: ' . $db->error);
      } else {
        while ($db->more_results() && $db->next_result()) { /* flush */ }
      }
    }
  }
}
if ($check instanceof mysqli_result) { $check->close(); }

// Obtener variedad
$stmt = $db->prepare('SELECT id,nombre_variedad,horas_frio_necesarias,dias_de_flor_a_cosecha,vida_anaquel_dias FROM catalogo_manzanos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $variedad_id);
$stmt->execute();
$res = $stmt->get_result();
$variedad = $res->fetch_assoc();
$stmt->close();
if (!$variedad) {
  echo json_encode(['error' => 'Variedad no encontrada']);
  $db->close();
  exit;
}

$horas_requeridas = (int)$variedad['horas_frio_necesarias'];

// Ver si hay acumulado existente
$acumulado = clima_obtener_acumulado($lat, $lon, $temporada, $fecha_corte);
if ($acumulado) {
  $horas_frio = (int)$acumulado['horas_frio'];
} else {
  // Ingestar periodo y calcular
  clima_ingestar_periodo($lat, $lon, $inicio_invierno, $fin_invierno);
  $horas_frio = clima_calcular_acumulado($lat, $lon, $inicio_invierno . ' 00:00:00', $fin_invierno . ' 23:59:59');
  clima_guardar_acumulado($lat, $lon, $temporada, $horas_frio, $fecha_corte);
}

$estado_horas = $horas_frio >= $horas_requeridas ? 'cumplido' : 'incompleto';
$deficit = $horas_frio >= $horas_requeridas ? 0 : ($horas_requeridas - $horas_frio);

// Cosecha estimada
$dias_flor_cosecha = (int)$variedad['dias_de_flor_a_cosecha'];
$fecha_cosecha_estimada = date('Y-m-d', strtotime($fecha_floracion . " +{$dias_flor_cosecha} days"));
$vida_anaquel = (int)$variedad['vida_anaquel_dias'];
$fecha_limite_anaquel = date('Y-m-d', strtotime($fecha_cosecha_estimada . " +{$vida_anaquel} days"));

$respuesta = [
  'entrada' => [
    'variedad_id' => $variedad_id,
    'lat' => $lat,
    'lon' => $lon,
    'temporada' => $temporada,
    'invierno_periodo' => [$inicio_invierno, $fin_invierno],
    'fecha_floracion' => $fecha_floracion
  ],
  'variedad' => [
    'nombre' => $variedad['nombre_variedad'],
    'horas_frio_requeridas' => $horas_requeridas,
    'dias_flor_cosecha' => $dias_flor_cosecha,
    'vida_anaquel_dias' => $vida_anaquel
  ],
  'horas_frio' => [
    'acumuladas' => $horas_frio,
    'estado' => $estado_horas,
    'deficit' => $deficit
  ],
  'cosecha' => [
    'fecha_estimada' => $fecha_cosecha_estimada,
    'fecha_limite_anaquel' => $fecha_limite_anaquel
  ]
];

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$db->close();
