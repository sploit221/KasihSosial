<?php   
// 1. Memulai session dengan pengaturan keamanan
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true
    ]);
}

// 2. Sertakan koneksi database
include 'koneksi.php';

// 3. Proteksi Halaman: Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Silakan login terlebih dahulu.";
    header("Location: login.php");
    exit;
}

// 4. Proteksi: Hanya user dengan role 'user' yang boleh upload
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user') {
    $_SESSION['flash_error'] = "Akses ditolak. Anda tidak memiliki izin untuk mengunggah donasi.";
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// 5. Logika Proses Upload
if (isset($_POST['submit'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Token keamanan tidak valid. Silakan refresh halaman dan coba lagi.";
    } else {
        // Sanitasi input menggunakan fungsi helper
        $jenis = sanitize($_POST['jenis_pakaian']);
        $ukuran = sanitize($_POST['ukuran']);
        $kondisi = sanitize($_POST['kondisi']);
        $user_id = (int)$_SESSION['user_id'];

        // Validasi input dasar
        $validasi_errors = [];
        
        if (empty($jenis) || strlen($jenis) > 50) {
            $validasi_errors[] = "Jenis pakaian tidak valid (maksimal 50 karakter)";
        }
        
        if (empty($ukuran) || strlen($ukuran) > 10) {
            $validasi_errors[] = "Ukuran tidak valid (maksimal 10 karakter)";
        }
        
        if (!in_array($kondisi, ['Sangat Layak', 'Seperti Baru', 'Layak Pakai'])) {
            $validasi_errors[] = "Kondisi yang dipilih tidak valid";
        }

        // Validasi file upload
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $validasi_errors[] = "File foto harus diunggah";
        } else {
            $foto_name = $_FILES['foto']['name'];
            $foto_tmp = $_FILES['foto']['tmp_name'];
            $foto_size = $_FILES['foto']['size'];
            $foto_ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            
            // Daftar ekstensi yang diizinkan
            $allowed_types = array("jpg", "jpeg", "png", "webp");
            $max_file_size = 5 * 1024 * 1024; // 5MB dalam bytes

            // Validasi ekstensi file
            if (!in_array($foto_ext, $allowed_types)) {
                $validasi_errors[] = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau WebP";
            }

            // Validasi ukuran file
            if ($foto_size > $max_file_size) {
                $validasi_errors[] = "Ukuran file terlalu besar. Maksimal 5MB";
            }

            // KEAMANAN EKSTRA: Validasi MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $foto_tmp);
            finfo_close($finfo);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime_type, $allowed_mimes)) {
                $validasi_errors[] = "File yang diunggah bukan gambar yang valid";
            }

            // KEAMANAN EKSTRA: Cek apakah file adalah gambar valid dengan getimagesize
            $image_info = @getimagesize($foto_tmp);
            if ($image_info === false) {
                $validasi_errors[] = "File yang diunggah bukan gambar yang valid";
            }
        }

        // Jika ada error validasi, tampilkan semua error
        if (!empty($validasi_errors)) {
            $error = implode("<br>", $validasi_errors);
        } else {
            // Buat folder uploads jika belum ada
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $error = "Gagal membuat folder upload. Hubungi administrator.";
                }
            }

            if (empty($error)) {
                // Buat nama file yang unik dan aman
                $nama_baru = uniqid() . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $foto_ext;
                $path = $upload_dir . $nama_baru;

                // Pindahkan file ke folder uploads
                if (move_uploaded_file($foto_tmp, $path)) {
                    // Simpan data ke database menggunakan prepared statement (AMAN dari SQL Injection)
                    $stmt = $conn->prepare(
                        "INSERT INTO pakaian (user_id, jenis_pakaian, ukuran, kondisi, foto_pakaian, status_ketersediaan, tanggal_upload) 
                         VALUES (?, ?, ?, ?, ?, 'Tersedia', NOW())"
                    );
                    $stmt->bind_param("issss", $user_id, $jenis, $ukuran, $kondisi, $nama_baru);
                    
                    if ($stmt->execute()) {
                        $_SESSION['flash_success'] = "Terima kasih! Donasi Anda berhasil diunggah dan akan segera tampil di katalog.";
                        header("Location: index.php");
                        exit;
                    } else {
                        // Jika gagal insert ke database, hapus file yang sudah diupload
                        unlink($path);
                        error_log("Database insert error: " . $stmt->error);
                        $error = "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.";
                    }
                    $stmt->close();
                } else {
                    $error = "Gagal memindahkan file ke server. Silakan coba lagi atau hubungi administrator.";
                }
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
    <title>Donasi Pakaian - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4f46e5;
            --accent: #0891b2;
        }
        
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh;
            padding: 2rem 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .card-upload { 
            border-radius: 20px; 
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 2rem;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.15);
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            font-weight: 600;
            padding: 0.875rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
        }

        .btn-light {
            border-radius: 10px;
            font-weight: 600;
            padding: 0.875rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card card-upload">
                <div class="card-header-custom">
                    <i class="bi bi-gift-fill fs-1 mb-2"></i>
                    <h3 class="fw-bold mb-1">Form Donasi Pakaian</h3>
                    <p class="mb-0 opacity-90 small">Berbagi kebaikan untuk sesama</p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div><?= $success; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-tag-fill me-1"></i>Jenis Pakaian
                            </label>
                            <select name="jenis_pakaian" class="form-select" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="Atasan">Atasan (Kemeja, Kaos, Blus)</option>
                                <option value="Bawahan">Bawahan (Celana, Rok)</option>
                                <option value="Outerwear">Outerwear (Jaket, Hoodie)</option>
                                <option value="Pakaian Anak">Pakaian Anak</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-rulers me-1"></i>Ukuran
                            </label>
                            <input type="text" name="ukuran" class="form-control" 
                                   placeholder="Contoh: M, S, L, XL, atau 32" 
                                   maxlength="10" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-star-fill me-1"></i>Kondisi
                            </label>
                            <select name="kondisi" class="form-select" required>
                                <option value="Seperti Baru">Seperti Baru</option>
                                <option value="Sangat Layak">Sangat Layak</option>
                                <option value="Layak Pakai">Layak Pakai</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="bi bi-camera-fill me-1"></i>Foto Pakaian
                            </label>
                            <input type="file" name="foto" class="form-control" 
                                   accept="image/jpeg,image/png,image/webp" required>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Format: JPG, JPEG, PNG, atau WebP (Maksimal 5MB)
                            </small>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send-fill me-2"></i>Kirim Donasi
                            </button>
                            <a href="index.php" class="btn btn-light btn-lg">
                                <i class="bi bi-arrow-left me-2"></i>Kembali ke Katalog
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3 text-white">
                <small>
                    <i class="bi bi-shield-check me-1"></i>
                    Data Anda aman dan terenkripsi
                </small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>