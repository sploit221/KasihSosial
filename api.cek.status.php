<?php
include_once 'koneksi.php';

// Pastikan user sudah login
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

$request_id = validateId($_GET['request_id'] ?? 0);
$my_id      = (int)$_SESSION['user_id'];
$my_role    = $_SESSION['role'] ?? '';

// hanya penerima yang bisa mengakses request miliknya
if ($my_role !== 'penerima') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}

$stmt = dbQuery(
    "SELECT dr.status AS status_request,
            tp.status_pengantaran,
            u_driver.username AS nama_driver,
            u_driver.no_hp    AS hp_driver
     FROM donasi_request dr
     LEFT JOIN tugas_pengantaran tp ON dr.request_id = tp.request_id
     LEFT JOIN users u_driver ON tp.driver_id = u_driver.user_id
     WHERE dr.request_id = ? AND dr.penerima_id = ?",
    'ii', [$request_id, $my_id]
);
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    die(json_encode(['error' => 'Not found']));
}

// Normalisasi nomor WA (hapus karakter non-digit, ubah awalan 0 menjadi 62)
$hp_driver_wa = '';
if (!empty($row['hp_driver'])) {
    $hp_driver_wa = preg_replace('/\D/', '', $row['hp_driver']);
    if (str_starts_with($hp_driver_wa, '0')) {
        $hp_driver_wa = '62' . substr($hp_driver_wa, 1);
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status_request'     => $row['status_request'],
    'status_pengantaran' => $row['status_pengantaran'],
    'nama_driver'        => $row['nama_driver'],
    'hp_driver_wa'       => $hp_driver_wa,
    'driver_tiba'        => ($row['status_request'] === 'Tiba di Tujuan'),
    'sudah_diterima'     => ($row['status_request'] === 'Diterima'),
]);
exit;