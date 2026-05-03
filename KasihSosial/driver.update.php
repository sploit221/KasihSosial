<?php

include_once 'koneksi.php';
requireRole('driver');

// Hanya terima POST dengan tombol update_status
if (!isset($_POST['update_status'])) {
    header("Location: driver.dashboard.php"); exit;
}

// ── CSRF check ────────────────────────────────────────────────────────────────
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token keamanan tidak valid.');
    header("Location: driver.dashboard.php"); exit;
}

$tugas_id  = validateId($_POST['tugas_id']  ?? 0);
$status_input = validateEnum($_POST['status_baru'] ?? '', [
    'Menuju Penjemputan', 'Barang Diambil', 'Dalam Perjalanan', 'Tiba di Tujuan', 'Selesai'
]);
$driver_id = (int)$_SESSION['user_id'];

if (!$tugas_id || !$status_input) {
    flash('error', 'Data tidak valid.');
    header("Location: driver.dashboard.php"); exit;
}

// ── Pastikan tugas milik driver ini ──────────────────────────────────────────
$cek = dbQuery(
    "SELECT tp.tugas_id, tp.request_id, tp.status_pengantaran
     FROM tugas_pengantaran tp
     WHERE tp.tugas_id = ? AND tp.driver_id = ?",
    'ii', [$tugas_id, $driver_id]
)->get_result()->fetch_assoc();

if (!$cek) {
    flash('error', 'Tugas tidak ditemukan atau bukan milik Anda.');
    header("Location: driver.dashboard.php"); exit;
}

$request_id       = (int)$cek['request_id'];
$status_sekarang  = $cek['status_pengantaran'];


if ($status_input === 'Tiba di Tujuan') {
    $status_simpan = 'Selesai'; // driver selesai, simpan Selesai di tugas_pengantaran
} else {
    $status_simpan = $status_input;
}

// ── Update tugas_pengantaran ──────────────────────────────────────────────────
$stmt = dbQuery(
    "UPDATE tugas_pengantaran
     SET status_pengantaran = ?, updated_at = NOW()
     WHERE tugas_id = ? AND driver_id = ?",
    'sii', [$status_simpan, $tugas_id, $driver_id]
);

if ($stmt->affected_rows > 0) {

    if ($status_input === 'Tiba di Tujuan') {
        // Update donasi_request → status 'Tiba di Tujuan'
        // supaya dashboard penerima menampilkan tombol "Konfirmasi Diterima"
        dbQuery(
            "UPDATE donasi_request SET status = 'Tiba di Tujuan' WHERE request_id = ?",
            'i', [$request_id]
        );
        flash('success', 'Barang telah tiba di tujuan! Tugas selesai. Menunggu konfirmasi penerima.');

    } elseif ($status_input === 'Selesai') {
        // Fallback manual Selesai
        dbQuery(
            "UPDATE donasi_request SET status = 'Diterima' WHERE request_id = ?",
            'i', [$request_id]
        );
        flash('success', 'Tugas selesai! Terima kasih telah mengantarkan donasi.');

    } else {
        // Status perjalanan biasa
        flash('success', 'Status diperbarui menjadi: ' . $status_input);
    }

} else {
    // affected_rows = 0 bisa terjadi kalau status tidak berubah (pilih status yg sama)
    flash('info', 'Status tidak berubah (sudah ' . $status_sekarang . ').');
}

header("Location: driver.dashboard.php");
exit;
?>