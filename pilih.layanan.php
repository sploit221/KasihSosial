<?php
// pilih_layanan.php
include_once 'koneksi.php';
requireRole('penerima');

$penerima_id = (int)$_SESSION['user_id'];
$request_id  = validateId($_GET['request_id'] ?? 0);

// Ambil data request + tugas, pastikan milik penerima
$data = dbQuery(
    "SELECT dr.request_id, tp.tugas_id, tp.ongkos_kirim, tp.status_pembayaran,
            p.jenis_pakaian
     FROM donasi_request dr
     JOIN tugas_pengantaran tp ON dr.request_id = tp.request_id
     JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
     WHERE dr.request_id = ? AND dr.penerima_id = ?",
    'ii', [$request_id, $penerima_id]
)->get_result()->fetch_assoc();

if (!$data) {
    flash('error', 'Order tidak ditemukan atau bukan milik Anda.');
    header("Location: dashboard.penerima.php");
    exit;
}

$tugas_id = (int)$data['tugas_id'];
$order_id = $request_id; // tampilkan sebagai ID Order

// Layanan yang tersedia
$services = [
    'reguler'  => ['nama' => 'Reguler', 'desc' => '2-3 hari', 'harga' => 15000],
    'express'  => ['nama' => 'Express', 'desc' => '1 hari',   'harga' => 25000],
    'same_day' => ['nama' => 'Same Day','desc' => 'Hari ini', 'harga' => 40000],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pilih Layanan Pengiriman — KasihSosial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background: #f0f4f8; font-family: 'Plus Jakarta Sans', sans-serif; }
    .top-bar { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    .main-card { max-width: 480px; margin: 2rem auto; background: #fff; border-radius: 22px; box-shadow: 0 8px 30px rgba(0,0,0,.08); overflow: hidden; }
    .card-header-custom { background: linear-gradient(135deg, #4f46e5, #0891b2); color: #fff; padding: 1.25rem 1.5rem; }
    .order-id-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; margin: 1.25rem; }
    .service-option { border: 2px solid #e2e8f0; border-radius: 14px; padding: 1rem 1.2rem; margin: 0 1.25rem .75rem; cursor: pointer; transition: all .2s; display: flex; justify-content: space-between; align-items: center; }
    .service-option:hover, .service-option.active { border-color: #059669; background: #f0fdf4; }
    .service-option input[type=radio] { display: none; }
    .service-left { display: flex; flex-direction: column; }
    .service-name { font-weight: 700; font-size: 1.05rem; }
    .service-desc { font-size: .85rem; color: #64748b; }
    .service-price { font-weight: 800; font-size: 1.1rem; color: #065f46; }
    .btn-lanjut { display: block; margin: 1.25rem; border: none; border-radius: 14px; padding: .9rem; background: linear-gradient(135deg, #059669, #0d9488); color: #fff; font-weight: 700; font-size: 1rem; box-shadow: 0 4px 15px rgba(5,150,105,.3); text-align: center; text-decoration: none; transition: opacity .2s; }
    .btn-lanjut:hover { opacity: .9; color: #fff; }
  </style>
</head>
<body>
  <div class="top-bar">
    <div><strong>KasihSosial</strong> <span class="opacity-75 ms-2">Pilih Layanan</span></div>
    <a href="dashboard.penerima.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Dashboard</a>
  </div>

  <div class="container">
    <div class="main-card">
      <div class="card-header-custom">
        <h5 class="fw-bold mb-0"><i class="bi bi-truck me-2"></i>Pilih Layanan Pengiriman</h5>
        <small class="opacity-75">Langkah 1 dari 3</small>
      </div>

      <div class="order-id-box">
        <div class="small text-muted">ID Order Pengiriman</div>
        <div class="fw-bold fs-5"><?= $order_id ?></div>
        <div class="small text-muted">Barang: <?= e($data['jenis_pakaian'] ?? '—') ?></div>
      </div>

      <form method="post" action="pilih.metode.bayar.php" id="formLayanan">
        <input type="hidden" name="request_id" value="<?= $request_id ?>">
        <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
        <input type="hidden" name="layanan" id="layananInput" value="">

        <?php foreach ($services as $key => $svc): ?>
        <label class="service-option <?= $key==='same_day' ? 'active' : '' ?>">
          <input type="radio" name="layanan_radio" value="<?= $key ?>" <?= $key==='same_day' ? 'checked' : '' ?>>
          <div class="service-left">
            <span class="service-name"><?= $svc['nama'] ?></span>
            <span class="service-desc"><?= $svc['desc'] ?></span>
          </div>
          <span class="service-price">Rp <?= number_format($svc['harga'], 0, ',', '.') ?></span>
        </label>
        <?php endforeach; ?>

        <button type="submit" class="btn-lanjut mt-2">
          <i class="bi bi-arrow-right-circle me-2"></i>Lanjut
        </button>
      </form>
    </div>
  </div>

  <script>
    // Set hidden input sesuai radio yang dipilih
    const radios = document.querySelectorAll('input[name="layanan_radio"]');
    const hiddenInput = document.getElementById('layananInput');
    const labels = document.querySelectorAll('.service-option');

    function updateUI() {
      const selected = document.querySelector('input[name="layanan_radio"]:checked');
      if (selected) {
        hiddenInput.value = selected.value;
        labels.forEach(l => l.classList.remove('active'));
        selected.closest('.service-option').classList.add('active');
      }
    }

    radios.forEach(r => r.addEventListener('change', updateUI));
    // Init
    updateUI();
  </script>
</body>
</html>