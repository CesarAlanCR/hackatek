
<?php
// Interfaz inicial para proyecto de cultivos
// Archivo: vistas/index.php
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
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
			<h2>Bienvenido a tu panel de cultivos</h2>
			<p class="lead">Monitorea, planifica y actúa para mejorar el rendimiento de tus cultivos. Interfaz inicial con módulos para comenzar.</p>
		</section>

		<!-- Preview: Imágenes y Clima -->
		<section class="preview-clima">
			<div class="preview-grid">
				<div class="preview-image card">
					<!-- Contenedor del mapa interactivo -->
					<div id="weather-map" aria-label="Mapa de clima y ubicación"></div>
				</div>
				<div class="preview-info card">
					<h3>Clima</h3>
					<p>Vista rápida: imagen satelital / drone y resumen meteorológico actual.</p>
					<ul>
						<li>Temperatura: <span id="temp-value">--</span></li>
						<li>Humedad: <span id="humidity-value">--</span></li>
						<li>Última imagen: <span id="satellite-updated">--</span></li>
					</ul>
					<button class="btn btn-primary">Ver detalle</button>
				</div>
			</div>
		</section>

		<section class="modules" aria-label="Módulos principales">
			

			<article id="modulo-mercado" class="card module-card" data-module="Mercado" tabindex="0">
				<h3>Mercado</h3>
				<p>Precios, tendencias y canales de venta.</p>
				<a href="mercado.php" class="btn btn-primary">Abrir</a>
			</article>

			<article id="modulo-chat-ia" class="card module-card" data-module="Chat IA" tabindex="0">
				<h3>Chat IA</h3>
				<p>Asistente para recomendaciones y diagnósticos.</p>
				<a href="ia/chat.php" class="btn btn-primary">Abrir</a>
			</article>

			<article id="modulo-exportacion" class="card module-card" data-module="Exportación" tabindex="0">
				<h3>Exportación</h3>
				<p>Exporta datos de cultivo a CSV/Excel y reportes.</p>
				<a href="exportacion.php" class="btn btn-primary">Abrir</a>
			</article>
			<article id="modulo-agua" class="card module-card" data-module="Cuerpos de agua" tabindex="0">
				<h3>Cuerpos de agua</h3>
				<p>Explora pozos, presas y cuerpos de agua de México con datos oficiales en un mapa interactivo.</p>
				<a href="agua.php" class="btn btn-primary">Abrir</a>
			</article>
		</section>

		<!-- Resumen rápido removido por solicitud del usuario -->
	</main>

	<footer class="site-footer">
		<div class="container">
			<small>© <?php echo date('Y'); ?> Optilife - Derechos Reservados</small>
		</div>
	</footer>

	<!-- Modal simple -->
	<div id="module-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="modal-title">
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
		$configPath = __DIR__ . '/../includes/owm_config.php';
		$owmKey = '';
		if (file_exists($configPath)) {
			$config = include $configPath;
			if (is_array($config) && isset($config['OWM_API_KEY'])) {
				$owmKey = $config['OWM_API_KEY'];
			}
		}
	?>
	<script>
		// Expone la API key de OpenWeatherMap al frontend (configurar variable de entorno OWM_API_KEY en el servidor)
		window.OWM_API_KEY = <?php echo json_encode($owmKey); ?>;
	</script>
	<script src="../recursos/js/class.js" defer></script>
</body>
</html>
