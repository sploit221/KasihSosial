<?php

include_once 'koneksi.php';
requireRole('admin');

// Helper: normalisasi nomor HP ke format WhatsApp (62xxx)
function toWaNumber(string $hp): string {
    $hp = preg_replace('/\D/', '', $hp);
    if (str_starts_with($hp, '0')) $hp = '62' . substr($hp, 1);
    return $hp;
}

// ─── Proses assign ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        header("Location: admin.driver.php"); exit;
    }

    $tugas_id  = validateId($_POST['tugas_id']  ?? 0);
    $driver_id = validateId($_POST['driver_id'] ?? 0);

    if (!$tugas_id || !$driver_id) {
        flash('error', 'Data tidak lengkap.');
        header("Location: admin.driver.php"); exit;
    }

    // Pastikan user yang dipilih benar-benar driver
    $cek_driver = dbQuery("SELECT user_id FROM users WHERE user_id = ? AND role = 'driver'",
                          'i', [$driver_id])->get_result()->num_rows;
    if (!$cek_driver) {
        flash('error', 'User bukan driver.');
        header("Location: admin.driver.php"); exit;
    }

    // Ambil estimasi ongkos dari form (sudah dihitung di halaman)
    $estimasi_ongkos = filter_var($_POST['estimasi_ongkos'] ?? 0, FILTER_VALIDATE_FLOAT);
    if ($estimasi_ongkos < 0) $estimasi_ongkos = 0;

    dbQuery("UPDATE tugas_pengantaran SET driver_id = ?, status_pengantaran = 'Pending', ongkos_kirim = ?
             WHERE tugas_id = ? AND driver_id IS NULL",
            'idi', [$driver_id, $estimasi_ongkos, $tugas_id]);

    flash('success', 'Driver berhasil ditugaskan! Tugas kini muncul di dashboard driver.');
    header("Location: admin.driver.php"); exit;
}

// ─── Proses unassign (batalkan penugasan driver) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        header("Location: admin.driver.php"); exit;
    }
    $tugas_id = validateId($_POST['tugas_id'] ?? 0);
    if ($tugas_id) {
        dbQuery("UPDATE tugas_pengantaran SET driver_id = NULL, status_pengantaran = 'Pending'
                 WHERE tugas_id = ? AND status_pengantaran = 'Pending'",
                'i', [$tugas_id]);
        flash('success', 'Penugasan driver dibatalkan.');
    }
    header("Location: admin.driver.php"); exit;
}

// ─── Data: Tugas BELUM ada driver (driver_id IS NULL) ────────────────────────
$stmt_unassigned = dbQuery(
    "SELECT tp.tugas_id, tp.status_pengantaran, tp.request_id,
            p.jenis_pakaian, p.ukuran, p.foto_pakaian,
            p.lokasi_pengambilan,
            p.latitude AS lat_pemberi, p.longitude AS lng_pemberi,
            dr.latitude AS lat_penerima, dr.longitude AS lng_penerima,
            dr.lokasi_terkini AS alamat_penerima,
            dr.catatan_penerima,
            u_pemberi.username  AS nama_pemberi,
            u_pemberi.no_hp     AS hp_pemberi,
            u_penerima.username AS nama_penerima,
            u_penerima.no_hp    AS hp_penerima
     FROM tugas_pengantaran tp
     JOIN donasi_request dr   ON tp.request_id  = dr.request_id
     JOIN pakaian p           ON dr.pakaian_id  = p.pakaian_id
     JOIN users u_pemberi     ON p.user_id       = u_pemberi.user_id
     JOIN users u_penerima    ON dr.penerima_id  = u_penerima.user_id
     WHERE tp.driver_id IS NULL
     ORDER BY tp.tugas_id DESC"
);
$unassigned = $stmt_unassigned->get_result();

// ─── Data: Tugas SUDAH ada driver (aktif, belum selesai) ── (TAMBAH ongkos_kirim, status_pembayaran, metode_pembayaran)
$stmt_assigned = dbQuery(
    "SELECT tp.tugas_id, tp.status_pengantaran,
            tp.ongkos_kirim, tp.status_pembayaran, tp.metode_pembayaran,
            p.jenis_pakaian, p.ukuran,
            u_pemberi.username  AS nama_pemberi,
            u_penerima.username AS nama_penerima,
            u_driver.username   AS nama_driver,
            u_driver.no_hp      AS hp_driver
     FROM tugas_pengantaran tp
     JOIN donasi_request dr   ON tp.request_id  = dr.request_id
     JOIN pakaian p           ON dr.pakaian_id  = p.pakaian_id
     JOIN users u_pemberi     ON p.user_id       = u_pemberi.user_id
     JOIN users u_penerima    ON dr.penerima_id  = u_penerima.user_id
     JOIN users u_driver      ON tp.driver_id    = u_driver.user_id
     WHERE tp.status_pengantaran != 'Selesai'
     ORDER BY tp.tugas_id DESC"
);
$assigned = $stmt_assigned->get_result();

// ─── Daftar driver aktif untuk dropdown ──────────────────────────────────────
$stmt_drivers = dbQuery(
    "SELECT u.user_id, u.username, u.no_hp,
            COUNT(tp.tugas_id) AS tugas_aktif
     FROM users u
     LEFT JOIN tugas_pengantaran tp
           ON tp.driver_id = u.user_id AND tp.status_pengantaran != 'Selesai'
     WHERE u.role = 'driver' AND (u.is_active IS NULL OR u.is_active = 1)
     GROUP BY u.user_id
     ORDER BY tugas_aktif ASC, u.username ASC"
);
$drivers = $stmt_drivers->get_result()->fetch_all(MYSQLI_ASSOC);

// ─── Pendapatan bulan ini ─────────────────────────────────────────────────────
$bulan_ini = date('Y-m');
$pendapatan_stmt = dbQuery(
    "SELECT SUM(ongkos_kirim) AS total FROM tugas_pengantaran
     WHERE status_pembayaran IN ('cash_lunas','transfer_lunas')
       AND DATE_FORMAT(updated_at, '%Y-%m') = ?",
    's', [$bulan_ini]
);
$pendapatan_bulan = (int)($pendapatan_stmt->get_result()->fetch_assoc()['total'] ?? 0);

// Status badge color
function statusColor(string $s): string {
    return match($s) {
        'Pending'             => 'warning text-dark',
        'Menuju Penjemputan'  => 'info text-dark',
        'Barang Diambil'      => 'primary',
        'Dalam Perjalanan'    => 'info text-dark',
        'Tiba di Tujuan'      => 'success',
        default               => 'secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Penugasan Driver — Admin KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root { --dark:#0f1923; --coral:#e85d4a; --teal:#0d9488; --cream:#f8f9fc; --sidebar:248px; }
    body { background:var(--cream); font-family:'Plus Jakarta Sans',sans-serif; }
    .sidebar { width:var(--sidebar); position:fixed; top:0; left:0; height:100vh;
               background:var(--dark); display:flex; flex-direction:column;
               z-index:1000; transition:transform .3s; overflow-y:auto; }
    .sb-brand { padding:1.5rem; border-bottom:1px solid rgba(255,255,255,.07); }
    .sb-brand .bn { font-family:'Fraunces',serif; font-size:1.25rem; color:#fff; }
    .sb-brand .bn span { color:var(--coral); }
    .rb { font-size:.65rem; font-weight:700; background:var(--coral); color:#fff;
          padding:2px 9px; border-radius:20px; display:inline-block; margin-top:.3rem; }
    .sb-nav { padding:1.25rem 0; flex:1; }
    .nl { display:flex; align-items:center; gap:.75rem; padding:.65rem 1.5rem;
          color:rgba(255,255,255,.5); font-size:.875rem; font-weight:500;
          text-decoration:none; border-left:3px solid transparent; transition:all .2s; }
    .nl:hover, .nl.active { color:#fff; background:rgba(255,255,255,.06); border-left-color:var(--coral); }
    .sb-foot { padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,.06); }
    .main { margin-left:var(--sidebar); min-height:100vh; }
    .topbar { background:#fff; padding:.8rem 1.75rem; border-bottom:1px solid #e9ecef;
              display:flex; align-items:center; justify-content:space-between;
              position:sticky; top:0; z-index:500; }
    .topbar-title { font-weight:800; font-size:1.05rem; }
    .clock-chip { background:var(--dark); color:#fff; border-radius:20px;
                  padding:.3rem .9rem; font-size:.78rem; font-family:monospace; letter-spacing:1px; }

    /* Alert banner */
    .alert-banner { background:linear-gradient(135deg,#dc2626,var(--coral)); color:#fff;
                    border-radius:14px; padding:1rem 1.5rem;
                    display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
    .alert-banner .count { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; line-height:1; }

    /* Task card */
    .task-card { background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,.06);
                 overflow:hidden; margin-bottom:1.25rem; border:2px solid transparent;
                 transition:border-color .2s; }
    .task-card:hover { border-color:#fde68a; }
    .task-card.assigned-card { border-color:#e5e7eb; }
    .task-card .tc-header { padding:.9rem 1.25rem; border-bottom:1px solid #f3f4f6;
                            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
    .task-card .tc-body { padding:1.1rem 1.25rem; }
    .task-thumb { width:48px; height:48px; border-radius:10px;
                  object-fit:cover; border:2px solid #e5e7eb; flex-shrink:0; }
    .loc-box { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;
               padding:.7rem .9rem; font-size:.82rem; }
    .loc-box .loc-title { font-weight:700; font-size:.75rem; text-transform:uppercase;
                          letter-spacing:.5px; color:#6b7280; margin-bottom:.3rem; }
    .driver-select { border-radius:10px; border-color:#e5e7eb; font-size:.875rem;
                     padding:.55rem .85rem; }
    .driver-select:focus { border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,148,136,.1); }
    .btn-assign { background:linear-gradient(135deg,var(--teal),#065f46);
                  border:none; color:#fff; border-radius:10px; padding:.6rem 1.25rem;
                  font-weight:700; font-size:.875rem; transition:opacity .2s; }
    .btn-assign:hover { opacity:.88; color:#fff; }
    .driver-chip { background:#ede9fe; color:#4f46e5; border-radius:20px;
                   padding:.25em .75em; font-size:.75rem; font-weight:700;
                   display:inline-flex; align-items:center; gap:.35rem; }

    /* Section header */
    .sec-head { font-weight:800; font-size:1.05rem; margin-bottom:1rem;
                display:flex; align-items:center; gap:.6rem; }
    .sec-count { background:var(--dark); color:#fff; font-size:.7rem; font-weight:700;
                 padding:.2em .65em; border-radius:20px; }

    /* Table for assigned */
    .table > thead > tr > th { font-size:.72rem; text-transform:uppercase; letter-spacing:.5px;
                               color:#6b7280; font-weight:700; background:#fafafa; border:none; padding:.8rem 1rem; }
    .table > tbody > tr > td { vertical-align:middle; font-size:.85rem; padding:.75rem 1rem; border-color:#f3f4f6; }
    .sbadge { font-size:.7rem; font-weight:700; padding:.25em .65em; border-radius:20px; }

    /* Mobile */
    @media(max-width:768px) {
      .sidebar { transform:translateX(-100%); } .sidebar.open { transform:translateX(0); }
      .main { margin-left:0; }
      .overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; }
      .overlay.open { display:block; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="bn"><span>❤</span> KasihSosial</div>
    <span class="rb"><i class="bi bi-shield-fill me-1"></i>Admin Panel</span>
  </div>
  <nav class="sb-nav">
    <a href="admin.dashboard.php"      class="nl"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a>
    <a href="kelola.users.php"         class="nl"><i class="bi bi-people-fill"></i>Kelola Users</a>
    <a href="admin.driver.php"  class="nl active"><i class="bi bi-truck"></i>Penugasan Driver</a>
    <a href="laporan.rekap.php"        class="nl"><i class="bi bi-file-earmark-bar-graph-fill"></i>Laporan</a>
  </nav>
  <div class="sb-foot">
    <a href="logout.php" class="nl" style="color:#f87171;padding-left:0;"
       onclick="return confirm('Yakin keluar?')">
      <i class="bi bi-box-arrow-right"></i>Logout
    </a>
  </div>
</aside>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="d-flex align-items:center gap-2">
      <button class="btn btn-sm btn-light border d-md-none me-2" id="menuBtn">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="topbar-title">Penugasan Driver</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="clock-chip d-none d-sm-block" id="topClock">00:00:00</span>
    </div>
  </div>

  <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1200px;">

    <?= renderFlash(); ?>

    <!-- Pendapatan Bulan Ini -->
    <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
      <div style="font-size:2rem;"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="fw-bold">Pendapatan Bulan Ini (<?= date('F Y'); ?>)</div>
        <div style="font-size:1.5rem; font-weight:800;">Rp <?= number_format($pendapatan_bulan, 0, ',', '.'); ?></div>
      </div>
    </div>

    <!-- Alert: tugas menunggu driver -->
    <?php if ($unassigned->num_rows > 0): ?>
      <div class="alert-banner">
        <div class="count"><?= $unassigned->num_rows; ?></div>
        <div>
          <div class="fw-bold">Tugas Menunggu Driver!</div>
          <div style="font-size:.82rem;opacity:.85;">
            Tugas di bawah ini sudah disetujui donatur tapi belum ada driver yang ditugaskan.
            Segera assign agar proses pengantaran dapat berjalan.
          </div>
        </div>
        <i class="bi bi-exclamation-triangle-fill ms-auto fs-2 d-none d-md-block"></i>
      </div>
    <?php endif; ?>

    <div class="row g-4">

      <!-- Kolom kiri: Tugas belum ada driver -->
      <div class="col-lg-7">
        <div class="sec-head">
          <i class="bi bi-hourglass-split text-warning"></i>
          Tugas Belum Ada Driver
          <span class="sec-count"><?= $unassigned->num_rows; ?></span>
        </div>

        <?php if ($unassigned->num_rows > 0):
          $unassigned->data_seek(0);
          while ($t = $unassigned->fetch_assoc()):
            // Hitung estimasi ongkos untuk tugas ini
            $estimasi_ongkos = 0;
            if (!empty($t['lat_pemberi']) && !empty($t['lng_pemberi']) && !empty($t['lat_penerima']) && !empty($t['lng_penerima'])) {
                $estimasi_ongkos = hitungOngkos(
                    (float)$t['lat_pemberi'], (float)$t['lng_pemberi'],
                    (float)$t['lat_penerima'], (float)$t['lng_penerima']
                );
            }
        ?>
          <div class="task-card">
            <div class="tc-header">
              <div class="d-flex align-items-center gap-2">
                <img src="uploads/<?= e($t['foto_pakaian']??''); ?>" class="task-thumb"
                     onerror="this.src='https://placehold.co/48?text=?'">
                <div>
                  <div class="fw-bold"><?= e($t['jenis_pakaian']); ?>
                    <span class="badge bg-light text-dark border ms-1" style="font-size:.7rem;"><?= e($t['ukuran']??''); ?></span>
                  </div>
                  <small class="text-muted">Tugas #<?= $t['tugas_id']; ?></small>
                </div>
              </div>
              <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-hourglass-split me-1"></i>Menunggu Driver</span>
            </div>

            <div class="tc-body">
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <div class="loc-box">
                    <div class="loc-title"><i class="bi bi-arrow-up-right-circle me-1"></i>Penjemputan</div>
                    <div class="fw-semibold small"><?= e($t['nama_pemberi']); ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= e($t['lokasi_pengambilan']??'Lokasi tidak diisi'); ?></div>
                    <?php if($t['hp_pemberi']): ?>
                      <a href="https://wa.me/<?= preg_replace('/\D/','',$t['hp_pemberi']); ?>" target="_blank"
                         class="text-success small mt-1 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-whatsapp"></i><?= e($t['hp_pemberi']); ?>
                      </a>
                    <?php endif; ?>
                    <?php if($t['lat_pemberi'] && $t['lng_pemberi']): ?>
                      <a href="https://www.google.com/maps?q=<?= $t['lat_pemberi']; ?>,<?= $t['lng_pemberi']; ?>"
                         target="_blank" class="btn btn-sm btn-outline-primary w-100 mt-2">
                          <i class="bi bi-geo-alt-fill me-1"></i>Lihat peta
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-6">
                  <div class="loc-box">
                    <div class="loc-title"><i class="bi bi-arrow-down-left-circle me-1"></i>Pengantaran</div>
                    <div class="fw-semibold small"><?= e($t['nama_penerima']); ?></div>
                    <div class="text-muted" style="font-size:.75rem;"><?= e($t['alamat_penerima']??'—'); ?></div>
                    <?php if($t['hp_penerima']): ?>
                      <a href="https://wa.me/<?= preg_replace('/\D/','',$t['hp_penerima']); ?>" target="_blank"
                         class="text-success small mt-1 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-whatsapp"></i><?= e($t['hp_penerima']); ?>
                      </a>
                    <?php endif; ?>
                    <?php if(!empty($t['lat_penerima']) && !empty($t['lng_penerima'])):
                        $url_navigasi = "https://www.google.com/maps/dir/?api=1&destination=" . $t['lat_penerima'] . "," . $t['lng_penerima'] . "&travelmode=driving";
                    ?>
                        <a href="<?= $url_navigasi; ?>" target="_blank" class="btn btn-sm btn-primary w-100 mt-2">
                            <i class="bi bi-cursor-fill me-2"></i>Mulai Navigasi (Buka Maps)
                        </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Assign form dengan ongkos -->
              <form method="POST" action="" class="d-flex gap-2 align-items-end flex-wrap">
                <?= csrfField(); ?>
                <input type="hidden" name="tugas_id" value="<?= (int)$t['tugas_id']; ?>">
                <input type="hidden" name="estimasi_ongkos" value="<?= $estimasi_ongkos; ?>">
                <?php if ($estimasi_ongkos > 0): ?>
                <div class="flex-grow-1">
                  <label class="form-label fw-bold mb-1" style="font-size:.8rem;">
                    <i class="bi bi-cash-stack me-1"></i>Estimasi Ongkos (Rp)
                  </label>
                  <div class="fw-bold text-teal fs-5">
                    <?= number_format($estimasi_ongkos, 0, ',', '.'); ?>
                  </div>
                </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                  <label class="form-label fw-bold mb-1" style="font-size:.8rem;">
                    <i class="bi bi-person-badge me-1"></i>Pilih Driver
                  </label>
                  <select name="driver_id" class="form-select driver-select" required>
                    <option value="">— Pilih driver —</option>
                    <?php foreach ($drivers as $d): ?>
                      <option value="<?= $d['user_id']; ?>">
                        <?= e($d['username']); ?>
                        <?= $d['no_hp'] ? ' · '.e($d['no_hp']) : ''; ?>
                        (<?= $d['tugas_aktif']; ?> tugas aktif)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" name="assign" class="btn-assign">
                  <i class="bi bi-send-fill me-1"></i>Tugaskan
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; else: ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-check-circle-fill fs-2 d-block mb-2 text-success"></i>
            <h5 class="fw-bold">Semua tugas sudah memiliki driver.</h5>
            <p>Tidak ada tugas yang menunggu penugasan saat ini.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Kolom kanan: Tugas aktif (sudah ada driver) -->
      <div class="col-lg-5">
        <div class="sec-head">
          <i class="bi bi-truck text-primary"></i>
          Tugas Aktif (Ada Driver)
          <span class="sec-count"><?= $assigned->num_rows; ?></span>
        </div>

        <div class="bg-white rounded-4 shadow-sm overflow-hidden">
          <?php if ($assigned->num_rows > 0):
            while ($a = $assigned->fetch_assoc()): ?>
            <div class="p-3 border-bottom">
              <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <div>
                  <span class="fw-bold"><?= e($a['jenis_pakaian']); ?></span>
                  <small class="text-muted ms-1"><?= e($a['ukuran']??''); ?></small>
                  <div style="font-size:.75rem;color:#6b7280;margin-top:.2rem;">
                    #<?= $a['tugas_id']; ?> &middot;
                    <?= e($a['nama_pemberi']); ?> → <?= e($a['nama_penerima']); ?>
                  </div>
                </div>
                <span class="sbadge bg-<?= statusColor($a['status_pengantaran']); ?>">
                  <?= e($a['status_pengantaran']); ?>
                </span>
              </div>
              <!-- Informasi Ongkos & Pembayaran -->
              <?php if ($a['ongkos_kirim']): ?>
              <div class="d-flex align-items-center gap-2 my-1">
                <span class="badge bg-teal text-white">
                  <i class="bi bi-cash"></i> Rp <?= number_format($a['ongkos_kirim'], 0, ',', '.'); ?>
                </span>
                <span class="badge bg-light text-dark">
                  <?php
                  $status_bayar = $a['status_pembayaran'];
                  $metode = $a['metode_pembayaran'];
                  if ($status_bayar === 'belum_dibayar') echo '⏳ Belum bayar';
                  elseif ($status_bayar === 'cash_lunas') echo '✅ Cash lunas';
                  elseif ($status_bayar === 'transfer_lunas') echo '💳 ' . e($metode) . ' lunas';
                  ?>
                </span>
              </div>
              <?php endif; ?>
              <div class="d-flex align-items-center justify-content-between">
                <span class="driver-chip">
                  <i class="bi bi-person-fill"></i><?= e($a['nama_driver']); ?>
                  <?php if($a['hp_driver']): ?>
                    &middot; <?= e($a['hp_driver']); ?>
                  <?php endif; ?>
                </span>
                <?php if ($a['status_pengantaran'] === 'Pending'): ?>
                  <form method="POST" action="" class="m-0"
                        onsubmit="return confirm('Batalkan penugasan driver ini?')">
                    <?= csrfField(); ?>
                    <input type="hidden" name="tugas_id" value="<?= (int)$a['tugas_id']; ?>">
                    <button type="submit" name="unassign"
                            class="btn btn-sm btn-outline-danger" style="font-size:.72rem;">
                      <i class="bi bi-x-circle me-1"></i>Batalkan
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endwhile; else: ?>
            <div class="p-5 text-center text-muted">
              <i class="bi bi-truck fs-2 d-block mb-2"></i>
              Belum ada tugas aktif dengan driver.
            </div>
          <?php endif; ?>
        </div>

        <!-- Info driver tersedia -->
        <div class="mt-4">
          <div class="sec-head" style="font-size:.9rem;">
            <i class="bi bi-people text-teal"></i>
            Driver Tersedia (<?= count($drivers); ?>)
          </div>
          <div class="bg-white rounded-4 shadow-sm overflow-hidden">
            <?php if (!empty($drivers)): foreach ($drivers as $d): ?>
              <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                  <span class="fw-semibold" style="font-size:.875rem;"><?= e($d['username']); ?></span>
                  <?php if($d['no_hp']): ?>
                    <small class="text-muted ms-2"><?= e($d['no_hp']); ?></small>
                  <?php endif; ?>
                </div>
                <span class="badge <?= $d['tugas_aktif'] > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
                  <?= $d['tugas_aktif']; ?> tugas aktif
                </span>
              </div>
            <?php endforeach; else: ?>
              <div class="p-4 text-center text-muted small">Belum ada driver terdaftar.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function tick() {
    const el = document.getElementById('topClock');
    if (el) {
      const n = new Date();
      el.textContent = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2,'0')).join(':');
    }
    setTimeout(tick, 1000);
  })();
  const sb = document.getElementById('sidebar'), ov = document.getElementById('overlay');
  document.getElementById('menuBtn')?.addEventListener('click', () => {
    sb.classList.toggle('open'); ov.classList.toggle('open');
  });
  function closeSidebar() { sb.classList.remove('open'); ov.classList.remove('open'); }
</script>
</body>
</html>