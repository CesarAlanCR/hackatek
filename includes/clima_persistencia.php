<?php
// includes/clima_persistencia.php
// Helper para almacenar temperaturas horarias y calcular horas frío acumuladas

declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/clima_manager.php';

function clima_inicializar_tablas(): void {
    $path = __DIR__ . '/sql/clima_horas.sql';
    if (!file_exists($path)) return;
    $sql = file_get_contents($path);
    if ($sql === false) return;
    $db = conectar_bd();
    if (!$db->multi_query($sql)) {
        error_log('Error creando tablas clima: ' . $db->error);
    } else {
        while ($db->more_results() && $db->next_result()) { /* flush */ }
    }
    $db->close();
}

function clima_guardar_registro(float $lat, float $lon, string $fecha_hora, float $temperatura): bool {
    $db = conectar_bd();
    $stmt = $db->prepare('INSERT IGNORE INTO clima_horas (lat, lon, fecha_hora, temperatura_c) VALUES (?,?,?,?)');
    $stmt->bind_param('ddsd', $lat, $lon, $fecha_hora, $temperatura);
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();
    return $ok;
}

/**
 * Verifica si las columnas extendidas existen y aplica ALTER TABLE si faltan.
 */
function clima_verificar_columnas_extendidas(): void {
    $db = conectar_bd();
    $colsNecesarias = [
        'relative_humidity_pct' => 'ADD COLUMN relative_humidity_pct DECIMAL(5,2) NULL AFTER temperatura_c',
        'dew_point_c' => 'ADD COLUMN dew_point_c DECIMAL(5,2) NULL AFTER relative_humidity_pct',
        'precipitation_mm' => 'ADD COLUMN precipitation_mm DECIMAL(6,2) NULL AFTER dew_point_c',
        'cloud_cover_pct' => 'ADD COLUMN cloud_cover_pct DECIMAL(5,2) NULL AFTER precipitation_mm',
        'wind_speed_ms' => 'ADD COLUMN wind_speed_ms DECIMAL(5,2) NULL AFTER cloud_cover_pct',
        'pressure_hpa' => 'ADD COLUMN pressure_hpa DECIMAL(7,2) NULL AFTER wind_speed_ms',
        'radiation_wm2' => 'ADD COLUMN radiation_wm2 DECIMAL(8,2) NULL AFTER pressure_hpa'
    ];
    $faltantes = [];
    $res = $db->query("SHOW COLUMNS FROM clima_horas");
    $presentes = [];
    if ($res) {
        while($r = $res->fetch_assoc()) { $presentes[] = $r['Field']; }
    }
    foreach($colsNecesarias as $col => $ddl) {
        if (!in_array($col, $presentes, true)) { $faltantes[$col] = $ddl; }
    }
    foreach($faltantes as $ddl) {
        $sql = "ALTER TABLE clima_horas $ddl";
        if(!$db->query($sql)) {
            error_log('No se pudo alterar clima_horas: ' . $db->error . ' SQL=' . $sql);
        }
    }
    $db->close();
}

/**
 * Guarda registro extendido (INSERT IGNORE).
 */
function clima_guardar_registro_extendido(array $row): bool {
    $db = conectar_bd();
    $stmt = $db->prepare('INSERT IGNORE INTO clima_horas (lat, lon, fecha_hora, temperatura_c, relative_humidity_pct, dew_point_c, precipitation_mm, cloud_cover_pct, wind_speed_ms, pressure_hpa, radiation_wm2) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param(
        'ddsdddddddd',
        $row['lat'],
        $row['lon'],
        $row['fecha_hora'],
        $row['temperatura_c'],
        $row['relative_humidity_pct'],
        $row['dew_point_c'],
        $row['precipitation_mm'],
        $row['cloud_cover_pct'],
        $row['wind_speed_ms'],
        $row['pressure_hpa'],
        $row['radiation_wm2']
    );
    $ok = $stmt->execute();
    if(!$ok) { error_log('Error insert clima extendido: ' . $stmt->error); }
    $stmt->close();
    $db->close();
    return $ok;
}

/**
 * Ingesta extendida desde Open-Meteo (histórico + forecast) con variables adicionales.
 */
function clima_ingestar_open_meteo_extendido(float $lat, float $lon, int $past_days = 120, int $forecast_days = 16, string $timezone = 'UTC'): int {
    clima_verificar_columnas_extendidas();
    $endpoint = 'https://api.open-meteo.com/v1/forecast';
    $hourlyVars = 'temperature_2m,relative_humidity_2m,dew_point_2m,shortwave_radiation,precipitation,cloud_cover,wind_speed_10m,pressure_msl';
    $query = http_build_query([
        'latitude' => $lat,
        'longitude' => $lon,
        'past_days' => $past_days,
        'forecast_days' => $forecast_days,
        'timezone' => $timezone,
        'hourly' => $hourlyVars
    ]);
    $url = $endpoint . '?' . $query;
    $raw = @file_get_contents($url);
    if ($raw === false) {
        error_log('Fallo al obtener Open-Meteo extendido');
        return 0;
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['hourly']['time'])) { return 0; }
    $times = $json['hourly']['time'];
    $temp = $json['hourly']['temperature_2m'] ?? [];
    $rh = $json['hourly']['relative_humidity_2m'] ?? [];
    $dew = $json['hourly']['dew_point_2m'] ?? [];
    $rad = $json['hourly']['shortwave_radiation'] ?? [];
    $ppt = $json['hourly']['precipitation'] ?? [];
    $cloud = $json['hourly']['cloud_cover'] ?? [];
    $wind = $json['hourly']['wind_speed_10m'] ?? [];
    $press = $json['hourly']['pressure_msl'] ?? [];

    $count = count($times);
    $insertados = 0;
    for ($i=0; $i < $count; $i++) {
        $row = [
            'lat' => $lat,
            'lon' => $lon,
            'fecha_hora' => str_replace('T',' ', $times[$i]) . ':00',
            'temperatura_c' => isset($temp[$i]) ? (float)$temp[$i] : null,
            'relative_humidity_pct' => isset($rh[$i]) ? (float)$rh[$i] : null,
            'dew_point_c' => isset($dew[$i]) ? (float)$dew[$i] : null,
            'precipitation_mm' => isset($ppt[$i]) ? (float)$ppt[$i] : null,
            'cloud_cover_pct' => isset($cloud[$i]) ? (float)$cloud[$i] : null,
            'wind_speed_ms' => isset($wind[$i]) ? (float)$wind[$i] : null,
            'pressure_hpa' => isset($press[$i]) ? (float)$press[$i] : null,
            'radiation_wm2' => isset($rad[$i]) ? (float)$rad[$i] : null,
        ];
        // Si falta temperatura no insertamos (clave para cálculos posteriores)
        if ($row['temperatura_c'] === null) { continue; }
        if (clima_guardar_registro_extendido($row)) { $insertados++; }
    }
    return $insertados;
}

function clima_ingestar_periodo(float $lat, float $lon, string $inicio, string $fin): int {
    $datos = obtener_historico_clima($lat, $lon, $inicio, $fin);
    $temps = $datos['hourly']['temperature_2m'] ?? [];
    $times = $datos['hourly']['time'] ?? [];
    $insertados = 0;
    for ($i=0; $i<count($temps); $i++) {
        if (clima_guardar_registro($lat, $lon, str_replace('T',' ', $times[$i]) . ':00', (float)$temps[$i])) {
            $insertados++;
        }
    }
    return $insertados;
}

function clima_calcular_acumulado(float $lat, float $lon, string $desde, string $hasta): int {
    $db = conectar_bd();
    $stmt = $db->prepare('SELECT temperatura_c FROM clima_horas WHERE lat = ? AND lon = ? AND fecha_hora BETWEEN ? AND ? ORDER BY fecha_hora');
    $stmt->bind_param('ddss', $lat, $lon, $desde, $hasta);
    $stmt->execute();
    $res = $stmt->get_result();
    $horas = 0;
    while ($row = $res->fetch_assoc()) {
        $t = (float)$row['temperatura_c'];
        if ($t > 0 && $t <= 7) { $horas++; }
    }
    $stmt->close();
    $db->close();
    return $horas;
}

/**
 * Heurística simplificada para Chill Portions.
 * NO equivalente al modelo dinámico oficial.
 * Retorna porciones estimadas y acumulador bruto para análisis.
 */
function clima_calcular_chill_portions_heuristica(float $lat, float $lon, string $desde, string $hasta): array {
    $db = conectar_bd();
    $stmt = $db->prepare('SELECT temperatura_c FROM clima_horas WHERE lat = ? AND lon = ? AND fecha_hora BETWEEN ? AND ? ORDER BY fecha_hora');
    $stmt->bind_param('ddss', $lat, $lon, $desde, $hasta);
    $stmt->execute();
    $res = $stmt->get_result();
    $acumulador = 0.0;
    $horas = 0;
    while ($row = $res->fetch_assoc()) {
        $t = (float)$row['temperatura_c'];
        $horas++;
        // Tabla de factores heurísticos
        if ($t < 0.5) { $factor = 0.0; }
        elseif ($t < 12.5) { $factor = 0.6; }
        elseif ($t < 16) { $factor = 0.2; }
        else { $factor = -0.4; }
        $acumulador += $factor;
    }
    $stmt->close();
    $db->close();
    // Escala para obtener "porciones" aproximadas
    $porciones = $acumulador > 0 ? round($acumulador / 0.7, 1) : 0.0;
    return [
        'porciones_estimadas' => $porciones,
        'acumulador_bruto' => round($acumulador,2),
        'horas_procesadas' => $horas,
        'nota' => 'Heurística; sustituir por modelo dinámico oficial para producción.'
    ];
}

function clima_guardar_acumulado(float $lat, float $lon, string $temporada, int $horas, string $fecha_corte, string $fuente='open-meteo'): bool {
    $db = conectar_bd();
    $stmt = $db->prepare('INSERT INTO horas_frio_acumuladas (lat, lon, temporada, horas_frio, fecha_corte, fuente) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE horas_frio = VALUES(horas_frio), fuente = VALUES(fuente)');
    $stmt->bind_param('ddsiss', $lat, $lon, $temporada, $horas, $fecha_corte, $fuente);
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();
    return $ok;
}

function clima_obtener_acumulado(float $lat, float $lon, string $temporada, string $fecha_corte): ?array {
    $db = conectar_bd();
    $stmt = $db->prepare('SELECT horas_frio FROM horas_frio_acumuladas WHERE lat = ? AND lon = ? AND temporada = ? AND fecha_corte = ? LIMIT 1');
    $stmt->bind_param('ddss', $lat, $lon, $temporada, $fecha_corte);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    $db->close();
    return $row;
}

?>