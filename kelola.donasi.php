<?php 
include_once 'koneksi.php';
requireLogin();

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT * FROM pakaian WHERE user_id = ? ORDER BY tanggal_upload DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$items  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total  = count($items);
$avail  = array_reduce($items, fn($c,$r) => $c + ($r['status_ketersediaan']==='Tersedia' ? 1 : 0), 0);
$donated= $total - $avail;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Donasi Saya - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { background: #f1f5f9; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }

        .page-title { font-size: 1.6rem; font-weight: 800; color: #1e293b; }

        /* ── Stat cards ── */
        .stat-card {
            border: none; border-radius: 16px;
            padding: 1.25rem 1.5rem;
            display: flex; align-items: center; gap: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; }
        .stat-label { font-size: .8rem; color: #64748b; font-weight: 500; }

        /* ── Table card ── */
        .table-card {
            border: none; border-radius: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .table-card thead th {
            background: #f8fafc;
            font-size: .8rem; text-transform: uppercase;
            letter-spacing: .6px; color: #64748b;
            font-weight: 700; border: none;
            padding: 1rem 1.25rem;
        }
        .table-card tbody td {
            vertical-align: middle;
            padding: .9rem 1.25rem;
            border-color: #f1f5f9;
            font-size: .9rem;
        }
        .table-card tbody tr:hover { background: #f8fafc; }

        .item-thumb {
            width: 56px; height: 56px; object-fit: cover;
            border-radius: 12px; border: 2px solid #e2e8f0;
        }

        .status-pill {
            font-size: .75rem; font-weight: 700;
            padding: .35em .75em; border-radius: 20px;
        }

        .btn-edit {
            background: #fef3c7; color: #92400e;
            border: none; border-radius: 8px;
            padding: .35rem .75rem; font-size: .8rem; font-weight: 600;
            transition: background .2s;
        }
        .btn-edit:hover { background: #fde68a; }

        .btn-hapus {
            background: #fee2e2; color: #991b1b;
            border: none; border-radius: 8px;
            padding: .35rem .75rem; font-size: .8rem; font-weight: 600;
            transition: background .2s;
        }
        .btn-hapus:hover { background: #fecaca; }

        .empty-state { padding: 3.5rem 2rem; text-align: center; color: #94a3b8; }
        .empty-state i { font-size: 3.5rem; display: block; margin-bottom: 1rem; }
    </style>
</head>
<body>


<div class="container mt-4 pb-5">
    
    <?php foreach (['flash_success'=>'success','flash_error'=>'danger'] as $key=>$cls):
        if (!empty($_SESSION[$key])): ?>
        <div class="alert alert-<?= $cls; ?> alert-dismissible rounded-3 border-0 shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-<?= $cls==='success'?'check-circle':'exclamation-circle'; ?>-fill"></i>
            <?= e($_SESSION[$key]); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php unset($_SESSION[$key]); endif; endforeach; ?>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">Manajemen Donasi</h1>
            <p class="text-muted small mb-0">Kelola pakaian yang sudah Anda donasikan</p>
        </div>
        <div class="d-flex gap-2">
            <a href="upload.php" class="btn btn-primary fw-bold rounded-pill px-4">
                <i class="bi bi-plus-circle me-1"></i>Tambah Donasi
            </a>
            <a href="index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3">
                <i class="bi bi-grid me-1"></i>Katalog
            </a>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h5 class="fw-bold">Belum ada donasi</h5>
                <p class="mb-3">Mulai donasikan pakaian layak pakai Anda.</p>
                <a href="upload.php" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-plus-circle me-1"></i>Donasikan Sekarang
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Jenis & Ukuran</th>
                            <th>Kondisi</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td>
                                <img src="uploads/<?= e($row['foto_pakaian']); ?>"
                                     class="item-thumb"
                                     alt="<?= e($row['jenis_pakaian']); ?>" loading="lazy"
                                     onerror="this.onerror=null;this.src='https://placehold.co/56x56?text=?'">
                            </td>
                            <td>
                                <span class="fw-semibold text-capitalize"><?= e($row['nama_pakaian'] ?? $row['jenis_pakaian']); ?></span>
                                <br><span class="badge bg-light text-dark border" style="font-size:.72rem;"><?= e($row['ukuran'] ?? '-'); ?></span>
                            </td>
                            <td><?= e($row['kondisi'] ?? '-'); ?></td>
                            <td class="text-muted" style="font-size:.82rem;">
                                <?= date('d M Y', strtotime($row['tanggal_upload'])); ?>
                            </td>
                            <td>
                                <?php if ($row['status_ketersediaan'] === 'Tersedia'): ?>
                                    <span class="status-pill" style="background:#dcfce7;color:#166534;">Tersedia</span>
                                <?php else: ?>
                                    <span class="status-pill" style="background:#f1f5f9;color:#475569;">Terdonasi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="edit.donasi.php?id=<?= (int)$row['pakaian_id']; ?>"
                                       class="btn-edit">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    <form method="POST" action="hapus.donasi.php"
                                          onsubmit="return confirm('Hapus barang ini secara permanen?')">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                                        <input type="hidden" name="id" value="<?= (int)$row['pakaian_id']; ?>">
                                        <button type="submit" class="btn-hapus">
                                            <i class="bi bi-trash me-1"></i>Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>