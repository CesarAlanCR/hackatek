// Interacciones básicas para los módulos
document.addEventListener('DOMContentLoaded', function(){
	console.log('🚀 JavaScript cargado - DOMContentLoaded ejecutándose');
	
	const cards = document.querySelectorAll('.module-card');
	const modal = document.getElementById('module-modal');
	const modalTitle = document.getElementById('modal-title');
	const modalBody = document.getElementById('modal-body');
	const closeBtn = modal.querySelector('.modal-close');
    
    // Mapa y clima
	const mapContainer = document.getElementById('weather-map');
	const OWM_API_KEY = window.OWM_API_KEY || '';
	
	console.log('🗺️ Contenedor del mapa:', mapContainer);
	console.log('🔑 API Key:', OWM_API_KEY ? 'Disponible' : 'Falta');
	console.log('📦 Leaflet disponible:', typeof L !== 'undefined');
	
	// Estado global de la aplicación
	window.appState = window.appState || {};
	if (!window.appState.lastCoords) {
		window.appState.lastCoords = { lat: 9.7489, lon: -83.7534 }; // Fallback inicial
	}
	
	// UI Clima - inicializar con "Cargando..."
	const tempEl = document.getElementById('temp-value');
	const humEl = document.getElementById('humidity-value');
	const satEl = document.getElementById('satellite-updated');
	if (tempEl) tempEl.textContent = 'Cargando...';
	if (humEl) humEl.textContent = 'Cargando...';
	if (satEl) satEl.textContent = 'Cargando...';

	console.log('🌡️ Elementos clima encontrados:', {
		temp: !!tempEl,
		humidity: !!humEl,
		satellite: !!satEl
	});

	function openModal(title, content){
		modalTitle.textContent = title;
		modalBody.innerHTML = '<p>'+content+'</p>';
		modal.setAttribute('aria-hidden','false');
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
		
		// Focus management
		window.appState.lastFocus = document.activeElement;
		closeBtn.focus();
		
		// Focus trap básico
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
		document.body.style.overflow = '';
		
		// Reactivar interacción del mapa
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
		
		// Limpiar focus trap
		if (window.appState && window.appState.modalKeyHandler) {
			modal.removeEventListener('keydown', window.appState.modalKeyHandler);
			window.appState.modalKeyHandler = null;
		}
		
		// Restaurar foco
		if (window.appState && window.appState.lastFocus && typeof window.appState.lastFocus.focus === 'function') {
			window.appState.lastFocus.focus();
		}
	}

	// Eventos de módulos
	cards.forEach(card => {
		const btn = card.querySelector('button');
		function handleOpen(){
			const name = card.getAttribute('data-module') || card.id || 'Módulo';
			openModal(name, 'Contenido inicial para el módulo "'+name+'". Aquí puedes añadir formularios, listados y gráficos.');
		}
		if (btn) {
			btn.addEventListener('click', handleOpen);
		}
		card.addEventListener('keydown', function(e){
			if(e.key === 'Enter' || e.key === ' '){
				e.preventDefault(); 
				handleOpen();
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

	// Función para reintentar inicialización del mapa
	function tryInitMap(attempt = 1) {
		if (!mapContainer) {
			console.warn('No hay contenedor de mapa');
			return;
		}
		if (typeof L === 'undefined') {
			if (attempt <= 3) {
				console.log(`Leaflet aún no disponible. Reintentando (${attempt}/3)...`);
				return setTimeout(() => tryInitMap(attempt + 1), 500);
			}
			console.error('Leaflet no se cargó tras 3 reintentos');
			return;
		}
		console.log('✅ Inicializando mapa con Leaflet');
		try { 
			initWeatherMap(mapContainer, OWM_API_KEY); 
		} catch(e) { 
			console.error('Error al inicializar mapa:', e);
		} 
	}

	// Inicializar mapa
	if (mapContainer && typeof L !== 'undefined') {
		console.log('✅ Leaflet disponible, inicializando mapa...');
		try {
			initWeatherMap(mapContainer, OWM_API_KEY);
		} catch (e) {
			console.error('❌ Error al inicializar mapa:', e);
		}
	} else {
		if (!mapContainer) {
			console.error('❌ Contenedor del mapa no encontrado (#weather-map)');
		}
		if (typeof L === 'undefined') {
			console.log('⏳ Leaflet no disponible, intentando reintento...');
			tryInitMap();
		}
		
		// Fallback: cargar clima sin mapa
		if (OWM_API_KEY) {
			console.log('🔄 Cargando clima sin mapa...');
			const fallback = { lat: 9.7489, lon: -83.7534 };
			window.appState.lastCoords = fallback;
			fetchWeather(fallback.lat, fallback.lon, OWM_API_KEY)
				.then(updateWeatherUIFromData)
				.catch(err => showWeatherError(err));
		} else {
			showWeatherError('Falta API key');
		}
	}

	// Botón Ver detalle
	const btnDetalle = document.getElementById('btn-ver-detalle-clima');
	if (btnDetalle) {
		console.log('✅ Botón "Ver detalle" encontrado');
		btnDetalle.addEventListener('click', () => {
			console.log('🔘 Clic en "Ver detalle"');
			openModal('Detalle de clima y suelo', 'Obteniendo tu ubicación...');
			
			if (!navigator.geolocation) {
				modalBody.innerHTML = '<p>Geolocalización no soportada por tu navegador.</p>';
				return;
			}
			
			navigator.geolocation.getCurrentPosition(async (pos) => {
				const lat = pos.coords.latitude;
				const lon = pos.coords.longitude;
				window.appState.lastCoords = { lat, lon };
				
				try {
					modalBody.innerHTML = '<p>Consultando información de clima y suelo...</p>';
					
					// Obtener datos de suelo y clima en paralelo
					const [soil, weather] = await Promise.all([
						getINEGISoilType(lat, lon),
						fetchExtendedWeather(lat, lon, OWM_API_KEY)
					]);
					
					// Información de suelo
					const tipoSuelo = soil?.soilType || 'Desconocido';
					const codigo = soil?.soilCode ? ` (${soil.soilCode})` : '';
					const desc = soil?.description || 'Sin descripción disponible';
					
					// Determinar región
					let region = 'Centro/Altiplano mexicano';
					if (lat > 27 && lat < 32 && lon < -108 && lon > -116) region = 'Desierto de Sonora';
					else if (lat > 28) region = 'Norte de México (zona árida)';
					else if (lat < 22 && lon > -92) region = 'Península de Yucatán';
					else if (lon > -98 && lat < 26) region = 'Costa del Golfo';
					else if (lat > 18 && lat < 22 && lon > -104 && lon < -96) region = 'Eje Neovolcánico';
					else if (lat < 20) region = 'Sur tropical';
					
					// Información de clima
					const condition = weather.weather?.[0]?.description || 'N/D';
					const currentTemp = weather.main?.temp != null ? Math.round(weather.main.temp) : 'N/D';
					const humidity = weather.main?.humidity != null ? weather.main.humidity : 'N/D';
					const pressure = weather.main?.pressure != null ? weather.main.pressure : 'N/D';
					const visibility = weather.visibility != null ? (weather.visibility / 1000).toFixed(1) : 'N/D';
					const currentWind = weather.wind?.speed != null ? (weather.wind.speed * 3.6).toFixed(1) : 'N/D';
					const windDir = weather.wind?.deg != null ? weather.wind.deg : null;
					
					// Datos extendidos (si están disponibles)
					const tempMax = weather.extended?.tempMax != null ? Math.round(weather.extended.tempMax) : currentTemp;
					const tempMin = weather.extended?.tempMin != null ? Math.round(weather.extended.tempMin) : currentTemp;
					const precipitation = weather.extended?.precipitation || 0;
					const maxWind = weather.extended?.maxWindSpeed != null ? (weather.extended.maxWindSpeed * 3.6).toFixed(1) : currentWind;
					const rainProb = weather.extended?.rainProbability != null ? Math.round(weather.extended.rainProbability) : 0;
					
					// Dirección del viento
					const getWindDirection = (deg) => {
						if (!deg) return '';
						const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
						const index = Math.round(deg / 22.5) % 16;
						return directions[index];
					};
					
					const windDirection = windDir ? ` (${getWindDirection(windDir)})` : '';
					
					modalBody.innerHTML = `
						<div style="max-height: 70vh; overflow-y: auto;">
							<!-- Información de Ubicación -->
							<div class="section-divider location-section">
								<h4 class="section-title location-title">📍 Ubicación</h4>
								<div class="detail-item"><strong>Coordenadas:</strong> ${lat.toFixed(5)}, ${lon.toFixed(5)}</div>
								<div class="detail-item"><strong>Región:</strong> ${region}</div>
							</div>
							
							<!-- Información de Clima -->
							<div class="section-divider climate-section">
								<h4 class="section-title climate-title">🌤️ Condiciones Climáticas</h4>
								<div class="detail-grid">
									<div class="detail-item"><strong>Condición:</strong> ${condition}</div>
									<div class="detail-item"><strong>Temperatura:</strong> ${currentTemp}°C</div>
									<div class="detail-item"><strong>Temp. máxima:</strong> ${tempMax}°C</div>
									<div class="detail-item"><strong>Temp. mínima:</strong> ${tempMin}°C</div>
									<div class="detail-item"><strong>Humedad:</strong> ${humidity}%</div>
									<div class="detail-item"><strong>Presión:</strong> ${pressure} hPa</div>
									<div class="detail-item"><strong>Precipitación total:</strong> ${precipitation.toFixed(1)} mm</div>
									<div class="detail-item"><strong>Prob. de lluvia:</strong> ${rainProb}%</div>
									<div class="detail-item"><strong>Viento actual:</strong> ${currentWind} km/h${windDirection}</div>
									<div class="detail-item"><strong>Viento máximo:</strong> ${maxWind} km/h</div>
									<div class="detail-item"><strong>Visibilidad:</strong> ${visibility} km</div>
								</div>
							</div>
							
							<!-- Información de Suelo -->
							<div class="section-divider soil-section">
								<h4 class="section-title soil-title">🌱 Tipo de Suelo</h4>
								<div class="detail-item"><strong>Clasificación:</strong> ${tipoSuelo}${codigo}</div>
								<div style="margin-top:12px;padding:12px;border-radius:8px;background:rgba(255, 152, 0, 0.1);border:1px solid rgba(255, 152, 0, 0.3);color:var(--text-primary);">
									<div style="margin:0 0 6px 0;font-weight:600;">Características:</div>
									<div style="margin:0;line-height:1.5;">${desc}</div>
								</div>
							</div>
							
							<div style="margin-top:20px;font-size:0.85rem;color:var(--text-muted);text-align:center;padding:10px;border-top:1px solid var(--border);">
								Fuentes: OpenWeatherMap • INEGI México
							</div>
						</div>
					`;
				} catch (e) {
					console.error('Error obteniendo información:', e);
					modalBody.innerHTML = `<p>Error obteniendo información: ${e.message}</p>`;
				}
			}, (err) => {
				console.error('Error de geolocalización:', err);
				modalBody.innerHTML = `<p>No se pudo obtener la ubicación: ${err.message}</p>`;
			}, { 
				enableHighAccuracy: true, 
				timeout: 10000, 
				maximumAge: 60000 
			});
		});
	} else {
		console.warn('❌ Botón "Ver detalle" no encontrado');
	}
});

/**
 * Inicializa un mapa Leaflet centrado en la ubicación del usuario y agrega overlays de OpenWeatherMap.
 */
function initWeatherMap(container, apiKey) {
	console.log('🗺️ Iniciando mapa Leaflet...');
	
	// Crear mapa
	const map = L.map(container, { zoomControl: true, attributionControl: true });
	window.appState = window.appState || {};
	window.appState.map = map;

	// Capa base
	const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
	}).addTo(map);

	// Overlays de clima si hay API key
	const overlays = {};
	if (apiKey) {
		const clouds = L.tileLayer(
			`https://tile.openweathermap.org/map/clouds_new/{z}/{x}/{y}.png?appid=${apiKey}`,
			{ opacity: 0.6, attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>' }
		);
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

	// Geolocalización
	const defaultCenter = [9.7489, -83.7534];
	const defaultZoom = 7;

	if (navigator.geolocation) {
		navigator.geolocation.getCurrentPosition(function(pos){
			const { latitude, longitude, accuracy } = pos.coords;
			console.log('📍 Ubicación obtenida:', { latitude, longitude });
			window.appState.lastCoords = { lat: latitude, lon: longitude };
			
			const text = accuracy ? `Tu ubicación (±${Math.round(accuracy)} m)` : 'Tu ubicación aproximada';
			map.setView([latitude, longitude], 13);
			const marker = L.marker([latitude, longitude]).addTo(map);
			marker.bindPopup(text).openPopup();
			
			if (accuracy) {
				L.circle([latitude, longitude], { 
					radius: accuracy, 
					color: '#2f8f44', 
					fillColor: '#2f8f44', 
					fillOpacity: 0.15 
				}).addTo(map);
			}
			
			// Cargar clima
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
			console.warn('⚠️ Error de geolocalización:', err && err.message);
			map.setView(defaultCenter, defaultZoom);
			L.marker(defaultCenter).addTo(map).bindPopup('No se pudo obtener tu ubicación. Vista por defecto.');
			window.appState.lastCoords = { lat: defaultCenter[0], lon: defaultCenter[1] };
			
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
		window.appState.lastCoords = { lat: defaultCenter[0], lon: defaultCenter[1] };
		
		if (apiKey) {
			fetchWeather(defaultCenter[0], defaultCenter[1], apiKey)
				.then(updateWeatherUIFromData)
				.catch(err => showWeatherError(err));
		} else {
			showWeatherError('Falta API key');
		}
	}
}

// Utilidad para formatear tiempo relativo
function timeAgo(date) {
	const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
	const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
	
	let unit = 'second';
	let value = -seconds;
	if (seconds >= 60 && seconds < 3600) { 
		unit = 'minute'; 
		value = -Math.floor(seconds / 60); 
	} else if (seconds >= 3600 && seconds < 86400) { 
		unit = 'hour'; 
		value = -Math.floor(seconds / 3600); 
	} else if (seconds >= 86400) { 
		unit = 'day'; 
		value = -Math.floor(seconds / 86400); 
	}
	
	return rtf.format(value, unit);
}

// Consulta OpenWeatherMap
function fetchWeather(lat, lon, apiKey) {
	const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&units=metric&lang=es&appid=${apiKey}`;
	console.log('🌤️ Consultando clima para:', { lat, lon });
	
	return fetch(url).then(r => {
		if (!r.ok) {
			return r.text().then(t => {
				throw new Error(`OWM error ${r.status}: ${t}`);
			});
		}
		return r.json();
	}).catch(err => {
		console.error('❌ Error en fetchWeather:', err);
		throw err;
	});
}

// Consulta datos extendidos de clima (incluye pronóstico para temp máx/mín)
async function fetchExtendedWeather(lat, lon, apiKey) {
	try {
		// Obtener clima actual
		const currentWeather = await fetchWeather(lat, lon, apiKey);
		
		// Obtener pronóstico para temp máx/mín de hoy
		const forecastUrl = `https://api.openweathermap.org/data/2.5/forecast?lat=${lat}&lon=${lon}&units=metric&lang=es&appid=${apiKey}`;
		const forecastResponse = await fetch(forecastUrl);
		
		if (!forecastResponse.ok) {
			console.warn('No se pudo obtener pronóstico, usando solo datos actuales');
			return currentWeather;
		}
		
		const forecastData = await forecastResponse.json();
		
		// Extraer datos del día actual del pronóstico
		const today = new Date().toDateString();
		const todayForecasts = forecastData.list.filter(item => {
			const itemDate = new Date(item.dt * 1000).toDateString();
			return itemDate === today;
		});
		
		// Calcular temp máx/mín del día
		let tempMax = currentWeather.main.temp;
		let tempMin = currentWeather.main.temp;
		let totalPrecip = 0;
		let maxWindSpeed = currentWeather.wind?.speed || 0;
		let rainProb = 0;
		
		todayForecasts.forEach(forecast => {
			tempMax = Math.max(tempMax, forecast.main.temp_max);
			tempMin = Math.min(tempMin, forecast.main.temp_min);
			if (forecast.rain?.['3h']) {
				totalPrecip += forecast.rain['3h'];
			}
			if (forecast.wind?.speed) {
				maxWindSpeed = Math.max(maxWindSpeed, forecast.wind.speed);
			}
			if (forecast.pop) {
				rainProb = Math.max(rainProb, forecast.pop * 100);
			}
		});
		
		// Combinar datos actuales con datos extendidos
		return {
			...currentWeather,
			extended: {
				tempMax,
				tempMin,
				precipitation: totalPrecip,
				maxWindSpeed,
				rainProbability: rainProb
			}
		};
	} catch (err) {
		console.error('❌ Error obteniendo clima extendido:', err);
		// Fallback a datos básicos
		return fetchWeather(lat, lon, apiKey);
	}
}

// Actualiza UI del clima
function updateWeatherUIFromData(data) {
	try {
		const tempEl = document.getElementById('temp-value');
		const humEl = document.getElementById('humidity-value');
		const satEl = document.getElementById('satellite-updated');
		
		if (tempEl) {
			if (data.main?.temp != null) {
				tempEl.textContent = `${Math.round(data.main.temp)} °C`;
			} else {
				tempEl.textContent = 'N/D';
			}
		}
		if (humEl) {
			if (data.main?.humidity != null) {
				humEl.textContent = `${data.main.humidity} %`;
			} else {
				humEl.textContent = 'N/D';
			}
		}
		if (satEl) {
			const dt = data.dt ? new Date(data.dt * 1000) : new Date();
			satEl.textContent = timeAgo(dt);
			satEl.setAttribute('title', dt.toLocaleString());
		}
	} catch (e) {
		console.error('❌ Error actualizando UI clima:', e);
		showWeatherError(e);
	}
}

// Muestra errores de clima
function showWeatherError(err) {
	const tempEl = document.getElementById('temp-value');
	const humEl = document.getElementById('humidity-value');
	const satEl = document.getElementById('satellite-updated');
	
	if (tempEl && tempEl.textContent === 'Cargando...') tempEl.textContent = 'No disponible';
	if (humEl && humEl.textContent === 'Cargando...') humEl.textContent = 'No disponible';
	if (satEl && satEl.textContent === 'Cargando...') satEl.textContent = 'No disponible';
	
	console.error('Error al obtener clima:', err);
}

// Consulta tipo de suelo vía proxy INEGI
async function getINEGISoilType(lat, lon) {
	const url = `../includes/inegi_soil_proxy.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`;
	const res = await fetch(url);
	if (!res.ok) {
		const txt = await res.text();
		throw new Error(`INEGI proxy error ${res.status}: ${txt}`);
	}
	return res.json();
}