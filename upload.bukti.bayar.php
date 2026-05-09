<?php
include_once 'koneksi.php';
requireRole('penerima');

$penerima_id = (int)$_SESSION['user_id'];
$tugas_id    = validateId($_GET['tugas_id']   ?? 0);
$request_id  = validateId($_GET['request_id'] ?? 0); // opsional untuk redirect kembali

// Ambil data tugas dan pastikan milik penerima ini
$tugas = dbQuery(
    "SELECT tp.tugas_id, tp.ongkos_kirim
     FROM tugas_pengantaran tp
     JOIN donasi_request dr ON tp.request_id = dr.request_id
     WHERE tp.tugas_id = ? AND dr.penerima_id = ?",
    'ii', [$tugas_id, $penerima_id]
)->get_result()->fetch_assoc();

if (!$tugas) {
    flash('error', 'Tugas tidak ditemukan atau bukan milik Anda.');
    header("Location: dashboard.penerima.php");
    exit;
}

$ongkos = $tugas['ongkos_kirim'];

// Cek apakah sudah pernah upload
$existing = dbQuery(
    "SELECT id, status FROM bukti_pembayaran WHERE tugas_id = ? ORDER BY id DESC LIMIT 1",
    'i', [$tugas_id]
)->get_result()->fetch_assoc();
$sudah_upload = $existing && $existing['status'] !== 'ditolak';

// ── Proses Upload ─────────────────────────────────────────────────────────────
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$sudah_upload) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid.";
    } else {
        // Validasi metode pembayaran
        $metode = $_POST['metode_pembayaran'] ?? '';
        if (!in_array($metode, ['transfer', 'dana'])) {
            $error = "Metode pembayaran tidak valid.";
        }

        if (!$error) {
            // Validasi khusus per metode
            if ($metode === 'transfer') {
                $bank_asal = sanitize($_POST['bank_asal'] ?? '', 50);
                if (empty($bank_asal)) {
                    $error = "Nama bank asal harus diisi.";
                }
            } else {
                // DANA: simpan 'DANA' sebagai bank_asal
                $bank_asal = 'DANA';
            }
        }

        if (!$error) {
            // Validasi file bukti
            if (empty($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
                $error = "File bukti harus diunggah.";
            } else {
                $file          = $_FILES['bukti'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                $finfo         = finfo_open(FILEINFO_MIME_TYPE);
                $mime          = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowed_types)) {
                    $error = "Format gambar tidak valid (gunakan JPG, PNG, WEBP).";
                } elseif ($file['size'] > 2 * 1024 * 1024) {
                    $error = "Ukuran file maksimal 2MB.";
                } else {
                    $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $nama_file  = 'bukti_' . $tugas_id . '_' . time() . '.' . strtolower($ext);
                    $upload_dir = __DIR__ . '/uploads/bukti/';

                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $nama_file)) {
                        // Simpan dengan kolom metode_pembayaran
                        dbQuery(
                            "INSERT INTO bukti_pembayaran
                                (tugas_id, penerima_id, nominal, bank_asal, metode_pembayaran, foto_bukti)
                             VALUES (?, ?, ?, ?, ?, ?)",
                            'iissss',
                            [$tugas_id, $penerima_id, $ongkos, $bank_asal, $metode, $nama_file]
                        );

                        $label = ($metode === 'dana') ? 'DANA' : 'Transfer Bank';
                        flash('success', "Bukti pembayaran via {$label} berhasil diunggah. Menunggu verifikasi driver.");
                        $redirect = $request_id
                            ? "tracking.penerima.php?id=$request_id"
                            : "dashboard.penerima.php";
                        header("Location: $redirect");
                        exit;
                    } else {
                        $error = "Gagal menyimpan file. Coba lagi.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Bukti Pembayaran — KasihSosial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background: #f0f4f8; font-family: 'Segoe UI', sans-serif; min-height: 100vh;
           display: flex; align-items: center; justify-content: center; padding: 1.5rem; }

    .card-wrap { max-width: 480px; width: 100%; background: #fff;
                 border-radius: 22px; box-shadow: 0 12px 45px rgba(0,0,0,.10); overflow: hidden; }

    /* Header */
    .card-header-custom { background: linear-gradient(135deg, #059669, #0d9488);
                          color: #fff; padding: 1.4rem 1.6rem; display: flex;
                          align-items: center; gap: .85rem; }
    .card-header-custom .icon { font-size: 1.9rem; }
    .card-header-custom h5 { margin: 0; font-weight: 700; font-size: 1.05rem; }
    .card-header-custom small { opacity: .82; font-size: .82rem; }

    .card-body-custom { padding: 1.5rem 1.6rem; }

    /* Pilihan Metode */
    .metode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: 1.2rem; }
    .metode-label { border: 2px solid #e2e8f0; border-radius: 14px; padding: .9rem .8rem;
                    cursor: pointer; transition: all .18s; display: flex; flex-direction: column;
                    align-items: center; gap: .4rem; text-align: center; }
    .metode-label:hover { border-color: #059669; background: #f0fdf4; }
    .metode-label.active { border-color: #059669; background: #ecfdf5; }
    .metode-label .logo { font-size: 1.8rem; }
    .metode-label .nama { font-weight: 700; font-size: .88rem; color: #1f2937; }
    .metode-label .sub  { font-size: .74rem; color: #6b7280; }
    input[type="radio"].metode-radio { display: none; }

    /* Info Box */
    .info-box { border-radius: 12px; padding: .9rem 1rem; font-size: .85rem;
                margin-bottom: 1.2rem; display: flex; align-items: flex-start; gap: .6rem; }
    .info-box.bank { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
    .info-box.dana { background: #fdf4ff; border: 1px solid #e9d5ff; color: #6b21a8; }
    .info-box .rekening { font-size: 1.15rem; font-weight: 800; letter-spacing: .04em; margin: .3rem 0 .1rem; }
    .info-box .atas-nama { font-size: .8rem; opacity: .75; }

    /* Form field */
    .form-label { font-weight: 600; font-size: .88rem; color: #374151; }
    .form-control { border-radius: 10px; border-color: #d1d5db; font-size: .9rem; }
    .form-control:focus { border-color: #059669; box-shadow: 0 0 0 .2rem rgba(5,150,105,.15); }

    /* Tombol */
    .btn-kirim { display: block; width: 100%; border: none; border-radius: 14px; padding: .85rem;
                 font-weight: 700; font-size: .95rem; cursor: pointer;
                 background: linear-gradient(135deg, #059669, #0d9488); color: #fff;
                 box-shadow: 0 4px 15px rgba(5,150,105,.35); transition: opacity .2s; }
    .btn-kirim:hover { opacity: .88; }
    .btn-batal { display: block; text-align: center; margin-top: .65rem;
                 font-size: .84rem; color: #9ca3af; text-decoration: none; }
    .btn-batal:hover { color: #374151; }

    /* Nominal badge */
    .nominal-badge { display: inline-block; background: #ecfdf5; border: 1px solid #6ee7b7;
                     border-radius: 10px; padding: .25rem .7rem; font-weight: 800;
                     color: #065f46; font-size: 1.05rem; margin-bottom: .3rem; }
  </style>
</head>
<body>
<div class="card-wrap">

  <div class="card-header-custom">
    <span class="icon">💳</span>
    <div>
      <h5>Upload Bukti Pembayaran</h5>
      <small>Ongkos kirim yang harus dibayar</small>
    </div>
  </div>

  <div class="card-body-custom">

    <!-- Nominal -->
    <div class="text-center mb-3">
      <div class="nominal-badge">Rp <?= number_format($ongkos, 0, ',', '.') ?></div>
    </div>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger border-0 rounded-3 py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($sudah_upload): ?>
      <div class="alert alert-info border-0 rounded-3">
        Anda sudah mengunggah bukti pembayaran (status: <strong><?= e($existing['status']) ?></strong>).
        Silakan tunggu verifikasi dari driver.
      </div>
      <a href="dashboard.penerima.php" class="btn-kirim text-decoration-none text-center">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
      </a>

    <?php else: ?>

    <form method="post" enctype="multipart/form-data" id="form-upload">
      <?= csrfField() ?>

      <!-- ── Pilih Metode ────────────────────────────────────────────────── -->
      <p class="form-label mb-2">Pilih Metode Pembayaran</p>
      <div class="metode-grid">
        <label class="metode-label active" id="lbl-transfer" for="metode_transfer">
          <input type="radio" class="metode-radio" name="metode_pembayaran"
                 id="metode_transfer" value="transfer" checked>
          <span class="logo">🏦</span>
          <span class="nama">Transfer Bank</span>
          <span class="sub">BCA · BNI · BRI · Mandiri</span>
        </label>

        <label class="metode-label" id="lbl-dana" for="metode_dana">
          <input type="radio" class="metode-radio" name="metode_pembayaran"
                 id="metode_dana" value="dana">
          <span class="logo">💜</span>
          <span class="nama">DANA</span>
          <span class="sub">Dompet Digital DANA</span>
        </label>
      </div>

      <!-- ── Info Bank Transfer ─────────────────────────────────────────── -->
      <div class="info-box bank" id="info-transfer">
        <i class="bi bi-bank2 mt-1 flex-shrink-0"></i>
        <div>
          <div><?= ADMIN_BANK_NAME ?></div>
          <div class="rekening"><?= ADMIN_BANK_ACCOUNT ?></div>
          <div class="atas-nama">a.n. <?= ADMIN_BANK_HOLDER ?></div>
        </div>
      </div>

      <!-- ── Info DANA ──────────────────────────────────────────────────── -->
      <div class="info-box dana d-none" id="info-dana">
        <i class="bi bi-wallet2 mt-1 flex-shrink-0"></i>
        <div>
          <div>Nomor DANA</div>
          <div class="rekening"><?= defined('ADMIN_DANA_NUMBER') ? ADMIN_DANA_NUMBER : '0812-XXXX-XXXX' ?></div>
          <div class="atas-nama">a.n. <?= defined('ADMIN_DANA_HOLDER') ? ADMIN_DANA_HOLDER : ADMIN_BANK_HOLDER ?></div>
        </div>
      </div>

      <!-- ── Bank Asal (hanya untuk Transfer) ──────────────────────────── -->
      <div class="mb-3" id="field-bank">
        <label class="form-label" for="bank_asal">Bank / Dompet Asal</label>
        <input type="text" name="bank_asal" id="bank_asal" class="form-control"
               placeholder="Contoh: BCA, BNI, BRI, Mandiri" maxlength="50">
        <small class="text-muted">Nama bank yang Anda gunakan untuk transfer</small>
      </div>

      <!-- ── Upload Foto Bukti ───────────────────────────────────────────── -->
      <div class="mb-3">
        <label class="form-label" for="bukti">Foto Bukti Pembayaran</label>
        <input type="file" name="bukti" id="bukti" class="form-control"
               accept="image/jpeg,image/png,image/webp" required>
        <small class="text-muted">Format JPG, PNG, atau WebP · Maksimal 2 MB</small>
      </div>

      <button type="submit" class="btn-kirim" id="btn-submit">
        <i class="bi bi-check-circle-fill me-2"></i>Kirim Bukti Pembayaran
      </button>
    </form>

    <a href="dashboard.penerima.php" class="btn-batal">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>

    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const radios      = document.querySelectorAll('input[name="metode_pembayaran"]');
  const lblTransfer = document.getElementById('lbl-transfer');
  const lblDana     = document.getElementById('lbl-dana');
  const infoTransfer= document.getElementById('info-transfer');
  const infoDana    = document.getElementById('info-dana');
  const fieldBank   = document.getElementById('field-bank');
  const inputBank   = document.getElementById('bank_asal');

  function updateUI() {
    const val = document.querySelector('input[name="metode_pembayaran"]:checked').value;
    const isTransfer = (val === 'transfer');

    lblTransfer.classList.toggle('active', isTransfer);
    lblDana.classList.toggle('active', !isTransfer);

    infoTransfer.classList.toggle('d-none', !isTransfer);
    infoDana.classList.toggle('d-none', isTransfer);

    // Tampilkan field bank hanya untuk transfer
    fieldBank.classList.toggle('d-none', !isTransfer);

    if (isTransfer) {
      inputBank.setAttribute('required', 'required');
      inputBank.placeholder = 'Contoh: BCA, BNI, BRI, Mandiri';
    } else {
      inputBank.removeAttribute('required');
      inputBank.value = '';
    }
  }

  radios.forEach(r => r.addEventListener('change', updateUI));
  updateUI(); // inisialisasi

  // Prevent double submit
  document.getElementById('form-upload')?.addEventListener('submit', function () {
    const btn = document.getElementById('btn-submit');
    if (btn) {
      btn.style.pointerEvents = 'none';
      btn.style.opacity = '0.7';
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengunggah...';
    }
  });
})();
</script>
</body>
</html>