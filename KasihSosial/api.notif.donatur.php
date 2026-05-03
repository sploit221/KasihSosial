<?php

include_once 'koneksi.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['pending_count' => 0]);
    exit;
}

$my_id = (int)$_SESSION['user_id'];

// 1. Hitung Permintaan Barang (Request) yang statusnya 'Pending'
$sql_req = "SELECT COUNT(*) AS n 
            FROM donasi_request dr 
            JOIN pakaian p ON dr.pakaian_id = p.pakaian_id 
            WHERE p.user_id = ? AND dr.status = 'Pending'";
$res_req = dbQuery($sql_req, 'i', [$my_id])->get_result()->fetch_assoc();
$count_req = (int)($res_req['n'] ?? 0);


$sql_chat = "SELECT COUNT(DISTINCT cd.pengirim_id) AS n
             FROM chat_donasi cd
             JOIN pakaian p ON cd.pakaian_id = p.pakaian_id
             WHERE p.user_id = ?";
$res_chat = dbQuery($sql_chat, 'i', [$my_id])->get_result()->fetch_assoc();
$count_chat = (int)($res_chat['n'] ?? 0);

// Gabungkan keduanya
$total_pending = $count_req + $count_chat;

echo json_encode([
    'status'        => 'success',
    'pending_count' => $total_pending,
    'timestamp'     => date('H:i:s'),
    'debug'         => ['req' => $count_req, 'chat' => $count_chat] // Untuk mempermudah cek jika 0 lagi
]);
?>