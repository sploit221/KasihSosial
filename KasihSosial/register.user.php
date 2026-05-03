<?php

include_once 'koneksi.php';

// ── Redirect jika sudah login ─────────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    $dest = match($_SESSION['role'] ?? '') {
        'driver'   => 'driver.dashboard.php',
        'admin'    => 'admin.dashboard.php',
        'penerima' => 'dashboard.penerima.php',
        default    => 'index.php',
    };
    header("Location: $dest"); exit;
}

// ── Escape helper (jaga-jaga jika belum ada di koneksi.php) ───────
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// ── Whitelist role yang boleh didaftarkan dari halaman ini ────────
const ALLOWED_REG_ROLES = ['user', 'penerima'];

$errors  = [];
$success = false;
$old     = [];   // nilai form lama untuk re-fill setelah error

// ── Proses POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verifikasi CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token keamanan tidak valid. Muat ulang halaman dan coba lagi.";
    }

    // 2. Rate limit: maks 5 percobaan per 10 menit per IP
    elseif (!rateLimit('user_register_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 5, 600)) {
        $errors[] = "Terlalu banyak percobaan pendaftaran. Coba lagi dalam 10 menit.";
    }

    else {
        // 3. Ambil & sanitasi input
        $username  = sanitize($_POST['username']  ?? '', 50);
        $no_hp     = sanitize($_POST['no_hp']     ?? '', 20);
        $alamat    = sanitize($_POST['alamat']     ?? '', 500);
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Whitelist role — tidak boleh ambil nilai bebas dari user
        $raw_role = $_POST['role'] ?? '';
        $role_reg = in_array($raw_role, ALLOWED_REG_ROLES, true) ? $raw_role : '';

        // Simpan nilai lama untuk re-fill (tanpa password)
        $old = compact('username', 'no_hp', 'alamat', 'role_reg');

        // 4. Validasi input
        if (empty($role_reg)) {
            $errors[] = "Pilih jenis akun terlebih dahulu (Donatur atau Penerima).";
        }
        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $errors[] = "Nama pengguna harus 3–50 karakter.";
        }
        if (!preg_match('/^[a-zA-Z0-9_. ]+$/', $username)) {
            $errors[] = "Nama pengguna hanya boleh huruf, angka, titik, spasi, dan underscore.";
        }
        if ($no_hp !== '' && !preg_match('/^[\d\s\+\-]{6,20}$/', $no_hp)) {
            $errors[] = "Format nomor HP tidak valid.";
        }
        if (mb_strlen($password) < 8) {
            $errors[] = "Password minimal 8 karakter.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password harus mengandung minimal 1 huruf kapital.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password harus mengandung minimal 1 angka.";
        }
        if ($password !== $password2) {
            $errors[] = "Konfirmasi password tidak cocok.";
        }

        // 5. Cek duplikat username
        if (empty($errors)) {
            $cek = dbQuery(
                "SELECT user_id FROM users WHERE username = ?",
                's', [$username]
            );
            if ($cek->get_result()->num_rows > 0) {
                $errors[] = "Nama pengguna <strong>" . e($username) . "</strong> sudah digunakan, coba nama lain.";
            }
        }

        // 6. Simpan ke database
        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            dbQuery(
                "INSERT INTO users (username, password, role, no_hp, alamat_lengkap, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                'sssss', [$username, $hash, $role_reg, $no_hp, $alamat]
            );

            $registered_role = $role_reg; // simpan untuk pesan sukses
            $success = true;
            $old = [];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Akun — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root {
      --dark:   #0f1923;
      --coral:  #e85d4a;
      --indigo: #4f46e5;
      --teal:   #0d9488;
      --cream:  #f0f4f8;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      min-height: 100vh;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--cream);
      display: flex;
      align-items: stretch;
    }

    .reg-left {
      width: 400px;
      flex-shrink: 0;
      background: linear-gradient(160deg, var(--dark) 0%, #16213e 55%, #0d2240 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 3rem 2.5rem;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow: hidden;
    }

    /* Dekorasi lingkaran background */
    .reg-left::before {
      content: '';
      position: absolute;
      width: 320px; height: 320px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(79,70,229,.2), transparent 70%);
      top: -80px; right: -80px;
      pointer-events: none;
    }
    .reg-left::after {
      content: '';
      position: absolute;
      width: 250px; height: 250px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(13,148,136,.15), transparent 70%);
      bottom: -60px; left: -60px;
      pointer-events: none;
    }

    .reg-left > * { position: relative; z-index: 1; }

    .reg-brand {
      font-family: 'Fraunces', serif;
      font-size: 1.4rem;
      color: #fff;
      display: flex;
      align-items: center;
      gap: .65rem;
      text-decoration: none;
    }
    .reg-brand .brand-icon {
      background: rgba(255,255,255,.15);
      width: 44px; height: 44px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem;
    }

    .reg-left-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1.75rem;
    }

    .reg-left-title {
      font-family: 'Fraunces', serif;
      font-size: 2rem;
      font-weight: 700;
      color: #fff;
      line-height: 1.25;
    }
    .reg-left-title span { color: var(--coral); }

    .reg-left-desc {
      color: rgba(255,255,255,.5);
      font-size: .88rem;
      line-height: 1.7;
      margin-top: -.5rem;
    }

    .feature-list {
      list-style: none;
      padding: 0;
      display: flex;
      flex-direction: column;
      gap: .75rem;
    }
    .feature-list li {
      display: flex;
      align-items: center;
      gap: .75rem;
      color: rgba(255,255,255,.68);
      font-size: .84rem;
      font-weight: 500;
    }
    .feature-list .fi {
      width: 32px; height: 32px;
      border-radius: 9px;
      background: rgba(255,255,255,.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }

    .reg-left-foot {
      font-size: .72rem;
      color: rgba(255,255,255,.25);
    }

  
    .reg-right {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 3rem 2rem;
      overflow-y: auto;
    }

    .reg-card {
      width: 100%;
      max-width: 540px;
    }

    .reg-heading { margin-bottom: 2rem; }
    .reg-heading h1 {
      font-family: 'Fraunces', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: .3rem;
    }
    .reg-heading p {
      color: #6b7280;
      font-size: .88rem;
    }

    /* ── Pilihan peran (radio cards) ── */
    .role-picker {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .85rem;
      margin-bottom: 1.75rem;
    }
    .role-pick-label {
      cursor: pointer;
      position: relative;
    }
    .role-pick-label input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .role-pick-card {
      border: 2px solid #e5e7eb;
      border-radius: 14px;
      padding: 1rem 1.1rem;
      background: #fff;
      transition: border-color .2s, background .2s, box-shadow .2s;
      display: flex;
      align-items: flex-start;
      gap: .85rem;
    }
    .role-pick-label input:checked + .role-pick-card {
      border-color: var(--indigo);
      background: #eef2ff;
      box-shadow: 0 0 0 3px rgba(79,70,229,.1);
    }
    .role-pick-label:hover .role-pick-card {
      border-color: #a5b4fc;
    }
    .rp-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.15rem;
      flex-shrink: 0;
    }
    .rp-icon.donatur  { background: #ede9fe; color: var(--indigo); }
    .rp-icon.penerima { background: #fce7f3; color: #db2777; }
    .rp-title {
      font-size: .88rem;
      font-weight: 700;
      color: var(--dark);
      line-height: 1.2;
    }
    .rp-desc {
      font-size: .75rem;
      color: #6b7280;
      margin-top: .15rem;
      line-height: 1.45;
    }
    /* Check indicator */
    .role-pick-label input:checked + .role-pick-card .rp-icon.donatur { background: var(--indigo); color: #fff; }
    .role-pick-label input:checked + .role-pick-card .rp-icon.penerima { background: #db2777; color: #fff; }

    /* ── Form controls ── */
    .section-label {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .8px;
      text-transform: uppercase;
      color: #9ca3af;
      margin-bottom: .85rem;
    }
    .form-label {
      font-size: .82rem;
      font-weight: 700;
      color: #374151;
      margin-bottom: .35rem;
    }
    .form-control, .form-select {
      border-radius: 10px;
      border: 1.5px solid #e5e7eb;
      font-size: .9rem;
      padding: .65rem .9rem;
      background: #fff;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--indigo);
      box-shadow: 0 0 0 3.5px rgba(79,70,229,.12);
      outline: none;
    }
    .input-group .form-control { border-radius: 0 10px 10px 0 !important; }
    .input-group-text {
      border-radius: 10px 0 0 10px;
      background: #f8fafc;
      border: 1.5px solid #e5e7eb;
      border-right: none;
      color: #9ca3af;
      font-size: 1rem;
    }
    .input-group:focus-within .input-group-text { border-color: var(--indigo); }

    /* Toggle password button */
    .toggle-pw {
      border-radius: 0 10px 10px 0 !important;
      border: 1.5px solid #e5e7eb !important;
      border-left: none !important;
      background: #f8fafc !important;
      color: #6b7280;
      padding: 0 .85rem;
      cursor: pointer;
      transition: color .2s;
    }
    .toggle-pw:hover { color: var(--dark); }
    .input-group:focus-within .toggle-pw { border-color: var(--indigo) !important; }
    /* Field password di tengah (kiri icon, kanan toggle) */
    .pw-mid { border-radius: 0 !important; border-right: none !important; }

    /* ── Password strength ── */
    .pw-strength {
      height: 4px;
      background: #e5e7eb;
      border-radius: 4px;
      overflow: hidden;
      margin-top: .45rem;
    }
    .pw-strength-bar {
      height: 100%;
      width: 0;
      border-radius: 4px;
      transition: width .3s, background .3s;
    }
    .pw-hint { font-size: .72rem; color: #9ca3af; margin-top: .3rem; }

    /* ── Divider ── */
    .form-divider {
      display: flex; align-items: center; gap: .75rem;
      margin: 1.5rem 0;
      font-size: .78rem; color: #9ca3af;
    }
    .form-divider::before, .form-divider::after {
      content: ''; flex: 1; height: 1px; background: #e5e7eb;
    }

    /* ── Alert errors ── */
    .alert-reg {
      background: #fee2e2;
      border: 1px solid #fca5a5;
      border-radius: 12px;
      padding: 1rem 1.1rem;
      color: #991b1b;
      font-size: .86rem;
      margin-bottom: 1.5rem;
    }
    .alert-reg ul { margin: .35rem 0 0 1.1rem; padding: 0; }
    .alert-reg li { margin-bottom: .2rem; }

    /* ── Tombol daftar ── */
    .btn-register {
      background: linear-gradient(135deg, var(--indigo), #7c3aed);
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: .95rem;
      border-radius: 12px;
      padding: .82rem;
      width: 100%;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
      box-shadow: 0 4px 18px rgba(79,70,229,.3);
      transition: filter .2s, transform .15s, box-shadow .2s;
      cursor: pointer;
    }
    .btn-register:hover {
      filter: brightness(1.1);
      transform: translateY(-1px);
      box-shadow: 0 6px 24px rgba(79,70,229,.4);
      color: #fff;
    }

    /* ── Kartu sukses ── */
    .success-card {
      background: #fff;
      border-radius: 20px;
      padding: 3rem 2.5rem;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0,0,0,.07);
    }
    .success-icon {
      width: 72px; height: 72px;
      background: linear-gradient(135deg, var(--indigo), #7c3aed);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.2rem; color: #fff;
      margin: 0 auto 1.5rem;
      box-shadow: 0 8px 24px rgba(79,70,229,.3);
    }

    /* ── Footer link ── */
    .footer-link {
      text-align: center;
      font-size: .86rem;
      color: #6b7280;
      margin-top: 1.5rem;
    }
    .footer-link a {
      color: var(--indigo);
      font-weight: 700;
      text-decoration: none;
    }
    .footer-link a:hover { text-decoration: underline; }
    .footer-link .sep { color: #d1d5db; margin: 0 .5rem; }

    /* ── Mobile ── */
    @media (max-width: 768px) {
      body { flex-direction: column; }
      .reg-left {
        width: 100%; height: auto;
        position: static;
        padding: 2rem 1.5rem;
      }
      .reg-left-body { gap: 1rem; }
      .reg-left-title { font-size: 1.5rem; }
      .feature-list { display: none; }
      .reg-right { padding: 2rem 1.25rem; }
      .role-picker { grid-template-columns: 1fr 1fr; gap: .65rem; }
    }
    @media (max-width: 400px) {
      .role-picker { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ═══════════════ KIRI: Branding ═══════════════ -->
<div class="reg-left">
  <a class="reg-brand" href="index.php">
    <span class="brand-icon"><i class="bi bi-heart-fill text-white"></i></span>
    KasihSosial
  </a>

  <div class="reg-left-body">
    <div>
      <div class="reg-left-title">
        Bergabung &amp;<br>Mulai <span>Berbagi</span>
      </div>
      <p class="reg-left-desc">
        Donasikan pakaian yang tidak terpakai, atau dapatkan pakaian yang kamu butuhkan.
        Satu langkah kecil, dampak besar bagi sesama.
      </p>
    </div>

    <ul class="feature-list">
      <li>
        <span class="fi"><i class="bi bi-box-seam text-white"></i></span>
        Upload &amp; kelola donasi pakaian kapan saja
      </li>
      <li>
        <span class="fi"><i class="bi bi-bag-heart text-white"></i></span>
        Ajukan permintaan pakaian dengan mudah
      </li>
      <li>
        <span class="fi"><i class="bi bi-chat-dots text-white"></i></span>
        Chat langsung dengan donatur atau penerima
      </li>
      <li>
        <span class="fi"><i class="bi bi-truck text-white"></i></span>
        Lacak status pengiriman secara real-time
      </li>
      <li>
        <span class="fi"><i class="bi bi-shield-check text-white"></i></span>
        Data aman &amp; terenkripsi
      </li>
    </ul>
  </div>

  <div class="reg-left-foot">&copy; <?= date('Y') ?> KasihSosial — Saling Berbagi, Saling Peduli</div>
</div>

<!-- ═══════════════ KANAN: Form ═══════════════ -->
<div class="reg-right">
  <div class="reg-card">

    <?php if ($success): ?>
    <!-- ══ SUKSES ══ -->
    <div class="success-card">
      <div class="success-icon"><i class="bi bi-check-lg"></i></div>
      <h2 style="font-family:'Fraunces',serif;font-size:1.65rem;font-weight:700;margin-bottom:.5rem;">
        Akun Berhasil Dibuat!
      </h2>
      <p style="color:#6b7280;font-size:.9rem;margin-bottom:.5rem;">
        Akun <strong><?= e(($registered_role ?? '') === 'penerima' ? 'Penerima' : 'Donatur') ?></strong>
        kamu sudah aktif. Silakan masuk dan mulai gunakan KasihSosial.
      </p>
      <p style="color:#9ca3af;font-size:.8rem;margin-bottom:2rem;">
        <?php if (($registered_role ?? '') === 'penerima'): ?>
          Kamu bisa langsung mengajukan permintaan pakaian di dashboard penerima.
        <?php else: ?>
          Kamu bisa langsung mengunggah donasi pakaian melalui tombol +Donasikan.
        <?php endif; ?>
      </p>
      <a href="login.php"
         style="display:inline-flex;align-items:center;gap:.5rem;
                background:linear-gradient(135deg,#4f46e5,#7c3aed);
                color:#fff;font-weight:700;font-size:.92rem;
                border-radius:12px;padding:.75rem 2rem;
                text-decoration:none;box-shadow:0 4px 18px rgba(79,70,229,.3);">
        <i class="bi bi-box-arrow-in-right"></i>Masuk Sekarang
      </a>
    </div>

    <?php else: ?>
    <!-- ══ FORM ══ -->
    <div class="reg-heading">
      <h1>Buat Akun Baru</h1>
      <p>Gratis, langsung aktif, tidak perlu persetujuan admin.</p>
    </div>

    <!-- Error messages -->
    <?php if (!empty($errors)): ?>
    <div class="alert-reg">
      <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Periksa kembali:</strong>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= $err ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate autocomplete="off">
      <?= csrfField() ?>

      <!-- ── Pilihan Jenis Akun ── -->
      <div class="section-label"><i class="bi bi-person-badge me-1"></i>Pilih Jenis Akun</div>

      <div class="role-picker mb-4">

        <!-- Donatur -->
        <label class="role-pick-label">
          <input type="radio" name="role" value="user"
                 <?= (($old['role_reg'] ?? '') === 'user' || empty($old)) ? 'checked' : '' ?>>
          <div class="role-pick-card">
            <div class="rp-icon donatur"><i class="bi bi-gift-fill"></i></div>
            <div>
              <div class="rp-title">Donatur</div>
              <div class="rp-desc">Saya ingin mendonasikan pakaian</div>
            </div>
          </div>
        </label>

        <!-- Penerima -->
        <label class="role-pick-label">
          <input type="radio" name="role" value="penerima"
                 <?= (($old['role_reg'] ?? '') === 'penerima') ? 'checked' : '' ?>>
          <div class="role-pick-card">
            <div class="rp-icon penerima"><i class="bi bi-bag-heart-fill"></i></div>
            <div>
              <div class="rp-title">Penerima</div>
              <div class="rp-desc">Saya ingin menerima donasi pakaian</div>
            </div>
          </div>
        </label>

      </div>

      <!-- ── Data Akun ── -->
      <div class="section-label"><i class="bi bi-key me-1"></i>Data Akun</div>

      <!-- Username -->
      <div class="mb-3">
        <label class="form-label">
          Nama Pengguna <span class="text-danger">*</span>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
          <input type="text" name="username" class="form-control"
                 placeholder="contoh: budi123"
                 value="<?= e($old['username'] ?? '') ?>"
                 maxlength="50" required
                 autocomplete="username"
                 pattern="[a-zA-Z0-9_. ]+">
        </div>
        <div class="pw-hint">Huruf, angka, titik, spasi, underscore. Min 3 karakter.</div>
      </div>

      <!-- Password -->
      <div class="mb-3">
        <label class="form-label">
          Password <span class="text-danger">*</span>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
          <input type="password" name="password" id="pwInput"
                 class="form-control pw-mid"
                 placeholder="Min 8 karakter, 1 kapital, 1 angka"
                 required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="btn toggle-pw" tabindex="-1"
                  onclick="togglePw('pwInput', this)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
        <div id="pwMsg" class="pw-hint"></div>
      </div>

      <!-- Konfirmasi Password -->
      <div class="mb-4">
        <label class="form-label">
          Konfirmasi Password <span class="text-danger">*</span>
        </label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
          <input type="password" name="password2" id="pw2Input"
                 class="form-control pw-mid"
                 placeholder="Ulangi password di atas"
                 required autocomplete="new-password"
                 oninput="checkMatch()">
          <button type="button" class="btn toggle-pw" tabindex="-1"
                  onclick="togglePw('pw2Input', this)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div id="matchMsg" class="pw-hint"></div>
      </div>

      <div class="form-divider">Informasi Kontak (Opsional)</div>

      <!-- ── Data Diri ── -->
      <div class="section-label"><i class="bi bi-card-text me-1"></i>Data Diri</div>

      <!-- No HP -->
      <div class="mb-3">
        <label class="form-label">Nomor HP / WhatsApp</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
          <input type="tel" name="no_hp" class="form-control"
                 placeholder="contoh: 08123456789"
                 value="<?= e($old['no_hp'] ?? '') ?>"
                 maxlength="20" autocomplete="tel">
        </div>
        <div class="pw-hint">Untuk koordinasi pengiriman donasi.</div>
      </div>

      <!-- Alamat -->
      <div class="mb-4">
        <label class="form-label">Alamat Lengkap</label>
        <div class="input-group align-items-start">
          <span class="input-group-text" style="padding-top:.7rem;">
            <i class="bi bi-geo-alt-fill"></i>
          </span>
          <textarea name="alamat" class="form-control" rows="3"
                    placeholder="Jl. Merdeka No. 1, Kelurahan, Kota..."
                    maxlength="500"
                    style="border-radius:0 10px 10px 0;"><?= e($old['alamat'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Tombol submit -->
      <button type="submit" class="btn-register">
        <i class="bi bi-person-check-fill"></i>
        Buat Akun Sekarang
      </button>

    </form>

    <!-- Footer links -->
    <div class="footer-link">
      Sudah punya akun?
      <a href="login.php">Masuk di sini</a>
      <span class="sep">·</span>
      <a href="register.php" style="color:#9ca3af;font-weight:500;">Pilihan akun lain</a>
    </div>
    <div class="footer-link mt-1">
      <a href="index.php" style="color:#9ca3af;font-weight:400;">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Katalog
      </a>
    </div>

    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Toggle show/hide password ─────────────────────────────────────
function togglePw(inputId, btn) {
  const inp  = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

// ── Password strength meter ───────────────────────────────────────
function checkStrength(val) {
  const bar = document.getElementById('pwBar');
  const msg = document.getElementById('pwMsg');
  if (!val) { bar.style.width = '0'; msg.textContent = ''; return; }

  let score = 0;
  if (val.length >= 8)            score++;
  if (/[A-Z]/.test(val))          score++;
  if (/[0-9]/.test(val))          score++;
  if (/[^A-Za-z0-9]/.test(val))  score++;

  const levels = [
    { w: '20%',  bg: '#ef4444', txt: '⚠ Terlalu lemah' },
    { w: '40%',  bg: '#f97316', txt: '⚠ Lemah' },
    { w: '65%',  bg: '#f59e0b', txt: '~ Sedang' },
    { w: '85%',  bg: '#3b82f6', txt: '✓ Kuat' },
    { w: '100%', bg: '#10b981', txt: '✓ Sangat Kuat' },
  ];
  const lv = levels[Math.min(score, 4)];
  bar.style.width      = lv.w;
  bar.style.background = lv.bg;
  msg.textContent      = lv.txt;
  msg.style.color      = lv.bg;
}

// ── Konfirmasi password match ─────────────────────────────────────
function checkMatch() {
  const baru  = document.getElementById('pwInput').value;
  const ulang = document.getElementById('pw2Input').value;
  const el    = document.getElementById('matchMsg');
  if (!ulang) { el.textContent = ''; return; }
  if (baru === ulang) {
    el.textContent = '✓ Password cocok';
    el.style.color = '#10b981';
  } else {
    el.textContent = '✗ Password tidak cocok';
    el.style.color = '#ef4444';
  }
}
</script>
</body>
</html>