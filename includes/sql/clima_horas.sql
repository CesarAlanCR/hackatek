-- Tabla de almacenamiento de temperaturas horarias
CREATE TABLE IF NOT EXISTS clima_horas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  huerto_id INT NULL, -- futuro si se asocia a huerto especifico
  lat DECIMAL(9,6) NOT NULL,
  lon DECIMAL(9,6) NOT NULL,
  fecha_hora DATETIME NOT NULL,
  temperatura_c DECIMAL(5,2) NOT NULL,
  relative_humidity_pct DECIMAL(5,2) NULL,
  dew_point_c DECIMAL(5,2) NULL,
  precipitation_mm DECIMAL(6,2) NULL,
  cloud_cover_pct DECIMAL(5,2) NULL,
  wind_speed_ms DECIMAL(5,2) NULL,
  pressure_hpa DECIMAL(7,2) NULL,
  radiation_wm2 DECIMAL(8,2) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_coord_fecha (lat, lon, fecha_hora),
  KEY idx_coord (lat, lon),
  KEY idx_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de acumulado de horas frío por temporada
CREATE TABLE IF NOT EXISTS horas_frio_acumuladas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  lat DECIMAL(9,6) NOT NULL,
  lon DECIMAL(9,6) NOT NULL,
  temporada VARCHAR(9) NOT NULL, -- Ej: 2024-2025
  horas_frio INT NOT NULL DEFAULT 0,
  fecha_corte DATE NOT NULL,
  fuente VARCHAR(40) DEFAULT 'open-meteo',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_coord_temp (lat, lon, temporada, fecha_corte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;