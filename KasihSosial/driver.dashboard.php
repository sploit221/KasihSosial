<?php

include_once 'koneksi.php';
requireRole('driver');

$driver_id   = (int)$_SESSION['user_id'];
$nama_driver = e($_SESSION['username'] ?? 'Driver');

// Profil driver
$profile = dbQuery("SELECT no_hp, alamat_lengkap FROM users WHERE user_id = ?", 'i', [$driver_id])
             ->get_result()->fetch_assoc();

// Statistik
$stat_selesai = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id=? AND status_pengantaran IN ('Selesai','Tiba di Tujuan')",
    'i', [$driver_id])->get_result()->fetch_assoc()['n'];

$stat_aktif = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id=? AND status_pengantaran NOT IN ('Selesai','Tiba di Tujuan')",
    'i', [$driver_id])->get_result()->fetch_assoc()['n'];

$stat_bulan = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran
     WHERE driver_id=? AND status_pengantaran='Selesai'
       AND MONTH(updated_at)=MONTH(CURDATE()) AND YEAR(updated_at)=YEAR(CURDATE())",
    'i', [$driver_id])->get_result()->fetch_assoc()['n'];

// Data tugas aktif
$stmt_tugas = dbQuery(
    "SELECT tp.tugas_id, tp.status_pengantaran,
            p.jenis_pakaian, p.ukuran,
            p.lokasi_pengambilan AS lokasi_pemberi,
            p.latitude  AS lat_pemberi,
            p.longitude AS lng_pemberi,
            dr.lokasi_terkini AS alamat_penerima,
            dr.latitude  AS lat_penerima,
            dr.longitude AS lng_penerima,
            u_pemberi.username  AS nama_pemberi,
            u_pemberi.no_hp     AS hp_pemberi,
            u_penerima.username AS nama_penerima,
            u_penerima.no_hp    AS hp_penerima
     FROM tugas_pengantaran tp
     JOIN donasi_request dr   ON tp.request_id  = dr.request_id
     JOIN pakaian p           ON dr.pakaian_id  = p.pakaian_id
     JOIN users u_pemberi     ON p.user_id       = u_pemberi.user_id
     JOIN users u_penerima    ON dr.penerima_id  = u_penerima.user_id
     WHERE tp.driver_id = ? AND tp.status_pengantaran NOT IN ('Selesai', 'Tiba di Tujuan')
     ORDER BY tp.tugas_id DESC",
    'i', [$driver_id]
);
$result = $stmt_tugas->get_result();

// ── Tugas yang baru saja "Tiba di Tujuan" (perlu notif WA ke penerima) ───────
// Ditampilkan sebagai reminder agar driver menghubungi penerima
$stmt_tiba = dbQuery(
    "SELECT tp.tugas_id, tp.status_pengantaran,
            dr.request_id,
            p.jenis_pakaian,
            u_penerima.username AS nama_penerima,
            u_penerima.no_hp    AS hp_penerima
     FROM tugas_pengantaran tp
     JOIN donasi_request dr  ON tp.request_id  = dr.request_id
     JOIN pakaian p          ON dr.pakaian_id  = p.pakaian_id
     JOIN users u_penerima   ON dr.penerima_id = u_penerima.user_id
     WHERE tp.driver_id = ? AND dr.status = 'Tiba di Tujuan'
     ORDER BY tp.tugas_id DESC",
    'i', [$driver_id]
);
$result_tiba = $stmt_tiba->get_result();

// Warna badge status
function statusBadge(string $s): string {
    return match($s) {
        'Pending'            => 'secondary',
        'Menuju Penjemputan' => 'warning text-dark',
        'Barang Diambil'     => 'primary',
        'Dalam Perjalanan'   => 'info text-dark',
        'Tiba di Tujuan'     => 'success',
        default              => 'secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Driver — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root { --dark:#0f1923; --coral:#e85d4a; --teal:#0d9488; --cream:#f0f4f8; --sidebar:240px; }
    body { background:var(--cream); font-family:'Plus Jakarta Sans',sans-serif; }
    .sidebar { width:var(--sidebar); position:fixed; top:0; left:0; height:100vh;
               background:var(--dark); display:flex; flex-direction:column;
               z-index:1000; transition:transform .3s; overflow-y:auto; }
    .sb-brand { padding:1.5rem; border-bottom:1px solid rgba(255,255,255,.07); }
    .sb-brand .bn { font-family:'Fraunces',serif; font-size:1.2rem; color:#fff; }
    .sb-brand .bn span { color:var(--coral); }
    .sb-role { font-size:.65rem; font-weight:700; background:var(--teal); color:#fff;
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
    .topbar-title { font-weight:800; font-size:1rem; }
    .clock-chip { background:var(--dark); color:#fff; border-radius:20px;
                  padding:.3rem .9rem; font-size:.78rem; font-family:monospace; letter-spacing:1px; }

    /* Hero bar */
    .hero-bar { background:linear-gradient(135deg,var(--dark),#16213e);
                border-radius:16px; padding:1.25rem 1.75rem; color:#fff;
                display:flex; align-items:center; justify-content:space-between;
                flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; }
    #live-clock { font-family:'Fraunces',serif; font-size:2rem; font-weight:700; line-height:1; }
    #live-date  { font-size:.72rem; opacity:.55; text-transform:uppercase; letter-spacing:2px; }

    /* Stat cards */
    .stat-card { background:#fff; border-radius:14px; padding:1rem 1.25rem;
                 box-shadow:0 2px 10px rgba(0,0,0,.05);
                 display:flex; align-items:center; gap:.85rem; transition:transform .25s; }
    .stat-card:hover { transform:translateY(-4px); }
    .stat-icon { width:42px; height:42px; border-radius:12px;
                 display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
    .stat-val { font-size:1.55rem; font-weight:800; line-height:1; }
    .stat-lbl { font-size:.72rem; color:#6b7280; }

    /* Task card */
    .task-card { background:#fff; border-radius:16px;
                 box-shadow:0 4px 20px rgba(0,0,0,.07); overflow:hidden;
                 margin-bottom:1.25rem; transition:transform .25s; }
    .task-card:hover { transform:translateY(-4px); }
    .task-header { padding:.9rem 1.25rem; border-bottom:1px solid #f3f4f6;
                   display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
    .task-body { padding:1.1rem 1.25rem; }

    /* Loc box */
    .loc-box { background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:.9rem 1rem; }
    .loc-title { font-weight:700; font-size:.72rem; text-transform:uppercase;
                 letter-spacing:.5px; color:#6b7280; margin-bottom:.4rem; }
    .loc-name  { font-weight:700; font-size:.9rem; }
    .loc-addr  { font-size:.78rem; color:#6b7280; margin-bottom:.5rem; }

    /* Update form */
    .update-form { background:#f8fafc; border-radius:12px; padding:1rem; margin-top:1rem; }
    .form-select-custom { border-radius:10px; border-color:#e5e7eb; font-size:.875rem;
                          padding:.55rem .85rem; }
    .form-select-custom:focus { border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,148,136,.1); }
    .btn-upd { background:var(--dark); border:none; color:#fff; border-radius:10px;
               padding:.6rem 1.1rem; font-weight:700; font-size:.875rem; transition:opacity .2s; }
    .btn-upd:hover { opacity:.8; color:#fff; }

    /* Btn copy */
    .btn-copy { background:#f1f5f9; border:none; color:#374151; border-radius:8px;
                padding:.35rem .75rem; font-size:.78rem; font-weight:600; transition:background .2s; }
    .btn-copy:hover { background:#e2e8f0; }

    /* Notifikasi sudah tiba — card hijau mencolok */
    .tiba-card {
        background: linear-gradient(135deg, #065f46, #0d9488);
        border-radius: 16px; color: #fff;
        padding: 1.1rem 1.4rem;
        margin-bottom: 1rem;
        box-shadow: 0 6px 20px rgba(5,150,105,.35);
        animation: pulse-border 2s ease infinite;
    }
    @keyframes pulse-border {
        0%,100% { box-shadow: 0 6px 20px rgba(5,150,105,.35); }
        50%      { box-shadow: 0 6px 30px rgba(5,150,105,.7); }
    }
    .tiba-card .tiba-icon { font-size:2rem; margin-right:.75rem; }
    .btn-wa-tiba {
        background: #25d366; color: #fff; border: none;
        border-radius: 10px; padding: .6rem 1.1rem;
        font-weight: 700; font-size: .875rem;
        display: inline-flex; align-items: center; gap: .4rem;
        text-decoration: none; white-space: nowrap;
        transition: background .2s;
    }
    .btn-wa-tiba:hover { background: #128c7e; color: #fff; }
    .btn-wa-tiba-outline {
        background: rgba(255,255,255,.15); color: #fff;
        border: 1.5px solid rgba(255,255,255,.4);
        border-radius: 10px; padding: .6rem 1.1rem;
        font-weight: 700; font-size: .875rem;
        display: inline-flex; align-items: center; gap: .4rem;
        text-decoration: none; white-space: nowrap;
        transition: background .2s;
    }
    .btn-wa-tiba-outline:hover { background: rgba(255,255,255,.25); color: #fff; }

    /* Mobile */
    @media(max-width:768px) {
      .sidebar { transform:translateX(-100%); } .sidebar.open { transform:translateX(0); }
      .main { margin-left:0; }
      .overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; }
      .overlay.open { display:block; }
      #live-clock { font-size:1.5rem; }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="bn"><span>❤</span> KasihSosial</div>
    <span class="sb-role"><i class="bi bi-truck me-1"></i>Driver</span>
  </div>
  <nav class="sb-nav">
    <a href="driver.dashboard.php" class="nl active"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a>
    <a href="profile.driver.php"   class="nl"><i class="bi bi-person-circle"></i>Profile Saya</a>
    <a href="riwayat.driver.php"   class="nl"><i class="bi bi-clock-history"></i>Riwayat Tugas</a>
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
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-light border d-md-none" id="menuBtn">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="topbar-title">Dashboard Driver</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="clock-chip d-none d-sm-block" id="topClock">00:00:00</span>
      <a href="profile.driver.php" class="text-dark text-decoration-none">
        <div style="width:34px;height:34px;border-radius:50%;background:var(--teal);
                    display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">
          <?= strtoupper(substr($_SESSION['username'],0,1)); ?>
        </div>
      </a>
    </div>
  </div>

  <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1100px;">

    <?= renderFlash(); ?>

    <!-- Hero bar -->
    <div class="hero-bar">
      <div>
        <span id="live-clock">00:00:00</span>
        <span id="live-date" class="d-block mt-1">—</span>
      </div>
      <div class="text-end">
        <div style="font-size:.9rem;opacity:.8;">Halo, <strong><?= $nama_driver; ?></strong> 👋</div>
        <?php if ($profile['no_hp'] ?? ''): ?>
          <div style="font-size:.75rem;opacity:.55;">
            <i class="bi bi-phone me-1"></i><?= e($profile['no_hp']); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <?php
      $sc = [
        ['val'=>$stat_aktif,  'lbl'=>'Tugas Aktif',     'icon'=>'list-task',          'c'=>'#d97706','bg'=>'#fef3c7'],
        ['val'=>$stat_selesai,'lbl'=>'Total Selesai',    'icon'=>'check-circle-fill',   'c'=>'#059669','bg'=>'#d1fae5'],
        ['val'=>$stat_bulan,  'lbl'=>'Selesai Bulan Ini','icon'=>'calendar2-check-fill','c'=>'#0d9488','bg'=>'#ccfbf1'],
      ];
      foreach ($sc as $s): ?>
        <div class="col-4">
          <div class="stat-card">
            <div class="stat-icon" style="background:<?= $s['bg']; ?>;">
              <i class="bi bi-<?= $s['icon']; ?>" style="color:<?= $s['c']; ?>;"></i>
            </div>
            <div>
              <div class="stat-val" style="color:<?= $s['c']; ?>;"><?= $s['val']; ?></div>
              <div class="stat-lbl"><?= $s['lbl']; ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Notifikasi: Menunggu Konfirmasi Penerima ─────────────────────── -->
    <?php if ($result_tiba->num_rows > 0): ?>
    <div class="mb-4">
      <h2 style="font-weight:800;font-size:1.1rem;margin-bottom:.75rem;">
        <i class="bi bi-bell-fill me-2 text-warning"></i>Menunggu Konfirmasi Penerima
        <span class="badge bg-warning text-dark ms-1"><?= $result_tiba->num_rows; ?></span>
      </h2>
      <?php while ($t = $result_tiba->fetch_assoc()):
        // Format no HP untuk WA
        $hp_wa = '';
        if (!empty($t['hp_penerima'])) {
            $hp = preg_replace('/\D/', '', $t['hp_penerima']);
            if (str_starts_with($hp, '0')) $hp = '62' . substr($hp, 1);
            $hp_wa = $hp;
        }
        // Pesan WA otomatis
        $pesan_wa = urlencode(
            "Halo {$t['nama_penerima']}, saya driver KasihSosial. " .
            "Saya sudah tiba di lokasi Anda dengan barang: {$t['jenis_pakaian']}. " .
            "Mohon konfirmasi penerimaan di dashboard KasihSosial Anda. Terima kasih!"
        );
      ?>
      <div class="tiba-card">
        <div class="d-flex align-items-center flex-wrap gap-3">
          <span class="tiba-icon">📍</span>
          <div class="flex-grow-1">
            <div class="fw-bold" style="font-size:.95rem;">
              Anda sudah tiba untuk: <strong><?= e($t['jenis_pakaian']); ?></strong>
            </div>
            <div style="font-size:.82rem; opacity:.85; margin-top:.2rem;">
              Penerima: <strong><?= e($t['nama_penerima']); ?></strong>
              &nbsp;·&nbsp; Tugas #<?= (int)$t['tugas_id']; ?>
              &nbsp;·&nbsp; <i class="bi bi-clock me-1"></i>Menunggu konfirmasi penerima
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <?php if ($hp_wa): ?>
            <a href="https://wa.me/<?= $hp_wa; ?>?text=<?= $pesan_wa; ?>"
               target="_blank" class="btn-wa-tiba">
              <i class="bi bi-whatsapp"></i>Beritahu via WhatsApp
            </a>
            <?php endif; ?>
            <a href="https://wa.me/?text=<?= $pesan_wa; ?>"
               target="_blank" class="btn-wa-tiba-outline">
              <i class="bi bi-share"></i>Bagikan Info
            </a>
          </div>
        </div>
        <div style="margin-top:.75rem; padding:.6rem .9rem;
                    background:rgba(255,255,255,.12); border-radius:8px;
                    font-size:.78rem; opacity:.9;">
          <i class="bi bi-info-circle me-1"></i>
          Setelah penerima mengkonfirmasi di dashboard mereka, status tugas ini akan otomatis selesai.
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <!-- Tugas aktif -->
    <h2 style="font-weight:800;font-size:1.1rem;margin-bottom:1rem;">
      <i class="bi bi-truck me-2 text-primary"></i>Tugas Pengantaran Aktif
      <?php if ($stat_aktif > 0): ?>
        <span class="badge bg-danger ms-1"><?= $stat_aktif; ?></span>
      <?php endif; ?>
    </h2>

    <?php if ($result->num_rows > 0):
      while ($row = $result->fetch_assoc()):
        $badge = statusBadge($row['status_pengantaran']);
    ?>
    <div class="task-card">
      <div class="task-header">
        <div>
          <span class="fw-bold"><?= e($row['jenis_pakaian']); ?></span>
          <span class="badge bg-light text-dark border ms-1" style="font-size:.7rem;"><?= e($row['ukuran']??''); ?></span>
          <small class="text-muted ms-2">Tugas #<?= $row['tugas_id']; ?></small>
        </div>
        <span class="badge bg-<?= $badge; ?> fw-semibold">
          <?= e($row['status_pengantaran']); ?>
        </span>
      </div>

      <div class="task-body">
        <div class="row g-3 mb-1">
          <!-- Penjemputan -->
          <div class="col-md-6">
            <div class="loc-box">
              <div class="loc-title"><i class="bi bi-arrow-up-right-circle me-1 text-warning"></i>Penjemputan</div>
              <div class="loc-name"><?= e($row['nama_pemberi']); ?></div>
              <div class="loc-addr" id="addr-<?= $row['tugas_id']; ?>"><?= e($row['lokasi_pemberi']??'Lokasi belum diisi'); ?></div>
              <div class="d-flex gap-2 flex-wrap">
                <?php if ($row['hp_pemberi']): ?>
                  <a href="https://wa.me/<?= preg_replace('/\D/','',$row['hp_pemberi']); ?>"
                     target="_blank" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-whatsapp me-1"></i>WA
                  </a>
                <?php endif; ?>
                <?php if ($row['lat_pemberi'] && $row['lng_pemberi']): ?>
                  <a href="https://www.google.com/maps?q=<?= (float)$row['lat_pemberi']; ?>,<?= (float)$row['lng_pemberi']; ?>"
                     target="_blank" data-bs-toggle="modal"
                     data-bs-target="#mapModal<?= $row['tugas_id']; ?>"
                     class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-map me-1"></i>Peta
                  </a>
                <?php endif; ?>
                <button class="btn-copy" onclick="copyAddr('addr-<?= $row['tugas_id']; ?>', this)">
                  <i class="bi bi-clipboard me-1"></i>Salin
                </button>
              </div>
            </div>
          </div>

          <!-- Pengantaran -->
          <div class="col-md-6">
            <div class="loc-box">
              <div class="loc-title"><i class="bi bi-arrow-down-left-circle me-1 text-success"></i>Tujuan Pengantaran</div>
              <div class="loc-name"><?= e($row['nama_penerima']); ?></div>
              <div class="loc-addr" id="addr-b-<?= $row['tugas_id']; ?>"><?= e($row['alamat_penerima']??'—'); ?></div>
              <div class="d-flex gap-2 flex-wrap">
                <?php if ($row['hp_penerima']): ?>
                  <a href="https://wa.me/<?= preg_replace('/\D/','',$row['hp_penerima']); ?>"
                     target="_blank" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-whatsapp me-1"></i>WA
                  </a>
                <?php endif; ?>
                <?php if ($row['lat_penerima'] && $row['lng_penerima']): ?>
                  <a href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$row['lat_penerima']; ?>,<?= (float)$row['lng_penerima']; ?>"
                     target="_blank" class="btn btn-sm btn-success">
                    <i class="bi bi-cursor-fill me-1"></i>Navigasi
                  </a>
                <?php endif; ?>
                <button class="btn-copy" onclick="copyAddr('addr-b-<?= $row['tugas_id']; ?>', this)">
                  <i class="bi bi-clipboard me-1"></i>Salin
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Update status form — BUG FIX: tambah CSRF token -->
        <div class="update-form">
          <form action="driver.update.php" method="POST">
            <?= csrfField(); ?>
            <input type="hidden" name="tugas_id" value="<?= (int)$row['tugas_id']; ?>">
            <div class="d-flex gap-2 align-items-end flex-wrap">
              <div class="flex-grow-1">
                <label class="form-label fw-bold mb-1" style="font-size:.8rem;">
                  <i class="bi bi-arrow-repeat me-1"></i>Update Status Perjalanan
                </label>
                <select name="status_baru" class="form-select form-select-custom">
                  <?php
                  $statuses = [
                    'Menuju Penjemputan' => 'Menuju Penjemputan',
                    'Barang Diambil'     => 'Barang Telah Diambil',
                    'Dalam Perjalanan'   => 'Dalam Perjalanan ke Penerima',
                    'Tiba di Tujuan'     => 'Tiba di Tujuan',
                  ];
                  foreach ($statuses as $val => $label): ?>
                    <option value="<?= $val; ?>" <?= $row['status_pengantaran']===$val?'selected':''; ?>>
                      <?= $label; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" name="update_status" class="btn-upd"
                      onclick="return confirm('Update status tugas ini?')">
                <i class="bi bi-arrow-repeat me-1"></i>Update
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Map modal -->
    <?php if ($row['lat_pemberi'] && $row['lng_pemberi']): ?>
    <div class="modal fade" id="mapModal<?= $row['tugas_id']; ?>" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
          <div class="modal-header bg-dark text-white">
            <h6 class="modal-title fw-bold">Lokasi Penjemputan #<?= $row['tugas_id']; ?></h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <iframe width="100%" height="280" frameborder="0" loading="lazy"
              src="https://www.google.com/maps?q=<?= (float)$row['lat_pemberi']; ?>,<?= (float)$row['lng_pemberi']; ?>&hl=id&z=16&output=embed">
            </iframe>
          </div>
          <div class="modal-footer py-2">
            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= (float)$row['lat_pemberi']; ?>,<?= (float)$row['lng_pemberi']; ?>"
               target="_blank" class="btn btn-sm btn-success fw-bold">
              <i class="bi bi-cursor-fill me-1"></i>Navigasi ke Sini
            </a>
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php endwhile; else: ?>
      <div class="text-center py-5 bg-white rounded-4 shadow-sm">
        <i class="bi bi-box-seam fs-1 text-muted d-block mb-3"></i>
        <h5 class="fw-bold text-muted">Belum ada tugas pengantaran untuk Anda</h5>
        <p class="text-muted small">Tugas akan muncul di sini setelah Admin menugaskan Anda.</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Clock
  const days   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  (function tick() {
    const n = new Date();
    const c = document.getElementById('live-clock');
    const d = document.getElementById('live-date');
    const t = document.getElementById('topClock');
    const ts = [n.getHours(), n.getMinutes(), n.getSeconds()].map(v=>String(v).padStart(2,'0')).join(':');
    if(c) c.textContent = ts;
    if(t) t.textContent = ts;
    if(d) d.textContent = days[n.getDay()] + ', ' + n.getDate() + ' ' + months[n.getMonth()] + ' ' + n.getFullYear();
    setTimeout(tick, 1000);
  })();

  // Sidebar mobile
  const sb = document.getElementById('sidebar'), ov = document.getElementById('overlay');
  document.getElementById('menuBtn')?.addEventListener('click', () => {
    sb.classList.toggle('open'); ov.classList.toggle('open');
  });
  function closeSidebar() { sb.classList.remove('open'); ov.classList.remove('open'); }

  // Copy address
  function copyAddr(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.textContent.trim()).then(() => {
      const orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Disalin!';
      btn.style.background = '#d1fae5'; btn.style.color = '#065f46';
      setTimeout(() => { btn.innerHTML = orig; btn.style.background=''; btn.style.color=''; }, 2000);
    });
  }
</script>
</body>
</html>