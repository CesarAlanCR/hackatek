<?php
// Página mínima: sólo el mapa interactivo de cuerpos de agua
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mapa - Cuerpos de agua</title>
    <link rel="stylesheet" href="../recursos/css/general.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <style>
        .map-container{
            background:var(--bg-card);
            border-radius:var(--radius-lg);
            overflow:hidden;
            box-shadow:var(--shadow-lg);
            border:1px solid var(--border);
            animation:scaleIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes scaleIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}
        .map-header{
            padding:20px 24px;
            background:rgba(30, 41, 54, 0.6);
            backdrop-filter:blur(10px);
            border-bottom:1px solid var(--border);
            display:flex;
            align-items:center;
            gap:16px;
        }
        .map-header h5{
            margin:0;
            color:var(--accent);
            font-size:1.4rem;
            font-weight:700;
            flex:1;
            text-align:center;
            letter-spacing:-0.5px;
        }
        .btn-back{
            background:rgba(124, 179, 66, 0.15);
            border:1px solid var(--border-hover);
            color:var(--accent);
            padding:10px 20px;
            border-radius:var(--radius);
            font-weight:600;
            text-decoration:none;
            transition:var(--transition-fast);
        }
        .btn-back:hover{
            background:var(--accent);
            color:white;
            transform:translateX(-4px);
        }
        #map{width:100%;height:75vh;background:var(--bg-secondary)}
        .leaflet-popup-content-wrapper{
            background:var(--bg-card);
            color:var(--text-primary);
            border-radius:var(--radius);
            box-shadow:var(--shadow-lg);
        }
        .leaflet-popup-tip{background:var(--bg-card)}
        .c1, .c2{
            background:var(--accent);
            border:2px solid var(--bg-card);
            box-shadow:0 0 12px var(--green-glow);
            border-radius:50%;
        }
        .c2{background:var(--green-3)}
    </style>
</head>
<body>
    <main class="container" style="padding:40px 0">
        <div class="map-container">
            <div class="map-header">
                <a href="index.php" class="btn-back">← Volver</a>
                <h5>Cuerpos de agua de México</h5>
                <div style="width:100px"></div>
            </div>
            <div id="map"></div>
        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    <script>
    (function(){
        const map = L.map('map').setView([23.634501, -102.552784], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors' }).addTo(map);

        const acuiferosCluster = L.markerClusterGroup({ disableClusteringAtZoom: 10 });
        const estacionesCluster = L.markerClusterGroup({ disableClusteringAtZoom: 10 });

        // Cargar acuíferos
        fetch('https://raw.githubusercontent.com/CesarA3v/SCRUM/main/Acuiferos_Disponibilidad.json')
            .then(r => r.json())
            .then(data => {
                data.forEach(item => {
                    const { Y, X, NOM_EDO, NOM_REGION, AREA_KM2 } = item;
                    if (!Y || !X) return;
                    const m = L.marker([Y, X], { icon: L.divIcon({ className:'c1', html:'', iconSize:[10,10] }) });
                    m.bindPopup(`<strong>Acuífero</strong><br>${NOM_REGION || ''}<br>${NOM_EDO || ''}<br>Área: ${AREA_KM2 || 'N/A'} km²`)
                    acuiferosCluster.addLayer(m);
                });
                map.addLayer(acuiferosCluster);
            }).catch(()=>{});

        // Cargar estaciones
        fetch('https://raw.githubusercontent.com/CesarA3v/SCRUM/main/Estaciones_hidrometricas.json')
            .then(r => r.json())
            .then(data => {
                data.forEach(s => {
                    const lat = parseFloat(s.Latitud);
                    const lon = parseFloat(s.Longitud);
                    if (!lat || !lon) return;
                    const m = L.marker([lat, lon], { icon: L.divIcon({ className:'c2', html:'', iconSize:[8,8] }) });
                    m.bindPopup(`<strong>Estación</strong><br>${s.Estacion || ''}<br>${s.Municipio || ''}<br>${s.Estado || ''}`)
                    estacionesCluster.addLayer(m);
                });
                map.addLayer(estacionesCluster);
            }).catch(()=>{});

        L.control.layers(null, { 'Acuíferos': acuiferosCluster, 'Estaciones': estacionesCluster }, { collapsed:false }).addTo(map);
    })();
    </script>
    <script src="../recursos/js/animations.js" defer></script>
</body>
</html>
