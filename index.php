<?php

include_once 'koneksi.php';

if (empty($_SESSION['user_id'])) {
    header('Location: beranda.php');
    exit;
}

if (!function_exists('e')) { function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); } }

$my_id   = (int)$_SESSION['user_id'];
$my_role = $_SESSION['role'] ?? 'user';

// ── Notifikasi permintaan masuk (untuk donatur) ──────────────────────────────
// HARUS dihitung SEBELUM navbar.php di-include agar navbar bisa menggunakannya
$notif_count = 0;
if ($my_role === 'user') {
    $s = dbQuery("SELECT COUNT(*) AS n FROM donasi_request dr JOIN pakaian p ON dr.pakaian_id = p.pakaian_id WHERE p.user_id = ? AND dr.status = 'Pending'", 'i', [$my_id]);
    $notif_count = (int)$s->get_result()->fetch_assoc()['n'];
    $s->close();
}

include_once 'navbar.php';

// ── Filter & pencarian ───────────────────────────────────────────────────────
$search   = sanitize($_GET['q']   ?? '', 100);
$filter   = validateEnum($_GET['jenis'] ?? '', ['Atasan','Bawahan','Outerwear','Pakaian Anak']) ?? '';
$sort     = validateEnum($_GET['sort']  ?? '', ['terbaru','terlama']) ?? 'terbaru';

$sql = "SELECT p.*, u.username FROM pakaian p JOIN users u ON p.user_id = u.user_id
        WHERE p.status_ketersediaan = 'Tersedia'";
$types  = '';
$params = [];

if ($search !== '') {
    $sql    .= " AND (p.jenis_pakaian LIKE ? OR p.kondisi LIKE ? OR u.username LIKE ?)";
    $like    = "%{$search}%";
    $types  .= 'sss';
    $params  = array_merge($params, [$like, $like, $like]);
}
if ($filter !== '') {
    $sql   .= " AND p.jenis_pakaian = ?";
    $types .= 's';
    $params[] = $filter;
}

$sql .= ($sort === 'terlama') ? " ORDER BY p.tanggal_upload ASC" : " ORDER BY p.tanggal_upload DESC";

$stmt   = dbQuery($sql, $types, $params);
$result = $stmt->get_result();
$total_items = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KasihSosial — Katalog Donasi Pakaian</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root {
      --coral:   #e85d4a;
      --coral-d: #c94130;
      --teal:    #0d9488;
      --teal-d:  #0a7a70;
      --cream:   #fef7f0;
      --dark:    #1a1a2e;
      --muted:   #6b7280;
      --card-r:  18px;
      --shadow:  0 4px 24px rgba(26,26,46,.08);
      --shadow-h:0 12px 40px rgba(26,26,46,.18);
    }
    *, *::before, *::after { box-sizing: border-box; }
    body {
      background: var(--cream);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--dark);
    }

    /* ─── HERO CLOCK WIDGET ──────────────────────────────────── */
    .hero-widget {
      background: linear-gradient(135deg, var(--dark) 0%, #16213e 60%, #0d324d 100%);
      border-radius: 24px;
      padding: 2rem 2.5rem;
      color: #fff;
      position: relative;
      overflow: hidden;
      margin-bottom: 2rem;
    }
    .hero-widget::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(circle at 75% 50%, rgba(232,93,74,.18), transparent 55%),
                  radial-gradient(circle at 20% 80%, rgba(13,148,136,.12), transparent 50%);
    }
    .hero-widget .content { position: relative; z-index: 1; }
    #live-clock {
      font-family: 'Fraunces', serif;
      font-size: 3.2rem;
      font-weight: 700;
      line-height: 1;
      letter-spacing: -2px;
      display: block;
    }
    #live-date {
      font-size: .8rem;
      opacity: .65;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-top: .3rem;
    }
    .hero-greeting { font-size: 1.1rem; font-weight: 600; opacity: .9; }
    .hero-greeting strong { color: var(--coral); font-weight: 800; }
    .hero-sub { font-size: .8rem; opacity: .55; margin-top: .25rem; }

    /* ─── SEARCH & FILTER BAR ────────────────────────────────── */
    .filter-bar {
      background: #fff;
      border-radius: var(--card-r);
      padding: 1rem 1.25rem;
      box-shadow: var(--shadow);
      margin-bottom: 1.75rem;
    }
    .filter-bar .form-control,
    .filter-bar .form-select {
      border-radius: 10px;
      border-color: #e5e7eb;
      font-size: .875rem;
    }
    .filter-bar .form-control:focus,
    .filter-bar .form-select:focus {
      border-color: var(--coral);
      box-shadow: 0 0 0 3px rgba(232,93,74,.12);
    }
    .btn-search {
      background: var(--coral);
      border: none;
      color: #fff;
      border-radius: 10px;
      padding: .5rem 1.25rem;
      font-weight: 700;
      font-size: .875rem;
      transition: background .2s, transform .15s;
    }
    .btn-search:hover { background: var(--coral-d); transform: translateY(-1px); color: #fff; }

    /* ─── ITEM CARDS ─────────────────────────────────────────── */
    .item-card {
      border: none;
      border-radius: var(--card-r);
      overflow: hidden;
      box-shadow: var(--shadow);
      background: #fff;
      height: 100%;
      transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s;
      animation: card-in .5s ease both;
    }
    .item-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-h);
    }
    @keyframes card-in {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .item-card:nth-child(1) { animation-delay: .05s; }
    .item-card:nth-child(2) { animation-delay: .10s; }
    .item-card:nth-child(3) { animation-delay: .15s; }
    .item-card:nth-child(4) { animation-delay: .20s; }
    .item-card:nth-child(5) { animation-delay: .25s; }
    .item-card:nth-child(6) { animation-delay: .30s; }

    .card-img-wrap { overflow: hidden; position: relative; height: 210px; }
    .card-img-wrap img {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform .4s ease;
    }
    .item-card:hover .card-img-wrap img { transform: scale(1.07); }

    .card-badge {
      position: absolute; top: 10px; left: 10px;
      background: rgba(26,26,46,.7);
      backdrop-filter: blur(6px);
      color: #fff;
      font-size: .7rem; font-weight: 700;
      padding: 3px 10px; border-radius: 20px;
      text-transform: uppercase; letter-spacing: .5px;
    }
    .card-badge.new { background: rgba(232,93,74,.85); }

    .item-card .card-body { padding: 1rem 1.1rem; }
    .item-name { font-size: 1rem; font-weight: 700; margin-bottom: .25rem; }
    .item-meta { font-size: .8rem; color: var(--muted); }
    .kondisi-chip {
      display: inline-block;
      font-size: .7rem; font-weight: 700;
      padding: .25em .65em; border-radius: 6px;
      margin-top: .4rem; margin-bottom: .75rem;
    }
    .chip-baru     { background: #d1fae5; color: #065f46; }
    .chip-layak    { background: #dbeafe; color: #1e40af; }
    .chip-default  { background: #f3f4f6; color: #374151; }

    .btn-req {
      display: block;
      background: linear-gradient(135deg, var(--coral), #f4845f);
      border: none; color: #fff;
      border-radius: 10px; font-weight: 700; font-size: .85rem;
      padding: .55rem; text-align: center; text-decoration: none;
      transition: opacity .2s, transform .15s;
      margin-bottom: .5rem;
    }
    .btn-req:hover { opacity: .9; transform: translateY(-1px); color: #fff; }

    .btn-row { display: flex; gap: .5rem; }
    .btn-sm-outline {
      flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px;
      border: 1.5px solid #e5e7eb; background: transparent; color: var(--dark);
      border-radius: 10px; font-size: .78rem; font-weight: 600;
      padding: .45rem; text-decoration: none;
      transition: border-color .2s, background .2s;
    }
    .btn-sm-outline:hover { border-color: var(--teal); background: #f0fdfa; color: var(--teal); }
    .btn-sm-outline.map:hover { border-color: var(--coral); background: #fff5f4; color: var(--coral); }

    /* ─── EMPTY STATE ────────────────────────────────────────── */
    .empty-state {
      text-align: center;
      padding: 5rem 2rem;
      animation: card-in .5s ease;
    }
    .empty-state .empty-icon {
      font-size: 4rem; color: #d1d5db;
      display: block; margin-bottom: 1rem;
    }

    /* ─── RESULTS HEADER ─────────────────────────────────────── */
    .results-header {
      display: flex; align-items: center;
      justify-content: space-between; flex-wrap: wrap; gap: .75rem;
      margin-bottom: 1.25rem;
    }
    .results-header h2 {
      font-family: 'Fraunces', serif;
      font-size: 1.5rem; font-weight: 700; margin: 0;
    }
    .count-chip {
      background: var(--dark); color: #fff;
      font-size: .75rem; font-weight: 700;
      padding: .3em .75em; border-radius: 20px;
    }

    /* ─── NOTIFIKASI BANNER ──────────────────────────────────── */
    .notif-banner {
      background: linear-gradient(135deg, #1e293b, #1e3a5f);
      border: 1.5px solid #3b82f6;
      border-radius: 14px;
      padding: .9rem 1.25rem;
      color: #fff;
      transition: transform .2s, box-shadow .2s;
      box-shadow: 0 4px 20px rgba(59,130,246,.25);
    }
    .notif-banner:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(59,130,246,.4);
    }
    .notif-bell {
      position: relative;
      width: 42px; height: 42px;
      background: rgba(59,130,246,.2);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; flex-shrink: 0;
      animation: bellRing 1.5s ease infinite;
    }
    .notif-dot {
      position: absolute; top: 6px; right: 6px;
      width: 10px; height: 10px;
      background: #f87171; border-radius: 50%;
      border: 2px solid #1e293b;
      animation: pulseDot 1s ease infinite;
    }
    @keyframes bellRing {
      0%, 100% { transform: rotate(0); }
      15%       { transform: rotate(15deg); }
      30%       { transform: rotate(-15deg); }
      45%       { transform: rotate(10deg); }
      60%       { transform: rotate(-10deg); }
      75%       { transform: rotate(5deg); }
    }
    @keyframes pulseDot {
      0%, 100% { transform: scale(1); opacity: 1; }
      50%       { transform: scale(1.4); opacity: .7; }
    }

    /* ─── FLASH TOAST ─────────────────────────────────────────── */
    .flash-wrap {
      position: fixed; top: 1rem; right: 1rem;
      z-index: 9999; min-width: 280px;
    }

    /* ─── RESPONSIVE ──────────────────────────────────────────── */
    @media (max-width: 576px) {
      #live-clock { font-size: 2.2rem; }
      .hero-widget { padding: 1.5rem; }
    }
  </style>
</head>
<body>

<!-- ═══════════════════════════════════ FLASH MESSAGES ══════════ -->
<div class="flash-wrap"><?= renderFlash(); ?></div>

<div class="container-fluid px-3 px-md-4 mt-4 pb-5" style="max-width:1400px;">

  <!-- ── HERO CLOCK ──────────────────────────────────────────── -->
  <?php if ($my_role === 'user' && $notif_count > 0): ?>
  <a href="dashboard.donatur.php" class="d-block text-decoration-none mb-3">
    <div class="notif-banner">
      <div class="d-flex align-items-center gap-3">
        <div class="notif-bell">
          <i class="bi bi-bell-fill"></i>
        </div>
          <span class="notif-dot"></span>
        <div>
          <div class="fw-bold" style="font-size:.95rem;">
            🎉 Ada <strong><?= $notif_count; ?> permintaan baru</strong> masuk untuk barang Anda!
          </div>
          <div style="font-size:.78rem; opacity:.8;">
            Klik untuk lihat dan setujui permintaan → Dashboard Donatur
          </div>
        </div>
        <i class="bi bi-arrow-right-circle-fill ms-auto fs-5"></i>
      </div>
    </div>
  </a>
  <?php endif; ?>
  <div class="hero-widget">
    <div class="content d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <span id="live-clock">00:00:00</span>
        <span id="live-date" class="d-block">—</span>
      </div>
      <div class="text-end">
        <div class="hero-greeting">Selamat datang, <strong><?= e($_SESSION['username']); ?></strong> 👋</div>
        <div class="hero-sub">Berbagi kebaikan dimulai dari satu langkah kecil.</div>
      </div>
    </div>
  </div>

  <!-- ── SEARCH & FILTER BAR ────────────────────────────────── -->
  <div class="filter-bar">
    <form method="GET" action="">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q" class="form-control border-start-0 ps-0"
                   placeholder="Cari pakaian, kondisi, donatur…"
                   value="<?= e($search); ?>"
                   style="border-radius:0 10px 10px 0;">
          </div>
        </div>
        <div class="col-6 col-md-3">
          <select name="jenis" class="form-select">
            <option value="">Semua Jenis</option>
            <?php foreach (['Atasan','Bawahan','Outerwear','Pakaian Anak'] as $j): ?>
              <option value="<?= $j ?>" <?= $filter === $j ? 'selected' : '' ?>><?= $j ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select name="sort" class="form-select">
            <option value="terbaru" <?= $sort === 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
            <option value="terlama" <?= $sort === 'terlama' ? 'selected' : '' ?>>Terlama</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn-search flex-fill">
            <i class="bi bi-funnel me-1"></i>Filter
          </button>
          <?php if ($search || $filter || $sort !== 'terbaru'): ?>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-2" title="Reset filter">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- ── RESULTS HEADER ─────────────────────────────────────── -->
  <div class="results-header">
    <h2>
      Katalog Pakaian
      <?php if ($search): ?><small class="text-muted fw-normal fs-6"> — "<?= e($search) ?>"</small><?php endif; ?>
    </h2>
    <span class="count-chip"><?= $total_items ?> barang tersedia</span>
  </div>

  <!-- ── CATALOG GRID ───────────────────────────────────────── -->
  <div class="row g-3 g-md-4">
    <?php if ($total_items > 0):
      while ($row = $result->fetch_assoc()):
        $cond = strtolower($row['kondisi'] ?? '');
        $chip = match(true) {
          str_contains($cond,'baru')    => 'chip-baru',
          str_contains($cond,'layak')   => 'chip-layak',
          default                       => 'chip-default',
        };
        $is_new = (strtotime($row['tanggal_upload']) > strtotime('-7 days'));
    ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="item-card">
        <div class="card-img-wrap">
          <img src="uploads/<?= e($row['foto_pakaian']); ?>"
               alt="<?= e($row['jenis_pakaian']); ?>" loading="lazy"
               onerror="this.src='https://placehold.co/400x210?text=KasihSosial'">
          <?php if ($is_new): ?>
            <span class="card-badge new"><i class="bi bi-stars me-1"></i>Baru</span>
          <?php else: ?>
            <span class="card-badge"><?= e($row['ukuran'] ?? '-'); ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div class="item-name"><?= e($row['nama_pakaian'] ?? e($row['jenis_pakaian'])); ?></div>
          <div class="item-meta">
            <i class="bi bi-person-circle me-1"></i><?= e($row['username']); ?>
            &nbsp;·&nbsp;<?= e($row['ukuran'] ?? '-'); ?>
          </div>
          <span class="kondisi-chip <?= $chip; ?>"><?= e($row['kondisi'] ?? '-'); ?></span>

          <?php if (($_SESSION['role'] ?? '') === 'penerima'): ?>
            <a href="donasi.request.php?id=<?= (int)$row['pakaian_id']; ?>" class="btn-req">
              <i class="bi bi-hand-index-thumb me-1"></i>Minta Barang
            </a>
          <?php else: ?>
            <div class="btn-req" style="background: #6b7280; cursor: not-allowed; opacity: 0.7;" title="Hanya penerima yang bisa meminta barang">
              <i class="bi bi-hand-index-thumb me-1"></i>Minta Barang
            </div>
          <?php endif; ?>

          <div class="btn-row">
            <a href="chat.donasi.php?pakaian_id=<?= (int)$row['pakaian_id']; ?>&penerima_id=<?= (int)$row['user_id']; ?>"
               class="btn-sm-outline">
              <i class="bi bi-chat-dots"></i><span class="d-none d-sm-inline">Chat</span>
            </a>
            <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
              <a href="https://www.google.com/maps/search/?api=1&query=<?= (float)$row['latitude']; ?>,<?= (float)$row['longitude']; ?>"
                 target="_blank" rel="noopener noreferrer" class="btn-sm-outline map">
                <i class="bi bi-geo-alt-fill"></i><span class="d-none d-sm-inline">Maps</span>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endwhile; else: ?>
    <div class="col-12">
      <div class="empty-state">
        <i class="bi bi-box-seam empty-icon"></i>
        <h5 class="fw-bold">Belum ada pakaian<?= $search ? ' untuk "'.e($search).'"' : '' ?></h5>
        <p class="text-muted mb-3">
          <?= $search ? 'Coba kata kunci lain atau' : 'Jadilah yang pertama mendonasikan!'; ?>
        </p>
        <?php if ($search || $filter): ?>
          <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i>Tampilkan Semua
          </a>
        <?php elseif ($my_role === 'user'): ?>
          <a href="upload.php" class="btn rounded-pill px-4 fw-bold text-white" style="background:var(--coral);">
            <i class="bi bi-plus-circle me-1"></i>Donasikan Sekarang
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /container -->

<footer class="text-center py-4 mt-5" style="font-size:.8rem;color:#9ca3af;">
  &copy; 2026 KasihSosial &mdash; Saling Berbagi, Saling Peduli
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ── Real-time clock ──────────────────────────────────────────
  const days   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  (function tick() {
    const n = new Date();
    const clockEl = document.getElementById('live-clock');
    const dateEl  = document.getElementById('live-date');
    if (clockEl) clockEl.textContent =
      [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(v => String(v).padStart(2, '0')).join(':');
    if (dateEl) dateEl.textContent =
      days[n.getDay()] + ', ' + n.getDate() + ' ' + months[n.getMonth()] + ' ' + n.getFullYear();
    setTimeout(tick, 1000);
  })();

  // ── Auto-dismiss flash toast setelah 4 detik ─────────────────
  document.querySelectorAll('.flash-wrap .alert').forEach(el => {
    setTimeout(() => el.classList.add('fade'), 3800);
    setTimeout(() => el.remove(), 4200);
  });
</script>
</body>
</html>