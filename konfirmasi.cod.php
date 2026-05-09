<?php
// konfirmasi.cod.php
include_once 'koneksi.php';
requireRole('penerima');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.penerima.php");
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token keamanan tidak valid.');
    header("Location: dashboard.penerima.php");
    exit;
}

$penerima_id = (int)$_SESSION['user_id'];
$tugas_id    = validateId($_POST['tugas_id']   ?? 0);
$request_id  = validateId($_POST['request_id'] ?? 0);

// Pastikan tugas milik penerima
$tugas = dbQuery(
    "SELECT tp.tugas_id FROM tugas_pengantaran tp
     JOIN donasi_request dr ON tp.request_id = dr.request_id
     WHERE tp.tugas_id = ? AND dr.penerima_id = ?",
    'ii', [$tugas_id, $penerima_id]
)->get_result()->fetch_assoc();

if (!$tugas) {
    flash('error', 'Tugas tidak valid.');
    header("Location: dashboard.penerima.php");
    exit;
}

// Set metode COD, biarkan status_pembayaran tetap 'belum_dibayar' (nanti driver yang konfirmasi cash)
dbQuery(
    "UPDATE tugas_pengantaran SET metode_pembayaran = 'cod' WHERE tugas_id = ?",
    'i', [$tugas_id]
);

// Buat ID pembayaran unik untuk ditampilkan
$payment_id = uniqid('pay_');

// Redirect ke halaman sukses
header("Location: pembayaran.sukses.php?payment_id=" . urlencode($payment_id) . "&request_id=" . $request_id . "&tugas_id=" . $tugas_id . "&metode=cod");
exit;