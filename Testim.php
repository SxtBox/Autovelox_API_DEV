<?php
$bbox = '35.4,6.6,47.1,18.8';
$query = "[out:json][timeout:30];node[\"highway\"=\"speed_camera\"]($bbox);out body;";
$response = file_get_contents("https://overpass-api.de/api/interpreter?data=" . urlencode($query));

if ($response === false) {
    echo "Failed to get Response";
} else {
    echo "Raw Response:\n$response\n";
}
