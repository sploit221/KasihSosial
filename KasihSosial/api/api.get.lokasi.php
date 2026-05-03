<?php
header('Content-Type: application/json');
include_once 'koneksi.php'; // Pastikan di dalamnya ada variabel $conn

// Sesuaikan dengan JavaScript yang mengirim parameter ?id=
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0; 

if ($id <= 0) {
    echo json_encode(['error' => 'ID tidak valid']);
    exit;
}

$query = "SELECT tp.latitude, tp.longitude, tp.status_pengantaran as status 
          FROM tugas_pengantaran tp 
          WHERE tp.request_id = ?";

$stmt = $conn->prepare($query); // Gunakan $conn sesuai file koneksi Anda
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data) {
    echo json_encode($data);
} else {
    // Fallback jika belum ada di tugas_pengantaran, ambil dari request
    $query_alt = "SELECT latitude, longitude, status FROM donasi_request WHERE request_id = ?";
    $stmt_alt = $conn->prepare($query_alt);
    $stmt_alt->bind_param('i', $id);
    $stmt_alt->execute();
    echo json_encode($stmt_alt->get_result()->fetch_assoc());
}
?>