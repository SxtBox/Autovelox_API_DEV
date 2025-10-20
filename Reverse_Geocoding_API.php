<?php
/*
{
type: "node",
id: 15414443,
lat: 40.6061687,
lon: 15.108049,
tags: {
highway: "speed_camera"
}
*/

function getCityFromCoords($lat, $lon) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&addressdetails=1";

    $opts = [
        "http" => [
            "header" => "User-Agent: MyPHPApp/1.0\r\n"
        ]
    ];
    $context = stream_context_create($opts);

    $json = file_get_contents($url, false, $context);
    if ($json === false) return null;

    $data = json_decode($json, true);
    return $data['address']['city'] ?? 
           $data['address']['town'] ?? 
           $data['address']['village'] ?? 
           $data['address']['municipality'] ?? 
           null;
}

// Si ta përdorësh në skriptin tënd
//Në ciklin ku përpunon elementët (elements), për secilin lat/lon mund të thërrasësh këtë funksion dhe t’i shtosh qytetin në JSON:
foreach ($data['elements'] as &$elem) {
    if (isset($elem['lat'], $elem['lon'])) {
        $elem['city'] = getCityFromCoords($elem['lat'], $elem['lon']);
    }
}