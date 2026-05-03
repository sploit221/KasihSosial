<?php
/**
 * Menyiapkan pesan flash ke dalam session
 */
if (!function_exists('setFlash')) {
    function setFlash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type, 
            'message' => $message
        ];
    }
}

/**
 * Menampilkan pesan flash dalam format HTML Alert Bootstrap
 */
if (!function_exists('renderFlash')) {
    function renderFlash() {
        if (!isset($_SESSION['flash'])) return '';

        $flash = $_SESSION['flash'];
        $type  = $flash['type'];
        $msg   = htmlspecialchars($flash['message']);

        unset($_SESSION['flash']);

        return "
        <div class='alert alert-{$type} alert-dismissible fade show shadow-sm border-0' role='alert' style='border-radius: 12px;'>
            <div class='d-flex align-items-center gap-2'>
                <i class='bi " . ($type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') . "'></i>
                <div>{$msg}</div>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}