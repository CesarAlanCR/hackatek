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
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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
    $qCommon = sprintf('lon=%f&lat=%f&property=TAXNWRB&depth=0-5cm', $lon, $lat);

    // 1) Intentar probabilidades para obtener clase y confianza
    $urlProb = $base . '?' . $qCommon . '&value=probabilities';
    list($jsonProb, $codeProb, $errProb) = http_get_json($urlProb);

    $bestClass = null; $bestProb = null; $probList = [];
    if ($jsonProb && isset($jsonProb['properties']['TAXNWRB']['layers'][0]['depths'][0]['values'])) {
        $vals = $jsonProb['properties']['TAXNWRB']['layers'][0]['depths'][0]['values'];
        // values podría contener un objeto probabilities o pares clase=>prob
        // Intentamos detectar la estructura
        if (isset($vals['probabilities']) && is_array($vals['probabilities'])) {
            foreach ($vals['probabilities'] as $class => $prob) {
                $probList[$class] = $prob;
                if ($bestProb === null || $prob > $bestProb) { $bestProb = $prob; $bestClass = $class; }
            }
        } else {
            // Si no hay una clave explícita, asumimos que values ya es un mapa clase=>prob
            foreach ($vals as $class => $prob) {
                if (!is_numeric($prob)) continue;
                $probList[$class] = $prob;
                if ($bestProb === null || $prob > $bestProb) { $bestProb = $prob; $bestClass = $class; }
            }
        }
    }

    // 2) Fallback: mean (algunas veces puede traer etiqueta/código)
    $urlMean = $base . '?' . $qCommon . '&value=mean';
    list($jsonMean, $codeMean, $errMean) = http_get_json($urlMean);
    $meanVal = null;
    if ($jsonMean && isset($jsonMean['properties']['TAXNWRB']['layers'][0]['depths'][0]['values']['mean'])) {
        $meanVal = $jsonMean['properties']['TAXNWRB']['layers'][0]['depths'][0]['values']['mean'];
    }

    // Determinar tipo de suelo legible
    $soilType = null; $confidence = null;
    if ($bestClass !== null) {
        $soilType = $bestClass; // frecuentemente ya es nombre WRB p.ej. "Calcisols"; si fuera código, al menos devolvemos algo
        if (is_numeric($bestProb)) $confidence = round($bestProb * 100);
    } elseif (!empty($meanVal)) {
        $soilType = $meanVal; // podría ser la clase dominante
    }

    // Si no hay clase determinada, intentamos con vecinos (offsets pequeños)
    $usedNeighbor = null;
    if (!$soilType || $soilType === 'Desconocido') {
        // Aproximación: ~0.002 grados ~ 200m aprox (varía con latitud)
        $offsets = [
            [0.002, 0], [0, 0.002], [-0.002, 0], [0, -0.002],
            [0.002, 0.002], [0.002, -0.002], [-0.002, 0.002], [-0.002, -0.002]
        ];
        $bestNeighbor = null;
        foreach ($offsets as $off) {
            $nlat = $lat + $off[0];
            $nlon = $lon + $off[1];
            $qCommonN = sprintf('lon=%f&lat=%f&property=TAXNWRB&depth=0-5cm', $nlon, $nlat);
            $urlProbN = $base . '?' . $qCommonN . '&value=probabilities';
            list($jProbN) = http_get_json($urlProbN);
            $nbClass = null; $nbProb = null;
            if ($jProbN && isset($jProbN['properties']['TAXNWRB']['layers'][0]['depths'][0]['values'])) {
                $valsN = $jProbN['properties']['TAXNWRB']['layers'][0]['depths'][0]['values'];
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
        if ($bestNeighbor) {
            $soilType = $bestNeighbor['class'];
            $confidence = $bestNeighbor['prob'] !== null ? round($bestNeighbor['prob'] * 100) : null;
            $usedNeighbor = $bestNeighbor;
        }
    }

    echo json_encode([
        'soilType' => $soilType ?: 'Desconocido',
        'confidence' => $confidence,
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
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
