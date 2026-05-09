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
include 'koneksi.php';

// 3. Proteksi Halaman - Harus login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Silakan login terlebih dahulu.";
    header("Location: login.php");
    exit;
}

// 4. CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token. Silakan refresh halaman.");
        header("Location: dashboard.donatur.php");
        exit;
    }
}

// 5. Proses Konfirmasi
if (isset($_GET['id']) && isset($_GET['aksi']) && isset($_GET['token'])) {
    // Validasi CSRF token dari URL
    if (!verifyCSRFToken($_GET['token'])) {
        $_SESSION['flash_error'] = "Token tidak valid. Silakan coba lagi.";
        header("Location: dashboard.donatur.php");
        exit;
    }

    $request_id = validateId($_POST['id'] ?? 0);
    $aksi       = $_POST['aksi'] ?? '';
    $my_id      = (int)$_SESSION['user_id'];

    // Validasi input
    if (!$request_id || !in_array($aksi, ['setujui', 'tolak'])) {
        $_SESSION['flash_error'] = "Parameter tidak valid.";
        header("Location: dashboard.donatur.php");
        exit;
    }

    // Validasi: Pastikan barang ini milik donatur yang login
    $stmt_check = $conn->prepare(
        "SELECT dr.pakaian_id, dr.penerima_id 
         FROM donasi_request dr 
         JOIN pakaian p ON dr.pakaian_id = p.pakaian_id 
         WHERE dr.request_id = ? AND p.user_id = ? AND dr.status = 'Pending'"
    );
    $stmt_check->bind_param("ii", $request_id, $my_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $pakaian_id = (int)$data['pakaian_id'];
        $penerima_id = (int)$data['penerima_id'];

        // Mulai transaksi untuk data consistency
        $conn->begin_transaction();
        
        try {
            if ($aksi === 'setujui') {
                // Update status request
                $stmt1 = $conn->prepare("UPDATE donasi_request SET status = 'Disetujui' WHERE request_id = ?");
                $stmt1->bind_param("i", $request_id);
                $stmt1->execute();

                // Update status pakaian
                $stmt2 = $conn->prepare("UPDATE pakaian SET status_ketersediaan = 'Sudah Didonasikan' WHERE pakaian_id = ?");
                $stmt2->bind_param("i", $pakaian_id);
                $stmt2->execute();

                $conn->commit();
                $_SESSION['flash_success'] = "Permintaan berhasil disetujui!";
                
            } elseif ($aksi === 'tolak') {
                $stmt3 = $conn->prepare("UPDATE donasi_request SET status = 'Ditolak' WHERE request_id = ?");
                $stmt3->bind_param("i", $request_id);
                $stmt3->execute();
                
                $conn->commit();
                $_SESSION['flash_success'] = "Permintaan berhasil ditolak.";
            }

            header("Location: dashboard.donatur.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error konfirmasi donasi: " . $e->getMessage());
            $_SESSION['flash_error'] = "Terjadi kesalahan. Silakan coba lagi.";
            header("Location: dashboard.donatur.php");
            exit;
        }
    } else {
        $_SESSION['flash_error'] = "Data tidak ditemukan atau Anda tidak berwenang.";
        header("Location: dashboard.donatur.php");
        exit;
    }
    
    $stmt_check->close();
} else {
    $_SESSION['flash_error'] = "Akses tidak valid.";
    header("Location: dashboard.donatur.php");
    exit;
}
?>