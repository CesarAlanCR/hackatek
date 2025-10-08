<?php
// includes/data_fetcher.php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$api_keys = [
    'tipo_cambio' => 'b83b87a5e0b6cdd0099692fb',
    'usda' => '8m8aRV27swLw9wgwEulkxbWnsmxKDz1mCKyhUwdW',
    'rutas' => 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjdhOGExZGNkYjc0MDQ4NGRhZGNiNjIxZjg0MWYyMDY5IiwiaCI6Im11cm11cjY0In0=',
    // URL opcional para obtener costo de aduana por camión (MXN) desde un servicio propio
    // Debe devolver JSON con una de estas claves numéricas: costo_aduana_mxn, aduana_mxn
    'aduana_url' => '',
    // Fallback fijo (MXN por camión) si no hay servicio
    'aduana_fija' => 5000,
];

/**
 * Detecta la primera columna existente en una tabla a partir de una lista de candidatas.
 */
function detectar_columna(mysqli $mysqli, string $tabla, array $candidatas): ?string {
    $cols = [];
    $res = $mysqli->query("SHOW COLUMNS FROM `" . $mysqli->real_escape_string($tabla) . "`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[strtolower($row['Field'])] = $row['Field'];
        }
        $res->free();
    }
    foreach ($candidatas as $cand) {
        $key = strtolower($cand);
        if (isset($cols[$key])) {
            return $cols[$key];
        }
    }
    return null;
}

function llamar_api_get(string $url, array $headers = [], int $timeout = 15): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'User-Agent: OptiLife/1.0'
        ], $headers),
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false || $http < 200 || $http >= 300) {
        return [];
    }
    $data = json_decode((string)$response, true);
    return is_array($data) ? $data : [];
}

function llamar_api_post(string $url, array $payload, array $headers = [], int $timeout = 20): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: OptiLife/1.0',
        ], $headers),
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $errstr = $errno ? curl_error($ch) : '';
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false || $http < 200 || $http >= 300) {
        // Devolver detalles de error para depuración aguas arriba
        $decoded = json_decode((string)$response, true);
        if (is_array($decoded)) {
            $decoded['_http'] = $http;
            $decoded['_curl_errno'] = $errno;
            if ($errstr) { $decoded['_curl_error'] = $errstr; }
            return $decoded;
        }
        return [
            '_error' => true,
            '_http' => $http,
            '_curl_errno' => $errno,
            '_curl_error' => $errstr,
            '_raw' => (string) $response,
        ];
    }
    $data = json_decode((string)$response, true);
    return is_array($data) ? $data : [];
}

/**
 * Fallback: distancia Haversine en km entre dos puntos [lon, lat].
 */
function calcular_distancia_haversine_km(array $origen, array $destino): float {
    [$lon1, $lat1] = $origen;
    [$lon2, $lat2] = $destino;
    $R = 6371.0088; // Radio medio de la Tierra en km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

function obtener_tipo_cambio(string $api_key): float {
    $url = sprintf('https://v6.exchangerate-api.com/v6/%s/latest/USD', urlencode($api_key));
    $fallback = 17.55;
    $data = llamar_api_get($url);
    $mxn = $data['conversion_rates']['MXN'] ?? null;
    return is_numeric($mxn) ? (float)$mxn : $fallback;
}

/**
 * Intenta obtener el costo de aduana por camión (MXN) desde una API configurable.
 * Si no hay URL configurada o la respuesta es inválida, regresa un valor por defecto.
 * Devuelve arreglo con: valor (float) y fuente ('api'|'default').
 */
function obtener_costo_aduana_por_camion(): array {
    global $api_keys;
    $fallback = isset($api_keys['aduana_fija']) ? (float)$api_keys['aduana_fija'] : 5000.0;
    $url = isset($api_keys['aduana_url']) ? trim((string)$api_keys['aduana_url']) : '';

    if ($url !== '') {
        $data = llamar_api_get($url);
        if (is_array($data) && !empty($data)) {
            // Intentar varias formas comunes
            $valor = null;
            if (isset($data['costo_aduana_mxn']) && is_numeric($data['costo_aduana_mxn'])) {
                $valor = (float)$data['costo_aduana_mxn'];
            } elseif (isset($data['aduana_mxn']) && is_numeric($data['aduana_mxn'])) {
                $valor = (float)$data['aduana_mxn'];
            } elseif (isset($data['data']['costo_aduana_mxn']) && is_numeric($data['data']['costo_aduana_mxn'])) {
                $valor = (float)$data['data']['costo_aduana_mxn'];
            }
            if (is_numeric($valor) && $valor >= 0) {
                return ['valor' => $valor, 'fuente' => 'api'];
            }
        }
    }
    return ['valor' => $fallback, 'fuente' => 'default'];
}

function obtener_precios_usda(string $api_key): array {
    // TODO: Integrar API real del USDA utilizando $api_key.
    // Simulación extendida con varios mercados (USD/caja)
    return [
        'manzana_gala' => [
            ['mercado' => 'Dallas, TX', 'precio_usd_por_caja' => 28.50],
            ['mercado' => 'Los Angeles, CA', 'precio_usd_por_caja' => 29.10],
            ['mercado' => 'Houston, TX', 'precio_usd_por_caja' => 27.80],
            ['mercado' => 'Phoenix, AZ', 'precio_usd_por_caja' => 27.50],
            ['mercado' => 'San Diego, CA', 'precio_usd_por_caja' => 28.90],
            ['mercado' => 'El Paso, TX', 'precio_usd_por_caja' => 28.20],
        ]
    ];
}

function obtener_precios_sniim(): array {
    $mysqli = conectar_bd();

    $tabla = 'registros_agricolas_completos';

    // Detectar columnas posibles
    $colPrecio = detectar_columna($mysqli, $tabla, [
        'precio_tonelada', 'precio_mxn_por_tonelada', 'precio_por_tonelada',
        'precio', 'precio_ton', 'precio_ton_mxn', 'precio_promedio', 'precio_productor'
    ]);
    if (!$colPrecio) {
        $mysqli->close();
        return [];
    }

    $colMunicipio = detectar_columna($mysqli, $tabla, ['municipio', 'municipio_nombre', 'municipio_desc', 'localidad', 'ciudad']);
    $colFecha = detectar_columna($mysqli, $tabla, ['fecha', 'fecha_registro', 'fecha_captura', 'created_at', 'updated_at', 'periodo']);
    $colCultivo = detectar_columna($mysqli, $tabla, ['cultivo', 'producto', 'cultivo_nombre']);
    $colEstado = detectar_columna($mysqli, $tabla, ['estado', 'entidad', 'estado_nombre']);
    $colId = detectar_columna($mysqli, $tabla, ['id', 'pk', 'id_registro']);

    $select = [];
    if ($colMunicipio) { $select[] = "`$colMunicipio` AS municipio"; }
    $select[] = "`$colPrecio` AS precio_valor";
    $selectList = implode(', ', $select);

    $where = [];
    $params = [];
    $types = '';
    // Nota: Verificar que los nombres de columnas en tu tabla coincidan (por ejemplo, 'producto' y 'entidad')
    // y que los valores correspondan (por ejemplo, que 'producto' contenga la cadena 'maíz grano').
    if ($colCultivo) {
        $where[] = "(`$colCultivo` COLLATE utf8mb4_general_ci LIKE ? OR `$colCultivo` COLLATE utf8mb4_general_ci LIKE ?)";
        $params[] = '%maíz grano%';
        $params[] = '%maiz grano%';
        $types .= 'ss';
    }
    if ($colEstado) {
        $where[] = "`$colEstado` COLLATE utf8mb4_general_ci LIKE ?";
        $params[] = 'Chihuahua%';
        $types .= 's';
    }
    if (empty($where)) { $where[] = '1=1'; }

    $order = '';
    if ($colFecha) { $order = " ORDER BY `$colFecha` DESC"; }
    elseif ($colId) { $order = " ORDER BY `$colId` DESC"; }

    $sql = "SELECT $selectList FROM `$tabla` WHERE " . implode(' AND ', $where) . $order . " LIMIT 1";
    if (!empty($_GET['debug'])) {
        var_dump(['sql'=>$sql,'types'=>$types,'params'=>$params]);
    }

    $fila = null;
    if (!empty($params)) {
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $fila = $res ? $res->fetch_assoc() : null;
            }
            $stmt->close();
        }
    } else {
        $res = $mysqli->query($sql);
        $fila = $res ? $res->fetch_assoc() : null;
    }

    $mysqli->close();

    if (!$fila) {
        return [];
    }

    return [
        'cultivo' => 'Cultivo de maíz grano',
        'estado' => 'Chihuahua',
        'municipio' => $fila['municipio'] ?? null,
        'precio_mxn_por_tonelada' => isset($fila['precio_valor']) ? (float)$fila['precio_valor'] : 0.0,
    ];
}

/**
 * Obtiene los mejores precios recientes por estado (nacional) desde la BD.
 * Devuelve hasta $limit entradas con la mejor observación por estado.
 */
function obtener_precios_sniim_top(int $limit = 4): array {
    $mysqli = conectar_bd();
    $tabla = 'registros_agricolas_completos';

    // Detectar columnas
    $colPrecio = detectar_columna($mysqli, $tabla, [
        'precio_tonelada', 'precio_mxn_por_tonelada', 'precio_por_tonelada',
        'precio', 'precio_ton', 'precio_ton_mxn', 'precio_promedio', 'precio_productor'
    ]);
    $colMunicipio = detectar_columna($mysqli, $tabla, ['municipio', 'municipio_nombre', 'municipio_desc', 'localidad', 'ciudad']);
    $colFecha = detectar_columna($mysqli, $tabla, ['fecha', 'fecha_registro', 'fecha_captura', 'created_at', 'updated_at', 'periodo']);
    $colCultivo = detectar_columna($mysqli, $tabla, ['cultivo', 'producto', 'cultivo_nombre']);
    $colEstado = detectar_columna($mysqli, $tabla, ['estado', 'entidad', 'estado_nombre']);

    if (!$colPrecio || !$colEstado) {
        $mysqli->close();
        return [];
    }

    $select = ["`$colEstado` AS estado", "`$colPrecio` AS precio_valor"];
    if ($colMunicipio) { $select[] = "`$colMunicipio` AS municipio"; }
    $selectList = implode(', ', $select);

    // Filtros flexibles por cultivo y estado (no restringimos estado para obtener varios)
    $where = [];
    $params = [];
    $types = '';
    if ($colCultivo) {
        $where[] = "(`$colCultivo` COLLATE utf8mb4_general_ci LIKE ? OR `$colCultivo` COLLATE utf8mb4_general_ci LIKE ?)";
        $params[] = '%maíz grano%';
        $params[] = '%maiz grano%';
        $types .= 'ss';
    }
    if (empty($where)) { $where[] = '1=1'; }

    $order = '';
    if ($colFecha) { $order = " ORDER BY `$colFecha` DESC"; }

    // Tomar suficientes filas recientes y consolidar por estado en PHP
    $sql = "SELECT $selectList FROM `$tabla` WHERE " . implode(' AND ', $where) . $order . " LIMIT 500";

    if (!empty($_GET['debug'])) {
        var_dump(['sql_top'=>$sql,'types'=>$types,'params'=>$params]);
    }

    $rows = [];
    if (!empty($params)) {
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($res && ($fila = $res->fetch_assoc())) {
                    $rows[] = $fila;
                }
            }
            $stmt->close();
        }
    } else {
        $res = $mysqli->query($sql);
        while ($res && ($fila = $res->fetch_assoc())) {
            $rows[] = $fila;
        }
    }
    $mysqli->close();

    // Consolidar: mejor precio por estado
    $mejoresPorEstado = [];
    foreach ($rows as $r) {
        $estado = $r['estado'] ?? null;
        $precio = isset($r['precio_valor']) ? (float)$r['precio_valor'] : null;
        if (!$estado || !is_numeric($precio)) continue;
        if (!isset($mejoresPorEstado[$estado]) || $precio > $mejoresPorEstado[$estado]['precio_mxn_por_tonelada']) {
            $mejoresPorEstado[$estado] = [
                'estado' => $estado,
                'municipio' => $r['municipio'] ?? null,
                'precio_mxn_por_tonelada' => $precio,
            ];
        }
    }

    // Ordenar por precio desc y tomar top N
    usort($mejoresPorEstado, function($a, $b){
        return ($b['precio_mxn_por_tonelada'] <=> $a['precio_mxn_por_tonelada']);
    });

    return array_slice(array_values($mejoresPorEstado), 0, max(1, $limit));
}

function obtener_datos_frontera(): array {
    $url = 'https://bwt.cbp.gov/api/rss';
    $xmlStr = @file_get_contents($url);
    if ($xmlStr === false) {
        return [];
    }
    $xml = @simplexml_load_string($xmlStr);
    if ($xml === false) {
        return [];
    }

    $esperaMin = null;
    foreach ($xml->channel->item as $item) {
        $title = (string) $item->title;
        if (stripos($title, 'El Paso') !== false) {
            $desc = (string) $item->description; // Suele contener "Commercial Vehicles: XX Minutes"
            if (preg_match('/Commercial Vehicles:\s*(\d+)\s*Minutes/i', $desc, $m)) {
                $esperaMin = (int) $m[1];
                break;
            }
        }
    }

    return [
        'puerto' => 'El Paso',
        'espera_minutos_comercial' => $esperaMin ?? 0,
    ];
}

function obtener_datos_logistica(string $api_key, array $coords_destino): float {
    // Origen: Cuauhtémoc, Chihuahua
    $origen = [-106.8654, 28.4063];

    $url = 'https://api.openrouteservice.org/v2/directions/driving-truck';
    $payload = [
        'coordinates' => [
            $origen,
            $coords_destino,
        ],
        'units' => 'km',
    ];
    $headers = [
        'Authorization: ' . $api_key,
        'Accept: application/json, application/geo+json, application/gpx+xml, img/png; charset=utf-8',
    ];

    $respuesta = llamar_api_post($url, $payload, $headers);

    $parsedKm = null;
    // Intento 1: formato routes[0].summary.distance (en metros)
    $distanceMeters = $respuesta['routes'][0]['summary']['distance'] ?? null;
    if (is_numeric($distanceMeters)) {
        $parsedKm = ((float)$distanceMeters) / 1000;
    }
    // Intento 2: formato features[0].properties.segments[0].distance (en km)
    if (!is_numeric($parsedKm)) {
        $distanceKm = $respuesta['features'][0]['properties']['segments'][0]['distance'] ?? null;
        if (is_numeric($distanceKm)) {
            $parsedKm = (float)$distanceKm;
        }
    }

    // Si no hay valor o es <= 0, usar fallback (y opcionalmente mostrar respuesta cruda)
    if (!is_numeric($parsedKm) || $parsedKm <= 0) {
        if (!empty($_GET['debug'])) {
            var_dump($respuesta);
        }
        $parsedKm = calcular_distancia_haversine_km($origen, $coords_destino);
    }

    return round((float)$parsedKm, 3);
}
