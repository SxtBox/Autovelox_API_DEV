<?php
/*
https://nominatim.openstreetmap.org/search.php?q=Firenze&format=json&polygon_geojson=0

 Zgjidhja:
Merr të dhënat nga Overpass si zakonisht

Kontrollo nëse mungojnë addr:* tags

Në ato raste, bëj reverse geocoding për të marrë city, postcode, street

Shto ato vlera si addr:* në tags

Rezultat për Autovelox_by_City.php?city=Firenze
Tani të gjitha pikat:

Do kenë minimumi: lat, lon, highway, name, enforcement

Dhe nëse mungojnë addr:*, do plotësohen nga reverse geocoding:

addr:city

addr:postcode

addr:street
*/

header('Content-Type: application/json; charset=utf-8');

define('USER_AGENT', 'AutoveloxByCityPHP/1.0 (contact: your@email.it)');
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

// Overpass query për kamerat në këtë bbox
$query = <<<OVERPASS
[out:json][timeout:30];
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

$cache = file_exists(CACHE_FILE) ? json_decode(file_get_contents(CACHE_FILE), true) : [];

foreach ($data['elements'] as &$elem) {
    if (!isset($elem['lat'], $elem['lon'])) continue;

    $tags = $elem['tags'] ?? [];

    // Nëse mungojnë addr:city ose addr:street, plotëso me reverse geocoding
    if (!isset($tags['addr:city']) || !isset($tags['addr:street'])) {
        $address = getAddressFromCoordsCached($elem['lat'], $elem['lon'], $cache);
        if ($address) {
            foreach ($address as $k => $v) {
                $tags["addr:$k"] = $v;
            }
        }
    }

    // Siguro që kanë disa fusha bazë
    if (!isset($tags['name'])) $tags['name'] = "autovelox";
    if (!isset($tags['enforcement'])) $tags['enforcement'] = "maxspeed";

    $elem['tags'] = $tags;
}

// Ruaj cache të përditësuar
file_put_contents(CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Kthe JSON
echo json_encode($data['elements'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


/** Marr bbox nga qyteti */
function getBboxFromCity(string $city): ?string {
    $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
        'q' => $city,
        'format' => 'json',
        'limit' => 1
    ]);

    $ctx = stream_context_create(['http' => ['header' => "User-Agent: " . USER_AGENT . "\r\n"]]);
    $res = @file_get_contents($url, false, $ctx);
    if (!$res) return null;

    $obj = json_decode($res, true);
    if (!isset($obj[0]['boundingbox'])) return null;

    $bb = $obj[0]['boundingbox']; // [south, north, west, east]
    return "{$bb[0]},{$bb[2]},{$bb[1]},{$bb[3]}";
}

/** Bën reverse geocoding dhe ruan në cache */
function getAddressFromCoordsCached(float $lat, float $lon, array &$cache): ?array {
    $key = round($lat, 5) . ',' . round($lon, 5);
    if (isset($cache[$key])) return $cache[$key];

    $address = getAddressFromCoords($lat, $lon);
    if ($address) {
        $cache[$key] = $address;
    }
    return $address;
}

/** Reverse geocoding i pastër nga Nominatim */
function getAddressFromCoords(float $lat, float $lon): ?array {
    $url = "https://nominatim.openstreetmap.org/reverse?" . http_build_query([
        'format' => 'json',
        'lat' => $lat,
        'lon' => $lon,
        'addressdetails' => 1
    ]);

    $ctx = stream_context_create(['http' => ['header' => "User-Agent: " . USER_AGENT . "\r\n"]]);
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

/** Thirrje cURL POST për Overpass */
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
