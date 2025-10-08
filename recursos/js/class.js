
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

	// Botón Ver detalle: solicitar ubicación específica para consulta de suelo
	const btnDetalle = document.getElementById('btn-ver-detalle-clima');
	if (btnDetalle) {
		btnDetalle.addEventListener('click', async () => {
			openModal('Detalle de clima y suelo', 'Solicitando acceso a tu ubicación...');
			
			// Solicitar geolocalización específicamente para esta consulta
			if (!navigator.geolocation) {
				modalBody.innerHTML = '<p>Tu navegador no soporta geolocalización.</p>';
				return;
			}
			
			navigator.geolocation.getCurrentPosition(
				async (position) => {
					const lat = position.coords.latitude;
					const lon = position.coords.longitude;
					
					try {
						modalBody.innerHTML = '<p>Consultando tipo de suelo INEGI...</p>';
						const soil = await getINEGISoilType(lat, lon);
						const tipoSuelo = soil?.soilType || 'Desconocido';
						const codigo = soil?.soilCode ? ` (${soil.soilCode})` : '';
						const desc = soil?.description || 'Sin descripción disponible';
						const isClimateEstimate = soil?.source?.includes('climática') || soil?.debug?.used_climate_fallback;
						
						// Determinar región geográfica para mostrar contexto
						let region = 'Ubicación desconocida';
						if (lat > 27 && lat < 32 && lon < -108 && lon > -116) {
							region = 'Desierto de Sonora';
						} else if (lat > 28) {
							region = 'Norte de México (zona árida)';
						} else if (lat < 22 && lon > -92) {
							region = 'Península de Yucatán';
						} else if (lon > -98 && lat < 26) {
							region = 'Costa del Golfo';
						} else if (lat > 18 && lat < 22 && lon > -104 && lon < -96) {
							region = 'Eje Neovolcánico';
						} else if (lat < 20) {
							region = 'Sur tropical';
						} else {
							region = 'Centro/Altiplano mexicano';
						}
						
						modalBody.innerHTML = `
							<div>
								<p><strong>Ubicación:</strong> ${lat.toFixed(5)}, ${lon.toFixed(5)}</p>
								<p><strong>Región:</strong> ${region}</p>
								<p><strong>Tipo de suelo:</strong> ${tipoSuelo}${codigo}</p>
								${isClimateEstimate ? '<p style="color:#f57c00;font-size:0.9em;"><i>⚠️ Estimación basada en ubicación geográfica</i></p>' : ''}
								<div class="soil-explain" style="margin-top:10px;padding:10px;border-radius:8px;background:#e8f5e9;border:1px solid #4CAF50;color:#333;">
									<p style="margin:0 0 6px 0;"><strong>Características:</strong></p>
									<p style="margin:0;">${desc}</p>
								</div>
								<p style="margin-top:8px;font-size:0.9em;color:#666;">Fuente: Estimación climática (INEGI)</p>
							</div>
						`;
					} catch (e) {
						console.error('Error obteniendo tipo de suelo', e);
						modalBody.innerHTML = `
							<div>
								<p><strong>Error:</strong> No se pudo obtener el tipo de suelo</p>
								<p><strong>Ubicación consultada:</strong> ${lat.toFixed(5)}, ${lon.toFixed(5)}</p>
								<p><strong>Detalles del error:</strong> ${e.message}</p>
								<button id="retry-soil" class="btn btn-primary" style="margin-top:10px;padding:6px 12px;">Reintentar</button>
							</div>
						`;
						
						// Agregar evento al botón reintentar
						setTimeout(() => {
							const retryBtn = document.getElementById('retry-soil');
							if (retryBtn) {
								retryBtn.addEventListener('click', async () => {
									modalBody.innerHTML = '<p>Reintentando consulta...</p>';
									try {
										const soil = await getINEGISoilType(lat, lon);
										const tipoSuelo = soil?.soilType || 'Desconocido';
										const codigo = soil?.soilCode ? ` (${soil.soilCode})` : '';
										const desc = soil?.description || 'Sin descripción disponible';
										const isClimateEstimate = soil?.source?.includes('climática') || soil?.debug?.used_climate_fallback;
										
										// Determinar región geográfica para mostrar contexto
										let region = 'Ubicación desconocida';
										if (lat > 27 && lat < 32 && lon < -108 && lon > -116) {
											region = 'Desierto de Sonora';
										} else if (lat > 28) {
											region = 'Norte de México (zona árida)';
										} else if (lat < 22 && lon > -92) {
											region = 'Península de Yucatán';
										} else if (lon > -98 && lat < 26) {
											region = 'Costa del Golfo';
										} else if (lat > 18 && lat < 22 && lon > -104 && lon < -96) {
											region = 'Eje Neovolcánico';
										} else if (lat < 20) {
											region = 'Sur tropical';
										} else {
											region = 'Centro/Altiplano mexicano';
										}
										
										modalBody.innerHTML = `
											<div>
												<p><strong>Ubicación:</strong> ${lat.toFixed(5)}, ${lon.toFixed(5)}</p>
												<p><strong>Región:</strong> ${region}</p>
												<p><strong>Tipo de suelo:</strong> ${tipoSuelo}${codigo}</p>
												${isClimateEstimate ? '<p style="color:#f57c00;font-size:0.9em;"><i>⚠️ Estimación basada en ubicación geográfica</i></p>' : ''}
												<div class="soil-explain" style="margin-top:10px;padding:10px;border-radius:8px;background:#e8f5e9;border:1px solid #4CAF50;color:#333;">
													<p style="margin:0 0 6px 0;"><strong>Características:</strong></p>
													<p style="margin:0;">${desc}</p>
												</div>
												<p style="margin-top:8px;font-size:0.9em;color:#666;">Fuente: Estimación climática (INEGI)</p>
											</div>
										`;
									} catch (e2) {
										modalBody.innerHTML = `<p>Error persistente: ${e2.message}</p><p>Consulta la consola del navegador para más detalles.</p>`;
										console.error('Error en reintento:', e2);
									}
								});
							}
						}, 0);
					}
				},
				(error) => {
					let errorMsg = 'No se pudo obtener tu ubicación. ';
					switch(error.code) {
						case error.PERMISSION_DENIED:
							errorMsg += 'Has denegado el permiso de ubicación.';
							break;
						case error.POSITION_UNAVAILABLE:
							errorMsg += 'La información de ubicación no está disponible.';
							break;
						case error.TIMEOUT:
							errorMsg += 'La solicitud de ubicación ha expirado.';
							break;
						default:
							errorMsg += 'Error desconocido.';
							break;
					}
					modalBody.innerHTML = `<p>${errorMsg}</p><p>Para obtener el tipo de suelo, necesitamos acceso a tu ubicación actual.</p>`;
				},
				{
					enableHighAccuracy: true,
					timeout: 10000,
					maximumAge: 60000 // Aceptar ubicación de hasta 1 minuto de antigüedad
				}
			);
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

// Integración opcional con WeatherAPI para pruebas del snippet del usuario
async function fetchWeatherAPI(lat, lon) {
    const key = window.WEATHERAPI_KEY;
    if (!key) throw new Error('WEATHERAPI_KEY no configurada');
    const url = `https://api.weatherapi.com/v1/current.json?key=${encodeURIComponent(key)}&q=${encodeURIComponent(lat+','+lon)}`;
    console.debug('Consultando WeatherAPI:', url);
    const r = await fetch(url);
    if (!r.ok) {
        const txt = await r.text();
        throw new Error(`WeatherAPI error ${r.status}: ${txt}`);
    }
    return r.json();
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

// Consulta tipo de suelo vía proxy del servidor (INEGI México)
async function getINEGISoilType(lat, lon) {
	const url = `../includes/inegi_soil_proxy.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`;
	const res = await fetch(url);
	if (!res.ok) {
		const txt = await res.text();
		throw new Error(`INEGI proxy error ${res.status}: ${txt}`);
	}
	return res.json();
}

// Mapea clases WRB comunes a una descripción en español
function describeWRB(name) {
	if (!name || typeof name !== 'string') return 'No se pudo determinar la clase del suelo para este punto.';
	const key = name.trim().toLowerCase();
	const map = {
		'calcisols': 'Suelos con acumulación de carbonatos (cal), típicos de climas áridos o semiáridos; suelen tener limitaciones por calcificación y baja materia orgánica.',
		'cambisols': 'Suelos jóvenes con desarrollo cambico; generalmente bien drenados y de fertilidad moderada, aptos para diversos usos agrícolas con manejo adecuado.',
		'chernozems': 'Suelos muy fértiles, ricos en materia orgánica (negros), comunes en praderas templadas; alta productividad agrícola.',
		'luvisols': 'Suelos con acumulación de arcillas (iluviación), normalmente de buena fertilidad pero con posible compactación en horizontes subsuperficiales.',
		'phaeozems': 'Suelos oscuros y fértiles, con alto contenido de materia orgánica; similares a Chernozems en regiones más húmedas.',
		'kastanozems': 'Suelos de estepa, ricos en bases y con horizonte superior castaño; fertilidad buena a moderada.',
		'ferralsols': 'Suelos muy meteorizados y ácidos de regiones tropicales húmedas; baja fertilidad natural, requieren manejo y fertilización.',
		'acrisols': 'Suelos ácidos y lixiviados con alta saturación de aluminio; limitaciones de fertilidad y toxicidad de Al sin enmiendas.',
		'andosols': 'Suelos volcánicos, porosos y bien drenados; muy productivos pero pueden fijar fósforo, requiriendo manejo específico.',
		'vertisols': 'Suelos con alta arcilla expandible; agrietamiento estacional, drenaje lento y manejo difícil, pero buena fertilidad.',
		'gleysols': 'Suelos saturados de agua con condiciones reductoras (hidromorfía); limitaciones severas de drenaje.',
		'fluvisols': 'Suelos aluviales jóvenes en llanuras de inundación; fertilidad variable, a menudo buena con riesgo de inundación.',
		'leptosols': 'Suelos someros sobre roca o material duro; poca profundidad efectiva, limitaciones para raíces y retención de agua.',
		'regosols': 'Suelos muy poco desarrollados, a menudo arenosos o esqueléticos; baja fertilidad y capacidad de retención.',
		'arenosols': 'Suelos dominados por arena; excelente drenaje pero baja retención de agua y nutrientes, requieren fertilización frecuente.',
		'lixisols': 'Suelos lixiviados con baja saturación de bases; fertilidad moderada a baja, respuesta a encalado y fertilización.',
		'nitisols': 'Suelos rojizos profundos, bien estructurados y drenados; muy aptos para agricultura con buen manejo.' ,
		'planosols': 'Suelos con horizontes endurecidos o densos que restringen el drenaje; riesgo de encharcamiento estacional.',
		'podzols': 'Suelos ácidos con lavado intenso; baja fertilidad natural, comunes bajo bosques de coníferas.',
		'solonchaks': 'Suelos salinos; requieren manejo de salinidad y drenaje para uso agrícola.',
		'solonetz': 'Suelos sódicos con estructura densa; problemas de infiltración y aireación, requieren enmiendas (yeso) y drenaje.',
		'umbrisols': 'Suelos con horizontes superficiales oscuros ricos en materia orgánica, típicos de climas húmedos y fríos.',
		'histosols': 'Suelos orgánicos (turberas); muy alta materia orgánica, saturados frecuentemente, limitaciones para mecanización.',
		'plinthosols': 'Suelos con plintita (óxidos de Fe endurecibles); limitaciones para raíces y drenaje en condiciones alternantes.',
	};
	// Normaliza plurales mínimos (p.ej., "Calcisol" -> "calcisols")
	let norm = key;
	if (!map[norm] && !norm.endsWith('s')) norm = norm + 's';
	return map[norm] || 'Clase WRB detectada sin descripción específica disponible. Considera un análisis local para recomendaciones de manejo.';
}

// Demo: estimar tipo de suelo a partir de temperatura (como el snippet del usuario)
function estimateSoilFromTemp(temperature) {
	const res = { type: 'Desconocido', description: 'No se pudo determinar el tipo de suelo.' };
	if (typeof temperature !== 'number') return res;
	if (temperature < 15) {
		res.type = 'Arcilloso';
		res.description = 'El suelo arcilloso tiene partículas muy pequeñas y es más húmedo. Es común en áreas frías o húmedas.';
	} else if (temperature >= 15 && temperature <= 25) {
		res.type = 'Limoso';
		res.description = 'El suelo limoso es fértil y retiene bien el agua, pero puede ser propenso a la compactación. Es común en áreas templadas.';
	} else {
		res.type = 'Arenoso';
		res.description = 'El suelo arenoso drena rápidamente y es más cálido, pero tiene menos nutrientes. Es común en áreas cálidas y secas.';
	}
	return res;
}
