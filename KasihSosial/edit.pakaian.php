<?php
include_once 'koneksi.php';
requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: admin.dashboard.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pakaian WHERE pakaian_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("<div class='alert alert-danger m-4'>Data tidak ditemukan.</div>");
}

// Jika tombol Simpan diklik
if (isset($_POST['update'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        header("Location: edit_pakaian.php?id={$id}"); exit;
    }
    $jenis   = trim($_POST['jenis_pakaian']);
    $ukuran  = trim($_POST['ukuran']);
    $kondisi = trim($_POST['kondisi']);
    $status  = trim($_POST['status']);

    // Whitelist nilai yang boleh masuk
    $valid_jenis   = ['Atasan','Bawahan','Outerwear','Pakaian Anak'];
    $valid_kondisi = ['Sangat Layak','Seperti Baru','Layak Pakai'];
    $valid_status  = ['Tersedia','Sudah Donasi'];

    if (!in_array($jenis, $valid_jenis) || !in_array($kondisi, $valid_kondisi) || !in_array($status, $valid_status)) {
        $error = "Nilai input tidak valid.";
    } else {
        $update = $conn->prepare(
            "UPDATE pakaian SET jenis_pakaian=?, ukuran=?, kondisi=?, status_ketersediaan=?
             WHERE pakaian_id=?"
        );
        $update->bind_param("ssssi", $jenis, $ukuran, $kondisi, $status, $id);

        if ($update->execute()) {
            echo "<script>alert('Data berhasil diperbarui!'); window.location='admin.dashboard.php';</script>";
            exit;
        } else {
            $error = "Gagal menyimpan perubahan.";
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pakaian - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style> body { background: #f4f6f9; } </style>
</head>
<body>
<!-- Navbar Admin -->
<nav class="navbar navbar-dark px-4 py-2" style="background:#0f1923;">
  <a class="navbar-brand fw-bold" href="admin_dashboard.php">
    <span style="color:#e85d4a;">&#10084;</span> KasihSosial
    <small class="opacity-50 fw-normal ms-1" style="font-size:.7rem;">Admin</small>
  </a>
  <div class="d-flex gap-2">
    <a href="admin.dashboard.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
      <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
    <a href="logout.php" class="btn btn-sm btn-danger rounded-pill"
       onclick="return confirm('Yakin keluar?')">
      <i class="bi bi-box-arrow-right"></i>
    </a>
  </div>
</nav>
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark fw-bold py-3 d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square"></i>
                    Edit Detail Pakaian — Admin
                </div>

                <div class="card-body p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= e($error); ?></div>
                    <?php endif; ?>

                    <p class="text-muted small border-bottom pb-2 mb-3">
                        Mengubah data pakaian ID: <strong>#<?= $id; ?></strong>
                    </p>

                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Jenis Pakaian</label>
                            <select name="jenis_pakaian" class="form-select">
                                <?php foreach (['Atasan','Bawahan','Outerwear','Pakaian Anak'] as $j): ?>
                                    <option value="<?= $j; ?>" <?= $data['jenis_pakaian']===$j ? 'selected' : ''; ?>>
                                        <?= $j; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Ukuran</label>
                            <input type="text" name="ukuran" class="form-control"
                                   value="<?= e($data['ukuran']); ?>" required
                                   placeholder="Contoh: M, L, XL">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Kondisi</label>
                            <select name="kondisi" class="form-select">
                                <?php foreach (['Seperti Baru','Sangat Layak','Layak Pakai'] as $k): ?>
                                    <option value="<?= $k; ?>" <?= $data['kondisi']===$k ? 'selected' : ''; ?>>
                                        <?= $k; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Status Ketersediaan</label>
                            <select name="status" class="form-select">
                                <option value="Tersedia"    <?= $data['status_ketersediaan']==='Tersedia'    ? 'selected' : ''; ?>>Tersedia</option>
                                <option value="Sudah Donasi" <?= $data['status_ketersediaan']==='Sudah Donasi' ? 'selected' : ''; ?>>Sudah Donasi</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="admin.dashboard.php" class="btn btn-light w-50 border fw-bold">
                                <i class="bi bi-arrow-left me-1"></i>Batal
                            </a>
                            <button type="submit" name="update" class="btn btn-warning w-50 fw-bold">
                                <i class="bi bi-save me-1"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>