-- Script para corregir los días de germinación de árboles frutales
-- Los valores representan días desde árbol plantado/establecido hasta primera cosecha
-- No el tiempo total de crecimiento desde semilla

-- Árboles de nuez (árboles establecidos producen en 180-365 días)
UPDATE productos SET dias_germinacion = 365 WHERE nombre IN ('Nuez', 'Nuez pecanera');

-- Cítricos (árboles establecidos producen en 180-270 días)
UPDATE productos SET dias_germinacion = 240 WHERE nombre IN ('Naranja', 'Limón');

-- Mango (árbol establecido produce en 120-180 días)
UPDATE productos SET dias_germinacion = 150 WHERE nombre = 'Mango';

-- Papaya (planta establecida produce en 180-270 días)
UPDATE productos SET dias_germinacion = 210 WHERE nombre = 'Papaya';

-- Durazno (árbol establecido produce en 90-150 días)
UPDATE productos SET dias_germinacion = 120 WHERE nombre = 'Durazno';

-- Manzana (árbol establecido produce en 120-180 días)
UPDATE productos SET dias_germinacion = 150 WHERE nombre = 'Manzana';

-- Uva (vid establecida produce en 90-120 días)
UPDATE productos SET dias_germinacion = 105 WHERE nombre IN ('Uva', 'Uva industrial', 'Uva de mesa');

-- Aceituna (árbol establecido produce en 180-240 días)
UPDATE productos SET dias_germinacion = 210 WHERE nombre = 'Aceituna';

-- Dátil (palma establecida produce en 240-365 días)
UPDATE productos SET dias_germinacion = 300 WHERE nombre = 'Dátil';

-- Café (planta establecida produce en 180-270 días)
UPDATE productos SET dias_germinacion = 225 WHERE nombre = 'Café cereza';

-- Cacao (árbol establecido produce en 150-240 días)
UPDATE productos SET dias_germinacion = 195 WHERE nombre = 'Cacao';

-- Palma de aceite (palma establecida produce en 365-450 días)
UPDATE productos SET dias_germinacion = 400 WHERE nombre = 'Palma de aceite';

-- Verificar cambios
SELECT nombre, dias_germinacion, dias_caducidad 
FROM productos 
WHERE nombre IN ('Nuez', 'Mango', 'Dátil', 'Aceituna', 'Cacao', 'Manzana', 'Durazno', 'Uva', 'Naranja', 'Limón', 'Café cereza', 'Palma de aceite', 'Nuez pecanera')
ORDER BY nombre;