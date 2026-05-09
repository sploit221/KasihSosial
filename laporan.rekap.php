<?php
include 'koneksi.php';

// Proteksi: Hanya Admin yang bisa melihat laporan
if ($_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// 1. Ambil Data Ringkasan (Statistik)
$stats = [];
$stats['total_pakaian'] = (int)($conn->query("SELECT COUNT(*) FROM pakaian")->fetch_row()[0] ?? 0);
$stats['total_donasi']  = (int)($conn->query("SELECT COUNT(*) FROM pakaian WHERE status_ketersediaan = 'Sudah Donasi'")->fetch_row()[0] ?? 0);
$stats['total_users']   = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetch_row()[0] ?? 0);
$stats['total_request'] = (int)($conn->query("SELECT COUNT(*) FROM donasi_request")->fetch_row()[0] ?? 0);
$stats['selesai']       = (int)($conn->query("SELECT COUNT(*) FROM donasi_request WHERE status = 'Selesai'")->fetch_row()[0] ?? 0);

$query = $conn->query(
    "SELECT p.pakaian_id, p.jenis_pakaian, p.ukuran, p.kondisi,
            p.status_ketersediaan, p.tanggal_upload, u.username
     FROM pakaian p
     JOIN users u ON p.user_id = u.user_id
     ORDER BY p.tanggal_upload DESC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Laporan - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary:#4f46e5; --accent:#0891b2; }
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }

        /* Admin nav */
        .admin-nav {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            box-shadow: 0 2px 16px rgba(30,27,75,.3);
        }

        /* Stat cards */
        .stat-card {
            border: none; border-radius: 18px;
            padding: 1.4rem 1.5rem;
            display: flex; align-items: center; gap: 1rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .stat-value { font-size: 2rem; font-weight: 800; line-height: 1; }
        .stat-label { font-size: .78rem; font-weight: 500; color: #64748b; margin-top: .2rem; }

        /* Table */
        .report-table-card {
            border: none; border-radius: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .report-table-card thead th {
            background: #f8fafc;
            font-size: .78rem; text-transform: uppercase;
            letter-spacing: .6px; color: #64748b; font-weight: 700;
            border: none; padding: .9rem 1rem;
        }
        .report-table-card tbody td {
            padding: .85rem 1rem;
            vertical-align: middle;
            border-color: #f1f5f9;
            font-size: .875rem;
        }
        .report-table-card tbody tr:hover { background: #fafbff; }

        /* Print styles */
        @media print {
            .d-print-none { display: none !important; }
            body { background: white; }
            .stat-card, .report-table-card { box-shadow: none; border: 1px solid #e5e7eb !important; }
            .admin-nav { background: #1e1b4b !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark admin-nav py-3">
    <div class="container">
        <a class="navbar-brand fw-bold" href="admin.dashboard.php">Admin KasihSosial</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="admin.dashboard.php">Dashboard</a>
            <a class="nav-link active" href="laporan.rekap.php">Laporan</a>
            <a class="nav-link text-danger" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-5">

 <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1" style="color:#1e293b;">
                <i class="bi bi-file-earmark-bar-graph-fill text-primary me-2"></i>Rekap Laporan Donasi
            </h2>
            <p class="text-muted mb-0">Diperbarui: <?= date('d F Y, H:i'); ?> WIB</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-primary rounded-pill d-print-none px-4">
            <i class="bi bi-printer me-1"></i>Cetak Laporan
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:white;">
                <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="stat-value text-dark"><?= $stats['total_pakaian']; ?></div>
                    <div class="stat-label">Total Pakaian Masuk</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:white;">
                <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-gift-fill"></i></div>
                <div>
                    <div class="stat-value" style="color:#16a34a;"><?= $stats['total_donasi']; ?></div>
                    <div class="stat-label">Berhasil Didonasikan</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:white;">
                <div class="stat-icon" style="background:#dbeafe;color:#2563eb;"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-value" style="color:#2563eb;"><?= $stats['total_users']; ?></div>
                    <div class="stat-label">Jumlah Pengguna</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:white;">
                <div class="stat-icon" style="background:#fef3c7;color:#b45309;"><i class="bi bi-hand-index-thumb-fill"></i></div>
                <div>
                    <div class="stat-value" style="color:#b45309;"><?= $stats['total_request']; ?></div>
                    <div class="stat-label">Total Permintaan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Completion rate bar -->
    <?php
    $pct = $stats['total_pakaian'] > 0
         ? round(($stats['total_donasi'] / $stats['total_pakaian']) * 100)
         : 0;
    ?>
    <div class="card border-0 rounded-4 shadow-sm p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold text-muted small">
                <i class="bi bi-bar-chart-line me-1"></i>Tingkat Keberhasilan Donasi
            </span>
            <span class="fw-bold text-primary"><?= $pct; ?>%</span>
        </div>
        <div class="progress rounded-pill" style="height:10px;">
            <div class="progress-bar bg-primary rounded-pill" style="width:<?= $pct; ?>%"></div>
        </div>
        <p class="text-muted small mt-2 mb-0">
            <?= $stats['total_donasi']; ?> dari <?= $stats['total_pakaian']; ?> pakaian telah berhasil didonasikan.
        </p>
    </div>

    <!-- Detail Table -->
    <div class="card report-table-card">
        <div class="card-header bg-white border-0 py-3 px-4">
            <h6 class="fw-bold mb-0">Daftar Pakaian &amp; Status Terkini</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Pakaian</th>
                            <th>Donatur</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $no = 1; while ($row = $query->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted"><?= $no++; ?></td>
                            <td class="text-muted" style="font-size:.8rem;">
                                <?= date('d M Y', strtotime($row['tanggal_upload'])); ?>
                            </td>
                            <td>
                                <!-- BUG FIX: was echo $row[] without e() — XSS risk -->
                                <span class="fw-semibold text-capitalize"><?= e($row['jenis_pakaian']); ?></span>
                                <span class="badge bg-light text-dark border ms-1" style="font-size:.7rem;"><?= e($row['ukuran'] ?? '-'); ?></span>
                            </td>
                            <td><?= e($row['username']); ?></td>
                            <td><?= e($row['kondisi'] ?? '-'); ?></td>
                            <td>
                                <?php if ($row['status_ketersediaan'] === 'Tersedia'): ?>
                                    <span class="badge rounded-pill" style="background:#dbeafe;color:#1e40af;font-size:.75rem;">Tersedia</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill" style="background:#dcfce7;color:#166534;font-size:.75rem;">Selesai Donasi</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>