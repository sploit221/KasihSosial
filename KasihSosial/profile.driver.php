<?php

include_once 'koneksi.php';
requireRole('driver');

$driver_id = (int)$_SESSION['user_id'];

// ─── Ambil data profil driver ─────────────────────────────────────────────────
$stmt_profile = dbQuery(
    "SELECT user_id, username, no_hp, alamat_lengkap, created_at
     FROM users WHERE user_id = ?",
    'i', [$driver_id]
);
$profile = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

if (!$profile) {
    flash('error', 'Profile tidak ditemukan.');
    header("Location: driver.dashboard.php"); exit;
}

// ─── Statistik tugas ──────────────────────────────────────────────────────────
$stat_selesai = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id = ? AND status_pengantaran = 'Selesai'",
    'i', [$driver_id]
)->get_result()->fetch_assoc()['n'];

$stat_aktif = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id = ? AND status_pengantaran != 'Selesai'",
    'i', [$driver_id]
)->get_result()->fetch_assoc()['n'];

$stat_total = $stat_selesai + $stat_aktif;

$stat_bulan_ini = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran
     WHERE driver_id = ? AND status_pengantaran = 'Selesai'
       AND MONTH(updated_at) = MONTH(CURDATE())
       AND YEAR(updated_at)  = YEAR(CURDATE())",
    'i', [$driver_id]
)->get_result()->fetch_assoc()['n'];

// ─── Riwayat tugas (selesai) ──────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

$total_hist = (int)dbQuery(
    "SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id = ? AND status_pengantaran = 'Selesai'",
    'i', [$driver_id]
)->get_result()->fetch_assoc()['n'];
$total_pages = max(1, (int)ceil($total_hist / $per_page));

$stmt_hist = dbQuery(
    "SELECT tp.tugas_id, tp.status_pengantaran, tp.updated_at,
            p.jenis_pakaian, p.ukuran,
            u_pemberi.username  AS nama_pemberi,
            u_penerima.username AS nama_penerima,
            dr.lokasi_terkini   AS alamat_tujuan
     FROM tugas_pengantaran tp
     JOIN donasi_request dr   ON tp.request_id   = dr.request_id
     JOIN pakaian p           ON dr.pakaian_id   = p.pakaian_id
     JOIN users u_pemberi     ON p.user_id        = u_pemberi.user_id
     JOIN users u_penerima    ON dr.penerima_id   = u_penerima.user_id
     WHERE tp.driver_id = ? AND tp.status_pengantaran = 'Selesai'
     ORDER BY tp.updated_at DESC
     LIMIT ? OFFSET ?",
    'iii', [$driver_id, $per_page, $offset]
);
$riwayat = $stmt_hist->get_result();

// ─── Proses update profil ─────────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token keamanan tidak valid.";
    } elseif (!rateLimit('profile_update', 5, 60)) {
        $errors[] = "Terlalu banyak percobaan. Tunggu 1 menit.";
    } else {
        $username = sanitize($_POST['username'] ?? '', 50);
        $no_hp    = sanitize($_POST['no_hp']    ?? '', 20);
        $alamat   = sanitize($_POST['alamat']   ?? '', 500);

        // Validasi
        if (mb_strlen($username) < 3)  $errors[] = "Nama minimal 3 karakter.";
        if ($no_hp && !preg_match('/^[\d\s\+\-]{6,20}$/', $no_hp)) $errors[] = "Format nomor HP tidak valid.";

        // Cek username duplikat (kecuali milik diri sendiri)
        if (empty($errors)) {
            $cek = dbQuery(
                "SELECT user_id FROM users WHERE username = ? AND user_id != ?",
                'si', [$username, $driver_id]
            );
            if ($cek->get_result()->num_rows > 0) $errors[] = "Username sudah digunakan.";
        }

        if (empty($errors)) {
            dbQuery(
                "UPDATE users SET username = ?, no_hp = ?, alamat_lengkap = ? WHERE user_id = ?",
                'sssi', [$username, $no_hp, $alamat, $driver_id]
            );
            // Update session username
            $_SESSION['username'] = $username;
            // Refresh profil
            $profile['username']       = $username;
            $profile['no_hp']          = $no_hp;
            $profile['alamat_lengkap'] = $alamat;
            flash('success', 'Profile berhasil diperbarui!');
            header("Location: profile.driver.php"); exit;
        }
    }
}

// ─── Proses ganti password ────────────────────────────────────────────────────
$pw_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $pw_errors[] = "Token keamanan tidak valid.";
    } elseif (!rateLimit('pw_change', 3, 300)) {
        $pw_errors[] = "Terlalu banyak percobaan. Tunggu 5 menit.";
    } else {
        $pw_lama = $_POST['pw_lama']     ?? '';
        $pw_baru = $_POST['pw_baru']     ?? '';
        $pw_ulang= $_POST['pw_ulang']    ?? '';

        // Ambil hash password sekarang
        $row_pw = dbQuery("SELECT password FROM users WHERE user_id = ?", 'i', [$driver_id])
                    ->get_result()->fetch_assoc();

        if (!password_verify($pw_lama, $row_pw['password'])) {
            $pw_errors[] = "Password lama tidak cocok.";
        } elseif (mb_strlen($pw_baru) < 8) {
            $pw_errors[] = "Password baru minimal 8 karakter.";
        } elseif ($pw_baru !== $pw_ulang) {
            $pw_errors[] = "Konfirmasi password tidak cocok.";
        } else {
            $hash = password_hash($pw_baru, PASSWORD_BCRYPT, ['cost' => 12]);
            dbQuery("UPDATE users SET password = ? WHERE user_id = ?", 'si', [$hash, $driver_id]);
            flash('success', 'Password berhasil diubah!');
            header("Location: profile.driver.php"); exit;
        }
    }
}

$initial = strtoupper(substr($profile['username'] ?? 'D', 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil Driver — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    :root {
      --dark:    #0f1923;
      --dark2:   #1a2535;
      --coral:   #e85d4a;
      --teal:    #0d9488;
      --amber:   #f59e0b;
      --cream:   #f0f4f8;
      --sidebar: 240px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { background: var(--cream); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }

    /* ─── SIDEBAR ─────────────────────────────────────────────── */
    .sidebar {
      width: var(--sidebar); position: fixed; top: 0; left: 0;
      height: 100vh; background: var(--dark);
      display: flex; flex-direction: column; z-index: 1000;
      transition: transform .3s; overflow-y: auto;
    }
    .sidebar-brand { padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,.07); }
    .sidebar-brand .brand-name {
      font-family: 'Fraunces', serif; font-size: 1.25rem; color: #fff;
    }
    .sidebar-brand .brand-name span { color: var(--coral); }
    .sidebar-brand .role-tag {
      font-size: .65rem; font-weight: 700;
      background: var(--teal); color: #fff;
      padding: 2px 9px; border-radius: 20px; margin-top: .3rem; display: inline-block;
    }
    .sidebar-nav { padding: 1.25rem 0; flex: 1; }
    .nav-lnk {
      display: flex; align-items: center; gap: .75rem;
      padding: .65rem 1.5rem; color: rgba(255,255,255,.5);
      font-size: .875rem; font-weight: 500; text-decoration: none;
      border-left: 3px solid transparent; transition: all .2s;
    }
    .nav-lnk:hover, .nav-lnk.active {
      color: #fff; background: rgba(255,255,255,.06);
      border-left-color: var(--coral);
    }
    .nav-lnk i { font-size: 1rem; }
    .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,.06); }

    /* ─── MAIN ────────────────────────────────────────────────── */
    .main { margin-left: var(--sidebar); min-height: 100vh; }

    /* Topbar */
    .topbar {
      background: #fff; padding: .8rem 1.75rem;
      border-bottom: 1px solid #e9ecef;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 500;
    }
    .topbar-title { font-weight: 800; font-size: 1rem; }
    .clock-chip {
      background: var(--dark); color: #fff;
      border-radius: 20px; padding: .3rem .9rem;
      font-size: .78rem; font-family: monospace; letter-spacing: 1px;
    }

    /* ─── HERO BANNER ─────────────────────────────────────────── */
    .hero-banner {
      background: linear-gradient(135deg, var(--dark), #16213e 55%, #0a3d62 100%);
      border-radius: 20px; padding: 2rem;
      color: #fff; position: relative; overflow: hidden;
      margin-bottom: 1.75rem;
    }
    .hero-banner::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(circle at 80% 40%, rgba(232,93,74,.2), transparent 50%),
                  radial-gradient(circle at 15% 80%, rgba(13,148,136,.15), transparent 45%);
    }
    .hero-banner .content { position: relative; z-index: 1; }
    .avatar-circle {
      width: 72px; height: 72px; border-radius: 50%;
      background: linear-gradient(135deg, var(--coral), var(--amber));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Fraunces', serif; font-size: 1.6rem;
      font-weight: 700; color: #fff; flex-shrink: 0;
      box-shadow: 0 4px 20px rgba(232,93,74,.4);
    }
    .hero-name { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 700; }
    .hero-sub  { font-size: .8rem; opacity: .65; }
    .join-badge {
      background: rgba(255,255,255,.12);
      backdrop-filter: blur(6px);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 30px; padding: .35rem .9rem;
      font-size: .75rem; display: inline-flex; align-items: center; gap: .4rem;
    }

    /* ─── STAT CARDS ──────────────────────────────────────────── */
    .stat-card {
      background: #fff; border-radius: 14px;
      padding: 1.1rem 1.3rem;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
      display: flex; align-items: center; gap: .9rem;
      transition: transform .25s;
    }
    .stat-card:hover { transform: translateY(-4px); }
    .stat-icon {
      width: 46px; height: 46px; border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem; flex-shrink: 0;
    }
    .stat-val { font-size: 1.65rem; font-weight: 800; line-height: 1; }
    .stat-lbl { font-size: .75rem; color: #6b7280; }

    /* ─── FORM CARD ───────────────────────────────────────────── */
    .form-card {
      background: #fff; border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
      overflow: hidden; margin-bottom: 1.5rem;
    }
    .form-card-header {
      padding: .95rem 1.4rem;
      border-bottom: 1px solid #f3f4f6;
      display: flex; align-items: center; gap: .6rem;
    }
    .form-card-header .hdr-title { font-weight: 800; font-size: .95rem; }
    .form-card-body { padding: 1.4rem; }
    .form-label { font-weight: 700; font-size: .825rem; margin-bottom: .3rem; color: #374151; }
    .form-control, .form-select {
      border-radius: 10px; border-color: #e5e7eb;
      font-size: .875rem; padding: .6rem .9rem;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--teal); box-shadow: 0 0 0 3px rgba(13,148,136,.1);
    }
    .btn-save {
      background: linear-gradient(135deg, var(--teal), #065f46);
      border: none; color: #fff; border-radius: 10px;
      padding: .65rem 1.5rem; font-weight: 700; font-size: .875rem;
      transition: opacity .2s, transform .15s;
    }
    .btn-save:hover { opacity: .88; transform: translateY(-1px); color: #fff; }
    .btn-pw {
      background: var(--dark); border: none; color: #fff; border-radius: 10px;
      padding: .65rem 1.5rem; font-weight: 700; font-size: .875rem;
      transition: opacity .2s;
    }
    .btn-pw:hover { opacity: .8; color: #fff; }

    /* ─── TABLE ───────────────────────────────────────────────── */
    .table-card {
      background: #fff; border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,.06); overflow: hidden;
    }
    .table-card .tbl-header {
      background: var(--dark); color: #fff;
      padding: .95rem 1.4rem;
      display: flex; align-items: center; justify-content: space-between;
    }
    .table > thead > tr > th {
      font-size: .73rem; text-transform: uppercase;
      letter-spacing: .5px; color: #6b7280;
      font-weight: 700; background: #fafafa;
      border: none; padding: .8rem 1rem;
    }
    .table > tbody > tr > td {
      vertical-align: middle; font-size: .85rem;
      padding: .75rem 1rem; border-color: #f3f4f6;
    }
    .table-hover > tbody > tr:hover > td { background: #f8fafc; }
    .pill-done { background: #d1fae5; color: #065f46; font-size: .7rem;
                 font-weight: 700; padding: .3em .75em; border-radius: 20px; }

    /* ─── PASSWORD STRENGTH ───────────────────────────────────── */
    .pw-strength { height: 4px; border-radius: 4px; background: #e5e7eb; margin-top: 6px; }
    .pw-strength-bar { height: 100%; border-radius: 4px; width: 0; transition: width .3s, background .3s; }

    /* ─── MOBILE ──────────────────────────────────────────────── */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main { margin-left: 0; }
      .overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.5); z-index: 999;
      }
      .overlay.open { display: block; }
    }
  </style>
</head>
<body>

<!-- ════════════════════ SIDEBAR ════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name"><span>❤</span> KasihSosial</div>
    <span class="role-tag"><i class="bi bi-truck me-1"></i>Driver</span>
  </div>
  <nav class="sidebar-nav">
    <a href="driver.dashboard.php" class="nav-lnk">
      <i class="bi bi-grid-1x2-fill"></i>Dashboard
    </a>
    <a href="profile.driver.php" class="nav-lnk active">
      <i class="bi bi-person-circle"></i>Profile Saya
    </a>
    <a href="riwayat.driver.php" class="nav-lnk">
      <i class="bi bi-clock-history"></i>Riwayat Tugas
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-lnk" style="color:#f87171;padding-left:0;"
       onclick="return confirm('Yakin keluar?')">
      <i class="bi bi-box-arrow-right"></i>Logout
    </a>
  </div>
</aside>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ════════════════════ MAIN ═══════════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-light border d-md-none" id="menuBtn">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="topbar-title">Profil Driver</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="clock-chip d-none d-sm-block" id="topClock">00:00:00</span>
      <a href="driver_dashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill">
        <i class="bi bi-arrow-left me-1"></i><span class="d-none d-sm-inline">Dashboard</span>
      </a>
    </div>
  </div>

  <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1100px;">

    <?= renderFlash(); ?>

    <!-- ── HERO BANNER ────────────────────────────────────────── -->
    <div class="hero-banner">
      <div class="content d-flex align-items-center gap-3 flex-wrap">
        <div class="avatar-circle"><?= $initial; ?></div>
        <div>
          <div class="hero-name"><?= e($profile['username']); ?></div>
          <div class="hero-sub"><?= e($profile['email'] ?? '—'); ?></div>
          <div class="mt-2">
            <span class="join-badge">
              <i class="bi bi-calendar3"></i>
              Bergabung <?= isset($profile['created_at'])
                ? date('d M Y', strtotime($profile['created_at']))
                : '—'; ?>
            </span>
          </div>
        </div>
        <div class="ms-auto text-end d-none d-md-block">
          <div style="font-size:.75rem;opacity:.55;">Total Pengantaran</div>
          <div style="font-size:2.5rem;font-family:'Fraunces',serif;font-weight:700;line-height:1;">
            <?= $stat_selesai; ?>
          </div>
          <div style="font-size:.72rem;opacity:.5;text-transform:uppercase;letter-spacing:1px;">Selesai</div>
        </div>
      </div>
    </div>

    <!-- ── STAT CARDS ─────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <?php
      $sc = [
        ['val' => $stat_total,    'lbl' => 'Total Tugas',      'icon' => 'list-task',           'c' => '#4f46e5', 'bg' => '#ede9fe'],
        ['val' => $stat_aktif,    'lbl' => 'Tugas Aktif',      'icon' => 'arrow-repeat',         'c' => '#d97706', 'bg' => '#fef3c7'],
        ['val' => $stat_selesai,  'lbl' => 'Berhasil Antar',   'icon' => 'check-circle-fill',    'c' => '#059669', 'bg' => '#d1fae5'],
        ['val' => $stat_bulan_ini,'lbl' => 'Bulan Ini',        'icon' => 'calendar2-check-fill', 'c' => '#0d9488', 'bg' => '#ccfbf1'],
      ];
      foreach ($sc as $s): ?>
        <div class="col-6 col-md-3">
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

    <div class="row g-4">
      <!-- Kolom Kiri: Edit profil & ganti password -->
      <div class="col-lg-5">

        <!-- Edit Profil -->
        <div class="form-card">
          <div class="form-card-header">
            <i class="bi bi-person-fill" style="color:var(--teal);font-size:1.1rem;"></i>
            <span class="hdr-title">Edit Informasi Profile</span>
          </div>
          <div class="form-card-body">
            <?php if (!empty($errors)): ?>
              <div class="alert alert-danger rounded-3 border-0 small mb-3">
                <?php foreach ($errors as $e_msg): ?>
                  <div><i class="bi bi-exclamation-circle me-1"></i><?= e($e_msg); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="POST" novalidate>
              <?= csrfField(); ?>

              <div class="mb-3">
                <label class="form-label">Nama / Username</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                  <input type="text" name="username" class="form-control"
                         value="<?= e($profile['username']); ?>"
                         maxlength="50" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Email</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                  <input type="email" class="form-control"
                         value="<?= e($profile['email'] ?? ''); ?>"
                         disabled title="Email tidak dapat diubah di sini">
                </div>
                <div class="form-text">Hubungi admin untuk mengubah email.</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Nomor HP / WhatsApp</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-phone text-muted"></i></span>
                  <input type="tel" name="no_hp" class="form-control"
                         placeholder="Contoh: 08123456789"
                         value="<?= e($profile['no_hp'] ?? ''); ?>"
                         maxlength="20">
                </div>
                <div class="form-text">Digunakan donatur & penerima untuk menghubungi Anda via WhatsApp.</div>
              </div>

              <div class="mb-4">
                <label class="form-label">Alamat Lengkap</label>
                <textarea name="alamat" class="form-control" rows="3"
                          placeholder="Masukkan alamat tempat tinggal Anda…"
                          maxlength="500"><?= e($profile['alamat_lengkap'] ?? ''); ?></textarea>
              </div>

              <button type="submit" name="update_profil" class="btn-save w-100">
                <i class="bi bi-save-fill me-1"></i>Simpan Perubahan
              </button>
            </form>
          </div>
        </div>

        <!-- Ganti Password -->
        <div class="form-card">
          <div class="form-card-header">
            <i class="bi bi-shield-lock-fill" style="color:var(--coral);font-size:1.1rem;"></i>
            <span class="hdr-title">Ganti Password</span>
          </div>
          <div class="form-card-body">
            <?php if (!empty($pw_errors)): ?>
              <div class="alert alert-danger rounded-3 border-0 small mb-3">
                <?php foreach ($pw_errors as $pwe): ?>
                  <div><i class="bi bi-exclamation-circle me-1"></i><?= e($pwe); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="POST" novalidate>
              <?= csrfField(); ?>

              <div class="mb-3">
                <label class="form-label">Password Lama</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                  <input type="password" name="pw_lama" class="form-control"
                         placeholder="••••••••" required autocomplete="current-password">
                  <button type="button" class="btn btn-outline-secondary toggle-pw" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                  <input type="password" name="pw_baru" id="pwBaru" class="form-control"
                         placeholder="Min. 8 karakter" minlength="8" required
                         autocomplete="new-password" oninput="checkStrength(this.value)">
                  <button type="button" class="btn btn-outline-secondary toggle-pw" tabindex="-1">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
                <div class="pw-strength mt-1"><div class="pw-strength-bar" id="pwBar"></div></div>
                <div id="pwMsg" style="font-size:.72rem;color:#9ca3af;margin-top:3px;"></div>
              </div>

              <div class="mb-4">
                <label class="form-label">Konfirmasi Password Baru</label>
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-key-fill text-muted"></i></span>
                  <input type="password" name="pw_ulang" id="pwUlang" class="form-control"
                         placeholder="Ulangi password baru" required autocomplete="new-password"
                         oninput="checkMatch()">
                </div>
                <div id="matchMsg" style="font-size:.72rem;margin-top:3px;"></div>
              </div>

              <button type="submit" name="ganti_password" class="btn-pw w-100">
                <i class="bi bi-shield-check me-1"></i>Perbarui Password
              </button>
            </form>
          </div>
        </div>

      </div><!-- /kolom kiri -->

      <!-- Kolom Kanan: Riwayat tugas -->
      <div class="col-lg-7">
        <div class="table-card">
          <div class="tbl-header">
            <div>
              <span class="fw-bold"><i class="bi bi-clock-history me-2"></i>Riwayat Pengantaran Selesai</span>
            </div>
            <span class="badge bg-secondary"><?= $total_hist; ?> tugas</span>
          </div>

          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>#</th><th>Barang</th>
                  <th>Pemberi → Penerima</th>
                  <th>Tujuan</th><th>Selesai</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($riwayat->num_rows > 0):
                $no = $offset + 1;
                while ($r = $riwayat->fetch_assoc()): ?>
                <tr>
                  <td class="text-muted" style="font-size:.75rem;"><?= $no++; ?></td>
                  <td>
                    <span class="fw-semibold"><?= e($r['jenis_pakaian']); ?></span>
                    <small class="d-block text-muted"><?= e($r['ukuran'] ?? ''); ?></small>
                  </td>
                  <td>
                    <div style="font-size:.8rem;">
                      <i class="bi bi-arrow-up-right-circle text-muted me-1"></i><?= e($r['nama_pemberi']); ?>
                    </div>
                    <div style="font-size:.8rem;">
                      <i class="bi bi-arrow-down-left-circle text-muted me-1"></i><?= e($r['nama_penerima']); ?>
                    </div>
                  </td>
                  <td style="font-size:.78rem;max-width:150px;">
                    <span class="text-truncate d-block" style="max-width:140px;"
                          title="<?= e($r['alamat_tujuan']); ?>">
                      <?= e($r['alamat_tujuan'] ?? '—'); ?>
                    </span>
                  </td>
                  <td>
                    <span class="pill-done"><i class="bi bi-check me-1"></i>Selesai</span>
                    <?php if ($r['updated_at']): ?>
                      <small class="d-block text-muted mt-1" style="font-size:.7rem;">
                        <?= date('d M Y', strtotime($r['updated_at'])); ?>
                      </small>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; else: ?>
                <tr>
                  <td colspan="5" class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Belum ada riwayat pengantaran selesai.
                  </td>
                </tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top"
                 style="font-size:.78rem;">
              <span class="text-muted">
                <?= $offset + 1; ?>–<?= min($offset + $per_page, $total_hist); ?> dari <?= $total_hist; ?>
              </span>
              <nav>
                <ul class="pagination pagination-sm mb-0">
                  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?= $p; ?>"><?= $p; ?></a>
                    </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            </div>
          <?php endif; ?>

        </div><!-- /table-card -->
      </div><!-- /kolom kanan -->
    </div><!-- /row -->

  </div><!-- /container -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ── Clock ────────────────────────────────────────────────────
  (function tick() {
    const el = document.getElementById('topClock');
    if (el) {
      const n = new Date();
      el.textContent = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2,'0')).join(':');
    }
    setTimeout(tick, 1000);
  })();

  // ── Sidebar mobile ───────────────────────────────────────────
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  document.getElementById('menuBtn')?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }

  // ── Toggle show/hide password ────────────────────────────────
  document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = btn.previousElementSibling;
      const isText = inp.type === 'text';
      inp.type = isText ? 'password' : 'text';
      btn.querySelector('i').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
  });

  // ── Password strength meter ──────────────────────────────────
  function checkStrength(val) {
    const bar = document.getElementById('pwBar');
    const msg = document.getElementById('pwMsg');
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
      { w: '0%',   bg: '#e5e7eb', txt: '' },
      { w: '25%',  bg: '#ef4444', txt: 'Lemah' },
      { w: '50%',  bg: '#f59e0b', txt: 'Sedang' },
      { w: '75%',  bg: '#3b82f6', txt: 'Kuat' },
      { w: '100%', bg: '#10b981', txt: 'Sangat Kuat' },
    ];
    const lv = levels[Math.min(score, 4)];
    bar.style.width  = val.length ? lv.w  : '0%';
    bar.style.background = lv.bg;
    msg.textContent  = val.length ? lv.txt : '';
    msg.style.color  = lv.bg;
  }

  // ── Konfirmasi password match ────────────────────────────────
  function checkMatch() {
    const baru  = document.getElementById('pwBaru').value;
    const ulang = document.getElementById('pwUlang').value;
    const el    = document.getElementById('matchMsg');
    if (!ulang) { el.textContent = ''; return; }
    if (baru === ulang) {
      el.textContent = '✓ Password cocok';
      el.style.color = '#10b981';
    } else {
      el.textContent = '✗ Tidak cocok';
      el.style.color = '#ef4444';
    }
  }
</script>
</body>
</html>