<?php
// Proxy/cache para cuerpos de agua y pozos de México
// Descarga GeoJSON de fuente oficial y lo sirve al frontend
header('Content-Type: application/json; charset=utf-8');

$cacheFile = __DIR__ . '/water_bodies_cache.geojson';
$ttl = 24 * 3600; // 24 horas
// Fuente remota (actualizar con dataset válido) y respaldo local
$sourceUrl = 'https://raw.githubusercontent.com/GeoHackWeek/ghw2017_geojson/master/data/mexico_waterbodies.geojson';
$fallbackFile = dirname(__DIR__) . '/recursos/data/water_bodies_sample.geojson';
// Archivo de log
$logFile = __DIR__ . '/logs/water_bodies.log';

// Crear carpeta de cache si no existe
@mkdir(__DIR__, 0755, true);

// Si hay cache reciente, servirlo
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    @file_put_contents($logFile, date('c') . " CACHE HIT\n", FILE_APPEND);
    readfile($cacheFile);
    exit;
}

// Descargar desde la fuente
$ch = curl_init($sourceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$data = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($http !== 200 || !$data) {
    @file_put_contents($logFile, date('c') . " FETCH FAIL http=$http err=$err\n", FILE_APPEND);
    // Usar respaldo local si existe
    if (file_exists($fallbackFile)) {
        @file_put_contents($logFile, date('c') . " FALLBACK LOCAL\n", FILE_APPEND);
        header('X-Data-Source: fallback');
        readfile($fallbackFile);
        exit;
    }
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo recuperar la fuente', 'http' => $http, 'curl_err' => $err]);
    exit;
}

// Validar que sea GeoJSON
$json = json_decode($data, true);
if (!isset($json['type']) || $json['type'] !== 'FeatureCollection') {
    @file_put_contents($logFile, date('c') . " INVALID FORMAT\n", FILE_APPEND);
    // Respaldo si el formato no es válido
    if (file_exists($fallbackFile)) {
        header('X-Data-Source: fallback');
        readfile($fallbackFile);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Formato inesperado de la fuente']);
    exit;
}

// Guardar cache y servir
file_put_contents($cacheFile, $data);
@file_put_contents($logFile, date('c') . " FETCH OK size=" . strlen($data) . "\n", FILE_APPEND);
header('X-Data-Source: remote');
echo $data;
