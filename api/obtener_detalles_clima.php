<?php
// api/obtener_detalles_clima.php
// Endpoint para obtener detalles climáticos desde la base de datos

declare(strict_types=1);
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/clima_persistencia.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 28.4069;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : -106.8666;
$dias = isset($_GET['dias']) ? max(1, min(30, (int)$_GET['dias'])) : 7;

try {
    $db = conectar_bd();
    
    // Obtener estadísticas de horas frío recientes
    $sql_horas_frio = "SELECT 
                        COUNT(*) as total_registros,
                        SUM(CASE WHEN temperatura_c BETWEEN 0 AND 7 THEN 1 ELSE 0 END) as horas_frio_acumuladas,
                        MIN(temperatura_c) as temp_minima,
                        MAX(temperatura_c) as temp_maxima,
                        AVG(temperatura_c) as temp_promedio,
                        AVG(relative_humidity_pct) as humedad_promedio,
                        SUM(precipitation_mm) as precipitacion_total,
                        AVG(wind_speed_ms) as viento_promedio,
                        AVG(pressure_hpa) as presion_promedio,
                        DATE(MIN(fecha_hora)) as fecha_inicio,
                        DATE(MAX(fecha_hora)) as fecha_fin
                       FROM clima_horas 
                       WHERE lat = ? AND lon = ? 
                       AND fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)
                       AND fecha_hora <= NOW()";
    
    $stmt = $db->prepare($sql_horas_frio);
    $stmt->bind_param('ddi', $lat, $lon, $dias);
    $stmt->execute();
    $estadisticas = $stmt->get_result()->fetch_assoc();
    
    // Obtener últimas temperaturas por día
    $sql_diario = "SELECT 
                    DATE(fecha_hora) as fecha,
                    MIN(temperatura_c) as temp_min,
                    MAX(temperatura_c) as temp_max,
                    AVG(temperatura_c) as temp_prom,
                    SUM(CASE WHEN temperatura_c BETWEEN 0 AND 7 THEN 1 ELSE 0 END) as horas_frio_dia,
                    AVG(relative_humidity_pct) as humedad_prom,
                    SUM(precipitation_mm) as lluvia_mm
                   FROM clima_horas 
                   WHERE lat = ? AND lon = ? 
                   AND fecha_hora >= DATE_SUB(NOW(), INTERVAL ? DAY)
                   GROUP BY DATE(fecha_hora)
                   ORDER BY fecha DESC";
    
    $stmt2 = $db->prepare($sql_diario);
    $stmt2->bind_param('ddi', $lat, $lon, $dias);
    $stmt2->execute();
    $datos_diarios = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Obtener registro de horas frío acumuladas por temporada
    $sql_temporadas = "SELECT 
                        temporada,
                        horas_frio,
                        fecha_corte,
                        fuente
                       FROM horas_frio_acumuladas 
                       WHERE lat = ? AND lon = ?
                       ORDER BY fecha_corte DESC
                       LIMIT 5";
    
    $stmt3 = $db->prepare($sql_temporadas);
    $stmt3->bind_param('dd', $lat, $lon);
    $stmt3->execute();
    $temporadas = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Formatear datos
    $estadisticas['porcentaje_horas_frio'] = $estadisticas['total_registros'] > 0 ? 
        round(($estadisticas['horas_frio_acumuladas'] / $estadisticas['total_registros']) * 100, 1) : 0;
    
    $estadisticas['temp_promedio'] = $estadisticas['temp_promedio'] ? round($estadisticas['temp_promedio'], 1) : null;
    $estadisticas['temp_minima'] = $estadisticas['temp_minima'] ? round($estadisticas['temp_minima'], 1) : null;
    $estadisticas['temp_maxima'] = $estadisticas['temp_maxima'] ? round($estadisticas['temp_maxima'], 1) : null;
    $estadisticas['humedad_promedio'] = $estadisticas['humedad_promedio'] ? round($estadisticas['humedad_promedio'], 1) : null;
    $estadisticas['precipitacion_total'] = $estadisticas['precipitacion_total'] ? round($estadisticas['precipitacion_total'], 2) : 0;
    $estadisticas['viento_promedio'] = $estadisticas['viento_promedio'] ? round($estadisticas['viento_promedio'], 2) : null;
    $estadisticas['presion_promedio'] = $estadisticas['presion_promedio'] ? round($estadisticas['presion_promedio'], 1) : null;
    
    // Formatear datos diarios
    foreach ($datos_diarios as &$dia) {
        $dia['temp_min'] = $dia['temp_min'] ? round($dia['temp_min'], 1) : null;
        $dia['temp_max'] = $dia['temp_max'] ? round($dia['temp_max'], 1) : null;
        $dia['temp_prom'] = $dia['temp_prom'] ? round($dia['temp_prom'], 1) : null;
        $dia['humedad_prom'] = $dia['humedad_prom'] ? round($dia['humedad_prom'], 1) : null;
        $dia['lluvia_mm'] = $dia['lluvia_mm'] ? round($dia['lluvia_mm'], 2) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'parametros' => [
            'lat' => $lat,
            'lon' => $lon,
            'dias_consultados' => $dias
        ],
        'estadisticas_generales' => $estadisticas,
        'datos_diarios' => $datos_diarios,
        'temporadas_historicas' => $temporadas,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    $stmt->close();
    $stmt2->close(); 
    $stmt3->close();
    $db->close();
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'mensaje' => $e->getMessage()
    ]);
}
?>