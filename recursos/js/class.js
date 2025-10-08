
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

	function openModal(title, content){
		modalTitle.textContent = title;
		modalBody.innerHTML = '<p>'+content+'</p>';
		modal.setAttribute('aria-hidden','false');
		// move focus into modal
		closeBtn.focus();
	}

	function closeModal(){
		modal.setAttribute('aria-hidden','true');
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
			const text = accuracy ? `Tu ubicación (±${Math.round(accuracy)} m)` : 'Tu ubicación aproximada';
			map.setView([latitude, longitude], 13);
			const marker = L.marker([latitude, longitude]).addTo(map);
			marker.bindPopup(text).openPopup();
			if (accuracy) {
				L.circle([latitude, longitude], { radius: accuracy, color: '#2f8f44', fillColor: '#2f8f44', fillOpacity: 0.15 }).addTo(map);
			}
			// Cargar clima actual como popup extra (opcional)
			if (apiKey) {
				fetch(`https://api.openweathermap.org/data/2.5/weather?lat=${latitude}&lon=${longitude}&units=metric&lang=es&appid=${apiKey}`)
					.then(r => r.ok ? r.json() : Promise.reject(r))
					.then(data => {
						const desc = data.weather?.[0]?.description || 'Clima';
						const temp = Math.round(data.main?.temp);
						marker.setPopupContent(`${text}<br>${desc}, ${temp}°C`);
					})
					.catch(()=>{});
			}
		}, function(err){
			// Permiso denegado o error
			console.warn('Geolocalización no disponible:', err && err.message);
			map.setView(defaultCenter, defaultZoom);
			L.marker(defaultCenter).addTo(map).bindPopup('No se pudo obtener tu ubicación. Vista por defecto.');
		}, { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
	} else {
		map.setView(defaultCenter, defaultZoom);
		L.marker(defaultCenter).addTo(map).bindPopup('Geolocalización no soportada por tu navegador.');
	}
}
