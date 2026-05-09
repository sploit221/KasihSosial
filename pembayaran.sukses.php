<?php
// pembayaran.sukses.php
include_once 'koneksi.php';
requireRole('penerima');

$payment_id = $_GET['payment_id'] ?? 'unknown';
$request_id = validateId($_GET['request_id'] ?? 0);
$tugas_id   = validateId($_GET['tugas_id']   ?? 0);
$metode     = $_GET['metode'] ?? 'transfer';

$ongkos = 0;
if ($tugas_id) {
    $res = dbQuery("SELECT ongkos_kirim FROM tugas_pengantaran WHERE tugas_id = ?", 'i', [$tugas_id]);
    $row = $res->get_result()->fetch_assoc();
    if ($row) $ongkos = (int)$row['ongkos_kirim'];
}

$method_label = match($metode) {
    'cod'      => 'COD (Bayar di Tempat)',
    'dana'     => 'DANA',
    'transfer' => 'Transfer Bank',
    default    => $metode,
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Pembayaran Berhasil — KasihSosial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background:#f0f4f8; font-family:'Plus Jakarta Sans',sans-serif; }
    .top-bar { background: linear-gradient(135deg,#1e293b,#0f172a); color:#fff; padding:.75rem 1.5rem; }
    .main-card { max-width:460px; margin:2rem auto; background:#fff; border-radius:22px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
    .card-header-custom { background: linear-gradient(135deg,#059669,#0d9488); color:#fff; padding:1.5rem; text-align:center; }
    .card-header-custom i { font-size:3rem; display:block; margin-bottom:.75rem; }
    .detail-box { background:#f8fafc; border-radius:14px; padding:1.2rem; margin:1.25rem; }
    .detail-row { display:flex; justify-content:space-between; margin-bottom:.5rem; font-size:.9rem; }
    .btn-dashboard { display:block; margin:0 1.25rem 1.5rem; border:none; border-radius:14px; padding:.85rem; background:#4f46e5; color:#fff; font-weight:700; font-size:1rem; text-align:center; text-decoration:none; box-shadow:0 4px 15px rgba(79,70,229,.3); transition:opacity .2s; }
    .btn-dashboard:hover { opacity:.9; color:#fff; }
  </style>
</head>
<body>
  <div class="top-bar">
    <strong>KasihSosial</strong>
    <span class="opacity-75">Pembayaran</span>
  </div>

  <div class="container">
    <div class="main-card">
      <div class="card-header-custom">
        <i class="bi bi-check-circle-fill"></i>
        <h4 class="fw-bold"><?= $metode === 'cod' ? 'Pembayaran Dicatat!' : 'Bukti Terkirim!' ?></h4>
        <p class="mb-0 small opacity-75">
          <?= $metode === 'cod' ? 'Siapkan uang tunai saat driver tiba.' : 'Terima kasih, tim kami akan memverifikasi pembayaran Anda.' ?>
        </p>
      </div>

      <div class="detail-box">
        <div class="detail-row"><span class="text-muted">ID Pembayaran</span><strong class="text-dark"><?= e($payment_id) ?></strong></div>
        <div class="detail-row"><span class="text-muted">Order ID</span><strong><?= $request_id ?></strong></div>
        <div class="detail-row"><span class="text-muted">Jumlah</span><strong>Rp <?= number_format($ongkos, 0, ',', '.') ?></strong></div>
        <div class="detail-row"><span class="text-muted">Metode</span><strong><?= $method_label ?></strong></div>
        <div class="detail-row"><span class="text-muted">Status</span>
          <span class="badge bg-success">
            <i class="bi bi-check-circle me-1"></i><?= $metode === 'cod' ? 'Pembayaran Dikonfirmasi' : 'Menunggu Verifikasi' ?>
          </span>
        </div>
      </div>

      <a href="dashboard.penerima.php" class="btn-dashboard">
        <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
      </a>
    </div>
  </div>
</body>
</html>