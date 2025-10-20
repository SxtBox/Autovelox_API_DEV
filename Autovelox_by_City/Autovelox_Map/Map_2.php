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
  <title>Autovelox by City</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  />
  <style>
    body, html {
      margin: 0;
      padding: 0;
      height: 100%;
      font-family: sans-serif;
    }
    #map {
      height: 100%;
    }
    .leaflet-popup-content {
      font-size: 14px;
    }
    #cityForm {
      position: absolute;
      top: 10px;
      left: 10px;
      z-index: 1000;
      background: white;
      padding: 8px;
      border-radius: 6px;
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
  <script>
    const map = L.map('map').setView([43.77, 11.25], 13); // default Firenze

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const markerGroup = L.layerGroup().addTo(map);

    async function loadAutovelox(city) {
      const url = `Autovelox_by_City_Simple.php?city=${encodeURIComponent(city)}`;
      const response = await fetch(url);
      const data = await response.json();

      markerGroup.clearLayers();

      if (!Array.isArray(data)) {
        alert('Asnjë të dhënë');
        return;
      }

      data.forEach((point) => {
        const { lat, lon, tags } = point;

        const popupContent = `
          <strong>${tags['name'] || 'Autovelox'}</strong><br>
          ${tags['addr:street'] || '—'}, ${tags['addr:city'] || ''}<br>
          ${tags['addr:postcode'] || ''}<br>
          Shpejtësi max: <strong>${tags['maxspeed'] || '?'} km/h</strong><br>
          ${tags['enforcement'] ? 'Monitorim: ' + tags['enforcement'] : ''}
        `;

        const marker = L.marker([lat, lon])
          .bindPopup(popupContent)
          .addTo(markerGroup);
      });

      if (data.length > 0) {
        const bounds = markerGroup.getBounds();
        map.fitBounds(bounds, { padding: [20, 20] });
      } else {
        alert('Nuk u gjetën kamera për këtë qytet.');
      }
    }

    document.getElementById('cityForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const city = document.getElementById('cityInput').value.trim();
      if (city) {
        loadAutovelox(city);
      }
    });

    // ngarko default
    loadAutovelox("Firenze");
  </script>
</body>
</html>
