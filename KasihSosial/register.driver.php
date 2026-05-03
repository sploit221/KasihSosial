<?php

include_once 'koneksi.php';

// Jika sudah login, tolak akses ke halaman registrasi
if (!empty($_SESSION['user_id'])) {
    $dest = match($_SESSION['role'] ?? '') {
        'driver'   => 'driver.dashboard.php',
        'admin'    => 'admin.dashboard.php',
        'penerima' => 'dashboard.penerima.php',
        default    => 'index.php',
    };
    header("Location: $dest"); exit;
}

$errors  = [];
$success = false;
$old     = []; // nilai form lama (untuk re-fill setelah error)

// ── Proses POST ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token keamanan tidak valid. Muat ulang halaman.";
    }

    // 2. Rate limit — maks 5 percobaan per 10 menit per IP
    elseif (!rateLimit('driver_register_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 600)) {
        $errors[] = "Terlalu banyak percobaan registrasi. Coba lagi dalam 10 menit.";
    }

    else {
        // 3. Ambil & sanitasi input
        $username  = sanitize($_POST['username']  ?? '', 50);
        $no_hp     = sanitize($_POST['no_hp']     ?? '', 20);
        $alamat    = sanitize($_POST['alamat']     ?? '', 500);
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Simpan nilai lama untuk re-fill
        $old = compact('username', 'no_hp', 'alamat');

        // 4. Validasi
        if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $errors[] = "Nama pengguna harus 3–50 karakter.";
        }
        if (!preg_match('/^[a-zA-Z0-9_. ]+$/', $username)) {
            $errors[] = "Nama pengguna hanya boleh huruf, angka, titik, spasi, dan underscore.";
        }
        if ($no_hp && !preg_match('/^[\d\s\+\-]{6,20}$/', $no_hp)) {
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

        // 5. Cek username duplikat
        if (empty($errors)) {
            $cek = dbQuery(
                "SELECT user_id FROM users WHERE username = ?",
                's', [$username]
            );
            if ($cek->get_result()->num_rows > 0) {
                $errors[] = "Nama pengguna <strong>" . e($username) . "</strong> sudah digunakan.";
            }
        }

        // 6. Simpan ke database
        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            dbQuery(
                "INSERT INTO users (username, password, role, no_hp, alamat_lengkap, created_at)
                 VALUES (?, ?, 'driver', ?, ?, NOW())",
                'ssss', [$username, $hash, $no_hp, $alamat]
            );

            $success = true;
            $old = []; // bersihkan form
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrasi Driver — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root {
      --dark:  #0f1923;
      --coral: #e85d4a;
      --teal:  #0d9488;
      --cream: #f0f4f8;
    }
    *, *::before, *::after { box-sizing: border-box; }

    body {
      min-height: 100vh;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--cream);
      display: flex;
      align-items: stretch;
    }

    /* ── Split layout ── */
    .reg-left {
      width: 420px;
      flex-shrink: 0;
      background: linear-gradient(160deg, var(--dark) 0%, #16213e 55%, #0d2240 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 3rem 2.5rem;
      position: sticky;
      top: 0;
      height: 100vh;
    }
    .reg-left-brand {
      font-family: 'Fraunces', serif;
      font-size: 1.5rem;
      color: #fff;
      display: flex;
      align-items: center;
      gap: .65rem;
    }
    .reg-left-brand .icon {
      background: rgba(255,255,255,.15);
      width: 44px; height: 44px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem;
    }
    .reg-left-body { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 2rem; }
    .reg-left-title {
      font-family: 'Fraunces', serif;
      font-size: 2rem;
      font-weight: 700;
      color: #fff;
      line-height: 1.25;
    }
    .reg-left-title span { color: var(--coral); }
    .reg-left-desc {
      color: rgba(255,255,255,.55);
      font-size: .88rem;
      line-height: 1.65;
    }
    .feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .75rem; }
    .feature-list li {
      display: flex; align-items: center; gap: .75rem;
      color: rgba(255,255,255,.7);
      font-size: .85rem; font-weight: 500;
    }
    .feature-list li .fi {
      width: 32px; height: 32px;
      border-radius: 9px;
      background: rgba(255,255,255,.08);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
    }
    .reg-left-foot {
      font-size: .75rem;
      color: rgba(255,255,255,.28);
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
      max-width: 520px;
    }

    /* ── Card heading ── */
    .reg-heading {
      margin-bottom: 2rem;
    }
    .reg-heading h1 {
      font-family: 'Fraunces', serif;
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: .25rem;
    }
    .reg-heading p {
      color: #6b7280;
      font-size: .88rem;
      margin: 0;
    }

    /* ── Form elements ── */
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
      transition: border-color .2s, box-shadow .2s;
      background: #fff;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--teal);
      box-shadow: 0 0 0 3.5px rgba(13,148,136,.12);
      outline: none;
    }
    .input-group .form-control { border-radius: 0 10px 10px 0; }
    .input-group-text {
      border-radius: 10px 0 0 10px;
      background: #f8fafc;
      border: 1.5px solid #e5e7eb;
      border-right: none;
      color: #9ca3af;
      font-size: 1rem;
    }
    .input-group:focus-within .input-group-text {
      border-color: var(--teal);
    }
    .input-group .toggle-pw {
      border-radius: 0 10px 10px 0;
      border: 1.5px solid #e5e7eb;
      border-left: none;
      background: #f8fafc;
      color: #6b7280;
      padding: 0 .85rem;
      cursor: pointer;
      transition: color .2s;
    }
    .input-group .toggle-pw:hover { color: var(--dark); }
    .input-group:focus-within .toggle-pw { border-color: var(--teal); }

    /* password field saat ada toggle button */
    .input-group .pw-field { border-radius: 0 !important; border-right: none; }

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

    /* ── Submit button ── */
    .btn-register {
      background: linear-gradient(135deg, #0d9488, #0891b2);
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: .95rem;
      border-radius: 12px;
      padding: .8rem;
      width: 100%;
      transition: filter .2s, transform .15s, box-shadow .2s;
      box-shadow: 0 4px 18px rgba(13,148,136,.3);
      display: flex; align-items: center; justify-content: center; gap: .5rem;
    }
    .btn-register:hover {
      filter: brightness(1.08);
      transform: translateY(-1px);
      box-shadow: 0 6px 22px rgba(13,148,136,.4);
      color: #fff;
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
    .alert-reg ul { margin: .35rem 0 0 1rem; padding: 0; }
    .alert-reg li { margin-bottom: .2rem; }

    /* ── Success card ── */
    .success-card {
      background: #fff;
      border-radius: 20px;
      padding: 3rem 2.5rem;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0,0,0,.07);
    }
    .success-icon {
      width: 72px; height: 72px;
      background: linear-gradient(135deg, #0d9488, #0891b2);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem; color: #fff;
      margin: 0 auto 1.5rem;
      box-shadow: 0 8px 24px rgba(13,148,136,.3);
    }

    /* ── Divider ── */
    .form-divider {
      display: flex; align-items: center; gap: .75rem;
      margin: 1.5rem 0;
      color: #d1d5db; font-size: .78rem;
    }
    .form-divider::before,
    .form-divider::after {
      content: ''; flex: 1;
      height: 1px; background: #e5e7eb;
    }

    /* ── Section label ── */
    .section-label {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .8px;
      text-transform: uppercase;
      color: #9ca3af;
      margin-bottom: 1rem;
    }

    /* ── Login link ── */
    .login-link {
      text-align: center;
      font-size: .85rem;
      color: #6b7280;
      margin-top: 1.5rem;
    }
    .login-link a {
      color: var(--teal);
      font-weight: 700;
      text-decoration: none;
    }
    .login-link a:hover { text-decoration: underline; }

    /* ── Mobile ── */
    @media (max-width: 768px) {
      body { flex-direction: column; }
      .reg-left {
        width: 100%; height: auto;
        position: static;
        padding: 2rem 1.5rem;
      }
      .reg-left-body { gap: 1.25rem; }
      .reg-left-title { font-size: 1.5rem; }
      .feature-list { display: none; }
      .reg-right { padding: 2rem 1.25rem; }
    }
  </style>
</head>
<body>

<!-- ═══════════════ KIRI: Branding ═══════════════ -->
<div class="reg-left">
  <div class="reg-left-brand">
    <span class="icon"><i class="bi bi-heart-fill text-white"></i></span>
    KasihSosial
  </div>

  <div class="reg-left-body">
    <div>
      <div class="reg-left-title">Bergabung sebagai<br><span>Driver Sukarela</span></div>
      <p class="reg-left-desc mt-3">
        Bantu antarkan donasi pakaian ke tangan yang membutuhkan.
        Daftarkan dirimu dan mulai bertugas hari ini.
      </p>
    </div>
    <ul class="feature-list">
      <li>
        <span class="fi"><i class="bi bi-truck text-white"></i></span>
        Kelola tugas pengantaran secara real-time
      </li>
      <li>
        <span class="fi"><i class="bi bi-shield-check text-white"></i></span>
        Akun aman dengan enkripsi password bcrypt
      </li>
      <li>
        <span class="fi"><i class="bi bi-person-badge text-white"></i></span>
        Admin hanya memonitor, tidak perlu approval
      </li>
      <li>
        <span class="fi"><i class="bi bi-clock-history text-white"></i></span>
        Riwayat pengantaran tercatat otomatis
      </li>
    </ul>
  </div>

  <div class="reg-left-foot">&copy; <?= date('Y') ?> KasihSosial — Saling Berbagi, Saling Peduli</div>
</div>

<!-- ═══════════════ KANAN: Form ═══════════════ -->
<div class="reg-right">
  <div class="reg-card">

    <?php if ($success): ?>
    <!-- ── Sukses ── -->
    <div class="success-card">
      <div class="success-icon"><i class="bi bi-check-lg"></i></div>
      <h2 class="fw-bold mb-2" style="font-family:'Fraunces',serif;font-size:1.6rem;">
        Akun Berhasil Dibuat!
      </h2>
      <p class="text-muted mb-4" style="font-size:.9rem;">
        Akun driver Anda sudah aktif. Silakan masuk dan mulai bertugas.
      </p>
      <a href="login.php" class="btn-register text-decoration-none" style="display:inline-flex;width:auto;padding:.7rem 2rem;">
        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Sekarang
      </a>
    </div>

    <?php else: ?>
    <!-- ── Form registrasi ── -->
    <div class="reg-heading">
      <h1>Buat Akun Driver</h1>
      <p>Isi data di bawah — akun langsung aktif tanpa persetujuan admin.</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert-reg">
      <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Periksa kembali:</strong>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= $err /* sudah di-escape di atas */ ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate autocomplete="off">
      <?= csrfField() ?>

      <!-- ── Data Akun ── -->
      <div class="section-label"><i class="bi bi-person me-1"></i>Data Akun</div>

      <div class="mb-3">
        <label class="form-label">Nama Pengguna <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
          <input type="text" name="username" class="form-control"
                 placeholder="contoh: driver_budi"
                 value="<?= e($old['username'] ?? '') ?>"
                 maxlength="50" required autocomplete="username"
                 pattern="[a-zA-Z0-9_. ]+">
        </div>
        <div class="pw-hint">Huruf, angka, titik, spasi, underscore. Min 3 karakter.</div>
      </div>

      <div class="mb-3">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
          <input type="password" name="password" id="pwInput" class="form-control pw-field"
                 placeholder="Min 8 karakter + huruf kapital + angka"
                 required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="toggle-pw" tabindex="-1"
                  onclick="togglePw('pwInput', this)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
        <div id="pwMsg" class="pw-hint"></div>
      </div>

      <div class="mb-4">
        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
          <input type="password" name="password2" id="pw2Input" class="form-control pw-field"
                 placeholder="Ulangi password"
                 required autocomplete="new-password"
                 oninput="checkMatch()">
          <button type="button" class="toggle-pw" tabindex="-1"
                  onclick="togglePw('pw2Input', this)">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div id="matchMsg" class="pw-hint"></div>
      </div>

      <div class="form-divider">Data Diri (Opsional)</div>

      <!-- ── Data Diri ── -->
      <div class="section-label"><i class="bi bi-card-text me-1"></i>Informasi Kontak</div>

      <div class="mb-3">
        <label class="form-label">Nomor HP / WhatsApp</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-telephone-fill"></i></span>
          <input type="tel" name="no_hp" class="form-control"
                 placeholder="contoh: 08123456789"
                 value="<?= e($old['no_hp'] ?? '') ?>"
                 maxlength="20" autocomplete="tel">
        </div>
        <div class="pw-hint">Digunakan untuk koordinasi pengantaran.</div>
      </div>

      <div class="mb-4">
        <label class="form-label">Alamat Lengkap</label>
        <div class="input-group align-items-start">
          <span class="input-group-text" style="padding-top:.7rem;"><i class="bi bi-geo-alt-fill"></i></span>
          <textarea name="alamat" class="form-control" rows="3"
                    placeholder="Jl. Merdeka No. 1, Kota..."
                    maxlength="500"
                    style="border-radius:0 10px 10px 0;"><?= e($old['alamat'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn-register">
        <i class="bi bi-truck-front-fill"></i>Daftarkan Akun Driver
      </button>

    </form>

    <div class="login-link">
      Sudah punya akun? <a href="login.php">Masuk di sini</a>
    </div>
    <div class="login-link mt-2">
      Bukan driver? <a href="index.php">Kembali ke Katalog</a>
    </div>

    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Toggle show/hide password ─────────────────────────────────
function togglePw(inputId, btn) {
  const inp = document.getElementById(inputId);
  const icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

// ── Password strength meter ───────────────────────────────────
function checkStrength(val) {
  const bar = document.getElementById('pwBar');
  const msg = document.getElementById('pwMsg');
  if (!val) { bar.style.width = '0'; msg.textContent = ''; return; }

  let score = 0;
  if (val.length >= 8)           score++;
  if (/[A-Z]/.test(val))         score++;
  if (/[0-9]/.test(val))         score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

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

// ── Konfirmasi password match ─────────────────────────────────
function checkMatch() {
  const baru  = document.getElementById('pwInput').value;
  const ulang = document.getElementById('pw2Input').value;
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