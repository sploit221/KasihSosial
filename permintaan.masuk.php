<?php

include 'koneksi.php';
requireLogin();

$my_id = (int)$_SESSION['user_id'];

// ─── Handle approval ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setujui'])) {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die("Token keamanan tidak valid.");
    }

    $id_request = intval($_POST['id_request'] ?? 0);

    if ($id_request > 0) {
        // BUG FIX: was raw string interpolation → now prepared statement
        $update = $conn->prepare(
            "UPDATE donasi_request SET status = 'Setuju' WHERE request_id = ?"
        );
        $update->bind_param("i", $id_request);

        if ($update->execute()) {
            $_SESSION['flash_success'] = "Permintaan disetujui! Silakan hubungi penerima via chat.";
        } else {
            $_SESSION['flash_error'] = "Gagal menyetujui permintaan.";
        }
        $update->close();
    }

    header("Location: permintaan.masuk.php");
    exit;
}

// ─── Fetch pending requests ───────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT dr.request_id, dr.pakaian_id, dr.penerima_id, dr.catatan_penerima,
            dr.lokasi_terkini, dr.tanggal_request,
            p.jenis_pakaian, p.foto_pakaian, p.ukuran,
            u.username AS nama_penerima, u.no_hp
     FROM donasi_request dr
     JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
     JOIN users u ON dr.penerima_id = u.user_id
     WHERE p.user_id = ? AND dr.status = 'Pending'
     ORDER BY dr.tanggal_request DESC"
);
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permintaan Masuk — KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .request-card {
            background: #fff;
            border: none;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            transition: box-shadow .2s;
        }
        .request-card:hover { box-shadow: 0 6px 20px rgba(79,70,229,.12); }

        .item-thumb { width: 60px; height: 60px; border-radius: 12px; object-fit: cover; border: 2px solid #e2e8f0; }
        .requester-name { font-weight: 700; color: #1e293b; }
        .item-name { color: #4f46e5; font-weight: 600; }
        .note-text { font-style: italic; color: #64748b; font-size: .875rem; }
        .loc-text { color: #dc2626; font-size: .8rem; }

        .btn-approve {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none; color: white;
            border-radius: 10px; font-weight: 700;
            padding: .5rem 1.25rem;
            transition: opacity .2s, transform .2s;
        }
        .btn-approve:hover { opacity: .9; transform: translateY(-1px); color: white; }

        .btn-chat-sm {
            background: #ede9fe; color: #5b21b6;
            border: none; border-radius: 10px;
            font-weight: 600; padding: .5rem 1rem;
            transition: background .2s;
        }
        .btn-chat-sm:hover { background: #ddd6fe; }

        .empty-state { text-align: center; padding: 4rem 2rem; color: #94a3b8; }
        .empty-state i { font-size: 3.5rem; display: block; margin-bottom: 1rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4 pb-5">

    <!-- Flash -->
    <?php foreach (['flash_success'=>'success','flash_error'=>'danger'] as $key=>$cls):
        if (!empty($_SESSION[$key])): ?>
        <div class="alert alert-<?= $cls; ?> alert-dismissible rounded-3 border-0 shadow-sm d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-<?= $cls==='success'?'check-circle':'exclamation-circle'; ?>-fill"></i>
            <?= e($_SESSION[$key]); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php unset($_SESSION[$key]); endif; endforeach; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-0" style="color:#1e293b;">Permintaan Masuk</h2>
            <p class="text-muted small mb-0">
                <?= count($requests); ?> permintaan pending menunggu konfirmasi Anda
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <?php if (empty($requests)): ?>
    <div class="empty-state bg-white rounded-4 shadow-sm">
        <i class="bi bi-inbox"></i>
        <h5 class="fw-bold">Tidak ada permintaan pending</h5>
        <p class="mb-0">Saat ada yang meminta barang Anda, notifikasi akan muncul di sini.</p>
    </div>
    <?php else: ?>
        <?php foreach ($requests as $row): ?>
        <div class="request-card d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-start gap-3">
                <img src="uploads/<?= e($row['foto_pakaian']); ?>"
                     class="item-thumb"
                     alt="<?= e($row['jenis_pakaian']); ?>"
                     onerror="this.src='uploads/default.jpg'">
                <div>
                    <p class="mb-1">
                        <span class="requester-name"><?= e($row['nama_penerima']); ?></span>
                        <span class="text-muted fw-normal"> meminta </span>
                        <span class="item-name"><?= e($row['jenis_pakaian']); ?>
                            <?php if ($row['ukuran']): ?>
                            <span class="badge bg-light text-dark border" style="font-size:.7rem;"><?= e($row['ukuran']); ?></span>
                            <?php endif; ?>
                        </span>
                    </p>
                    <?php if (!empty($row['catatan_penerima'])): ?>
                    <p class="note-text mb-1">
                        <i class="bi bi-chat-quote me-1"></i>"<?= e($row['catatan_penerima']); ?>"
                    </p>
                    <?php endif; ?>
                    <p class="loc-text mb-1">
                        <i class="bi bi-geo-alt-fill me-1"></i><?= e($row['lokasi_terkini'] ?? 'Lokasi tidak dicantumkan'); ?>
                    </p>
                    <p class="text-muted mb-0" style="font-size:.78rem;">
                        <i class="bi bi-clock me-1"></i>
                        <?= date('d M Y, H:i', strtotime($row['tanggal_request'] ?? 'now')); ?>
                    </p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <!-- BUG FIX: Added CSRF token -->
                <form method="POST" action=""
                      onsubmit="return confirm('Setujui permintaan dari <?= e($row['nama_penerima']); ?>?')">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                    <input type="hidden" name="id_request" value="<?= (int)$row['request_id']; ?>">
                    <button type="submit" name="setujui" class="btn-approve">
                        <i class="bi bi-check-lg me-1"></i>Setujui
                    </button>
                </form>

                <a href="chat.donasi.php?pakaian_id=<?= (int)$row['pakaian_id']; ?>&amp;penerima_id=<?= (int)$row['penerima_id']; ?>"
                   class="btn-chat-sm">
                    <i class="bi bi-chat-dots me-1"></i>Chat
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>