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

// 3. Proteksi Halaman
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Silakan login terlebih dahulu.";
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// 4. Proses Form Submit
if (isset($_POST['simpan'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Token keamanan tidak valid. Silakan refresh halaman.";
    } else {
        $user_id = (int)$_SESSION['user_id'];
        $jenis = sanitize($_POST['jenis_pakaian']);
        $kategori = sanitize($_POST['kategori']);
        $ukuran = sanitize($_POST['ukuran']);
        $kondisi = sanitize($_POST['kondisi']);
        $lat = sanitize($_POST['latitude']);
        $lng = sanitize($_POST['longitude']);

        // Validasi input
        $errors = [];
        if (empty($jenis) || strlen($jenis) > 100) {
            $errors[] = "Jenis pakaian tidak valid";
        }
        if (!in_array($kategori, ['Atasan', 'Bawahan', 'Outerwear', 'Pakaian Anak'])) {
            $errors[] = "Kategori tidak valid";
        }
        if (empty($ukuran) || strlen($ukuran) > 10) {
            $errors[] = "Ukuran tidak valid";
        }
        if (empty($lat) || empty($lng)) {
            $errors[] = "Lokasi harus ditandai pada peta";
        }

        // Validasi file upload
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File foto harus diunggah";
        } else {
            $foto_name = $_FILES['foto']['name'];
            $foto_tmp = $_FILES['foto']['tmp_name'];
            $foto_size = $_FILES['foto']['size'];
            $foto_ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($foto_ext, $allowed_types)) {
                $errors[] = "Format file tidak didukung. Gunakan JPG, PNG, atau WebP";
            }

            if ($foto_size > $max_size) {
                $errors[] = "Ukuran file terlalu besar. Maksimal 5MB";
            }

            // Validasi MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $foto_tmp);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (!in_array($mime_type, $allowed_mimes)) {
                $errors[] = "File yang diunggah bukan gambar yang valid";
            }
        }

        if (!empty($errors)) {
            $error = implode("<br>", $errors);
        } else {
            // Buat nama file unik
            $nama_baru = uniqid() . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $foto_ext;
            $upload_dir = "uploads/";
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $path = $upload_dir . $nama_baru;

            if (move_uploaded_file($foto_tmp, $path)) {
                // Insert menggunakan prepared statement
                $stmt = $conn->prepare(
                    "INSERT INTO pakaian (user_id, jenis_pakaian, kategori, ukuran, kondisi, foto_pakaian, latitude, longitude, status_ketersediaan, tanggal_upload) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Tersedia', NOW())"
                );
                $stmt->bind_param("isssssdd", $user_id, $jenis, $kategori, $ukuran, $kondisi, $nama_baru, $lat, $lng);
                
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Donasi berhasil ditambahkan!";
                    header("Location: kelola.donasi.php");
                    exit;
                } else {
                    unlink($path);
                    error_log("Database error: " . $conn->error);
                    $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
                }
                $stmt->close();
            } else {
                $error = "Gagal mengunggah file. Silakan coba lagi.";
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
    <title>Tambah Donasi - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary: #4f46e5;
            --accent: #0891b2;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .card-main {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 2rem;
        }

        #map { 
            height: 400px; 
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 3px solid #e2e8f0;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.15);
        }

        .form-label {
            font-weight: 600;
            color: #374151;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.875rem;
            border-radius: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .map-instructions {
            background: #eff6ff;
            border-left: 4px solid var(--accent);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .location-display {
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 10px;
            padding: 0.75rem;
            display: none;
        }

        .location-display.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4 mb-5">
        <div class="card card-main">
            <div class="card-header-custom">
                <h3 class="fw-bold mb-1">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tambah Donasi Baru
                </h3>
                <p class="mb-0 opacity-90">Isi detail pakaian dan tentukan lokasi penjemputan</p>
            </div>

            <div class="card-body p-4 p-md-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?= $error; ?></div>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" id="donasiForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">Detail Pakaian</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-tag-fill me-1"></i>Jenis Pakaian
                                </label>
                                <input type="text" name="jenis_pakaian" class="form-control" 
                                       placeholder="Contoh: Kemeja Flanel Biru" 
                                       maxlength="100" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-grid-3x3-gap-fill me-1"></i>Kategori
                                </label>
                                <select name="kategori" class="form-select" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="Atasan">Atasan</option>
                                    <option value="Bawahan">Bawahan</option>
                                    <option value="Outerwear">Outerwear</option>
                                    <option value="Pakaian Anak">Pakaian Anak</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-rulers me-1"></i>Ukuran
                                    </label>
                                    <input type="text" name="ukuran" class="form-control" 
                                           placeholder="S, M, L, XL" maxlength="10" required>
                                </div>

                                <div class="col-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-star-fill me-1"></i>Kondisi
                                    </label>
                                    <select name="kondisi" class="form-select" required>
                                        <option value="Seperti Baru">Seperti Baru</option>
                                        <option value="Sangat Layak">Sangat Layak</option>
                                        <option value="Layak Pakai">Layak Pakai</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-camera-fill me-1"></i>Foto Pakaian
                                </label>
                                <input type="file" name="foto" class="form-control" 
                                       accept="image/jpeg,image/png,image/webp" required>
                                <small class="text-muted">Format: JPG, PNG, WebP (Max 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-geo-alt-fill me-1"></i>Lokasi Penjemputan
                            </h5>
                            
                            <div class="map-instructions">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Cara Penggunaan:</strong>
                                <ol class="mb-0 mt-2 ps-3">
                                    <li>Klik pada peta untuk menandai lokasi</li>
                                    <li>Marker biru akan muncul di titik yang Anda pilih</li>
                                    <li>Koordinat akan tersimpan otomatis</li>
                                </ol>
                            </div>
                            
                            <div id="map" class="mb-3"></div>
                            
                            <div class="location-display" id="locationDisplay">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill text-success fs-4 me-2"></i>
                                    <div>
                                        <strong class="d-block">Lokasi Berhasil Ditandai</strong>
                                        <small class="text-muted">
                                            Lat: <span id="displayLat">-</span>, 
                                            Long: <span id="displayLng">-</span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" id="lat" name="latitude" required>
                            <input type="hidden" id="lng" name="longitude" required>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="simpan" class="btn btn-submit btn-lg">
                            <i class="bi bi-send-fill me-2"></i>Publikasikan Donasi
                        </button>
                        <a href="kelola.donasi.php" class="btn btn-light btn-lg">
                            <i class="bi bi-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Inisialisasi peta dengan view ke Batam
        var map = L.map('map').setView([1.1279, 104.0531], 13);
        
        // Tambahkan tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var marker;
        var locationDisplay = document.getElementById('locationDisplay');
        
        // Event click pada peta
        map.on('click', function(e) {
            // Hapus marker lama jika ada
            if (marker) {
                map.removeLayer(marker);
            }
            
            // Tambah marker baru
            marker = L.marker(e.latlng, {
                icon: L.icon({
                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41]
                })
            }).addTo(map);
            
            // Simpan koordinat
            document.getElementById('lat').value = e.latlng.lat.toFixed(6);
            document.getElementById('lng').value = e.latlng.lng.toFixed(6);
            
            // Tampilkan koordinat
            document.getElementById('displayLat').textContent = e.latlng.lat.toFixed(6);
            document.getElementById('displayLng').textContent = e.latlng.lng.toFixed(6);
            locationDisplay.classList.add('active');
        });

        // Form validation
        document.getElementById('donasiForm').addEventListener('submit', function(e) {
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;
            
            if (!lat || !lng) {
                e.preventDefault();
                alert('Silakan tandai lokasi penjemputan pada peta terlebih dahulu!');
                return false;
            }
        });
    </script>
</body>
</html>