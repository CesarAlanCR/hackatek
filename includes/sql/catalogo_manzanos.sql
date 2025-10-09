-- SQL: Tabla de catálogo de variedades de manzana
CREATE TABLE IF NOT EXISTS catalogo_manzanos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre_variedad VARCHAR(100) NOT NULL,
  portainjerto VARCHAR(60),
  descripcion TEXT,
  horas_frio_necesarias VARCHAR(40),
  epoca_floracion VARCHAR(30),
  dias_de_flor_a_cosecha INT,
  vida_anaquel_dias INT,
  polinizadores_recomendados VARCHAR(255),
  resistencia_heladas VARCHAR(30),
  ventana_cosecha_tipica VARCHAR(120),
  uso_principal VARCHAR(80),
  fuente VARCHAR(120),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO catalogo_manzanos (nombre_variedad, portainjerto, descripcion, horas_frio_necesarias, epoca_floracion, dias_de_flor_a_cosecha, vida_anaquel_dias, polinizadores_recomendados, resistencia_heladas, ventana_cosecha_tipica, uso_principal, fuente)
VALUES
('Red Delicious','MM-111','Variedad clásica de buena conservación','900-1000','intermedia',95,30,'Golden Delicious, Gala','media','Finales de Agosto a mediados de Septiembre','fresco','INIFAP'),
('Golden Delicious','MM-106','Versátil y buen polinizador','800-900','intermedia',100,25,'Gala, Red Delicious','media','Principios de Septiembre','fresco','INIFAP'),
('Gala','G-41','Alta productividad y color atractivo','750-850','temprana',90,20,'Red Delicious, Golden','media','Finales de Agosto','fresco','WSU'),
('Fuji','MM-111','Dulce y firme, buena poscosecha','1000-1100','tardia',115,40,'Gala, Golden','media','Segunda mitad de Septiembre','fresco','WSU');