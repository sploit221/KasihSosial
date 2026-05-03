<?php

include_once 'koneksi.php';
// requireLogin(); <-- DIHAPUS agar bisa diakses publik

// ---- Status login ----
$my_id   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$my_role = $_SESSION['role']      ?? null;
$my_name = $_SESSION['username']  ?? 'Pengunjung';
$logged_in = ($my_id !== null);

// ── Statistik Global (tampil di hero) ────────────────────────────────────────
$stat_pakaian   = (int)dbQuery("SELECT COUNT(*) n FROM pakaian WHERE status_ketersediaan='Tersedia'")->get_result()->fetch_assoc()['n'];
$stat_donasi    = (int)dbQuery("SELECT COUNT(*) n FROM pakaian WHERE status_ketersediaan='Sudah Donasi'")->get_result()->fetch_assoc()['n'];
$stat_penerima  = (int)dbQuery("SELECT COUNT(*) n FROM users WHERE role='penerima'")->get_result()->fetch_assoc()['n'];
$stat_driver    = (int)dbQuery("SELECT COUNT(*) n FROM users WHERE role='driver' AND is_active=1")->get_result()->fetch_assoc()['n'];

// ── Data kontekstual per role ─────────────────────────────────────────────────
$ctx = [];
if ($logged_in) {
    if ($my_role === 'user') {
        $ctx['barang']  = (int)dbQuery("SELECT COUNT(*) n FROM pakaian WHERE user_id=?", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
        $ctx['pending'] = (int)dbQuery("SELECT COUNT(*) n FROM donasi_request dr JOIN pakaian p ON dr.pakaian_id=p.pakaian_id WHERE p.user_id=? AND dr.status='Pending'", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
        $ctx['selesai'] = (int)dbQuery("SELECT COUNT(*) n FROM donasi_request dr JOIN pakaian p ON dr.pakaian_id=p.pakaian_id WHERE p.user_id=? AND dr.status='Disetujui'", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
    } elseif ($my_role === 'penerima') {
        $ctx['request']  = (int)dbQuery("SELECT COUNT(*) n FROM donasi_request WHERE penerima_id=?", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
        $ctx['diterima'] = (int)dbQuery("SELECT COUNT(*) n FROM donasi_request WHERE penerima_id=? AND status='Diterima'", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
        $ctx['tiba']     = (int)dbQuery("SELECT COUNT(*) n FROM donasi_request WHERE penerima_id=? AND status='Tiba di Tujuan'", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
    } elseif ($my_role === 'driver') {
        $ctx['aktif']   = (int)dbQuery("SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id=? AND status_pengantaran NOT IN ('Selesai')", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
        $ctx['selesai'] = (int)dbQuery("SELECT COUNT(*) n FROM tugas_pengantaran WHERE driver_id=? AND status_pengantaran='Selesai'", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
    }
}

// ── Barang terbaru di katalog (3 item) ───────────────────────────────────────
$stmt_katalog = dbQuery(
    "SELECT p.pakaian_id, p.jenis_pakaian, p.ukuran, p.kondisi, p.foto_pakaian,
            u.username AS nama_donatur, p.tanggal_upload
     FROM pakaian p
     JOIN users u ON p.user_id = u.user_id
     WHERE p.status_ketersediaan = 'Tersedia'
     ORDER BY p.tanggal_upload DESC LIMIT 3",
    '', []
);
$katalog_terbaru = $stmt_katalog->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KasihSosial — Berbagi Kebaikan</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    /* ── Tokens ─────────────────────────────────────────── */
    :root {
      --ink:     #1a1208;
      --soil:    #3d2b1f;
      --terra:   #c4622d;
      --sand:    #e8dcc8;
      --cream:   #f5f0e8;
      --sage:    #5a7a5c;
      --warm-w:  #fdfaf4;
      --card-r:  18px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }

    body {
      background: var(--cream);
      font-family: 'DM Sans', sans-serif;
      color: var(--ink);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Topbar ───────────────────────────────────────── */
    .topbar {
      background: var(--ink);
      padding: 1.2rem 2rem; /* Diperbesar dari .7rem */
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 1000;
    }
    .topbar-brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.4rem; /* Diperbesar dari 1.2rem */
      color: var(--sand);
      text-decoration: none; letter-spacing: .5px;
    }
    .topbar-brand span { color: var(--terra); font-style: italic; }
    .topbar-right { display: flex; align-items: center; gap: 1rem; }
    .topbar-role {
      font-family: 'DM Mono', monospace;
      font-size: .65rem; text-transform: uppercase;
      letter-spacing: 2px; color: var(--sand); opacity: .5;
    }
    .topbar-avatar {
      width: 38px; height: 38px; border-radius: 50%; /* Diperbesar sedikit */
      background: var(--terra);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-weight: 700; font-size: 1rem;
      cursor: pointer;
    }
    .topbar-logout {
      color: rgba(255,255,255,.35); font-size: .9rem;
      text-decoration: none; transition: color .2s;
    }
    .topbar-logout:hover { color: #f87171; }
    
    /* Tautan publik */
    .topbar-link {
      color: var(--sand); text-decoration: none;
      font-size: .9rem; font-weight: 500;
      transition: color .2s;
    }
    .topbar-link:hover { color: var(--terra); }

    /* ── Hero ─────────────────────────────────────────── */
    .hero {
      position: relative;
      background: var(--soil);
      overflow: hidden;
      padding: 5rem 0 4rem;
    }
    /* Texture overlay */
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background-image:
        radial-gradient(ellipse 80% 60% at 70% 50%, rgba(196,98,45,.18) 0%, transparent 70%),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Ccircle cx='30' cy='30' r='1' fill='%23ffffff08'/%3E%3C/svg%3E");
      pointer-events: none;
    }
    .hero-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: .7rem; letter-spacing: 3px;
      text-transform: uppercase;
      color: var(--terra); margin-bottom: 1.25rem;
      display: flex; align-items: center; gap: .75rem;
    }
    .hero-eyebrow::before {
      content: ''; width: 32px; height: 1.5px;
      background: var(--terra); display: block;
    }
    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.6rem, 6vw, 4.5rem);
      font-weight: 900; line-height: 1.05;
      color: var(--sand); margin-bottom: 1.5rem;
    }
    .hero-title em {
      font-style: italic;
      color: var(--terra);
    }
    .hero-desc {
      font-size: 1.05rem; line-height: 1.75;
      color: rgba(232,220,200,.7);
      max-width: 480px; margin-bottom: 2.5rem;
      font-weight: 300;
    }
    .hero-cta {
      display: inline-flex; align-items: center; gap: .6rem;
      background: var(--terra); color: #fff;
      padding: .8rem 1.75rem; border-radius: 50px;
      font-weight: 600; font-size: .9rem;
      text-decoration: none; transition: all .25s;
      box-shadow: 0 8px 24px rgba(196,98,45,.4);
    }
    .hero-cta:hover {
      background: #d9732f; color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 12px 32px rgba(196,98,45,.5);
    }
    .hero-cta-ghost {
      display: inline-flex; align-items: center; gap: .6rem;
      border: 1.5px solid rgba(232,220,200,.25);
      color: var(--sand); padding: .8rem 1.75rem;
      border-radius: 50px; font-weight: 500; font-size: .9rem;
      text-decoration: none; transition: all .25s; margin-right: .75rem;
    }
    .hero-cta-ghost:hover {
      border-color: var(--sand); color: var(--sand);
      background: rgba(232,220,200,.08);
    }

    /* Dekoratif kanan */
    .hero-deco {
      position: absolute; right: -60px; top: 50%;
      transform: translateY(-50%);
      width: 420px; height: 420px;
      border-radius: 50%;
      border: 1px solid rgba(196,98,45,.15);
      display: flex; align-items: center; justify-content: center;
    }
    .hero-deco::before {
      content: '';
      position: absolute;
      width: 300px; height: 300px;
      border-radius: 50%;
      border: 1px solid rgba(196,98,45,.1);
    }
    .hero-deco-icon {
      font-size: 6rem; opacity: .12;
    }

    /* ── Greeting card ────────────────────────────────── */
    .greeting-card {
      background: var(--warm-w);
      border-radius: var(--card-r);
      border: 1px solid rgba(61,43,31,.08);
      padding: 1.5rem 2rem;
      display: flex; align-items: center; gap: 1.25rem;
      margin-bottom: 2rem;
      box-shadow: 0 2px 12px rgba(61,43,31,.05);
    }
    .greeting-icon {
      width: 56px; height: 56px; border-radius: 16px;
      background: var(--terra); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; flex-shrink: 0;
    }
    .greeting-role-badge {
      font-family: 'DM Mono', monospace;
      font-size: .65rem; text-transform: uppercase;
      letter-spacing: 2px; padding: .2rem .6rem;
      border-radius: 20px; font-weight: 500;
    }
    .role-user     { background: #ede9fe; color: #5b21b6; }
    .role-penerima { background: #d1fae5; color: #065f46; }
    .role-driver   { background: #fef3c7; color: #92400e; }

    /* ── Quick action cards ───────────────────────────── */
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem; font-weight: 700;
      color: var(--soil); margin-bottom: .4rem;
    }
    .section-sub {
      font-size: .875rem; color: #7c6a5a;
      margin-bottom: 1.75rem;
    }

    .action-card {
      background: var(--warm-w);
      border-radius: var(--card-r);
      border: 1px solid rgba(61,43,31,.07);
      padding: 1.75rem;
      text-decoration: none; color: var(--ink);
      display: block; height: 100%;
      transition: all .28s cubic-bezier(.34,1.56,.64,1);
      position: relative; overflow: hidden;
    }
    .action-card::after {
      content: '';
      position: absolute; bottom: 0; left: 0; right: 0;
      height: 3px;
      background: var(--terra);
      transform: scaleX(0); transform-origin: left;
      transition: transform .3s ease;
    }
    .action-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 40px rgba(61,43,31,.12);
      color: var(--ink);
    }
    .action-card:hover::after { transform: scaleX(1); }

    .action-icon {
      width: 52px; height: 52px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; margin-bottom: 1.1rem;
    }
    .action-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      margin-bottom: .4rem;
    }
    .action-desc { font-size: .82rem; color: #7c6a5a; line-height: 1.6; }
    .action-arrow {
      position: absolute; top: 1.5rem; right: 1.5rem;
      opacity: .2; font-size: 1.1rem;
      transition: all .25s;
    }
    .action-card:hover .action-arrow {
      opacity: .8; transform: translate(3px, -3px);
    }

    /* ── Personal stats ───────────────────────────────── */
    .mini-stat {
      background: var(--warm-w);
      border-radius: 14px;
      border: 1px solid rgba(61,43,31,.07);
      padding: 1.25rem;
      display: flex; align-items: center; gap: .85rem;
      box-shadow: 0 2px 8px rgba(61,43,31,.04);
    }
    .mini-stat-ico {
      width: 42px; height: 42px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; flex-shrink: 0;
    }
    .mini-stat-val {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem; font-weight: 700; line-height: 1;
    }
    .mini-stat-lbl { font-size: .72rem; color: #7c6a5a; margin-top: .15rem; }

    /* ── About / Biografi ─────────────────────────────── */
    .about-section {
      background: var(--soil);
      border-radius: 24px;
      padding: 3.5rem;
      position: relative; overflow: hidden;
      color: var(--sand);
    }
    .about-section::before {
      content: '"';
      position: absolute; top: -20px; left: 30px;
      font-family: 'Playfair Display', serif;
      font-size: 14rem; opacity: .04;
      color: var(--terra); line-height: 1;
      pointer-events: none;
    }
    .about-tag {
      font-family: 'DM Mono', monospace;
      font-size: .65rem; letter-spacing: 3px;
      text-transform: uppercase;
      color: var(--terra); margin-bottom: 1rem;
      display: flex; align-items: center; gap: .6rem;
    }
    .about-tag::before {
      content: ''; width: 24px; height: 1.5px;
      background: var(--terra); display: block;
    }
    .about-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 3.5vw, 2.6rem);
      font-weight: 900; line-height: 1.1;
      margin-bottom: 1.5rem;
      color: var(--sand);
    }
    .about-title em { color: var(--terra); font-style: italic; }
    .about-body {
      font-size: .95rem; line-height: 1.85;
      color: rgba(232,220,200,.72);
      font-weight: 300;
    }
    .about-body strong { color: var(--sand); font-weight: 500; }

    .about-pillar {
      border-top: 1px solid rgba(232,220,200,.1);
      padding-top: 1.25rem; margin-top: 2rem;
    }
    .pillar-item {
      display: flex; gap: 1rem; align-items: flex-start;
      margin-bottom: 1.25rem;
    }
    .pillar-num {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem; font-weight: 700;
      color: var(--terra); opacity: .5;
      line-height: 1; flex-shrink: 0; width: 40px;
    }
    .pillar-text strong {
      display: block; font-size: .85rem;
      color: var(--sand); margin-bottom: .2rem;
    }
    .pillar-text span { font-size: .8rem; color: rgba(232,220,200,.55); }

    /* ── Katalog terbaru ──────────────────────────────── */
    .katalog-card {
      background: var(--warm-w);
      border-radius: var(--card-r);
      border: 1px solid rgba(61,43,31,.07);
      overflow: hidden;
      transition: all .25s;
    }
    .katalog-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(61,43,31,.1);
    }
    .katalog-img {
      width: 100%; height: 180px;
      object-fit: cover;
      background: var(--sand);
    }
    .katalog-body { padding: 1rem 1.1rem; }
    .katalog-jenis {
      font-family: 'Playfair Display', serif;
      font-size: 1rem; font-weight: 700;
    }
    .katalog-meta { font-size: .75rem; color: #7c6a5a; margin-top: .25rem; }
    .katalog-badge {
      display: inline-block; font-size: .65rem;
      background: #fef3c7; color: #92400e;
      padding: .15rem .5rem; border-radius: 6px;
      font-weight: 600; margin-top: .4rem;
    }
    .katalog-btn {
      display: block; text-align: center;
      background: var(--terra); color: #fff;
      padding: .5rem; margin: .75rem 1.1rem 1rem;
      border-radius: 10px; font-size: .8rem;
      font-weight: 600; text-decoration: none;
      transition: background .2s;
    }
    .katalog-btn:hover { background: #d9732f; color: #fff; }

    /* ── How it works ─────────────────────────────────── */
    .how-step {
      display: flex; gap: 1.25rem;
      align-items: flex-start;
      padding: 1.5rem;
      background: var(--warm-w);
      border-radius: 16px;
      border: 1px solid rgba(61,43,31,.07);
      height: 100%;
      transition: box-shadow .25s;
    }
    .how-step:hover { box-shadow: 0 8px 24px rgba(61,43,31,.09); }
    .how-num {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem; font-weight: 900;
      color: var(--terra); opacity: .25;
      line-height: 1; flex-shrink: 0;
    }
    .how-title { font-weight: 700; font-size: .9rem; margin-bottom: .35rem; }
    .how-desc  { font-size: .8rem; color: #7c6a5a; line-height: 1.65; }

    /* ── Footer ───────────────────────────────────────── */
    .footer {
      background: var(--ink); color: rgba(232,220,200,.45);
      padding: 2.5rem 0; margin-top: 5rem;
      text-align: center; font-size: .8rem;
    }
    .footer-brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.5rem; color: var(--sand);
      margin-bottom: .4rem; display: block;
    }
    .footer-brand em { color: var(--terra); font-style: italic; }

    /* ── Counter animation ────────────────────────────── */
    .counter-val { display: inline-block; }

    /* ── Entrance animations ──────────────────────────── */
    .fade-up {
      opacity: 0; transform: translateY(28px);
      animation: fadeUp .6s ease forwards;
    }
    @keyframes fadeUp {
      to { opacity: 1; transform: translateY(0); }
    }
    .delay-1 { animation-delay: .1s; }
    .delay-2 { animation-delay: .2s; }
    .delay-3 { animation-delay: .3s; }
    .delay-4 { animation-delay: .4s; }
    .delay-5 { animation-delay: .5s; }

    @media (max-width: 768px) {
      .hero { padding: 3rem 0 2.5rem; }
      .hero-deco { display: none; }
      .about-section { padding: 2rem 1.5rem; }
      .about-section::before { display: none; }
      .topbar { padding: 1rem 1.2rem; }
    }
  </style>
</head>
<body>

<!-- ── TOPBAR ────────────────────────────────────────────────────────────── -->
<nav class="topbar">
  <a href="beranda.php" class="topbar-brand">Kasih<span>Sosial</span></a>
  <div class="topbar-right">
    <?php if ($logged_in): ?>
      <span class="topbar-role d-none d-sm-block">
        <?= match($my_role) {
          'user'     => 'Donatur',
          'penerima' => 'Penerima',
          'driver'   => 'Driver',
          default    => $my_role,
        }; ?>
      </span>
      <a href="<?= match($my_role) {
        'user'     => 'dashboard.donatur.php',
        'penerima' => 'dashboard.penerima.php',
        'driver'   => 'driver.dashboard.php',
        default    => 'index.php',
      }; ?>" class="topbar-avatar" title="Dashboard saya">
        <?= strtoupper(substr($my_name, 0, 1)); ?>
      </a>
      <a href="logout.php" class="topbar-logout" onclick="return confirm('Yakin keluar?')">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    <?php else: ?>
      <a href="login.php" class="topbar-link">Masuk</a>
      <a href="register.php" class="hero-cta" style="padding:.5rem 1.4rem; font-size:.85rem;">Daftar</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── HERO ──────────────────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-deco"><div class="hero-deco-icon">🤲</div></div>
  <div class="container">
    <div class="row">
      <div class="col-lg-8">
        <div class="hero-eyebrow fade-up">Platform Donasi Pakaian Indonesia</div>
        <h1 class="hero-title fade-up delay-1">
          Berbagi Kebaikan<br>untuk <em>yang Membutuhkan</em>
        </h1>
        <p class="hero-desc fade-up delay-2">
          KasihSosial menghubungkan donatur pakaian layak dengan
          penerima yang membutuhkan, melalui sistem pengantaran
          yang terorganisir dan transparan.
        </p>
        <div class="fade-up delay-3">
          <?php if ($logged_in): ?>
            <?php if ($my_role === 'user'): ?>
            <a href="upload.php" class="hero-cta-ghost">
              <i class="bi bi-plus-circle"></i>Donasikan Pakaian
            </a>
            <?php elseif ($my_role === 'penerima'): ?>
            <a href="dashboard.penerima.php" class="hero-cta-ghost">
              <i class="bi bi-bag-heart"></i>Permintaan Saya
            </a>
            <?php elseif ($my_role === 'driver'): ?>
            <a href="driver.dashboard.php" class="hero-cta-ghost">
              <i class="bi bi-truck"></i>Tugas Saya
            </a>
            <?php endif; ?>
          <?php else: ?>
            <a href="register.php" class="hero-cta-ghost">
              <i class="bi bi-person-plus"></i>Daftar Sekarang
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── MAIN CONTENT ───────────────────────────────────────────────────────── -->
<div class="container py-5">

  <?= renderFlash(); ?>

  <?php if ($logged_in): ?>
  <!-- Greeting + Personal Stats (hanya untuk user yang sudah login) -->
  <div class="greeting-card fade-up">
    <div class="greeting-icon">
      <i class="bi bi-<?= match($my_role) {
        'user'     => 'heart-fill',
        'penerima' => 'person-heart',
        'driver'   => 'truck',
        default    => 'person',
      }; ?>"></i>
    </div>
    <div class="flex-grow-1">
      <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
        <strong style="font-size:1.05rem;">Selamat datang, <?= e($my_name); ?> 👋</strong>
        <span class="greeting-role-badge role-<?= $my_role; ?>">
          <?= match($my_role) {
            'user'     => 'Donatur',
            'penerima' => 'Penerima',
            'driver'   => 'Driver',
            default    => $my_role,
          }; ?>
        </span>
      </div>
      <p class="mb-0" style="font-size:.84rem; color:#7c6a5a;">
        <?= match($my_role) {
          'user'     => 'Terima kasih telah menjadi bagian dari gerakan berbagi kebaikan.',
          'penerima' => 'Kami senang bisa membantu. Temukan pakaian yang Anda butuhkan di katalog.',
          'driver'   => 'Jasamu mengantar kebaikan sangat berarti bagi banyak orang.',
          default    => 'Selamat menggunakan KasihSosial.',
        }; ?>
      </p>
    </div>
    <a href="<?= match($my_role) {
      'user'     => 'dashboard.donatur.php',
      'penerima' => 'dashboard.penerima.php',
      'driver'   => 'driver.dashboard.php',
      default    => 'index.php',
    }; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-none d-md-block">
      Dashboard Saya <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <!-- Personal Stats -->
  <div class="row g-3 mb-5 fade-up delay-1">
    <?php if ($my_role === 'user'): ?>
      <?php foreach ([
        ['val' => $ctx['barang'],  'lbl' => 'Barang Didonasikan', 'ico' => 'box-seam-fill', 'bg' => '#fef3c7', 'c' => '#d97706'],
        ['val' => $ctx['pending'], 'lbl' => 'Permintaan Masuk',   'ico' => 'inbox-fill',     'bg' => '#ede9fe', 'c' => '#7c3aed'],
        ['val' => $ctx['selesai'], 'lbl' => 'Berhasil Disetujui', 'ico' => 'check-circle-fill','bg' => '#d1fae5','c' => '#059669'],
      ] as $s): ?>
      <div class="col-4">
        <div class="mini-stat">
          <div class="mini-stat-ico" style="background:<?= $s['bg']; ?>;">
            <i class="bi bi-<?= $s['ico']; ?>" style="color:<?= $s['c']; ?>;"></i>
          </div>
          <div>
            <div class="mini-stat-val" style="color:<?= $s['c']; ?>;"><?= $s['val']; ?></div>
            <div class="mini-stat-lbl"><?= $s['lbl']; ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    <?php elseif ($my_role === 'penerima'): ?>
      <?php foreach ([
        ['val' => $ctx['request'],  'lbl' => 'Total Permintaan',   'ico' => 'bag-heart-fill',  'bg' => '#fce7f3', 'c' => '#be185d'],
        ['val' => $ctx['diterima'], 'lbl' => 'Sudah Diterima',     'ico' => 'check-circle-fill','bg' => '#d1fae5','c' => '#059669'],
        ['val' => $ctx['tiba'],     'lbl' => 'Menunggu Konfirmasi','ico' => 'geo-alt-fill',     'bg' => '#fef3c7','c' => '#d97706'],
      ] as $s): ?>
      <div class="col-4">
        <div class="mini-stat">
          <div class="mini-stat-ico" style="background:<?= $s['bg']; ?>;">
            <i class="bi bi-<?= $s['ico']; ?>" style="color:<?= $s['c']; ?>;"></i>
          </div>
          <div>
            <div class="mini-stat-val" style="color:<?= $s['c']; ?>;"><?= $s['val']; ?></div>
            <div class="mini-stat-lbl"><?= $s['lbl']; ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    <?php elseif ($my_role === 'driver'): ?>
      <?php foreach ([
        ['val' => $ctx['aktif'],   'lbl' => 'Tugas Aktif',       'ico' => 'truck',             'bg' => '#fef3c7', 'c' => '#d97706'],
        ['val' => $ctx['selesai'], 'lbl' => 'Total Terselesaikan','ico' => 'check-circle-fill', 'bg' => '#d1fae5', 'c' => '#059669'],
        ['val' => $stat_driver,    'lbl' => 'Driver Aktif Sistem','ico' => 'people-fill',       'bg' => '#dbeafe', 'c' => '#1d4ed8'],
      ] as $s): ?>
      <div class="col-4">
        <div class="mini-stat">
          <div class="mini-stat-ico" style="background:<?= $s['bg']; ?>;">
            <i class="bi bi-<?= $s['ico']; ?>" style="color:<?= $s['c']; ?>;"></i>
          </div>
          <div>
            <div class="mini-stat-val" style="color:<?= $s['c']; ?>;"><?= $s['val']; ?></div>
            <div class="mini-stat-lbl"><?= $s['lbl']; ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Quick Actions (kontekstual per role) -->
  <div class="mb-5 fade-up delay-2">
    <p class="section-title">Akses Cepat</p>
    <p class="section-sub">Navigasi ke fitur yang paling sering Anda gunakan</p>
    <div class="row g-3">
    <?php
    $actions = match($my_role) {
      'user' => [
        ['href'=>'upload.php',           'ico'=>'plus-circle-fill', 'bg'=>'#fef3c7','c'=>'#d97706','title'=>'Donasikan Pakaian',   'desc'=>'Upload pakaian layak pakai untuk didonasikan kepada yang membutuhkan.'],
        ['href'=>'dashboard.donatur.php','ico'=>'inbox-fill',       'bg'=>'#ede9fe','c'=>'#7c3aed','title'=>'Permintaan Masuk',    'desc'=>'Lihat dan proses permintaan donasi dari para penerima.'],
        ['href'=>'kelola.donasi.php',    'ico'=>'collection-fill',  'bg'=>'#d1fae5','c'=>'#059669','title'=>'Kelola Donasi Saya',  'desc'=>'Pantau dan kelola semua barang donasi yang sudah Anda upload.'],
        ['href'=>'index.php',            'ico'=>'grid-1x2-fill',    'bg'=>'#dbeafe','c'=>'#1d4ed8','title'=>'Lihat Katalog',       'desc'=>'Jelajahi semua pakaian tersedia yang menunggu penerima.'],
      ],
      'penerima' => [
        ['href'=>'index.php',              'ico'=>'search-heart',      'bg'=>'#fce7f3','c'=>'#be185d','title'=>'Cari Pakaian',          'desc'=>'Temukan pakaian layak yang tersedia di katalog donasi.'],
        ['href'=>'dashboard.penerima.php', 'ico'=>'bag-heart-fill',    'bg'=>'#d1fae5','c'=>'#059669','title'=>'Permintaan Saya',       'desc'=>'Pantau status semua permintaan donasi yang sudah Anda ajukan.'],
        ['href'=>'tracking.penerima.php',  'ico'=>'radar',             'bg'=>'#fef3c7','c'=>'#d97706','title'=>'Lacak Pengiriman',      'desc'=>'Lihat posisi dan status driver yang mengantarkan barang Anda.'],
        ['href'=>'konfirmasi.terima.php',  'ico'=>'check-circle-fill', 'bg'=>'#ede9fe','c'=>'#7c3aed','title'=>'Konfirmasi Penerimaan', 'desc'=>'Konfirmasi bahwa barang donasi sudah Anda terima dengan baik.'],
      ],
      'driver' => [
        ['href'=>'driver.dashboard.php', 'ico'=>'truck',              'bg'=>'#fef3c7','c'=>'#d97706','title'=>'Tugas Aktif',        'desc'=>'Lihat dan kelola tugas pengantaran yang sedang berjalan.'],
        ['href'=>'riwayat.driver.php',   'ico'=>'clock-history',      'bg'=>'#d1fae5','c'=>'#059669','title'=>'Riwayat Tugas',      'desc'=>'Lihat semua tugas yang sudah berhasil Anda selesaikan.'],
        ['href'=>'profile.driver.php',   'ico'=>'person-circle',      'bg'=>'#dbeafe','c'=>'#1d4ed8','title'=>'Profil Saya',        'desc'=>'Perbarui informasi dan nomor telepon untuk dihubungi penerima.'],
        ['href'=>'index.php',            'ico'=>'grid-1x2-fill',      'bg'=>'#ede9fe','c'=>'#7c3aed','title'=>'Lihat Katalog',       'desc'=>'Jelajahi katalog pakaian yang tersedia di platform.'],
      ],
      default => [],
    };
    foreach ($actions as $i => $a): ?>
      <div class="col-sm-6 col-lg-3">
        <a href="<?= $a['href']; ?>" class="action-card fade-up" style="animation-delay:<?= .1*($i+1); ?>s;">
          <div class="action-icon" style="background:<?= $a['bg']; ?>;">
            <i class="bi bi-<?= $a['ico']; ?>" style="color:<?= $a['c']; ?>;"></i>
          </div>
          <div class="action-title"><?= $a['title']; ?></div>
          <div class="action-desc"><?= $a['desc']; ?></div>
          <span class="action-arrow"><i class="bi bi-arrow-up-right"></i></span>
        </a>
      </div>
    <?php endforeach; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- Saat pengguna belum login: tampilkan pesan selamat datang publik -->
  <div class="greeting-card fade-up mb-5">
    <div class="greeting-icon">
      <i class="bi bi-people-fill"></i>
    </div>
    <div class="flex-grow-1">
      <strong style="font-size:1.1rem;">Selamat datang di KasihSosial! 🤝</strong>
      <p class="mb-0 mt-1" style="font-size:.9rem; color:#7c6a5a;">
        Platform donasi pakaian yang transparan dan terorganisir. 
        Bergabunglah sebagai donatur, penerima, atau driver untuk memulai misi kebaikan.
      </p>
    </div>
    <a href="register.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-none d-md-block">
      Daftar Sekarang <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>
  <?php endif; ?>

  <!-- Dua kolom: Biografi + How it works (untuk semua pengunjung) -->
  <div class="row g-4 mb-5">

    <!-- Biografi KasihSosial -->
    <div class="col-lg-7 fade-up delay-2">
      <div class="about-section h-100">
        <div class="about-tag">Tentang Kami</div>
        <h2 class="about-title">
          Mengapa <em>KasihSosial</em><br>Ada?
        </h2>
        <div class="about-body">
          <p class="mb-3">
            <strong>KasihSosial</strong> lahir dari keyakinan sederhana: setiap lembar pakaian
            yang sudah tidak terpakai memiliki potensi untuk mengubah kehidupan seseorang.
            Di satu sisi, banyak pakaian layak yang menumpuk di lemari dan akhirnya terbuang.
            Di sisi lain, masih banyak saudara kita yang membutuhkan.
          </p>
          <p class="mb-3">
            Kami hadir sebagai <strong>jembatan digital</strong> yang menghubungkan
            keduanya — memastikan setiap donasi sampai ke tangan yang tepat, dengan
            sistem pelacakan yang transparan dan proses yang mudah.
          </p>
          <p>
            Bersama donatur yang dermawan, driver yang berdedikasi, dan penerima
            yang membutuhkan, kami percaya bahwa <strong>kebaikan bisa bergerak
            lebih cepat</strong> ketika teknologi dan kepedulian berjalan beriringan.
          </p>
        </div>
        <div class="about-pillar">
          <?php foreach ([
            ['01', 'Transparan',    'Setiap donasi dapat dilacak dari pemberi hingga penerima secara real-time.'],
            ['02', 'Terorganisir',  'Driver terverifikasi memastikan barang sampai dengan aman dan tepat waktu.'],
            ['03', 'Inklusif',      'Siapa pun bisa berpartisipasi — sebagai donatur, penerima, atau driver.'],
          ] as $p): ?>
          <div class="pillar-item">
            <div class="pillar-num"><?= $p[0]; ?></div>
            <div class="pillar-text">
              <strong><?= $p[1]; ?></strong>
              <span><?= $p[2]; ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- How it works -->
    <div class="col-lg-5 fade-up delay-3">
      <p class="section-title">Cara Kerja</p>
      <p class="section-sub">Proses donasi yang mudah dan terpercaya</p>
      <div class="d-flex flex-column gap-3">
        <?php foreach ([
          ['01', 'Donatur Upload',     'bi-cloud-upload',   'Donatur mengunggah foto dan detail pakaian layak yang ingin didonasikan.'],
          ['02', 'Penerima Mengajukan','bi-hand-index-thumb','Penerima yang membutuhkan mengajukan permintaan disertai alasan dan lokasi.'],
          ['03', 'Donatur Menyetujui', 'bi-check2-circle',  'Donatur meninjau profil penerima dan memutuskan untuk menyetujui atau menolak.'],
          ['04', 'Driver Mengantarkan','bi-truck',          'Admin menugaskan driver. Driver menjemput barang dan mengantarkan ke penerima.'],
          ['05', 'Penerima Konfirmasi','bi-heart-fill',     'Setelah barang tiba, penerima mengkonfirmasi penerimaan. Donasi selesai!'],
        ] as $step): ?>
        <div class="how-step">
          <div class="how-num"><?= $step[0]; ?></div>
          <div>
            <div class="how-title">
              <i class="bi <?= $step[2]; ?> me-2" style="color:var(--terra);"></i><?= $step[1]; ?>
            </div>
            <div class="how-desc"><?= $step[3]; ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Katalog Terbaru -->
  <?php if ($logged_in && $katalog_terbaru->num_rows > 0): ?>
  <div class="fade-up delay-4">
    <div class="d-flex align-items-end justify-content-between mb-1">
      <p class="section-title mb-0">Baru Ditambahkan</p>
      <a href="index.php" style="font-size:.82rem;color:var(--terra);text-decoration:none;font-weight:600;">
        Lihat Semua <i class="bi bi-arrow-right"></i>
      </a>
    </div>
    <p class="section-sub">Pakaian terbaru yang menunggu penerima</p>
    <div class="row g-3">
      <?php while ($item = $katalog_terbaru->fetch_assoc()): ?>
      <div class="col-sm-6 col-md-4">
        <div class="katalog-card">
          <img src="uploads/<?= e($item['foto_pakaian'] ?? ''); ?>"
               class="katalog-img"
               onerror="this.style.background='#e8dcc8';this.src=''"
               alt="<?= e($item['jenis_pakaian']); ?>">
          <div class="katalog-body">
            <div class="katalog-jenis"><?= e($item['jenis_pakaian']); ?></div>
            <div class="katalog-meta">
              Ukuran <?= e($item['ukuran'] ?? '—'); ?> &nbsp;·&nbsp;
              oleh <?= e($item['nama_donatur']); ?>
            </div>
            <span class="katalog-badge"><?= e($item['kondisi'] ?? 'Layak Pakai'); ?></span>
          </div>
          <?php if ($logged_in && $my_role === 'penerima'): ?>
          <a href="donasi.request.php?id=<?= (int)$item['pakaian_id']; ?>"
             class="katalog-btn">
            <i class="bi bi-hand-index-thumb me-1"></i>Minta Barang Ini
          </a>
          <?php else: ?>
          <a href="index.php" class="katalog-btn" style="background:#6b7280;">
            <i class="bi bi-eye me-1"></i>Lihat Detail
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ── FOOTER ─────────────────────────────────────────────────────────────── -->
<footer class="footer">
  <span class="footer-brand">Kasih<em>Sosial</em></span>
  <p>Berbagi Kebaikan untuk yang Membutuhkan</p>
  <p style="margin-top:.5rem; font-size:.72rem; opacity:.4;">
    &copy; <?= date('Y'); ?> KasihSosial · Dibuat dengan ❤ untuk Indonesia
  </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Counter animasi angka ────────────────────────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.target) || 0;
  if (target === 0) { el.textContent = '0'; return; }
  const duration = 1800;
  const step     = 16;
  const increment= target / (duration / step);
  let current    = 0;
  const timer = setInterval(() => {
    current += increment;
    if (current >= target) { current = target; clearInterval(timer); }
    el.textContent = Math.floor(current).toLocaleString('id-ID');
  }, step);
}

// Jalankan counter saat elemen masuk viewport
const counters = document.querySelectorAll('.counter-val[data-target]');
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      animateCounter(e.target);
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.5 });
counters.forEach(c => observer.observe(c));
</script>
</body>
</html>