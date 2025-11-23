<?php
if (!isset($_GET['z'], $_GET['x'], $_GET['y'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Parámetros inválidos";
    exit;
}

$z = intval($_GET['z']);
$x = intval($_GET['x']);
$y = intval($_GET['y']);

$url = "https://tile.openstreetmap.org/$z/$x/$y.png";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'TuCalleTuImagen/1.0 (https://arstore.tech/)');
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($http_code == 200) {
    header("Content-Type: $content_type");
    echo $response;
} else {
    header("HTTP/1.1 $http_code Error");
    echo "Error al cargar la imagen ($http_code)";
}

