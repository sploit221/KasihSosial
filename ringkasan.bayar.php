<?php
// ringkasan_bayar.php
include_once 'koneksi.php';
requireRole('penerima');

$penerima_id = (int)$_SESSION['user_id'];
$request_id  = validateId($_POST['request_id'] ?? 0);
$tugas_id    = validateId($_POST['tugas_id']   ?? 0);
$layanan     = $_POST['layanan'] ?? 'same_day';
$metode      = $_POST['metode']  ?? 'transfer';

// Validasi kepemilikan
$tugas = dbQuery(
    "SELECT tp.ongkos_kirim FROM tugas_pengantaran tp
     JOIN donasi_request dr ON tp.request_id = dr.request_id
     WHERE tp.tugas_id = ? AND dr.penerima_id = ?",
    'ii', [$tugas_id, $penerima_id]
)->get_result()->fetch_assoc();

if (!$tugas) {
    flash('error', 'Data tidak valid.');
    header("Location: dashboard.penerima.php"); exit;
}

$ongkos = (int)$tugas['ongkos_kirim'];
$nama_layanan = ['reguler' => 'Reguler (2-3 hari)', 'express' => 'Express (1 hari)', 'same_day' => 'Same Day (Hari ini)'][$layanan] ?? 'Same Day';

// Info rekening (sesuaikan dengan konfigurasi di koneksi.php)
$bank_name    = defined('ADMIN_BANK_NAME') ? ADMIN_BANK_NAME : 'BCA';
$bank_acc     = defined('ADMIN_BANK_ACCOUNT') ? ADMIN_BANK_ACCOUNT : '1234567890';
$bank_holder  = defined('ADMIN_BANK_HOLDER') ? ADMIN_BANK_HOLDER : 'KasihSosial Foundation';
$dana_number  = defined('ADMIN_DANA_NUMBER') ? ADMIN_DANA_NUMBER : '0812-3456-7890';
$dana_holder  = defined('ADMIN_DANA_HOLDER') ? ADMIN_DANA_HOLDER : 'KasihSosial';

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ringkasan Pembayaran — KasihSosial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background: #f0f4f8; font-family: 'Plus Jakarta Sans', sans-serif; }
    .top-bar { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: .75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    .main-card { max-width: 500px; margin: 2rem auto; background: #fff; border-radius: 22px; box-shadow: 0 8px 30px rgba(0,0,0,.08); overflow: hidden; }
    .card-header-custom { background: linear-gradient(135deg, #4f46e5, #0891b2); color: #fff; padding: 1.25rem 1.5rem; }
    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; margin-bottom: 1rem; }
    .instruksi { background: #eff6ff; border-left: 4px solid #4f46e5; border-radius: 0 12px 12px 0; padding: 1rem; margin-bottom: 1.25rem; }
    .instruksi.dana { background: #faf5ff; border-left-color: #7c3aed; }
    .instruksi h6 { font-weight: 700; margin-bottom: .5rem; }
    .rekening { font-size: 1.2rem; font-weight: 800; letter-spacing: 0.5px; margin: .3rem 0; }
    .btn-saldo { background: #e2e8f0; border: none; border-radius: 8px; padding: .35rem .8rem; font-size: .8rem; font-weight: 600; cursor: pointer; }
    .form-control { border-radius: 10px; border-color: #d1d5db; font-size: .9rem; }
    .btn-kirim { border: none; border-radius: 14px; padding: .85rem; background: linear-gradient(135deg, #059669, #0d9488); color: #fff; font-weight: 700; font-size: 1rem; box-shadow: 0 4px 15px rgba(5,150,105,.3); width: 100%; margin-top: 1rem; transition: opacity .2s; }
    .btn-kirim:hover { opacity: .9; }
    .file-upload { position: relative; overflow: hidden; display: inline-block; width: 100%; }
    .file-upload input[type=file] { position: absolute; top: 0; right: 0; min-width: 100%; min-height: 100%; opacity: 0; cursor: pointer; }
    .file-upload label { display: block; background: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 1rem 1.2rem; text-align: center; cursor: pointer; transition: .2s; }
    .file-upload label:hover { background: #e2e8f0; }
  </style>
</head>
<body>
  <div class="top-bar">
    <div><strong>KasihSosial</strong> <span class="opacity-75 ms-2">Ringkasan Pembayaran</span></div>
    <a href="pilih.metode.bayar.php?request_id=<?= $request_id ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
  </div>

  <div class="container">
    <div class="main-card">
      <div class="card-header-custom">
        <h5 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Ringkasan Pembayaran</h5>
        <small class="opacity-75">Langkah 3 dari 3</small>
      </div>

      <div class="p-3">
        <div class="info-box">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Layanan</span>
            <strong><?= $nama_layanan ?></strong>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Metode</span>
            <strong>
              <?= $metode === 'transfer' ? 'Transfer Bank' : ($metode === 'dana' ? 'DANA' : 'COD') ?>
            </strong>
          </div>
          <hr>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Total Ongkir</span>
            <strong class="text-success">Rp <?= number_format($ongkos, 0, ',', '.') ?></strong>
          </div>
        </div>

        <?php if ($metode !== 'cod'): ?>
          <!-- Instruksi Transfer -->
          <div class="instruksi <?= $metode === 'dana' ? 'dana' : '' ?>">
            <h6>Instruksi Pembayaran</h6>
            <?php if ($metode === 'transfer'): ?>
              <div>Nomor Rekening / Akun</div>
              <div class="rekening"><?= $bank_acc ?></div>
              <div class="small text-muted">a.n. <?= $bank_holder ?></div>
            <?php else: ?>
              <div>Nomor DANA</div>
              <div class="rekening"><?= $dana_number ?></div>
              <div class="small text-muted">a.n. <?= $dana_holder ?></div>
            <?php endif; ?>
            <div class="mt-2 d-flex gap-2">
              <button class="btn-saldo" onclick="copyText('<?= $metode==='dana' ? $dana_number : $bank_acc ?>', this)"><i class="bi bi-clipboard me-1"></i>Salin</button>
            </div>
          </div>
        <?php else: ?>
          <div class="instruksi">
            <h6>Bayar di Tempat (COD)</h6>
            <p>Siapkan uang tunai <strong>Rp <?= number_format($ongkos, 0, ',', '.') ?></strong> saat driver tiba. Uang pas sangat membantu driver kami.</p>
          </div>
        <?php endif; ?>

        <!-- Form data pembayar -->
        <form method="post" enctype="multipart/form-data" action="<?= $metode === 'cod' ? 'konfirmasi.cod.php' : 'upload.bukti.bayar.php' ?>" id="formBayar">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" name="tugas_id" value="<?= $tugas_id ?>">
          <input type="hidden" name="request_id" value="<?= $request_id ?>">
          <input type="hidden" name="metode_pembayaran" value="<?= $metode ?>">
          <?php if ($metode === 'transfer'): ?>
            <input type="hidden" name="bank_asal" id="bank_asal_hidden" value="">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Lengkap</label>
            <input type="text" name="nama_pembayaran" class="form-control" placeholder="Nama sesuai rekening" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nomor Telepon</label>
            <input type="tel" name="telp_pembayar" class="form-control" placeholder="08xxxxxxxxxx" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Catatan (opsional)</label>
            <textarea name="catatan" class="form-control" rows="2" placeholder="Instruksi khusus untuk driver..."></textarea>
          </div>

          <?php if ($metode !== 'cod'): ?>
            <?php if ($metode === 'transfer'): ?>
              <div class="mb-3">
                <label class="form-label fw-semibold">Bank Asal</label>
                <input type="text" name="bank_asal_display" id="bank_asal_display" class="form-control" placeholder="Contoh: BCA, BNI, Mandiri" required>
                <small class="text-muted">Bank yang Anda gunakan untuk transfer</small>
              </div>
            <?php endif; ?>

            <div class="mb-3">
              <label class="form-label fw-semibold">Upload Bukti Transfer</label>
              <div class="file-upload">
                <label for="buktiFile">
                  <i class="bi bi-cloud-upload fs-3 d-block mb-1 text-muted"></i>
                  <span id="fileName">Upload screenshot bukti transfer</span>
                </label>
                <input type="file" name="bukti" id="buktiFile" accept="image/jpeg,image/png,image/webp" required>
              </div>
              <small class="text-muted">Format JPG, PNG, atau WebP · Maksimal 2MB</small>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn-kirim" id="btnSubmit">
            <i class="bi bi-check-circle-fill me-2"></i>Konfirmasi Pembayaran
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Copy text
    function copyText(text, btn) {
      navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Disalin!';
        btn.style.background = '#d1fae5';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background=''; }, 2000);
      });
    }

    // Show selected file name
    document.getElementById('buktiFile')?.addEventListener('change', function(e) {
      const name = e.target.files[0]?.name || 'Upload screenshot bukti transfer';
      document.getElementById('fileName').textContent = name;
    });

    // Sync bank_asal hidden field
    const bankDisplay = document.getElementById('bank_asal_display');
    const bankHidden = document.getElementById('bank_asal_hidden');
    if (bankDisplay && bankHidden) {
      bankDisplay.addEventListener('input', () => bankHidden.value = bankDisplay.value);
      // Saat form submit
      document.getElementById('formBayar').addEventListener('submit', () => bankHidden.value = bankDisplay.value);
    }
  </script>
</body>
</html>