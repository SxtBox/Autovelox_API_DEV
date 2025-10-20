<?php
/**
 * autovelox.php me cache qytetesh në file
 */

header('Content-Type: application/json; charset=utf-8');

define('CACHE_FILE', __DIR__ . '/cache_cities.json');

// Ngarko cache (ose krijo array bosh)
$cache = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];

$default_bbox = '35.4,6.6,47.1,18.8';

$bbox = isset($_GET['bbox']) ? trim($_GET['bbox']) : $default_bbox;
$format = (isset($_GET['format']) && $_GET['format'] === 'minimal') ? 'minimal' : 'full';

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

// Funksioni i cache për qytetin
function getCityFromCoordsCached(float $lat, float $lon): ?string {
    global $cache;

    $key = round($lat, 5) . ',' . round($lon, 5);

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $city = getCityFromCoords($lat, $lon);

    if ($city !== null) {
        $cache[$key] = $city;
        file_put_contents(CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return $city;
}

// Funksioni për reverse geocoding (pa cache)
function getCityFromCoords(float $lat, float $lon): ?string {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&addressdetails=1";

    $opts = [
        "http" => [
            "header" => "User-Agent: PHP-autovelox-app/1.0\r\n"
        ]
    ];
    $context = stream_context_create($opts);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) return null;

    $data = json_decode($json, true);
    if (!$data || !isset($data['address'])) return null;

    return $data['address']['city'] ?? 
           $data['address']['town'] ?? 
           $data['address']['village'] ?? 
           $data['address']['municipality'] ?? 
           null;
}

// Përdorimin e cache në ciklin e rezultateve:
if ($format === 'minimal') {
    $minimal = [];
    foreach ($data['elements'] as $elem) {
        if (!isset($elem['lat'], $elem['lon'])) continue;
        $name = $elem['tags']['name'] ?? ($elem['tags']['ref'] ?? null);
        $city = getCityFromCoordsCached($elem['lat'], $elem['lon']);
        $minimal[] = [
            'lat' => $elem['lat'],
            'lon' => $elem['lon'],
            'name' => $name,
            'city' => $city
        ];
        // Për të shmangur overloading, shtu delay nëse dëshiron
        // usleep(100000);
    }
    echo json_encode($minimal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    foreach ($data['elements'] as &$elem) {
        if (isset($elem['lat'], $elem['lon'])) {
            $elem['city'] = getCityFromCoordsCached($elem['lat'], $elem['lon']);
            // usleep(100000);
        }
    }
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
