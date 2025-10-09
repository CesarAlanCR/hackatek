<?php
// api/huerto_resumen.php
// Endpoint consolidado: variedad + horas frío + ventana cosecha + riesgo helada

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/catalogo_manzanos.php';
require_once __DIR__ . '/../includes/planificador_manager.php';
require_once __DIR__ . '/../includes/clima_manager.php';
require_once __DIR__ . '/../includes/clima_persistencia.php';

// Parámetros
$variedad_id = isset($_GET['variedad_id']) ? (int)$_GET['variedad_id'] : 1;
$fecha_floracion = isset($_GET['fecha_floracion']) ? $_GET['fecha_floracion'] : date('Y-m-d', strtotime('-90 days'));
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 28.40;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : -106.86;
$etapa = isset($_GET['etapa']) ? trim($_GET['etapa']) : 'floracion';
$huerto_nombre = isset($_GET['huerto']) ? trim($_GET['huerto']) : 'Huerto Manzanas';

// Inicializar tablas necesarias
catalogo_inicializar();
clima_inicializar_tablas();

// Obtener variedad
$variedad = planificador_obtener_variedad($variedad_id);
if (!$variedad) {
    echo json_encode(['error' => 'Variedad no encontrada']);
    exit;
}

// Horas frío acumuladas (último registro) o cálculo rápido 30 días si falta
$db = conectar_bd();
$horas_frio_actual = null;
$q = $db->query('SELECT horas_frio FROM horas_frio_acumuladas ORDER BY fecha_corte DESC LIMIT 1');
if ($q && $row = $q->fetch_assoc()) { $horas_frio_actual = (int)$row['horas_frio']; }
$db->close();
if ($horas_frio_actual === null) {
    $inicio = date('Y-m-d', strtotime('-30 days'));
    $fin = date('Y-m-d');
    clima_ingestar_periodo($lat, $lon, $inicio, $fin);
    $horas_frio_actual = clima_calcular_acumulado($lat, $lon, $inicio.' 00:00:00', $fin.' 23:59:59');
    clima_guardar_acumulado($lat, $lon, date('Y'), $horas_frio_actual, $fin);
}

// Calcular planificación
$plan = planificador_calcular($variedad, $fecha_floracion, $horas_frio_actual);

// Riesgo helada (reuso lógica de sagro_ia.php simplificada)
$umbrales_etapa = ['brotacion' => -3.0, 'floracion' => -2.0, 'fruto_cuajado' => -1.0];
$temp_critica = $umbrales_etapa[$etapa] ?? -2.0;
$factor_resistencia = 1.0;
if (($variedad['resistencia_heladas'] ?? '') === 'baja') $factor_resistencia = 1.2;
if (($variedad['resistencia_heladas'] ?? '') === 'alta') $factor_resistencia = 0.85;
$temp_critica_ajustada = $temp_critica + (($factor_resistencia - 1) * 1.0);

$periodo_inicio = date('Y-m-d');
$periodo_fin = date('Y-m-d', strtotime('+3 days'));
$datos_clima = obtener_historico_clima($lat, $lon, $periodo_inicio, $periodo_fin);
$forecast = $datos_clima['hourly']['temperature_2m'] ?? [];
$hours = $datos_clima['hourly']['time'] ?? [];
$eventos_riesgo = [];
for ($i=0; $i<count($forecast); $i++) {
    $t = (float)$forecast[$i];
    if ($t <= $temp_critica_ajustada + 0.5) {
        $eventos_riesgo[] = ['hora' => $hours[$i] ?? 'desconocida', 'temp' => $t];
    }
}
$riesgo = 'bajo';
if (count($eventos_riesgo) >= 2) $riesgo = 'moderado';
if (count($eventos_riesgo) >= 4) $riesgo = 'alto';

// Respuesta consolidada
$resp = [
    'huerto' => $huerto_nombre,
    'variedad' => [
        'id' => $variedad['id'],
        'nombre' => $variedad['nombre_variedad'],
        'horas_frio_necesarias' => $variedad['horas_frio_necesarias'] ?? null
    ],
    'planificacion' => $plan,
    'clima' => [
        'lat' => $lat,
        'lon' => $lon,
        'horas_frio_acumuladas' => $horas_frio_actual
    ],
    'helada' => [
        'riesgo' => $riesgo,
        'temp_critica_ajustada' => $temp_critica_ajustada,
        'eventos_riesgo' => $eventos_riesgo,
        'periodo' => [$periodo_inicio, $periodo_fin]
    ]
];

echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
