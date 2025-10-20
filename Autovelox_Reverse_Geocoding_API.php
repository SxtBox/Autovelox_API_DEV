<?php
/**

Kujdes:
Kam vendosur një usleep(300000) për të mos bërë më shumë se ~3 kërkesa/sekondë në Nominatim.

Nëse ke shumë pikat, ky skript do marrë pak më shumë kohë për t’u ekzekutuar.

Për performancë më të mirë, mund ta bëjmë paralelisht (curl_multi) ose me cache, por ky version është i thjeshtë dhe funksional.

Përdorim:
https://domaini.yt/autovelox.php — kthen full + city
http://localhost/4/Autovelox/Autovelox_Reverse_Geocoding_API.php?format=minimal
http://localhost/4/Autovelox/Autovelox_Reverse_Geocoding_API.php?format=minimal — kthen minimal + city

http://localhost/4/Autovelox/Autovelox_Reverse_Geocoding_API.php?bbox=44.9,8.9,46.2,10.2 — filtër zona

Kombino parametra sipas dëshirës.

 * autovelox.php
 *
 * Merr pikat "speed_camera" nga OpenStreetMap (Overpass API),
 * dhe bën reverse geocoding në Nominatim për të marrë qytetin,
 * duke e shtuar në JSON output.
 */

header('Content-Type: application/json; charset=utf-8');

$default_bbox = '35.4,6.6,47.1,18.8'; // Italia kontinentale + ishujt (lat1,lon1,lat2,lon2)

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

if ($format === 'minimal') {
    $minimal = [];
    foreach ($data['elements'] as $elem) {
        if (!isset($elem['lat'], $elem['lon'])) continue;
        $name = $elem['tags']['name'] ?? ($elem['tags']['ref'] ?? null);
        $city = getCityFromCoords($elem['lat'], $elem['lon']);
        $minimal[] = [
            'lat' => $elem['lat'],
            'lon' => $elem['lon'],
            'name' => $name,
            'city' => $city
        ];
        // Shmang API overload: ndalo pak mes kërkesave
        //usleep(300000); // 300 ms (rreth 3 kërkesa/sekondë)
    }
    echo json_encode($minimal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Shto city në secilin element full
    foreach ($data['elements'] as &$elem) {
        if (isset($elem['lat'], $elem['lon'])) {
            $elem['city'] = getCityFromCoords($elem['lat'], $elem['lon']);
            //usleep(300000);
        }
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Merr emrin e qytetit nga lat/lon me Nominatim API
 https://nominatim.openstreetmap.org/reverse?format=json&lat=40.6061687&lon=15.108049&addressdetails=1
 https://nominatim.openstreetmap.org/reverse?format=json&lat=43.7697955&lon=11.2556404&addressdetails=1
 */
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
