<?php

include_once 'koneksi.php';
requireLogin(); // Harus login (semua role bisa, tapi di-filter query)

$my_id      = (int)$_SESSION['user_id'];
$request_id = validateId($_GET['id'] ?? 0);

if (!$request_id) {
    flash('error', 'ID permintaan tidak valid.');
    header("Location: dashboard.penerima.php"); exit;
}

// Ambil data request + tugas + driver — pastikan milik penerima ini
$stmt = dbQuery(
    "SELECT
        dr.request_id,
        dr.status                        AS status_request,
        dr.lokasi_terkini                AS alamat_penerima,
        dr.latitude                      AS lat_tujuan,  
        dr.longitude                     AS lng_tujuan, // 
        tp.tugas_id,
        tp.status_pengantaran,
        tp.updated_at                    AS last_update,
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

$latTujuan = $data['lat_tujuan'] ?? 0;
$lngTujuan = $data['lng_tujuan'] ?? 0;
$url_maps = "https://www.google.com/maps/dir/?api=1&destination=" . $latTujuan . "," . $lngTujuan . "&travelmode=driving";

// ── Tentukan langkah-langkah status ──────────────────────────────────────────
// Urutan tampilan di timeline (dari sisi penerima)
$alur = [
    'Pending'             => ['label' => 'Menunggu Driver',      'icon' => 'bi-hourglass-split',    'color' => 'warning'],
    'Menuju Penjemputan'  => ['label' => 'Driver Berangkat',     'icon' => 'bi-truck',               'color' => 'info'],
    'Barang Diambil'      => ['label' => 'Barang Diambil',       'icon' => 'bi-bag-check-fill',      'color' => 'info'],
    'Dalam Perjalanan'    => ['label' => 'Dalam Perjalanan',     'icon' => 'bi-arrow-right-circle-fill', 'color' => 'primary'],
    'Tiba di Tujuan'      => ['label' => 'Tiba di Tujuan',       'icon' => 'bi-geo-alt-fill',        'color' => 'success'],
    'Selesai'             => ['label' => 'Selesai',              'icon' => 'bi-check-circle-fill',   'color' => 'success'],
];

// Status aktual dari DB
$status_driver  = $data['status_pengantaran'] ?? 'Pending';
$status_request = $data['status_request'];

// Indeks urutan untuk menandai step yang sudah terlewati
$urutan_kunci = array_keys($alur);
$status_driver = $data['status_pengantaran'] ?? 'Pending';
$idx_aktif    = array_search($status_driver, $urutan_kunci);
if ($idx_aktif === false) $idx_aktif = 0;

// Apakah bisa konfirmasi? Hanya ketika donasi_request.status = 'Tiba di Tujuan'
$bisa_konfirmasi = ($status_request === 'Tiba di Tujuan');
$sudah_diterima  = ($status_request === 'Diterima');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lacak Pengiriman — KasihSosial</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
  <style>
    body { background: #f0f4f8; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }

    /* ── Navbar tipis ── */
    .top-bar {
      background: linear-gradient(135deg, #1e293b, #0f172a);
      color: #fff; padding: .75rem 1.5rem;
      display: flex; align-items: center; justify-content: space-between;
    }

    /* ── Kartu utama ── */
    .main-card {
      max-width: 680px; margin: 2rem auto; padding: 0 1rem;
    }
    .card { border: none; border-radius: 20px; box-shadow: 0 6px 30px rgba(0,0,0,.08); }
    .card-header-custom {
      background: linear-gradient(135deg, #4f46e5, #0891b2);
      color: #fff; border-radius: 20px 20px 0 0 !important;
      padding: 1.25rem 1.5rem;
    }

    /* ── Info barang ── */
    .item-box {
      background: #f8fafc; border: 1px solid #e2e8f0;
      border-radius: 14px; padding: 1rem;
      display: flex; gap: 1rem; align-items: center; margin-bottom: 1.25rem;
    }
    .item-box img { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; }

    /* ── Timeline ── */
    .timeline { position: relative; padding-left: 2.5rem; }
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
      box-shadow: 0 2px 6px rgba(0,0,0,.15);
      transition: all .4s;
    }
    .tl-dot.done  { background: #10b981; color: #fff; }
    .tl-dot.aktif { background: #4f46e5; color: #fff; animation: pulse 1.5s infinite; }
    .tl-dot.belum { background: #e2e8f0; color: #94a3b8; }

    @keyframes pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(79,70,229,.4); }
      50%       { box-shadow: 0 0 0 8px rgba(79,70,229,0); }
    }

    .tl-label { font-weight: 600; color: #1e293b; font-size: .9rem; }
    .tl-label.belum { color: #94a3b8; font-weight: 400; }
    .tl-sub   { font-size: .78rem; color: #64748b; margin-top: .1rem; }

    /* ── Driver card ── */
    .driver-card {
      background: #eff6ff; border: 1px solid #bfdbfe;
      border-radius: 14px; padding: 1rem 1.25rem;
      display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;
    }
    .driver-avatar {
      width: 48px; height: 48px; border-radius: 50%;
      background: linear-gradient(135deg, #4f46e5, #0891b2);
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 1.25rem; flex-shrink: 0;
    }

    /* ── Tombol konfirmasi ── */
    .btn-konfirmasi {
      display: block; width: 100%; padding: .85rem;
      background: linear-gradient(135deg, #059669, #0d9488);
      color: #fff; font-weight: 700; font-size: 1rem;
      border: none; border-radius: 14px; cursor: pointer;
      text-decoration: none; text-align: center;
      box-shadow: 0 4px 15px rgba(5, 150, 105, .35);
      transition: opacity .2s;
    }
    .btn-konfirmasi:hover { opacity: .88; color: #fff; }

    /* ── Badge refresh ── */
    #refresh-info { font-size: .78rem; color: #94a3b8; }
    #countdown    { font-weight: 700; color: #4f46e5; }

    /* ── Sudah diterima ── */
    .done-banner {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      border: 1px solid #6ee7b7; border-radius: 14px;
      padding: 1.25rem; text-align: center; color: #065f46;
    }
  </style>
</head>
<body>

<!-- Top bar -->
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

<!-- Konten utama -->
<div class="main-card">

  <!-- Judul -->
  <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
    <h5 class="fw-bold mb-0">
      <i class="bi bi-radar me-2 text-primary"></i>Lacak Pengiriman #<?= $request_id; ?>
    </h5>
    <span id="refresh-info">Refresh dalam <span id="countdown">30</span>s</span>
  </div>

  <div class="card">
    <div class="card-header-custom">
      <h6 class="fw-bold mb-1"><i class="bi bi-box-seam me-2"></i>Detail Paket</h6>
      <small class="opacity-75">Status diperbarui secara otomatis setiap 30 detik</small>
    </div>
    <div class="card-body p-3">

      <!-- Info barang -->
      <div class="item-box">
        <img src="uploads/<?= e($data['foto_pakaian'] ?? ''); ?>"
             onerror="this.src='https://placehold.co/64x64?text=?'" alt="foto">
        <div>
          <div class="fw-bold"><?= e($data['jenis_pakaian']); ?>
            <?php if ($data['ukuran']): ?>
              &mdash; Ukuran <?= e($data['ukuran']); ?>
            <?php endif; ?>
          </div>
          <small class="text-muted">
            <i class="bi bi-person-fill me-1"></i>Dari: <strong><?= e($data['nama_pemberi']); ?></strong>
          </small><br>
          <small class="text-muted">
            <i class="bi bi-geo-alt me-1"></i><?= e($data['alamat_pemberi'] ?? '—'); ?>
          </small>
        </div>
      </div>

      <!-- Info driver (tampil jika sudah di-assign) -->
      <?php if ($data['nama_driver']): ?>
      <div class="driver-card" id="driver-info">
        <div class="driver-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="flex-grow-1">
          <div class="fw-bold"><?= e($data['nama_driver']); ?></div>
          <small class="text-muted">Driver Pengantaran</small>
        </div>

        <div id="map" style="height: 300px; border-radius: 15px; margin-bottom: 20px; z-index: 1;"></div>

        <?php if (!empty($data['telp_driver'])): ?>
        <a href="tel:<?= e($data['telp_driver']); ?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-telephone-fill me-1"></i><?= e($data['telp_driver']); ?>
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="alert alert-warning border-0 rounded-3 small py-2 mb-3">
        <i class="bi bi-hourglass-split me-1"></i>
        Driver belum ditugaskan. Admin akan segera menugaskan driver.
      </div>
      <?php endif; ?>

      <!-- ── Timeline Status ── -->
      <h6 class="fw-semibold mb-3 text-secondary">
        <i class="bi bi-list-check me-1"></i>Perjalanan Paket
      </h6>
      <div class="timeline" id="timeline-wrap">
        <?php foreach ($alur as $kunci => $step):
          $idx_step = array_search($kunci, $urutan_kunci);
          if ($idx_step < $idx_aktif)       $state = 'done';
          elseif ($idx_step === $idx_aktif)  $state = 'aktif';
          else                               $state = 'belum';
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

      <!-- Lokasi tujuan -->
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

      <!-- ── Area Konfirmasi ── -->
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
        <a href="konfirmasi.terima.php?id=<?= $request_id; ?>&csrf=<?= generateCSRFToken(); ?>"
           class="btn-konfirmasi">
          <i class="bi bi-check-circle-fill me-2"></i>Konfirmasi Barang Diterima
        </a>

      <?php else: ?>
        <p class="text-center text-muted small mb-0">
          <i class="bi bi-info-circle me-1"></i>
          Tombol konfirmasi akan muncul setelah driver melaporkan sudah tiba di lokasi Anda.
        </p>
      <?php endif; ?>

    </div><!-- /.card-body -->
  </div><!-- /.card -->

  <div class="text-center mt-3 mb-5">
    <a href="dashboard.penerima.php" class="text-muted small">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
    </a>
  </div>

</div><!-- /.main-card -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
<script>
// ── Auto-refresh halaman setiap 30 detik ─────────────────────────────────────
const requestId = <?= (int)$_GET['id']; ?>;
    
    // Data ini diambil dari database donasi_request yang dikelola admin/driver
    const latTujuan = <?= json_encode($data['lat_tujuan']); ?>; 
    const lngTujuan = <?= json_encode($data['lng_tujuan']); ?>;
    
    // Inisialisasi Peta (Default ke koordinat Batam jika data belum ada)
    const map = L.map('map').setView([1.1285, 104.0523], 13); 
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    // Buat Marker Driver/Barang
    let markerDriver = L.marker([1.1285, 104.0523]).addTo(map)
        .bindPopup('Posisi Barang Anda').openPopup();

    function updateTracking() {
      const requestId = <?= $_GET['id']; ?>;

        fetch(`api/api.get.lokasi.php?id=${requestId}`)
            .then(response => response.json())
            .then(data => {
                if (data.latitude && data.longitude) {
                    const newPos = [data.latitude, data.longitude];
                    
                    // Geser Marker ke posisi baru
                    markerDriver.setLatLng(newPos);
                    L.Routing.control({
                    waypoints: [
                        L.latLng(newPos), // Posisi Driver (dari API)
                        L.latLng(latTujuan, lngTujuan) // Posisi Penerima
                    ],
                    routeWhileDragging: false
                }).addTo(map);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Update posisi setiap 5 detik
    setInterval(updateTracking, 5000);
    updateTracking(); // Jalankan sekali saat start
</script>
</body>
</html>