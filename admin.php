<?php
// admin.php — Dashboard Admin LapakThriftUMSU
session_start();
require_once 'koneksi.php';   // menyediakan $conn (mysqli) & $pdo

// ── Guard: hanya admin ────────────────────────────────────────────────────
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: adminlogin.html");
    exit();
}

// ── Logout ─────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: adminlogin.html");
    exit();
}

// ── Aksi POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Approve produk → is_approved = 1, status = tersedia
    if ($aksi === 'approve' && $id > 0) {
        $st = mysqli_prepare($conn, "UPDATE produk SET is_approved = 1, status = 'tersedia' WHERE id = ?");
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        // Notifikasi ke penjual
        $r = mysqli_query($conn, "SELECT seller_id, nama_produk FROM produk WHERE id = $id");
        if ($row = mysqli_fetch_assoc($r)) {
            $seller_id   = (int)$row['seller_id'];
            $nama_produk = mysqli_real_escape_string($conn, $row['nama_produk']);
            mysqli_query($conn,
                "INSERT INTO notifikasi (user_id, judul, pesan, tipe, referensi_id)
                 VALUES ($seller_id, 'Produk Disetujui 🎉',
                 'Produk \"{$nama_produk}\" Anda telah disetujui dan kini tampil di halaman belanja.',
                 'produk', $id)"
            );
        }

        header("Location: admin.php?tab=produk&msg=approved");
        exit();
    }

    // Tolak produk → sembunyikan
    if ($aksi === 'tolak' && $id > 0) {
        $st = mysqli_prepare($conn, "UPDATE produk SET status = 'dihapus', is_approved = 0 WHERE id = ?");
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        // Notifikasi ke penjual
        $r = mysqli_query($conn, "SELECT seller_id, nama_produk FROM produk WHERE id = $id");
        if ($row = mysqli_fetch_assoc($r)) {
            $seller_id   = (int)$row['seller_id'];
            $nama_produk = mysqli_real_escape_string($conn, $row['nama_produk']);
            mysqli_query($conn,
                "INSERT INTO notifikasi (user_id, judul, pesan, tipe, referensi_id)
                 VALUES ($seller_id, 'Produk Ditolak',
                 'Produk \"{$nama_produk}\" Anda ditolak. Silakan periksa kembali aturan platform.',
                 'produk', $id)"
            );
        }

        header("Location: admin.php?tab=produk&msg=rejected");
        exit();
    }

    // Hapus produk permanen
    if ($aksi === 'hapus_produk' && $id > 0) {
        // Hapus file foto dari server
        $r = mysqli_query($conn, "SELECT url_foto FROM foto_produk WHERE produk_id = $id");
        while ($f = mysqli_fetch_assoc($r)) {
            if (file_exists($f['url_foto'])) unlink($f['url_foto']);
        }
        $rUtama = mysqli_query($conn, "SELECT foto_utama FROM produk WHERE id = $id");
        if ($rU = mysqli_fetch_assoc($rUtama)) {
            if ($rU['foto_utama'] && file_exists($rU['foto_utama'])) unlink($rU['foto_utama']);
        }

        $st = mysqli_prepare($conn, "DELETE FROM produk WHERE id = ?");
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        header("Location: admin.php?tab=produk&msg=deleted");
        exit();
    }

    // Toggle aktif/nonaktif user
    if ($aksi === 'toggle_user' && $id > 0) {
        $st = mysqli_prepare($conn, "UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id_users = ?");
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        header("Location: admin.php?tab=users&msg=toggled");
        exit();
    }

    // Hapus user (kecuali admin)
    if ($aksi === 'hapus_user' && $id > 0) {
        $st = mysqli_prepare($conn, "DELETE FROM users WHERE id_users = ? AND role != 'admin'");
        mysqli_stmt_bind_param($st, "i", $id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        header("Location: admin.php?tab=users&msg=deleted");
        exit();
    }
}

// ── Tab & Message ──────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'dashboard';
$msg = $_GET['msg']  ?? '';

// ── KPI Stats ──────────────────────────────────────────────────────────────
$kpi = [];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM produk WHERE status != 'dihapus'");
$kpi['total_produk'] = (int)mysqli_fetch_assoc($r)['c'];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM produk WHERE is_approved = 0 AND status != 'dihapus'");
$kpi['pending'] = (int)mysqli_fetch_assoc($r)['c'];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM produk WHERE is_approved = 1 AND status = 'tersedia'");
$kpi['tersedia'] = (int)mysqli_fetch_assoc($r)['c'];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM produk WHERE status = 'terjual'");
$kpi['terjual'] = (int)mysqli_fetch_assoc($r)['c'];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role != 'admin'");
$kpi['total_users'] = (int)mysqli_fetch_assoc($r)['c'];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'penjual'");
$kpi['penjual'] = (int)mysqli_fetch_assoc($r)['c'];

$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'pembeli'");
$kpi['pembeli'] = (int)mysqli_fetch_assoc($r)['c'];

// ── Data Produk ─────────────────────────────────────────────────────────────
$produk_pending = mysqli_query($conn,
    "SELECT p.*, u.nama AS nama_seller, k.nama AS nama_kategori
     FROM produk p
     LEFT JOIN users u ON p.seller_id = u.id_users
     LEFT JOIN kategori k ON p.kategori_id = k.id
     WHERE p.is_approved = 0 AND p.status != 'dihapus'
     ORDER BY p.created_at DESC"
);

$produk_semua = mysqli_query($conn,
    "SELECT p.*, u.nama AS nama_seller, k.nama AS nama_kategori
     FROM produk p
     LEFT JOIN users u ON p.seller_id = u.id_users
     LEFT JOIN kategori k ON p.kategori_id = k.id
     WHERE p.status != 'dihapus'
     ORDER BY p.created_at DESC
     LIMIT 50"
);

// ── Data Users ──────────────────────────────────────────────────────────────
$users_semua = mysqli_query($conn,
    "SELECT u.*, 
            (SELECT COUNT(*) FROM produk p WHERE p.seller_id = u.id_users) AS jumlah_produk
     FROM users u
     WHERE u.role != 'admin'
     ORDER BY u.created_at DESC"
);

// ── Laporan ─────────────────────────────────────────────────────────────────
$laporan_kategori = mysqli_query($conn,
    "SELECT k.nama, COUNT(p.id) AS jumlah, SUM(p.harga) AS total_nilai
     FROM kategori k
     LEFT JOIN produk p ON p.kategori_id = k.id AND p.status != 'dihapus'
     GROUP BY k.id, k.nama
     ORDER BY jumlah DESC"
);

$laporan_reg = mysqli_query($conn,
    "SELECT DATE(created_at) AS tgl, COUNT(*) AS jumlah
     FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY tgl ASC"
);

// ── Helpers ─────────────────────────────────────────────────────────────────
function fmtRp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function escH($s)  { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function statusBadge($status, $approved) {
    if ($status === 'terjual') return '<span class="badge-status sold">Terjual</span>';
    if ((int)$approved === 1)  return '<span class="badge-status active">Tersedia</span>';
    return '<span class="badge-status pending">Pending</span>';
}
function msgBanner($msg) {
    $map = [
        'approved' => ['Produk berhasil di-approve dan sekarang tampil di halaman belanja.', 'success'],
        'rejected' => ['Produk telah ditolak dan disembunyikan dari halaman belanja.',        'warn'],
        'deleted'  => ['Data berhasil dihapus secara permanen.',                             'danger'],
        'toggled'  => ['Status user berhasil diubah.',                                       'success'],
    ];
    if (!isset($map[$msg])) return '';
    [$text, $type] = $map[$msg];
    return "<div class=\"msg-banner msg-{$type}\"><i class=\"fa-solid fa-circle-check\"></i> {$text}</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard – LapakThriftUMSU</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    :root {
      --primary:#4f8cff;--primary-dark:#1e3a8a;--accent:#7dd3fc;
      --success:#22c55e;--danger:#ef4444;--warn:#f59e0b;
      --text:#0f172a;--text-muted:#64748b;--bg:#f1f5f9;--white:#ffffff;
      --border:#e2e8f0;--radius:14px;--shadow:0 4px 20px rgba(15,23,42,.07);
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);font-size:.93rem;}
    a{color:inherit;text-decoration:none;}

    /* Sidebar */
    .sidebar{position:fixed;top:0;left:0;bottom:0;width:230px;background:var(--primary-dark);display:flex;flex-direction:column;z-index:200;}
    .sidebar-logo{display:flex;align-items:center;gap:10px;padding:20px 20px 16px;color:#fff;font-weight:800;font-size:1rem;border-bottom:1px solid rgba(255,255,255,.1);}
    .sidebar-logo .icon{width:36px;height:36px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;}
    .sidebar-logo span{color:var(--accent);}
    nav.sidebar-nav{flex:1;padding:14px 10px;display:flex;flex-direction:column;gap:4px;overflow-y:auto;}
    .nav-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.7);font-weight:600;transition:.15s;cursor:pointer;}
    .nav-item:hover{background:rgba(255,255,255,.08);color:#fff;}
    .nav-item.active{background:var(--primary);color:#fff;font-weight:700;}
    .nav-item i{width:18px;text-align:center;}
    .sidebar-footer{padding:14px 10px;border-top:1px solid rgba(255,255,255,.1);}
    .sidebar-user{padding:10px 12px;color:rgba(255,255,255,.8);font-size:.82rem;font-weight:600;}
    .sidebar-user strong{display:block;color:#fff;font-size:.88rem;}
    .btn-logout{display:flex;align-items:center;gap:8px;width:100%;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.25);color:#fca5a5;font-weight:700;cursor:pointer;font-family:inherit;font-size:.88rem;transition:.15s;}
    .btn-logout:hover{background:rgba(239,68,68,.28);}

    /* Main */
    .main{margin-left:230px;min-height:100vh;padding:28px 28px 48px;}
    .page-header{margin-bottom:22px;}
    .page-header h1{font-size:1.55rem;font-weight:800;}
    .page-header p{color:var(--text-muted);font-weight:500;margin-top:3px;}

    /* Banner */
    .msg-banner{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;font-weight:700;margin-bottom:18px;}
    .msg-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#15803d;}
    .msg-warn{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#92400e;}
    .msg-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#991b1b;}

    /* KPI */
    .kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
    .kpi-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);position:relative;overflow:hidden;}
    .kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--primary);}
    .kpi-card.green::before{background:var(--success);}
    .kpi-card.amber::before{background:var(--warn);}
    .kpi-card.red::before{background:var(--danger);}
    .kpi-val{font-size:2rem;font-weight:800;line-height:1;}
    .kpi-lbl{margin-top:6px;font-size:.82rem;font-weight:600;color:var(--text-muted);}
    .kpi-icon{position:absolute;top:16px;right:16px;width:38px;height:38px;border-radius:10px;background:rgba(79,140,255,.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:.95rem;}
    .kpi-card.green .kpi-icon{background:rgba(34,197,94,.1);color:var(--success);}
    .kpi-card.amber .kpi-icon{background:rgba(245,158,11,.1);color:var(--warn);}
    .kpi-card.red .kpi-icon{background:rgba(239,68,68,.1);color:var(--danger);}

    /* Card */
    .card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:18px;overflow:hidden;}
    .card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border);}
    .card-head h2{font-size:1rem;font-weight:800;}
    .card-body{padding:20px;}

    /* Table */
    .tbl-wrap{overflow-x:auto;}
    table{width:100%;border-collapse:collapse;}
    th,td{padding:12px 14px;text-align:left;border-bottom:1px solid #f1f5f9;}
    th{font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;background:#f8fafc;}
    td{font-weight:500;vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:#f8fafc;}

    /* Badge */
    .badge-status{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700;}
    .badge-status.pending{background:rgba(245,158,11,.12);color:#b45309;}
    .badge-status.active{background:rgba(34,197,94,.12);color:#15803d;}
    .badge-status.sold{background:rgba(100,116,139,.12);color:#475569;}
    .role-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:700;}
    .role-penjual{background:rgba(79,140,255,.1);color:var(--primary-dark);}
    .role-pembeli{background:rgba(100,116,139,.1);color:#475569;}

    /* Buttons */
    .btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;border:none;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap;}
    .btn:hover{filter:brightness(.92);transform:translateY(-1px);}
    .btn-approve{background:rgba(34,197,94,.12);color:#15803d;}
    .btn-tolak{background:rgba(245,158,11,.12);color:#b45309;}
    .btn-delete{background:rgba(239,68,68,.1);color:#b91c1c;}
    .btn-toggle{background:rgba(79,140,255,.1);color:var(--primary-dark);}
    .btn-sm{padding:5px 10px;font-size:.78rem;}

    /* Misc */
    .empty-state{padding:36px;text-align:center;color:var(--text-muted);font-weight:600;}
    .empty-state i{font-size:2rem;margin-bottom:10px;display:block;opacity:.4;}
    .stat-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;}
    .stat-row:last-child{border-bottom:none;}
    .stat-row .bar-wrap{flex:1;margin:0 16px;background:#f1f5f9;border-radius:999px;height:7px;overflow:hidden;}
    .stat-row .bar-fill{height:100%;border-radius:999px;background:var(--primary);}
    .prod-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--border);}
    .prod-thumb-placeholder{width:44px;height:44px;border-radius:8px;background:#f1f5f9;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}

    @media(max-width:900px){.sidebar{display:none;}.main{margin-left:0;padding:18px;}.kpi-grid{grid-template-columns:1fr 1fr;}.info-grid{grid-template-columns:1fr;}}
    @media(max-width:600px){.kpi-grid{grid-template-columns:1fr;}}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon"><i class="fa-solid fa-shop"></i></div>
    Lapak<span>Thrift</span>
  </div>
  <nav class="sidebar-nav">
    <a href="admin.php?tab=dashboard" class="nav-item <?= $tab==='dashboard'?'active':'' ?>">
      <i class="fa-solid fa-gauge-high"></i> Dashboard
    </a>
    <a href="admin.php?tab=produk" class="nav-item <?= $tab==='produk'?'active':'' ?>">
      <i class="fa-solid fa-box-open"></i> Produk
      <?php if ($kpi['pending'] > 0): ?>
        <span style="margin-left:auto;background:var(--warn);color:#fff;border-radius:999px;padding:1px 8px;font-size:.72rem;font-weight:800;"><?= $kpi['pending'] ?></span>
      <?php endif; ?>
    </a>
    <a href="admin.php?tab=users" class="nav-item <?= $tab==='users'?'active':'' ?>">
      <i class="fa-solid fa-users"></i> Pengguna
    </a>
    <a href="admin.php?tab=laporan" class="nav-item <?= $tab==='laporan'?'active':'' ?>">
      <i class="fa-solid fa-chart-bar"></i> Laporan
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <strong><?= escH($_SESSION['username']) ?></strong>
      <?= escH($_SESSION['email'] ?? '') ?>
    </div>
    <button class="btn-logout" onclick="window.location.href='admin.php?logout=1'">
      <i class="fa-solid fa-right-from-bracket"></i> Logout
    </button>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <?= msgBanner($msg) ?>

  <?php /* ══════════════ DASHBOARD ══════════════ */ if ($tab === 'dashboard'): ?>

  <div class="page-header">
    <h1><i class="fa-solid fa-gauge-high" style="color:var(--primary);margin-right:8px;"></i>Dashboard</h1>
    <p>Selamat datang, <strong><?= escH($_SESSION['username']) ?></strong>. Berikut ringkasan platform LapakThriftUMSU.</p>
  </div>

  <div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-val"><?= $kpi['total_produk'] ?></div><div class="kpi-lbl">Total Produk</div><div class="kpi-icon"><i class="fa-solid fa-box"></i></div></div>
    <div class="kpi-card amber"><div class="kpi-val"><?= $kpi['pending'] ?></div><div class="kpi-lbl">Menunggu Approval</div><div class="kpi-icon"><i class="fa-solid fa-clock"></i></div></div>
    <div class="kpi-card green"><div class="kpi-val"><?= $kpi['tersedia'] ?></div><div class="kpi-lbl">Produk Tersedia</div><div class="kpi-icon"><i class="fa-solid fa-check-circle"></i></div></div>
    <div class="kpi-card"><div class="kpi-val"><?= $kpi['total_users'] ?></div><div class="kpi-lbl">Total Pengguna</div><div class="kpi-icon"><i class="fa-solid fa-users"></i></div></div>
  </div>

  <?php
  mysqli_data_seek($produk_pending, 0);
  $pending_rows = [];
  while ($row = mysqli_fetch_assoc($produk_pending)) { $pending_rows[] = $row; }
  ?>

  <div class="card">
    <div class="card-head">
      <h2><i class="fa-solid fa-clock" style="color:var(--warn);margin-right:6px;"></i>Produk Menunggu Approval (<?= count($pending_rows) ?>)</h2>
      <a href="admin.php?tab=produk" style="font-size:.82rem;color:var(--primary);font-weight:700;">Lihat semua →</a>
    </div>
    <?php if (empty($pending_rows)): ?>
      <div class="empty-state"><i class="fa-solid fa-circle-check"></i>Tidak ada produk yang menunggu approval.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Produk</th><th>Penjual</th><th>Kategori</th><th>Harga</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($pending_rows, 0, 5) as $p): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($p['foto_utama']): ?>
                  <img src="<?= escH($p['foto_utama']) ?>" class="prod-thumb" alt="" onerror="this.style.display='none'"/>
                <?php else: ?>
                  <div class="prod-thumb-placeholder"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:700;"><?= escH($p['nama_produk']) ?></div>
                  <div style="font-size:.78rem;color:var(--text-muted);"><?= escH($p['kondisi']) ?></div>
                </div>
              </div>
            </td>
            <td><?= escH($p['nama_seller'] ?? '-') ?></td>
            <td><?= escH($p['nama_kategori'] ?? '-') ?></td>
            <td><?= fmtRp($p['harga']) ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="aksi" value="approve">
                  <button class="btn btn-approve btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="aksi" value="tolak">
                  <button class="btn btn-tolak btn-sm"><i class="fa-solid fa-xmark"></i> Tolak</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>


  <?php /* ══════════════ PRODUK ══════════════ */ elseif ($tab === 'produk'): ?>

  <div class="page-header">
    <h1><i class="fa-solid fa-box-open" style="color:var(--primary);margin-right:8px;"></i>Manajemen Produk</h1>
    <p>Approve, tolak, atau hapus listing produk dari penjual.</p>
  </div>

  <?php
  mysqli_data_seek($produk_pending, 0);
  $pending_rows = [];
  while ($row = mysqli_fetch_assoc($produk_pending)) { $pending_rows[] = $row; }
  ?>

  <!-- Pending -->
  <div class="card">
    <div class="card-head">
      <h2><i class="fa-solid fa-clock" style="color:var(--warn);margin-right:6px;"></i>Menunggu Approval (<?= count($pending_rows) ?>)</h2>
    </div>
    <?php if (empty($pending_rows)): ?>
      <div class="empty-state"><i class="fa-solid fa-circle-check"></i>Tidak ada produk yang menunggu approval.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Produk</th><th>Penjual</th><th>Kategori</th><th>Harga</th><th>Kondisi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($pending_rows as $p): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($p['foto_utama']): ?>
                  <img src="<?= escH($p['foto_utama']) ?>" class="prod-thumb" alt="" onerror="this.style.display='none'"/>
                <?php else: ?>
                  <div class="prod-thumb-placeholder"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
                <span style="font-weight:700;"><?= escH($p['nama_produk']) ?></span>
              </div>
            </td>
            <td><?= escH($p['nama_seller'] ?? '-') ?></td>
            <td><?= escH($p['nama_kategori'] ?? '-') ?></td>
            <td><?= fmtRp($p['harga']) ?></td>
            <td><?= escH($p['kondisi']) ?></td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="aksi" value="approve">
                  <button class="btn btn-approve btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                </form>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="aksi" value="tolak">
                  <button class="btn btn-tolak btn-sm"><i class="fa-solid fa-xmark"></i> Tolak</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus produk ini permanen?')">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="aksi" value="hapus_produk">
                  <button class="btn btn-delete btn-sm"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Semua Produk -->
  <div class="card">
    <div class="card-head">
      <h2><i class="fa-solid fa-list" style="margin-right:6px;color:var(--text-muted);"></i>Semua Produk (maks. 50)</h2>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Produk</th><th>Penjual</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php
          mysqli_data_seek($produk_semua, 0);
          $any = false;
          while ($p = mysqli_fetch_assoc($produk_semua)):
            $any = true;
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($p['foto_utama']): ?>
                  <img src="<?= escH($p['foto_utama']) ?>" class="prod-thumb" alt="" onerror="this.style.display='none'"/>
                <?php else: ?>
                  <div class="prod-thumb-placeholder"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
                <div>
                  <div style="font-weight:700;"><?= escH($p['nama_produk']) ?></div>
                  <div style="font-size:.75rem;color:var(--text-muted);"><?= escH($p['kondisi']) ?></div>
                </div>
              </div>
            </td>
            <td><?= escH($p['nama_seller'] ?? '-') ?></td>
            <td><?= escH($p['nama_kategori'] ?? '-') ?></td>
            <td><?= fmtRp($p['harga']) ?></td>
            <td><?= (int)$p['stok'] ?></td>
            <td><?= statusBadge($p['status'], $p['is_approved']) ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('Hapus produk ini permanen?')">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="aksi" value="hapus_produk">
                <button class="btn btn-delete btn-sm"><i class="fa-solid fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$any): ?><tr><td colspan="7" class="empty-state">Belum ada produk.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>


  <?php /* ══════════════ USERS ══════════════ */ elseif ($tab === 'users'): ?>

  <div class="page-header">
    <h1><i class="fa-solid fa-users" style="color:var(--primary);margin-right:8px;"></i>Manajemen Pengguna</h1>
    <p>Monitor akun pembeli & penjual. Nonaktifkan atau hapus akun yang melanggar ketentuan.</p>
  </div>

  <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);max-width:600px;">
    <div class="kpi-card"><div class="kpi-val"><?= $kpi['total_users'] ?></div><div class="kpi-lbl">Total Pengguna</div><div class="kpi-icon"><i class="fa-solid fa-users"></i></div></div>
    <div class="kpi-card green"><div class="kpi-val"><?= $kpi['penjual'] ?></div><div class="kpi-lbl">Penjual</div><div class="kpi-icon"><i class="fa-solid fa-store"></i></div></div>
    <div class="kpi-card"><div class="kpi-val"><?= $kpi['pembeli'] ?></div><div class="kpi-lbl">Pembeli</div><div class="kpi-icon"><i class="fa-solid fa-user"></i></div></div>
  </div>

  <div class="card">
    <div class="card-head">
      <h2><i class="fa-solid fa-table-list" style="margin-right:6px;color:var(--text-muted);"></i>Daftar Pengguna</h2>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Nama</th><th>Email</th><th>No. HP</th><th>Role</th><th>Produk</th><th>Terdaftar</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php
          $any = false;
          while ($u = mysqli_fetch_assoc($users_semua)):
            $any = true;
          ?>
          <tr>
            <td style="font-weight:700;"><?= escH($u['nama']) ?></td>
            <td style="color:var(--text-muted);"><?= escH($u['email']) ?></td>
            <td style="color:var(--text-muted);"><?= escH($u['no_hp'] ?? '-') ?></td>
            <td><span class="role-badge role-<?= escH($u['role']) ?>"><?= ucfirst(escH($u['role'])) ?></span></td>
            <td><?= (int)$u['jumlah_produk'] ?></td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td><?= (int)$u['is_active']===1 ? '<span class="badge-status active">Aktif</span>' : '<span class="badge-status sold">Nonaktif</span>' ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="id" value="<?= (int)$u['id_users'] ?>">
                  <input type="hidden" name="aksi" value="toggle_user">
                  <button class="btn btn-toggle btn-sm" title="<?= (int)$u['is_active']?'Nonaktifkan':'Aktifkan' ?>">
                    <i class="fa-solid <?= (int)$u['is_active']?'fa-user-slash':'fa-user-check' ?>"></i>
                  </button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus akun ini permanen?')">
                  <input type="hidden" name="id" value="<?= (int)$u['id_users'] ?>">
                  <input type="hidden" name="aksi" value="hapus_user">
                  <button class="btn btn-delete btn-sm"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          <?php if (!$any): ?><tr><td colspan="8" class="empty-state">Belum ada pengguna.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>


  <?php /* ══════════════ LAPORAN ══════════════ */ elseif ($tab === 'laporan'): ?>

  <div class="page-header">
    <h1><i class="fa-solid fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i>Laporan Sistem</h1>
    <p>Ringkasan statistik produk, kategori, dan aktivitas pengguna.</p>
  </div>

  <div class="info-grid">
    <!-- Produk per Kategori -->
    <div class="card">
      <div class="card-head"><h2><i class="fa-solid fa-tags" style="margin-right:6px;color:var(--text-muted);"></i>Produk per Kategori</h2></div>
      <div class="card-body">
        <?php
        $cat_rows = [];
        while ($row = mysqli_fetch_assoc($laporan_kategori)) { $cat_rows[] = $row; }
        $max_cat = max(1, ...array_map(fn($r)=>(int)$r['jumlah'], $cat_rows ?: [['jumlah'=>1]]));
        foreach ($cat_rows as $row):
          $pct = round(($row['jumlah'] / $max_cat) * 100);
        ?>
        <div class="stat-row">
          <span style="font-weight:700;min-width:90px;"><?= escH($row['nama']) ?></span>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
          <span style="font-weight:800;min-width:24px;text-align:right;"><?= (int)$row['jumlah'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($cat_rows)): ?><p style="color:var(--text-muted);">Belum ada data.</p><?php endif; ?>
      </div>
    </div>

    <!-- Ringkasan Platform -->
    <div class="card">
      <div class="card-head"><h2><i class="fa-solid fa-circle-info" style="margin-right:6px;color:var(--text-muted);"></i>Ringkasan Platform</h2></div>
      <div class="card-body">
        <?php foreach ([
          ['Total Produk Aktif','fa-box',$kpi['total_produk'],'var(--primary)'],
          ['Pending Approval','fa-clock',$kpi['pending'],'var(--warn)'],
          ['Produk Tersedia','fa-check',$kpi['tersedia'],'var(--success)'],
          ['Produk Terjual','fa-bag-shopping',$kpi['terjual'],'#8b5cf6'],
          ['Total Penjual','fa-store',$kpi['penjual'],'var(--primary)'],
          ['Total Pembeli','fa-user',$kpi['pembeli'],'var(--text-muted)'],
        ] as [$lbl,$icon,$val,$color]): ?>
        <div class="stat-row">
          <div style="display:flex;align-items:center;gap:10px;min-width:170px;">
            <div style="width:30px;height:30px;border-radius:8px;background:<?= $color ?>1a;display:flex;align-items:center;justify-content:center;color:<?= $color ?>;font-size:.78rem;"><i class="fa-solid <?= $icon ?>"></i></div>
            <span style="font-weight:600;font-size:.88rem;"><?= $lbl ?></span>
          </div>
          <span style="font-weight:800;font-size:1.05rem;"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Registrasi 7 Hari -->
    <div class="card">
      <div class="card-head"><h2><i class="fa-solid fa-calendar" style="margin-right:6px;color:var(--text-muted);"></i>Registrasi 7 Hari Terakhir</h2></div>
      <div class="card-body">
        <?php
        $reg_rows = [];
        while ($row = mysqli_fetch_assoc($laporan_reg)) { $reg_rows[] = $row; }
        if (empty($reg_rows)):
        ?><p style="color:var(--text-muted);font-size:.88rem;">Belum ada registrasi dalam 7 hari terakhir.</p>
        <?php else:
          $max_reg = max(1, ...array_map(fn($r)=>(int)$r['jumlah'], $reg_rows));
          foreach ($reg_rows as $row):
            $pct = round(($row['jumlah'] / $max_reg) * 100);
        ?>
        <div class="stat-row">
          <span style="font-weight:700;min-width:90px;font-size:.82rem;"><?= date('d M', strtotime($row['tgl'])) ?></span>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:var(--success);"></div></div>
          <span style="font-weight:800;min-width:24px;text-align:right;"><?= (int)$row['jumlah'] ?></span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Aksi Cepat -->
    <div class="card">
      <div class="card-head"><h2><i class="fa-solid fa-bolt" style="margin-right:6px;color:var(--text-muted);"></i>Aksi Cepat</h2></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ([
          ['admin.php?tab=produk','fa-clock','var(--warn)',"Review {$kpi['pending']} Produk Pending"],
          ['admin.php?tab=users','fa-users','var(--primary)',"Kelola {$kpi['total_users']} Pengguna"],
          ['userafterlogin.php','fa-arrow-up-right-from-square','var(--text-muted)','Lihat Halaman Belanja'],
        ] as [$href,$icon,$color,$label]): ?>
        <a href="<?= $href ?>" <?= $href==='userafterlogin.php'?'target="_blank"':'' ?>
           style="display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;background:#f8fafc;border:1px solid var(--border);font-weight:700;transition:.15s;"
           onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#f8fafc'">
          <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>;"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

</main>
</body>
</html>
