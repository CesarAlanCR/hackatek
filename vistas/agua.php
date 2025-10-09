<?php
// Vista dedicada a cuerpos de agua y pozos en México
// Archivo: vistas/agua.php
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cuerpos de agua | Hackatek</title>
    <link rel="stylesheet" href="../recursos/css/general.css">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
    <style>
        .source-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            margin-top: 1.5rem;
        }
        .source-card ul {
            padding-left: 1.25rem;
            margin-bottom: 1rem;
        }
        #preview-map {
            width: 100%;
            height: 360px;
            border-radius: 12px;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex align-items-center mb-3">
            <a href="index.php" class="btn btn-outline-success btn-sm">← Volver</a>
            <h1 class="ms-3 mb-0">Cuerpos de agua y pozos</h1>
        </div>

        <section class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h4">Conecta con fuentes oficiales</h2>
                <p class="mb-0">
                    Este módulo enlaza datos hidrológicos oficiales de México. Aquí podrás combinar información del
                    <strong>INEGI</strong> y la <strong>CONAGUA</strong> para visualizar lagunas, ríos, embalses y pozos en tu mapa agrícola.
                </p>
            </div>
        </section>

        <section class="source-grid">
            <article class="card source-card shadow-sm">
                <div class="card-body">
                    <h3 class="h5">INEGI · Temas de hidrología</h3>
                    <p>Catálogo nacional con cartografía hidrológica: redes de ríos, lagos, cuencas, acuíferos y estadísticas.</p>
                    <ul>
                        <li>Formatos: WMS, WFS, Shapefile, CSV.</li>
                        <li>Licencia de uso público con atribución.</li>
                        <li>Actualizaciones periódicas según censo y estudios especiales.</li>
                    </ul>
                    <a class="btn btn-outline-primary" href="https://www.inegi.org.mx/temas/hidrologia/" target="_blank" rel="noopener">
                        Abrir sitio de INEGI
                    </a>
                </div>
            </article>
            <article class="card source-card shadow-sm">
                <div class="card-body">
                    <h3 class="h5">CONAGUA · SIGA GIS (RP20)</h3>
                    <p>Visor geoespacial con capas de pozos, concesiones, presas, infraestructura hidráulica y reportes de disponibilidad.</p>
                    <ul>
                        <li>Servicios de mapas (WMS/WFS) listos para integrar.</li>
                        <li>Incluye capas temáticas (pozos, zonas de riego, presas, bordos, etc.).</li>
                        <li>Datos actualizados en el Registro Público de Derechos de Agua.</li>
                    </ul>
                    <a class="btn btn-outline-primary" href="https://sigagis.conagua.gob.mx/rp20/" target="_blank" rel="noopener">
                        Abrir visor CONAGUA
                    </a>
                </div>
            </article>
        </section>

        <section class="card shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5">Vista previa del mapa</h2>
                <p class="text-muted mb-3">Carga tus capas preferidas pronto desde el backend para que aparezcan aquí automáticamente.</p>
                <div id="preview-map" aria-label="Mapa de cuerpos de agua"></div>
                <p class="small text-muted mt-3 mb-0">
                    Nota: Los datos en este mapa son ilustrativos. Configura el proxy en <code>api/water_bodies.php</code> para consumir las capas reales y mostrarlas aquí con Leaflet.
                </p>
            </div>
        </section>

        <section class="card shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5">Próximos pasos sugeridos</h2>
                <ol class="mb-0">
                    <li>Configurar un proxy backend que consuma los servicios WMS/WFS de INEGI y CONAGUA.</li>
                    <li>Normalizar los datos a GeoJSON y almacenarlos con caché local para rendimiento.</li>
                    <li>Visualizar las capas en Leaflet con filtros (tipo de cuerpo de agua, profundidad, disponibilidad, año).</li>
                </ol>
            </div>
        </section>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
        defer
    ></script>
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
        defer
    ></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const mapElement = document.getElementById('preview-map');
        if (!mapElement) return;
        const map = L.map(mapElement).setView([23.9, -102.5], 5.3);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Determinar fuente: WFS (si viene en querystring) o endpoint local
        const params = new URLSearchParams(window.location.search);
        const service = params.get('service');
        const typeName = params.get('typeName');
        const bbox = params.get('bbox');
        const cql = params.get('cql_filter');
        let url = '../api/water_bodies.php';
        if (service && typeName) {
            const wfs = new URL('../api/wfs_proxy.php', window.location.origin);
            wfs.searchParams.set('service', service);
            wfs.searchParams.set('typeName', typeName);
            if (bbox) wfs.searchParams.set('bbox', bbox);
            if (cql) wfs.searchParams.set('cql_filter', cql);
            url = wfs.toString();
        }

        // Cargar cuerpos de agua desde la fuente y clasificar por categoría
        fetch(url)
            .then(r => r.json())
            .then(geojson => {
                const normalize = (str) => (str || '').toString().toLowerCase();
                const getTipo = (props) => {
                    const t = normalize(props.tipo || props.type);
                    const name = normalize(props.nombre || props.name);
                    if (t.includes('pozo') || name.includes('pozo') || name.includes('well')) return 'Pozos';
                    if (t.includes('laguna') || t.includes('lago') || name.includes('laguna') || name.includes('lake')) return 'Lagunas';
                    if (t.includes('presa') || t.includes('embalse') || t.includes('represa') || name.includes('presa') || name.includes('dam')) return 'Presas';
                    return 'Otros';
                };

                const styles = {
                    Pozos: { radius: 5, color: '#264653', fillColor: '#2a9d8f', fillOpacity: 0.75, weight: 1 },
                    Lagunas: { radius: 6, color: '#1d3557', fillColor: '#457b9d', fillOpacity: 0.7, weight: 1 },
                    Presas: { radius: 6, color: '#9d0208', fillColor: '#e76f51', fillOpacity: 0.7, weight: 1 },
                    Otros: { radius: 4, color: '#6c757d', fillColor: '#adb5bd', fillOpacity: 0.6, weight: 1 }
                };

                const overlays = {
                    'Pozos': L.geoJSON(geojson, {
                        filter: (f) => getTipo(f.properties || {}) === 'Pozos',
                        pointToLayer: (feature, latlng) => L.circleMarker(latlng, styles.Pozos),
                        onEachFeature: (feature, layer) => {
                            const p = feature.properties || {};
                            let popup = `<strong>${p.nombre || p.name || 'Pozo'}</strong>`;
                            popup += `<br>Tipo: ${p.tipo || p.type || 'Pozo'}`;
                            if (p.estado) popup += `<br>Estado: ${p.estado}`;
                            layer.bindPopup(popup);
                        }
                    }),
                    'Lagunas': L.geoJSON(geojson, {
                        filter: (f) => getTipo(f.properties || {}) === 'Lagunas',
                        pointToLayer: (feature, latlng) => L.circleMarker(latlng, styles.Lagunas),
                        onEachFeature: (feature, layer) => {
                            const p = feature.properties || {};
                            let popup = `<strong>${p.nombre || p.name || 'Laguna'}</strong>`;
                            popup += `<br>Tipo: ${p.tipo || p.type || 'Laguna'}`;
                            if (p.volumen) popup += `<br>Volumen: ${p.volumen}`;
                            layer.bindPopup(popup);
                        }
                    }),
                    'Presas': L.geoJSON(geojson, {
                        filter: (f) => getTipo(f.properties || {}) === 'Presas',
                        pointToLayer: (feature, latlng) => L.circleMarker(latlng, styles.Presas),
                        onEachFeature: (feature, layer) => {
                            const p = feature.properties || {};
                            let popup = `<strong>${p.nombre || p.name || 'Presa'}</strong>`;
                            popup += `<br>Tipo: ${p.tipo || p.type || 'Presa'}`;
                            if (p.volumen) popup += `<br>Volumen: ${p.volumen}`;
                            if (p.estado) popup += `<br>Estado: ${p.estado}`;
                            layer.bindPopup(popup);
                        }
                    }),
                    'Otros': L.geoJSON(geojson, {
                        filter: (f) => getTipo(f.properties || {}) === 'Otros',
                        pointToLayer: (feature, latlng) => L.circleMarker(latlng, styles.Otros),
                        onEachFeature: (feature, layer) => {
                            const p = feature.properties || {};
                            let popup = `<strong>${p.nombre || p.name || 'Cuerpo de agua'}</strong>`;
                            if (p.tipo || p.type) popup += `<br>Tipo: ${p.tipo || p.type}`;
                            layer.bindPopup(popup);
                        }
                    })
                };

                // Añadir todas las capas por defecto y control para activar/desactivar
                const added = [];
                Object.values(overlays).forEach(l => { l.addTo(map); added.push(l); });
                L.control.layers(null, overlays, { collapsed: false }).addTo(map);

                // Ajustar vista a todos los datos si hay bounds válidos
                const group = L.featureGroup(added);
                const bounds = group.getBounds();
                if (bounds && bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [20, 20] });
                }
            })
            .catch(err => {
                console.error('Error cargando cuerpos de agua:', err);
            });
    });
    </script>
</body>
</html>
