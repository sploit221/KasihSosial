<?php

include_once 'koneksi.php';
requireRole('admin'); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.dashboard.php"); exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token keamanan tidak valid.');
    header("Location: admin.dashboard.php"); exit;
}

$id = validateId($_POST['id'] ?? 0);

if ($id === 0) {
    flash('error', 'ID tidak valid.');
    header("Location: admin.dashboard.php"); exit;
}

// ── Fetch photo filename ──────────────────────────────────────────────────────
$stmt = dbQuery("SELECT foto_pakaian FROM pakaian WHERE pakaian_id = ?", 'i', [$id]);
$res  = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    flash('error', 'Data tidak ditemukan.');
    header("Location: admin.dashboard.php"); exit;
}

// ── Hapus file (dengan path traversal protection) ────────────────────────────
$nama_file = basename($res['foto_pakaian'] ?? '');
if ($nama_file !== '' && $nama_file !== 'default.jpg') {
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $nama_file;
    $real_base = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    $real_file = realpath($file_path);

    if ($real_file && $real_base && str_starts_with($real_file, $real_base)) {
        @unlink($real_file);
    }
}

// ── Hapus dari database ───────────────────────────────────────────────────────
$del = dbQuery("DELETE FROM pakaian WHERE pakaian_id = ?", 'i', [$id]);

if ($del->affected_rows > 0) {
    flash('success', 'Data pakaian berhasil dihapus.');
} else {
    flash('error', 'Gagal menghapus data.');
}

header("Location: admin.dashboard.php");
exit;
?>