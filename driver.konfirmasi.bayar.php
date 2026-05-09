<?php
include_once 'koneksi.php';
requireRole('driver');

$tugas_id = validateId($_GET['tugas_id'] ?? 0);
$driver_id = (int)$_SESSION['user_id'];

// Ambil data tugas
$tugas = dbQuery(
    "SELECT tp.tugas_id, tp.request_id, tp.ongkos_kirim,
            tp.status_pembayaran, tp.status_pengantaran,
            u_penerima.username AS nama_penerima
     FROM tugas_pengantaran tp
     JOIN donasi_request dr ON tp.request_id = dr.request_id
     JOIN users u_penerima ON dr.penerima_id = u_penerima.user_id
     WHERE tp.tugas_id = ? AND tp.driver_id = ?",
    'ii', [$tugas_id, $driver_id]
)->get_result()->fetch_assoc();

if (!$tugas) {
    flash('error', 'Tugas tidak ditemukan.');
    header("Location: driver.dashboard.php"); exit;
}

if ($tugas['status_pembayaran'] !== 'belum_dibayar') {
    flash('warning', 'Pembayaran sudah dikonfirmasi sebelumnya.');
    header("Location: driver.dashboard.php"); exit;
}

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token tidak valid.');
        header("Location: driver.dashboard.php"); exit;
    }

    $metode = validateEnum($_POST['metode_pembayaran'] ?? '', ['cash', 'DANA', 'QRIS']);
    if (!$metode) {
        flash('error', 'Pilih metode pembayaran.');
        header("Location: driver.konfirmasi.bayar.php?tugas_id=" . $tugas_id); exit;
    }

    // CASH → langsung lunas & selesai
    if ($metode === 'cash') {
        dbQuery("UPDATE tugas_pengantaran SET status_pembayaran = 'cash_lunas',
                 metode_pembayaran = 'cash' WHERE tugas_id = ?", 'i', [$tugas_id]);

        dbQuery("UPDATE tugas_pengantaran SET status_pengantaran = 'Selesai',
                 updated_at = NOW() WHERE tugas_id = ?", 'i', [$tugas_id]);
        dbQuery("UPDATE donasi_request SET status = 'Diterima' WHERE request_id = ?",
                'i', [$tugas['request_id']]);

        flash('success', 'Pembayaran cash dikonfirmasi. Tugas selesai!');
        header("Location: driver.dashboard.php"); exit;
    }

    // DANA / QRIS → simpan metode & alihkan ke panduan
    dbQuery("UPDATE tugas_pengantaran SET metode_pembayaran = ?
             WHERE tugas_id = ?", 'si', [$metode, $tugas_id]);

    header("Location: panduan.bayar.php?tugas_id={$tugas_id}&metode={$metode}");
    exit;
}

// Tampilkan form pilih metode (hanya cash / DANA / QRIS)
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Konfirmasi Pembayaran — Driver</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background: #f0f4f8; font-family: 'Plus Jakarta Sans', sans-serif; }
    .konfirm-card { max-width: 500px; margin: 2rem auto; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card konfirm-card shadow-lg border-0 rounded-4">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-3">
          <i class="bi bi-cash-stack me-2 text-success"></i>Konfirmasi Pembayaran
        </h5>
        <p>Tugas #<?= $tugas['tugas_id']; ?> &nbsp;·&nbsp;
          Penerima: <strong><?= e($tugas['nama_penerima']); ?></strong>
        </p>
        <p>Ongkos Kirim:
          <strong class="text-success">Rp <?= number_format($tugas['ongkos_kirim'], 0, ',', '.'); ?></strong>
        </p>

        <form method="POST">
          <?= csrfField(); ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Metode Pembayaran</label>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="metode_pembayaran" value="cash" id="cash" required>
              <label class="form-check-label" for="cash">💵 Tunai / Cash</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="metode_pembayaran" value="DANA" id="dana">
              <label class="form-check-label" for="dana">💳 Transfer DANA</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="metode_pembayaran" value="QRIS" id="qris">
              <label class="form-check-label" for="qris">📱 QRIS (BCA, Mandiri, dll)</label>
            </div>
          </div>
          <button type="submit" class="btn btn-success w-100 fw-bold">
            <i class="bi bi-arrow-right-circle me-1"></i>Lanjutkan
          </button>
        </form>
        <a href="driver.dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Batal</a>
      </div>
    </div>
  </div>
</body>
</html>