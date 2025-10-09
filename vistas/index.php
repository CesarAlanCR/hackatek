
<?php
// Interfaz inicial para proyecto de cultivos
// Archivo: vistas/index.php
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<link rel="icon" type="image/png" href="logo.png">

	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Hackatek - Gestión de Cultivos</title>
	<link rel="stylesheet" href="../recursos/css/general.css">
	<!-- Leaflet CSS (mapas) -->
	<link
		rel="stylesheet"
		href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
		integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
		crossorigin=""
	>
</head>
<body>
	<!-- Header removed by user request -->

	<main class="container">
		<section class="hero">
			<h2>OptiLife</h2>
			<p class="lead">Monitorea, planifica y actúa para mejorar el rendimiento de tus cultivos.</p>
		</section>

	

		<!-- Preview: Imágenes y Clima -->
		<section class="preview-clima">
			<div class="preview-grid">
				<div class="preview-image card">
					<!-- Contenedor del mapa interactivo -->
					<div id="weather-map" aria-label="Mapa de clima y ubicación"></div>
				</div>
				<div class="preview-info card">
					<!-- Buscador de ciudades -->
					<div class="city-search-container">
						<div class="search-input-group">
							<input 
								type="text" 
								id="city-search-input" 
								placeholder="Buscar ciudad en México (ej: Ciudad de México, Guadalajara...)"
								autocomplete="off"
							>
							<button id="search-city-btn" class="btn btn-secondary" title="Buscar ciudad">
								🔍
							</button>
							<button id="current-location-btn" class="btn btn-secondary" title="Obtener ubicación actual">
								📍
							</button>
						</div>
						<div id="city-search-results" class="search-results" style="display: none;"></div>
						<div id="current-city-display" class="current-city"></div>
					</div>
					
					<h3>Clima</h3>
					<p>Vista rápida: imagen satelital / drone y resumen meteorológico actual.</p>
					<ul>
						<li>Temperatura: <span id="temp-value">--</span></li>
						<li>Humedad: <span id="humidity-value">--</span></li>
						<li>Última imagen: <span id="satellite-updated">--</span></li>
					</ul>
					<button id="btn-ver-detalle-clima" class="btn btn-primary">Ver detalle</button>
				</div>
			</div>
		</section>

		<section class="modules" aria-label="Módulos principales">
			<article id="modulo-clima" class="card module-card" data-module="Clima" tabindex="0">
				<h3>Planificador virtual</h3>
				<p>Planifica tus cultivos como un experto</p>
				<a href="planificador_visual.php" class="btn btn-primary" aria-label="Abrir Planificador virtual">Abrir</a>
			</article>

			

			<article id="modulo-chat-ia" class="card module-card" data-module="Chat IA" tabindex="0">
				<h3>Chat IA</h3>
				<p>Asistente para recomendaciones y diagnósticos.</p>
				<a href="ia/chat.php" class="btn btn-primary">Abrir</a>
			</article>

			<article id="modulo-exportacion" class="card module-card" data-module="Cuerpos de agua" tabindex="0">
				<h3>Cuerpos de agua</h3>
				<p>Visualiza los cuerpos de agua en mexico</p>
				<a href="agua.php" class="btn btn-primary" aria-label="Abrir Cuerpos de agua">Abrir</a>
			</article>

			<article id="modulo-mercado" class="card module-card" data-module="Mercado" tabindex="0">
				<h3>Gastos Aproximados</h3>
				<p>Calcula el aproximado para tu fertilizante</p>
				<a href="insumos.php" class="btn btn-primary" aria-label="Abrir Insumos">Abrir</a>
			</article>
		</section>

		
	</main>

	<footer class="site-footer">
		<div class="container">
			<small>© <?php echo date('Y'); ?> Hackatek - Proyecto de ejemplo</small>
		</div>
	</footer>

	<!-- Modal simple -->
	<div id="module-modal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="modal-title">
		<div class="modal-content">
			<button class="modal-close" aria-label="Cerrar">×</button>
			<h3 id="modal-title"></h3>
			<div id="modal-body"></div>
		</div>
	</div>
	<!-- Leaflet JS (mapas) -->
	<script
		src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
		integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
		crossorigin=""
		defer
	></script>
	<?php
		// Carga la API key desde un archivo de configuración centralizado.
		// Nota: evita subir este archivo al repositorio (ver .gitignore).
		$configPath = realpath(__DIR__ . '/../includes/owm_config.php');
		$owmKey = '';
        $weatherApiKey = '';
		
		if ($configPath && file_exists($configPath)) {
			$config = include $configPath;
			if (is_array($config) && isset($config['OWM_API_KEY'])) {
				$owmKey = $config['OWM_API_KEY'];
			}
            if (is_array($config) && isset($config['WEATHERAPI_KEY'])) {
                $weatherApiKey = $config['WEATHERAPI_KEY'];
            }
		}
		
		// Fallback en caso de que no se cargue la configuración
		if (empty($owmKey)) {
			$owmKey = 'e3f0790da98e5d2fa495d11bb819e9f1';
		}
		if (empty($weatherApiKey)) {
			$weatherApiKey = 'ff26c6b8641d423e926224810250810';
		}
	?>
	<script>
		// Expone la API key de OpenWeatherMap al frontend
		window.OWM_API_KEY = <?php echo json_encode($owmKey); ?>;
        // Expone la API key de WeatherAPI si está disponible
        window.WEATHERAPI_KEY = <?php echo json_encode($weatherApiKey); ?>;
	</script>
	<script src="../recursos/js/class.js" defer></script>
	<script src="../recursos/js/animations.js" defer></script>
</body>
</html>
