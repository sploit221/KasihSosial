<?php

include_once 'koneksi.php';

requireLogin();

$my_id   = (int)$_SESSION['user_id'];
$my_role = $_SESSION['role'] ?? '';

// 1. Ambil data permintaan donasi
$stmt_req = dbQuery(
    "SELECT dr.request_id, dr.pakaian_id, dr.status,
            dr.catatan_penerima, dr.lokasi_terkini,
            dr.latitude  AS req_lat,
            dr.longitude AS req_lng,
            dr.tanggal_request,
            p.foto_pakaian, p.jenis_pakaian, p.ukuran,
            u.username AS nama_peminta, u.user_id AS peminta_id
     FROM donasi_request dr
     JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
     JOIN users   u ON dr.penerima_id = u.user_id
     WHERE p.user_id = ?
     ORDER BY dr.status ASC, dr.tanggal_request DESC",
    'i', [$my_id]
);
$result_req = $stmt_req->get_result();
$total_req_pending = 0;
$all_rows = [];
while ($r = $result_req->fetch_assoc()) {
    if ($r['status'] === 'Pending') $total_req_pending++;
    $all_rows[] = $r;
}

// 2. Ambil Data Chat Masuk
$stmt_chat_count = dbQuery(
    "SELECT COUNT(DISTINCT cd.pengirim_id) as n 
     FROM chat_donasi cd 
     JOIN pakaian p ON cd.pakaian_id = p.pakaian_id 
     WHERE p.user_id = ?", 
    'i', [$my_id]
);
$total_chat_masuk = (int)($stmt_chat_count->get_result()->fetch_assoc()['n'] ?? 0);

$stmt_chat_list = dbQuery(
    "SELECT cd.*, p.jenis_pakaian, u.username AS nama_pengirim
     FROM chat_donasi cd
     JOIN pakaian p ON cd.pakaian_id = p.pakaian_id
     JOIN users u ON cd.pengirim_id = u.user_id
     WHERE p.user_id = ?
     GROUP BY cd.pakaian_id, cd.pengirim_id",
    'i', [$my_id]
);
$result_chat = $stmt_chat_list->get_result();

// 3. Gabungkan untuk Notifikasi
$total_notifikasi_gabungan = $total_req_pending + $total_chat_masuk;

// 4. Statistik Cepat
$stat_total    = dbQuery("SELECT COUNT(*) n FROM pakaian WHERE user_id = ?", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
$stat_tersedia = dbQuery("SELECT COUNT(*) n FROM pakaian WHERE user_id = ? AND status_ketersediaan='Tersedia'", 'i', [$my_id])->get_result()->fetch_assoc()['n'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Donatur — KasihSosial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root { --coral: #e85d4a; --teal: #0d9488; --dark: #1a1a2e; }
        body { background: #f0f4f8; font-family: 'Plus Jakarta Sans', sans-serif; }
        .ks-navbar { background: var(--dark); padding: .75rem 1.5rem; }
        .ks-brand { font-family: 'Fraunces', serif; font-size: 1.3rem; color: #fff; text-decoration: none; }
        .ks-brand span { color: var(--coral); }
        .stat-card { background: #fff; border-radius: 14px; padding: 1.1rem 1.3rem; box-shadow: 0 2px 10px rgba(0,0,0,.05); display: flex; align-items: center; gap: .9rem; }
        .stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .stat-val { font-size: 1.6rem; font-weight: 800; line-height: 1; }
        .stat-lbl { font-size: .75rem; color: #6b7280; font-weight: 600; }
        .content-card { background: #fff; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.06); overflow: hidden; margin-bottom: 1.5rem; }
        .card-head { padding: .9rem 1.25rem; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; background: #fff; }
        .card-head .title { font-weight: 800; font-size: .95rem; }
        .req-thumb { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; border: 2px solid #e5e7eb; }
        .pill { font-size: .7rem; font-weight: 700; padding: .3em .8em; border-radius: 20px; }
        .pill-pending { background: #fef3c7; color: #92400e; }
        .pill-setuju { background: #d1fae5; color: #065f46; }
        .pill-ditolak { background: #fee2e2; color: #991b1b; }
        .table > thead > tr > th { font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; font-weight: 700; background: #fafafa; border-bottom: 2px solid #f3f4f6; }
        .table > tbody > tr > td { vertical-align: middle; font-size: .875rem; }
    </style>
</head>
<body>

<nav class="ks-navbar d-flex align-items-center justify-content-between text-white mb-4">
    <a href="index.php" class="ks-brand"><span>❤</span> KasihSosial</a>
    <div class="d-flex align-items-center gap-2">
        <a href="kelola.donasi.php" class="btn btn-sm btn-outline-light rounded-pill fw-semibold">
            <i class="bi bi-collection me-1"></i><span class="d-none d-sm-inline">Donasi Saya</span>
        </a>
        <a href="logout.php" class="btn btn-sm btn-danger rounded-pill fw-semibold" onclick="return confirm('Yakin keluar?')">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</nav>

<div class="container-fluid px-3 px-md-4 py-2" style="max-width:1200px;">
    <?= renderFlash(); ?>

    <!-- Baris Kartu Statistik -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede9fe;"><i class="bi bi-box-seam-fill" style="color:#4f46e5;"></i></div>
                <div>
                    <div class="stat-val" style="color:#4f46e5;"><?= $stat_total; ?></div>
                    <div class="stat-lbl">Barang Saya</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d1fae5;"><i class="bi bi-check-circle-fill" style="color:#059669;"></i></div>
                <div>
                    <div class="stat-val" style="color:#059669;"><?= $stat_tersedia; ?></div>
                    <div class="stat-lbl">Tersedia</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;"><i class="bi bi-bell-fill" style="color:#d97706;"></i></div>
                <div>
                    <div id="stat-pending-val" class="stat-val" style="color:#d97706;"><?= $total_notifikasi_gabungan; ?></div>
                    <div class="stat-lbl">Notifikasi Masuk</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#ccfbf1;"><i class="bi bi-chat-dots-fill" style="color:#0d9488;"></i></div>
                <div>
                    <div class="stat-val" style="color:#0d9488;"><?= $total_chat_masuk; ?></div>
                    <div class="stat-lbl">Pesan Chat</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Permintaan Masuk -->
    <div class="content-card mb-4">
        <div class="card-head">
            <span class="title"><i class="bi bi-inbox-fill me-2 text-primary"></i>Permintaan Donasi Masuk</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Barang</th><th>Peminta</th><th>Lokasi & Catatan</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if (count($all_rows) > 0): foreach ($all_rows as $row): 
                        $pill_cls = match($row['status']) {
                            'Pending'   => 'pill-pending',
                            'Disetujui' => 'pill-setuju',
                            'Ditolak'   => 'pill-ditolak',
                            default     => 'bg-secondary text-white',
                        };
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="uploads/<?= e($row['foto_pakaian']); ?>" class="req-thumb" onerror="this.src='https://placehold.co/48x48?text=?'">
                                <div>
                                    <div class="fw-semibold"><?= e($row['jenis_pakaian']); ?></div>
                                    <small class="text-muted"><?= e($row['ukuran'] ?? '-'); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="fw-semibold"><?= e($row['nama_peminta']); ?></span></td>
                        <td>
                            <p class="mb-1 small text-truncate" style="max-width:200px;"><?= e($row['catatan_penerima'] ?? '—'); ?></p>
                            <?php if (!empty($row['req_lat']) && !empty($row['req_lng'])): ?>
                                <a href="https://www.google.com/maps?q=<?= (float)$row['req_lat']; ?>,<?= (float)$row['req_lng']; ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:.72rem;">
                                    <i class="bi bi-geo-alt-fill me-1"></i>Maps
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.72rem;">Lokasi tidak diatur</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="pill <?= $pill_cls; ?>"><?= e($row['status']); ?></span></td>
                        <td>
                            <?php if ($row['status'] === 'Pending'): ?>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="konfirmasi.penerima.php?request_id=<?= (int)$row['request_id']; ?>&status=Disetujui" class="btn btn-sm btn-success fw-semibold" onclick="return confirm('Setujui permintaan ini?')">
                                        <i class="bi bi-check-lg"></i> Setuju
                                    </a>
                                    <a href="chat.donasi.php?pakaian_id=<?= (int)$row['pakaian_id']; ?>&penerima_id=<?= (int)$row['peminta_id']; ?>" class="btn btn-sm btn-primary fw-semibold">
                                        <i class="bi bi-chat-dots"></i> Chat
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada permintaan donasi yang masuk.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Daftar Pesan Chat Terbaru -->
    <div class="content-card">
        <div class="card-head">
            <span class="title"><i class="bi bi-chat-text-fill me-2 text-info"></i>Pesan Chat Terbaru</span>
        </div>
        <div>
            <?php if ($result_chat->num_rows > 0): while ($chat = $result_chat->fetch_assoc()): ?>
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= e($chat['nama_pengirim']); ?></strong> 
                        <span class="text-muted small ms-1">(<?= e($chat['jenis_pakaian']); ?>)</span><br>
                        <span class="text-muted small"><?= e(mb_substr($chat['isi_pesan'], 0, 80)); ?>...</span>
                    </div>
                    <a href="chat.donasi.php?pakaian_id=<?= (int)$chat['pakaian_id']; ?>&penerima_id=<?= (int)$chat['pengirim_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="bi bi-reply me-1"></i>Balas
                    </a>
                </div>
            <?php endwhile; else: ?>
                <div class="p-4 text-center text-muted">Belum ada chat yang masuk.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SCRIPT NOTIFIKASI & TOAST -->
<audio id="notif-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let lastPendingCount = <?= (int)$total_notifikasi_gabungan; ?>; 

    function checkNewRequests() {
        fetch('../../api/api.notif.donatur.php?t=' + Date.now())
            .then(response => response.json())
            .then(data => {
                if (data.error) return;
                const current = parseInt(data.pending_count) || 0;

                if (current > lastPendingCount) {
                    const diff = current - lastPendingCount;
                    showToast('🔔 Notifikasi Baru!', `Ada ${diff} pesan atau permintaan donasi baru.`, 'primary');
                    
                    const statEl = document.getElementById('stat-pending-val');
                    if (statEl) statEl.textContent = current;
                }
                lastPendingCount = current;
            })
            .catch(err => console.error("Polling Error:", err));
    }

    // Jalankan pengecekan setiap 10 detik
    setInterval(checkNewRequests, 10000);

    function showToast(title, msg, type) {
        // Mainkan suara notifikasi
        const sound = document.getElementById('notif-sound');
        if (sound) {
            sound.volume = 0.5;
            sound.play().catch(() => {});
        }

        // Tampilkan Toast UI
        const container = document.getElementById('toast-container');
        const id = 'toast-' + Date.now();
        const html = `
            <div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        <small>${msg}</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 6000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }
</script>

<?php include_once 'footer.php'; ?>
</body>
</html>