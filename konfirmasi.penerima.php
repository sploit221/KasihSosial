<?php

include_once 'koneksi.php';
requireLogin();

$my_id      = (int)$_SESSION['user_id'];
$request_id = validateId($_GET['request_id'] ?? 0);
$status     = validateEnum($_GET['status'] ?? '', ['Setuju', 'Ditolak', 'Disetujui']);
$aksi = ($status === 'Disetujui') ? 'Setuju' : $status;

if (!$request_id || !$status) {
    flash('error', 'Parameter tidak valid.');
    header("Location: dashboard.donatur.php"); exit;
}

$stmt = dbQuery(
    "SELECT dr.request_id, dr.status, dr.pakaian_id, dr.penerima_id,
            dr.catatan_penerima, dr.lokasi_terkini,
            p.jenis_pakaian, p.ukuran, p.foto_pakaian, p.user_id AS pemilik_id,
            u_penerima.username AS nama_penerima
     FROM donasi_request dr
     JOIN pakaian p         ON dr.pakaian_id  = p.pakaian_id
     JOIN users   u_penerima ON dr.penerima_id = u_penerima.user_id
     WHERE dr.request_id = ? AND p.user_id = ?",
    'si', [$request_id, $my_id]
);
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    flash('error', "Permintaan tidak ditemukan atau Anda tidak berhak mengaksesnya.");
    header("Location: dashboard.donatur.php"); exit;
}

if ($req['status'] !== 'Pending') {
    flash('error', "Permintaan sudah diproses (status: '{$req['status']}').");
    header("Location: dashboard.donatur.php"); exit;
}

// ── Proses POST ───────────────────────────────────────────────────────────────
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman.';
    } else {
        $status_final = validateEnum($_POST['status'] ?? '', ['Disetujui', 'Ditolak']);
        if (!$status_final) {
            $error = "Status tidak valid.";
        } else {
            $conn->begin_transaction();
            try {
                dbQuery(
                    "UPDATE donasi_request SET status = ? WHERE request_id = ? AND status = 'Pending'",
                    'si', [$status_final, $request_id]
                );

                if ($status_final === 'Disetujui') {
                    // Cegah duplikat tugas
                    $cek = dbQuery(
                        "SELECT tugas_id FROM tugas_pengantaran WHERE request_id = ?",
                        'i', [$request_id]
                    )->get_result()->num_rows;

                    if ($cek === 0) {
                        dbQuery(
                            "INSERT INTO tugas_pengantaran (request_id, driver_id, status_pengantaran, status_pembayaran)
                             VALUES (?, NULL, 'Pending', 'belum dibayar')",
                            'i', [$request_id]
                        );
                    }

                    // BUG 3 FIX: nilai enum DB adalah 'Sudah Donasi' bukan 'Sudah Didonasikan'
                    dbQuery(
                        "UPDATE pakaian SET status_ketersediaan = 'Sudah Donasi' WHERE pakaian_id = ?",
                        'i', [$req['pakaian_id']]
                    );

                    flash('success', 'Permintaan disetujui! Admin akan menugaskan driver.');
                } else {
                    flash('success', 'Permintaan ditolak. Barang tetap tersedia di katalog.');
                }

                $conn->commit();
                header("Location: dashboard.donatur.php"); exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Konfirmasi error: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

// Warna header berdasarkan aksi
$isSetuju  = ($aksi === 'Setuju');
$hdrGrad   = $isSetuju
    ? 'linear-gradient(135deg,#059669,#0d9488)'
    : 'linear-gradient(135deg,#dc2626,#e85d4a)';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isSetuju ? 'Setujui' : 'Tolak'; ?> Permintaan — KasihSosial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background:#f0f4f8; font-family:'Segoe UI',sans-serif; min-height:100vh;
           display:flex; align-items:center; justify-content:center; padding:1.5rem; }
    .wrap { max-width:460px; width:100%; background:#fff; border-radius:22px;
            box-shadow:0 12px 45px rgba(0,0,0,.1); overflow:hidden; }

    /* BUG 4 FIX: class .hdr tidak punya modifier .ok/.ng → pakai inline style */
    .hdr { color:#fff; padding:1.5rem; display:flex; align-items:center; gap:1rem; }
    .hdr-icon { font-size:2.5rem; animation:bounce .8s ease infinite alternate; }
    @keyframes bounce { from{transform:translateY(0)} to{transform:translateY(-6px)} }

    .body-wrap { padding:1.5rem; }
    .item-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px;
                padding:.9rem; display:flex; gap:.85rem; align-items:center; margin-bottom:1rem; }
    .item-box img { width:64px; height:64px; border-radius:12px; object-fit:cover; flex-shrink:0; }
    .info-row { display:flex; gap:.5rem; font-size:.84rem; margin-bottom:.4rem; }
    .info-row .lb { font-weight:700; min-width:90px; flex-shrink:0; color:#374151; }

    /* BUG 5 FIX: class .warn tidak ada → pakai .warn-box dengan modifier */
    .warn-box { border-radius:12px; padding:.9rem 1rem; font-size:.84rem;
                display:flex; gap:.6rem; align-items:flex-start; margin:1rem 0 1.5rem; }
    .warn-box.ok { background:#ecfdf5; border:1px solid #6ee7b7; color:#065f46; }
    .warn-box.ng { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }

    /* BUG 6 FIX: class .btn-ok dan .btn-ng tidak ada di CSS */
    .btn-ok { display:block; width:100%; border:none; border-radius:14px; padding:.85rem;
              font-weight:700; font-size:.95rem; cursor:pointer;
              background:linear-gradient(135deg,#059669,#0d9488); color:#fff;
              box-shadow:0 4px 15px rgba(5,150,105,.35); transition:opacity .2s; }
    .btn-ok:hover { opacity:.88; }
    .btn-ng { display:block; width:100%; border:none; border-radius:14px; padding:.85rem;
              font-weight:700; font-size:.95rem; cursor:pointer;
              background:#fee2e2; color:#991b1b; transition:opacity .2s; }
    .btn-ng:hover { opacity:.85; }
    .btn-batal { display:block; text-align:center; margin-top:.75rem;
                 font-size:.84rem; color:#9ca3af; text-decoration:none; }
    .btn-batal:hover { color:#374151; }
  </style>
</head>
<body>
<div class="wrap">

  <!-- BUG 4 FIX: pakai inline style bukan class modifier yang tidak ada -->
  <div class="hdr" style="background:<?= $hdrGrad; ?>">
    <span style="font-size:2rem;"><?= $isSetuju ? '✅' : '❌'; ?></span>
    <div>
      <h5 class="fw-bold mb-0">
        <?= $isSetuju ? 'Setujui Permintaan' : 'Tolak Permintaan'; ?>
      </h5>
      <small style="opacity:.8;">Periksa detail sebelum mengkonfirmasi</small>
    </div>
  </div>

  <div class="body-wrap">

    <?php if ($error): ?>
      <div class="alert alert-danger border-0 rounded-3 py-2 small mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e($error); ?>
      </div>
    <?php endif; ?>

    <div class="item-box">
      <img src="uploads/<?= e($req['foto_pakaian'] ?? ''); ?>"
           onerror="this.src='https://placehold.co/64x64?text=?'" alt="">
      <div>
        <div class="fw-bold">
          <?= e($req['jenis_pakaian']); ?> &mdash; <?= e($req['ukuran'] ?? '-'); ?>
        </div>
        <small class="text-muted">
          Diminta oleh: <strong><?= e($req['nama_penerima']); ?></strong>
        </small>
      </div>
    </div>

    <div class="info-row">
      <span class="lb"><i class="bi bi-chat-quote me-1"></i>Catatan:</span>
      <span><?= e($req['catatan_penerima'] ?? '—'); ?></span>
    </div>
    <div class="info-row">
      <span class="lb"><i class="bi bi-geo-alt me-1"></i>Lokasi:</span>
      <span><?= e($req['lokasi_terkini'] ?? '—'); ?></span>
    </div>

    <!-- BUG 5 FIX: pakai .warn-box.ok / .warn-box.ng -->
    <?php if ($isSetuju): ?>
      <div class="warn-box ok">
        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
        <div>Barang akan ditandai <strong>Sudah Donasi</strong> dan tugas
        pengantaran otomatis dibuat untuk di-assign ke driver oleh Admin.</div>
      </div>
    <?php else: ?>
      <div class="warn-box ng">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>Permintaan akan <strong>ditolak</strong>. Barang tetap tersedia di katalog.</div>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="form-konfirmasi">
      <?= csrfField(); ?>
      <input type="hidden" name="status"      value="<?= $isSetuju ? 'Disetujui' : 'Ditolak'; ?>">
      <input type="hidden" name="konfirmasi"  value="1">

      <?php if ($isSetuju): ?>
        <button type="submit" id="btn-submit" class="btn-ok">
          <i class="bi bi-check-circle-fill me-2"></i>Ya, Setujui Permintaan Ini
        </button>
      <?php else: ?>
        <button type="submit" id="btn-submit" class="btn-ng">
          <i class="bi bi-x-circle-fill me-2"></i>Ya, Tolak Permintaan Ini
        </button>
      <?php endif; ?>
    </form>

    <!-- BUG 8 FIX: nama file tracking salah (titik → underscore) -->
    <a href="dashboard.donatur.php" class="btn-batal">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

document.getElementById('form-konfirmasi').addEventListener('submit', function () {
  const btn = document.getElementById('btn-submit');
  if (btn) {
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.7';
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
  }
});
</script>
</body>
</html>