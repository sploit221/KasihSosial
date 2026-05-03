<?php

include_once 'koneksi.php';
requireLogin();  

// ── Hanya terima POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: kelola.donasi.php"); exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token keamanan tidak valid. Muat ulang halaman.');
    header("Location: kelola.donasi.php"); exit;
}

$id      = validateId($_POST['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($id === 0) {
    flash('error', 'ID tidak valid.');
    header("Location: kelola.donasi.php"); exit;
}

// ── Ambil data & verifikasi kepemilikan ──────────────────────────────────────
$stmt = dbQuery(
    "SELECT foto_pakaian FROM pakaian WHERE pakaian_id = ? AND user_id = ?",
    'ii', [$id, $user_id]
);
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    flash('error', 'Barang tidak ditemukan atau bukan milik Anda.');
    header("Location: kelola.donasi.php"); exit;
}

// ── Hapus file foto (validasi path agar tidak ada path traversal) ─────────────
$nama_file = basename($res['foto_pakaian'] ?? ''); // basename() cegah path traversal
if ($nama_file !== '' && $nama_file !== 'default.jpg') {
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $nama_file;
    $real_base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    $real_file = realpath($file_path);

    // Pastikan file benar-benar di dalam folder uploads
    if ($real_file && $real_base && str_starts_with($real_file, $real_base)) {
        @unlink($real_file);
    }
}

// ── Hapus dari database ───────────────────────────────────────────────────────
$del = dbQuery(
    "DELETE FROM pakaian WHERE pakaian_id = ? AND user_id = ?",
    'ii', [$id, $user_id]
);

if ($del->affected_rows > 0) {
    flash('success', 'Barang berhasil dihapus.');
} else {
    flash('error', 'Gagal menghapus barang.');
}

header("Location: kelola.donasi.php");
exit;
?>