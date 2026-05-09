<?php

include_once 'koneksi.php';
requireRole('admin');

// ── Statistik ────────────────────────────────────────────────────────────────
$stats = [];
foreach ([
    'total'     => "SELECT COUNT(*) n FROM pakaian",
    'tersedia'  => "SELECT COUNT(*) n FROM pakaian WHERE status_ketersediaan='Tersedia'",
    'terdonasi' => "SELECT COUNT(*) n FROM pakaian WHERE status_ketersediaan='Sudah Donasi'",
    'users'     => "SELECT COUNT(*) n FROM users WHERE role='user'",
    'penerima'  => "SELECT COUNT(*) n FROM users WHERE role='penerima'",
    'pending'   => "SELECT COUNT(*) n FROM donasi_request WHERE status='Pending'",
] as $k => $q) {
    $r = $conn->query($q);
    $stats[$k] = (int)$r->fetch_assoc()['n'];
}

// ── Data pakaian (dengan paginasi sederhana) ─────────────────────────────────
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$search_q = sanitize($_GET['q'] ?? '', 100);
$filter_s = validateEnum($_GET['status'] ?? '', ['Tersedia', 'Sudah Donasi']) ?? '';

$where  = "WHERE 1=1";
$types  = '';
$params = [];

if ($search_q !== '') {
    $where  .= " AND (p.jenis_pakaian LIKE ? OR u.username LIKE ?)";
    $like    = "%{$search_q}%";
    $types  .= 'ss';
    $params  = array_merge($params, [$like, $like]);
}
if ($filter_s !== '') {
    $where .= " AND p.status_ketersediaan = ?";
    $types .= 's';
    $params[] = $filter_s;
}

// Total count
$count_q = dbQuery("SELECT COUNT(*) n FROM pakaian p JOIN users u ON p.user_id=u.user_id $where", $types, $params);
$total_rows = (int)$count_q->get_result()->fetch_assoc()['n'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Actual data
$data_stmt = dbQuery(
    "SELECT p.*, u.username FROM pakaian p JOIN users u ON p.user_id=u.user_id
     $where ORDER BY p.tanggal_upload DESC LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$per_page, $offset])
);
$items = $data_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — KasihSosial</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:wght@700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root {
      --sidebar-w: 248px;
      --dark: #0f1923;
      --dark2: #1a2535;
      --coral: #e85d4a;
      --teal: #0d9488;
      --cream: #f8f9fc;
    }
    body { background: var(--cream); font-family: 'Plus Jakarta Sans', sans-serif; }

    /* ─── SIDEBAR ───────────────────────────────────────────── */
    .sidebar {
      width: var(--sidebar-w); position: fixed;
      top: 0; left: 0; height: 100vh;
      background: var(--dark);
      display: flex; flex-direction: column;
      z-index: 1000; transition: transform .3s;
      overflow-y: auto;
    }
    .sidebar-brand {
      padding: 1.5rem 1.5rem 1rem;
      border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .sidebar-brand .logo-text {
      font-family: 'Fraunces', serif;
      font-size: 1.3rem; color: #fff;
      letter-spacing: -.3px;
    }
    .sidebar-brand .logo-text span { color: var(--coral); }
    .sidebar-brand .role-badge {
      display: inline-block;
      background: var(--coral);
      color: #fff; font-size: .65rem;
      font-weight: 700; padding: 2px 8px;
      border-radius: 20px; margin-top: .25rem;
    }
    .sidebar-nav { padding: 1rem 0; flex: 1; }
    .nav-item-link {
      display: flex; align-items: center; gap: .75rem;
      padding: .7rem 1.5rem;
      color: rgba(255,255,255,.55);
      font-size: .875rem; font-weight: 500;
      text-decoration: none;
      border-left: 3px solid transparent;
      transition: all .2s;
    }
    .nav-item-link:hover,
    .nav-item-link.active {
      color: #fff;
      background: rgba(255,255,255,.06);
      border-left-color: var(--coral);
    }
    .nav-item-link i { font-size: 1.05rem; flex-shrink: 0; }
    .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,.06); }

    /* ─── MAIN CONTENT ──────────────────────────────────────── */
    .main-wrap { margin-left: var(--sidebar-w); min-height: 100vh; }

    .topbar {
      background: #fff;
      padding: .85rem 1.75rem;
      border-bottom: 1px solid #e9ecef;
      display: flex; align-items: center;
      justify-content: space-between;
      position: sticky; top: 0; z-index: 500;
    }
    .topbar-title { font-weight: 800; font-size: 1.05rem; }
    .clock-pill {
      background: var(--dark);
      color: #fff; border-radius: 20px;
      padding: .3rem .9rem;
      font-size: .8rem; font-family: monospace;
      letter-spacing: 1px;
    }

    /* ─── STAT CARDS ────────────────────────────────────────── */
    .stat-card {
      background: #fff; border-radius: 16px;
      padding: 1.25rem 1.4rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
      transition: transform .25s;
      border: 1px solid #f0f0f5;
    }
    .stat-card:hover { transform: translateY(-4px); }
    .stat-icon {
      width: 46px; height: 46px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; flex-shrink: 0;
    }
    .stat-value { font-size: 1.8rem; font-weight: 800; line-height: 1; }
    .stat-label { font-size: .78rem; color: #6b7280; font-weight: 500; margin-top: .15rem; }

    /* ─── TABLE ─────────────────────────────────────────────── */
    .table-card {
      background: #fff; border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
      overflow: hidden; border: 1px solid #f0f0f5;
    }
    .table-card .card-header {
      background: var(--dark); color: #fff;
      padding: 1rem 1.4rem;
      display: flex; align-items: center;
      justify-content: space-between;
    }
    .table > thead > tr > th {
      font-size: .75rem; text-transform: uppercase;
      letter-spacing: .6px; color: #6b7280;
      font-weight: 700; border: none;
      background: #fafafa; padding: .85rem 1rem;
    }
    .table > tbody > tr > td {
      vertical-align: middle; padding: .8rem 1rem;
      border-color: #f3f4f6; font-size: .875rem;
    }
    .table-hover > tbody > tr:hover > td { background: #f8fafc; }
    .item-thumb {
      width: 46px; height: 46px; border-radius: 10px;
      object-fit: cover; border: 2px solid #e5e7eb;
    }
    .status-pill {
      font-size: .72rem; font-weight: 700;
      padding: .3em .75em; border-radius: 20px;
    }
    .pill-tersedia  { background: #d1fae5; color: #065f46; }
    .pill-terdonasi { background: #f1f5f9; color: #475569; }

    /* ─── FILTER BAR ────────────────────────────────────────── */
    .filter-row {
      background: #fafafa;
      padding: .75rem 1.4rem;
      border-bottom: 1px solid #f0f0f5;
    }
    .filter-row .form-control,
    .filter-row .form-select { border-radius: 8px; font-size: .8rem; }

    /* ─── MOBILE ────────────────────────────────────────────── */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main-wrap { margin-left: 0; }
      .sidebar-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.5); z-index: 999;
      }
      .sidebar-overlay.open { display: block; }
    }
  </style>
</head>
<body>

<!-- ═══════════════════════ SIDEBAR ═══════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="logo-text"><span>❤</span> KasihSosial</div>
    <span class="role-badge"><i class="bi bi-shield-fill me-1"></i>Admin Panel</span>
  </div>
  <nav class="sidebar-nav">
    <a href="admin.dashboard.php" class="nav-item-link active">
      <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>
    <a href="laporan.rekap.php" class="nav-item-link">
      <i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan
    </a>
    <a href="kelola.users.php" class="nav-item-link">
      <i class="bi bi-people-fill"></i> Kelola Users
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="nav-item-link text-danger ps-0"
       onclick="return confirm('Yakin keluar?')">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══════════════════════ MAIN ══════════════════════════════ -->
<div class="main-wrap">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light d-md-none border" id="menuBtn">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="topbar-title">Dashboard Utama</span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span class="clock-pill d-none d-sm-block" id="topClock">00:00:00</span>
      <div class="dropdown">
        <a href="#" class="text-dark text-decoration-none d-flex align-items-center gap-2" data-bs-toggle="dropdown">
          <div class="text-end d-none d-sm-block">
            <div style="font-size:.8rem;font-weight:700;"><?= e($_SESSION['username']); ?></div>
            <div style="font-size:.68rem;color:#9ca3af;"><?= date('d M Y'); ?></div>
          </div>
          <div style="width:34px;height:34px;border-radius:50%;background:var(--coral);
                      display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">
            <?= strtoupper(substr($_SESSION['username'], 0, 1)); ?>
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

    <!-- ── STAT CARDS ──────────────────────────────────────── -->
    <div class="row g-3 mb-4">
      <?php

      $pendapatan_bulan = (int)dbQuery(
          "SELECT SUM(ongkos_kirim) AS total FROM tugas_pengantaran
           WHERE status_pembayaran IN ('cash_lunas','transfer_lunas')
             AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())"
      )->get_result()->fetch_assoc()['total'];

      $cards = [
        ['label' => 'Total Pakaian',  'val' => $stats['total'],    'color' => '#4f46e5', 'icon' => 'box-seam-fill',     'bg' => '#ede9fe'],
        ['label' => 'Tersedia',       'val' => $stats['tersedia'], 'color' => '#059669', 'icon' => 'check-circle-fill',  'bg' => '#d1fae5'],
        ['label' => 'Terdonasi',      'val' => $stats['terdonasi'],'color' => '#0d9488', 'icon' => 'gift-fill',          'bg' => '#ccfbf1'],
        ['label' => 'Total Donatur',  'val' => $stats['users'],    'color' => '#d97706', 'icon' => 'people-fill',        'bg' => '#fef3c7'],
        ['label' => 'Penerima',       'val' => $stats['penerima'], 'color' => '#db2777', 'icon' => 'heart-fill',         'bg' => '#fce7f3'],
        ['label' => 'Req. Pending',   'val' => $stats['pending'],  'color' => '#e85d4a', 'icon' => 'hourglass-split',   'bg' => '#fee2e2'],
        ['label' => 'Pendapatan Bulan Ini', 'val' => 'Rp '.number_format($pendapatan_bulan,0,',','.'), 'color' => '#0d9488', 'icon' => 'wallet2', 'bg' => '#ccfbf1'],
      ];
      
      foreach ($cards as $c): ?>
        <div class="col-6 col-md-4 col-xl-2">
          <div class="stat-card d-flex align-items-center gap-3">
            <div class="stat-icon" style="background:<?= $c['bg']; ?>;">
              <i class="bi bi-<?= $c['icon']; ?>" style="color:<?= $c['color']; ?>;"></i>
            </div>
            <div>
              <div class="stat-value" style="color:<?= $c['color']; ?>;"><?= $c['val']; ?></div>
              <div class="stat-label"><?= $c['label']; ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── TABLE ───────────────────────────────────────────── -->
    <div class="table-card">
      <div class="card-header">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-table fs-5"></i>
          <span class="fw-bold">Manajemen Pakaian</span>
          <span class="badge bg-secondary ms-1"><?= $total_rows; ?> item</span>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="filter-row">
        <form method="GET" class="row g-2">
          <div class="col-12 col-md-5">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Cari barang / username…" value="<?= e($search_q); ?>">
          </div>
          <div class="col-6 col-md-3">
            <select name="status" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              <option value="Tersedia"    <?= $filter_s === 'Tersedia'    ? 'selected' : ''; ?>>Tersedia</option>
              <option value="Sudah Donasi"<?= $filter_s === 'Sudah Donasi'? 'selected' : ''; ?>>Sudah Donasi</option>
            </select>
          </div>
          <div class="col-6 col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
            <?php if ($search_q || $filter_s): ?>
              <a href="admin.dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x"></i>
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0 mobile-card">
          <thead>
            <tr>
              <th>#</th><th>Foto</th><th>Jenis Pakaian</th>
              <th>Ukuran</th><th>Kondisi</th>
              <th>Pemilik</th><th>Upload</th>
              <th>Status</th><th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($items->num_rows > 0):
            $no = $offset + 1;
            while ($row = $items->fetch_assoc()): ?>
            <tr>
              <td class="text-muted" style="font-size:.78rem;"><?= $no++; ?></td>
              <td>
                <img src="uploads/<?= e($row['foto_pakaian']); ?>"
                     class="item-thumb"
                     onerror="this.src='https://placehold.co/46x46?text=?'"
                     alt="">
              </td>
              <td class="fw-semibold"><?= e($row['jenis_pakaian']); ?></td>
              <td><span class="badge bg-light text-dark border"><?= e($row['ukuran'] ?? '-'); ?></span></td>
              <td><?= e($row['kondisi'] ?? '-'); ?></td>
              <td><?= e($row['username']); ?></td>
              <td class="text-muted" style="font-size:.78rem;">
                <?= date('d M Y', strtotime($row['tanggal_upload'])); ?>
              </td>
              <td>
                <span class="status-pill <?= $row['status_ketersediaan']==='Tersedia' ? 'pill-tersedia' : 'pill-terdonasi'; ?>">
                  <?= e($row['status_ketersediaan']); ?>
                </span>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="edit.pakaian.php?id=<?= (int)$row['pakaian_id']; ?>"
                     class="btn btn-sm btn-warning fw-semibold" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="POST" action="hapus.pakaian.php"
                        onsubmit="return confirm('Hapus permanen barang ini?')">
                    <?= csrfField(); ?>
                    <input type="hidden" name="id" value="<?= (int)$row['pakaian_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="9" class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Tidak ada data<?= $search_q ? ' untuk "'.e($search_q).'"' : ''; ?>.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" style="font-size:.8rem;">
          <span class="text-muted">
            Menampilkan <?= $offset + 1; ?>–<?= min($offset + $per_page, $total_rows); ?> dari <?= $total_rows; ?>
          </span>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                  <a class="page-link" href="?page=<?= $p; ?>&q=<?= urlencode($search_q); ?>&status=<?= urlencode($filter_s); ?>">
                    <?= $p; ?>
                  </a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        </div>
      <?php endif; ?>

    </div><!-- /table-card -->

  </div><!-- /container -->
</div><!-- /main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Clock
  (function tick() {
    const el = document.getElementById('topClock');
    if (el) {
      const n = new Date();
      el.textContent = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2, '0')).join(':');
    }
    setTimeout(tick, 1000);
  })();

  // Sidebar mobile 
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  document.getElementById('menuBtn')?.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  }
</script>
</body>
</html>