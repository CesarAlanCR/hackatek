<?php
// Proxy WFS genérico con cache y logging
// Uso: /api/wfs_proxy.php?service=https://ejemplo.com/geoserver/wfs&typeName=capa&bbox=...&cql_filter=...
header('Content-Type: application/json; charset=utf-8');

$service = $_GET['service'] ?? '';
$typeName = $_GET['typeName'] ?? '';
$outputFormat = 'application/json';
$bbox = $_GET['bbox'] ?? '';
$cql = $_GET['cql_filter'] ?? '';

if (!$service || !$typeName) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros obligatorios: service y typeName']);
    exit;
}

$cacheDir = __DIR__ . '/cache';
@mkdir($cacheDir, 0755, true);
$logFile = __DIR__ . '/logs/wfs_proxy.log';

// Construir URL WFS GetFeature
$params = [
    'service' => 'WFS',
    'version' => '2.0.0',
    'request' => 'GetFeature',
    'typeName' => $typeName,
    'outputFormat' => $outputFormat
];
if ($bbox) $params['bbox'] = $bbox;
if ($cql) $params['cql_filter'] = $cql;

$query = http_build_query($params);
$wfsUrl = rtrim($service, '?&') . (strpos($service, '?') !== false ? '&' : '?') . $query;

// Cache key
$cacheKey = md5($wfsUrl);
$cacheFile = "$cacheDir/$cacheKey.geojson";
$ttl = 12 * 3600; // 12 horas

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    @file_put_contents($logFile, date('c') . " CACHE HIT $wfsUrl\n", FILE_APPEND);
    readfile($cacheFile);
    exit;
}

// Descargar WFS
$ch = curl_init($wfsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);
$data = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($http !== 200 || !$data) {
    @file_put_contents($logFile, date('c') . " FETCH FAIL http=$http err=$err $wfsUrl\n", FILE_APPEND);
    http_response_code(502);
    echo json_encode(['error' => 'Fallo al consultar WFS', 'http' => $http, 'curl_err' => $err]);
    exit;
}

// Validar GeoJSON
$json = json_decode($data, true);
if (!$json || !isset($json['type']) || $json['type'] !== 'FeatureCollection') {
    @file_put_contents($logFile, date('c') . " INVALID FORMAT $wfsUrl\n", FILE_APPEND);
    // Algunas implementaciones usan GeoJSON con Content-Type distinto; intentar pasar tal cual
    echo $data;
    exit;
}

file_put_contents($cacheFile, $data);
@file_put_contents($logFile, date('c') . " FETCH OK size=" . strlen($data) . " $wfsUrl\n", FILE_APPEND);
header('X-Data-Source: wfs');
echo $data;
