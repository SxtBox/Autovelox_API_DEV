<?php
/*
Autovelox_by_City.php?city=Firenze
Autovelox_by_City.php që:

Pranon ?city=Firenze (ose çdo qytet tjetër)

Pyet Overpass për speed_camera në atë zonë

Bën reverse geocoding në pikat që nuk kanë addr:*

Plotëson tags si: addr:city, addr:street, postcode, maxspeed, name, enforcement

Ruhet në cache që të mos bëjë thirrje të përsëritura në Nominatim
*/
header('Content-Type: application/json; charset=utf-8');

define('USER_AGENT', 'AutoveloxByCityPHP/1.0 (contact: example@yourdomain.com)');
define('CACHE_FILE', __DIR__ . '/cache_city_addr.json');

$city = $_GET['city'] ?? null;
if (!$city) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametri ?city mungon']);
    exit;
}

$bbox = getBboxFromCity($city);
if (!$bbox) {
    http_response_code(400);
    echo json_encode(['error' => "City '$city' not found"]);
    exit;
}

$query = <<<OVERPASS
[out:json][timeout:25];
node["highway"="speed_camera"]($bbox);
out body;
OVERPASS;

$response = curlPost("https://overpass-api.de/api/interpreter", ['data' => $query]);
$data = json_decode($response, true);

if (!$data || !isset($data['elements'])) {
    http_response_code(500);
    echo json_encode(['error' => 'No data from Overpass']);
    exit;
}

// cache për adresat
$cache = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];

foreach ($data['elements'] as &$elem) {
    if (!isset($elem['lat'], $elem['lon'])) continue;

    $tags = $elem['tags'] ?? [];

    // Nëse mungojnë addr:* → bëj reverse geocoding
    if (!isset($tags['addr:city']) || !isset($tags['addr:street'])) {
        $address = getAddressFromCoordsCached($elem['lat'], $elem['lon'], $cache);
        if ($address) {
            foreach ($address as $k => $v) {
                $tags["addr:$k"] = $v;
            }
        }
    }

    // plotëso disa vlera bazë nëse mungojnë
    if (!isset($tags['name'])) $tags['name'] = "autovelox";
    if (!isset($tags['enforcement'])) $tags['enforcement'] = "maxspeed";

    $elem['tags'] = $tags;
}

file_put_contents(CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($data['elements'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


// ======================== FUNKSIONE =========================

function getBboxFromCity(string $city): ?string {
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
        'q' => $city,
        'format' => 'json',
        'limit' => 1
    ]);

    $ctx = stream_context_create(['http' => ['header' => "User-Agent: " . USER_AGENT]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;

    $obj = json_decode($res, true);
    if (!isset($obj[0]['boundingbox'])) return null;

    $bb = $obj[0]['boundingbox']; // [south, north, west, east]
    return "{$bb[0]},{$bb[2]},{$bb[1]},{$bb[3]}";
}

function getAddressFromCoordsCached(float $lat, float $lon, array &$cache): ?array {
    $key = round($lat, 5) . ',' . round($lon, 5);
    if (isset($cache[$key])) return $cache[$key];

    $address = getAddressFromCoords($lat, $lon);
    if ($address) {
        $cache[$key] = $address;
    }
    return $address;
}

function getAddressFromCoords(float $lat, float $lon): ?array {
    $url = "https://nominatim.openstreetmap.org/reverse?" . http_build_query([
        'format' => 'json',
        'lat' => $lat,
        'lon' => $lon,
        'addressdetails' => 1
    ]);

    $ctx = stream_context_create(['http' => ['header' => "User-Agent: " . USER_AGENT]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;

    $obj = json_decode($res, true);
    if (!isset($obj['address'])) return null;

    $addr = $obj['address'];

    return [
        'city' => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? null,
        'postcode' => $addr['postcode'] ?? null,
        'street' => $addr['road'] ?? $addr['street'] ?? null,
    ];
}

function curlPost(string $url, array $params): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}
