
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
				<a id="planificador-link" href="#" class="btn btn-primary" aria-label="Abrir Planificador virtual">Abrir</a>
			</article>

			

			<article id="modulo-chat-ia" class="card module-card" data-module="Chat IA" tabindex="0">
				<h3>Chat IA</h3>
				<p>Asistente para recomendaciones y diagnósticos.</p>
				<a href="#" id="link-chat-ia" class="btn btn-primary">Abrir</a>
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
			<small>© <?php echo date('Y'); ?> Hackatek - Proyecto de OptiLife</small>
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

<script>
// Recoge los datos de clima/ubicación mostrados en index y los pasa al planificador
function getPlanificadorParams() {
	// Obtener datos básicos de ubicación
	const lat = window.appState?.lastCoords?.lat || '';
	const lon = window.appState?.lastCoords?.lon || '';
	const cityName = window.appState?.currentCity || '';
	
	// Extraer ciudad y estado por separado
	let ciudad = '';
	let estado = '';
	if (cityName) {
		const parts = cityName.split(',');
		if (parts.length > 1) {
			ciudad = parts[0].trim();
			estado = parts[1].trim();
		} else {
			ciudad = cityName.trim();
		}
	}
	
	// Obtener datos climáticos completos del estado global
	const weatherData = window.appState?.currentWeatherData || {};
	
	// Extraer datos climáticos detallados
	const temperatura = weatherData.main?.temp ? Math.round(weatherData.main.temp) : '';
	const tempMax = weatherData.extended?.tempMax ? Math.round(weatherData.extended.tempMax) : 
	                (weatherData.main?.temp_max ? Math.round(weatherData.main.temp_max) : '');
	const tempMin = weatherData.extended?.tempMin ? Math.round(weatherData.extended.tempMin) : 
	                (weatherData.main?.temp_min ? Math.round(weatherData.main.temp_min) : '');
	const humedad = weatherData.main?.humidity || '';
	const presion = weatherData.main?.pressure || '';
	const visibilidad = weatherData.visibility ? (weatherData.visibility / 1000).toFixed(1) : '';
	const velocidadViento = weatherData.wind?.speed ? Math.round(weatherData.wind.speed * 3.6) : '';
	const direccionViento = weatherData.wind?.deg || '';
	const nubosidad = weatherData.clouds?.all || '';
	const condicionClimatica = weatherData.weather?.[0]?.description || '';
	const iconoClima = weatherData.weather?.[0]?.icon || '';
	
	// Datos de pronóstico extendido
	const probabilidadLluvia = weatherData.extended?.rainProbability ? Math.round(weatherData.extended.rainProbability) : '';
	const precipitacionTotal = weatherData.extended?.precipitation ? weatherData.extended.precipitation.toFixed(1) : '';
	const vientoMaximo = weatherData.extended?.maxWindSpeed ? Math.round(weatherData.extended.maxWindSpeed * 3.6) : '';
	
	// Datos adicionales disponibles en la app
	const suelo = window.appState?.currentSoil || '';
	
	// Determinar temporada actual
	const m = new Date().getMonth() + 1;
	let temporada = 'Otoño';
	if (m === 12 || m <= 2) temporada = 'Invierno';
	else if (m >= 3 && m <= 5) temporada = 'Primavera';
	else if (m >= 6 && m <= 8) temporada = 'Verano';
	
	return {
		// Ubicación detallada
		lat: lat,
		lon: lon,
		ciudad: ciudad,
		estado: estado,
		ciudad_completa: cityName,
		
		// Clima actual detallado
		temperatura: temperatura,
		temp_max: tempMax,
		temp_min: tempMin,
		humedad: humedad,
		presion: presion,
		visibilidad: visibilidad,
		viento_velocidad: velocidadViento,
		viento_direccion: direccionViento,
		viento_maximo: vientoMaximo,
		nubosidad: nubosidad,
		condicion: condicionClimatica,
		icono_clima: iconoClima,
		probabilidad_lluvia: probabilidadLluvia,
		precipitacion_total: precipitacionTotal,
		
		// Información adicional
		suelo: suelo,
		temporada: temporada,
		
		// Mantener compatibilidad con versión anterior
		clima: `${temperatura}°C`,
		tmax: tempMax
	};
}

document.getElementById('planificador-link').addEventListener('click', async function(e) {
	e.preventDefault();
	const params = getPlanificadorParams();

	console.log('📊 Parámetros enviados al planificador (inicial):', params);

	// Si no tenemos tipo de suelo pero sí coordenadas, intentar obtenerlo desde el proxy INEGI
	if ((!params.suelo || params.suelo === '') && params.lat && params.lon) {
		try {
			const soilUrl = `../includes/inegi_soil_proxy.php?lat=${encodeURIComponent(params.lat)}&lon=${encodeURIComponent(params.lon)}`;
			const resp = await fetch(soilUrl);
			if (resp.ok) {
				const json = await resp.json();
				if (json && (json.soilType || json.soil)) {
					const soilType = json.soilType || json.soil;
					window.appState = window.appState || {};
					window.appState.currentSoil = soilType;
					params.suelo = soilType;
					console.log('🌱 Tipo de suelo obtenido antes de redirigir:', soilType);
				}
			} else {
				console.warn('No se pudo obtener suelo (INEGI) antes de redirigir:', resp.status);
			}
		} catch (err) {
			console.warn('Error al obtener tipo de suelo antes de redirigir:', err);
		}
	}

	// Construye la querystring y redirige
	const qs = Object.entries(params)
		.filter(([k,v]) => v !== '') // Solo parámetros con valor
		.map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
		.join('&');
	window.location.href = `planificador_visual.php?${qs}`;
});

// Listener para el chat IA: pasa el mismo contexto de ubicación
document.getElementById('link-chat-ia').addEventListener('click', function(e) {
	e.preventDefault();
	const params = getPlanificadorParams();
	const qs = Object.entries(params)
		.filter(([k,v]) => v !== '')
		.map(([k,v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
		.join('&');
	window.location.href = `ia/chat.php?${qs}`;
});
</script>
</body>
</html>
