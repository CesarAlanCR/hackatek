<?php
// api/obtener_detalles_insumos.php
// Endpoint para obtener información detallada de insumos calculados

declare(strict_types=1);
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/insumos_data.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Parámetros opcionales
$superficie = isset($_GET['superficie']) ? max(0.01, (float)$_GET['superficie']) : 1.0;
$densidad = isset($_GET['densidad']) ? max(1, (int)$_GET['densidad']) : 400;
$tipo_cultivo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'manzana';

try {
    // Cargar datos de insumos directamente del archivo
    $insumos_base = require __DIR__ . '/../includes/insumos_data.php';
    
    if (empty($insumos_base)) {
        throw new Exception('No se pudieron cargar los datos base de insumos');
    }
    
    // Reorganizar por categorías
    $insumos_por_categoria = [];
    foreach ($insumos_base as $item) {
        $categoria = $item['tipo'] ?? 'otros';
        if (!isset($insumos_por_categoria[$categoria])) {
            $insumos_por_categoria[$categoria] = [];
        }
        $insumos_por_categoria[$categoria][] = $item;
    }
    
    // Calcular totales por superficie
    $total_arboles = $superficie * $densidad;
    $insumos_calculados = [];
    $costo_total = 0;
    
    foreach ($insumos_por_categoria as $categoria => $items) {
        $insumos_calculados[$categoria] = [];
        $subtotal_categoria = 0;
        
        foreach ($items as $item) {
            $cantidad_calculada = $item['dosis_por_hectarea'] * $superficie;
            $costo_calculado = $cantidad_calculada * $item['precio_por_unidad'];
            $subtotal_categoria += $costo_calculado;
            
            $insumos_calculados[$categoria][] = [
                'id' => $item['id'],
                'nombre' => $item['nombre'],
                'unidad' => $item['unidad_dosis'],
                'dosis_por_hectarea' => $item['dosis_por_hectarea'],
                'cantidad_calculada' => round($cantidad_calculada, 2),
                'precio_unitario' => $item['precio_por_unidad'],
                'costo_calculado' => round($costo_calculado, 2),
                'presentacion' => $item['presentacion'] ?? 'No especificada'
            ];
        }
        
        $costo_total += $subtotal_categoria;
    }
    
    // Estadísticas generales
    $total_items = array_sum(array_map('count', $insumos_calculados));
    $promedio_por_item = $total_items > 0 ? $costo_total / $total_items : 0;
    $costo_por_hectarea = $superficie > 0 ? $costo_total / $superficie : 0;
    $costo_por_arbol = $total_arboles > 0 ? $costo_total / $total_arboles : 0;
    
    // Categorías más costosas
    $costos_por_categoria = [];
    foreach ($insumos_calculados as $categoria => $items) {
        $costos_por_categoria[$categoria] = array_sum(array_column($items, 'costo_calculado'));
    }
    arsort($costos_por_categoria);
    
    echo json_encode([
        'success' => true,
        'parametros' => [
            'superficie_ha' => $superficie,
            'densidad_arboles_ha' => $densidad,
            'total_arboles' => $total_arboles,
            'tipo_cultivo' => $tipo_cultivo
        ],
        'insumos_calculados' => $insumos_calculados,
        'estadisticas' => [
            'costo_total' => round($costo_total, 2),
            'total_items' => $total_items,
            'promedio_por_item' => round($promedio_por_item, 2),
            'costo_por_hectarea' => round($costo_por_hectarea, 2),
            'costo_por_arbol' => round($costo_por_arbol, 2),
            'costos_por_categoria' => array_map(function($v) { return round($v, 2); }, $costos_por_categoria)
        ],
        'recomendaciones' => [
            'categoria_mas_costosa' => array_key_first($costos_por_categoria),
            'ahorro_potencial' => 'Revisar precios de ' . array_key_first($costos_por_categoria),
            'optimizacion' => $costo_por_hectarea > 50000 ? 
                'Considerar negociación por volumen' : 
                'Costos dentro del rango esperado'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'mensaje' => $e->getMessage(),
        'codigo' => $e->getCode()
    ]);
}
?>