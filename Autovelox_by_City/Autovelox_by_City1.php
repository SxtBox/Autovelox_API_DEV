<?php
/*
https://nominatim.openstreetmap.org/search.php?q=Firenze&format=json&polygon_geojson=0

Rrjedha logjike në PHP:
Nëse përdoruesi jep ?city=Firenze, kërko në Nominatim dhe nxirr bbox

Përdor atë bbox për të bërë query në Overpass API


Shembuj përdorimi:

Gjithë Italia:
/autovelox.php

Qytet i veçantë (me bbox nga Nominatim):
/autovelox.php?city=Firenze
/autovelox.php?city=Roma&format=minimal

Me bbox manual (për zonë të vogël):
/autovelox.php?bbox=43.75,11.20,43.80,11.30
*/
header('Content-Type: application/json; charset=utf-8');

define('USER_AGENT', 'AutoveloxPHP/1.0 (contact: example@yourdomain.it)');

$city = $_GET['city'] ?? null;
$bbox = $_GET['bbox'] ?? null;
$format = ($_GET['format'] ?? 'full') === 'minimal' ? 'minimal' : 'full';

// Merr bbox nga city nëse është vendosur
if ($city) {
    $bbox = getBboxFromCity($city);
    if (!$bbox) {
        http_response_code(400);
        echo json_encode(['error' => "City '$city' not found or bbox unavailable"]);
        exit;
    }
} elseif (!$bbox) {
    // Nëse s'ka as city, as bbox → përdor Italia
    $bbox = '35.4,6.6,47.1,18.8';
}

// Sigurohu që bbox është në format korrekt
if (!preg_match('/^\s*(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?)\s*$/', $bbox)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid bbox format. Use: lat1,lon1,lat2,lon2']);
    exit;
}

// Overpass query
$query = <<<OVERPASS
[out:json][timeout:30];
node["highway"="speed_camera"]($bbox);
out body;
OVERPASS;

$response = curlPost('https://overpass-api.de/api/interpreter', ['data' => $query]);

if (!$response || !$data = json_decode($response, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch or decode Overpass data']);
    exit;
}

// Përgatit rezultatet
if ($format === 'minimal') {
    $output = [];
    foreach ($data['elements'] as $e) {
        if (!isset($e['lat'], $e['lon'])) continue;
        $output[] = [
            'lat' => $e['lat'],
            'lon' => $e['lon'],
            'name' => $e['tags']['name'] ?? ($e['tags']['ref'] ?? null),
        ];
    }
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


// Merr bbox për një qytet nga Nominatim
function getBboxFromCity(string $city): ?string {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $city,
        'format' => 'json',
        'limit' => 1,
        'polygon' => 0,
        'addressdetails' => 0,
    ]);

    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: " . USER_AGENT . "\r\n"
        ]
    ]);

    $json = @file_get_contents($url, false, $context);
    if (!$json) return null;

    $result = json_decode($json, true);
    if (!$result || !isset($result[0]['boundingbox'])) return null;

    $bbox = $result[0]['boundingbox']; // [south, north, west, east]
    return "{$bbox[0]},{$bbox[2]},{$bbox[1]},{$bbox[3]}";
}

// POST në Overpass API
function curlPost(string $url, array $params): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
