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

// 3. Proteksi: Cek apakah sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Silakan login terlebih dahulu.";
    header("Location: login.php");
    exit;
}

// 4. Proteksi Tambahan: Hanya role 'user' yang boleh upload
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user') {
    $_SESSION['flash_error'] = "Akses Ditolak! Anda tidak memiliki izin untuk mengunggah donasi.";
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// 5. Logika Proses Upload
if (isset($_POST['submit'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Token keamanan tidak valid. Silakan refresh halaman.";
    } else {
        // Sanitasi input
        $jenis = sanitize($_POST['jenis_pakaian']);
        $ukuran = sanitize($_POST['ukuran']);
        $kondisi = sanitize($_POST['kondisi']);
        $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? sanitize($_POST['latitude']) : null;
        $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? sanitize($_POST['longitude']) : null;
        $user_id = (int)$_SESSION['user_id'];

        // Validasi input
        $validasi_errors = [];
        if (empty($jenis) || strlen($jenis) > 50) {
            $validasi_errors[] = "Jenis pakaian tidak valid";
        }
        if (empty($ukuran) || strlen($ukuran) > 10) {
            $validasi_errors[] = "Ukuran tidak valid";
        }
        if (!in_array($kondisi, ['Sangat Layak', 'Seperti Baru', 'Layak Pakai'])) {
            $validasi_errors[] = "Kondisi tidak valid";
        }

        // Validasi file upload
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $validasi_errors[] = "File foto harus diunggah";
        } else {
            $foto_name = $_FILES['foto']['name'];
            $foto_tmp = $_FILES['foto']['tmp_name'];
            $foto_size = $_FILES['foto']['size'];
            $foto_ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $allowed_types = array("jpg", "jpeg", "png", "webp");
            $max_size = 5 * 1024 * 1024; // 5MB

            // Validasi ekstensi file
            if (!in_array($foto_ext, $allowed_types)) {
                $validasi_errors[] = "Format file tidak didukung. Gunakan JPG, PNG, atau WebP";
            }

            // Validasi ukuran file
            if ($foto_size > $max_size) {
                $validasi_errors[] = "Ukuran file terlalu besar. Maksimal 5MB";
            }

            // Validasi MIME type untuk keamanan ekstra
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $foto_tmp);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (!in_array($mime_type, $allowed_mimes)) {
                $validasi_errors[] = "File yang diunggah bukan gambar yang valid";
            }
        }

        // Tampilkan error jika ada
        if (!empty($validasi_errors)) {
            $error = implode("<br>", $validasi_errors);
        } else {
            // Buat nama file unik dan aman
            $nama_baru = uniqid() . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $foto_ext;
            $upload_dir = "uploads/";
            
            // Buat folder jika belum ada
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $path = $upload_dir . $nama_baru;

            // Pindahkan file
            if (move_uploaded_file($foto_tmp, $path)) {
                // Simpan ke database menggunakan prepared statement
                $stmt = $conn->prepare(
                    "INSERT INTO pakaian (user_id, jenis_pakaian, ukuran, kondisi, foto_pakaian, latitude, longitude, status_ketersediaan, tanggal_upload) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Tersedia', NOW())"
                );
                $stmt->bind_param("issssss", $user_id, $jenis, $ukuran, $kondisi, $nama_baru, $lat, $lng);
                
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Terima kasih! Donasi Anda berhasil diunggah dan akan segera tampil di katalog.";
                    header("Location: index.php");
                    exit;
                } else {
                    // Hapus file jika gagal insert database
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
    <title>Donasi Pakaian - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --accent: #0891b2;
        }
        
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 2rem 0;
        }
        
        .card-upload {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .card-header-custom h3 {
            font-weight: 800;
            margin-bottom: 0.5rem;
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

        .drop-zone {
            border: 3px dashed #cbd5e1;
            border-radius: 15px;
            padding: 3rem 2rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .drop-zone:hover {
            border-color: var(--primary);
            background: #f1f5f9;
        }

        .drop-zone.dragover {
            border-color: var(--accent);
            background: #e0f2fe;
            transform: scale(1.02);
        }

        .drop-zone i {
            color: #94a3b8;
            transition: color 0.3s ease;
        }

        .drop-zone:hover i {
            color: var(--primary);
        }

        #preview {
            max-width: 100%;
            max-height: 300px;
            margin-top: 1rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            font-weight: 600;
            padding: 0.875rem;
            border-radius: 10px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
            color: white;
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

        .file-info {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f0fdf4;
            border-radius: 10px;
            border: 2px solid #86efac;
        }

        /* ── GPS Lokasi ── */
        .lokasi-box {
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem;
            background: #f8fafc;
            transition: border-color .3s;
        }
        .lokasi-box.has-location {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .lokasi-box.error-location {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .btn-gps {
            background: linear-gradient(135deg, #4f46e5, #0891b2);
            color: #fff; border: none;
            border-radius: 10px; font-weight: 600;
            padding: .6rem 1.1rem; font-size: .875rem;
            display: flex; align-items: center; gap: .5rem;
            cursor: pointer; transition: all .25s;
            white-space: nowrap;
        }
        .btn-gps:hover   { opacity: .88; transform: translateY(-1px); }
        .btn-gps:active  { transform: translateY(0); }
        .btn-gps:disabled {
            opacity: .6; cursor: not-allowed; transform: none;
        }

        .btn-gps-clear {
            background: #fee2e2; color: #991b1b;
            border: none; border-radius: 8px;
            padding: .4rem .8rem; font-size: .8rem;
            font-weight: 600; cursor: pointer; transition: opacity .2s;
        }
        .btn-gps-clear:hover { opacity: .8; }

        .gps-status {
            font-size: .82rem; display: flex;
            align-items: center; gap: .4rem; margin-top: .5rem;
        }
        .gps-status.loading { color: #4f46e5; }
        .gps-status.success { color: #059669; }
        .gps-status.error   { color: #dc2626; }

        .coords-row {
            display: flex; gap: .5rem; margin-top: .6rem;
        }
        .coords-row input {
            font-size: .85rem;
            background: #fff !important;
        }
        .coords-row input[readonly] {
            background: #f0fdf4 !important;
            border-color: #6ee7b7 !important;
            color: #065f46; font-weight: 600;
        }

        /* Peta mini Google Maps */
        #map-preview {
            display: none;
            width: 100%; height: 180px;
            border-radius: 10px; margin-top: .75rem;
            border: 1px solid #d1fae5; overflow: hidden;
        }
        #map-preview iframe {
            width: 100%; height: 100%; border: none;
        }

        .accuracy-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            background: #dbeafe; color: #1e40af;
            border-radius: 20px; padding: .15rem .6rem;
            font-size: .75rem; font-weight: 600;
        }

        @media (max-width: 576px) {
            .card-header-custom {
                padding: 1.5rem 1rem;
            }
            
            .drop-zone {
                padding: 2rem 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7 col-xl-6">
            <div class="card card-upload">
                <div class="card-header-custom">
                    <i class="bi bi-gift-fill fs-1 mb-2"></i>
                    <h3>Donasikan Pakaian Anda</h3>
                    <p class="mb-0 opacity-90 small">Berbagi kebaikan untuk yang membutuhkan</p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <?php if ($error != ""): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success != ""): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div><?= $success; ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-tag-fill me-1"></i>Jenis Pakaian
                            </label>
                            <select name="jenis_pakaian" class="form-select" required>
                                <option value="">-- Pilih Jenis Pakaian --</option>
                                <option value="Atasan">Atasan (Kemeja, Kaos, Blus)</option>
                                <option value="Bawahan">Bawahan (Celana, Rok)</option>
                                <option value="Outerwear">Outerwear (Jaket, Hoodie)</option>
                                <option value="Pakaian Anak">Pakaian Anak</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-rulers me-1"></i>Ukuran
                                </label>
                                <input type="text" name="ukuran" class="form-control" 
                                       placeholder="Contoh: M, L, XL, 32" 
                                       maxlength="10" required>
                                <small class="text-muted">S, M, L, XL, atau ukuran angka</small>
                            </div>

                            <div class="col-md-6 mb-3">
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
                            <label class="form-label fw-bold">
                                <i class="bi bi-camera-fill me-1"></i>Foto Pakaian
                            </label>
                            <div class="drop-zone" id="dropZone">
                                <i class="bi bi-cloud-upload fs-1"></i>
                                <p class="mt-3 mb-2 fw-bold">Klik atau Drag & Drop Foto</p>
                                <p class="text-muted small mb-0">Format: JPG, PNG, WebP (Maksimal 5MB)</p>
                                <input type="file" name="foto" id="fileInput" 
                                       accept="image/jpeg,image/png,image/webp" 
                                       style="display: none;" required>
                            </div>
                            <img id="preview" src="#" alt="Preview" style="display:none;">
                            <div class="file-info" id="fileInfo">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <span id="fileName"></span>
                                <span class="float-end text-muted" id="fileSize"></span>
                            </div>
                        </div>

                        <!-- Lokasi Opsional — GPS Otomatis -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-geo-alt-fill me-1"></i>Lokasi Pengambilan
                                <span class="badge bg-secondary small">Opsional</span>
                            </label>

                            <div class="lokasi-box" id="lokasiBox">
                                <!-- Baris tombol GPS + clear -->
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn-gps" id="btnGPS">
                                        <i class="bi bi-crosshair2" id="gpsIcon"></i>
                                        <span id="gpsBtnText">Gunakan Lokasi GPS Saya</span>
                                    </button>
                                    <button type="button" class="btn-gps-clear"
                                            id="btnClear" style="display:none;">
                                        <i class="bi bi-x-lg me-1"></i>Hapus
                                    </button>
                                </div>

                                <!-- Status teks -->
                                <div class="gps-status" id="gpsStatus" style="display:none;">
                                    <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                                    <span id="gpsStatusText"></span>
                                    <span class="accuracy-badge" id="accuracyBadge" style="display:none;">
                                        <i class="bi bi-bullseye"></i>
                                        <span id="accuracyText"></span>
                                    </span>
                                </div>

                                <!-- Input koordinat (readonly setelah GPS berhasil) -->
                                <div class="coords-row" id="coordsRow" style="display:none;">
                                    <input type="text" name="latitude" id="inputLat"
                                           class="form-control" placeholder="Latitude" readonly>
                                    <input type="text" name="longitude" id="inputLng"
                                           class="form-control" placeholder="Longitude" readonly>
                                </div>

                                <!-- Peta mini -->
                                <div id="map-preview">
                                    <iframe id="mapFrame" src="" allowfullscreen
                                            loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                                    </iframe>
                                </div>
                            </div>

                            <small class="text-muted mt-1 d-block">
                                <i class="bi bi-info-circle me-1"></i>
                                Klik tombol GPS untuk mengisi koordinat secara otomatis, atau biarkan kosong.
                            </small>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="submit" class="btn btn-submit btn-lg">
                                <i class="bi bi-send-fill me-2"></i>Kirim Donasi Sekarang
                            </button>
                            <a href="index.php" class="btn btn-light btn-lg">
                                <i class="bi bi-arrow-left me-2"></i>Kembali ke Katalog
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">
                    <i class="bi bi-shield-check me-1"></i>
                    Data Anda aman dan terenkripsi
                </small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const preview = document.getElementById('preview');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    // Click to upload
    dropZone.addEventListener('click', (e) => {
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });
    
    // Drag over
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    // Drag leave
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    // Drop file
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            handleFile(files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length) {
            handleFile(e.target.files[0]);
        }
    });
    
    // Handle file preview
    function handleFile(file) {
        // Validasi ukuran file (5MB)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('Ukuran file terlalu besar! Maksimal 5MB');
            fileInput.value = '';
            return;
        }
        
        // Validasi tipe file
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Format file tidak didukung! Gunakan JPG, PNG, atau WebP');
            fileInput.value = '';
            return;
        }
        
        // Show preview
        if (file && file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.style.display = 'block';
                
                // Show file info
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Form validation
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        if (!fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Silakan pilih foto pakaian terlebih dahulu!');
            return false;
        }
    });

    // ── GPS Geolocation ──────────────────────────────────────────────────────
    const btnGPS      = document.getElementById('btnGPS');
    const btnClear    = document.getElementById('btnClear');
    const gpsIcon     = document.getElementById('gpsIcon');
    const gpsBtnText  = document.getElementById('gpsBtnText');
    const gpsStatus   = document.getElementById('gpsStatus');
    const gpsStatusTx = document.getElementById('gpsStatusText');
    const accuracyBdg = document.getElementById('accuracyBadge');
    const accuracyTx  = document.getElementById('accuracyText');
    const coordsRow   = document.getElementById('coordsRow');
    const inputLat    = document.getElementById('inputLat');
    const inputLng    = document.getElementById('inputLng');
    const lokasiBox   = document.getElementById('lokasiBox');
    const mapPreview  = document.getElementById('map-preview');
    const mapFrame    = document.getElementById('mapFrame');

    // Cek apakah browser mendukung Geolocation
    if (!navigator.geolocation) {
        btnGPS.disabled = true;
        gpsBtnText.textContent = 'GPS tidak didukung browser ini';
    }

    function setStatus(type, msg) {
        gpsStatus.style.display = 'flex';
        gpsStatus.className = 'gps-status ' + type;
        gpsStatusTx.textContent = msg;
    }

    function setLoading(loading) {
        btnGPS.disabled = loading;
        if (loading) {
            gpsIcon.className = 'bi bi-arrow-repeat spin';
            gpsBtnText.textContent = 'Mengambil lokasi...';
        } else {
            gpsIcon.className = 'bi bi-crosshair2';
            gpsBtnText.textContent = 'Perbarui Lokasi GPS';
        }
    }

    function showMap(lat, lng) {
        // Google Maps Embed — tidak perlu API key untuk tampilan statis
        const src = `https://maps.google.com/maps?q=${lat},${lng}&z=16&output=embed`;
        mapFrame.src = src;
        mapPreview.style.display = 'block';
    }

    function clearLocation() {
        inputLat.value = '';
        inputLng.value = '';
        coordsRow.style.display = 'none';
        mapPreview.style.display = 'none';
        mapFrame.src = '';
        gpsStatus.style.display = 'none';
        accuracyBdg.style.display = 'none';
        lokasiBox.classList.remove('has-location', 'error-location');
        btnClear.style.display = 'none';
        gpsIcon.className = 'bi bi-crosshair2';
        gpsBtnText.textContent = 'Gunakan Lokasi GPS Saya';
        btnGPS.disabled = false;
    }

    btnGPS.addEventListener('click', function () {
        setLoading(true);
        setStatus('loading', 'Meminta izin akses lokasi...');
        lokasiBox.classList.remove('has-location', 'error-location');

        navigator.geolocation.getCurrentPosition(
            // ── SUKSES ──
            function (pos) {
                const lat = pos.coords.latitude.toFixed(7);
                const lng = pos.coords.longitude.toFixed(7);
                const acc = Math.round(pos.coords.accuracy);

                // Isi input
                inputLat.value = lat;
                inputLng.value = lng;
                coordsRow.style.display = 'flex';

                // Status
                setStatus('success', 'Lokasi berhasil didapatkan!');
                accuracyTx.textContent = '± ' + acc + ' m';
                accuracyBdg.style.display = 'inline-flex';

                // Styling kotak
                lokasiBox.classList.add('has-location');
                btnClear.style.display = 'inline-block';
                setLoading(false);

                // Tampilkan peta
                showMap(lat, lng);
            },
            // ── GAGAL ──
            function (err) {
                setLoading(false);
                lokasiBox.classList.add('error-location');
                gpsIcon.className = 'bi bi-crosshair2';
                gpsBtnText.textContent = 'Gunakan Lokasi GPS Saya';

                const pesan = {
                    1: 'Izin lokasi ditolak. Aktifkan izin lokasi di pengaturan browser.',
                    2: 'Sinyal GPS tidak tersedia. Pastikan GPS aktif.',
                    3: 'Waktu habis. Coba lagi di tempat yang lebih terbuka.'
                };
                setStatus('error', pesan[err.code] || 'Gagal mendapatkan lokasi.');
            },
            // ── OPSI ──
            {
                enableHighAccuracy: true, // Gunakan GPS chip (bukan IP/WiFi)
                timeout: 15000,           // Maks 15 detik
                maximumAge: 0             // Selalu ambil posisi terbaru
            }
        );
    });

    btnClear.addEventListener('click', clearLocation);

    // Animasi spin untuk ikon loading
    const style = document.createElement('style');
    style.textContent = `
        .spin { animation: spinAnim .8s linear infinite; display: inline-block; }
        @keyframes spinAnim { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);
</script>
</body>
</html>