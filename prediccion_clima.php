<?php
// prediccion_clima.php
// Devuelve agregados diarios a partir de datos en clima_horas (histórico + forecast ya ingerido)
// Parámetros opcionales: lat, lon, dias (rango futuro), pasado (dias pasados)

declare(strict_types=1);
require_once __DIR__ . '/includes/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 28.4069;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : -106.8666;
$dias_future = isset($_GET['dias']) ? max(1, min(14, (int)$_GET['dias'])) : 5; // máximo 14
$dias_past = isset($_GET['pasado']) ? max(0, min(14, (int)$_GET['pasado'])) : 0; // opcional pasado

try {
    $db = conectar_bd();
    $hoy = new DateTime('today');
    $inicio = (clone $hoy)->sub(new DateInterval('P' . $dias_past . 'D'));
    $fin = (clone $hoy)->add(new DateInterval('P' . $dias_future . 'D'));

    $sql = "SELECT DATE(fecha_hora) AS dia,
        MIN(temperatura_c) AS tmin,
        MAX(temperatura_c) AS tmax,
        AVG(temperatura_c) AS tmed,
        SUM(CASE WHEN temperatura_c > 0 AND temperatura_c <= 7 THEN 1 ELSE 0 END) AS horas_frio,
        SUM(CASE WHEN temperatura_c <= 1 THEN 1 ELSE 0 END) AS horas_leq_1,
        AVG(relative_humidity_pct) AS rh_prom,
        SUM(precipitation_mm) AS precip_mm,
        AVG(cloud_cover_pct) AS nubosidad_pct,
        AVG(wind_speed_ms) AS viento_ms
        FROM clima_horas
        WHERE lat = ? AND lon = ? AND fecha_hora BETWEEN ? AND ?
        GROUP BY dia
        ORDER BY dia";
    $stmt = $db->prepare($sql);
    $desdeStr = $inicio->format('Y-m-d 00:00:00');
    $hastaStr = $fin->format('Y-m-d 23:59:59');
    $stmt->bind_param('ddss', $lat, $lon, $desdeStr, $hastaStr);
    $stmt->execute();
    $res = $stmt->get_result();
    $filas = [];
    while($r = $res->fetch_assoc()) {
        $filas[] = [
            'dia' => $r['dia'],
            'tmin' => $r['tmin'] !== null ? round((float)$r['tmin'],1) : null,
            'tmax' => $r['tmax'] !== null ? round((float)$r['tmax'],1) : null,
            'tmed' => $r['tmed'] !== null ? round((float)$r['tmed'],1) : null,
            'horas_frio' => (int)$r['horas_frio'],
            'horas_leq_1' => (int)$r['horas_leq_1'],
            'rh_promedio_pct' => $r['rh_prom'] !== null ? round((float)$r['rh_prom'],1) : null,
            'precip_mm' => $r['precip_mm'] !== null ? round((float)$r['precip_mm'],2) : null,
            'nubosidad_pct' => $r['nubosidad_pct'] !== null ? round((float)$r['nubosidad_pct'],1) : null,
            'viento_ms' => $r['viento_ms'] !== null ? round((float)$r['viento_ms'],2) : null
        ];
    }
    $stmt->close();
    $db->close();

    echo json_encode([
        'parametros' => [
            'lat' => $lat,
            'lon' => $lon,
            'dias_future' => $dias_future,
            'dias_past' => $dias_past,
            'rango_desde' => $desdeStr,
            'rango_hasta' => $hastaStr
        ],
        'dias' => $filas,
        'fuente' => 'clima_horas (Open-Meteo extendido)'
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar predicción','mensaje' => $e->getMessage()]);
}
// Fin del script único
?>