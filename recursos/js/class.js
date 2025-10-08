// class.js - mapa + clima (versión única y limpia)

document.addEventListener('DOMContentLoaded', () => {
  console.log('DEBUG: window.OWM_API_KEY =', window.OWM_API_KEY);
  console.log('DEBUG: Leaflet (L) tipo =', typeof window.L);

  const mapContainer = document.getElementById('weather-map');
  const API_KEY = window.OWM_API_KEY || '';

  const tempEl = document.getElementById('temp-value');
  const humEl = document.getElementById('humidity-value');
  const satEl = document.getElementById('satellite-updated');
  if (tempEl) tempEl.textContent = 'Cargando...';
  if (humEl) humEl.textContent = 'Cargando...';
  if (satEl) satEl.textContent = 'Cargando...';

  if (typeof window.L === 'undefined') console.warn('Leaflet no está cargado. Verifica las etiquetas <script>.');

  if (mapContainer && typeof window.L !== 'undefined') {
    initWeatherMap(mapContainer, API_KEY);
  } else {
    if (API_KEY) fetchWeather(9.7489, -83.7534, API_KEY).then(updateWeatherUIFromData).catch(showWeatherError);
    else showWeatherError('Falta API key');
  }
});

function initWeatherMap(container, apiKey) {
  const map = L.map(container, { zoomControl: true, attributionControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

  if (apiKey) {
    const clouds = L.tileLayer(`https://tile.openweathermap.org/map/clouds_new/{z}/{x}/{y}.png?appid=${apiKey}`, { opacity: 0.6 });
    const temp = L.tileLayer(`https://tile.openweathermap.org/map/temp_new/{z}/{x}/{y}.png?appid=${apiKey}`, { opacity: 0.5 });
    clouds.addTo(map);
    L.control.layers(null, { 'Nubes': clouds, 'Temperatura': temp }, { collapsed: true }).addTo(map);
  }

  const defaultCenter = [9.7489, -83.7534];

  if (navigator.geolocation && navigator.geolocation.getCurrentPosition) {
    navigator.geolocation.getCurrentPosition(pos => {
      const { latitude, longitude, accuracy } = pos.coords;
      map.setView([latitude, longitude], 13);
      const marker = L.marker([latitude, longitude]).addTo(map);
      const text = accuracy ? `Tu ubicación (±${Math.round(accuracy)} m)` : 'Tu ubicación aproximada';
      marker.bindPopup(text).openPopup();
      if (accuracy) L.circle([latitude, longitude], { radius: accuracy, color: '#2f8f44', fillOpacity: 0.15 }).addTo(map);
      if (apiKey) {
        fetchWeather(latitude, longitude, apiKey).then(data => {
          const desc = data.weather?.[0]?.description || 'Clima';
          const t = data.main?.temp != null ? Math.round(data.main.temp) : 'N/D';
          marker.setPopupContent(`${text}<br>${desc}, ${t}°C`);
          updateWeatherUIFromData(data);
        }).catch(showWeatherError);
      }
    }, err => {
      console.warn('Geolocalización no disponible:', err && err.message);
      map.setView(defaultCenter, 7);
      L.marker(defaultCenter).addTo(map).bindPopup('Vista por defecto');
      if (apiKey) fetchWeather(defaultCenter[0], defaultCenter[1], apiKey).then(updateWeatherUIFromData).catch(showWeatherError);
    }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 });
  } else {
    map.setView(defaultCenter, 7);
    L.marker(defaultCenter).addTo(map).bindPopup('Geolocalización no soportada');
    if (apiKey) fetchWeather(defaultCenter[0], defaultCenter[1], apiKey).then(updateWeatherUIFromData).catch(showWeatherError);
  }
}

function timeAgo(date) {
  const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
  if (seconds < 60) return 'hace unos segundos';
  if (seconds < 3600) return `hace ${Math.floor(seconds/60)} minutos`;
  if (seconds < 86400) return `hace ${Math.floor(seconds/3600)} horas`;
  return `hace ${Math.floor(seconds/86400)} días`;
}

function fetchWeather(lat, lon, apiKey) {
  const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&units=metric&lang=es&appid=${apiKey}`;
  console.debug('Consultando OWM:', url);
  return fetch(url).then(r => {
    if (!r.ok) return r.text().then(t => { throw new Error(`OWM error ${r.status}: ${t}`); });
    return r.json();
  });
}

function updateWeatherUIFromData(data) {
  try {
    const tempEl = document.getElementById('temp-value');
    const humEl = document.getElementById('humidity-value');
    const satEl = document.getElementById('satellite-updated');
    if (tempEl && data.main?.temp != null) tempEl.textContent = `${Math.round(data.main.temp)} °C`;
    if (humEl && data.main?.humidity != null) humEl.textContent = `${data.main.humidity} %`;
    if (satEl) { const dt = data.dt ? new Date(data.dt*1000) : new Date(); satEl.textContent = timeAgo(dt); satEl.setAttribute('title', dt.toLocaleString()); }
  } catch (e) { showWeatherError(e); }
}

function showWeatherError(err) {
  const tempEl = document.getElementById('temp-value');
  const humEl = document.getElementById('humidity-value');
  const satEl = document.getElementById('satellite-updated');
  if (tempEl && tempEl.textContent === 'Cargando...') tempEl.textContent = 'No disponible';
  if (humEl && humEl.textContent === 'Cargando...') humEl.textContent = 'No disponible';
  if (satEl && satEl.textContent === 'Cargando...') satEl.textContent = 'No disponible';
  console.error('Error al obtener clima:', err);
}
