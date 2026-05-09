<?php 

// 1. Inisialisasi Session & Koneksi
session_start();
include_once 'koneksi.php'; 

// 2. Proteksi Halaman: Jika sudah login, lempar ke index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 3. Logika Proses Login
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid. Silakan muat ulang halaman.";
    }
    elseif (!rateLimit('login_attempt', 5, 300)) {
        $error = "Terlalu banyak percobaan login. Silakan coba lagi dalam 5 menit.";
    }
    else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Username dan password wajib diisi.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($password, $row['password'])) {
                $_SESSION['user_id']  = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role']     = $row['role'];
                session_regenerate_id(true);
                
                // BUG FIX: 'user' role was never redirected — added case below
                switch ($row['role']) {
                    case 'admin':  header("Location: admin.dashboard.php"); break;
                    case 'driver': header("Location: driver.dashboard.php"); break;
                    default:       header("Location: index.php");           break; // 'user' & others
                }
                exit;
            } else {
                $error = "Username atau password salah!";
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
    <title>Login — KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --accent: #06b6d4;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #0c4a6e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 1rem;
        }

        /* Animated floating blobs */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: float 8s ease-in-out infinite;
            pointer-events: none;
        }
        .blob-1 { width: 500px; height: 500px; background: #818cf8; top: -100px; left: -150px; }
        .blob-2 { width: 400px; height: 400px; background: #06b6d4; bottom: -80px; right: -100px; animation-delay: -4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-30px) scale(1.05); }
        }

        .card-login {
            background: rgba(255, 255, 255, 0.97);
            border: none;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.5s ease;
            overflow: hidden;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-header-login {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            color: #fff;
        }

        .brand-icon {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
            backdrop-filter: blur(10px);
        }

        .card-body-login { padding: 2rem; }

        .form-label { font-weight: 600; font-size: .875rem; color: #374151; margin-bottom: .4rem; }

        .form-control {
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            padding: .75rem 1rem;
            font-size: .95rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,.15);
        }

        .input-group-text {
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #6b7280;
        }
        .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }
        .input-group:focus-within .input-group-text {
            border-color: var(--primary);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            border-radius: 12px;
            padding: .8rem;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .3px;
            transition: transform .2s, box-shadow .2s, opacity .2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79,70,229,.4);
            opacity: .95;
        }
        .btn-login:active { transform: translateY(0); }

        .divider {
            display: flex; align-items: center; gap: 1rem;
            color: #9ca3af; font-size: .8rem; margin: 1.25rem 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }

        .alert-error {
            background: #fef2f2; border: 1px solid #fecaca;
            color: #dc2626; border-radius: 12px;
            padding: .75rem 1rem; font-size: .875rem;
            display: flex; align-items: center; gap: .5rem;
        }

        .toggle-password { cursor: pointer; color: #9ca3af; }
        .toggle-password:hover { color: var(--primary); }
    </style>
</head>
<body>
    <!-- Background blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="card-login">
        <!-- Header -->
        <div class="card-header-login">
            <div class="brand-icon"><i class="bi bi-heart-fill"></i></div>
            <h2 class="fw-bold mb-1" style="font-size:1.6rem;">KasihSosial</h2>
            <p class="mb-0" style="opacity:.85;font-size:.9rem;">Platform Donasi Pakaian</p>
        </div>

        <!-- Body -->
        <div class="card-body-login">
            <?php if ($error): ?>
            <div class="alert-error mb-3">
                <i class="bi bi-exclamation-circle-fill"></i> <?= e($error); ?>
            </div>
            <?php endif; ?>

            <a href="beranda.php" class="btn btn-outline-secondary w-100 mb-3">
                <i class="bi bi-house"></i> Lihat Beranda Publik
            </a>

            <form action="" method="POST" autocomplete="on" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control"
                               placeholder="Masukkan username" required autofocus
                               value="<?= e($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="••••••••" required id="password">
                        <span class="input-group-text toggle-password" onclick="togglePwd()">
                            <i class="bi bi-eye" id="eye-icon"></i>
                        </span>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" name="login" class="btn btn-login text-white">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                    </button>
                </div>
            </form>
            
            <div class="mt-3 text-center">
                <a href="lupa.password.php" class="text-decoration-none" style="color: var(--primary);">Lupa Password?</a>
            </div>

            <div class="divider">atau</div>

            <div class="text-center">
                <p class="text-muted small mb-0">
                    Belum punya akun?
                    <a href="register.php" class="fw-bold text-decoration-none" style="color:var(--primary);">
                        Daftar Sekarang
                    </a>
                </p>
            </div>
        </div>

        <div class="text-center pb-3">
            <small class="text-muted">&copy; 2026 KasihSosial</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePwd() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>