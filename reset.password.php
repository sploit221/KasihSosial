<?php
include_once 'koneksi.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Validasi token
if (empty($token)) {
    $error = 'Token tidak valid.';
} else {
    $stmt = dbQuery("SELECT pr.user_id, pr.expires_at, u.username 
                     FROM password_resets pr 
                     JOIN users u ON u.user_id = pr.user_id 
                     WHERE pr.token = ?", 's', [$token]);
    $reset = $stmt->get_result()->fetch_assoc();

    if (!$reset) {
        $error = 'Token tidak valid atau sudah digunakan.';
    } elseif (strtotime($reset['expires_at']) < time()) {
        $error = 'Token sudah kadaluarsa. Silakan minta reset ulang.';
        // Hapus token expired
        dbQuery("DELETE FROM password_resets WHERE token = ?", 's', [$token]);
    }
}

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (mb_strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password harus mengandung minimal 1 huruf kapital.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password harus mengandung minimal 1 angka.';
        } elseif ($password !== $password2) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            // Update password
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            dbQuery("UPDATE users SET password = ? WHERE user_id = ?", 'si', [$hash, $reset['user_id']]);
            // Hapus token
            dbQuery("DELETE FROM password_resets WHERE token = ?", 's', [$token]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; font-family: 'Segoe UI', sans-serif; }
        .card { width: 100%; max-width: 450px; border: none; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,.1); }
        .card-header { background: linear-gradient(135deg, #4f46e5, #06b6d4); color: #fff; border-radius: 20px 20px 0 0; padding: 2rem; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); border: none; border-radius: 10px; padding: .75rem; font-weight: bold; }
        .btn-primary:hover { filter: brightness(1.1); }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="bi bi-key me-2"></i>Reset Password</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
                <?php if (empty($reset)): ?>
                    <a href="lupa.password.php" class="btn btn-primary w-100">Minta Reset Ulang</a>
                <?php endif; ?>
            <?php elseif ($success): ?>
                <div class="alert alert-success">
                    Password berhasil diubah! Silakan <a href="login.php">login</a> dengan password baru Anda.
                </div>
                <a href="login.php" class="btn btn-primary w-100">Ke Halaman Login</a>
            <?php else: ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="fw-bold">Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="password2" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Simpan Password Baru</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>