<?php
// diagnostico_clima.php
// Script rápido para verificar ingesta extendida y cálculos heurísticos
require_once __DIR__ . '/includes/clima_persistencia.php';
require_once __DIR__ . '/includes/conexion.php';

$lat = 28.4069; $lon = -106.8666; // coordenadas ejemplo
$pastDays = isset($_GET['past']) ? (int)$_GET['past'] : 7;
$forecastDays = isset($_GET['forecast']) ? (int)$_GET['forecast'] : 2;
$tz = 'America/Mexico_City';

$insertados = clima_ingestar_open_meteo_extendido($lat, $lon, $pastDays, $forecastDays, $tz);

// Rango temporal evaluado
$desde = date('Y-m-d', strtotime('-'.$pastDays.' days')) . ' 00:00:00';
$hasta = date('Y-m-d') . ' 23:59:59';

// Horas frío acumuladas y chill portions
$horasFrio = clima_calcular_acumulado($lat, $lon, $desde, $hasta);
$chill = clima_calcular_chill_portions_heuristica($lat, $lon, $desde, $hasta);

// Conteo total filas en rango
$db = conectar_bd();
$stmt = $db->prepare('SELECT COUNT(*) c FROM clima_horas WHERE lat=? AND lon=? AND fecha_hora BETWEEN ? AND ?');
$stmt->bind_param('ddss', $lat, $lon, $desde, $hasta);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

header('Content-Type: application/json');

echo json_encode([
    'parametros' => [ 'lat' => $lat, 'lon' => $lon, 'past_days' => $pastDays, 'forecast_days' => $forecastDays, 'timezone' => $tz ],
    'insertados' => $insertados,
    'rango_evaluado' => [ 'desde' => $desde, 'hasta' => $hasta ],
    'filas_rango' => (int)$res['c'],
    'horas_frio' => $horasFrio,
    'chill_portions_heuristica' => $chill,
    'nota' => 'Verifica insertados == filas_rango si sólo se ingesta este script; diferencias indican datos previos. Chill portions heurística NO es oficial.'
]);
