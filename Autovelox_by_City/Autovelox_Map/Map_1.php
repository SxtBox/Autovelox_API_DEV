<?php
/*
Si funksionon:

E lexon këtë Autovelox_by_City.php?city=...

I vendos ngjyrë të ndryshme sipas maxspeed:

<=50 → 🟡 verdhë

51–90 → 🟠 portokalli

>90 → 🔴 e kuqe

Kërkon kamerat e shpejtësisë për qytetin e futur (?city=...)

Shfaq marker me ngjyrë sipas maxspeed (<50=verd, <=90=portok, >90=kuq)

Popup me info të detajuara (street, city, postcode, maxspeed, name, enforcement)


*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Autovelox me Ngjyra - Leaflet</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  />
  <style>
    body, html {
      margin: 0; padding: 0; height: 100%;
      font-family: sans-serif;
    }
    #map {
      height: 100%;
    }
    #cityForm {
      position: absolute;
      top: 10px; left: 10px;
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
  const map = L.map('map').setView([43.77, 11.25], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  const markerGroup = L.layerGroup().addTo(map);

  // Ngjyra sipas maxspeed
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
    const url = `Autovelox_by_City_Simple.php?city=${encodeURIComponent(city)}`;
    const resp = await fetch(url);
    const data = await resp.json();

    markerGroup.clearLayers();

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
      marker.addTo(markerGroup);
    });

    map.fitBounds(markerGroup.getBounds(), {padding: [20,20]});
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
