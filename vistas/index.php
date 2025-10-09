
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
					<button id="btn-ver-detalle-clima" class="btn btn-primary">Ver detalle</button>
				</div>
			</div>
		</section>

		<section class="modules" aria-label="Módulos principales">
			<article id="modulo-clima" class="card module-card" data-module="Clima" tabindex="0">
				<h3>Imagenes</h3>
				<p>IA que reconoce imagenes de hojas afectadas para ver que pasa</p>
				<button class="btn btn-primary">Abrir</button>
			</article>

			<article id="modulo-mercado" class="card module-card" data-module="Mercado" tabindex="0">
				<h3>Mercado</h3>
				<p>Precios, tendencias y canales de venta.</p>
				<button class="btn btn-primary">Abrir</button>
			</article>

			<article id="modulo-chat-ia" class="card module-card" data-module="Chat IA" tabindex="0">
				<h3>Chat IA</h3>
				<p>Asistente para recomendaciones y diagnósticos.</p>
				<a href="ia/chat.php" class="btn btn-primary">Abrir</a>
			</article>

			<article id="modulo-exportacion" class="card module-card" data-module="Exportación" tabindex="0">
				<h3>Exportación</h3>
				<p>Exporta datos de cultivo a CSV/Excel y reportes.</p>
				<button class="btn btn-primary">Abrir</button>
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
			<h3 id="modal-title" class="modal-title"></h3>
			<div id="modal-body" class="modal-body"></div>
			<div class="modal-footer">
				<button class="btn" id="modal-cancel">Cerrar</button>
				<button class="btn btn-primary" id="modal-action">Aceptar</button>
			</div>
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
	// Modal behavior: open/close, populate content, accessibility helpers
	(function(){
		const modal = document.getElementById('module-modal');
		const modalContent = modal.querySelector('.modal-content');
		const btnClose = modal.querySelector('.modal-close');
		const btnCancel = document.getElementById('modal-cancel');
		const btnAction = document.getElementById('modal-action');
		const titleEl = document.getElementById('modal-title');
		const bodyEl = document.getElementById('modal-body');
		let lastFocused = null;

		function openModal(title, htmlContent, actionLabel, actionHandler){
			lastFocused = document.activeElement;
			titleEl.textContent = title || '';
			bodyEl.innerHTML = htmlContent || '';
			btnAction.textContent = actionLabel || 'Aceptar';
			btnAction.onclick = function(e){ if (typeof actionHandler === 'function') actionHandler(e); closeModal(); };
			modal.setAttribute('aria-hidden', 'false');
			setTimeout(()=> modalContent.focus(), 50);
			// lock scroll on body
			document.documentElement.style.overflow = 'hidden';
		}

		function closeModal(){
			modal.setAttribute('aria-hidden', 'true');
			// restore focus
			document.documentElement.style.overflow = '';
			if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
		}

		// close handlers
		btnClose.addEventListener('click', closeModal);
		btnCancel.addEventListener('click', closeModal);
		modal.addEventListener('click', function(e){
			if (e.target === modal) closeModal(); // backdrop click
		});
		document.addEventListener('keydown', function(e){
			if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
		});

		// Wire climate detail button
		const climaBtn = document.getElementById('btn-ver-detalle-clima');
		if (climaBtn){
			climaBtn.addEventListener('click', function(){
				openModal('Detalle del clima', '<p>Información meteorológica extendida, imágenes satelitales y análisis de cultivos.</p>');
			});
		}

		// Wire module buttons to show a simple info modal
		document.querySelectorAll('.module-card .btn').forEach(btn => {
			btn.addEventListener('click', function(e){
				e.preventDefault();
				const card = btn.closest('.module-card');
				const title = card ? (card.querySelector('h3') ? card.querySelector('h3').textContent : 'Detalle') : 'Detalle';
				const desc = card ? (card.querySelector('p') ? card.querySelector('p').innerHTML : '') : '';
				openModal(title, '<div>' + desc + '</div>');
			});
		});
	})();
	</script>
</body>
</html>
