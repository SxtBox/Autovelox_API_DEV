<?php
/**
 * Autovelox.php
 *
 * Merr pikat "speed_camera" nga OpenStreetMap (Overpass API)
 * me opsion për bbox dhe format minimal ose full.
 * POST me cURL me http_build_query.

Përdorimi:
Gjithë Italia, full:
https://domaini.yt/Autovelox.php

Me bbox specifik:
https://domaini.yt/Autovelox.php?bbox=44.9,8.9,46.2,10.2

Minimal format:
https://domaini.yt/Autovelox.php?format=minimal

Minimal + bbox:
https://domaini.yt/Autovelox.php?bbox=44.9,8.9,46.2,10.2&format=minimal
*/

header('Content-Type: application/json; charset=utf-8');

$default_bbox = '35.4,6.6,47.1,18.8'; // Italia kontinentale + ishujt (lat1,lon1,lat2,lon2)

$bbox = isset($_GET['bbox']) ? trim($_GET['bbox']) : $default_bbox;
$format = (isset($_GET['format']) && $_GET['format'] === 'minimal') ? 'minimal' : 'full';

// Validate bbox format (4 numbers separated by commas)
if (!preg_match('/^\s*(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?),\s*(-?\d+(\.\d+)?)\s*$/', $bbox)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid bbox format. Use: lat1,lon1,lat2,lon2']);
    exit;
}

$overpassUrl = "https://overpass-api.de/api/interpreter";

$query = <<<OVERPASS
[out:json][timeout:30];
node["highway"="speed_camera"]($bbox);
out body;
OVERPASS;

$response = curlPost($overpassUrl, ['data' => $query]);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to contact Overpass API']);
    exit;
}

$data = json_decode($response, true);
if ($data === null) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to decode Overpass response',
        'raw_response' => $response,
    ]);
    exit;
}

if ($format === 'minimal') {
    $minimal = [];
    foreach ($data['elements'] as $elem) {
        if (!isset($elem['lat'], $elem['lon'])) continue;
        $name = $elem['tags']['name'] ?? ($elem['tags']['ref'] ?? null);
        $minimal[] = [
            'lat' => $elem['lat'],
            'lon' => $elem['lon'],
            'name' => $name
        ];
    }
    echo json_encode($minimal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * POST helper me cURL duke perdorur http_build_query
 */
function curlPost(string $url, array $params): string|false {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
