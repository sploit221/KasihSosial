<?php
include_once 'koneksi.php';
requireRole('driver');

$driver_id = (int)$_SESSION['user_id'];
$tugas_id  = validateId($_POST['tugas_id'] ?? 0);
$lat = (float)($_POST['lat'] ?? 0);
$lng = (float)($_POST['lng'] ?? 0);

if (!$tugas_id || !$lat || !$lng) {
    http_response_code(400); exit;
}

// Pastikan tugas milik driver ini
$cek = dbQuery("SELECT tugas_id FROM tugas_pengantaran 
    WHERE tugas_id = ? AND driver_id = ?", 'ii', [$tugas_id, $driver_id]);
if ($cek->get_result()->num_rows === 0) { http_response_code(403); exit; }

dbQuery(
    "INSERT INTO lokasi_driver (driver_id, tugas_id, latitude, longitude, akurasi)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE 
         latitude = VALUES(latitude), longitude = VALUES(longitude),
         akurasi = VALUES(akurasi), created_at = NOW()",
    'iiddd', [$driver_id, $tugas_id, $lat, $lng, $_POST['akurasi'] ?? null]
);

echo json_encode(['status' => 'ok']);