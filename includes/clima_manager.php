<?php
// includes/clima_manager.php
// Módulo: Inteligencia Agronómica (clima)

declare(strict_types=1);

function obtener_historico_clima(float $lat, float $lon, string $fecha_inicio, string $fecha_fin): array {
    // Caché simple por 30 min en /tmp (o directorio actual si no existe tmp en Windows).
    $cacheDir = sys_get_temp_dir();
    $clave = md5($lat.'|'.$lon.'|'.$fecha_inicio.'|'.$fecha_fin);
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'clima_cache_' . $clave . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 1800)) {
        $contenido = file_get_contents($cacheFile);
        if ($contenido !== false) {
            $json = json_decode($contenido, true);
            if (is_array($json)) return $json;
        }
    }
    $url = "https://archive-api.open-meteo.com/v1/archive?latitude={$lat}&longitude={$lon}&start_date={$fecha_inicio}&end_date={$fecha_fin}&hourly=temperature_2m";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    $output = curl_exec($ch);
    if ($output === false) {
        error_log('Error cURL clima: ' . curl_error($ch));
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    $data = json_decode($output, true);
    if (is_array($data)) {
        @file_put_contents($cacheFile, json_encode($data));
    }
    return is_array($data) ? $data : [];
}

function calcular_horas_frio(array $datos_clima): int {
    $horas_frio = 0;
    if (isset($datos_clima['hourly']['temperature_2m']) && is_array($datos_clima['hourly']['temperature_2m'])) {
        foreach ($datos_clima['hourly']['temperature_2m'] as $temperatura) {
            if ($temperatura > 0 && $temperatura <= 7) {
                $horas_frio++;
            }
        }
    }
    return $horas_frio;
}
