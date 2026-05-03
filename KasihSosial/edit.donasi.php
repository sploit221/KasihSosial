<?php
include_once 'koneksi.php';
requireLogin();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$user_id = (int)$_SESSION['user_id'];

if (!$id) {
    header("Location: kelola.donasi.php");
    exit;
}

// Ambil data dan pastikan ini milik user yang login
$stmt = $conn->prepare("SELECT * FROM pakaian WHERE pakaian_id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) { die("Data tidak ditemukan atau Anda tidak memiliki akses."); }

if (isset($_POST['update'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        header("Location: edit.donasi.php?id={$id}"); exit;
    }
    $jenis   = trim($_POST['jenis_pakaian']);
    $ukuran  = trim($_POST['ukuran']);
    $kondisi = trim($_POST['kondisi']);

    $update = $conn->prepare("UPDATE pakaian SET jenis_pakaian=?, ukuran=?, kondisi=? WHERE pakaian_id=? AND user_id=?");
    $update->bind_param("sssii", $jenis, $ukuran, $kondisi, $id, $user_id);
    
    if ($update->execute()) {
        echo "<script>alert('Data berhasil diperbarui!'); window.location='kelola.donasi.php';</script>";
        exit;
    } else {
        $error = "Gagal menyimpan perubahan. Silakan coba lagi.";
    }
    $update->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donasi - KasihSosial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style> body { background: #f4f6f9; } </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-dark px-4 py-2" style="background:#1a1a2e;">
  <a class="navbar-brand fw-bold" href="kelola.donasi.php">
    <span style="color:#e85d4a;">&#10084;</span> KasihSosial
  </a>
  <div class="d-flex gap-2">
    <a href="kelola.donasi.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
      <i class="bi bi-arrow-left me-1"></i>Donasi Saya
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
                <div class="card-header bg-warning text-dark fw-bold py-3">
                    <i class="bi bi-pencil-square me-2"></i>Edit Data Donasi Saya
                </div>

                <div class="card-body p-4">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= e($error); ?></div>
                    <?php endif; ?>

                    <p class="text-muted small border-bottom pb-2 mb-3">
                        Mengubah data barang ID: <strong>#<?= $id; ?></strong>
                    </p>

                    <!-- FIX: Form HTML ditambahkan — sebelumnya file ini tidak punya tampilan sama sekali -->
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Jenis Pakaian</label>
                            <select name="jenis_pakaian" class="form-select" required>
                                <?php
                                $jenis_list = ['Atasan','Bawahan','Outerwear','Pakaian Anak'];
                                foreach ($jenis_list as $j): ?>
                                    <option value="<?= $j; ?>"
                                        <?= $data['jenis_pakaian'] === $j ? 'selected' : ''; ?>>
                                        <?= $j; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Ukuran</label>
                            <select name="ukuran" class="form-select" required>
                                <?php foreach (['XS','S','M','L','XL','XXL','XXXL','All Size'] as $u): ?>
                                    <option value="<?= $u; ?>" <?= $data['ukuran'] === $u ? 'selected' : ''; ?>>
                                        <?= $u; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Kondisi Barang</label>
                            <select name="kondisi" class="form-select" required>
                                <?php foreach (['Seperti Baru','Sangat Layak','Layak Pakai'] as $k): ?>
                                    <option value="<?= $k; ?>" <?= $data['kondisi'] === $k ? 'selected' : ''; ?>>
                                        <?= $k; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <!-- FIX: Tambah tombol Batal yang sebelumnya tidak ada -->
                            <a href="kelola.donasi.php" class="btn btn-light w-50 border fw-bold">
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