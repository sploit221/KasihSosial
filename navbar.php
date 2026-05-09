<?php

include_once 'koneksi.php';

// ── Validasi session (anti session-fixation & brute-force) ────
$my_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$my_id = $my_id ?: null; // false/null → null

// ── Escape helper (XSS prevention) ───────────────────────────
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// ── Whitelist role (anti privilege-escalation) ────────────────
$allowed_roles = ['user', 'admin', 'driver', 'penerima'];
$raw_role      = $_SESSION['role'] ?? '';
$role          = in_array($raw_role, $allowed_roles, true) ? $raw_role : '';

// ── Sanitize username (XSS via session tampering) ─────────────
$nama_user = e($_SESSION['username'] ?? 'Guest');

// ── Notif count (prepared statement → anti SQL Injection) ─────
$total_notif = 0;
if ($my_id && $role === 'user') {

    // Pending donation requests untuk item milik donatur
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM donasi_request dr
         JOIN pakaian p ON dr.pakaian_id = p.pakaian_id
         WHERE p.user_id = ? AND dr.status = 'Pending'"
    );
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    $total_req = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Pesan chat yang belum dibaca
    $stmt2 = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM chat_donasi
         WHERE penerima_pesan_id = ?"
    );
    $stmt2->bind_param("i", $my_id);
    $stmt2->execute();
    $total_chat = (int)($stmt2->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt2->close();

    $total_notif = $total_req + $total_chat;
}

// ── Avatar initial (ambil dari raw session, bukan hasil escape) ──
$avatar_char = strtoupper(mb_substr(strip_tags($_SESSION['username'] ?? 'G'), 0, 1, 'UTF-8'));
?>

<style>
/* ─── Font ─── */
@import url('https://fonts.googleapis.com/css2?family=Fraunces:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ══════════════════════════════════════
   NAVBAR  —  Diperbesar & diperlebar
══════════════════════════════════════ */
#main-navbar {
    background: linear-gradient(135deg, #3730a3 0%, #4f46e5 42%, #0891b2 100%);
    padding: 1rem 0;
    box-shadow: 0 6px 28px rgba(49,46,129,.38);
    position: sticky;
    top: 0;
    z-index: 1030;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

/* ── Brand ── */
.ks-brand {
    font-family: 'Fraunces', serif;
    font-size: 1.45rem;
    color: #fff !important;
    letter-spacing: -.5px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: .65rem;
    font-weight: 700;
}
.ks-brand-icon {
    background: rgba(255,255,255,.22);
    width: 44px; height: 44px;
    border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    backdrop-filter: blur(6px);
    transition: background .2s, transform .2s;
}
.ks-brand:hover .ks-brand-icon {
    background: rgba(255,255,255,.34);
    transform: scale(1.08);
}

/* ── Nav Links ── */
.ks-nav-link {
    color: rgba(255,255,255,.82) !important;
    border-radius: 10px;
    padding: .55rem 1.05rem !important;
    font-weight: 600;
    font-size: .95rem;
    transition: background .2s, color .2s;
    display: flex;
    align-items: center;
    gap: .45rem;
    text-decoration: none;
    white-space: nowrap;
}
.ks-nav-link:hover,
.ks-nav-link.active {
    background: rgba(255,255,255,.15);
    color: #fff !important;
}
.ks-nav-link i { font-size: 1.05rem; opacity: .9; }

/* ── Notifikasi badge ── */
.ks-notif-wrap { position: relative; }
.ks-notif-dot {
    font-size: .62rem;
    position: absolute;
    top: 0px; right: 0px;
    min-width: 19px; height: 19px;
    padding: 0 4px;
    border: 2px solid #4338ca;
    border-radius: 20px;
    background: #ef4444;
    color: #fff;
    font-weight: 700;
    line-height: 15px;
    text-align: center;
    animation: ks-pulse 1.6s infinite;
    pointer-events: none;
}
@keyframes ks-pulse {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.22); }
}

/* ── Donasi Saya outline pill ── */
.ks-btn-donasi-saya {
    background: rgba(255,255,255,.13);
    border: 1.8px solid rgba(255,255,255,.38);
    color: #fff !important;
    font-weight: 700;
    font-size: .92rem;
    border-radius: 50px;
    padding: .5rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: .42rem;
    text-decoration: none;
    transition: background .2s, border-color .2s, transform .15s;
    white-space: nowrap;
}
.ks-btn-donasi-saya:hover {
    background: rgba(255,255,255,.24);
    border-color: rgba(255,255,255,.65);
    color: #fff !important;
    transform: translateY(-1px);
}
.ks-btn-donasi-saya i { font-size: 1rem; }

/* ── +Donasikan CTA ── */
.ks-btn-donate {
    background: linear-gradient(135deg, #f97316, #ef4444);
    border: none;
    color: #fff !important;
    font-weight: 700;
    font-size: .92rem;
    border-radius: 50px;
    padding: .5rem 1.35rem;
    display: inline-flex;
    align-items: center;
    gap: .42rem;
    text-decoration: none;
    box-shadow: 0 4px 16px rgba(239,68,68,.38);
    transition: transform .18s, box-shadow .18s, filter .18s;
    white-space: nowrap;
}
.ks-btn-donate:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 8px 24px rgba(239,68,68,.48);
    filter: brightness(1.08);
    color: #fff !important;
}
.ks-btn-donate i { font-size: 1rem; }

/* ── Avatar ── */
.ks-avatar {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, #e85d4a, #f4845f);
    color: #fff;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .9rem; font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,.22);
}

/* ── User pill — TANPA panah dropdown ── */
.ks-user-btn {
    background: rgba(255,255,255,.13);
    border: 1.8px solid rgba(255,255,255,.24);
    color: #fff !important;
    border-radius: 50px;
    padding: .38rem 1.1rem .38rem .38rem;
    font-weight: 700;
    font-size: .92rem;
    display: flex; align-items: center; gap: .55rem;
    transition: background .2s, box-shadow .2s;
    cursor: pointer;
}
.ks-user-btn:hover,
.ks-user-btn.show {
    background: rgba(255,255,255,.24);
    box-shadow: 0 4px 18px rgba(0,0,0,.22);
}
/* Sembunyikan panah default Bootstrap sepenuhnya */
.ks-user-btn::after { display: none !important; }

/* ── Dropdown Menu ── */
.ks-dropdown {
    min-width: 220px;
    border-radius: 18px !important;
    border: none !important;
    box-shadow: 0 14px 44px rgba(0,0,0,.18) !important;
    padding: .6rem !important;
    margin-top: .7rem !important;
    overflow: hidden;
}
.ks-dropdown-header {
    padding: .7rem 1rem .85rem;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: .4rem;
}
.ks-dropdown-header .ks-avatar {
    width: 44px; height: 44px;
    font-size: 1rem;
}
.ks-dropdown-item {
    border-radius: 10px !important;
    font-size: .92rem !important;
    padding: .65rem 1rem !important;
    display: flex !important; align-items: center; gap: .6rem;
    color: #1e293b !important;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 500;
    transition: background .15s;
}
.ks-dropdown-item:hover { background: #f1f5f9 !important; }
.ks-dropdown-item.danger { color: #dc2626 !important; }
.ks-dropdown-item.danger:hover { background: #fee2e2 !important; }
.ks-dropdown-item i { width: 18px; text-align: center; font-size: 1rem; }

/* ── Mobile Toggler ── */
.ks-toggler {
    border: none; background: transparent;
    color: rgba(255,255,255,.88);
    font-size: 1.9rem;
    padding: .2rem .4rem;
    line-height: 1;
}
.ks-toggler:focus { box-shadow: none; outline: none; }

/* ── Mobile Collapse ── */
@media (max-width: 991.98px) {
    #ksNavContent {
        background: rgba(15,17,50,.97);
        backdrop-filter: blur(18px);
        border-radius: 18px;
        margin-top: .85rem;
        padding: 1.1rem;
        border: 1px solid rgba(255,255,255,.09);
    }
    .ks-nav-link { padding: .7rem 1rem !important; font-size: 1rem !important; }
    .ks-divider-mobile {
        border-top: 1px solid rgba(255,255,255,.1);
        margin: .6rem 0;
    }
    .ks-dropdown {
        position: static !important;
        transform: none !important;
        width: 100%;
        border-radius: 14px !important;
        margin-top: .5rem !important;
    }
}
</style>

<nav class="navbar navbar-expand-lg" id="main-navbar">
    <div class="container">

        <!-- ── Brand ── -->
        <a class="ks-brand" href="index.php">
            <span class="ks-brand-icon"><i class="bi bi-heart-fill"></i></span>
            KasihSosial
        </a>

        <!-- ── Mobile Toggler ── -->
        <button class="ks-toggler ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#ksNavContent"
                aria-controls="ksNavContent" aria-expanded="false"
                aria-label="Toggle navigation">
            <i class="bi bi-list"></i>
        </button>

        <!-- ── Nav Content ── -->
        <div class="collapse navbar-collapse" id="ksNavContent">
            <ul class="navbar-nav ms-auto align-items-center gap-1 gap-lg-2 mt-3 mt-lg-0">

                <!-- Katalog -->
                <li class="nav-item">
                    <a class="ks-nav-link" href="index.php">
                        <i class="bi bi-grid-1x2"></i>Katalog
                    </a>
                </li>

                <?php if ($role === 'user'): ?>

                <!-- Notifikasi -->
                <li class="nav-item">
                    <a class="ks-nav-link ks-notif-wrap" href="dashboard.donatur.php">
                        <i class="bi bi-bell"></i>Notifikasi
                        <?php if ($total_notif > 0): ?>
                            <span class="ks-notif-dot"
                                  aria-label="<?= (int)$total_notif ?> notifikasi baru">
                                <?= (int)$total_notif ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Donasi Saya -->
                <li class="nav-item">
                    <a class="ks-btn-donasi-saya" href="kelola.donasi.php">
                        <i class="bi bi-box-seam"></i>Donasi Saya
                    </a>
                </li>

                <!-- +Donasikan -->
                <li class="nav-item">
                    <a class="ks-btn-donate" href="upload.php">
                        <i class="bi bi-plus-circle-fill"></i>Donasikan
                    </a>
                </li>

                <?php endif; ?>

                <!-- Mobile divider -->
                <li class="nav-item d-lg-none w-100">
                    <div class="ks-divider-mobile"></div>
                </li>

                <!-- ── User Dropdown (avatar + nama, tanpa panah) ── -->
                <li class="nav-item dropdown mt-2 mt-lg-0">

                    <button class="ks-user-btn dropdown-toggle"
                            id="ksUserDrop"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-haspopup="true">
                        <span class="ks-avatar"><?= $avatar_char ?></span>
                        <span class="d-none d-md-inline"><?= $nama_user ?></span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end ks-dropdown"
                        aria-labelledby="ksUserDrop">

                        <!-- Info user di dropdown -->
                        <li>
                            <div class="ks-dropdown-header d-flex align-items-center gap-2">
                                <span class="ks-avatar"><?= $avatar_char ?></span>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:.92rem;">
                                        <?= $nama_user ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;">
                                        <?= e(ucfirst($role)) ?>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <!-- Profile -->
                        <li>
                            <a class="dropdown-item ks-dropdown-item" href="profile.php">
                                <i class="bi bi-person-circle text-primary"></i>Profile Saya
                            </a>
                        </li>

                        <li><hr class="dropdown-divider mx-2 my-1"></li>

                        <!-- Logout -->
                        <li>
                            <a class="dropdown-item ks-dropdown-item danger"
                               href="logout.php"
                               onclick="return confirm('Yakin ingin keluar?')">
                                <i class="bi bi-box-arrow-right"></i>Keluar
                            </a>
                        </li>

                    </ul>
                </li>
                <!-- ── /User Dropdown ── -->

            </ul>
        </div>

    </div>
</nav>