<?php
// includes/planificador_manager.php
// Lógica de planificación de cosecha y vida de anaquel

declare(strict_types=1);
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/clima_persistencia.php';

function planificador_obtener_variedad(int $id): ?array {
    $db = conectar_bd();
    $stmt = $db->prepare('SELECT id,nombre_variedad,dias_de_flor_a_cosecha,vida_anaquel_dias,horas_frio_necesarias,epoca_floracion FROM catalogo_manzanos WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    $db->close();
    return $row;
}

function planificador_calcular(array $variedad, string $fecha_floracion, ?int $horas_frio_acumuladas = null): array {
    $dias = (int)($variedad['dias_de_flor_a_cosecha'] ?? 0);
    $vida = (int)($variedad['vida_anaquel_dias'] ?? 0);
    $fechaFlor = new DateTime($fecha_floracion);

    // Parse horas frío requeridas (puede ser rango)
    $horas_frio_requeridas = null;
    if (!empty($variedad['horas_frio_necesarias'])) {
        if (strpos($variedad['horas_frio_necesarias'], '-') !== false) {
            [$a,$b] = explode('-', $variedad['horas_frio_necesarias']);
            $horas_frio_requeridas = (int)floor(((int)$a + (int)$b)/2);
        } else {
            $horas_frio_requeridas = (int)$variedad['horas_frio_necesarias'];
        }
    }

    // Si no se proporcionó acumulado, intentar calcular desde inicio de temporada usando datos en DB
    if ($horas_frio_acumuladas === null) {
        try {
            $anioFlorTmp = (int)(new DateTime($fecha_floracion))->format('Y');
            $temporadaInicioTmp = ($anioFlorTmp - 1) . '-07-01 00:00:00';
            $hoyTmp = date('Y-m-d') . ' 23:59:59';
            // coordenadas por defecto
            $horas_frio_acumuladas = clima_calcular_acumulado(28.4069, -106.8666, $temporadaInicioTmp, $hoyTmp);
        } catch (Throwable $e) {
            $horas_frio_acumuladas = null;
        }
    }

    $porcentaje_frio = null;
    if ($horas_frio_acumuladas !== null && $horas_frio_requeridas) {
        $porcentaje_frio = $horas_frio_requeridas > 0 ? round(($horas_frio_acumuladas / $horas_frio_requeridas)*100,1) : null;
    }
    // Ajuste simple si horas frío muy por debajo (<80%): retrasar cosecha
    $ajuste_dias = 0;
    if ($porcentaje_frio !== null) {
        if ($porcentaje_frio < 80) { $ajuste_dias = 3; }
        elseif ($porcentaje_frio < 90) { $ajuste_dias = 1; }
    }
    // (Removido) Probabilidad de éxito de cosecha
    $prob_exito_cosecha = null; // mantenido nulo para compatibilidad temporal
    $chill_portions_estimadas = null;
    $debug_calc = [
        'porcentaje_frio' => $porcentaje_frio,
        'horas_frio_acumuladas' => $horas_frio_acumuladas,
        'horas_frio_requeridas' => $horas_frio_requeridas,
        'ajuste_dias' => $ajuste_dias,
        'chill_portions_estimadas' => null,
        // Campos de prob_exito removidos
    ];

    // Cálculo de prob_exito removido


    $fechaCosecha = (clone $fechaFlor)->add(new DateInterval('P' . ($dias + $ajuste_dias) . 'D'));
    $ventanaInicio = (clone $fechaCosecha)->sub(new DateInterval('P5D'));
    $ventanaFin = (clone $fechaCosecha)->add(new DateInterval('P5D'));
    $fechaLimiteAnaquel = (clone $fechaCosecha)->add(new DateInterval('P' . $vida . 'D'));

    // Proyección: contamos horas frío desde ahora hasta fecha estimada de cosecha usando datos forecast ya insertados
    try {
        $db = conectar_bd();
        $ahora = (new DateTime())->format('Y-m-d H:00:00');
        $hastaProy = $fechaCosecha->format('Y-m-d 23:59:59');
        $stmtP = $db->prepare('SELECT COUNT(*) AS c FROM clima_horas WHERE lat = ? AND lon = ? AND fecha_hora BETWEEN ? AND ? AND temperatura_c > 0 AND temperatura_c <= 7');
        $latP = 28.4069; $lonP = -106.8666; // coordenadas por defecto; ideal usar huerto real
        $stmtP->bind_param('ddss', $latP, $lonP, $ahora, $hastaProy);
        $stmtP->execute();
        $resP = $stmtP->get_result()->fetch_assoc();
        $proyectadas_horas = (int)($resP['c'] ?? 0);
        $stmtP->close();
        $db->close();
        if ($horas_frio_requeridas && $horas_frio_requeridas > 0) {
            $porcentaje_proyectado = round((($horas_frio_acumuladas ?? 0) + $proyectadas_horas) / $horas_frio_requeridas * 100, 1);
        } else { $porcentaje_proyectado = null; }
        $debug_calc['proyectadas_horas'] = $proyectadas_horas;
        // Se ignora ajuste de base por porcentaje proyectado (prob_exito eliminado)
    } catch (Throwable $e) {
        $debug_calc['proyectadas_horas'] = null;
        $debug_calc['porcentaje_proyectado'] = null;
    }

    $alertas = [];
    if ($vida < 25) {
        $alertas[] = 'Vida de anaquel corta: priorizar logística temprana.';
    }
    if ($ajuste_dias > 0) {
        $alertas[] = 'Se aplicó ajuste de cosecha por déficit de horas frío.';
    }
    if (strtolower($variedad['epoca_floracion']) === 'tardia') {
        $alertas[] = 'Variedad de floración tardía: monitorear riesgo de calor en maduración.';
    }

    // Desglose detallado (repetitivo intencional) para interfaz educativa
    $detalles = [
        'variedad' => [
            'titulo' => 'Variedad Seleccionada',
            'valor' => $variedad['nombre_variedad'],
            'explicacion_corta' => 'La variedad determina el ciclo y requerimientos específicos.',
            'explicacion_extensa' => 'La variedad "'.$variedad['nombre_variedad'].'" define duración entre floración y cosecha, vida de anaquel y necesidades de frío. Cada variedad tiene un rango de adaptación y sensibilidad climática distinta.'
        ],
        'fecha_floracion' => [
            'titulo' => 'Fecha de Floración Base',
            'valor' => $fechaFlor->format('Y-m-d'),
            'explicacion_corta' => 'Punto cero del conteo de días a cosecha.',
            'explicacion_extensa' => 'La fecha de floración es el inicio del cálculo. Desde este día se suman los días típicos hasta cosecha que la variedad necesita. Cambios en esta fecha alteran todas las demás estimaciones.'
        ],
        'dias_flor_cosecha' => [
            'titulo' => 'Días Floración a Cosecha (Base)',
            'valor' => $dias,
            'explicacion_corta' => 'Número típico sin ajustes climáticos.',
            'explicacion_extensa' => 'Los '.$dias.' días representan el promedio histórico del intervalo floración-cosecha para la variedad. Este valor puede moverse si las condiciones térmicas (horas frío) son insuficientes.'
        ],
        'horas_frio_requeridas' => [
            'titulo' => 'Horas Frío Requeridas',
            'valor' => $horas_frio_requeridas,
            'explicacion_corta' => 'Objetivo de acumulación (0°C–7°C).',
            'explicacion_extensa' => 'Las horas frío son el número de horas en las que la temperatura se mantiene entre 0°C y 7°C y permiten la correcta diferenciación y desarrollo de yemas. La variedad necesita aproximadamente '.($horas_frio_requeridas ?? 0).' horas para sincronizar su fisiología.'
        ],
        'horas_frio_acumuladas' => [
            'titulo' => 'Horas Frío Acumuladas',
            'valor' => $horas_frio_acumuladas,
            'explicacion_corta' => 'Avance real medido o estimado.',
            'explicacion_extensa' => 'Hasta el momento se han acumulado '.($horas_frio_acumuladas ?? 0).' horas frío. Si no existían datos previos se realizó una estimación reciente. Este valor se compara contra el requerido para evaluar posibles retrasos.'
        ],
        'porcentaje_frio' => [
            'titulo' => 'Porcentaje Cumplimiento Frío',
            'valor' => $porcentaje_frio,
            'explicacion_corta' => 'Relación acumulado vs requerido.',
            'explicacion_extensa' => 'El porcentaje ('.($porcentaje_frio ?? 0).'%) se calcula dividiendo horas acumuladas entre horas requeridas. Por debajo de 80% se espera retraso; 80–90% implica ajuste leve; >90% estabilidad en la programación.'
        ],
        'ajuste_aplicado_dias' => [
            'titulo' => 'Ajuste Aplicado por Frío',
            'valor' => $ajuste_dias,
            'explicacion_corta' => 'Días añadidos al ciclo base.',
            'explicacion_extensa' => 'El ajuste de '.$ajuste_dias.' día(s) compensa déficit térmico. Menor frío retrasa procesos fisiológicos, por lo tanto se difiere la cosecha para lograr parámetros de calidad adecuados.'
        ],
        'fecha_cosecha_estimada' => [
            'titulo' => 'Fecha Cosecha Estimada',
            'valor' => $fechaCosecha->format('Y-m-d'),
            'explicacion_corta' => 'Fecha probable de inicio óptimo.',
            'explicacion_extensa' => 'Resulta de sumar días base ('.$dias.') más ajuste ('.$ajuste_dias.') al inicio ('.$fechaFlor->format('Y-m-d').'). Indica el punto central donde se espera mejor equilibrio entre madurez interna y firmeza.'
        ],
        'ventana_cosecha' => [
            'titulo' => 'Ventana Recomendada de Cosecha',
            'valor' => $ventanaInicio->format('Y-m-d').' → '.$ventanaFin->format('Y-m-d'),
            'explicacion_corta' => 'Margen aceptable de corte.',
            'explicacion_extensa' => 'La ventana (inicio '.$ventanaInicio->format('Y-m-d').' fin '.$ventanaFin->format('Y-m-d').') ofrece rango operativo para cosechar. Anticiparse demasiado reduce calidad; retrasarse aumenta riesgo de sobre-maduración y pérdida de firmeza.'
        ],
        'vida_anaquel_dias' => [
            'titulo' => 'Vida de Anaquel Estimada (días)',
            'valor' => $vida,
            'explicacion_corta' => 'Duración esperada tras cosecha.',
            'explicacion_extensa' => 'Los '.$vida.' días representan cuánto mantiene atributos comerciales (color, textura, sabor) bajo manejo estándar postcosecha. Variedades con menor vida requieren logística acelerada.'
        ],
        'fecha_limite_anaquel' => [
            'titulo' => 'Fecha Límite Anaquel',
            'valor' => $fechaLimiteAnaquel->format('Y-m-d'),
            'explicacion_corta' => 'Último día recomendado de comercialización.',
            'explicacion_extensa' => 'La fecha límite ('.$fechaLimiteAnaquel->format('Y-m-d').') marca el final del periodo donde la fruta mantiene calidad aceptable. Después se incrementa riesgo de deterioro acelerado.'
        ],
        'alertas' => [
            'titulo' => 'Alertas Generadas',
            'valor' => implode('; ', $alertas) ?: 'Sin alertas',
            'explicacion_corta' => 'Mensajes que requieren atención.',
            'explicacion_extensa' => 'Cada alerta resume condiciones críticas: vida de anaquel corta, déficit de frío o fenología tardía que pueden implicar ajustes logísticos o monitoreo adicional.'
        ]
    ];

    return [
        'variedad_id' => $variedad['id'],
        'nombre_variedad' => $variedad['nombre_variedad'],
        'fecha_floracion' => $fechaFlor->format('Y-m-d'),
        'dias_flor_cosecha' => $dias,
        'ajuste_aplicado_dias' => $ajuste_dias,
        'fecha_cosecha_estimada' => $fechaCosecha->format('Y-m-d'),
        'ventana_inicio' => $ventanaInicio->format('Y-m-d'),
        'ventana_fin' => $ventanaFin->format('Y-m-d'),
        'vida_anaquel_dias' => $vida,
        'fecha_limite_anaquel' => $fechaLimiteAnaquel->format('Y-m-d'),
        'horas_frio_requeridas' => $horas_frio_requeridas,
        'horas_frio_acumuladas' => $horas_frio_acumuladas,
    'porcentaje_frio' => $porcentaje_frio,
    // 'prob_exito_cosecha' removido del retorno
        'alertas' => $alertas,
    'detalles' => $detalles,
    'chill_portions_estimadas' => $chill_portions_estimadas,
    'debug_calc' => $debug_calc,
        // Simulación de acumulación granular de horas frío (para vista uniforme)
        'acumulacion' => (function() use ($horas_frio_acumuladas, $horas_frio_requeridas, $fechaFlor){
            $total = $horas_frio_acumuladas ?? 0;
            $requeridas = $horas_frio_requeridas ?? 0;
            $porcentaje = ($requeridas > 0) ? round(($total / $requeridas)*100,1) : null;
            // Buckets hipotéticos por rangos térmicos (solo informativo)
            // Suponemos distribución proporcional simplificada.
            $bucket_0_3 = (int)round($total * 0.55); // 55%
            $bucket_3_5 = (int)round($total * 0.30); // 30%
            $bucket_5_7 = max(0, $total - $bucket_0_3 - $bucket_3_5); // resto
            // Últimos 10 días (simulación suave decreciente si bajo cumplimiento)
            $diasSimulados = [];
            for($i=9; $i>=0; $i--) {
                $fechaDia = (clone $fechaFlor)->add(new DateInterval('P'.$i.'D'));
                // Heurística: más cercanos a floración menor aporte (simple demostración)
                $factor = (10 - $i)/10; // crece hacia hoy
                $diasSimulados[] = [
                    'fecha' => $fechaDia->format('Y-m-d'),
                    'horas_aporte' => (int)round(($total/10) * $factor * 0.8),
                    'comentario' => 'Aporte estimado de horas frío día simulado'
                ];
            }
            return [
                'total' => $total,
                'requeridas' => $requeridas,
                'porcentaje' => $porcentaje,
                'buckets' => [
                    '0_3' => $bucket_0_3,
                    '3_5' => $bucket_3_5,
                    '5_7' => $bucket_5_7
                ],
                'ultimos_dias' => $diasSimulados,
                'nota' => 'Distribución simulada para visualización; no sustituye medición real horaria.'
            ];
        })(),
        // Resumen compacto para el agricultor (solo lo esencial)
    'resumen_agricultor' => (function() use ($variedad, $fechaFlor, $ventanaInicio, $ventanaFin, $fechaCosecha, $porcentaje_frio, $ajuste_dias, $horas_frio_acumuladas, $horas_frio_requeridas, $alertas){
            // Clasificación simple del estado del frío
            $estadoFrio = 'estable';
            $mensajeFrio = 'Frío suficiente para seguir con programación.';
            if ($porcentaje_frio !== null) {
                if ($porcentaje_frio < 80) { $estadoFrio = 'bajo'; $mensajeFrio = 'Frío bajo: considerar retrasar labores y monitorear.'; }
                elseif ($porcentaje_frio < 90) { $estadoFrio = 'medio'; $mensajeFrio = 'Frío casi completo: ligero ajuste aplicado.'; }
            } else {
                $estadoFrio = 'desconocido'; $mensajeFrio = 'Frío estimado reciente: seguir actualizando.';
            }
            // Recomendación principal
            $recomendacion = 'Planificar cosecha dentro de la ventana y revisar heladas próximas.';
            if ($estadoFrio === 'bajo') { $recomendacion = 'No apresurar cosecha; esperar avance de frío antes de fijar cuadrillas.'; }
            if ($estadoFrio === 'medio') { $recomendacion = 'Organizar logística, posible inicio apenas se alcance ≥90% frío.'; }
            // Seleccionar primera alerta si existe para agregar contexto
            $alertaClave = count($alertas) ? $alertas[0] : null;
            return [
                'variedad' => $variedad['nombre_variedad'],
                'floracion' => $fechaFlor->format('Y-m-d'),
                'ventana' => [ 'inicio' => $ventanaInicio->format('Y-m-d'), 'fin' => $ventanaFin->format('Y-m-d') ],
                'cosecha_estimada' => $fechaCosecha->format('Y-m-d'),
                'estado_frio' => $estadoFrio,
                'porcentaje_frio' => $porcentaje_frio,
                // 'prob_exito_cosecha' removido
                'mensaje_frio' => $mensajeFrio,
                'ajuste_dias' => $ajuste_dias,
                'horas_frio' => [ 'acumuladas' => $horas_frio_acumuladas, 'requeridas' => $horas_frio_requeridas ],
                'alerta_clave' => $alertaClave,
                'recomendacion' => $recomendacion
            ];
        })()
    ];
}

function planificador_listar_variedades(int $limit = 300): array {
    $db = conectar_bd();
    $stmt = $db->prepare('SELECT id,nombre_variedad FROM catalogo_manzanos ORDER BY nombre_variedad LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    $db->close();
    return $rows;
}

?>