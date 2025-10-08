<?php
// Proxy para SoilGrids v2 - devuelve tipo de suelo (WRB) y confianza cerca del punto dado.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

if ($lat === null || $lon === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros lat/lon requeridos']);
    exit;
}

function http_get_json($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $httpCode >= 400) {
        return [null, $httpCode, $err];
    }
    $json = json_decode($resp, true);
    if (!$json) {
        return [null, $httpCode ?: 500, 'JSON inválido'];
    }
    return [$json, $httpCode, null];
}

try {
    $base = 'https://rest.isric.org/soilgrids/v2.0/properties/query';

    // Intentar varias propiedades y profundidades comunes para mejorar cobertura
    $properties = ['TAXNWRB', 'WRB', 'TAXN'];
    $depths = ['0-5cm', '5-15cm', '15-30cm'];

    $bestClass = null; $bestProb = null; $probList = [];
    $jsonProb = null; $jsonMean = null; $urlProb = null; $urlMean = null;

    // Try faster: mean first (generally lighter), then probabilities only if mean is absent
    $cacheDir = sys_get_temp_dir() . '/soilgrids_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheKey = sprintf('%s_%s', round($lat,4), round($lon,4));
    $cacheFile = $cacheDir . '/' . preg_replace('/[^a-z0-9_.-]/i','_', $cacheKey) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            echo json_encode($cached);
            exit;
        }
    }

    foreach ($properties as $prop) {
        foreach ($depths as $dpth) {
            $qCommon = sprintf('lon=%f&lat=%f&property=%s&depth=%s', $lon, $lat, $prop, $dpth);
            // mean first
            $tryUrlMean = $base . '?' . $qCommon . '&value=mean';
            list($jMean, $codeMean, $errMean) = http_get_json($tryUrlMean);
            if ($jMean && isset($jMean['properties'][$prop]['layers'][0]['depths'][0]['values']['mean'])) {
                $jsonMean = $jMean; $urlMean = $tryUrlMean;
                $meanVal = $jMean['properties'][$prop]['layers'][0]['depths'][0]['values']['mean'];
                if ($meanVal) { $bestClass = $meanVal; break; }
            }
            // if no mean, try probabilities (more pesado)
            $tryUrlProb = $base . '?' . $qCommon . '&value=probabilities';
            list($jProb, $codeProb, $errProb) = http_get_json($tryUrlProb);
            if ($jProb && isset($jProb['properties'][$prop]['layers'][0]['depths'][0]['values'])) {
                $vals = $jProb['properties'][$prop]['layers'][0]['depths'][0]['values'];
                if (isset($vals['probabilities']) && is_array($vals['probabilities'])) {
                    foreach ($vals['probabilities'] as $class => $prob) {
                        $probList[$class] = $prob;
                        if ($bestProb === null || $prob > $bestProb) { $bestProb = $prob; $bestClass = $class; $jsonProb = $jProb; $urlProb = $tryUrlProb; }
                    }
                } else {
                    foreach ($vals as $class => $prob) {
                        if (!is_numeric($prob)) continue;
                        $probList[$class] = $prob;
                        if ($bestProb === null || $prob > $bestProb) { $bestProb = $prob; $bestClass = $class; $jsonProb = $jProb; $urlProb = $tryUrlProb; }
                    }
                }
            }
            if ($bestClass) break;
        }
        if ($bestClass) break;
    }

    // Determinar tipo de suelo legible
    $soilType = null; $confidence = null;
    if ($bestClass !== null) {
        $soilType = $bestClass; // frecuentemente ya es nombre WRB p.ej. "Calcisols"; si fuera código, al menos devolvemos algo
        if (is_numeric($bestProb)) $confidence = round($bestProb * 100);
    } elseif (!empty($meanVal)) {
        $soilType = $meanVal; // podría ser la clase dominante
    }

    // Si no hay clase determinada, intentamos con vecinos en varios radios y offsets
    $usedNeighbor = null;
    if (!$bestClass) {
        $radios = [0.0015, 0.003, 0.006]; // ~150m, 300m, 600m aprox en grados
        $bestNeighbor = null;
        foreach ($radios as $r) {
            // generar 16 offsets alrededor
            $steps = 8; // reducir para mejorar velocidad
            for ($i = 0; $i < $steps; $i++) {
                $angle = 2 * M_PI * $i / $steps;
                $nlat = $lat + $r * sin($angle);
                $nlon = $lon + $r * cos($angle);
                foreach ($properties as $prop) {
                    foreach ($depths as $dpth) {
                        $qCommonN = sprintf('lon=%f&lat=%f&property=%s&depth=%s', $nlon, $nlat, $prop, $dpth);
                        $urlProbN = $base . '?' . $qCommonN . '&value=probabilities';
                        list($jProbN, $codeN, $errN) = http_get_json($urlProbN);
                        $nbClass = null; $nbProb = null;
                        if ($jProbN && isset($jProbN['properties'][$prop]['layers'][0]['depths'][0]['values'])) {
                            $valsN = $jProbN['properties'][$prop]['layers'][0]['depths'][0]['values'];
                            if (isset($valsN['probabilities']) && is_array($valsN['probabilities'])) {
                                foreach ($valsN['probabilities'] as $class => $prob) {
                                    if ($nbProb === null || $prob > $nbProb) { $nbProb = $prob; $nbClass = $class; }
                                }
                            } else {
                                foreach ($valsN as $class => $prob) {
                                    if (!is_numeric($prob)) continue;
                                    if ($nbProb === null || $prob > $nbProb) { $nbProb = $prob; $nbClass = $class; }
                                }
                            }
                        }
                        if ($nbClass) {
                            if (!$bestNeighbor || ($nbProb !== null && $nbProb > $bestNeighbor['prob'])) {
                                $bestNeighbor = ['class' => $nbClass, 'prob' => $nbProb, 'lat' => $nlat, 'lon' => $nlon, 'url' => $urlProbN];
                            }
                        }
                    }
                }
            }
            if ($bestNeighbor) break;
        }
        if ($bestNeighbor) {
            $bestClass = $bestNeighbor['class'];
            $bestProb = $bestNeighbor['prob'];
            $usedNeighbor = $bestNeighbor;
        }
    }

        $result = [
        'soilType' => $bestClass ?: 'Desconocido',
        'confidence' => $bestProb !== null ? round($bestProb * 100) : null,
        'source' => 'SoilGrids',
        'debug' => [
            'urlProb' => $urlProb,
            'urlMean' => $urlMean,
            'topProb' => $bestProb,
            'topClass' => $bestClass,
            'neighborUsed' => $usedNeighbor,
        ],
        'raw' => [
            'prob' => $jsonProb,
            'mean' => $jsonMean,
        ]
        ];

        // Cache result
        if ($cacheFile) {
            file_put_contents($cacheFile, json_encode($result));
        }

        echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
