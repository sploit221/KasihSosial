<?php

include_once 'koneksi.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$penerima_id = (int)$_SESSION['user_id'];
$request_id  = validateId($_GET['request_id'] ?? 0);

if (!$request_id) {
    echo json_encode(['error' => 'ID tidak valid']); exit;
}

// Ambil status terbaru — pastikan milik penerima ini
$row = dbQuery(
    "SELECT dr.status AS status_request,
            tp.status_pengantaran,
            u_driver.username AS nama_driver,
            u_driver.no_hp    AS hp_driver
     FROM donasi_request dr
     LEFT JOIN tugas_pengantaran tp ON tp.request_id = dr.request_id
     LEFT JOIN users u_driver       ON tp.driver_id  = u_driver.user_id
     WHERE dr.request_id = ? AND dr.penerima_id = ?",
    'ii', [$request_id, $penerima_id]
)->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['error' => 'Data tidak ditemukan']); exit;
}

// Bersihkan no_hp untuk format wa.me (hanya angka, ganti 0 depan jadi 62)
$hp_driver_wa = '';
if (!empty($row['hp_driver'])) {
    $hp = preg_replace('/\D/', '', $row['hp_driver']);
    if (str_starts_with($hp, '0')) $hp = '62' . substr($hp, 1);
    $hp_driver_wa = $hp;
}

echo json_encode([
    'status_request'    => $row['status_request'],
    'status_pengantaran'=> $row['status_pengantaran'],
    'driver_tiba'       => ($row['status_request'] === 'Tiba di Tujuan'),
    'sudah_diterima'    => ($row['status_request'] === 'Diterima'),
    'nama_driver'       => $row['nama_driver'] ?? null,
    'hp_driver_wa'      => $hp_driver_wa,
    'timestamp'         => date('H:i:s'),
]);