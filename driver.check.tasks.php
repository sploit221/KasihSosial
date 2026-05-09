<?php
// 1. Memulai Session & Koneksi
session_start();
include_once 'koneksi.php';

header('Content-Type: application/json');

// 2. Proteksi: Hanya Driver yang bisa mengakses API ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$driver_id = $_SESSION['user_id'];

// Query untuk menghitung jumlah tugas aktif yang perlu dikerjakan
$sql = "SELECT COUNT(*) as jumlah_tugas 
        FROM tugas_pengantaran 
        WHERE driver_id = ? 
        AND status_pengantaran = 'Pending'";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $driver_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

// 3. Mengirimkan respon dalam format JSON
echo json_encode([
    'status' => 'success',
    'jumlah_tugas' => (int)$row['jumlah_tugas'],
    'timestamp' => date('H:i:s')
]);

$stmt->close();
$conn->close();
?>