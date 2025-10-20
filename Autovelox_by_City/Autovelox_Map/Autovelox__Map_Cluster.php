<?php
/*
Si ta përdorësh
Ruaje këtë si autovelox_map.html në të njëjtin folder ku ndodhet Autovelox_by_City.php

Hap në shfletues:

arduino
Copy
Edit
http://localhost/autovelox_map.html
Provo qytete të tjera: Roma, Milano, Napoli, etj
*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Autovelox me Ngjyra dhe Cluster</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  />
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"
  />
  
  <style>
    html, body, #map {
      height: 100%;
      margin: 0; padding: 0;
      font-family: sans-serif;
    }
    #cityForm {
      position: absolute;
      top: 10px; left: 10px; z-index: 1000;
      background: white; padding: 8px; border-radius: 6px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
  </style>
</head>
<body>

<form id="cityForm">
  <label>
    Qyteti:
    <input type="text" id="cityInput" value="Firenze" />
  </label>
  <button type="submit">Kërko</button>
</form>

<div id="map"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
  const map = L.map('map').setView([43.77, 11.25], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  let markers = L.markerClusterGroup();
  map.addLayer(markers);

  function getColor(maxspeed) {
    if (!maxspeed) return 'gray';
    const speed = parseInt(maxspeed, 10);
    if (speed <= 50) return 'yellow';
    if (speed <= 90) return 'orange';
    return 'red';
  }

  function createColoredMarker(lat, lon, color) {
    return L.circleMarker([lat, lon], {
      radius: 8,
      fillColor: color,
      color: '#000',
      weight: 1,
      opacity: 1,
      fillOpacity: 0.8
    });
  }

  async function loadAutovelox(city) {
    const url = `Autovelox_by_City.php?city=${encodeURIComponent(city)}`;
    const resp = await fetch(url);
    const data = await resp.json();

    markers.clearLayers();

    if (!Array.isArray(data) || data.length === 0) {
      alert('Nuk u gjetën kamera për këtë qytet.');
      return;
    }

    data.forEach(point => {
      const { lat, lon, tags } = point;
      const color = getColor(tags.maxspeed);
      const marker = createColoredMarker(lat, lon, color);

      const popup = `
        <strong>${tags.name || 'Autovelox'}</strong><br>
        ${tags['addr:street'] || '—'}, ${tags['addr:city'] || ''}<br>
        Postcode: ${tags['addr:postcode'] || '—'}<br>
        Shpejtësi max: <strong>${tags.maxspeed || '?'}</strong> km/h<br>
        ${tags.enforcement ? 'Monitorim: ' + tags.enforcement : ''}
      `;

      marker.bindPopup(popup);
      markers.addLayer(marker);
    });

    map.fitBounds(markers.getBounds(), { padding: [20, 20] });
  }

  document.getElementById('cityForm').addEventListener('submit', e => {
    e.preventDefault();
    const city = document.getElementById('cityInput').value.trim();
    if (city) loadAutovelox(city);
  });

  // Ngarko fillimisht Firenze
  loadAutovelox('Firenze');
</script>

</body>
</html>
