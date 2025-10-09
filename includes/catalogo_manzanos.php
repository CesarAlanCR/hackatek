<?php
// includes/catalogo_manzanos.php
// CRUD del catálogo de variedades de manzana

declare(strict_types=1);
require_once __DIR__ . '/conexion.php';

function catalogo_inicializar(): void {
    $db = conectar_bd();
    $sql = "CREATE TABLE IF NOT EXISTS catalogo_manzanos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre_variedad VARCHAR(120) NOT NULL,
        dias_de_flor_a_cosecha INT NOT NULL DEFAULT 120,
        vida_anaquel_dias INT NOT NULL DEFAULT 30,
        horas_frio_necesarias VARCHAR(40) NULL,
        epoca_floracion VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_nombre_variedad (nombre_variedad)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $db->query($sql);

    // Semillas mínimas si vacío
    $check = $db->query('SELECT COUNT(*) c FROM catalogo_manzanos');
    $count = $check ? (int)$check->fetch_assoc()['c'] : 0;
    if ($count === 0) {
        $seed = [
            ['Red Delicious', 135, 40, '800-900', 'media'],
            ['Golden Delicious', 140, 35, '700-800', 'media'],
            ['Gala', 125, 30, '600-700', 'temprana'],
            ['Fuji', 160, 45, '900-1000', 'tardia']
        ];
        $stmt = $db->prepare('INSERT INTO catalogo_manzanos (nombre_variedad,dias_de_flor_a_cosecha,vida_anaquel_dias,horas_frio_necesarias,epoca_floracion) VALUES (?,?,?,?,?)');
        foreach ($seed as $s) {
            $stmt->bind_param('siiss', $s[0], $s[1], $s[2], $s[3], $s[4]);
            $stmt->execute();
        }
        $stmt->close();
    }
    $db->close();
}

function variedades_listar(): array {
    $db = conectar_bd();
    $res = $db->query('SELECT * FROM catalogo_manzanos ORDER BY nombre_variedad');
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $db->close();
    return $rows;
}

function variedad_obtener(int $id): ?array {
    $db = conectar_bd();
    $stmt = $db->prepare('SELECT * FROM catalogo_manzanos WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    $db->close();
    return $row;
}

function variedad_insertar(string $nombre, int $diasFlorCosecha, int $vidaAnaquel, ?string $horasFrio, ?string $epoca): bool {
    $db = conectar_bd();
    $stmt = $db->prepare('INSERT INTO catalogo_manzanos (nombre_variedad,dias_de_flor_a_cosecha,vida_anaquel_dias,horas_frio_necesarias,epoca_floracion) VALUES (?,?,?,?,?)');
    $stmt->bind_param('siiss', $nombre, $diasFlorCosecha, $vidaAnaquel, $horasFrio, $epoca);
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();
    return $ok;
}

function variedad_eliminar(int $id): bool {
    $db = conectar_bd();
    $stmt = $db->prepare('DELETE FROM catalogo_manzanos WHERE id=?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();
    return $ok;
}
?>