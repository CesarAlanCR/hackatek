<?php
// includes/market_analyzer.php

declare(strict_types=1);

require_once __DIR__ . '/data_fetcher.php';

function analizar_mercado_completo(): array {
    // Lectura y validación server-side de parámetros
    // Inicializar en 0; solo se usan tras pasar validación
    $COSTO_POR_KM_MXN = 0.0;             // Costo por km por camión
    $COSTO_ADUANA_MXN = 0.0;             // Costo por cruce por camión
    $COSTO_HORA_ESPERA_MXN = 0.0;        // Costo por hora de espera por camión
    $CAPACIDAD_TON_CAMION = 0.0;         // Toneladas por camión (requiere > 0)

    $errors = [];
    $getf = static function(string $k): ?float { return isset($_GET[$k]) && $_GET[$k] !== '' ? (float)$_GET[$k] : null; };
    $cap = $getf('capacidad_ton');
    $costoKm = $getf('costo_km');
    $costoAdu = $getf('costo_aduana');
    $costoEsp = $getf('costo_espera_hora');
    $cajasPorTon = $getf('cajas_por_ton');

    // Reglas de negocio (coherentes con la validación client-side)
    if ($cap === null || !($cap > 0) || $cap > 40) {
        $errors[] = 'Capacidad de camión debe ser > 0 y ≤ 40 toneladas.';
    } else {
        $CAPACIDAD_TON_CAMION = $cap;
    }
    if ($cajasPorTon === null || !($cajasPorTon > 0) || $cajasPorTon > 200) {
        $errors[] = 'Cajas por tonelada debe ser > 0 y ≤ 200.';
    }
    if ($costoKm !== null) {
        if ($costoKm < 0 || $costoKm > 200) { $errors[] = 'Costo por km debe estar entre 0 y 200 MXN.'; }
        else { $COSTO_POR_KM_MXN = $costoKm; }
    }
    if ($costoAdu !== null) {
        if ($costoAdu < 0 || $costoAdu > 100000) { $errors[] = 'Costo de aduana debe estar entre 0 y 100,000 MXN.'; }
        else { $COSTO_ADUANA_MXN = $costoAdu; }
    }
    if ($costoEsp !== null) {
        if ($costoEsp < 0 || $costoEsp > 10000) { $errors[] = 'Costo hora de espera debe estar entre 0 y 10,000 MXN.'; }
        else { $COSTO_HORA_ESPERA_MXN = $costoEsp; }
    }

    if (!empty($errors)) {
        return [
            'mejor_opcion' => null,
            'top4' => [],
            'todas_las_opciones' => [],
            'agrupado' => [],
            'recomendacion' => 'Corrige los parámetros: ' . implode(' ', $errors),
            'fuentes' => [],
            'timestamp' => date('c'),
            'notas' => [
                'costo_aduana' => 'El costo de aduana representa trámites y aranceles por cruce internacional. Este valor lo ingresa el usuario y se prorratea por tonelada según la capacidad del camión.',
                'cajas_por_ton' => 'Debes ingresar una conversión válida de cajas por tonelada para convertir USD/caja a MXN/ton.',
            ],
        ];
    }

    global $api_keys;

    // Obtener datos
    $tipoCambio = obtener_tipo_cambio($api_keys['tipo_cambio']); // USD->MXN
    $preciosUSDA = obtener_precios_usda($api_keys['usda']); // array de mercados USD/caja
    $precioSNIIM = obtener_precios_sniim(); // precio nacional (MXN/tonelada) específico (Chihuahua)
    $preciosNacionalesTop = obtener_precios_sniim_top(8); // mejores por estado
    $frontera = obtener_datos_frontera();
    $esperaMinutos = (int) ($frontera['espera_minutos_comercial'] ?? 0);
    $costoEspera = ($esperaMinutos / 60.0) * $COSTO_HORA_ESPERA_MXN;

    $opciones = [];

    // 1) Opciones de exportación: evaluar cada mercado USDA disponible
    if (!empty($preciosUSDA['manzana_gala']) && is_array($preciosUSDA['manzana_gala'])) {
        foreach ($preciosUSDA['manzana_gala'] as $entry) {
            $mercado = $entry['mercado'] ?? 'Desconocido';
            $precioUsdCaja = (float) ($entry['precio_usd_por_caja'] ?? 0.0);

            // Intentamos obtener coordenadas aproximadas para cálculo de flete (ejemplos simples)
            $coords = null;
            if (stripos($mercado, 'Dallas') !== false) { $coords = [-96.7970, 32.7767]; }
            if (stripos($mercado, 'Los Angeles') !== false) { $coords = [-118.2437, 34.0522]; }
            if (stripos($mercado, 'Houston') !== false) { $coords = [-95.3698, 29.7604]; }
            if (stripos($mercado, 'Phoenix') !== false) { $coords = [-112.0740, 33.4484]; }
            if (stripos($mercado, 'San Diego') !== false) { $coords = [-117.1611, 32.7157]; }
            if (stripos($mercado, 'El Paso') !== false) { $coords = [-106.4850, 31.7619]; }
            // Si no hay coords, sólo cálculo del ingreso y marcar flete como 0

            $distKm = $coords ? obtener_datos_logistica($api_keys['rutas'], $coords) : 0.0;
            // Costos por tonelada (capacidad validada > 0 en server-side)
            $costoFlete = ($distKm * $COSTO_POR_KM_MXN) / $CAPACIDAD_TON_CAMION;
            $aduana = $COSTO_ADUANA_MXN / $CAPACIDAD_TON_CAMION;
            $espera = $costoEspera / $CAPACIDAD_TON_CAMION;

            $ingresoBruto = $precioUsdCaja * $cajasPorTon * $tipoCambio; // MXN/ton
            $costosTotales = $costoFlete + $aduana + $espera;
            $gananciaNeta = $ingresoBruto - $costosTotales;

            $opciones[] = [
                'modo' => 'exportacion',
                'mercado' => $mercado,
                'pais' => 'EE.UU.',
                'precio_usd_por_caja' => $precioUsdCaja,
                'tipo_cambio_usd_mxn' => $tipoCambio,
                'ingreso_bruto_mxn' => round($ingresoBruto, 2),
                'distancia_km' => round($distKm, 2),
                'costos' => [ // costos por tonelada
                    'flete_mxn' => round($costoFlete, 2),
                    'aduana_mxn' => round($aduana, 2),
                    'espera_mxn' => round($espera, 2),
                ],
                'ganancia_neta_mxn' => round($gananciaNeta, 2),
                'disponibilidad' => true,
            ];
        }
    } else {
        // No hay datos USDA
        $opciones[] = [
            'modo' => 'exportacion',
            'mercado' => null,
            'disponibilidad' => false,
        ];
    }

    // 2) Opción nacional (SNIIM / BD) específica (Chihuahua)
    $precioNacional = isset($precioSNIIM['precio_mxn_por_tonelada']) ? (float)$precioSNIIM['precio_mxn_por_tonelada'] : 0.0;
    $municipioNacional = $precioSNIIM['municipio'] ?? null;

    // Para la venta nacional no aplican aduana ni espera
    // Distancia: usar capital del estado relevante (Chihuahua) en lugar de Monterrey
    $coordsChihuahua = [-106.0691, 28.6320]; // Chihuahua capital
    $distKmLocal = obtener_datos_logistica($api_keys['rutas'], $coordsChihuahua);
    $costoFleteLocal = ($distKmLocal * $COSTO_POR_KM_MXN) / $CAPACIDAD_TON_CAMION;

    $opciones[] = [
        'modo' => 'nacional',
        'mercado' => 'Chihuahua (SNIIM)',
        'pais' => 'México',
        'municipio' => $municipioNacional,
        'precio_mxn_por_tonelada' => $precioNacional,
        'ingreso_bruto_mxn' => round($precioNacional, 2),
        'distancia_km' => round($distKmLocal, 2),
        'costos' => [
            'flete_mxn' => round($costoFleteLocal, 2),
            'aduana_mxn' => 0.0,
            'espera_mxn' => 0.0,
        ],
        'ganancia_neta_mxn' => round($precioNacional - $costoFleteLocal, 2),
        'disponibilidad' => $precioNacional > 0,
    ];

    // 2b) Opciones nacionales Top por otros estados (mejores precios recientes)
    foreach ($preciosNacionalesTop as $nac) {
        $estado = $nac['estado'] ?? null;
        $precio = isset($nac['precio_mxn_por_tonelada']) ? (float)$nac['precio_mxn_por_tonelada'] : 0.0;
        if (!$estado || $precio <= 0) continue;
        // Distancia aproximada: usar capital/ciudad principal (simplificación)
        $coordsEstado = null;
        if (stripos($estado, 'Nuevo León') !== false || stripos($estado, 'Nuevo Leon') !== false) { $coordsEstado = [-100.3180, 25.6751]; } // Monterrey
        if (stripos($estado, 'Chihuahua') !== false) { $coordsEstado = [-106.0691, 28.6320]; } // Chihuahua capital
        if (stripos($estado, 'Coahuila') !== false) { $coordsEstado = [-101.0079, 25.4267]; } // Saltillo
        if (stripos($estado, 'Jalisco') !== false) { $coordsEstado = [-103.3496, 20.6597]; } // Guadalajara
        if (!$coordsEstado) { continue; }
    $distKmNac = obtener_datos_logistica($api_keys['rutas'], $coordsEstado);
    $costoFleteNac = ($distKmNac * $COSTO_POR_KM_MXN) / $CAPACIDAD_TON_CAMION;
        $gananciaNetaNac = $precio - $costoFleteNac;
        $opciones[] = [
            'modo' => 'nacional',
            'mercado' => $estado . ' (SNIIM)',
            'pais' => 'México',
            'municipio' => $nac['municipio'] ?? null,
            'precio_mxn_por_tonelada' => $precio,
            'ingreso_bruto_mxn' => round($precio, 2),
            'distancia_km' => round($distKmNac, 2),
            'costos' => [
                'flete_mxn' => round($costoFleteNac, 2),
                'aduana_mxn' => 0.0,
                'espera_mxn' => 0.0,
            ],
            'ganancia_neta_mxn' => round($gananciaNetaNac, 2),
            'disponibilidad' => true,
        ];
    }

    // 3) Elegir mejor opción por ganancia neta
    // Ordenar por ganancia (desc) priorizando opciones disponibles
    usort($opciones, function($a, $b) {
        $da = !empty($a['disponibilidad']);
        $db = !empty($b['disponibilidad']);
        if ($da !== $db) return $db <=> $da; // disponibles primero
        $ga = $a['ganancia_neta_mxn'] ?? -INF;
        $gb = $b['ganancia_neta_mxn'] ?? -INF;
        return $gb <=> $ga;
    });

    $mejor = $opciones[0] ?? null;

    // Construir mensaje recomendación
    $recomendacion = '';
    if (!$mejor) {
        $recomendacion = 'No hay datos suficientes para generar una recomendación.';
    } else {
        if (($mejor['modo'] ?? '') === 'exportacion') {
            $recomendacion = sprintf(
                "Recomiendo exportar a %s: ganancia neta estimada %s MXN por tonelada.",
                $mejor['mercado'] ?? 'mercado externo',
                number_format($mejor['ganancia_neta_mxn'], 2)
            );
            // Comparativa con local
            $local = null;
            foreach ($opciones as $o) { if (($o['modo'] ?? '') === 'nacional') { $local = $o; break; } }
            $localGain = isset($local['ganancia_neta_mxn']) ? (float)$local['ganancia_neta_mxn'] : null;
            if (is_numeric($localGain)) {
                $diff = $mejor['ganancia_neta_mxn'] - $localGain;
                if ($localGain > 0 && $mejor['ganancia_neta_mxn'] > 0) {
                    $pct = ($diff / $localGain) * 100.0;
                    $recomendacion .= sprintf(" Esto es %s MXN (%s%%) más que la venta local.", number_format($diff, 2), number_format($pct, 2));
                } else {
                    $recomendacion .= sprintf(" Esto es %s MXN más que la venta local (comparación porcentual no aplica).", number_format($diff, 2));
                }
            }
        } else {
            $recomendacion = sprintf(
                "Recomiendo vender en el mercado nacional: ganancia neta estimada %s MXN por tonelada.",
                number_format($mejor['ganancia_neta_mxn'], 2)
            );
            $exp = null;
            foreach ($opciones as $o) { if (($o['modo'] ?? '') === 'exportacion' && !empty($o['disponibilidad'])) { $exp = $o; break; } }
            if ($exp) {
                $expGain = (float)$exp['ganancia_neta_mxn'];
                $diff = $mejor['ganancia_neta_mxn'] - $expGain;
                if ($expGain > 0 && $mejor['ganancia_neta_mxn'] > 0) {
                    $pct = ($diff / $expGain) * 100.0;
                    $recomendacion .= sprintf(" Esto es %s MXN (%s%%) más que la mejor opción de exportación.", number_format($diff, 2), number_format($pct, 2));
                } else {
                    $recomendacion .= sprintf(" Esto es %s MXN más que la mejor opción de exportación (comparación porcentual no aplica).", number_format($diff, 2));
                }
            }
        }
    }

    // Top 4 por ganancia neta
    $top4 = array_slice($opciones, 0, 4);

    // Agrupar por país/estado para mostrar en UI
    $porPaisEstado = [];
    foreach ($opciones as $o) {
        $pais = $o['pais'] ?? 'N/A';
        $grupo = $pais;
        if ($pais === 'México' && !empty($o['mercado'])) {
            $grupo .= ' - ' . $o['mercado'];
        } elseif ($pais === 'EE.UU.' && !empty($o['mercado'])) {
            $grupo .= ' - ' . $o['mercado'];
        }
        $porPaisEstado[$grupo][] = $o;
    }

    return [
        'mejor_opcion' => $mejor,
        'top4' => $top4,
        'todas_las_opciones' => $opciones,
        'agrupado' => $porPaisEstado,
        'recomendacion' => $recomendacion,
        'fuentes' => [
            'tipo_cambio' => 'exchangerate-api',
            'usda' => 'simulada por ahora',
            'sniim' => 'MySQL optilife',
            'rutas' => 'openrouteservice',
            'frontera' => 'CBP RSS',
        ],
        'timestamp' => date('c'),
        'notas' => [
            'costo_aduana' => 'El costo de aduana representa trámites y aranceles por cruce internacional. Este valor lo ingresa el usuario (' . number_format($COSTO_ADUANA_MXN, 2) . ' MXN por camión) y se prorratea por tonelada según la capacidad del camión.',
            'cajas_por_ton' => 'Conversión aplicada: ' . number_format((float)$cajasPorTon, 0) . ' cajas por tonelada para convertir precios USD/caja a MXN/ton.',
        ],
    ];
}
