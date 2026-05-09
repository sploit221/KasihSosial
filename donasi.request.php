<?php
include_once 'koneksi.php';
requireLogin();

if ($_SESSION['role'] !== 'penerima') {
    flash('error', 'Hanya penerima yang dapat mengajukan permintaan donasi.');
    header('Location: index.php');
    exit;
}

$pakaian_id  = isset($_GET['id']) ? (int)$_GET['id'] : null;
$penerima_id = (int)$_SESSION['user_id'];

if (!$pakaian_id) {
    die("Permintaan tidak valid. ID Barang tidak ditemukan.");
}

// Cek agar satu user tidak bisa request barang yang sama dua kali
$cek = $conn->prepare("SELECT request_id FROM donasi_request WHERE pakaian_id = ? AND penerima_id = ? AND status != 'Ditolak'");
$cek->bind_param("ii", $pakaian_id, $penerima_id);
$cek->execute();
if ($cek->get_result()->num_rows > 0) {
    echo "<script>alert('Anda sudah pernah mengajukan permintaan untuk barang ini.'); window.location='index.php';</script>";
    exit;
}
$cek->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid. Muat ulang halaman.');
        header("Location: donasi.request.php?id={$pakaian_id}"); exit;
    }
    $catatan     = sanitize($_POST['catatan'] ?? '', 500);
    $lokasi_teks = sanitize($_POST['lokasi']  ?? '', 300);
    // Validasi range koordinat GPS
    $lat_raw = !empty($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng_raw = !empty($_POST['lng']) ? (float)$_POST['lng'] : null;
    $lat = ($lat_raw !== null && $lat_raw >= -90  && $lat_raw <= 90)  ? $lat_raw : null;
    $lng = ($lng_raw !== null && $lng_raw >= -180 && $lng_raw <= 180) ? $lng_raw : null;

    if ($lat !== null && $lng !== null) {
        $sql = "INSERT INTO donasi_request
                (pakaian_id, penerima_id, catatan_penerima, lokasi_terkini, latitude, longitude, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissdd", $pakaian_id, $penerima_id, $catatan, $lokasi_teks, $lat, $lng);
    } else {
        $sql = "INSERT INTO donasi_request
                (pakaian_id, penerima_id, catatan_penerima, lokasi_terkini, status)
                VALUES (?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $pakaian_id, $penerima_id, $catatan, $lokasi_teks);
    }

    if ($stmt->execute()) {
        $stmt->close();
        flash('success', 'Permintaan berhasil terkirim! Donatur akan segera memproses permintaan Anda.');
        header("Location: dashboard.penerima.php"); exit;
    } else {
        $error_msg = "Maaf, terjadi kesalahan teknis saat menyimpan data.";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minta Barang - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; }
        .map-preview {
            margin-top: 10px; padding: 10px 14px; background: #f0fff4;
            border: 1px solid #b7ebc8; border-radius: 8px; font-size: .85rem;
            color: #155724; display: flex; align-items: center; gap: 8px;
        }
        .map-preview.error { background:#fff5f5; border-color:#f5c6cb; color:#721c24; }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-dark px-4 py-2" style="background:#1a1a2e;">
  <a class="navbar-brand fw-bold" href="index.php">
    <span style="color:#e85d4a;">&#10084;</span> KasihSosial
  </a>
  <div class="d-flex align-items-center gap-2">
    <span class="text-white small opacity-75">
      <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['username'] ?? ''); ?>
    </span>
    <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
      <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
  </div>
</nav>
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold py-3">
                    <i class="bi bi-box2-heart me-2"></i>Ajukan Permintaan Donasi
                </div>
                <div class="card-body p-4">
                    <?php if (isset($error_msg)): ?>
                        <div class="alert alert-danger"><?= e($error_msg); ?></div>
                    <?php endif; ?>

                    <p class="text-muted small border-bottom pb-2">
                        Anda mengajukan permintaan untuk Barang ID: <strong>#<?= $pakaian_id; ?></strong>
                    </p>

                    <form action="" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">

                        <div class="mb-3 mt-3">
                            <label class="form-label fw-bold">Alamat / Patokan Lokasi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-geo-alt text-danger"></i>
                                </span>
                                <input type="text" name="lokasi" id="lokasiInput"
                                       class="form-control border-start-0"
                                       placeholder="Contoh: Depan Apartment Pollux atau Toko Pak Slamet"
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Titik Koordinat
                                <span class="text-muted fw-normal">(opsional, disarankan)</span>
                            </label>
                            <br>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    id="btnGps" onclick="getLocation()">
                                <i class="bi bi-crosshair me-1"></i>Ambil Lokasi GPS Saya
                            </button>
                            <div id="map-status" class="map-preview" style="display:none;"></div>
                            <small class="text-muted d-block mt-1" style="font-size:.75rem;">
                                *Pastikan GPS aktif agar donatur mudah menemukan lokasi Anda.
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Alasan Membutuhkan Barang</label>
                            <textarea name="catatan" class="form-control" rows="4"
                                      placeholder="Tuliskan alasan singkat mengapa Anda memerlukan donasi ini..."
                                      required></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="index.php" class="btn btn-light w-50 fw-bold border">Batal</a>
                            <button type="submit" name="kirim_request" class="btn btn-success w-50 fw-bold">
                                <i class="bi bi-send me-2"></i>Kirim Permintaan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                <small class="text-muted">&copy; 2026 KasihSosial - Saling Berbagi</small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // FIX: Fungsi GPS langsung inline — tidak bergantung file JS eksternal
    function getLocation() {
        const btn    = document.getElementById('btnGps');
        const status = document.getElementById('map-status');
        const latEl  = document.getElementById('lat');
        const lngEl  = document.getElementById('lng');

        if (!navigator.geolocation) {
            showStatus('Browser Anda tidak mendukung GPS.', true);
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengambil lokasi...';
        showStatus('Sedang mendeteksi lokasi Anda...', false);

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                 const lat = pos.coords.latitude.toFixed(6);
                const lng = pos.coords.longitude.toFixed(6);
                latEl.value = lat;
                lngEl.value = lng;
                showStatus(
                    '<i class="bi bi-check-circle-fill me-1"></i>Lokasi terkunci: ' + lat + ', ' + lng,
                    false
                );
                // Auto-isi kolom alamat jika masih kosong
                const lokasiInput = document.getElementById('lokasiInput');
                if (!lokasiInput.value) {
                    lokasiInput.value = 'Koordinat GPS: ' + lat + ', ' + lng;
                }
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Lokasi Terkunci';
                btn.classList.replace('btn-outline-primary', 'btn-success');
            },
            function(err) {
                const msg = {
                    1: 'Izin GPS ditolak. Aktifkan izin lokasi di browser.',
                    2: 'Lokasi tidak dapat dideteksi.',
                    3: 'Waktu habis. Coba lagi.'
                }[err.code] || 'Gagal mengambil lokasi.';
                showStatus(msg, true);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Coba Lagi';
            },
            { timeout: 10000, enableHighAccuracy: true }
        );
    }

    function showStatus(msg, isError) {
        const el = document.getElementById('map-status');
        el.style.display = 'flex';
        el.className = 'map-preview' + (isError ? ' error' : '');
        el.innerHTML = msg;
    }

    // Bootstrap 5 client-side validation
    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Disable submit button after click (cegah double-submit)
    document.querySelector('form')?.addEventListener('submit', function() {
        const btn = this.querySelector('[name=kirim_request]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengirim...';
        }
    });
</script>
</body>
</html>