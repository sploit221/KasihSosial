<?php

include_once 'koneksi.php';
requireLogin();

$my_id   = (int)$_SESSION['user_id'];
$my_role = $_SESSION['role'] ?? '';

// Hanya penerima yang bisa mengakses halaman ini
if ($my_role !== 'penerima') {
    flash('error', 'Halaman ini hanya untuk penerima donasi.');
    header("Location: index.php");
    exit;
}

$request_id = (int)($_GET['request_id'] ?? $_GET['id'] ?? 0);
$error      = '';
$success    = false;

// Ambil data request donasi
$stmt = dbQuery(
    "SELECT dr.*, 
            p.jenis_pakaian, p.ukuran, p.kondisi, p.foto_pakaian,
            u.username AS nama_donatur, u.no_hp AS hp_donatur,
            d.username AS nama_driver, d.no_hp AS hp_driver,
            tp.status_pengantaran
     FROM donasi_request dr
     JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
     JOIN users u ON p.user_id = u.user_id
     LEFT JOIN tugas_pengantaran tp ON dr.request_id = tp.request_id
     LEFT JOIN users d ON tp.driver_id = d.user_id
     WHERE dr.request_id = ? AND dr.penerima_id = ?",
    'ii',
    [$request_id, $my_id]
);
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    flash('error', 'Data permintaan tidak ditemukan atau bukan milik Anda.');
    header("Location: pages/penerima/dashboard.penerima.php");
    exit;
}

// Status yang sudah tidak bisa dikonfirmasi
$status_selesai = ['Diterima', 'Dibatalkan', 'Ditolak'];
if (in_array($request['status'], $status_selesai)) {
    flash('info', 'Permintaan ini sudah selesai dan tidak bisa dikonfirmasi ulang.');
    header("Location: pages/penerima/dashboard.penerima.php");
    exit;
}

// Proses konfirmasi penerimaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi'])) {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid. Silakan muat ulang halaman.";
    } else {
        $catatan = sanitize($_POST['catatan'] ?? '', 500);

        // Update status request menjadi 'Diterima'
        dbQuery(
            "UPDATE donasi_request SET status = 'Diterima', tanggal_konfirmasi = NOW(), catatan_penerima = ? WHERE request_id = ?",
            'si',
            [$catatan, $request_id]
        );

        // Update status pakaian jika perlu
        dbQuery(
            "UPDATE pakaian SET status_ketersediaan = 'Sudah Donasi' WHERE pakaian_id = ?",
            'i',
            [$request['pakaian_id']]
        );

        // Update tugas pengantaran jika ada
        if ($request['request_id'] && $request['status_pengantaran']) {
            dbQuery(
                "UPDATE tugas_pengantaran SET status_pengantaran = 'Selesai', tanggal_selesai = NOW() WHERE request_id = ?",
                'i',
                [$request_id]
            );
        }

        flash('success', 'Terima kasih! Anda telah mengkonfirmasi penerimaan donasi. Semoga bermanfaat! 🙏');
        header("Location: dashboard.penerima.php");
        exit;
    }
}

// Proses laporan masalah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['laporkan'])) {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid.";
    } else {
        $alasan_laporan = sanitize($_POST['alasan_laporan'] ?? '', 500);

        if (empty($alasan_laporan)) {
            $error = "Mohon isi alasan pelaporan.";
        } else {
            dbQuery(
                "UPDATE donasi_request SET status = 'Bermasalah', catatan_penerima = ? WHERE request_id = ?",
                'si',
                ["[LAPORAN] " . $alasan_laporan, $request_id]
            );

            flash('warning', 'Laporan Anda telah dicatat. Tim kami akan menindaklanjuti.');
            header("Location: pages/penerima/dashboard.penerima.php");
            exit;
        }
    }
}

// Label status dengan warna
$status_labels = [
    'Pending'            => ['bg' => '#fef3c7', 'c' => '#92400e', 'icon' => 'clock', 'text' => 'Menunggu Persetujuan'],
    'Disetujui'          => ['bg' => '#dbeafe', 'c' => '#1e40af', 'icon' => 'check-circle', 'text' => 'Disetujui'],
    'Ditolak'            => ['bg' => '#fee2e2', 'c' => '#991b1b', 'icon' => 'x-circle', 'text' => 'Ditolak'],
    'Driver Ditugaskan'  => ['bg' => '#ede9fe', 'c' => '#6d28d9', 'icon' => 'truck', 'text' => 'Driver Dalam Perjalanan'],
    'Dalam Pengantaran'  => ['bg' => '#dbeafe', 'c' => '#1e40af', 'icon' => 'truck', 'text' => 'Sedang Diantar'],
    'Tiba di Tujuan'     => ['bg' => '#d1fae5', 'c' => '#065f46', 'icon' => 'geo-alt', 'text' => 'Tiba di Tujuan'],
    'Diterima'           => ['bg' => '#d1fae5', 'c' => '#065f46', 'icon' => 'heart', 'text' => 'Diterima'],
    'Bermasalah'         => ['bg' => '#fee2e2', 'c' => '#991b1b', 'icon' => 'exclamation-triangle', 'text' => 'Bermasalah'],
];

$current_status = $status_labels[$request['status']] ?? ['bg' => '#f3f4f6', 'c' => '#374151', 'icon' => 'info-circle', 'text' => $request['status']];
$bisa_konfirmasi = ($request['status'] === 'Tiba di Tujuan' || $request['status'] === 'Dalam Pengantaran');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Penerimaan — KasihSosial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --coral: #e85d4a;
            --teal: #0d9488;
            --green: #10b981;
            --cream: #fef7f0;
            --dark: #1a1a2e;
            --muted: #6b7280;
            --card-r: 18px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--cream);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--dark);
            min-height: 100vh;
        }

        .page-wrapper {
            max-width: 680px;
            margin: 0 auto;
            padding: 2rem 1.25rem 4rem;
        }

        /* ── Header ─────────────────────────── */
        .page-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(26, 26, 46, .08);
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            text-decoration: none;
            transition: all .2s;
            flex-shrink: 0;
        }

        .back-btn:hover {
            background: var(--dark);
            color: #fff;
            border-color: var(--dark);
        }

        .page-title {
            font-family: 'Fraunces', serif;
            font-size: 1.35rem;
            font-weight: 700;
        }

        /* ── Card utama ─────────────────────── */
        .main-card {
            background: #fff;
            border-radius: var(--card-r);
            box-shadow: 0 4px 24px rgba(26, 26, 46, .08);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header-img {
            height: 200px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .card-header-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: .85;
        }

        .card-header-img::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(26, 26, 46, .7), transparent 50%);
        }

        .card-header-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 2;
            background: rgba(255, 255, 255, .95);
            backdrop-filter: blur(6px);
            padding: .35rem .85rem;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .4rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* ── Info item ──────────────────────── */
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .85rem;
            border-radius: 12px;
            background: #f8fafc;
            margin-bottom: .65rem;
            font-size: .88rem;
            transition: background .2s;
        }

        .info-row:hover {
            background: #eff6ff;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1rem;
        }

        .info-label {
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
            margin-bottom: .1rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark);
        }

        /* ── Progress tracker ───────────────── */
        .progress-tracker {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 0;
            position: relative;
        }

        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e5e7eb;
            transform: translateY(-50%);
            z-index: 0;
        }

        .progress-step {
            position: relative;
            z-index: 1;
            text-align: center;
            flex: 1;
        }

        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fff;
            border: 2.5px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto .4rem;
            font-size: 1rem;
            transition: all .3s;
        }

        .step-dot.completed {
            background: var(--teal);
            border-color: var(--teal);
            color: #fff;
        }

        .step-dot.active {
            background: var(--coral);
            border-color: var(--coral);
            color: #fff;
            animation: pulse 1.5s ease infinite;
        }

        @keyframes pulse {
            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(232, 93, 74, .4);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(232, 93, 74, 0);
            }
        }

        .step-label {
            font-size: .68rem;
            font-weight: 600;
            color: var(--muted);
        }

        .step-label.active {
            color: var(--coral);
            font-weight: 700;
        }

        .step-label.completed {
            color: var(--teal);
        }

        /* ── Tombol aksi ────────────────────── */
        .btn-konfirmasi {
            display: block;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 6px 20px rgba(16, 185, 129, .3);
            margin-bottom: 1rem;
        }

        .btn-konfirmasi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(16, 185, 129, .4);
        }

        .btn-konfirmasi:disabled {
            background: #d1d5db;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-lapor {
            display: block;
            width: 100%;
            padding: .85rem;
            background: transparent;
            border: 1.5px solid #fca5a5;
            color: #dc2626;
            border-radius: 14px;
            font-weight: 600;
            font-size: .9rem;
            cursor: pointer;
            transition: all .2s;
            margin-bottom: 1rem;
        }

        .btn-lapor:hover {
            background: #fef2f2;
            border-color: #ef4444;
        }

        /* ── Alert ──────────────────────────── */
        .alert-flash {
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: .88rem;
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: 1rem;
        }

        /* ── Modal ──────────────────────────── */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
        }

        .modal-header {
            border-bottom: 1px solid #f3f4f6;
            padding: 1.25rem 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #f3f4f6;
            padding: 1rem 1.5rem;
        }

        /* ── Mobile ─────────────────────────── */
        @media (max-width: 576px) {
            .page-header {
                flex-wrap: wrap;
            }
            .info-row {
                flex-wrap: wrap;
            }
            .progress-tracker::before {
                left: 15%;
                right: 15%;
            }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">

        <!-- Header -->
        <div class="page-header">
            <a href="dashboard.penerima.php" class="back-btn">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <div class="page-title">Konfirmasi Penerimaan</div>
                <small style="color: var(--muted); font-size: .82rem;">
                    Pastikan barang sudah Anda terima dengan baik
                </small>
            </div>
        </div>

        <!-- Flash messages -->
        <?php if ($error): ?>
            <div class="alert-flash" style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span><?= e($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Card Utama -->
        <div class="main-card">
            <!-- Header dengan foto -->
            <div class="card-header-img">
                <?php if ($request['foto_pakaian']): ?>
                    <img src="uploads/<?= e($request['foto_pakaian']); ?>"
                        alt="<?= e($request['jenis_pakaian']); ?>"
                        onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="card-header-status"
                    style="color: <?= $current_status['c']; ?>; background: <?= $current_status['bg']; ?>;">
                    <i class="bi bi-<?= $current_status['icon']; ?>"></i>
                    <?= $current_status['text']; ?>
                </div>
            </div>

            <!-- Body -->
            <div class="card-body">
                <!-- Info Utama -->
                <div class="info-row">
                    <div class="info-icon" style="background: #dbeafe; color: #1e40af;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div>
                        <div class="info-label">Barang Donasi</div>
                        <div class="info-value"><?= e($request['jenis_pakaian']); ?></div>
                        <small style="color: var(--muted);">
                            Ukuran: <?= e($request['ukuran'] ?? '-'); ?> •
                            Kondisi: <?= e($request['kondisi'] ?? '-'); ?>
                        </small>
                    </div>
                </div>

                <!-- Info Donatur -->
                <div class="info-row">
                    <div class="info-icon" style="background: #fef3c7; color: #92400e;">
                        <i class="bi bi-gift-fill"></i>
                    </div>
                    <div>
                        <div class="info-label">Donatur</div>
                        <div class="info-value"><?= e($request['nama_donatur']); ?></div>
                        <?php if ($request['hp_donatur']): ?>
                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $request['hp_donatur']); ?>"
                                target="_blank"
                                class="text-success"
                                style="font-size: .8rem; text-decoration: none;">
                                <i class="bi bi-whatsapp me-1"></i>Hubungi Donatur
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Driver (jika ada) -->
                <?php if ($request['nama_driver']): ?>
                    <div class="info-row">
                        <div class="info-icon" style="background: #ede9fe; color: #6d28d9;">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div>
                            <div class="info-label">Driver Pengantar</div>
                            <div class="info-value"><?= e($request['nama_driver']); ?></div>
                            <?php if ($request['hp_driver']): ?>
                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $request['hp_driver']); ?>"
                                    target="_blank"
                                    class="text-success"
                                    style="font-size: .8rem; text-decoration: none;">
                                    <i class="bi bi-whatsapp me-1"></i>Hubungi Driver
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Progress Tracker -->
                <div class="progress-tracker mt-3 mb-3">
                    <?php
                    $steps = ['Disetujui', 'Driver Ditugaskan', 'Dalam Pengantaran', 'Tiba di Tujuan', 'Diterima'];
                    $current_idx = array_search($request['status'], $steps);
                    if ($current_idx === false) $current_idx = -1;
                    ?>
                    <?php foreach ($steps as $i => $step):
                        $completed = ($request['status'] === 'Diterima') ? true : ($i < $current_idx);
                        $active = ($i === $current_idx);
                    ?>
                        <div class="progress-step">
                            <div class="step-dot <?= $completed ? 'completed' : '' ?> <?= $active ? 'active' : '' ?>">
                                <?php if ($completed): ?>
                                    <i class="bi bi-check-lg"></i>
                                <?php elseif ($active): ?>
                                    <i class="bi bi-arrow-right"></i>
                                <?php else: ?>
                                    <i class="bi bi-dot"></i>
                                <?php endif; ?>
                            </div>
                            <div class="step-label <?= $active ? 'active' : '' ?> <?= $completed ? 'completed' : '' ?>">
                                <?= $step; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tombol Konfirmasi -->
                <?php if ($bisa_konfirmasi): ?>
                    <form method="POST" id="formKonfirmasi">
                        <?= csrfField(); ?>
                        <input type="hidden" name="konfirmasi" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: .85rem;">
                                Catatan (opsional)
                            </label>
                            <textarea name="catatan" class="form-control" rows="2"
                                placeholder="Tulis catatan untuk donatur, misalnya: Terima kasih, pakaiannya bagus!"
                                style="border-radius: 10px; border-color: #e5e7eb; font-size: .85rem;"
                                maxlength="500"></textarea>
                        </div>
                        <button type="submit" class="btn-konfirmasi"
                            onclick="return confirm('Apakah Anda yakin sudah menerima barang dengan baik?')">
                            <i class="bi bi-check-circle-fill me-2"></i>Konfirmasi Sudah Diterima
                        </button>
                    </form>

                    <!-- Tombol Lapor Masalah -->
                    <button type="button" class="btn-lapor" data-bs-toggle="modal" data-bs-target="#modalLapor">
                        <i class="bi bi-exclamation-triangle me-2"></i>Laporkan Masalah
                    </button>
                <?php else: ?>
                    <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-2" style="font-size:.85rem;">
                        <i class="bi bi-info-circle-fill"></i>
                        Konfirmasi hanya bisa dilakukan saat barang sudah tiba di tujuan.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tombol kembali -->
        <a href="dashboard.penerima.php" class="btn btn-outline-secondary rounded-pill w-100 py-2">
            <i class="bi bi-arrow-left me-2"></i>Kembali ke Dashboard
        </a>

    </div>

    <!-- Modal Lapor Masalah -->
    <div class="modal fade" id="modalLapor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Laporkan Masalah
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrfField(); ?>
                    <input type="hidden" name="laporkan" value="1">
                    <div class="modal-body">
                        <p style="font-size:.85rem; color: var(--muted); margin-bottom: 1rem;">
                            Laporkan jika ada masalah dengan donasi ini, misalnya: barang rusak, tidak sesuai, atau belum diterima.
                        </p>
                        <label class="form-label fw-semibold">Alasan Pelaporan <span class="text-danger">*</span></label>
                        <textarea name="alasan_laporan" class="form-control" rows="4"
                            placeholder="Jelaskan masalah yang Anda alami..."
                            style="border-radius: 10px; border-color: #e5e7eb;"
                            required maxlength="500"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger rounded-pill fw-bold">
                            <i class="bi bi-send me-1"></i>Kirim Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss flash messages
        document.querySelectorAll('.alert-flash').forEach(el => {
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transition = 'opacity .3s';
                setTimeout(() => el.remove(), 300);
            }, 4000);
        });
    </script>
</body>
</html>