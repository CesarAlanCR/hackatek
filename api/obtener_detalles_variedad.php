<?php
// api/obtener_detalles_variedad.php
// Endpoint para obtener detalles completos de una variedad desde la base de datos

declare(strict_types=1);
require_once __DIR__ . '/../includes/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$variedad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($variedad_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de variedad inválido']);
    exit;
}

try {
    $db = conectar_bd();
    
    // Obtener detalles completos de la variedad
    $sql = "SELECT 
                id,
                nombre_variedad,
                portainjerto,
                descripcion,
                horas_frio_necesarias,
                epoca_floracion,
                dias_de_flor_a_cosecha,
                vida_anaquel_dias,
                polinizadores_recomendados,
                resistencia_heladas,
                ventana_cosecha_tipica,
                uso_principal,
                fuente,
                created_at
            FROM catalogo_manzanos 
            WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $variedad_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($variedad = $result->fetch_assoc()) {
        // Formatear datos para mejor presentación
        $variedad['descripcion_formateada'] = $variedad['descripcion'] ?: 'Información no disponible';
        $variedad['horas_frio_rango'] = $variedad['horas_frio_necesarias'] ?: 'No especificado';
        $variedad['polinizadores_lista'] = $variedad['polinizadores_recomendados'] ? 
            explode(', ', $variedad['polinizadores_recomendados']) : [];
        $variedad['epoca_floracion_descripcion'] = match($variedad['epoca_floracion']) {
            'temprana' => 'Floración temprana (marzo-abril)',
            'intermedia' => 'Floración intermedia (abril-mayo)', 
            'tardia' => 'Floración tardía (mayo-junio)',
            default => $variedad['epoca_floracion'] ?: 'No especificado'
        };
        $variedad['resistencia_heladas_descripcion'] = match($variedad['resistencia_heladas']) {
            'alta' => 'Alta resistencia a heladas',
            'media' => 'Resistencia media a heladas',
            'baja' => 'Baja resistencia a heladas',
            default => $variedad['resistencia_heladas'] ?: 'No especificado'
        };
        
        // Calcular información adicional
        $variedad['ventana_duracion_dias'] = round($variedad['dias_de_flor_a_cosecha'] * 0.1, 0); // Estimación simple
        $variedad['categoria_vida_anaquel'] = match(true) {
            $variedad['vida_anaquel_dias'] >= 35 => 'Excelente vida de anaquel',
            $variedad['vida_anaquel_dias'] >= 25 => 'Buena vida de anaquel',
            $variedad['vida_anaquel_dias'] >= 15 => 'Vida de anaquel regular',
            default => 'Vida de anaquel corta'
        };
        
        echo json_encode([
            'success' => true,
            'variedad' => $variedad,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Variedad no encontrada']);
    }
    
    $stmt->close();
    $db->close();
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'mensaje' => $e->getMessage()
    ]);
}
?>