<?php
/*
Për frontend (autovelox_map.html)
Duhet të sigurohesh që:

Thirrja AJAX shkon te Autovelox_by_City.php?city=...

Merr përgjigje vetëm array me pikat në formatin e thjeshtuar më sipër

Shfaq markerat me ngjyra sipas maxspeed në Leaflet
*/

header('Content-Type: application/json; charset=utf-8');
define('USER_AGENT', 'AutoveloxSimple/1.0 (contact: youremail@example.com)');
define('CACHE_FILE', __DIR__ . '/cache_city_addr.json');

$city = $_GET['city'] ?? null;
if (!$city) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametri ?city mungon']);
    exit;
}

// Merr bbox nga Nominatim për qytetin
$bbox = getBboxFromCity($city);
if (!$bbox) {
    http_response_code(400);
    echo json_encode(['error' => "Qyteti '$city' nuk u gjet"]);
    exit;
}

// Overpass query për autovelox në bbox
$query = <<<EOT
[out:json][timeout:20];
node["highway"="speed_camera"]($bbox);
out body;
EOT;

$resp = curlPost("https://overpass-api.de/api/interpreter", ['data' => $query]);
$data = json_decode($resp, true);

if (!$data || !isset($data['elements'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Nuk u morën të dhëna nga Overpass']);
    exit;
}

// ngarko cache reverse geocoding
$cache = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];

$result = [];
foreach ($data['elements'] as $elem) {
    if (!isset($elem['lat'], $elem['lon'])) continue;

    $tags = $elem['tags'] ?? [];

    // Nëse mungojnë addr:city ose addr:street, bëj reverse geocoding
    if (!isset($tags['addr:city']) || !isset($tags['addr:street'])) {
        $addr = getAddressCached($elem['lat'], $elem['lon'], $cache);
        if ($addr) {
            foreach ($addr as $k => $v) {
                $tags["addr:$k"] = $v;
            }
        }
    }

    if (!isset($tags['name'])) $tags['name'] = 'autovelox';
    if (!isset($tags['enforcement'])) $tags['enforcement'] = 'maxspeed';

    $result[] = [
        'type' => 'node',
        'id' => $elem['id'],
        'lat' => $elem['lat'],
        'lon' => $elem['lon'],
        'tags' => $tags
    ];
}

// ruaj cache-in
file_put_contents(CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// FUNKSIONET

function getBboxFromCity(string $city): ?string {
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
        'q' => $city,
        'format' => 'json',
        'limit' => 1
    ]);
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: " . USER_AGENT]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;
    $json = json_decode($res, true);
    if (!isset($json[0]['boundingbox'])) return null;
    $bb = $json[0]['boundingbox']; // [south, north, west, east]
    return "{$bb[0]},{$bb[2]},{$bb[1]},{$bb[3]}";
}

function getAddressCached(float $lat, float $lon, array &$cache): ?array {
    $key = round($lat,5).','.round($lon,5);
    if (isset($cache[$key])) return $cache[$key];
    $addr = getAddress($lat, $lon);
    if ($addr) $cache[$key] = $addr;
    return $addr;
}

function getAddress(float $lat, float $lon): ?array {
    $url = "https://nominatim.openstreetmap.org/reverse?" . http_build_query([
        'format' => 'json',
        'lat' => $lat,
        'lon' => $lon,
        'addressdetails' => 1
    ]);
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: " . USER_AGENT]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;
    $json = json_decode($res, true);
    if (!isset($json['address'])) return null;
    $a = $json['address'];
    return [
        'city' => $a['city'] ?? $a['town'] ?? $a['village'] ?? null,
        'postcode' => $a['postcode'] ?? null,
        'street' => $a['road'] ?? $a['street'] ?? null,
    ];
}

function curlPost(string $url, array $post): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r;
}
