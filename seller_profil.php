<?php
// seller_profil.php — DISESUAIKAN dengan skema DB lapakthriftumsu
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;
$username   = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$role       = $isLoggedIn ? ($_SESSION['role'] ?? 'pembeli') : 'guest';

$sellerId = (int)($_GET['id'] ?? 0);
if (!$sellerId) {
    header('Location: userafterlogin.php');
    exit;
}

$db = getDB();

// ── Data seller + profil toko ──────────────────────────────────
$stmt = $db->prepare("
    SELECT u.id_users, u.nama, u.email, u.no_hp, u.foto_profil, u.created_at,
           sp.nama_toko, sp.deskripsi AS toko_deskripsi, sp.foto_toko,
           sp.kota, sp.rating, sp.total_terjual
    FROM users u
    LEFT JOIN seller_profiles sp ON sp.user_id = u.id_users
    WHERE u.id_users = :id AND u.role IN ('penjual','admin') AND u.is_active = 1
");
$stmt->execute([':id' => $sellerId]);
$seller = $stmt->fetch();

if (!$seller) {
    header('Location: userafterlogin.php');
    exit;
}

// ── Produk milik seller ────────────────────────────────────────
$produkStmt = $db->prepare("
    SELECT p.id, p.nama_produk, p.harga, p.stok, p.kondisi,
           p.foto_utama, p.views, p.created_at,
           k.nama AS kategori
    FROM produk p
    JOIN kategori k ON k.id = p.kategori_id
    WHERE p.seller_id = :sid AND p.status = 'tersedia'
    ORDER BY p.created_at DESC
");
$produkStmt->execute([':sid' => $sellerId]);
$produkList = $produkStmt->fetchAll();
$totalProduk = count($produkList);

// ── Rating rata-rata dari semua ulasan produk seller ──────────
$ratingStats = $db->prepare("
    SELECT
        AVG(ul.rating)   AS avg_r,
        COUNT(ul.id)     AS total_r,
        SUM(ul.rating=5) AS r5,
        SUM(ul.rating=4) AS r4,
        SUM(ul.rating=3) AS r3,
        SUM(ul.rating=2) AS r2,
        SUM(ul.rating=1) AS r1
    FROM ulasan ul
    JOIN produk p ON p.id = ul.produk_id
    WHERE p.seller_id = :sid
");
$ratingStats->execute([':sid' => $sellerId]);
$rs          = $ratingStats->fetch();
$avgRating   = round((float)($rs['avg_r']  ?? 0), 1);
$totalRev    = (int)($rs['total_r'] ?? 0);

// ── Semua ulasan produk seller ─────────────────────────────────
$ulasanStmt = $db->prepare("
    SELECT ul.rating, ul.komentar, ul.created_at,
           u.nama AS reviewer, u.foto_profil,
           p.nama_produk
    FROM ulasan ul
    JOIN users   u ON u.id_users = ul.user_id
    JOIN produk  p ON p.id       = ul.produk_id
    WHERE p.seller_id = :sid
    ORDER BY ul.created_at DESC
    LIMIT 30
");
$ulasanStmt->execute([':sid' => $sellerId]);
$ulasanList = $ulasanStmt->fetchAll();

// ── Cek produk yang belum direview user ──────────────────────
$produkYangBisaDiReview = [];
if ($isLoggedIn && $userId !== $sellerId) {
    $cekReview = $db->prepare("
        SELECT p.id, p.nama_produk
        FROM produk p
        WHERE p.seller_id = :sid
          AND p.status = 'tersedia'
          AND p.id NOT IN (
              SELECT ul.produk_id FROM ulasan ul WHERE ul.user_id = :uid
          )
        ORDER BY p.nama_produk ASC
    ");
    $cekReview->execute([':sid' => $sellerId, ':uid' => $userId]);
    $produkYangBisaDiReview = $cekReview->fetchAll();
}

// ── Highlight produk dari halaman detail ──────────────────────
$fromProduk = (int)($_GET['from_produk'] ?? 0);

// ── Format helper ─────────────────────────────────────────────
/**
 * Menghasilkan URL foto yang benar.
 * Mengembalikan string URL jika ada, atau NULL jika tidak ada foto.
 */
function fotoUrl($path) {
    if (!$path || trim($path) === '' || $path === 'default.jpg') return null;
    if (str_starts_with($path, 'http')) return $path;
    // Hindari double prefix 'uploads/'
    $clean = ltrim($path, '/');
    if (str_starts_with($clean, 'uploads/')) return $clean;
    return 'uploads/' . $clean;
}

function formatWaNumber($hp) {
    $hp = preg_replace('/[^0-9]/', '', $hp ?? '');
    if (!$hp) return '';
    if (str_starts_with($hp, '0')) return '62' . substr($hp, 1);
    if (!str_starts_with($hp, '62')) return '62' . $hp;
    return $hp;
}

$fotoToko   = fotoUrl($seller['foto_toko']);
$fotoProfil = fotoUrl($seller['foto_profil']);
$namaToko   = $seller['nama_toko'] ?: $seller['nama'];
$isSelf     = $isLoggedIn && $userId === (int)$seller['id_users'];
$sellerWa   = formatWaNumber($seller['no_hp'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($namaToko) ?> – LapakThriftUMSU</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
/* ══════════════════════════════════
   ROOT & RESET
══════════════════════════════════ */
:root{
  --primary:#4f8cff; --primary-dark:#1e3a8a;
  --accent:#f59e0b;  --green:#16a34a; --red:#dc2626;
  --bg:#f0f4fa; --surface:#fff;
  --text:#0f172a; --muted:#64748b; --border:#dbe4f0;
  --shadow:0 10px 40px rgba(79,140,255,.13);
  --shadow-sm:0 4px 16px rgba(15,23,42,.07);
  --radius:20px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--text);}
a{text-decoration:none;color:inherit;}

/* NAV */
nav{position:sticky;top:0;z-index:300;background:rgba(255,255,255,.97);
    backdrop-filter:blur(16px);border-bottom:1px solid rgba(219,228,240,.7);
    box-shadow:0 1px 20px rgba(15,23,42,.06);}
.nav-inner{max-width:1340px;margin:auto;padding:13px 24px;display:flex;align-items:center;gap:14px;}
.logo{display:flex;align-items:center;gap:10px;font-size:1.18rem;font-weight:800;color:var(--primary-dark);}
.logo .icon{width:38px;height:38px;border-radius:11px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;}
.logo span{color:var(--primary);}
.back-btn{display:flex;align-items:center;gap:7px;padding:9px 16px;border-radius:999px;
  border:1.5px solid var(--border);background:#fff;font-weight:700;font-size:.84rem;
  cursor:pointer;transition:.2s;color:var(--text);}
.back-btn:hover{background:var(--bg);box-shadow:var(--shadow-sm);}
.nav-user{margin-left:auto;display:flex;align-items:center;gap:8px;
  font-size:.87rem;font-weight:700;color:var(--primary-dark);}
.nav-avatar{width:32px;height:32px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;}

/* WRAP */
.wrap{max-width:1340px;margin:26px auto 60px;padding:0 24px;}

/* HERO CARD */
.profile-hero{background:var(--surface);border-radius:var(--radius);
  border:1px solid var(--border);overflow:hidden;margin-bottom:24px;
  box-shadow:var(--shadow-sm);}
.hero-banner{height:120px;
  background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 50%,#4f8cff 100%);
  position:relative;overflow:hidden;}
.hero-banner::after{content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='20' cy='20' r='3'/%3E%3C/g%3E%3C/svg%3E");}
.hero-body{padding:0 28px 28px;}
.seller-top-row{display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:22px;}
.seller-ava{width:96px;height:96px;border-radius:22px;object-fit:cover;
  border:4px solid #fff;box-shadow:0 8px 28px rgba(0,0,0,.18);
  background:#e2e8f0;flex-shrink:0;margin-top:-36px;}
.seller-ava-ph{width:96px;height:96px;border-radius:22px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  border:4px solid #fff;box-shadow:0 8px 28px rgba(0,0,0,.18);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:2.2rem;font-weight:800;flex-shrink:0;margin-top:-36px;}
.seller-info-main{padding-top:14px;flex:1;min-width:200px;}
.seller-nama{font-size:1.6rem;font-weight:800;line-height:1.2;margin-bottom:6px;}
.seller-sub{color:var(--muted);font-size:.88rem;margin-bottom:14px;
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.seller-stats{display:flex;gap:24px;flex-wrap:wrap;}
.stat-item{text-align:center;}
.stat-val{font-size:1.25rem;font-weight:800;color:var(--primary-dark);}
.stat-lbl{font-size:.75rem;color:var(--muted);font-weight:600;margin-top:2px;}
.seller-desc{font-size:.88rem;color:#374151;line-height:1.7;margin-top:14px;
  max-width:600px;padding:13px 16px;background:var(--bg);
  border-radius:12px;border-left:3px solid var(--primary);}

/* Action buttons */
.profile-actions{display:flex;gap:10px;margin-top:22px;padding-top:20px;
  border-top:1px solid var(--border);flex-wrap:wrap;align-items:center;}
.btn-wa{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;
  border-radius:13px;background:#25D366;color:#fff;font-weight:700;font-size:.88rem;
  border:none;cursor:pointer;font-family:inherit;transition:.2s;text-decoration:none;}
.btn-wa:hover{opacity:.9;transform:translateY(-1px);}
.btn-chat{display:inline-flex;align-items:center;gap:8px;padding:11px 20px;
  border-radius:13px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff;font-weight:700;font-size:.88rem;border:none;cursor:pointer;
  font-family:inherit;transition:.2s;}
.btn-chat:hover{opacity:.9;transform:translateY(-1px);}
.notice-box{border-radius:12px;padding:12px 16px;font-size:.84rem;font-weight:700;
  display:flex;align-items:center;gap:10px;margin-bottom:0;}
.notice-box.info{background:#eff6ff;border:1px solid #bfdbfe;color:var(--primary-dark);}
.notice-box.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.notice-box.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.login-notice{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;
  padding:11px 15px;font-size:.84rem;color:#92400e;font-weight:700;
  display:inline-flex;align-items:center;gap:7px;}
.login-notice a{color:var(--primary);text-decoration:underline;}

/* TABS */
.tabs-bar{display:flex;gap:4px;background:var(--surface);border-radius:var(--radius);
  padding:6px;margin-bottom:20px;border:1px solid var(--border);box-shadow:var(--shadow-sm);}
.tab-btn{flex:1;padding:10px 16px;border-radius:14px;border:none;background:transparent;
  font-weight:700;font-size:.88rem;color:var(--muted);cursor:pointer;
  font-family:inherit;transition:.2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.tab-btn:hover{color:var(--primary-dark);background:var(--bg);}
.tab-btn.active{background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff;box-shadow:0 4px 16px rgba(79,140,255,.3);}
.tab-count{background:rgba(255,255,255,.25);padding:2px 8px;border-radius:99px;font-size:.78rem;}
.tab-btn:not(.active) .tab-count{background:var(--bg);color:var(--muted);}
.tab-panel{display:none;}
.tab-panel.active{display:block;}

/* PRODUK GRID */
.section-header{display:flex;align-items:center;justify-content:space-between;
  margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.section-title{font-size:1.2rem;font-weight:800;display:flex;align-items:center;gap:10px;}
.prod-count{background:var(--bg);border:1px solid var(--border);padding:4px 12px;
  border-radius:999px;font-size:.8rem;font-weight:700;color:var(--muted);}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;}
.prod-card{background:var(--surface);border-radius:18px;overflow:hidden;
  border:1px solid var(--border);cursor:pointer;transition:transform .25s,box-shadow .25s,border-color .25s;}
.prod-card:hover{transform:translateY(-5px);box-shadow:var(--shadow);}
.prod-card.highlighted{border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(79,140,255,.2),var(--shadow);}

/* ── FOTO PRODUK (FIXED) ── */
.prod-img-wrap{width:100%;height:220px;overflow:hidden;background:#e2e8f0;position:relative;}
.prod-img-wrap img{
  width:100%;height:100%;object-fit:cover;display:block;
  transition:opacity .3s;
}
/* Placeholder saat tidak ada foto / foto gagal load */
.prod-img-placeholder{
  width:100%;height:100%;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  background:linear-gradient(135deg,#e2e8f0,#f1f5f9);
  color:#94a3b8;gap:8px;
}
.prod-img-placeholder i{font-size:2.4rem;opacity:.5;}
.prod-img-placeholder span{font-size:.75rem;font-weight:600;opacity:.6;}

.prod-body{padding:13px;}
.prod-name{font-weight:700;font-size:.92rem;margin-bottom:4px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.prod-kondisi{font-size:.77rem;color:var(--muted);margin-bottom:7px;}
.prod-price{font-size:.98rem;font-weight:800;color:var(--primary-dark);}
.prod-stok{font-size:.77rem;font-weight:700;margin-top:4px;}
.in-stok{color:var(--green);} .out-stok{color:var(--red);}
.empty{text-align:center;color:var(--muted);padding:60px 20px;grid-column:1/-1;}
.empty .emoji{font-size:3rem;margin-bottom:12px;}

/* REVIEW SECTION */
.review-wrap{background:var(--surface);border-radius:var(--radius);
  border:1px solid var(--border);padding:28px;box-shadow:var(--shadow-sm);}
.rating-overview{display:flex;gap:28px;align-items:center;padding-bottom:24px;
  margin-bottom:24px;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.rating-big{text-align:center;min-width:90px;}
.rating-number{font-size:3rem;font-weight:800;color:var(--primary-dark);line-height:1;}
.rating-stars{color:var(--accent);font-size:1rem;margin:6px 0 4px;}
.rating-count{color:var(--muted);font-size:.8rem;font-weight:600;}
.bar-stack{flex:1;min-width:180px;}
.bar-row{display:flex;align-items:center;gap:10px;font-size:.82rem;
  color:var(--muted);margin-bottom:7px;}
.bar-track{flex:1;height:7px;background:#e2e8f0;border-radius:99px;overflow:hidden;}
.bar-fill{height:100%;background:linear-gradient(90deg,var(--accent),#f97316);
  border-radius:99px;transition:width .8s ease;}

/* Form review */
.review-form-box{background:linear-gradient(135deg,#f0f7ff,#fafbff);
  border:1.5px solid var(--border);border-radius:18px;padding:24px;margin-bottom:28px;}
.produk-select{width:100%;border:1.5px solid var(--border);border-radius:13px;
  padding:11px 14px;font-family:inherit;font-size:.9rem;outline:none;
  background:#fff;color:var(--text);margin-bottom:16px;transition:.2s;cursor:pointer;}
.produk-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,140,255,.12);}
.rfb-title{font-size:1rem;font-weight:800;color:var(--primary-dark);
  margin-bottom:4px;display:flex;align-items:center;gap:8px;}
.rfb-sub{font-size:.83rem;color:var(--muted);margin-bottom:18px;}
.star-picker{display:flex;gap:8px;margin-bottom:6px;}
.star-picker i{font-size:2rem;cursor:pointer;color:#d1d5db;transition:color .12s,transform .12s;}
.star-picker i.lit{color:var(--accent);}
.star-picker i:hover{transform:scale(1.2);}
.star-label{font-size:.83rem;color:var(--muted);font-weight:600;
  margin-bottom:16px;min-height:18px;}
.review-textarea{width:100%;border:1.5px solid var(--border);border-radius:13px;
  padding:13px 15px;font-family:inherit;font-size:.9rem;resize:vertical;
  min-height:100px;outline:none;transition:.2s;background:#fff;color:var(--text);}
.review-textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,140,255,.12);}
.char-count{text-align:right;font-size:.78rem;color:var(--muted);margin-top:5px;}
.char-count.warn{color:var(--red);}
.btn-kirim-review{margin-top:14px;padding:12px 28px;border-radius:13px;border:none;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff;font-weight:800;font-size:.92rem;cursor:pointer;font-family:inherit;
  transition:.2s;display:inline-flex;align-items:center;gap:8px;}
.btn-kirim-review:hover:not(:disabled){opacity:.9;transform:translateY(-1px);}
.btn-kirim-review:disabled{opacity:.5;cursor:not-allowed;}

/* Review cards */
.review-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:16px;margin-top:8px;}
.review-card{background:#f8fbff;border-radius:16px;padding:18px;
  border:1px solid var(--border);transition:.2s;}
.review-card:hover{box-shadow:var(--shadow-sm);}
.rc-top{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
.rc-ava{width:42px;height:42px;border-radius:50%;object-fit:cover;
  background:#e2e8f0;display:block;}
.rc-ava-ph{width:42px;height:42px;border-radius:50%;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-weight:800;font-size:.9rem;flex-shrink:0;}
.rc-name{font-weight:700;font-size:.9rem;}
.rc-meta{font-size:.77rem;color:var(--muted);}
.rc-stars{color:var(--accent);font-size:.88rem;margin-bottom:6px;}
.rc-produk{font-size:.78rem;color:var(--primary);font-weight:600;
  margin-bottom:6px;display:flex;align-items:center;gap:4px;}
.rc-text{font-size:.87rem;line-height:1.7;color:#374151;}
.no-review{text-align:center;color:var(--muted);padding:50px;font-size:.9rem;}

/* TOAST */
.toast{position:fixed;bottom:24px;left:50%;
  transform:translateX(-50%) translateY(20px);
  background:#0f172a;color:#fff;padding:14px 24px;border-radius:14px;
  font-weight:700;font-size:.88rem;z-index:999;opacity:0;
  transition:all .3s;pointer-events:none;white-space:nowrap;}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
.toast.green{background:var(--green);}
.toast.red{background:var(--red);}

@keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.profile-hero,.tabs-bar{animation:fadeUp .4s ease both;}

@media(max-width:700px){
  .grid{grid-template-columns:1fr 1fr;}
  .seller-nama{font-size:1.3rem;}
  .hero-body{padding:0 16px 22px;}
  .wrap{padding:0 14px;}
  .review-list{grid-template-columns:1fr;}
  .rating-overview{flex-direction:column;align-items:flex-start;gap:16px;}
  .tabs-bar{flex-direction:row;}
}
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <div class="nav-inner">
    <a href="userafterlogin.php" class="logo">
      <div class="icon"><i class="fa-solid fa-shop"></i></div>
      lapakthrift<span>UMSU</span>
    </a>
    <button class="back-btn" onclick="history.back()">
      <i class="fa-solid fa-arrow-left"></i> Kembali
    </button>
    <?php if ($isLoggedIn): ?>
    <div class="nav-user">
      <div class="nav-avatar"><?= strtoupper(substr($username,0,1)) ?></div>
      <?= $username ?>
    </div>
    <?php endif; ?>
  </div>
</nav>

<div class="wrap">

  <!-- PROFILE HERO -->
  <div class="profile-hero">
    <div class="hero-banner"></div>
    <div class="hero-body">
      <div class="seller-top-row">
        <?php if ($fotoToko): ?>
          <img class="seller-ava" src="<?= htmlspecialchars($fotoToko) ?>" alt="toko"
               onerror="this.style.display='none';document.getElementById('ava-ph').style.display='flex'"/>
          <div class="seller-ava-ph" id="ava-ph" style="display:none"><?= strtoupper(substr($namaToko,0,1)) ?></div>
        <?php elseif ($fotoProfil): ?>
          <img class="seller-ava" src="<?= htmlspecialchars($fotoProfil) ?>" alt="profil"
               onerror="this.style.display='none';document.getElementById('ava-ph2').style.display='flex'"/>
          <div class="seller-ava-ph" id="ava-ph2" style="display:none"><?= strtoupper(substr($namaToko,0,1)) ?></div>
        <?php else: ?>
          <div class="seller-ava-ph"><?= strtoupper(substr($namaToko,0,1)) ?></div>
        <?php endif; ?>

        <div class="seller-info-main">
          <h1 class="seller-nama"><?= htmlspecialchars($namaToko) ?></h1>
          <div class="seller-sub">
            <?php if ($seller['kota']): ?>
              <span><i class="fa-solid fa-location-dot" style="color:var(--primary);"></i> <?= htmlspecialchars($seller['kota']) ?></span>
              <span style="width:4px;height:4px;border-radius:50%;background:var(--border);display:inline-block;"></span>
            <?php endif; ?>
            <span><i class="fa-regular fa-calendar" style="color:var(--primary);"></i>
              Bergabung <?= date('M Y', strtotime($seller['created_at'])) ?>
            </span>
          </div>
          <div class="seller-stats">
            <div class="stat-item">
              <div class="stat-val"><?= $totalProduk ?></div>
              <div class="stat-lbl">Produk</div>
            </div>
            <div class="stat-item">
              <div class="stat-val"><?= (int)($seller['total_terjual'] ?? 0) ?></div>
              <div class="stat-lbl">Terjual</div>
            </div>
            <?php if ($avgRating > 0): ?>
            <div class="stat-item">
              <div class="stat-val">
                <i class="fa-solid fa-star" style="color:var(--accent);font-size:.9rem;"></i>
                <?= $avgRating ?>
              </div>
              <div class="stat-lbl"><?= $totalRev ?> Ulasan</div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($seller['toko_deskripsi']): ?>
      <div class="seller-desc">
        <i class="fa-solid fa-quote-left" style="color:var(--primary);opacity:.4;margin-right:5px;"></i>
        <?= nl2br(htmlspecialchars($seller['toko_deskripsi'])) ?>
      </div>
      <?php endif; ?>

      <!-- ACTION BUTTONS -->
      <div class="profile-actions">
        <?php if ($isLoggedIn && !$isSelf): ?>
          <?php if ($sellerWa): ?>
          <a class="btn-wa"
             href="https://wa.me/<?= $sellerWa ?>?text=<?= urlencode('Halo kak, saya dari LapakThriftUMSU ingin bertanya tentang toko ' . $namaToko . ' 😊') ?>"
             target="_blank" rel="noopener">
            <i class="fa-brands fa-whatsapp"></i> WhatsApp Penjual
          </a>
          <?php endif; ?>
          <button class="btn-chat" onclick="chatSeller()">
            <i class="fa-solid fa-comments"></i> Chat Penjual
          </button>
        <?php elseif ($isSelf): ?>
          <div class="notice-box info">
            <i class="fa-solid fa-store"></i> Ini adalah halaman toko Anda.
          </div>
        <?php else: ?>
          <div class="login-notice">
            <i class="fa-solid fa-circle-info"></i>
            <a href="login.html">Login</a> untuk menghubungi penjual.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /profile-hero -->

  <!-- TABS -->
  <div class="tabs-bar">
    <button class="tab-btn active" id="tabProduk" onclick="switchTab('Produk')">
      <i class="fa-solid fa-box-open"></i> Produk
      <span class="tab-count"><?= $totalProduk ?></span>
    </button>
    <button class="tab-btn" id="tabUlasan" onclick="switchTab('Ulasan')">
      <i class="fa-solid fa-star"></i> Ulasan Produk
      <span class="tab-count"><?= $totalRev ?></span>
    </button>
  </div>

  <!-- TAB: PRODUK -->
  <div class="tab-panel active" id="panelProduk">
    <div class="section-header">
      <div class="section-title">
        <i class="fa-solid fa-box-open" style="color:var(--primary);"></i>
        Semua Produk
        <span class="prod-count"><?= $totalProduk ?> produk</span>
      </div>
    </div>
    <div class="grid">
      <?php if ($produkList): ?>
        <?php foreach ($produkList as $p):
          // ── FOTO PRODUK: ambil path asli dari DB, tidak pakai fallback URL eksternal ──
          $pFoto = fotoUrl($p['foto_utama']);
          $isHl  = ($fromProduk && $fromProduk == $p['id']);
        ?>
        <div class="prod-card <?= $isHl ? 'highlighted' : '' ?>"
             id="produk-<?= $p['id'] ?>"
             onclick="window.location.href='detail_produk.php?id=<?= $p['id'] ?>'">

          <!-- ── WRAPPER FOTO ── -->
          <div class="prod-img-wrap">
            <?php if ($pFoto): ?>
              <img
                src="<?= htmlspecialchars($pFoto) ?>"
                alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                loading="lazy"
                onerror="
                  this.style.display='none';
                  this.nextElementSibling.style.display='flex';
                "
              />
              <!-- Fallback jika file tidak ditemukan di server -->
              <div class="prod-img-placeholder" style="display:none;">
                <i class="fa-regular fa-image"></i>
                <span>Foto tidak tersedia</span>
              </div>
            <?php else: ?>
              <!-- Tidak ada path foto sama sekali di DB -->
              <div class="prod-img-placeholder">
                <i class="fa-regular fa-image"></i>
                <span>Belum ada foto</span>
              </div>
            <?php endif; ?>
          </div>
          <!-- /wrapper foto -->

          <div class="prod-body">
            <div class="prod-name"><?= htmlspecialchars($p['nama_produk']) ?></div>
            <div class="prod-kondisi"><?= htmlspecialchars($p['kondisi']) ?> · <?= htmlspecialchars($p['kategori']) ?></div>
            <div class="prod-price">Rp <?= number_format($p['harga'],0,',','.') ?></div>
            <div class="prod-stok <?= $p['stok'] > 0 ? 'in-stok' : 'out-stok' ?>">
              <i class="fa-solid <?= $p['stok'] > 0 ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
              <?= $p['stok'] > 0 ? 'Stok ' . $p['stok'] : 'Stok habis' ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty">
          <div class="emoji">📦</div>
          <p style="font-weight:700;">Belum ada produk tersedia.</p>
        </div>
      <?php endif; ?>
    </div>
  </div><!-- /panelProduk -->

  <!-- TAB: ULASAN -->
  <div class="tab-panel" id="panelUlasan">
    <div class="review-wrap">

      <!-- Rating overview -->
      <?php if ($totalRev > 0): ?>
      <div class="rating-overview">
        <div class="rating-big">
          <div class="rating-number"><?= $avgRating ?: '–' ?></div>
          <div class="rating-stars">
            <?php
              $full    = (int)$avgRating;
              $half    = ($avgRating - $full >= 0.5) ? 1 : 0;
              $empty_s = 5 - $full - $half;
              echo str_repeat('<i class="fa-solid fa-star"></i>', $full);
              if ($half) echo '<i class="fa-solid fa-star-half-stroke"></i>';
              echo str_repeat('<i class="fa-regular fa-star"></i>', max(0, $empty_s));
            ?>
          </div>
          <div class="rating-count"><?= $totalRev ?> ulasan</div>
        </div>
        <div class="bar-stack">
          <?php
            $tot = max($totalRev, 1);
            foreach ([5,4,3,2,1] as $b) {
              $cnt = (int)($rs['r'.$b] ?? 0);
              $pct = round(($cnt / $tot) * 100);
              echo "<div class='bar-row'>
                <span style='min-width:10px'>$b</span>
                <i class='fa-solid fa-star' style='color:var(--accent);font-size:.7rem;'></i>
                <div class='bar-track'><div class='bar-fill' style='width:{$pct}%'></div></div>
                <span style='min-width:26px;text-align:right'>$cnt</span>
              </div>";
            }
          ?>
        </div>
      </div>
      <?php else: ?>
      <div class="notice-box info" style="margin-bottom:24px;">
        <i class="fa-regular fa-comment-dots" style="font-size:1.1rem;"></i>
        Belum ada ulasan produk dari toko ini.
      </div>
      <?php endif; ?>

      <!-- FORM ULASAN -->
      <?php if ($isLoggedIn && !$isSelf): ?>
        <?php if (!empty($produkYangBisaDiReview)): ?>
        <div class="review-form-box" id="reviewFormBox">
          <div class="rfb-title">
            <i class="fa-solid fa-pen-to-square" style="color:var(--primary);"></i>
            Tulis Ulasan Produk
          </div>
          <p class="rfb-sub">Pilih produk yang ingin kamu beri ulasan, lalu berikan penilaian ✨</p>

          <select class="produk-select" id="selectProduk">
            <option value="">— Pilih Produk —</option>
            <?php foreach ($produkYangBisaDiReview as $pil): ?>
            <option value="<?= $pil['id'] ?>"><?= htmlspecialchars($pil['nama_produk']) ?></option>
            <?php endforeach; ?>
          </select>

          <div class="star-picker" id="starPicker">
            <?php for ($s = 1; $s <= 5; $s++): ?>
            <i class="fa-regular fa-star" data-val="<?= $s ?>"></i>
            <?php endfor; ?>
          </div>
          <div class="star-label" id="starLabel">Tap bintang untuk memberi rating</div>

          <textarea class="review-textarea" id="reviewText"
                    placeholder="Ceritakan kondisi barang, pengalaman transaksi, atau kesan kamu..."
                    maxlength="500"
                    oninput="updateChar()"></textarea>
          <div class="char-count" id="charCount">0 / 500</div>

          <button class="btn-kirim-review" id="btnKirim" onclick="submitReview()">
            <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
          </button>
        </div>

        <?php else: ?>
        <div class="notice-box success" style="margin-bottom:24px;">
          <i class="fa-solid fa-circle-check"></i>
          Kamu sudah memberi ulasan untuk semua produk dari toko ini. Terima kasih!
        </div>
        <?php endif; ?>

      <?php elseif (!$isSelf): ?>
      <div class="notice-box warn" style="margin-bottom:24px;">
        <i class="fa-solid fa-circle-info"></i>
        <a href="login.html" style="color:var(--primary);text-decoration:underline;">Login</a>
        untuk memberikan ulasan produk.
      </div>
      <?php endif; ?>

      <!-- DAFTAR ULASAN -->
      <?php if ($ulasanList): ?>
      <div class="review-list">
        <?php foreach ($ulasanList as $ul): ?>
        <div class="review-card">
          <div class="rc-top">
            <?php if (!empty($ul['foto_profil']) && $ul['foto_profil'] !== 'default.jpg'): ?>
              <img class="rc-ava"
                   src="uploads/<?= htmlspecialchars($ul['foto_profil']) ?>"
                   alt="<?= htmlspecialchars($ul['reviewer']) ?>"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
              <div class="rc-ava-ph" style="display:none"><?= strtoupper(substr($ul['reviewer'],0,1)) ?></div>
            <?php else: ?>
              <div class="rc-ava-ph"><?= strtoupper(substr($ul['reviewer'],0,1)) ?></div>
            <?php endif; ?>
            <div>
              <div class="rc-name"><?= htmlspecialchars($ul['reviewer']) ?></div>
              <div class="rc-meta"><?= date('d M Y', strtotime($ul['created_at'])) ?></div>
            </div>
          </div>
          <div class="rc-produk">
            <i class="fa-solid fa-tag"></i>
            <?= htmlspecialchars($ul['nama_produk']) ?>
          </div>
          <div class="rc-stars">
            <?= str_repeat('<i class="fa-solid fa-star"></i>', (int)$ul['rating']) ?>
            <?= str_repeat('<i class="fa-regular fa-star"></i>', 5 - (int)$ul['rating']) ?>
          </div>
          <?php if (!empty(trim($ul['komentar']))): ?>
            <div class="rc-text"><?= nl2br(htmlspecialchars($ul['komentar'])) ?></div>
          <?php else: ?>
            <div class="rc-text" style="color:var(--muted);font-style:italic;">Tidak ada komentar.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="no-review">
        <i class="fa-regular fa-comment-dots" style="font-size:3rem;display:block;margin-bottom:14px;color:var(--border);"></i>
        Belum ada ulasan produk.<br>
        <span style="font-size:.83rem;">Jadilah yang pertama memberi ulasan!</span>
      </div>
      <?php endif; ?>

    </div><!-- /review-wrap -->
  </div><!-- /panelUlasan -->

</div><!-- /wrap -->
<div class="toast" id="toast"></div>

<script>
const SELLER_ID   = <?= $sellerId ?>;
const IS_LOGGED   = <?= $isLoggedIn ? 'true' : 'false' ?>;
const SELLER_NAME = <?= json_encode($namaToko) ?>;
const FROM_PRODUK = <?= $fromProduk ?: 0 ?>;

/* ── TABS ─────────────────────────────── */
function switchTab(name) {
  ['Produk','Ulasan'].forEach(t => {
    document.getElementById('tab'+t).classList.toggle('active', t===name);
    document.getElementById('panel'+t).classList.toggle('active', t===name);
  });
}
if (location.hash === '#ulasan') switchTab('Ulasan');

/* ── Scroll & highlight produk dari detail ── */
if (FROM_PRODUK) {
  const el = document.getElementById('produk-' + FROM_PRODUK);
  if (el) setTimeout(() => el.scrollIntoView({behavior:'smooth',block:'center'}), 500);
}

/* ── CHAT ─────────────────────────────── */
function chatSeller() {
  if (!IS_LOGGED) { window.location.href = 'login.html'; return; }
  sessionStorage.setItem('chatTarget', JSON.stringify({
    seller_id: SELLER_ID, product: 'Toko '+SELLER_NAME, produk_id: 0
  }));
  window.location.href = 'chat.php';
}

/* ── STAR PICKER ──────────────────────── */
let ratingVal = 0;
const LABELS  = ['','Sangat Buruk 😞','Buruk 😕','Cukup 😐','Bagus 😊','Sangat Bagus 🤩'];

document.querySelectorAll('#starPicker i').forEach(el => {
  const v = parseInt(el.dataset.val);
  el.addEventListener('mouseenter', () => lightStars(v, true));
  el.addEventListener('mouseleave', () => lightStars(ratingVal, false));
  el.addEventListener('click', () => {
    ratingVal = v;
    lightStars(v, false);
    document.getElementById('starLabel').textContent = LABELS[v];
  });
});

function lightStars(n, hover) {
  document.querySelectorAll('#starPicker i').forEach((el,i) => {
    el.className = i < n
      ? (hover ? 'fa-regular fa-star lit' : 'fa-solid fa-star lit')
      : 'fa-regular fa-star';
  });
}

function updateChar() {
  const len = document.getElementById('reviewText')?.value.length ?? 0;
  const el  = document.getElementById('charCount');
  if (!el) return;
  el.textContent = len + ' / 500';
  el.classList.toggle('warn', len >= 450);
}

/* ── SUBMIT ULASAN ────────────────────── */
function submitReview() {
  const produkId = parseInt(document.getElementById('selectProduk')?.value ?? 0);
  if (!produkId) { showToast('Pilih produk terlebih dahulu', 'red'); return; }
  if (!ratingVal) { showToast('Pilih rating bintang dulu ⭐', 'red'); return; }

  const komentar = document.getElementById('reviewText')?.value.trim() ?? '';
  const btn = document.getElementById('btnKirim');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';

  fetch('review_produk.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ produk_id: produkId, rating: ratingVal, komentar })
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast('⭐ Ulasan berhasil dikirim! Terima kasih.', 'green');
      const box = document.getElementById('reviewFormBox');
      if (box) {
        box.innerHTML = `<div style="text-align:center;padding:20px;">
          <i class="fa-solid fa-circle-check" style="font-size:2.5rem;color:#16a34a;"></i>
          <p style="font-weight:800;margin-top:12px;color:#166534;">Ulasan berhasil dikirim!</p>
          <p style="font-size:.85rem;color:var(--muted);">Memuat ulang halaman...</p>
        </div>`;
      }
      setTimeout(() => location.reload(), 2000);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Ulasan';
      showToast(d.error || 'Gagal mengirim ulasan', 'red');
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Ulasan';
    showToast('Error koneksi', 'red');
  });
}

/* ── TOAST ────────────────────────────── */
function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type?' '+type:'');
  clearTimeout(t._t);
  t._t = setTimeout(() => t.className='toast', 3200);
}
</script>
</body>
</html>
