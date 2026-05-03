<?php

include_once 'koneksi.php';

if (!empty($_SESSION['user_id'])) {
    $dest = match($_SESSION['role'] ?? '') {
        'driver'   => 'driver.dashboard.php',
        'admin'    => 'admin.dashboard.php',
        'penerima' => 'dashboard.penerima.php',
        default    => 'index.php',
    };
    header("Location: $dest"); exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Akun — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root {
      --dark:  #0f1923;
      --coral: #e85d4a;
      --teal:  #0d9488;
      --indigo:#4f46e5;
      --amber: #f59e0b;
      --cream: #f0f4f8;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      min-height: 100vh;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--cream);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.25rem;
      position: relative;
      overflow-x: hidden;
    }

    /* ── Background dekoratif ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 50% at 15% 20%, rgba(79,70,229,.08), transparent),
        radial-gradient(ellipse 50% 60% at 85% 80%, rgba(13,148,136,.08), transparent),
        radial-gradient(ellipse 40% 40% at 50% 50%, rgba(232,93,74,.05), transparent);
      pointer-events: none;
      z-index: 0;
    }

    .wrap { position: relative; z-index: 1; width: 100%; max-width: 860px; }

    /* ── Brand header ── */
    .brand-top {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .65rem;
      margin-bottom: .75rem;
    }
    .brand-icon {
      width: 48px; height: 48px;
      background: linear-gradient(135deg, #e85d4a, #f97316);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.35rem; color: #fff;
      box-shadow: 0 6px 20px rgba(232,93,74,.35);
    }
    .brand-name {
      font-family: 'Fraunces', serif;
      font-size: 1.65rem;
      font-weight: 700;
      color: var(--dark);
      letter-spacing: -.5px;
    }

    /* ── Judul halaman ── */
    .page-title {
      text-align: center;
      margin-bottom: 2.5rem;
    }
    .page-title h1 {
      font-family: 'Fraunces', serif;
      font-size: 1.9rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: .4rem;
    }
    .page-title p {
      color: #6b7280;
      font-size: .92rem;
    }

    /* ── Kartu pilihan ── */
    .role-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    .role-card {
      background: #fff;
      border-radius: 20px;
      padding: 2rem 1.75rem;
      text-decoration: none;
      color: inherit;
      border: 2px solid transparent;
      box-shadow: 0 4px 20px rgba(0,0,0,.06);
      transition: border-color .22s, transform .22s, box-shadow .22s;
      display: flex;
      flex-direction: column;
      gap: 1.1rem;
      position: relative;
      overflow: hidden;
    }
    .role-card::after {
      content: '';
      position: absolute;
      inset: 0;
      opacity: 0;
      transition: opacity .22s;
    }
    .role-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 14px 40px rgba(0,0,0,.12);
    }

    /* User/Donatur card */
    .role-card.card-user { --c: var(--indigo); --cbg: #ede9fe; }
    .role-card.card-user:hover { border-color: var(--indigo); }
    .role-card.card-user::after { background: radial-gradient(circle at 110% 110%, rgba(79,70,229,.07), transparent 60%); }
    .role-card.card-user:hover::after { opacity: 1; }

    /* Driver card */
    .role-card.card-driver { --c: var(--teal); --cbg: #ccfbf1; }
    .role-card.card-driver:hover { border-color: var(--teal); }
    .role-card.card-driver::after { background: radial-gradient(circle at 110% 110%, rgba(13,148,136,.07), transparent 60%); }
    .role-card.card-driver:hover::after { opacity: 1; }

    .role-icon-wrap {
      width: 56px; height: 56px;
      background: var(--cbg);
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem;
      color: var(--c);
      transition: transform .22s;
    }
    .role-card:hover .role-icon-wrap { transform: scale(1.1) rotate(-4deg); }

    .role-tag {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .6px;
      text-transform: uppercase;
      color: var(--c);
      background: var(--cbg);
      border-radius: 20px;
      padding: .2em .75em;
      width: fit-content;
    }

    .role-title {
      font-family: 'Fraunces', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--dark);
      line-height: 1.2;
      margin-bottom: .2rem;
    }
    .role-desc {
      font-size: .84rem;
      color: #6b7280;
      line-height: 1.6;
    }

    .role-features {
      list-style: none;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: .45rem;
      border-top: 1px solid #f3f4f6;
      padding-top: 1rem;
    }
    .role-features li {
      display: flex;
      align-items: center;
      gap: .6rem;
      font-size: .82rem;
      color: #4b5563;
    }
    .role-features li i {
      color: var(--c);
      font-size: .9rem;
      flex-shrink: 0;
    }

    .role-cta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--cbg);
      border-radius: 12px;
      padding: .7rem 1rem;
      font-weight: 700;
      font-size: .88rem;
      color: var(--c);
      margin-top: auto;
      transition: background .2s;
    }
    .role-card:hover .role-cta {
      background: var(--c);
      color: #fff;
    }
    .role-cta i { transition: transform .2s; }
    .role-card:hover .role-cta i { transform: translateX(4px); }

    /* ── Info box admin ── */
    .info-admin {
      background: #fff;
      border: 1.5px solid #e5e7eb;
      border-radius: 14px;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: flex-start;
      gap: .85rem;
      margin-bottom: 2rem;
      font-size: .83rem;
      color: #374151;
    }
    .info-admin .ii {
      width: 36px; height: 36px;
      background: #fef3c7;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    .info-admin strong { display: block; font-size: .85rem; margin-bottom: .15rem; color: var(--dark); }

    /* ── Login link ── */
    .already-login {
      text-align: center;
      font-size: .87rem;
      color: #6b7280;
    }
    .already-login a {
      color: var(--teal);
      font-weight: 700;
      text-decoration: none;
    }
    .already-login a:hover { text-decoration: underline; }

    /* ── Mobile ── */
    @media (max-width: 600px) {
      .role-grid { grid-template-columns: 1fr; gap: 1rem; }
      .page-title h1 { font-size: 1.5rem; }
      .role-card { padding: 1.5rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="wrap">

  <!-- Brand -->
  <div class="brand-top">
    <span class="brand-icon"><i class="bi bi-heart-fill"></i></span>
    <span class="brand-name">KasihSosial</span>
  </div>

  <!-- Judul -->
  <div class="page-title">
    <h1>Pilih Jenis Akun</h1>
    <p>Daftar sesuai peranmu di KasihSosial — gratis dan langsung aktif.</p>
  </div>

  <!-- Info: tidak ada akun admin dari sini -->
  <div class="info-admin">
    <div class="ii"><i class="bi bi-info-circle-fill text-warning"></i></div>
    <div>
      <strong>Perlu diketahui</strong>
      Pendaftaran di sini untuk <b>Donatur/Penerima</b> dan <b>Driver</b>.
      Akun <b>Admin</b> hanya dibuat langsung oleh pengelola sistem.
    </div>
  </div>

  <!-- Kartu pilihan peran -->
  <div class="role-grid">

    <!-- ── Donatur / Penerima ── -->
    <a class="role-card card-user" href="register.user.php">
      <div>
        <span class="role-tag"><i class="bi bi-dot"></i>Umum</span>
      </div>
      <div class="role-icon-wrap">
        <i class="bi bi-people-fill"></i>
      </div>
      <div>
        <div class="role-title">Donatur / Penerima</div>
        <div class="role-desc">
          Donasikan pakaian yang tidak terpakai, atau ajukan permintaan pakaian untuk dirimu dan keluarga.
        </div>
      </div>
      <ul class="role-features">
        <li><i class="bi bi-check-circle-fill"></i>Upload & kelola donasi pakaian</li>
        <li><i class="bi bi-check-circle-fill"></i>Ajukan permintaan barang donasi</li>
        <li><i class="bi bi-check-circle-fill"></i>Chat langsung dengan donatur</li>
        <li><i class="bi bi-check-circle-fill"></i>Lacak status pengiriman</li>
      </ul>
      <div class="role-cta">
        Daftar Sebagai Donatur / Penerima
        <i class="bi bi-arrow-right"></i>
      </div>
    </a>

    <!-- ── Driver ── -->
    <a class="role-card card-driver" href="register.driver.php">
      <div>
        <span class="role-tag"><i class="bi bi-dot"></i>Kurir Sukarela</span>
      </div>
      <div class="role-icon-wrap">
        <i class="bi bi-truck-front-fill"></i>
      </div>
      <div>
        <div class="role-title">Driver Pengantaran</div>
        <div class="role-desc">
          Bantu antarkan donasi pakaian dari donatur ke penerima. Jadilah bagian dari rantai kebaikan.
        </div>
      </div>
      <ul class="role-features">
        <li><i class="bi bi-check-circle-fill"></i>Terima & kelola tugas pengantaran</li>
        <li><i class="bi bi-check-circle-fill"></i>Navigasi langsung via Google Maps</li>
        <li><i class="bi bi-check-circle-fill"></i>Update status perjalanan real-time</li>
        <li><i class="bi bi-check-circle-fill"></i>Riwayat pengantaran tercatat otomatis</li>
      </ul>
      <div class="role-cta">
        Daftar Sebagai Driver
        <i class="bi bi-arrow-right"></i>
      </div>
    </a>

  </div>

  <!-- Sudah punya akun -->
  <div class="already-login">
    Sudah punya akun? <a href="login.php">Masuk di sini</a>
    &nbsp;·&nbsp;
    <a href="index.php" style="color:#9ca3af;">Kembali ke Katalog</a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>