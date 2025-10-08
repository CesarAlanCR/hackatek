
// Interacciones básicas para los módulos
document.addEventListener('DOMContentLoaded', function(){
	const cards = document.querySelectorAll('.module-card');
	const modal = document.getElementById('module-modal');
	const modalTitle = document.getElementById('modal-title');
	const modalBody = document.getElementById('modal-body');
	const closeBtn = modal.querySelector('.modal-close');
    // Mapa y clima
	const mapContainer = document.getElementById('weather-map');
	const OWM_API_KEY = window.OWM_API_KEY || '';
	// Inicializa con fallback para evitar "Ubicación no disponible" si el usuario hace clic muy rápido
	let lastCoords = { lat: 9.7489, lon: -83.7534 }; // Fallback: Costa Rica centro aprox
	// UI Clima
	const tempEl = document.getElementById('temp-value');
	const humEl = document.getElementById('humidity-value');
	const satEl = document.getElementById('satellite-updated');
	if (tempEl) tempEl.textContent = 'Cargando...';
	if (humEl) humEl.textContent = 'Cargando...';
	if (satEl) satEl.textContent = 'Cargando...';

	function openModal(title, content){
		modalTitle.textContent = title;
		modalBody.innerHTML = '<p>'+content+'</p>';
		modal.setAttribute('aria-hidden','false');
		// Bloquear scroll de fondo
		document.body.style.overflow = 'hidden';
		// Desactivar interacción del mapa si existe
		try {
			const map = window.appState && window.appState.map;
			if (map) {
				if (map.dragging) map.dragging.disable();
				if (map.doubleClickZoom) map.doubleClickZoom.disable();
				if (map.scrollWheelZoom) map.scrollWheelZoom.disable();
				if (map.boxZoom) map.boxZoom.disable();
				if (map.keyboard) map.keyboard.disable();
				if (map.touchZoom) map.touchZoom.disable();
			}
		} catch (e) { console.warn('No se pudo deshabilitar interacciones del mapa', e); }
		// Recordar foco anterior y hacer focus en el botón de cerrar
		window.appState = window.appState || {};
		window.appState.lastFocus = document.activeElement;
		closeBtn.focus();
		// Focus trap básico dentro del modal
		const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
		const firstEl = focusable[0];
		const lastEl = focusable[focusable.length - 1];
		function handleTab(e){
			if (e.key !== 'Tab') return;
			if (e.shiftKey) {
				if (document.activeElement === firstEl) { e.preventDefault(); lastEl.focus(); }
			} else {
				if (document.activeElement === lastEl) { e.preventDefault(); firstEl.focus(); }
			}
		}
		modal.addEventListener('keydown', handleTab);
		window.appState.modalKeyHandler = handleTab;
	}

	function closeModal(){
		modal.setAttribute('aria-hidden','true');
		// Desbloquear scroll
		document.body.style.overflow = '';
		// Reactivar interacción del mapa si existe
		try {
			const map = window.appState && window.appState.map;
			if (map) {
				if (map.dragging) map.dragging.enable();
				if (map.doubleClickZoom) map.doubleClickZoom.enable();
				if (map.scrollWheelZoom) map.scrollWheelZoom.enable();
				if (map.boxZoom) map.boxZoom.enable();
				if (map.keyboard) map.keyboard.enable();
				if (map.touchZoom) map.touchZoom.enable();
			}
		} catch (e) { console.warn('No se pudo reactivar interacciones del mapa', e); }
		// Eliminar manejador de focus trap
		if (window.appState && window.appState.modalKeyHandler) {
			modal.removeEventListener('keydown', window.appState.modalKeyHandler);
			window.appState.modalKeyHandler = null;
		}
		// Restaurar foco anterior
		if (window.appState && window.appState.lastFocus && typeof window.appState.lastFocus.focus === 'function') {
			window.appState.lastFocus.focus();
		}
	}

	cards.forEach(card=>{
		const btn = card.querySelector('button');
		function handleOpen(){
			const name = card.getAttribute('data-module') || card.id || 'Módulo';
			openModal(name, 'Contenido inicial para el módulo "'+name+'". Aquí puedes añadir formularios, listados y gráficos.');
		}
		btn.addEventListener('click', handleOpen);
		card.addEventListener('keydown', function(e){
			if(e.key === 'Enter' || e.key === ' '){
				e.preventDefault(); handleOpen();
			}
		});
	});

	closeBtn.addEventListener('click', closeModal);
	modal.addEventListener('click', function(e){
		if(e.target === modal) closeModal();
	});
	document.addEventListener('keydown', function(e){
		if(e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false'){
			closeModal();
		}
	});

	// Inicializa mapa si existe el contenedor
	if (mapContainer && typeof L !== 'undefined') {
		initWeatherMap(mapContainer, OWM_API_KEY);
	} else {
		// Si Leaflet no está disponible, al menos intenta poblar el panel de clima con el centro por defecto
		if (OWM_API_KEY) {
			const fallback = { lat: 9.7489, lon: -83.7534 };
			lastCoords = fallback;
			fetchWeather(fallback.lat, fallback.lon, OWM_API_KEY)
				.then(updateWeatherUIFromData)
				.catch(err => showWeatherError(err));
		} else {
			showWeatherError('Falta API key');
		}
	}

	// Botón Ver detalle: mostrar tipo de suelo usando las últimas coords
	const btnDetalle = document.getElementById('btn-ver-detalle-clima');
	if (btnDetalle) {
		btnDetalle.addEventListener('click', async () => {
			if (!lastCoords) {
				openModal('Detalle de clima y suelo', 'Ubicación no disponible aún. Intenta nuevamente en unos segundos.');
				return;
			}
			const { lat, lon } = lastCoords;
			openModal('Detalle de clima y suelo', 'Consultando tipo de suelo...');
			try {
				const soil = await getSoilType(lat, lon);
				const tipoSuelo = soil?.soilType || 'Desconocido';
				const conf = typeof soil?.confidence === 'number' ? ` (conf. ${soil.confidence}%)` : '';
				modalBody.innerHTML = `
					<div>
						<p><strong>Ubicación:</strong> ${lat.toFixed(5)}, ${lon.toFixed(5)}</p>
						<p><strong>Tipo de suelo (WRB):</strong> ${tipoSuelo}${conf}</p>
					</div>
				`;
			} catch (e) {
				console.error('Error obteniendo tipo de suelo', e);
				modalBody.innerHTML = '<p>No se pudo obtener el tipo de suelo.</p>';
			}
		});
	}
});

/**
 * Inicializa un mapa Leaflet centrado en la ubicación del usuario y agrega overlays de OpenWeatherMap.
 * @param {HTMLElement} container - Contenedor del mapa (#weather-map)
 * @param {string} apiKey - API key de OpenWeatherMap
 */
function initWeatherMap(container, apiKey) {
	// Capa base OSM
	const map = L.map(container, { zoomControl: true, attributionControl: true });
	// Guardamos la referencia globalmente para poder desactivar interacciones cuando se abra el modal
	window.appState = window.appState || {};
	window.appState.map = map;

	const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
	}).addTo(map);

	// Overlays de OWM si hay API key
	const overlays = {};
	if (apiKey) {
		// Capa de nubosidad
		const clouds = L.tileLayer(
			`https://tile.openweathermap.org/map/clouds_new/{z}/{x}/{y}.png?appid=${apiKey}`,
			{ opacity: 0.6, attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>' }
		);
		// Capa de temperatura
		const temp = L.tileLayer(
			`https://tile.openweathermap.org/map/temp_new/{z}/{x}/{y}.png?appid=${apiKey}`,
			{ opacity: 0.5, attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>' }
		);
		overlays['Nubes'] = clouds;
		overlays['Temperatura'] = temp;
		clouds.addTo(map);
	}

	// Control de capas
	if (Object.keys(overlays).length) {
		L.control.layers({ 'OpenStreetMap': osm }, overlays, { collapsed: true }).addTo(map);
	}

	// Geolocalización del navegador
	const defaultCenter = [9.7489, -83.7534]; // Fallback: Costa Rica centro aprox
	const defaultZoom = 7;

	function setViewAndMarker(lat, lng, placeText) {
		const latlng = [lat, lng];
		map.setView(latlng, 12);
		const marker = L.marker(latlng).addTo(map);
		marker.bindPopup(placeText || 'Tu ubicación aproximada').openPopup();
		// Círculo de precisión si disponible
		if (navigator.geolocation && navigator.geolocation.getCurrentPosition) {
			// No tenemos accuracy directamente aquí sin la respuesta, se maneja en success
		}
	}

	if (navigator.geolocation && navigator.geolocation.getCurrentPosition) {
		navigator.geolocation.getCurrentPosition(function(pos){
			const { latitude, longitude, accuracy } = pos.coords;
			lastCoords = { lat: latitude, lon: longitude };
			const text = accuracy ? `Tu ubicación (±${Math.round(accuracy)} m)` : 'Tu ubicación aproximada';
			map.setView([latitude, longitude], 13);
			const marker = L.marker([latitude, longitude]).addTo(map);
			marker.bindPopup(text).openPopup();
			if (accuracy) {
				L.circle([latitude, longitude], { radius: accuracy, color: '#2f8f44', fillColor: '#2f8f44', fillOpacity: 0.15 }).addTo(map);
			}
			// Cargar clima y actualizar popup + UI
			if (apiKey) {
				fetchWeather(latitude, longitude, apiKey)
					.then(data => {
						const desc = data.weather?.[0]?.description || 'Clima';
						const temp = data.main?.temp != null ? Math.round(data.main.temp) : 'N/D';
						marker.setPopupContent(`${text}<br>${desc}, ${temp}°C`);
						updateWeatherUIFromData(data);
					})
					.catch(err => showWeatherError(err));
			} else {
				showWeatherError('Falta API key');
			}
		}, function(err){
			// Permiso denegado o error
			console.warn('Geolocalización no disponible:', err && err.message);
			map.setView(defaultCenter, defaultZoom);
			L.marker(defaultCenter).addTo(map).bindPopup('No se pudo obtener tu ubicación. Vista por defecto.');
			lastCoords = { lat: defaultCenter[0], lon: defaultCenter[1] };
			// Intento de obtener clima en fallback por centro
			if (apiKey) {
				fetchWeather(defaultCenter[0], defaultCenter[1], apiKey)
					.then(updateWeatherUIFromData)
					.catch(err => showWeatherError(err));
			} else {
				showWeatherError('Falta API key');
			}
		}, { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
	} else {
		map.setView(defaultCenter, defaultZoom);
		L.marker(defaultCenter).addTo(map).bindPopup('Geolocalización no soportada por tu navegador.');
		lastCoords = { lat: defaultCenter[0], lon: defaultCenter[1] };
		if (apiKey) {
			fetchWeather(defaultCenter[0], defaultCenter[1], apiKey)
				.then(updateWeatherUIFromData)
				.catch(err => showWeatherError(err));
		} else {
			showWeatherError('Falta API key');
		}
	}
}

// util: formatea "hace X" en español
function timeAgo(date) {
	const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
	const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
	const intervals = [
		{ sec: 60, unit: 'second' },
		{ sec: 60 * 60, unit: 'minute' },
		{ sec: 60 * 60 * 24, unit: 'hour' },
		{ sec: 60 * 60 * 24 * 7, unit: 'day' }
	];
	let unit = 'second';
	let value = -seconds;
	if (seconds >= 60 && seconds < 3600) { unit = 'minute'; value = -Math.floor(seconds / 60); }
	else if (seconds >= 3600 && seconds < 86400) { unit = 'hour'; value = -Math.floor(seconds / 3600); }
	else if (seconds >= 86400) { unit = 'day'; value = -Math.floor(seconds / 86400); }
	return rtf.format(value, unit);
}

// Llama a OpenWeatherMap y devuelve JSON de clima actual
function fetchWeather(lat, lon, apiKey) {
	const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&units=metric&lang=es&appid=${apiKey}`;
	console.debug('Consultando OWM:', url);
	return fetch(url).then(r => {
		if (!r.ok) {
			return r.text().then(t => {
				throw new Error(`OWM error ${r.status}: ${t}`);
			});
		}
		return r.json();
	});
}

// Actualiza los spans del panel Clima con la data de OWM
function updateWeatherUIFromData(data) {
	try {
		const tempEl = document.getElementById('temp-value');
		const humEl = document.getElementById('humidity-value');
		const satEl = document.getElementById('satellite-updated');
		if (tempEl) {
			if (data.main?.temp != null) tempEl.textContent = `${Math.round(data.main.temp)} °C`;
			else tempEl.textContent = 'N/D';
		}
		if (humEl) {
			if (data.main?.humidity != null) humEl.textContent = `${data.main.humidity} %`;
			else humEl.textContent = 'N/D';
		}
		if (satEl) {
			const dt = data.dt ? new Date(data.dt * 1000) : new Date();
			satEl.textContent = timeAgo(dt);
			satEl.setAttribute('title', dt.toLocaleString());
		}
	} catch (e) {
		showWeatherError(e);
	}
}

// Muestra mensajes de error amigables en el panel Clima
function showWeatherError(err) {
	const tempEl = document.getElementById('temp-value');
	const humEl = document.getElementById('humidity-value');
	const satEl = document.getElementById('satellite-updated');
	if (tempEl && tempEl.textContent === 'Cargando...') tempEl.textContent = 'No disponible';
	if (humEl && humEl.textContent === 'Cargando...') humEl.textContent = 'No disponible';
	if (satEl && satEl.textContent === 'Cargando...') satEl.textContent = 'No disponible';
	console.error('Error al obtener clima:', err);
}

// Consulta tipo de suelo vía proxy del servidor (SoilGrids)
async function getSoilType(lat, lon) {
	const url = `../includes/soilgrids_proxy.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`;
	const res = await fetch(url);
	if (!res.ok) {
		const txt = await res.text();
		throw new Error(`Soil proxy error ${res.status}: ${txt}`);
	}
	return res.json();
}
