<?php
include_once 'koneksi.php';

$request_id = validateId($_GET['request_id'] ?? 0);
$data = dbQuery(
    "SELECT ld.latitude, ld.longitude, ld.created_at
     FROM lokasi_driver ld
     JOIN tugas_pengantaran tp ON tp.tugas_id = ld.tugas_id
     WHERE tp.request_id = ? AND tp.status_pengantaran != 'Selesai'
     ORDER BY ld.created_at DESC LIMIT 1",
    'i', [$request_id]
)->get_result()->fetch_assoc();

echo json_encode($data ?: ['error' => 'Lokasi belum tersedia']);