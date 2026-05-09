<?php
include_once 'koneksi.php';
requireRole('penerima'); // Redirect otomatis ke login.php jika bukan penerima

$penerima_id = (int)$_SESSION['user_id'];

// Query donasi aktif milik penerima ini
$stmt = dbQuery(
    "SELECT dr.request_id, dr.status AS status_request,
            p.jenis_pakaian,
            u_pemberi.username AS nama_pemberi,
            tp.status_pengantaran, tp.tugas_id,
            dr.tanggal_request
     FROM donasi_request dr
     JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
     JOIN users u_pemberi ON p.user_id = u_pemberi.user_id
     LEFT JOIN tugas_pengantaran tp ON dr.request_id = tp.request_id
     WHERE dr.penerima_id = ?
     ORDER BY dr.request_id DESC",
    'i', [$penerima_id]
);
$result = $stmt->get_result();

// Warna badge berdasarkan status pengantaran
function badgeColor($status) {
    return match($status) {
        'Menuju Penjemputan' => 'warning text-dark',
        'Barang Diambil'     => 'primary',
        'Dalam Perjalanan'   => 'info text-dark',
        'Tiba di Tujuan'     => 'success',
        default              => 'secondary',
    };
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Penerima - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6f9; }

        /* ── Banner notifikasi driver tiba ─────────────── */
        .arrival-banner {
            display: none;
            background: linear-gradient(135deg, #065f46, #0d9488);
            color: #fff; border-radius: 16px;
            padding: 1.1rem 1.4rem;
            margin-bottom: 1.5rem;
            animation: slideDown .5s ease;
            box-shadow: 0 8px 25px rgba(5,150,105,.35);
        }
        .arrival-banner.show { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .arrival-icon {
            font-size: 2.5rem;
            animation: ring 1s ease infinite alternate;
        }
        @keyframes ring {
            from { transform: rotate(-15deg); }
            to   { transform: rotate(15deg); }
        }
        .btn-konfirmasi-cepat {
            background: #fff; color: #065f46;
            border: none; border-radius: 10px;
            padding: .55rem 1.1rem; font-weight: 700;
            font-size: .875rem; cursor: pointer;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-konfirmasi-cepat:hover { background: #d1fae5; color: #065f46; }
        .btn-wa-driver {
            background: #25d366; color: #fff;
            border: none; border-radius: 10px;
            padding: .55rem 1.1rem; font-weight: 700;
            font-size: .875rem; cursor: pointer;
            white-space: nowrap; text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem;
        }
        .btn-wa-driver:hover { background: #128c7e; color: #fff; }

        /* ── Polling countdown ─────────────────────────── */
        .poll-indicator {
            font-size: .7rem; color: #9ca3af;
            display: flex; align-items: center; gap: .3rem;
        }
        .poll-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: #10b981;
            animation: blink 1.5s ease infinite;
        }
        @keyframes blink {
            0%,100% { opacity: 1; } 50% { opacity: .2; }
        }

        /* ── Row highlight ketika driver tiba ─────────── */
        tr.row-tiba { background: #f0fdf4 !important; }
        tr.row-tiba td { border-left: 3px solid #10b981; }
    </style>
</head>
<body class="bg-light">


<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4">
    <a class="navbar-brand fw-bold" href="index.php">
        <i class="bi bi-heart-fill text-danger me-2"></i>KasihSosial
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="text-white small">
            <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['username']); ?>
        </span>
        <a href="index.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-grid me-1"></i>Katalog
        </a>
        
        <a href="logout.php" class="btn btn-sm btn-danger"
           onclick="return confirm('Yakin ingin keluar?')">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">

            <?= renderFlash(); ?>

            <!-- ── Banner Driver Tiba (ditampilkan via JS polling) ── -->
            <div class="arrival-banner" id="arrivalBanner">
                <div class="arrival-icon">🔔</div>
                <div class="flex-grow-1">
                    <div class="fw-bold" style="font-size:1rem;">
                        Driver Sudah Tiba di Lokasi Anda!
                    </div>
                    <div style="font-size:.82rem; opacity:.85;" id="arrivalDriverName">
                        Segera konfirmasi bahwa barang sudah Anda terima.
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="#" id="btnKonfirmasiBanner" class="btn-konfirmasi-cepat">
                        <i class="bi bi-check-circle-fill"></i>Konfirmasi Diterima
                    </a>
                    <a href="#" id="btnWaDriver" class="btn-wa-driver" target="_blank"
                       style="display:none;">
                        <i class="bi bi-whatsapp"></i>Hubungi Driver
                    </a>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-0">
                        <i class="bi bi-bag-heart me-2 text-primary"></i>Barang yang Saya Minta
                    </h3>
                    <div class="poll-indicator mt-1">
                        <div class="poll-dot"></div>
                        <span>Status diperbarui otomatis · refresh dalam <span id="pollCountdown">15</span>s</span>
                    </div>
                </div>
                <a href="index.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Minta Barang
                </a>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive bg-white p-0 shadow-sm rounded overflow-hidden">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Barang</th>
                                <th>Pemberi</th>
                                <th>Status Pengiriman</th>
                                <th class="text-center pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()):
                                $status_req    = $row['status_request']     ?? 'Pending';
                                $status_driver = $row['status_pengantaran'] ?? null;
                                $label_tampil  = $status_driver ?? $status_req;
                                $is_tiba       = ($status_req === 'Tiba di Tujuan');
                            ?>
                                <tr class="<?= $is_tiba ? 'row-tiba' : ''; ?>"
                                    data-request-id="<?= (int)$row['request_id']; ?>"
                                    data-status="<?= e($status_req); ?>">
                                    <td class="ps-4">
                                        <strong><?= e($row['jenis_pakaian']); ?></strong>
                                        <small class="d-block text-muted">ID #<?= (int)$row['request_id']; ?></small>
                                    </td>
                                    <td><?= e($row['nama_pemberi']); ?></td>
                                    <td>
                                        <span class="badge bg-<?= badgeColor($label_tampil); ?>">
                                            <?= e($label_tampil); ?>
                                        </span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <?php if ($status_req === 'Tiba di Tujuan'): ?>
                                            <form action="konfirmasi.terima.php" method="POST"
                                                  onsubmit="return confirm('Apakah barang sudah benar-benar diterima?')">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                                                <input type="hidden" name="tugas_id"   value="<?= (int)$row['tugas_id']; ?>">
                                                <input type="hidden" name="request_id" value="<?= (int)$row['request_id']; ?>">
                                                <button type="submit" name="konfirmasi_terima"
                                                        class="btn btn-success btn-sm fw-bold">
                                                    <i class="bi bi-check2-circle me-1"></i>Konfirmasi Diterima
                                                </button>
                                            </form>
                                        <?php elseif ($status_req === 'Diterima'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle-fill me-1"></i>Sudah Diterima
                                            </span>
                                        <?php elseif (!empty($row['tugas_id'])): ?>
                                            <?php
                                            $tugasInfo = dbQuery(
                                                "SELECT status_pembayaran, metode_pembayaran FROM tugas_pengantaran WHERE tugas_id = ?",
                                                'i', [$row['tugas_id']]
                                            )->get_result()->fetch_assoc();
                                            $status_bayar = $tugasInfo['status_pembayaran'] ?? '';
                                            $metode_bayar = $tugasInfo['metode_pembayaran'] ?? '';

                                            // Tombol Bayar hanya jika belum dibayar DAN metode bukan COD
                                            $tampilBayar = ($status_bayar === 'belum_dibayar' && $metode_bayar !== 'cod');
                                            ?>
                                            <?php if ($tampilBayar): ?>
                                                <a href="pilih.layanan.php?request_id=<?= (int)$row['request_id']; ?>"
                                                   class="btn btn-warning btn-sm fw-bold">
                                                    <i class="bi bi-cash-coin me-1"></i>Bayar Ongkir
                                                </a>
                                            <?php elseif ($metode_bayar === 'cod' && $status_bayar === 'belum_dibayar'): ?>
                                                <span class="badge bg-info">COD - Bayar di Tempat</span>
                                            <?php else: ?>
                                                <a href="tracking.penerima.php?id=<?= (int)$row['request_id']; ?>"
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-radar me-1"></i>Lacak Driver
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="bi bi-hourglass-split me-1"></i>Menunggu
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-bag-x fs-1 text-muted d-block mb-3"></i>
                        <h5 class="text-muted">Belum ada permintaan barang yang aktif.</h5>
                        <p class="text-muted small">Temukan pakaian yang tersedia di katalog dan ajukan permintaan.</p>
                        <a href="index.php" class="btn btn-primary rounded-pill px-4 mt-2">
                             <i class="bi bi-search me-2"></i>Lihat Katalog
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Polling Status Driver ─────────────────────────────────────────────────────
// Kumpulkan semua request_id yang ada di halaman ini
const allRows = document.querySelectorAll('tr[data-request-id]');
const requestIds = [...new Set([...allRows].map(r => r.dataset.requestId).filter(Boolean))];

// Cari request yang statusnya belum selesai (perlu di-poll)
function getPendingRequests() {
    return [...allRows]
        .filter(r => !['Diterima'].includes(r.dataset.status))
        .map(r => r.dataset.requestId);
}

let pollInterval = null;
let countdown    = 15;
const countEl    = document.getElementById('pollCountdown');

function updateCountdown() {
    if (countEl) countEl.textContent = countdown;
    countdown--;
    if (countdown < 0) {
        countdown = 15;
        pollAll();
    }
}

function pollAll() {
    const pending = getPendingRequests();
    if (pending.length === 0) return; // semua sudah selesai, stop poll

    pending.forEach(reqId => {
        fetch(`api_cek_status.php?request_id=${reqId}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) return;
                handleStatusUpdate(reqId, data);
            })
            .catch(() => {}); // diam jika gagal
    });
}

function handleStatusUpdate(reqId, data) {
    const row = document.querySelector(`tr[data-request-id="${reqId}"]`);
    if (!row) return;

    const prevStatus = row.dataset.status;

    // Update data attribute
    row.dataset.status = data.status_request;

    // ── Driver baru saja tiba (transisi ke 'Tiba di Tujuan') ──────────────

    if (data.driver_tiba && navigator.vibrate) {
  navigator.vibrate([300, 100, 300]); // getar 2x
}

    if (data.driver_tiba && prevStatus !== 'Tiba di Tujuan') {
        tampilkanBannerTiba(reqId, data);
        row.classList.add('row-tiba');

        // Update badge di tabel
        const badgeEl = row.querySelector('.badge');
        if (badgeEl) {
            badgeEl.className = 'badge bg-success';
            badgeEl.textContent = 'Tiba di Tujuan';
        }

        // Tampilkan tombol konfirmasi di tabel
        const aksiTd = row.querySelector('td:last-child');
        if (aksiTd) {
            aksiTd.innerHTML = `
                <a href="konfirmasi_terima.php?id=${reqId}"
                   class="btn btn-success btn-sm fw-bold animate__animated animate__pulse">
                    <i class="bi bi-check2-circle me-1"></i>Konfirmasi Diterima
                </a>`;
        }

        // Bunyi notifikasi (tanpa library)
        playBeep();
    }

    // ── Sudah diterima ────────────────────────────────────────────────────
    if (data.sudah_diterima && prevStatus !== 'Diterima') {
        const aksiTd = row.querySelector('td:last-child');
        if (aksiTd) {
            aksiTd.innerHTML = `<span class="badge bg-success">
                <i class="bi bi-check-circle-fill me-1"></i>Sudah Diterima</span>`;
        }
        // Sembunyikan banner jika ada
        const banner = document.getElementById('arrivalBanner');
        if (banner) banner.classList.remove('show');
    }
}

function tampilkanBannerTiba(reqId, data) {
    const banner  = document.getElementById('arrivalBanner');
    const nameEl  = document.getElementById('arrivalDriverName');
    const btnKonf = document.getElementById('btnKonfirmasiBanner');
    const btnWa   = document.getElementById('btnWaDriver');

    if (!banner) return;

    // Update konten banner
    if (nameEl && data.nama_driver) {
        nameEl.textContent = `Driver ${data.nama_driver} sudah tiba. Segera konfirmasi penerimaan barang.`;
    }
    if (btnKonf) {
        btnKonf.href = `konfirmasi_terima.php?id=${reqId}`;
    }
    if (btnWa && data.hp_driver_wa) {
        const msg = encodeURIComponent('Halo, saya sudah melihat notifikasi. Saya akan konfirmasi penerimaan barang sekarang.');
        btnWa.href = `https://wa.me/${data.hp_driver_wa}?text=${msg}`;
        btnWa.style.display = 'inline-flex';
    }

    banner.classList.add('show');

    // Scroll ke banner
    banner.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Bunyi notifikasi sederhana via Web Audio API (tanpa file .mp3)
function playBeep() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [0, 200, 400].forEach(delay => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            osc.type = 'sine';
            gain.gain.setValueAtTime(0.3, ctx.currentTime + delay / 1000);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay / 1000 + 0.3);
            osc.start(ctx.currentTime + delay / 1000);
            osc.stop(ctx.currentTime + delay / 1000 + 0.3);
        });
    } catch(e) { /* browser tidak support */ }
}

// Mulai polling hanya jika ada request yang belum selesai
if (getPendingRequests().length > 0) {
    pollInterval = setInterval(updateCountdown, 1000);
    pollAll(); // langsung poll sekali saat halaman pertama dibuka
}

// Cek apakah ada yang sudah 'Tiba di Tujuan' saat halaman dimuat
// (kalau user buka halaman dan driver sudah tiba, langsung tampilkan banner)
allRows.forEach(row => {
    if (row.dataset.status === 'Tiba di Tujuan') {
        const reqId = row.dataset.requestId;
        // Langsung fetch detail untuk isi nama driver & no WA
        fetch(`api_cek_status.php?request_id=${reqId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.error && data.driver_tiba) {
                    tampilkanBannerTiba(reqId, data);
                    row.classList.add('row-tiba');
                }
            }).catch(() => {});
    }
});
</script>
</body>
</html>