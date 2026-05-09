<?php
include_once 'koneksi.php';
requireLogin();

$my_id      = (int)$_SESSION['user_id'];
$request_id = validateId($_GET['id'] ?? 0);

if (!$request_id) {
    flash('error', 'ID permintaan tidak valid.');
    header("Location: dashboard.penerima.php"); exit;
}

// Query data request, driver, penerima
$stmt = dbQuery(
    "SELECT
        dr.request_id,
        dr.status                        AS status_request,
        dr.lokasi_terkini                AS alamat_penerima,
        dr.latitude                      AS lat_tujuan,
        dr.longitude                     AS lng_tujuan,
        tp.tugas_id,
        tp.status_pengantaran,
        tp.updated_at                    AS last_update,
        tp.ongkos_kirim,
        tp.status_pembayaran,
        p.jenis_pakaian,
        p.ukuran,
        p.foto_pakaian,
        p.lokasi_pengambilan             AS alamat_pemberi,
        u_driver.username                AS nama_driver,
        u_driver.no_hp                   AS hp_driver,
        u_pemberi.username               AS nama_pemberi
     FROM donasi_request dr
     JOIN pakaian          p         ON dr.pakaian_id  = p.pakaian_id
     JOIN users            u_pemberi ON p.user_id      = u_pemberi.user_id
     LEFT JOIN tugas_pengantaran tp  ON tp.request_id  = dr.request_id
     LEFT JOIN users       u_driver  ON tp.driver_id   = u_driver.user_id
     WHERE dr.request_id = ? AND dr.penerima_id = ?",
    'ii', [$request_id, $my_id]
);

$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    flash('error', 'Data tidak ditemukan atau Anda tidak berhak mengakses halaman ini.');
    header("Location: dashboard.penerima.php"); exit;
}

$latTujuan = (float)($data['lat_tujuan'] ?? 0);
$lngTujuan = (float)($data['lng_tujuan'] ?? 0);
$url_maps  = "https://www.google.com/maps/dir/?api=1&destination={$latTujuan},{$lngTujuan}&travelmode=driving";

// Alur timeline
$alur = [
    'Pending'             => ['label' => 'Menunggu Driver',      'icon' => 'bi-hourglass-split',    'color' => 'warning'],
    'Menuju Penjemputan'  => ['label' => 'Driver Berangkat',     'icon' => 'bi-truck',               'color' => 'info'],
    'Barang Diambil'      => ['label' => 'Barang Diambil',       'icon' => 'bi-bag-check-fill',      'color' => 'info'],
    'Dalam Perjalanan'    => ['label' => 'Dalam Perjalanan',     'icon' => 'bi-arrow-right-circle-fill', 'color' => 'primary'],
    'Tiba di Tujuan'      => ['label' => 'Tiba di Tujuan',       'icon' => 'bi-geo-alt-fill',        'color' => 'success'],
    'Selesai'             => ['label' => 'Selesai',              'icon' => 'bi-check-circle-fill',   'color' => 'success'],
];

$status_driver  = $data['status_pengantaran'] ?? 'Pending';
$status_request = $data['status_request'];

$urutan_kunci = array_keys($alur);
$idx_aktif    = array_search($status_driver, $urutan_kunci);
if ($idx_aktif === false) $idx_aktif = 0;

$bisa_konfirmasi = ($status_request === 'Tiba di Tujuan');
$sudah_diterima  = ($status_request === 'Diterima');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lacak Pengiriman — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
  <style>
    body {
      background: #f0f4f8;
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
    }

    .top-bar {
      background: linear-gradient(135deg, #1e293b, #0f172a);
      color: #fff;
      padding: .75rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 12px rgba(0,0,0,.1);
    }

    .main-card {
      max-width: 720px;
      margin: 2rem auto;
      padding: 0 1rem;
    }
    .card {
      border: none;
      border-radius: 20px;
      box-shadow: 0 8px 30px rgba(0,0,0,.08);
      overflow: hidden;
    }
    .card-header-custom {
      background: linear-gradient(135deg, #4f46e5, #0891b2);
      color: #fff;
      padding: 1.25rem 1.5rem;
    }

    .item-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      padding: 1rem;
      display: flex;
      gap: 1rem;
      align-items: center;
      margin-bottom: 1.25rem;
    }
    .item-box img { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; }

    .driver-card {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 14px;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.25rem;
    }
    .driver-avatar {
      width: 48px; height: 48px; border-radius: 50%;
      background: linear-gradient(135deg, #4f46e5, #0891b2);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.25rem; flex-shrink: 0;
    }

    #map {
      height: 360px;
      border-radius: 14px;
      margin: 1.25rem 0;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
      z-index: 1;
    }

    .route-info {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 12px;
      padding: .75rem 1rem;
      margin-top: -.5rem;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      font-size: .9rem;
    }

    .timeline {
      position: relative;
      padding-left: 2.5rem;
    }
    .timeline::before {
      content: ''; position: absolute; left: .9rem; top: 0; bottom: 0;
      width: 2px; background: #e2e8f0;
    }
    .tl-step { position: relative; margin-bottom: 1.5rem; }
    .tl-dot {
      position: absolute; left: -1.95rem; top: .15rem;
      width: 1.6rem; height: 1.6rem; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .7rem; border: 3px solid #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,.15); transition: all .4s;
    }
    .tl-dot.done  { background: #10b981; color: #fff; }
    .tl-dot.aktif { background: #4f46e5; color: #fff; animation: pulse 1.5s infinite; }
    .tl-dot.belum { background: #e2e8f0; color: #94a3b8; }

    @keyframes pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(79,70,229,.4); }
      50%      { box-shadow: 0 0 0 8px rgba(79,70,229,0); }
    }

    .tl-label { font-weight: 600; color: #1e293b; font-size: .9rem; }
    .tl-label.belum { color: #94a3b8; font-weight: 400; }
    .tl-sub { font-size: .78rem; color: #64748b; margin-top: .1rem; }

    .btn-konfirmasi {
      display: block; width: 100%; padding: .85rem;
      background: linear-gradient(135deg, #059669, #0d9488);
      color: #fff; font-weight: 700; font-size: 1rem;
      border: none; border-radius: 14px; cursor: pointer;
      text-decoration: none; text-align: center;
      box-shadow: 0 4px 15px rgba(5,150,105,.35); transition: opacity .2s;
    }
    .btn-konfirmasi:hover { opacity: .88; color: #fff; }

    .done-banner {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      border: 1px solid #6ee7b7; border-radius: 14px;
      padding: 1.25rem; text-align: center; color: #065f46;
    }

    .leaflet-routing-container { display: none; } /* sembunyikan kontrol bawaan */
  </style>
</head>
<body>

<div class="top-bar">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-truck fs-5"></i>
    <strong>KasihSosial</strong>
    <span class="opacity-50 mx-1">|</span>
    <span class="opacity-75 small">Lacak Pengiriman</span>
  </div>
  <a href="dashboard.penerima.php" class="btn btn-sm btn-outline-light">
    <i class="bi bi-arrow-left me-1"></i>Dashboard
  </a>
</div>

<div class="main-card">
  <div class="card">
    <div class="card-header-custom">
      <h5 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Detail Paket</h5>
      <small class="opacity-75">Status diperbarui secara otomatis setiap 30 detik</small>
    </div>
    <div class="card-body p-3">

      <!-- Info Barang -->
      <div class="item-box">
        <img src="uploads/<?= e($data['foto_pakaian'] ?? ''); ?>"
             onerror="this.src='https://placehold.co/64x64?text=?'" alt="foto">
        <div>
          <div class="fw-bold"><?= e($data['jenis_pakaian']); ?>
            <?php if ($data['ukuran']): ?>&mdash; Ukuran <?= e($data['ukuran']); ?><?php endif; ?>
          </div>
          <small class="text-muted">
            <i class="bi bi-person-fill me-1"></i>Dari: <strong><?= e($data['nama_pemberi']); ?></strong>
          </small><br>
          <small class="text-muted">
            <i class="bi bi-geo-alt me-1"></i><?= e($data['alamat_pemberi'] ?? '—'); ?>
          </small>
        </div>
      </div>

      <!-- Info Driver -->
      <?php if ($data['nama_driver']): ?>
      <div class="driver-card">
        <div class="driver-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="flex-grow-1">
          <div class="fw-bold"><?= e($data['nama_driver']); ?></div>
          <small class="text-muted">Driver Pengantaran</small>
        </div>
        <?php if (!empty($data['hp_driver'])): ?>
        <a href="tel:<?= e($data['hp_driver']); ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-telephone-fill me-1"></i>Hubungi
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="alert alert-warning border-0 rounded-3 small py-2 mb-3">
        <i class="bi bi-hourglass-split me-1"></i>Driver belum ditugaskan.
      </div>
      <?php endif; ?>

      <!-- Peta -->
      <div id="map"></div>
      <div class="route-info" id="routeInfo" style="display:none;">
        <i class="bi bi-signpost-2 fs-5 me-2"></i>
        <span id="routeText">Memperkirakan rute...</span>
      </div>

      <!-- Timeline -->
      <h6 class="fw-semibold mb-3 text-secondary"><i class="bi bi-list-check me-1"></i>Perjalanan Paket</h6>
      <div class="timeline" id="timeline-wrap">
        <?php foreach ($alur as $kunci => $step):
          $idx_step = array_search($kunci, $urutan_kunci);
          $state = $idx_step < $idx_aktif ? 'done' : ($idx_step === $idx_aktif ? 'aktif' : 'belum');
        ?>
        <div class="tl-step">
          <div class="tl-dot <?= $state; ?>">
            <i class="bi <?= $state === 'belum' ? 'bi-circle' : $step['icon']; ?>"></i>
          </div>
          <div class="tl-label <?= $state === 'belum' ? 'belum' : ''; ?>">
            <?= $step['label']; ?>
            <?php if ($state === 'aktif'): ?>
              <span class="badge bg-primary ms-1" style="font-size:.65rem;">SEKARANG</span>
            <?php endif; ?>
          </div>
          <?php if ($state === 'aktif' && $data['last_update']): ?>
          <div class="tl-sub">
            <i class="bi bi-clock me-1"></i>
            Diperbarui: <?= date('d M Y, H:i', strtotime($data['last_update'])); ?> WIB
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Alamat Tujuan -->
      <div class="d-flex align-items-start gap-2 mt-1 mb-3 p-2 bg-light rounded-3">
        <i class="bi bi-house-fill text-success mt-1"></i>
        <div>
          <div class="small fw-semibold">Alamat Pengiriman</div>
          <div class="small text-muted"><?= e($data['alamat_penerima'] ?? 'Belum diisi'); ?></div>
          <?php if ($latTujuan && $lngTujuan): ?>
            <a href="<?= $url_maps; ?>" target="_blank" class="btn btn-sm btn-link p-0 text-decoration-none">
              <i class="bi bi-geo-alt-fill me-1"></i>Buka di Google Maps
            </a>
          <?php endif; ?>
        </div>
      </div>

      <hr class="my-3">

      <!-- Konfirmasi -->
      <?php if ($sudah_diterima): ?>
        <div class="done-banner">
          <i class="bi bi-check-circle-fill fs-2 mb-2 d-block"></i>
          <h6 class="fw-bold mb-1">Barang Sudah Diterima!</h6>
          <p class="mb-0 small">Anda telah mengkonfirmasi penerimaan barang ini. Terima kasih!</p>
        </div>
      <?php elseif ($bisa_konfirmasi): ?>
        <div class="alert alert-success border-0 rounded-3 small mb-3">
          <i class="bi bi-truck-front-fill me-1"></i>
          <strong>Driver sudah tiba!</strong> Pastikan Anda sudah menerima barang sebelum mengkonfirmasi.
        </div>
        <a href="konfirmasi.terima.php?id=<?= $request_id; ?>&csrf=<?= generateCSRFToken(); ?>" class="btn-konfirmasi">
          <i class="bi bi-check-circle-fill me-2"></i>Konfirmasi Barang Diterima
        </a>
      <?php else: ?>
        <p class="text-center text-muted small mb-0">
          <i class="bi bi-info-circle me-1"></i>
          Tombol konfirmasi akan muncul setelah driver melaporkan sudah tiba di lokasi Anda.
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
<script>
// ════════════════════════════════════════════════
//  TRACKING REAL‑TIME + RUTE NAVIGASI
// ════════════════════════════════════════════════
const requestId = <?= (int)$_GET['id']; ?>;
const latTujuan = <?= json_encode($latTujuan); ?>;
const lngTujuan = <?= json_encode($lngTujuan); ?>;

// Inisialisasi peta
const map = L.map('map', {
    zoomControl: true,
    attributionControl: false
}).setView([latTujuan || -6.2, lngTujuan || 106.8], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);

// Marker tujuan (penerima)
const tujuanIcon = L.divIcon({
    className: '',
    html: '<div style="background:#059669; width:32px; height:32px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,.3);"><i class="bi bi-house-fill" style="color:white; font-size:16px; transform:rotate(45deg);"></i></div>',
    iconSize: [32, 32],
    iconAnchor: [16, 32]
});

const tujuanMarker = L.marker([latTujuan, lngTujuan], { icon: tujuanIcon }).addTo(map)
    .bindPopup('📍 Lokasi Penerima');

// Variabel untuk driver
let driverMarker = null;
let routeControl = null;
let lastStatus = '<?= $status_driver ?>';

// Ikon driver
const driverIcon = L.divIcon({
    className: '',
    html: '<div style="background:#4f46e5; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 10px rgba(79,70,229,.5); border:2px solid white;"><i class="bi bi-bicycle" style="color:white; font-size:20px;"></i></div>',
    iconSize: [40, 40],
    iconAnchor: [20, 20]
});

// Fungsi format jarak & waktu
function formatDistance(meters) {
    if (meters >= 1000) return (meters/1000).toFixed(1) + ' km';
    return meters + ' m';
}
function formatTime(seconds) {
    const mins = Math.round(seconds / 60);
    if (mins >= 60) {
        const h = Math.floor(mins/60);
        const m = mins % 60;
        return h + ' jam ' + m + ' menit';
    }
    return mins + ' menit';
}

// Update rute dan posisi driver
async function updateMap() {
    try {
        const res = await fetch(`api.get.lokasi.driver.php?request_id=${requestId}`);
        const data = await res.json();
        const infoBox = document.getElementById('routeInfo');
        const routeText = document.getElementById('routeText');

        if (data.error || !data.latitude || !data.longitude) {
            // Lokasi belum tersedia, tampilkan info
            if (infoBox) {
                infoBox.style.display = 'flex';
                routeText.textContent = 'Menunggu lokasi driver...';
            }
            return;
        }

        const driverPos = [data.latitude, data.longitude];

        // Update atau buat marker driver
        if (!driverMarker) {
            driverMarker = L.marker(driverPos, { icon: driverIcon }).addTo(map)
                .bindPopup('Driver');
        } else {
            driverMarker.setLatLng(driverPos);
        }

        // Hapus rute lama
        if (routeControl) {
            map.removeControl(routeControl);
        }

        // Buat rute baru
        routeControl = L.Routing.control({
            waypoints: [
                L.latLng(driverPos),
                L.latLng(latTujuan, lngTujuan)
            ],
            lineOptions: {
                styles: [{ color: '#4f46e5', weight: 5, opacity: 0.8 }]
            },
            createMarker: function() { return null; }, // jangan buat marker default
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            show: false // sembunyikan panel instruksi
        }).addTo(map);

        // Ambil data rute (waktu & jarak)
        routeControl.on('routesfound', function(e) {
            const route = e.routes[0];
            const dist = route.summary.totalDistance;
            const time = route.summary.totalTime;
            if (infoBox && routeText) {
                infoBox.style.display = 'flex';
                routeText.innerHTML = `🚀 Estimasi: <strong>${formatDistance(dist)}</strong> · <strong>${formatTime(time)}</strong>`;
            }
        });

        // Sesuaikan tampilan peta
        const bounds = L.latLngBounds([driverPos, [latTujuan, lngTujuan]]);
        map.fitBounds(bounds, { padding: [40, 40] });

    } catch (err) {
        console.error('Tracking error:', err);
    }
}

// Cek perubahan status (untuk update timeline)
async function checkStatus() {
    try {
        const res = await fetch(`api.cek.status.php?request_id=${requestId}`);
        const data = await res.json();
        if (data.status_pengantaran !== lastStatus) {
            lastStatus = data.status_pengantaran;
            location.reload();
        }
    } catch(e) {}
}

// Mulai
updateMap();
checkStatus();
setInterval(updateMap, 10000);
setInterval(checkStatus, 15000);
</script>
</body>
</html>