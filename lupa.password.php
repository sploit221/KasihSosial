<?php
include_once 'koneksi.php';

// Kalau sudah login, redirect sesuai role
if (!empty($_SESSION['user_id'])) {
    $dest = match ($_SESSION['role'] ?? '') {
        'driver'   => 'driver.dashboard.php',
        'admin'    => 'admin.dashboard.php',
        'penerima' => 'dashboard.penerima.php',
        default    => 'index.php',
    };
    header("Location: $dest");
    exit;
}

$message = '';
$message_type = ''; // 'success' atau 'error'
$generated_link = ''; // link reset (development only)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token keamanan tidak valid.';
        $message_type = 'error';
    } elseif (!rateLimit('lupa_password', 5, 300)) {
        $message = 'Terlalu banyak percobaan. Silakan coba lagi dalam 5 menit.';
        $message_type = 'error';
    } else {
        $username = sanitize($_POST['username'] ?? '', 50);
        if (empty($username)) {
            $message = 'Username wajib diisi.';
            $message_type = 'error';
        } else {
            // Cari user
            $stmt = dbQuery("SELECT user_id FROM users WHERE username = ?", 's', [$username]);
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                // Buat token unik & hapus token lama user ini
                dbQuery("DELETE FROM password_resets WHERE user_id = ?", 'i', [$user['user_id']]);
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                dbQuery("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)",
                        'iss', [$user['user_id'], $token, $expires]);

                // 🔧 Development: tampilkan link (production: kirim email)
                $generated_link = "reset.password.php?token=" . urlencode($token);
                $message = 'Link reset telah dibuat. Klik tautan di bawah untuk mengatur ulang password Anda.';
                $message_type = 'success';
            } else {
                // Jangan kasih tahu apakah username ada/tidak untuk keamanan
                $message = 'Jika username terdaftar, link reset akan ditampilkan.';
                $message_type = 'success';
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
    <title>Lupa Password — KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; font-family: 'Segoe UI', sans-serif; }
        .card { width: 100%; max-width: 450px; border: none; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,.1); }
        .card-header { background: linear-gradient(135deg, #4f46e5, #06b6d4); color: #fff; border-radius: 20px 20px 0 0; padding: 2rem; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); border: none; border-radius: 10px; padding: .75rem; font-weight: bold; }
        .btn-primary:hover { filter: brightness(1.1); }
        .link-reset { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: .75rem; word-break: break-all; font-size: .9rem; color: #1e293b; margin-top: .5rem; display: block; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Lupa Password</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type === 'error' ? 'danger' : 'success' ?> d-flex align-items-center gap-2">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($generated_link): ?>
                <div class="mb-3">
                    <strong>Tautan Reset Password</strong>
                    <span class="link-reset">
                        <a href="<?= e($generated_link) ?>"><?= e($generated_link) ?></a>
                    </span>
                    <small class="text-muted">Berlaku 30 menit ke depan. Jangan bagikan tautan ini kepada siapa pun.</small>
                </div>
                <a href="login.php" class="btn btn-secondary w-100">Kembali ke Login</a>
            <?php else: ?>
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username Anda" required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Kirim Permintaan Reset</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="login.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>