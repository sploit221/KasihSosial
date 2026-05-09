<?php

include_once 'koneksi.php';
requireRole('admin');

// ─── Proses POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        header("Location: kelola.users.php"); exit;
    }

    $action  = $_POST['action'] ?? '';
    $uid     = validateId($_POST['user_id'] ?? 0);

    if (!$uid) {
        flash('error', 'ID pengguna tidak valid.');
        header("Location: kelola.users.php"); exit;
    }

    // Cegah admin hapus/ubah akun diri sendiri
    if ($uid === (int)$_SESSION['user_id'] && in_array($action, ['delete','change_role','toggle_status'], true)) {
        flash('error', 'Anda tidak dapat mengubah akun Anda sendiri melalui panel ini.');
        header("Location: kelola.users.php"); exit;
    }

    switch ($action) {

        // ── Ubah Role ─────────────────────────────────────────
        case 'change_role':
            $new_role = validateEnum($_POST['new_role'] ?? '', ['user','penerima','driver','admin']);
            if (!$new_role) {
                flash('error', 'Role tidak valid.'); break;
            }
            dbQuery("UPDATE users SET role = ? WHERE user_id = ?", 'si', [$new_role, $uid]);
            flash('success', "Role pengguna #$uid berhasil diubah menjadi '$new_role'.");
            break;

        // ── Reset Password ────────────────────────────────────
        case 'reset_password':
            // Generate password acak 10 karakter
            $new_pw   = bin2hex(random_bytes(5));   // 10 char hex
            $hashed   = password_hash($new_pw, PASSWORD_BCRYPT, ['cost' => 12]);
            dbQuery("UPDATE users SET password = ? WHERE user_id = ?", 'si', [$hashed, $uid]);
            flash('success', "Password pengguna #$uid direset menjadi: <strong>$new_pw</strong> — Informasikan kepada pengguna.");
            break;

        // ── Hapus User ────────────────────────────────────────
        case 'delete':
            // Hapus data terkait (soft delete lebih aman, tapi ini hard delete)
            dbQuery("DELETE FROM users WHERE user_id = ? AND role != 'admin'", 'i', [$uid]);
            flash('success', "Pengguna #$uid berhasil dihapus.");
            break;

        // ── Toggle Status (aktif/nonaktif) ────────────────────
        case 'toggle_status':
            $cur_status = dbQuery("SELECT is_active FROM users WHERE user_id = ?", 'i', [$uid])
                            ->get_result()->fetch_assoc()['is_active'] ?? 1;
            $new_status = $cur_status ? 0 : 1;
            dbQuery("UPDATE users SET is_active = ? WHERE user_id = ?", 'ii', [$new_status, $uid]);
            flash('success', "Status pengguna #$uid " . ($new_status ? 'diaktifkan' : 'dinonaktifkan') . ".");
            break;
    }

    header("Location: kelola.users.php?" . http_build_query(array_filter([
        'role'   => $_GET['role'] ?? '',
        'q'      => $_GET['q']    ?? '',
        'page'   => $_GET['page'] ?? '',
    ])));
    exit;
}

// ─── Filter, search, paginasi ─────────────────────────────────────────────────
$filter_role = validateEnum($_GET['role'] ?? '', ['user','penerima','driver','admin']) ?? '';
$search_q    = sanitize($_GET['q'] ?? '', 80);
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 15;
$offset      = ($page - 1) * $per_page;
$sort_by     = validateEnum($_GET['sort'] ?? '', ['username','role','created_at']) ?? 'created_at';
$sort_dir    = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$where  = "WHERE 1=1";
$types  = '';
$params = [];

if ($filter_role !== '') {
    $where  .= " AND role = ?"; $types .= 's'; $params[] = $filter_role;
}
if ($search_q !== '') {
    $where  .= " AND (username LIKE ? OR no_hp LIKE ?)";
    $like    = "%{$search_q}%";
    $types  .= 'sss'; $params = array_merge($params, [$like, $like, $like]);
}

// Count total
$total_rows  = (int)dbQuery("SELECT COUNT(*) n FROM users $where", $types, $params)
                ->get_result()->fetch_assoc()['n'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Data
$stmt = dbQuery(
    "SELECT user_id, username, role, no_hp, is_active,
            created_at,
            (SELECT COUNT(*) FROM pakaian WHERE user_id = users.user_id) AS cnt_pakaian,
            (SELECT COUNT(*) FROM tugas_pengantaran WHERE driver_id = users.user_id AND status_pengantaran='Selesai') AS cnt_tugas
     FROM users $where
     ORDER BY $sort_by $sort_dir
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$per_page, $offset])
);
$users = $stmt->get_result();

// ─── Statistik role ───────────────────────────────────────────────────────────
$role_stats = [];
foreach (['user','penerima','driver','admin'] as $r) {
    $role_stats[$r] = (int)dbQuery("SELECT COUNT(*) n FROM users WHERE role = ?", 's', [$r])
                        ->get_result()->fetch_assoc()['n'];
}
$total_users = array_sum($role_stats);

// ─── Helper sort link ─────────────────────────────────────────────────────────
function sortLink(string $col, string $cur, string $dir, array $get): string {
    $new_dir = ($cur === $col && $dir === 'DESC') ? 'asc' : 'desc';
    $params  = array_merge($get, ['sort' => $col, 'dir' => $new_dir, 'page' => 1]);
    $icon    = ($cur === $col) ? ($dir === 'DESC' ? '↓' : '↑') : '⇅';
    return '<a href="?' . http_build_query($params) . '" class="text-decoration-none text-inherit">'
         . ucfirst($col) . ' <span style="opacity:.5;font-size:.8em;">' . $icon . '</span></a>';
}
$get_clean = array_filter(['role' => $filter_role, 'q' => $search_q]);

// Role badge colors
$role_colors = [
    'admin'    => ['bg' => '#fee2e2', 'c' => '#991b1b', 'icon' => 'shield-fill'],
    'driver'   => ['bg' => '#dbeafe', 'c' => '#1e40af', 'icon' => 'truck'],
    'penerima' => ['bg' => '#fce7f3', 'c' => '#9d174d', 'icon' => 'heart-fill'],
    'user'     => ['bg' => '#d1fae5', 'c' => '#065f46', 'icon' => 'person-fill'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Pengguna — KasihSosial Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    :root {
      --dark: #0f1923; --dark2: #1a2535;
      --coral: #e85d4a; --teal: #0d9488;
      --cream: #f8f9fc;
      --sidebar: 248px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { background: var(--cream); font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; }

    /* ── Sidebar (reuse dari admin_dashboard) ─────────────────── */
    .sidebar {
      width: var(--sidebar); position: fixed; top:0; left:0;
      height: 100vh; background: var(--dark);
      display: flex; flex-direction: column; z-index:1000;
      transition: transform .3s; overflow-y: auto;
    }
    .sidebar-brand { padding:1.5rem; border-bottom:1px solid rgba(255,255,255,.07); }
    .brand-name { font-family:'Fraunces',serif; font-size:1.25rem; color:#fff; }
    .brand-name span { color: var(--coral); }
    .role-badge { font-size:.65rem; font-weight:700;
                  background:var(--coral); color:#fff;
                  padding:2px 9px; border-radius:20px; display:inline-block; margin-top:.3rem; }
    .sidebar-nav { padding:1.25rem 0; flex:1; }
    .nav-lnk {
      display:flex; align-items:center; gap:.75rem;
      padding:.65rem 1.5rem; color:rgba(255,255,255,.5);
      font-size:.875rem; font-weight:500; text-decoration:none;
      border-left:3px solid transparent; transition:all .2s;
    }
    .nav-lnk:hover, .nav-lnk.active {
      color:#fff; background:rgba(255,255,255,.06); border-left-color:var(--coral);
    }
    .sidebar-footer { padding:1rem 1.5rem; border-top:1px solid rgba(255,255,255,.06); }

    /* ── Main ─────────────────────────────────────────────────── */
    .main { margin-left: var(--sidebar); min-height:100vh; }
    .topbar {
      background:#fff; padding:.8rem 1.75rem;
      border-bottom:1px solid #e9ecef;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:500;
    }
    .topbar-title { font-weight:800; font-size:1.05rem; }
    .clock-chip {
      background:var(--dark); color:#fff; border-radius:20px;
      padding:.3rem .9rem; font-size:.78rem; font-family:monospace; letter-spacing:1px;
    }

    /* ── Role filter tabs ─────────────────────────────────────── */
    .role-tabs { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem; }
    .role-tab {
      padding:.45rem 1rem; border-radius:20px; font-size:.825rem; font-weight:700;
      border:1.5px solid #e5e7eb; background:#fff; color:#374151;
      text-decoration:none; transition:all .2s; display:flex; align-items:center; gap:.4rem;
    }
    .role-tab:hover { border-color:var(--teal); color:var(--teal); }
    .role-tab.active { background:var(--dark); border-color:var(--dark); color:#fff; }
    .role-tab .count { background:rgba(255,255,255,.2); padding:0 6px; border-radius:10px; font-size:.7rem; }
    .role-tab:not(.active) .count { background:#f3f4f6; color:#6b7280; }

    /* ── Stat row ─────────────────────────────────────────────── */
    .mini-stat {
      background:#fff; border-radius:12px; padding:.85rem 1rem;
      box-shadow:0 2px 8px rgba(0,0,0,.05);
      display:flex; align-items:center; gap:.75rem;
    }
    .mini-stat .icon { width:38px; height:38px; border-radius:10px;
                       display:flex; align-items:center; justify-content:center; font-size:1rem; }
    .mini-stat .val { font-size:1.4rem; font-weight:800; line-height:1; }
    .mini-stat .lbl { font-size:.7rem; color:#6b7280; }

    /* ── Filter bar ───────────────────────────────────────────── */
    .filter-bar {
      background:#fff; border-radius:14px;
      padding:.85rem 1.25rem; box-shadow:0 2px 10px rgba(0,0,0,.05);
      margin-bottom:1.25rem;
    }
    .filter-bar .form-control, .filter-bar .form-select {
      border-radius:9px; font-size:.825rem; border-color:#e5e7eb;
    }
    .filter-bar .form-control:focus, .filter-bar .form-select:focus {
      border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,148,136,.1);
    }

    /* ── Table card ───────────────────────────────────────────── */
    .table-card {
      background:#fff; border-radius:16px;
      box-shadow:0 2px 12px rgba(0,0,0,.05); overflow:hidden;
    }
    .table-card .tbl-header {
      background:var(--dark); color:#fff;
      padding:.9rem 1.4rem;
      display:flex; align-items:center; justify-content:space-between;
    }
    .table > thead > tr > th {
      font-size:.72rem; text-transform:uppercase; letter-spacing:.5px;
      color:#6b7280; font-weight:700; background:#fafafa;
      border:none; padding:.8rem 1rem;
    }
    .table > tbody > tr > td {
      vertical-align:middle; font-size:.85rem;
      padding:.75rem 1rem; border-color:#f3f4f6;
    }
    .table-hover > tbody > tr:hover > td { background:#f8fafc; }

    /* ── User avatar ──────────────────────────────────────────── */
    .u-avatar {
      width:36px; height:36px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:.85rem; flex-shrink:0;
    }

    /* ── Role badge ───────────────────────────────────────────── */
    .rbadge {
      font-size:.7rem; font-weight:700;
      padding:.25em .65em; border-radius:20px;
      display:inline-flex; align-items:center; gap:.3rem;
    }

    /* ── Status dot ───────────────────────────────────────────── */
    .status-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }
    .dot-active   { background:#10b981; }
    .dot-inactive { background:#d1d5db; }

    /* ── Action buttons ───────────────────────────────────────── */
    .act-btn {
      display:inline-flex; align-items:center; gap:4px;
      font-size:.75rem; font-weight:600; padding:.3rem .65rem;
      border-radius:8px; border:none; cursor:pointer; transition:opacity .2s;
    }
    .act-btn:hover { opacity:.8; }
    .act-role    { background:#ede9fe; color:#4f46e5; }
    .act-reset   { background:#fef3c7; color:#92400e; }
    .act-toggle  { background:#dbeafe; color:#1e40af; }
    .act-delete  { background:#fee2e2; color:#991b1b; }

    /* ── Modal ────────────────────────────────────────────────── */
    .modal-content { border:none; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,.15); }
    .modal-header  { border-bottom:1px solid #f3f4f6; padding:1.25rem 1.5rem; }
    .modal-footer  { border-top:1px solid #f3f4f6; }

    /* ── Mobile ───────────────────────────────────────────────── */
    @media (max-width:768px) {
      .sidebar { transform:translateX(-100%); }
      .sidebar.open { transform:translateX(0); }
      .main { margin-left:0; }
      .overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; }
      .overlay.open { display:block; }
    }
  </style>
</head>
<body>

<!-- ══════════════════════ SIDEBAR ══════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-name"><span>❤</span> KasihSosial</div>
    <span class="role-badge"><i class="bi bi-shield-fill me-1"></i>Admin Panel</span>
  </div>
  <nav class="sidebar-nav">
    <a href="admin.dashboard.php" class="nav-lnk">
      <i class="bi bi-grid-1x2-fill"></i>Dashboard
    </a>
    <a href="kelola.users.php" class="nav-lnk active">
      <i class="bi bi-people-fill"></i>Kelola Users
    </a>
    <a href="laporan.rekap.php" class="nav-lnk">
      <i class="bi bi-file-earmark-bar-graph-fill"></i>Laporan
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-lnk" style="color:#f87171;padding-left:0;"
       onclick="return confirm('Yakin keluar?')">
      <i class="bi bi-box-arrow-right"></i>Logout
    </a>
  </div>
</aside>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ══════════════════════ MAIN ═════════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-sm btn-light border d-md-none" id="menuBtn">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="topbar-title">Kelola Pengguna</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="clock-chip d-none d-sm-block" id="topClock">00:00:00</span>
      <div class="dropdown">
        <a href="#" class="text-dark text-decoration-none d-flex align-items-center gap-2"
           data-bs-toggle="dropdown">
          <div style="width:32px;height:32px;border-radius:50%;
                      background:var(--coral);display:flex;align-items:center;
                      justify-content:center;color:#fff;font-weight:700;font-size:.8rem;">
            <?= strtoupper(substr($_SESSION['username'],0,1)); ?>
          </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
          <li><a class="dropdown-item text-danger" href="logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1400px;">

    <?= renderFlash(); ?>

    <!-- ── Mini stats ──────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <?php
      $mini = [
        ['val'=>$total_users,         'lbl'=>'Total Pengguna', 'icon'=>'people-fill',   'c'=>'#4f46e5','bg'=>'#ede9fe'],
        ['val'=>$role_stats['user'],  'lbl'=>'Donatur',        'icon'=>'person-fill',   'c'=>'#059669','bg'=>'#d1fae5'],
        ['val'=>$role_stats['penerima'],'lbl'=>'Penerima',     'icon'=>'heart-fill',    'c'=>'#db2777','bg'=>'#fce7f3'],
        ['val'=>$role_stats['driver'],'lbl'=>'Driver',         'icon'=>'truck',         'c'=>'#1d4ed8','bg'=>'#dbeafe'],
        ['val'=>$role_stats['admin'], 'lbl'=>'Admin',          'icon'=>'shield-fill',   'c'=>'#e85d4a','bg'=>'#fee2e2'],
      ];
      foreach ($mini as $m): ?>
        <div class="col-6 col-md-4 col-xl">
          <div class="mini-stat">
            <div class="icon" style="background:<?= $m['bg']; ?>;">
              <i class="bi bi-<?= $m['icon']; ?>" style="color:<?= $m['c']; ?>;"></i>
            </div>
            <div>
              <div class="val" style="color:<?= $m['c']; ?>;"><?= $m['val']; ?></div>
              <div class="lbl"><?= $m['lbl']; ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Role Filter Tabs ────────────────────────────────────── -->
    <div class="role-tabs">
      <a href="?<?= http_build_query(array_merge($get_clean, ['role'=>'','page'=>1])); ?>"
         class="role-tab <?= $filter_role === '' ? 'active' : ''; ?>">
        <i class="bi bi-grid-3x3-gap"></i> Semua
        <span class="count"><?= $total_users; ?></span>
      </a>
      <?php foreach (['user'=>'Donatur','penerima'=>'Penerima','driver'=>'Driver','admin'=>'Admin'] as $r=>$label): ?>
        <a href="?<?= http_build_query(array_merge($get_clean, ['role'=>$r,'page'=>1])); ?>"
           class="role-tab <?= $filter_role === $r ? 'active' : ''; ?>">
          <i class="bi bi-<?= $role_colors[$r]['icon']; ?>"></i> <?= $label; ?>
          <span class="count"><?= $role_stats[$r]; ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ── Filter Bar ─────────────────────────────────────────── -->
    <div class="filter-bar">
      <form method="GET" class="row g-2 align-items-center">
        <?php if ($filter_role): ?>
          <input type="hidden" name="role" value="<?= e($filter_role); ?>">
        <?php endif; ?>
        <div class="col-12 col-md-6 col-lg-5">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0" style="border-radius:9px 0 0 9px;">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q" class="form-control border-start-0"
                   placeholder="Cari nama, email, atau nomor HP…"
                   value="<?= e($search_q); ?>"
                   style="border-radius:0 9px 9px 0;">
          </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <select name="sort" class="form-select form-select-sm">
            <option value="created_at" <?= $sort_by==='created_at'?'selected':''; ?>>Terbaru</option>
            <option value="username"   <?= $sort_by==='username'  ?'selected':''; ?>>Nama</option>
            <option value="role"       <?= $sort_by==='role'      ?'selected':''; ?>>Role</option>
          </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-flex gap-1">
          <button type="submit" class="btn btn-sm btn-primary flex-fill fw-semibold rounded-pill">
            <i class="bi bi-funnel me-1"></i>Cari
          </button>
          <?php if ($search_q || $filter_role): ?>
            <a href="kelola.users.php" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- ── Table ───────────────────────────────────────────────── -->
    <div class="table-card">
      <div class="tbl-header">
        <span class="fw-bold"><i class="bi bi-people me-2"></i>Daftar Pengguna</span>
        <span class="badge bg-secondary">
          <?= $total_rows; ?> pengguna<?= $filter_role ? ' ('.$filter_role.')' : ''; ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0 mobile-card">
          <thead>
            <tr>
              <th>#</th>
              <th><?= sortLink('username',  $sort_by, $sort_dir, $get_clean); ?></th>
              <th>Email / HP</th>
              <th><?= sortLink('role',      $sort_by, $sort_dir, $get_clean); ?></th>
              <th>Kontribusi</th>
              <th>Status</th>
              <th><?= sortLink('created_at',$sort_by, $sort_dir, $get_clean); ?></th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($users->num_rows > 0):
            $no = $offset + 1;
            while ($u = $users->fetch_assoc()):
              $rc   = $role_colors[$u['role']] ?? ['bg'=>'#f3f4f6','c'=>'#374151','icon'=>'person'];
              $init = strtoupper(substr($u['username'] ?? '?', 0, 2));
              $is_self = ($u['user_id'] == $_SESSION['user_id']);
              // Avatar warna berdasarkan user_id
              $av_colors = ['#4f46e5','#0d9488','#e85d4a','#d97706','#db2777'];
              $av_bg = $av_colors[$u['user_id'] % 5];
          ?>
            <tr class="<?= $is_self ? 'table-warning' : ''; ?>">
              <td class="text-muted" style="font-size:.75rem;"><?= $no++; ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="u-avatar" style="background:<?= $av_bg; ?>;color:#fff;">
                    <?= e($init); ?>
                  </div>
                  <div>
                    <div class="fw-semibold"><?= e($u['username']); ?></div>
                    <small class="text-muted">#<?= $u['user_id']; ?></small>
                    <?php if ($is_self): ?>
                      <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">Anda</span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-size:.8rem;"><?= e($u['email'] ?? '—'); ?></div>
                <?php if ($u['no_hp']): ?>
                  <a href="https://wa.me/<?= preg_replace('/\D/','',$u['no_hp']); ?>"
                     target="_blank" class="text-success" style="font-size:.75rem;">
                    <i class="bi bi-whatsapp me-1"></i><?= e($u['no_hp']); ?>
                  </a>
                <?php endif; ?>
              </td>
              <td>
                <span class="rbadge" style="background:<?= $rc['bg']; ?>;color:<?= $rc['c']; ?>;">
                  <i class="bi bi-<?= $rc['icon']; ?>"></i><?= ucfirst($u['role']); ?>
                </span>
              </td>
              <td style="font-size:.78rem;">
                <?php if ($u['role'] === 'user'): ?>
                  <span title="Barang didonasikan">
                    <i class="bi bi-box-seam text-muted me-1"></i><?= (int)$u['cnt_pakaian']; ?> barang
                  </span>
                <?php elseif ($u['role'] === 'driver'): ?>
                  <span title="Tugas selesai">
                    <i class="bi bi-check-circle text-muted me-1"></i><?= (int)$u['cnt_tugas']; ?> antar
                  </span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-dot <?= ($u['is_active'] ?? 1) ? 'dot-active' : 'dot-inactive'; ?> me-1"></span>
                <span style="font-size:.78rem;">
                  <?= ($u['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif'; ?>
                </span>
              </td>
              <td class="text-muted" style="font-size:.75rem;">
                <?= isset($u['created_at']) ? date('d M Y', strtotime($u['created_at'])) : '—'; ?>
              </td>
              <td>
                <?php if ($is_self): ?>
                  <span class="text-muted small">—</span>
                <?php else: ?>
                <div class="d-flex gap-1 flex-wrap">
                  <!-- Ubah Role -->
                  <button type="button" class="act-btn act-role"
                          title="Ubah Role"
                          onclick="openRoleModal(<?= $u['user_id']; ?>, '<?= e($u['username']); ?>', '<?= $u['role']; ?>')">
                    <i class="bi bi-arrow-repeat"></i>Role
                  </button>

                  <!-- Reset PW -->
                  <form method="POST" onsubmit="return confirm('Reset password pengguna ini? Password baru akan ditampilkan setelah ini.')">
                    <?= csrfField(); ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                    <input type="hidden" name="action"  value="reset_password">
                    <button type="submit" class="act-btn act-reset" title="Reset Password">
                      <i class="bi bi-key"></i>PW
                    </button>
                  </form>

                  <!-- Toggle status -->
                  <?php if ($u['role'] !== 'admin'): ?>
                    <form method="POST" onsubmit="return confirm('Ubah status aktif pengguna ini?')">
                      <?= csrfField(); ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                      <input type="hidden" name="action"  value="toggle_status">
                      <button type="submit" class="act-btn act-toggle" title="Toggle Status">
                        <i class="bi bi-<?= ($u['is_active']??1) ? 'pause' : 'play'; ?>"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Hapus (bukan admin) -->
                  <?php if ($u['role'] !== 'admin'): ?>
                    <form method="POST" onsubmit="return confirm('HAPUS PERMANEN pengguna ini? Semua data terkait akan ikut terhapus.')">
                      <?= csrfField(); ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
                      <input type="hidden" name="action"  value="delete">
                      <button type="submit" class="act-btn act-delete" title="Hapus User">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-people fs-2 d-block mb-2"></i>
                Tidak ada pengguna<?= $search_q ? ' untuk "'.e($search_q).'"' : ''; ?>.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top"
             style="font-size:.78rem;">
          <span class="text-muted">
            Menampilkan <?= $offset+1; ?>–<?= min($offset+$per_page,$total_rows); ?>
            dari <?= $total_rows; ?> pengguna
          </span>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php
              $base_pg = http_build_query(array_filter(['role'=>$filter_role,'q'=>$search_q,'sort'=>$sort_by,'dir'=>strtolower($sort_dir)]));
              for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p===$page?'active':''; ?>">
                  <a class="page-link" href="?<?= $base_pg; ?>&page=<?= $p; ?>"><?= $p; ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        </div>
      <?php endif; ?>

    </div><!-- /table-card -->
  </div><!-- /container -->
</div><!-- /main -->

<!-- ════════════════════ MODAL UBAH ROLE ════════════════════════ -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-arrow-repeat me-2 text-primary"></i>Ubah Role Pengguna
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="roleForm">
        <?= csrfField(); ?>
        <input type="hidden" name="action"  value="change_role">
        <input type="hidden" name="user_id" id="modalUserId">
        <div class="modal-body">
          <p class="mb-3">Mengubah role untuk: <strong id="modalUsername">—</strong></p>
          <label class="form-label fw-bold">Role Baru</label>
          <div class="d-flex flex-column gap-2" id="roleOptions">
            <?php foreach (['user'=>'Donatur','penerima'=>'Penerima','driver'=>'Driver','admin'=>'Admin'] as $r=>$lbl):
              $rc2 = $role_colors[$r]; ?>
              <label class="d-flex align-items-center gap-3 p-3 border rounded-3 cursor-pointer role-opt"
                     style="cursor:pointer;" for="role_<?= $r; ?>">
                <input type="radio" name="new_role" id="role_<?= $r; ?>" value="<?= $r; ?>"
                       class="form-check-input mt-0" style="flex-shrink:0;">
                <span class="rbadge" style="background:<?= $rc2['bg']; ?>;color:<?= $rc2['c']; ?>;">
                  <i class="bi bi-<?= $rc2['icon']; ?>"></i><?= $lbl; ?>
                </span>
                <small class="text-muted ms-auto" style="font-size:.75rem;">
                  <?php echo match($r) {
                    'user'     => 'Bisa mendonasikan pakaian',
                    'penerima' => 'Bisa meminta donasi',
                    'driver'   => 'Mengantar barang',
                    'admin'    => 'Akses penuh panel admin',
                  }; ?>
                </small>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary fw-bold">
            <i class="bi bi-check2 me-1"></i>Simpan Role
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ── Clock ────────────────────────────────────────────────────
  (function tick() {
    const el = document.getElementById('topClock');
    if (el) {
      const n = new Date();
      el.textContent = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2,'0')).join(':');
    }
    setTimeout(tick, 1000);
  })();

  // ── Sidebar mobile ───────────────────────────────────────────
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  document.getElementById('menuBtn')?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }

  // ── Modal ubah role ──────────────────────────────────────────
  function openRoleModal(userId, username, currentRole) {
    document.getElementById('modalUserId').value  = userId;
    document.getElementById('modalUsername').textContent = username;
    // Pre-select current role
    const radio = document.querySelector(`#roleForm input[value="${currentRole}"]`);
    if (radio) radio.checked = true;
    // Highlight selected
    document.querySelectorAll('.role-opt').forEach(el => {
      const inp = el.querySelector('input');
      el.style.borderColor = inp.checked ? '#4f46e5' : '#e5e7eb';
      el.style.background  = inp.checked ? '#f5f3ff' : '#fff';
    });
    new bootstrap.Modal(document.getElementById('roleModal')).show();
  }

  // Highlight on click
  document.querySelectorAll('.role-opt').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('.role-opt').forEach(o => {
        o.style.borderColor = '#e5e7eb'; o.style.background = '#fff';
      });
      el.style.borderColor = '#4f46e5';
      el.style.background  = '#f5f3ff';
    });
  });

  // Auto-dismiss flash
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => el.classList.add('fade'), 5000);
    setTimeout(() => el.remove(), 5400);
  });
</script>
</body>
</html>