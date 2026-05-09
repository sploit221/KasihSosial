<?php
// pilih_metode_bayar.php
include_once 'koneksi.php';
requireRole('penerima');

$penerima_id = (int)$_SESSION['user_id'];
$request_id  = validateId($_POST['request_id'] ?? 0);
$tugas_id    = validateId($_POST['tugas_id']   ?? 0);
$layanan     = $_POST['layanan'] ?? 'same_day';

// Validasi kepemilikan
$cek = dbQuery(
    "SELECT tp.tugas_id FROM tugas_pengantaran tp
     JOIN donasi_request dr ON tp.request_id = dr.request_id
     WHERE tp.tugas_id = ? AND dr.penerima_id = ?",
    'ii', [$tugas_id, $penerima_id]
);
if ($cek->get_result()->num_rows === 0) {
    flash('error', 'Tugas tidak valid.');
    header("Location: dashboard.penerima.php");
    exit;
}

// Harga sesuai layanan
$prices = [
    'reguler'  => 15000,
    'express'  => 25000,
    'same_day' => 40000,
];
if (!isset($prices[$layanan])) $layanan = 'same_day';
$ongkos = $prices[$layanan];

// Sinkronkan ongkos_kirim ke database
dbQuery("UPDATE tugas_pengantaran SET ongkos_kirim = ? WHERE tugas_id = ?", 'ii', [$ongkos, $tugas_id]);

$nama_layanan = [
    'reguler'  => 'Reguler (2-3 hari)',
    'express'  => 'Express (1 hari)',
    'same_day' => 'Same Day (Hari ini)'
][$layanan];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pilih Metode Pembayaran — KasihSosial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background:#f0f4f8; font-family:'Plus Jakarta Sans',sans-serif; }
    .top-bar { background: linear-gradient(135deg,#1e293b,#0f172a); color:#fff; padding:.75rem 1.5rem; display:flex; justify-content:space-between; align-items:center; }
    .main-card { max-width:500px; margin:2rem auto; background:#fff; border-radius:22px; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; }
    .card-header-custom { background: linear-gradient(135deg,#4f46e5,#0891b2); color:#fff; padding:1.25rem 1.5rem; }
    .info-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1rem; margin:1.25rem; }
    .payment-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin:1.25rem; }
    .pay-card { border:2px solid #e2e8f0; border-radius:16px; padding:1.4rem .8rem; text-align:center; cursor:pointer; transition:all .2s; }
    .pay-card.active { border-color:#059669; background:#ecfdf5; box-shadow:0 0 0 4px rgba(5,150,105,.15); }
    .pay-card i { font-size:2rem; display:block; margin-bottom:.5rem; }
    .pay-name { font-weight:700; margin-bottom:.2rem; }
    .pay-desc { font-size:.75rem; color:#64748b; }
    .total-badge { display:inline-block; background:#ecfdf5; border:1px solid #6ee7b7; border-radius:10px; padding:.25rem .75rem; font-weight:800; color:#065f46; font-size:1.05rem; margin-bottom:1rem; }
    .btn-lanjut { border:none; border-radius:14px; padding:.9rem; background: linear-gradient(135deg,#059669,#0d9488); color:#fff; font-weight:700; font-size:1rem; box-shadow:0 4px 15px rgba(5,150,105,.3); width:100%; margin:1.25rem 0; transition:opacity .2s; }
    .btn-lanjut:hover { opacity:.9; }
  </style>
</head>
<body>
  <div class="top-bar">
    <div><strong>KasihSosial</strong> <span class="opacity-75 ms-2">Metode Pembayaran</span></div>
    <a href="pilih.layanan.php?request_id=<?= $request_id ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
  </div>

  <div class="container">
    <div class="main-card">
      <div class="card-header-custom">
        <h5 class="fw-bold mb-0"><i class="bi bi-wallet2 me-2"></i>Pilih Metode Pembayaran</h5>
        <small class="opacity-75">Langkah 2 dari 3</small>
      </div>

      <div class="info-box">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <strong><?= $nama_layanan ?></strong><br>
            <span class="text-muted small">Order #<?= $request_id ?></span>
          </div>
          <div class="total-badge">Rp <?= number_format($ongkos, 0, ',', '.') ?></div>
        </div>
      </div>

      <form method="post" action="ringkasan.bayar.php" id="formMetode">
        <input type="hidden" name="request_id" value="<?= $request_id ?>">
        <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
        <input type="hidden" name="layanan" value="<?= $layanan ?>">
        <input type="hidden" name="metode" id="metodeInput" value="transfer">

        <div class="payment-grid" id="payGrid">
          <div class="pay-card active" data-method="transfer">
            <i class="bi bi-bank2" style="color:#4f46e5;"></i>
            <div class="pay-name">Transfer Bank</div>
            <div class="pay-desc">BCA, BNI, Mandiri</div>
          </div>
          <div class="pay-card" data-method="dana">
            <i class="bi bi-wallet2" style="color:#7c3aed;"></i>
            <div class="pay-name">DANA</div>
            <div class="pay-desc">Dompet Digital</div>
          </div>
          <div class="pay-card" data-method="cod">
            <i class="bi bi-cash-stack" style="color:#059669;"></i>
            <div class="pay-name">COD</div>
            <div class="pay-desc">Bayar di Tempat</div>
          </div>
        </div>

        <button type="submit" class="btn-lanjut">
          <i class="bi bi-arrow-right-circle me-2"></i>Lanjut
        </button>
      </form>
    </div>
  </div>

  <script>
    const cards = document.querySelectorAll('.pay-card');
    const hiddenMetode = document.getElementById('metodeInput');
    cards.forEach(card => {
      card.addEventListener('click', function() {
        cards.forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        hiddenMetode.value = this.dataset.method;
      });
    });
  </script>
</body>
</html>