<?php

include 'koneksi.php';
requireLogin();

$user_id     = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg   = '';

// ─── 1. Fetch current user data FIRST ────────────────────────────────────────

$query = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$query->bind_param("i", $user_id);
$query->execute();
$user = $query->get_result()->fetch_assoc();
$query->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ─── 2. Restore flash from redirect ──────────────────────────────────────────
if (isset($_SESSION['success_update'])) {
    $success_msg = $_SESSION['success_update'];
    unset($_SESSION['success_update']);
}

// ─── 3. Process profile update ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $username      = trim($_POST['username'] ?? '');
    $no_hp         = trim($_POST['no_hp'] ?? '') ?: $user['no_hp'];
    $alamat_lengkap = trim($_POST['alamat_lengkap'] ?? '');

    if (empty($username)) {
        $error_msg = "Username tidak boleh kosong.";
    } elseif (strlen($username) > 50) {
        $error_msg = "Username maksimal 50 karakter.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET username = ?, no_hp = ?, alamat_lengkap = ? WHERE user_id = ?"
        );
        $stmt->bind_param("sssi", $username, $no_hp, $alamat_lengkap, $user_id);

        if ($stmt->execute()) {
            $_SESSION['username']      = $username;
            $_SESSION['success_update'] = "Profil berhasil diperbarui!";
            header("Location: profile.php");
            exit;
        } else {
            $error_msg = "Gagal memperbarui profil. Silakan coba lagi.";
        }
        $stmt->close();
    }
}

// Activity stats
$count_pakaian = 0;
$count_request = 0;

if (in_array($user['role'], ['user', 'donatur'])) {
    $s = $conn->prepare("SELECT COUNT(*) AS total FROM pakaian WHERE user_id = ?");
    $s->bind_param("i", $user_id);
    $s->execute();
    $count_pakaian = (int)($s->get_result()->fetch_assoc()['total'] ?? 0);
    $s->close();
} else {
    $s = $conn->prepare("SELECT COUNT(*) AS total FROM donasi_request WHERE penerima_id = ?");
    $s->bind_param("i", $user_id);
    $s->execute();
    $count_request = (int)($s->get_result()->fetch_assoc()['total'] ?? 0);
    $s->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya — KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }

        .profile-wrapper { max-width: 680px; margin: 0 auto; }

        /* ── Profile header ── */
        .profile-hero {
            background: linear-gradient(135deg, #1e1b4b, #312e81 50%, #0c4a6e);
            border-radius: 24px 24px 0 0;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 70% 40%, rgba(6,182,212,.2), transparent 55%);
        }
        .avatar-lg {
            width: 84px; height: 84px;
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; font-weight: 800; color: white;
            margin: 0 auto 1rem;
            box-shadow: 0 6px 20px rgba(0,0,0,.3);
            border: 3px solid rgba(255,255,255,.3);
        }
        .role-badge {
            background: rgba(255,255,255,.2);
            color: white; font-size: .72rem;
            padding: .3em .9em; border-radius: 20px;
            font-weight: 700; letter-spacing: .5px; text-transform: uppercase;
        }

        /* ── Form card ── */
        .form-card {
            background: white;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }
        .form-card-body { padding: 2rem; }

        .form-label { font-weight: 600; font-size: .875rem; color: #374151; }
        .form-control, .form-select {
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            padding: .75rem 1rem;
            font-size: .9rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        }
        textarea.form-control { resize: none; }

        .input-group-text {
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #6b7280;
        }
        .input-group .form-control { border-left: none; border-radius: 0 12px 12px 0; }
        .input-group:focus-within .input-group-text { border-color: #4f46e5; }

        .btn-save {
            background: linear-gradient(135deg, #4f46e5, #0891b2);
            border: none; border-radius: 12px;
            font-weight: 700; padding: .75rem;
            transition: opacity .2s, transform .2s;
        }
        .btn-save:hover { opacity: .9; transform: translateY(-1px); }

        /* ── Stats ── */
        .stats-footer {
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            border-radius: 0 0 24px 24px;
            padding: 1.5rem 2rem;
        }
        .stat-mini {
            background: white;
            border-radius: 14px;
            padding: 1.1rem;
            text-align: center;
            border: 1.5px solid #f1f5f9;
            transition: border-color .2s;
        }
        .stat-mini:hover { border-color: #c7d2fe; }
        .stat-mini-value { font-size: 1.75rem; font-weight: 800; }
        .stat-mini-label { font-size: .75rem; color: #64748b; font-weight: 500; margin-top: .25rem; }
    </style>
</head>
<body>
<div class="container mt-4 pb-5">

    <?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-3 profile-wrapper mx-auto">
        <i class="bi bi-check-circle-fill"></i><?= e($success_msg); ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible border-0 rounded-3 shadow-sm d-flex align-items-center gap-2 mb-3 profile-wrapper mx-auto">
        <i class="bi bi-exclamation-triangle-fill"></i><?= e($error_msg); ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="profile-wrapper">
        <!-- Hero -->
        <div class="profile-hero">
            <div class="avatar-lg">
                <?= strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <h3 class="fw-bold mb-1"><?= e($user['username']); ?></h3>
            <span class="role-badge"><?= e($user['role']); ?></span>
            <p class="mt-2 mb-0" style="font-size:.82rem;opacity:.75;">
                Bergabung sejak <?= date('d M Y', strtotime($user['created_at'] ?? 'now')); ?>
            </p>
        </div>

        <!-- Form -->
        <div class="form-card">
            <div class="form-card-body">
                <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-primary"></i>Edit Profil
                </h5>
                <form action="" method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control"
                                   value="<?= e($user['username']); ?>"
                                   required maxlength="50">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor WhatsApp / HP</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                            <input type="tel" name="no_hp" class="form-control"
                                   placeholder="Nomor baru (kosongkan jika tidak diubah)"
                                   value="">
                        </div>
                        <?php if ($user['no_hp']): ?>
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle me-1"></i>Nomor saat ini: <?= e($user['no_hp']); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="alamat_lengkap" class="form-control"
                                  rows="3" placeholder="Jl. Contoh No. 1, Kota, Provinsi"><?= e($user['alamat_lengkap'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="update_profile" class="btn btn-save text-white">
                            <i class="bi bi-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats footer -->
            <div class="stats-footer">
                <h6 class="fw-bold mb-3 text-secondary">
                    <i class="bi bi-bar-chart-line me-1"></i>Aktivitas Saya
                </h6>
                <div class="row g-3">
                    <?php if (in_array($user['role'], ['user', 'donatur'])): ?>
                    <div class="col-6">
                        <div class="stat-mini">
                            <div class="stat-mini-value text-primary"><?= $count_pakaian; ?></div>
                            <div class="stat-mini-label">Barang Didonasikan</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <a href="kelola.donasi.php" class="text-decoration-none">
                            <div class="stat-mini h-100 d-flex flex-column align-items-center justify-content-center"
                                 style="border-color:#c7d2fe;">
                                <i class="bi bi-box-seam text-primary fs-4"></i>
                                <div class="stat-mini-label mt-1">Kelola Donasi</div>
                            </div>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="col-6">
                        <div class="stat-mini">
                            <div class="stat-mini-value text-success"><?= $count_request; ?></div>
                            <div class="stat-mini-label">Permintaan Saya</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <a href="index.php" class="text-decoration-none">
                            <div class="stat-mini h-100 d-flex flex-column align-items-center justify-content-center"
                                 style="border-color:#bbf7d0;">
                                <i class="bi bi-search text-success fs-4"></i>
                                <div class="stat-mini-label mt-1">Cari Barang</div>
                            </div>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>