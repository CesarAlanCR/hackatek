<?php
// Proxy para consultar tipo de suelo desde INEGI México
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

$debug = [
    'input_coordinates' => ['lat' => $lat, 'lon' => $lon],
    'timestamp' => date('Y-m-d H:i:s'),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
];

if ($lat === null || $lon === null) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Parámetros lat/lon requeridos',
        'debug' => array_merge($debug, ['received_params' => $_GET])
    ]);
    exit;
}

// Verificar que esté dentro del territorio mexicano aproximadamente
if ($lat < 14.5 || $lat > 32.7 || $lon < -118.4 || $lon > -86.7) {
    echo json_encode([
        'soilType' => 'Fuera de cobertura',
        'description' => 'Esta ubicación está fuera del territorio mexicano. Los datos de INEGI solo cubren México.',
        'source' => 'INEGI',
        'debug' => array_merge($debug, [
            'validation' => [
                'lat_valid' => ($lat >= 14.5 && $lat <= 32.7),
                'lon_valid' => ($lon >= -118.4 && $lon <= -86.7),
                'bounds' => ['lat_min' => 14.5, 'lat_max' => 32.7, 'lon_min' => -118.4, 'lon_max' => -86.7]
            ]
        ])
    ]);
    exit;
}

try {
    // Servicio WMS de INEGI para edafología (suelos)
    // Intentar diferentes URLs y capas de INEGI
    $wmsServices = [
        [
            'url' => 'https://gaia.inegi.org.mx/NLB/mdm5/viewer/gwc/service/wms',
            'layer' => 'edafologia_1m',
            'name' => 'INEGI Gaia WMS'
        ],
        [
            'url' => 'https://gaia.inegi.org.mx/wms/edafologia',
            'layer' => 'edafologia',
            'name' => 'INEGI Edafología Directo'
        ],
        [
            'url' => 'https://gaia.inegi.org.mx/wms/topografico',
            'layer' => 'topografico',
            'name' => 'INEGI Topográfico'
        ]
    ];
    
    $lastError = null;
    $success = false;
    
    foreach ($wmsServices as $service) {
        $debug['trying_service'] = $service['name'];
        
        // Parámetros para GetFeatureInfo
        $params = [
            'SERVICE' => 'WMS',
            'VERSION' => '1.1.1', // Cambiar a versión más compatible
            'REQUEST' => 'GetFeatureInfo',
            'LAYERS' => $service['layer'],
            'QUERY_LAYERS' => $service['layer'],
            'SRS' => 'EPSG:4326', // En lugar de CRS para versión 1.1.1
            'BBOX' => ($lon-0.001) . ',' . ($lat-0.001) . ',' . ($lon+0.001) . ',' . ($lat+0.001), // lon,lat,lon,lat
            'WIDTH' => '101',
            'HEIGHT' => '101',
            'X' => '50', // X en lugar de I para versión 1.1.1
            'Y' => '50', // Y en lugar de J para versión 1.1.1
            'INFO_FORMAT' => 'application/json',
            'FEATURE_COUNT' => '1'
        ];
        
        $url = $service['url'] . '?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $debug['current_attempt'] = [
            'service' => $service['name'],
            'url' => $url,
            'params' => $params
        ];
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        
        $debug['attempts'][] = [
            'service' => $service['name'],
            'http_code' => $httpCode,
            'error' => $err,
            'response_length' => strlen($resp ?: ''),
            'success' => ($httpCode == 200 && $resp !== false)
        ];
        
        if ($resp !== false && $httpCode == 200) {
            $json = json_decode($resp, true);
            if ($json && isset($json['features'])) {
                // Éxito! Salir del bucle
                $success = true;
                $debug['successful_service'] = $service['name'];
                $debug['final_response'] = $json;
                break;
            }
        }
        
        $lastError = "HTTP $httpCode: $err";
    }
    
    if (!$success) {
        // Ningún servicio funcionó, usar fallback climático
        $soilType = getClimateSoilEstimate($lat, $lon);
        $debug['used_climate_fallback'] = true;
        $debug['fallback_reason'] = 'Todos los servicios WMS de INEGI no disponibles';
        
        echo json_encode([
            'soilType' => $soilType,
            'soilCode' => null,
            'description' => describeMexicanSoil($soilType),
            'source' => 'Estimación climática (INEGI no disponible)',
            'debug' => $debug
        ]);
        exit;
    }
    
    // Extraer información del suelo
    $soilType = 'Desconocido';
    $soilCode = null;
    
    if (isset($json['features']) && count($json['features']) > 0) {
        $feature = $json['features'][0];
        $props = $feature['properties'] ?? [];
        
        // Buscar campos relevantes (pueden variar según la capa y servicio)
        $possibleFields = ['TIPO_SUELO', 'SUELO', 'EDAFOLOGIA', 'CVE_SUELO', 'SOIL_TYPE', 'SOIL', 'tipo', 'suelo'];
        
        foreach ($possibleFields as $field) {
            if (!empty($props[$field])) {
                $soilCode = $props[$field];
                break;
            }
        }
        
        if ($soilCode) {
            $soilType = mapINEGISoilCode($soilCode);
        }
        
        $debug['soil_extraction'] = [
            'found_code' => $soilCode,
            'mapped_type' => $soilType,
            'available_fields' => array_keys($props),
            'properties_sample' => array_slice($props, 0, 5, true) // Muestra las primeras 5 propiedades
        ];
    }
    
    // Si no encontramos suelo específico, intentar fallback climático básico
    if ($soilType === 'Desconocido' || !$soilCode) {
        $soilType = getClimateSoilEstimate($lat, $lon);
        $debug['used_climate_fallback'] = true;
    }
    
    echo json_encode([
        'soilType' => $soilType,
        'soilCode' => $soilCode,
        'description' => describeMexicanSoil($soilType),
        'source' => 'INEGI' . ($debug['used_climate_fallback'] ?? false ? ' + Estimación climática' : ''),
        'debug' => $debug
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'soilType' => 'Error del sistema',
        'description' => 'Ocurrió un error interno al procesar la consulta.',
        'source' => 'INEGI',
        'debug' => array_merge($debug, [
            'exception' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 3) // Solo las primeras 3 líneas del stack trace
            ]
        ])
    ]);
}

// Estimación básica de suelo basada en ubicación geográfica (fallback)
function getClimateSoilEstimate($lat, $lon) {
    // Estimaciones mejoradas para diferentes regiones de México
    
    // Desierto de Sonora (noroeste árido)
    if ($lat > 27 && $lat < 32 && $lon < -108 && $lon > -116) {
        return 'Arenosol'; // Suelos arenosos típicos del desierto de Sonora
    }
    
    // Norte árido/semiárido general (Chihuahua, Coahuila)
    if ($lat > 28) {
        return ($lon < -106) ? 'Calcisol' : 'Regosol';
    }
    
    // Península de Yucatán (suelos calcáreos)
    if ($lat < 22 && $lon > -92) {
        return 'Calcisol';
    }
    
    // Costa del Golfo (suelos aluviales)
    if ($lon > -98 && $lat < 26) {
        return 'Fluvisol';
    }
    
    // Eje Neovolcánico (suelos volcánicos)
    if ($lat > 18 && $lat < 22 && $lon > -104 && $lon < -96) {
        return 'Andosol';
    }
    
    // Sur tropical húmedo
    if ($lat < 20) {
        return 'Cambisol';
    }
    
    // Centro/altiplano
    return 'Phaeozem';
}

// Mapear códigos de suelo INEGI a nombres descriptivos
function mapINEGISoilCode($code) {
    if (!$code) return 'Desconocido';
    
    $codes = [
        'AC' => 'Acrisol',
        'AL' => 'Alisol', 
        'AN' => 'Andosol',
        'AR' => 'Arenosol',
        'CH' => 'Chernozem',
        'CM' => 'Cambisol',
        'CP' => 'Calcisol',
        'FL' => 'Fluvisol',
        'FO' => 'Foeozen',
        'GL' => 'Gleysol',
        'GY' => 'Gypsisol',
        'KS' => 'Kastañozem',
        'LP' => 'Leptosol',
        'LV' => 'Luvisol',
        'NT' => 'Nitisol',
        'PH' => 'Phaeozem',
        'PL' => 'Planosol',
        'RG' => 'Regosol',
        'SC' => 'Solonchak',
        'SN' => 'Solonetz',
        'UM' => 'Umbrisol',
        'VR' => 'Vertisol'
    ];
    
    $codeUpper = strtoupper(trim($code));
    return $codes[$codeUpper] ?? $code;
}

// Describir tipos de suelo mexicanos
function describeMexicanSoil($type) {
    $descriptions = [
        'Acrisol' => 'Suelos ácidos y lixiviados, comunes en zonas tropicales húmedas. Requieren encalado y fertilización para mejorar su productividad.',
        'Andosol' => 'Suelos derivados de cenizas volcánicas, muy porosos y fértiles. Comunes en zonas volcánicas de México como el Eje Neovolcánico.',
        'Arenosol' => 'Suelos arenosos con excelente drenaje pero baja retención de agua y nutrientes. Requieren riego frecuente y fertilización.',
        'Cambisol' => 'Suelos jóvenes en desarrollo, generalmente bien drenados y de fertilidad moderada. Buenos para agricultura con manejo adecuado.',
        'Chernozem' => 'Suelos muy fértiles y ricos en materia orgánica, de color oscuro. Excelentes para agricultura, raros en México.',
        'Fluvisol' => 'Suelos aluviales en llanuras de inundación y márgenes de ríos. Fertilidad variable, buenos para agricultura con control de inundaciones.',
        'Gleysol' => 'Suelos con problemas de drenaje y encharcamiento. Requieren sistemas de drenaje para uso agrícola.',
        'Kastañozem' => 'Suelos de zonas semiáridas, ricos en bases y materia orgánica. Buenos para ganadería y agricultura de temporal.',
        'Leptosol' => 'Suelos someros sobre roca o tepetate. Limitaciones severas para agricultura por poca profundidad.',
        'Luvisol' => 'Suelos con acumulación de arcillas, fertilidad moderada a buena. Susceptibles a erosión en pendientes.',
        'Phaeozem' => 'Suelos oscuros y fértiles, similares a Chernozems pero en climas más húmedos. Muy buenos para agricultura.',
        'Regosol' => 'Suelos poco desarrollados, a menudo en zonas áridas. Baja fertilidad, requieren manejo cuidadoso.',
        'Vertisol' => 'Suelos arcillosos que se agrietan en seco y se expanden húmedos. Fértiles pero difíciles de manejar.',
        'Calcisol' => 'Suelos con acumulación de carbonatos, típicos de zonas áridas. Problemas de pH alto y disponibilidad de nutrientes.',
        'Solonchak' => 'Suelos salinos, comunes en zonas costeras y áridas. Requieren manejo especial de salinidad.',
        'Fuera de cobertura' => 'Esta ubicación está fuera del territorio mexicano cubierto por INEGI.'
    ];
    
    return $descriptions[$type] ?? 'Tipo de suelo sin descripción disponible. Consulte análisis local para recomendaciones específicas.';
}
?>