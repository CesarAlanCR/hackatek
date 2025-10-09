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