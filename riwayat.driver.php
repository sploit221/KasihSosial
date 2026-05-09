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
include_once 'koneksi.php';

// 3. Proteksi Halaman - harus login sebagai driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    $_SESSION['flash_error'] = "Akses ditolak. Halaman ini khusus untuk driver.";
    header("Location: login.php");
    exit;
}

$driver_id = (int)$_SESSION['user_id'];
$nama_driver = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Driver';

// Fungsi helper untuk escape HTML
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// 4. Query untuk mengambil riwayat tugas yang sudah selesai
$query = "SELECT 
            tp.tugas_id, tp.status_pengantaran, tp.tanggal_tugas,
            p.jenis_pakaian, p.ukuran,
            p.lokasi_pengambilan AS lokasi_pemberi, 
            dr.lokasi_terkini AS alamat_penerima, 
            u_pemberi.username AS nama_pemberi, 
            u_penerima.username AS nama_penerima
          FROM tugas_pengantaran tp
          JOIN donasi_request dr ON tp.request_id = dr.request_id
          JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
          JOIN users u_pemberi ON p.user_id = u_pemberi.user_id
          JOIN users u_penerima ON dr.penerima_id = u_penerima.user_id
          WHERE tp.driver_id = ? AND tp.status_pengantaran = 'Selesai'
          ORDER BY tp.tanggal_tugas DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

// Hitung total tugas selesai
$total_selesai = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Tugas - KasihSosial Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { 
            --sidebar-bg: #1e293b;
            --primary: #4f46e5;
            --accent: #0891b2;
        }
        
        body { 
            background-color: #f8fafc; 
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
        }
        
        .sidebar { 
            min-height: 100vh; 
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #0f172a 100%);
            color: white;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-brand {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .nav-link { 
            color: #cbd5e1; 
            border-radius: 10px; 
            margin-bottom: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link:hover { 
            color: white; 
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }
        
        .nav-link.active { 
            color: white; 
            background: linear-gradient(135deg, var(--primary), var(--accent));
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .stats-card {
            border-radius: 15px;
            border: none;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .table-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table thead {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }

        .table thead th {
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
            transform: scale(1.01);
        }

        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            border-radius: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }

            .table-responsive {
                font-size: 0.9rem;
            }

            .stats-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
            <div class="sidebar-brand">
                <div class="d-flex align-items-center">
                    <i class="bi bi-truck fs-2 me-2"></i>
                    <div>
                        <h5 class="fw-bold mb-0">KasihSosial</h5>
                        <small class="opacity-75">Driver Panel</small>
                    </div>
                </div>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="driver.dashboard.php">
                        <i class="bi bi-grid-1x2-fill me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="riwayat.driver.php">
                        <i class="bi bi-clock-history me-2"></i> Riwayat Tugas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.driver.php">
                        <i class="bi bi-person-circle me-2"></i> Profile Saya
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i> Keluar
                    </a>
                </li>
            </ul>

            <div class="mt-4 pt-4 border-top border-secondary">
                <small class="text-muted d-block mb-2">Login sebagai:</small>
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle p-2 me-2">
                        <i class="bi bi-person-fill text-white"></i>
                    </div>
                    <strong class="text-white"><?= $nama_driver; ?></strong>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
                <div>
                    <h1 class="h2 fw-bold mb-1">Riwayat Pengantaran</h1>
                    <p class="text-muted mb-0">Daftar tugas yang sudah Anda selesaikan</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="driver.dashboard.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                </div>
            </div>

            <!-- Stats Card -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="mb-1 opacity-75">Total Selesai</p>
                                    <h2 class="fw-bold mb-0"><?= $total_selesai; ?></h2>
                                </div>
                                <i class="bi bi-check-circle-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card table-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">ID</th>
                                    <th>Tanggal</th>
                                    <th>Pakaian</th>
                                    <th>Dari (Pemberi)</th>
                                    <th>Ke (Penerima)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="badge bg-secondary">#<?= e($row['tugas_id']); ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?= date('d M Y', strtotime($row['tanggal_tugas'])); ?>
                                                </small><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?= date('H:i', strtotime($row['tanggal_tugas'])); ?> WIB
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?= e($row['jenis_pakaian']); ?></strong>
                                                <?php if (!empty($row['ukuran'])): ?>
                                                    <br><small class="text-muted">Ukuran: <?= e($row['ukuran']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong class="text-primary">
                                                    <i class="bi bi-person-fill me-1"></i><?= e($row['nama_pemberi']); ?>
                                                </strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-geo-alt-fill me-1"></i>
                                                    <?= e($row['lokasi_pemberi'] ?? 'Lokasi tidak tersedia'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong class="text-success">
                                                    <i class="bi bi-person-check-fill me-1"></i><?= e($row['nama_penerima']); ?>
                                                </strong><br>
                                                <small class="text-muted">
                                                    <i class="bi bi-geo-alt-fill me-1"></i>
                                                    <?= e($row['alamat_penerima'] ?? 'Lokasi tidak tersedia'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Selesai
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="border-0">
                                            <div class="empty-state">
                                                <i class="bi bi-inbox"></i>
                                                <h5 class="fw-bold text-secondary">Belum Ada Riwayat</h5>
                                                <p class="mb-0">Anda belum menyelesaikan tugas pengantaran apapun.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>