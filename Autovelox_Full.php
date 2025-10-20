<?php
/**
 * autovelox.php
 *
 * Merr te gjitha pikat "speed_camera" në Itali nga OpenStreetMap (Overpass API)
 * dhe kthen JSON "full" me të gjithë tag-et për secilin autovelox.
 */

header('Content-Type: application/json; charset=utf-8');

$overpassUrl = "https://overpass-api.de/api/interpreter";

/**
 * Overpass QL query:
 *  - bbox për Italinë kontinentale + ishujt
 *  - highway=speed_camera
 */
$query = <<<OVERPASS
[out:json][timeout:30];
node["highway"="speed_camera"](35.4,6.6,47.1,18.8);
out body;
OVERPASS;

// Bëjmë POST request
$response = httpPost($overpassUrl, ['data' => $query]);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to contact Overpass API']);
    exit;
}

// Kthejmë direkt rezultatin JSON që vjen nga OSM
echo $response;


/**
 * Simple POST helper me cURL
 */
function httpPost(string $url, array $params = []): string|false {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
