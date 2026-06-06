<?php
// detail_produk.php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = $isLoggedIn ? (int)$_SESSION['user_id']   : 0;
$username   = $isLoggedIn ? htmlspecialchars($_SESSION['username']) : '';
$role       = $isLoggedIn ? ($_SESSION['role'] ?? 'pembeli') : 'guest';

$produkId = (int)($_GET['id'] ?? 0);
if (!$produkId) {
    header('Location: userafterlogin.php');
    exit;
}

$db = getDB();

// Ambil data produk lengkap
$stmt = $db->prepare("
    SELECT p.*, k.nama AS kategori_nama,
           u.nama AS seller_nama, u.no_hp AS seller_hp, u.id_users AS seller_id,
           sp.nama_toko, sp.foto_toko, sp.rating AS toko_rating, sp.total_terjual,
           sp.deskripsi AS toko_deskripsi
    FROM produk p
    JOIN users u    ON u.id_users = p.seller_id
    JOIN kategori k ON k.id = p.kategori_id
    LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
    WHERE p.id = :id AND p.status != 'dihapus'
");
$stmt->execute([':id' => $produkId]);
$produk = $stmt->fetch();

if (!$produk) {
    header('Location: userafterlogin.php');
    exit;
}

// Tambah views
$db->prepare("UPDATE produk SET views = views + 1 WHERE id = :id")->execute([':id' => $produkId]);

// Foto tambahan
$fotoStmt = $db->prepare("SELECT url_foto FROM foto_produk WHERE produk_id = :id ORDER BY urutan ASC");
$fotoStmt->execute([':id' => $produkId]);
$fotoList = $fotoStmt->fetchAll(PDO::FETCH_COLUMN);

// Review / ulasan — ambil lebih banyak
$ulasanStmt = $db->prepare("
    SELECT ul.rating, ul.komentar, ul.created_at, u.nama AS reviewer, u.foto_profil
    FROM ulasan ul
    JOIN users u ON u.id_users = ul.user_id
    WHERE ul.produk_id = :id
    ORDER BY ul.created_at DESC
    LIMIT 20
");
$ulasanStmt->execute([':id' => $produkId]);
$ulasanList = $ulasanStmt->fetchAll();

// Rata-rata rating
$ratingStats = $db->prepare("
    SELECT
        AVG(rating) AS avg_rating,
        COUNT(*) AS total_ulasan,
        SUM(rating=5) AS r5, SUM(rating=4) AS r4,
        SUM(rating=3) AS r3, SUM(rating=2) AS r2, SUM(rating=1) AS r1
    FROM ulasan WHERE produk_id = :id
");
$ratingStats->execute([':id' => $produkId]);
$rs = $ratingStats->fetch();
$avgRating   = round((float)($rs['avg_rating'] ?? 0), 1);
$totalUlasan = (int)($rs['total_ulasan'] ?? 0);

// Apakah pembeli sudah pernah review
$sudahReview = false;
if ($isLoggedIn) {
    $cek = $db->prepare("SELECT id FROM ulasan WHERE produk_id=:pid AND user_id=:uid");
    $cek->execute([':pid' => $produkId, ':uid' => $userId]);
    $sudahReview = (bool)$cek->fetch();
}

// Di-wishlist?
$diWishlist = false;
if ($isLoggedIn) {
    $wl = $db->prepare("SELECT id FROM wishlist WHERE user_id=:uid AND produk_id=:pid");
    $wl->execute([':uid' => $userId, ':pid' => $produkId]);
    $diWishlist = (bool)$wl->fetch();
}

// ── Ambil no_hp user yang sedang login (untuk tombol WA penjual tetap pakai seller_hp)
// ── Format foto — path di DB sudah lengkap: 'uploads/produk/xxx.jpg'
function fotoUrl($path) {
    $path = trim($path ?? '');
    if (!$path) {
        return 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=800&auto=format&fit=crop';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return ltrim($path, '/');
}

$fotoUtama = fotoUrl($produk['foto_utama']);
$allFoto   = array_merge([$fotoUtama], array_map('fotoUrl', $fotoList));

// Format nomor WA penjual (hanya angka, awali dengan 62)
function formatWaNumber($hp) {
    $hp = preg_replace('/[^0-9]/', '', $hp ?? '');
    if (!$hp) return '';
    if (str_starts_with($hp, '0')) return '62' . substr($hp, 1);
    if (!str_starts_with($hp, '62')) return '62' . $hp;
    return $hp;
}
$sellerHpWa = formatWaNumber($produk['seller_hp']);

// Apakah penjual produk ini == user yang login
$isSeller = $isLoggedIn && ($userId === (int)$produk['seller_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($produk['nama_produk']) ?> – LapakThriftUMSU</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --primary:#4f8cff;--primary-dark:#1e3a8a;
  --bg:#f4f7fb;--text:#0f172a;--muted:#64748b;
  --border:#dbe4f0;--shadow:0 10px 30px rgba(79,140,255,.10);
  --green:#16a34a;--red:#dc2626;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--text);}
a{text-decoration:none;color:inherit;}

/* NAV */
nav{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid #eef2f7;}
.nav-inner{max-width:1300px;margin:auto;padding:14px 20px;display:flex;align-items:center;gap:14px;}
.logo{display:flex;align-items:center;gap:9px;font-size:1.2rem;font-weight:800;color:var(--primary-dark);}
.logo .icon{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;}
.logo span{color:var(--primary);}
.back-btn{display:flex;align-items:center;gap:7px;padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);background:#fff;font-weight:700;font-size:.85rem;cursor:pointer;transition:.2s;}
.back-btn:hover{box-shadow:var(--shadow);}
.nav-user{margin-left:auto;display:flex;align-items:center;gap:8px;font-size:.88rem;font-weight:700;color:var(--primary-dark);}
.nav-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.78rem;}

/* BREADCRUMB */
.breadcrumb{max-width:1300px;margin:16px auto 0;padding:0 20px;display:flex;align-items:center;gap:8px;color:var(--muted);font-size:.85rem;}
.breadcrumb a{color:var(--primary);font-weight:600;}
.breadcrumb a:hover{text-decoration:underline;}

/* WRAP */
.wrap{max-width:1300px;margin:18px auto 50px;padding:0 20px;}
.detail-grid{display:grid;grid-template-columns:1fr 420px;gap:30px;align-items:start;}

/* GALLERY */
.gallery{position:sticky;top:90px;}
.main-img{width:100%;aspect-ratio:1/1;border-radius:22px;overflow:hidden;background:#e2e8f0;cursor:zoom-in;position:relative;}
.main-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s;display:block;}
.main-img:hover img{transform:scale(1.04);}
.img-badge{position:absolute;top:14px;left:14px;background:rgba(15,23,42,.6);color:#fff;padding:5px 10px;border-radius:999px;font-size:.75rem;font-weight:700;backdrop-filter:blur(6px);}
.img-sold{position:absolute;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;border-radius:22px;}
.img-sold span{background:#dc2626;color:#fff;padding:12px 24px;border-radius:14px;font-size:1.1rem;font-weight:800;}
.thumb-row{display:flex;gap:10px;margin-top:12px;overflow-x:auto;padding-bottom:4px;}
.thumb-row::-webkit-scrollbar{height:4px;}
.thumb-row::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}
.thumb{width:72px;height:72px;border-radius:12px;overflow:hidden;cursor:pointer;border:2.5px solid transparent;flex-shrink:0;transition:.2s;}
.thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.thumb.active{border-color:var(--primary);}
.thumb:hover{border-color:var(--primary);}

/* DETAIL PANEL */
.detail-panel{display:flex;flex-direction:column;gap:20px;}
.info-card{background:#fff;border-radius:22px;padding:24px;border:1px solid var(--border);}
.cat-badge{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:var(--primary-dark);padding:5px 12px;border-radius:999px;font-size:.78rem;font-weight:700;margin-bottom:12px;}
.prod-name{font-size:1.7rem;font-weight:800;line-height:1.2;margin-bottom:10px;}
.prod-meta{color:var(--muted);font-size:.9rem;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;}
.meta-tag{display:inline-flex;align-items:center;gap:5px;background:var(--bg);padding:5px 10px;border-radius:8px;font-weight:600;}
.prod-price{font-size:2rem;font-weight:800;color:var(--primary-dark);margin-bottom:6px;}
.prod-stock{font-size:.9rem;font-weight:700;margin-bottom:18px;}
.in-stock{color:var(--green);}
.out-stock{color:var(--red);}

/* Buyer protection */
.buyer-prot{display:flex;align-items:center;gap:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:13px 15px;margin-bottom:18px;}
.bp-icon{width:36px;height:36px;background:#16a34a;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;flex-shrink:0;}
.bp-text{font-size:.83rem;color:#166534;font-weight:700;}

/* Action buttons */
.action-btns{display:flex;flex-direction:column;gap:10px;}
.btn-beli{width:100%;padding:14px;border-radius:15px;border:none;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;font-weight:800;font-size:1rem;cursor:pointer;font-family:inherit;transition:.25s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-beli:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 24px rgba(79,140,255,.35);}
.btn-beli:disabled{opacity:.55;cursor:not-allowed;}
.btn-outline{width:100%;padding:13px;border-radius:15px;border:1.5px solid var(--border);background:#f8fbff;color:var(--primary-dark);font-weight:700;font-size:.93rem;cursor:pointer;font-family:inherit;transition:.25s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-outline:hover:not(:disabled){background:#eff6ff;border-color:var(--primary);}
.btn-outline:disabled{opacity:.55;cursor:not-allowed;}
.btn-wl{width:100%;padding:13px;border-radius:15px;border:1.5px solid var(--border);background:#fff;color:var(--muted);font-weight:700;font-size:.93rem;cursor:pointer;font-family:inherit;transition:.25s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-wl:hover{border-color:#ef4444;color:#ef4444;}
.btn-wl.loved{border-color:#ef4444;color:#ef4444;background:#fff1f2;}
.login-notice{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:12px 15px;font-size:.85rem;color:#92400e;font-weight:700;text-align:center;}
.login-notice a{color:var(--primary);text-decoration:underline;}

/* Description */
.desc-card{background:#fff;border-radius:22px;padding:24px;border:1px solid var(--border);}
.card-title{font-size:1rem;font-weight:800;color:var(--primary-dark);margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.desc-text{font-size:.93rem;line-height:1.8;color:#374151;white-space:pre-wrap;}

/* ══ SELLER CARD ══ */
.seller-card{background:#fff;border-radius:22px;padding:20px;border:1px solid var(--border);}
.seller-top{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
.seller-ava{width:60px;height:60px;border-radius:16px;object-fit:cover;flex-shrink:0;background:#e2e8f0;display:block;}
.seller-ava-ph{width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:800;flex-shrink:0;}
.seller-info .s-name{font-weight:800;font-size:1rem;margin-bottom:3px;}
.seller-info .s-status{color:var(--green);font-size:.82rem;font-weight:700;display:flex;align-items:center;gap:5px;}
.seller-rating{color:#fbbf24;font-size:.83rem;margin-top:4px;font-weight:700;}

/* Seller buttons layout baru:
   Baris 1: WA (penjual) + Chat
   Baris 2: WA (user sendiri — tersembunyi / di profil) 
   Baris 3: [WA Saya] + [Lihat Profil] berdampingan */
.seller-btns{display:flex;flex-direction:column;gap:8px;}
.seller-btns-row{display:flex;gap:8px;}
/* WA hijau */
.btn-wa{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:13px;background:#25D366;color:#fff;font-weight:700;font-size:.88rem;border:none;cursor:pointer;font-family:inherit;transition:.2s;text-decoration:none;}
.btn-wa:hover{opacity:.9;transform:translateY(-1px);}
/* Chat biru */
.btn-chat{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:13px;background:#eff6ff;color:var(--primary-dark);font-weight:700;font-size:.88rem;border:1.5px solid var(--border);cursor:pointer;font-family:inherit;transition:.2s;text-decoration:none;}
.btn-chat:hover{background:#dbeafe;border-color:var(--primary);transform:translateY(-1px);}
/* Profil biru gelap */
.btn-profil{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:13px;background:var(--primary-dark);color:#fff;font-weight:700;font-size:.88rem;border:none;cursor:pointer;font-family:inherit;transition:.2s;text-decoration:none;}
.btn-profil:hover{opacity:.9;transform:translateY(-1px);}
/* Disabled WA */
.btn-wa-disabled{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:13px;background:#d1fae5;color:#6b7280;font-weight:700;font-size:.88rem;border:none;cursor:not-allowed;font-family:inherit;opacity:.6;}

/* ══ REVIEW SECTION ══ */
.review-section{grid-column:1/-1;margin-top:10px;}
.review-header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;}
.rating-summary{display:flex;gap:20px;align-items:center;}
.rating-big{text-align:center;}
.rating-number{font-size:2.5rem;font-weight:800;color:var(--primary-dark);line-height:1;}
.rating-stars{color:#fbbf24;font-size:.95rem;margin:4px 0 2px;}
.rating-count{color:var(--muted);font-size:.8rem;}
.bar-stack{flex:1;display:flex;flex-direction:column;gap:5px;min-width:160px;}
.bar-row{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--muted);}
.bar-track{flex:1;height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden;}
.bar-fill{height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-dark));border-radius:99px;transition:width .6s ease;}

/* ══ FORM REVIEW ══ */
.review-form{background:#f8fbff;border:1.5px solid var(--border);border-radius:18px;padding:22px;margin-bottom:24px;}
.review-form-title{font-size:.95rem;font-weight:800;color:var(--primary-dark);margin-bottom:6px;display:flex;align-items:center;gap:8px;}
.review-form-sub{font-size:.83rem;color:var(--muted);margin-bottom:16px;}

/* Star picker interaktif */
.star-picker{display:flex;gap:6px;margin-bottom:6px;}
.star-picker i{font-size:1.8rem;cursor:pointer;color:#d1d5db;transition:color .15s,transform .15s;}
.star-picker i.lit{color:#f59e0b;}
.star-picker i:hover{transform:scale(1.2);}
.star-label{font-size:.82rem;color:var(--muted);font-weight:600;margin-bottom:14px;min-height:18px;}

.review-input{width:100%;border:1.5px solid var(--border);border-radius:13px;padding:13px 15px;font-family:inherit;font-size:.92rem;resize:vertical;min-height:100px;outline:none;transition:.2s;background:#fff;color:var(--text);}
.review-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,140,255,.12);}
.review-char{text-align:right;font-size:.78rem;color:var(--muted);margin-top:5px;}
.review-char.warn{color:#dc2626;}

.btn-submit-review{margin-top:14px;padding:13px 26px;border-radius:13px;border:none;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;font-weight:800;font-size:.93rem;cursor:pointer;font-family:inherit;transition:.2s;display:inline-flex;align-items:center;gap:8px;}
.btn-submit-review:hover:not(:disabled){opacity:.9;transform:translateY(-1px);}
.btn-submit-review:disabled{opacity:.5;cursor:not-allowed;transform:none;}

/* Review list */
.review-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:16px;}
.review-card{background:#fff;border-radius:18px;padding:18px;border:1px solid var(--border);transition:.2s;}
.review-card:hover{box-shadow:var(--shadow);}
.rc-top{display:flex;align-items:center;gap:12px;margin-bottom:10px;}
.rc-ava{width:42px;height:42px;border-radius:50%;object-fit:cover;background:#e2e8f0;display:block;}
.rc-ava-ph{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.9rem;flex-shrink:0;}
.rc-name{font-weight:700;font-size:.9rem;}
.rc-date{font-size:.78rem;color:var(--muted);}
.rc-stars{color:#f59e0b;font-size:.88rem;margin-bottom:8px;}
.rc-text{font-size:.88rem;line-height:1.7;color:#374151;}
.no-review{text-align:center;color:var(--muted);padding:40px;font-size:.9rem;}

/* MODAL BELI */
.overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:500;display:none;align-items:center;justify-content:center;padding:20px;}
.overlay.show{display:flex;}
.modal{background:#fff;border-radius:24px;padding:28px;max-width:480px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.2);}
.modal h2{font-size:1.2rem;font-weight:800;margin-bottom:18px;color:var(--primary-dark);}
.modal-prod{display:flex;gap:14px;align-items:center;margin-bottom:20px;background:var(--bg);border-radius:14px;padding:14px;}
.modal-prod img{width:64px;height:64px;border-radius:12px;object-fit:cover;display:block;}
.modal-prod .mp-name{font-weight:700;font-size:.93rem;}
.modal-prod .mp-price{color:var(--primary-dark);font-weight:800;font-size:1rem;}
.modal label{display:block;font-weight:700;font-size:.85rem;margin-bottom:6px;color:var(--muted);}
.modal textarea,.modal input[type=text]{width:100%;border:1.5px solid var(--border);border-radius:12px;padding:11px 13px;font-family:inherit;font-size:.9rem;outline:none;margin-bottom:14px;transition:.2s;}
.modal textarea:focus,.modal input:focus{border-color:var(--primary);}
.modal textarea{resize:vertical;min-height:70px;}
.modal-btns{display:flex;gap:10px;margin-top:6px;}
.btn-cancel{flex:1;padding:12px;border-radius:12px;border:1.5px solid var(--border);background:#f8fbff;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-confirm{flex:2;padding:12px;border-radius:12px;border:none;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;font-weight:800;cursor:pointer;font-family:inherit;font-size:.93rem;}
.btn-confirm:hover{opacity:.9;}

/* TOAST */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);background:#0f172a;color:#fff;padding:14px 24px;border-radius:14px;font-weight:700;font-size:.9rem;z-index:999;opacity:0;transition:all .3s;pointer-events:none;white-space:nowrap;}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
.toast.green{background:#16a34a;}
.toast.red{background:#dc2626;}

@media(max-width:950px){
  .detail-grid{grid-template-columns:1fr;}
  .gallery{position:static;}
  .review-section{grid-column:1;}
}
@media(max-width:600px){
  .prod-name{font-size:1.35rem;}
  .prod-price{font-size:1.6rem;}
  .seller-btns-row{flex-direction:column;}
  .review-list{grid-template-columns:1fr;}
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
    <a class="back-btn" href="userafterlogin.php"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
    <?php if ($isLoggedIn): ?>
    <div class="nav-user">
      <div class="nav-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
      <?= $username ?>
    </div>
    <?php endif; ?>
  </div>
</nav>

<!-- BREADCRUMB -->
<div class="breadcrumb">
  <a href="userafterlogin.php">Beranda</a>
  <i class="fa-solid fa-angle-right"></i>
  <a href="userafterlogin.php?category=<?= urlencode($produk['kategori_nama']) ?>"><?= htmlspecialchars($produk['kategori_nama']) ?></a>
  <i class="fa-solid fa-angle-right"></i>
  <span><?= htmlspecialchars(mb_strimwidth($produk['nama_produk'], 0, 40, '...')) ?></span>
</div>

<!-- MAIN -->
<div class="wrap">
  <div class="detail-grid">

    <!-- ══ GALLERY ══ -->
    <div class="gallery">
      <div class="main-img" id="mainImgWrap">
        <span class="img-badge" id="imgBadge">1 / <?= count($allFoto) ?></span>
        <img id="mainImg"
             src="<?= htmlspecialchars($allFoto[0]) ?>"
             alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
             onerror="this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=800&auto=format&fit=crop'"/>
        <?php if ($produk['stok'] <= 0): ?>
        <div class="img-sold"><span>Stok Habis</span></div>
        <?php endif; ?>
      </div>
      <?php if (count($allFoto) > 1): ?>
      <div class="thumb-row">
        <?php foreach ($allFoto as $i => $foto): ?>
        <div class="thumb <?= $i===0?'active':'' ?>"
             onclick="changePhoto(<?=$i?>, '<?= htmlspecialchars($foto) ?>')">
          <img src="<?= htmlspecialchars($foto) ?>"
               alt="foto <?= $i+1 ?>"
               onerror="this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=200&auto=format&fit=crop'"/>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ DETAIL PANEL ══ -->
    <div class="detail-panel">

      <!-- INFO -->
      <div class="info-card">
        <div class="cat-badge"><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($produk['kategori_nama']) ?></div>
        <h1 class="prod-name"><?= htmlspecialchars($produk['nama_produk']) ?></h1>
        <div class="prod-meta">
          <?php if ($produk['kondisi']): ?>
          <span class="meta-tag"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($produk['kondisi']) ?></span>
          <?php endif; ?>
          <?php if ($produk['ukuran']): ?>
          <span class="meta-tag"><i class="fa-solid fa-ruler"></i> <?= htmlspecialchars($produk['ukuran']) ?></span>
          <?php endif; ?>
          <?php if ($avgRating > 0): ?>
          <span class="meta-tag" style="color:#b45309;background:#fef3c7;">
            <i class="fa-solid fa-star" style="color:#f59e0b;"></i> <?= $avgRating ?> (<?= $totalUlasan ?> ulasan)
          </span>
          <?php endif; ?>
          <?php if ($produk['views'] > 0): ?>
          <span class="meta-tag"><i class="fa-regular fa-eye"></i> <?= $produk['views'] ?> dilihat</span>
          <?php endif; ?>
        </div>
        <div class="prod-price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></div>
        <div class="prod-stock <?= $produk['stok'] > 0 ? 'in-stock' : 'out-stock' ?>">
          <i class="fa-solid <?= $produk['stok'] > 0 ? 'fa-box-open' : 'fa-ban' ?>"></i>
          <?= $produk['stok'] > 0 ? 'Stok tersedia · ' . $produk['stok'] . ' unit' : 'Stok habis' ?>
        </div>
      </div>

      <!-- BUYER PROTECTION -->
      <div class="buyer-prot">
        <div class="bp-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="bp-text">Perlindungan Pembeli – belanja aman & terjamin di LapakThriftUMSU</div>
      </div>

      <!-- ACTION BUTTONS -->
      <?php if (!$isLoggedIn): ?>
      <div class="login-notice">
        <i class="fa-solid fa-circle-info"></i>
        Silakan <a href="login.html">login</a> untuk membeli atau menghubungi penjual.
      </div>
      <?php elseif ($isSeller): ?>
      <div class="login-notice" style="background:#eff6ff;border-color:#bfdbfe;color:#1e3a8a;">
        <i class="fa-solid fa-store"></i> Ini adalah produk milik toko Anda.
      </div>
      <?php else: ?>
      <div class="action-btns">
        <?php if ($produk['stok'] > 0): ?>
        <button class="btn-beli" id="btnBeli" onclick="openBeli()">
          <i class="fa-solid fa-bag-shopping"></i> Beli Langsung
        </button>
        <?php else: ?>
        <button class="btn-beli" disabled>
          <i class="fa-solid fa-ban"></i> Stok Habis
        </button>
        <?php endif; ?>
        <button class="btn-outline" onclick="chatPenjual()">
          <i class="fa-solid fa-comments"></i> Chat Penjual
        </button>
        <button class="btn-wl <?= $diWishlist ? 'loved' : '' ?>" id="btnWL" onclick="toggleWishlist()">
          <i class="<?= $diWishlist ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
          <?= $diWishlist ? 'Sudah di Wishlist' : 'Tambah ke Wishlist' ?>
        </button>
      </div>
      <?php endif; ?>

      <!-- DESKRIPSI -->
      <div class="desc-card">
        <div class="card-title">
          <i class="fa-solid fa-align-left" style="color:var(--primary);"></i> Deskripsi Produk
        </div>
        <div class="desc-text"><?= nl2br(htmlspecialchars($produk['deskripsi'] ?: 'Tidak ada deskripsi.')) ?></div>
      </div>

      <!-- ══ INFO PENJUAL ══ -->
      <div class="seller-card">
        <div class="card-title">
          <i class="fa-solid fa-store" style="color:var(--primary);"></i> Info Penjual
        </div>
        <div class="seller-top">
          <?php $fotoToko = fotoUrl($produk['foto_toko']); ?>
          <?php if ($produk['foto_toko']): ?>
          <img class="seller-ava"
               src="<?= htmlspecialchars($fotoToko) ?>"
               alt="toko"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
          <div class="seller-ava-ph" style="display:none">
            <?= strtoupper(substr($produk['nama_toko'] ?: $produk['seller_nama'], 0, 1)) ?>
          </div>
          <?php else: ?>
          <div class="seller-ava-ph">
            <?= strtoupper(substr($produk['nama_toko'] ?: $produk['seller_nama'], 0, 1)) ?>
          </div>
          <?php endif; ?>
          <div class="seller-info">
            <div class="s-name"><?= htmlspecialchars($produk['nama_toko'] ?: $produk['seller_nama']) ?></div>
            <div class="s-status">
              <i class="fa-solid fa-circle" style="font-size:.45rem;"></i> Aktif
            </div>
            <?php if ($produk['toko_rating'] > 0): ?>
            <div class="seller-rating">
              <i class="fa-solid fa-star"></i> <?= $produk['toko_rating'] ?> · <?= $produk['total_terjual'] ?> terjual
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ══ TOMBOL PENJUAL: 3 tombol dalam 2 baris ══ -->
        <div class="seller-btns">
          <?php if ($isLoggedIn && !$isSeller): ?>

          <!-- Baris 1: WA Penjual + Chat In-App -->
          <div class="seller-btns-row">
            <?php if ($sellerHpWa): ?>
            <a class="btn-wa"
               href="https://wa.me/<?= $sellerHpWa ?>?text=<?= urlencode('Halo, saya tertarik dengan produk: ' . $produk['nama_produk'] . ' – ' . 'Rp ' . number_format($produk['harga'],0,',','.')) ?>"
               target="_blank" rel="noopener"
               title="WhatsApp penjual: <?= htmlspecialchars($produk['seller_hp'] ?? '') ?>">
              <i class="fa-brands fa-whatsapp"></i> WA Penjual
            </a>
            <?php else: ?>
            <span class="btn-wa-disabled" title="Nomor WA penjual tidak tersedia">
              <i class="fa-brands fa-whatsapp"></i> WA N/A
            </span>
            <?php endif; ?>
            <button class="btn-chat" onclick="chatPenjual()" title="Chat via LapakThrift">
              <i class="fa-solid fa-comments"></i> Chat
            </button>
          </div>

          <!-- Baris 2: Lihat Profil Toko -->
          <div class="seller-btns-row">
            <a class="btn-profil" href="seller_profil.php?id=<?= (int)$produk['seller_id'] ?>">
              <i class="fa-solid fa-store"></i> Lihat Profil Toko
            </a>
          </div>

          <?php else: ?>
          <!-- Jika bukan pembeli (misal tamu) hanya tampil profil -->
          <div class="seller-btns-row">
            <a class="btn-profil" href="seller_profil.php?id=<?= (int)$produk['seller_id'] ?>">
              <i class="fa-solid fa-store"></i> Lihat Profil Toko
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /detail-panel -->

    <!-- ══════════════════════════════════
         REVIEW SECTION
    ══════════════════════════════════ -->
    <div class="review-section">
      <div class="info-card">

        <!-- Header ringkasan rating -->
        <div class="review-header">
          <div class="card-title" style="font-size:1.2rem;margin-bottom:0;">
            <i class="fa-solid fa-star" style="color:#f59e0b;"></i> Ulasan Pembeli
          </div>
          <div class="rating-summary">
            <?php if ($totalUlasan > 0): ?>
            <div class="rating-big">
              <div class="rating-number"><?= $avgRating ?></div>
              <div class="rating-stars">
                <?= str_repeat('<i class="fa-solid fa-star"></i>', (int)$avgRating) ?>
                <?= ($avgRating - (int)$avgRating >= 0.5) ? '<i class="fa-solid fa-star-half-stroke"></i>' : '' ?>
                <?= str_repeat('<i class="fa-regular fa-star"></i>', max(0, 5 - (int)$avgRating - (($avgRating - (int)$avgRating >= 0.5)?1:0))) ?>
              </div>
              <div class="rating-count"><?= $totalUlasan ?> ulasan</div>
            </div>
            <div class="bar-stack">
              <?php
                $total = max($totalUlasan, 1);
                foreach ([5,4,3,2,1] as $bintang) {
                    $key = 'r'.$bintang;
                    $cnt = (int)($rs[$key] ?? 0);
                    $pct = round(($cnt / $total) * 100);
                    echo "<div class='bar-row'>
                            <span style='min-width:10px'>$bintang</span>
                            <i class='fa-solid fa-star' style='color:#f59e0b;font-size:.7rem;'></i>
                            <div class='bar-track'><div class='bar-fill' style='width:{$pct}%'></div></div>
                            <span style='min-width:32px;text-align:right'>$cnt</span>
                          </div>";
                }
              ?>
            </div>
            <?php else: ?>
            <div style="color:var(--muted);font-size:.9rem;display:flex;align-items:center;gap:8px;">
              <i class="fa-regular fa-comment-dots"></i> Belum ada ulasan
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ══ FORM TULIS ULASAN ══ -->
        <?php if ($isLoggedIn && !$isSeller && !$sudahReview): ?>
        <div class="review-form" id="reviewForm" style="margin-top:24px;">
          <div class="review-form-title">
            <i class="fa-solid fa-pen-to-square" style="color:var(--primary);"></i>
            Tulis Ulasan Kamu
          </div>
          <p class="review-form-sub">Bagikan pengalaman kamu – rating & komentar membantu pembeli lain</p>

          <!-- Bintang interaktif -->
          <div class="star-picker" id="starPicker">
            <i class="fa-regular fa-star" data-val="1" title="1 – Sangat Buruk"></i>
            <i class="fa-regular fa-star" data-val="2" title="2 – Buruk"></i>
            <i class="fa-regular fa-star" data-val="3" title="3 – Cukup"></i>
            <i class="fa-regular fa-star" data-val="4" title="4 – Bagus"></i>
            <i class="fa-regular fa-star" data-val="5" title="5 – Sangat Bagus"></i>
          </div>
          <div class="star-label" id="starLabel">Tap bintang untuk memberi rating</div>

          <!-- Textarea komentar -->
          <textarea class="review-input" id="reviewText"
                    placeholder="Ceritakan kondisi barang, pengalaman transaksi, atau kesan kamu..."
                    maxlength="500"
                    oninput="updateChar()"></textarea>
          <div class="review-char" id="charCount">0 / 500</div>

          <button class="btn-submit-review" id="btnSubmitReview" onclick="submitReview()">
            <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
          </button>
        </div>

        <?php elseif ($isLoggedIn && $sudahReview): ?>
        <div class="login-notice" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;margin-top:20px;margin-bottom:0;">
          <i class="fa-solid fa-circle-check"></i> Kamu sudah memberikan ulasan untuk produk ini. Terima kasih!
        </div>

        <?php elseif ($isLoggedIn && $isSeller): ?>
        <!-- Seller tidak bisa review produknya sendiri — tidak tampil form -->

        <?php else: ?>
        <!-- Belum login -->
        <div class="login-notice" style="margin-top:20px;margin-bottom:0;">
          <i class="fa-solid fa-circle-info"></i>
          <a href="login.html">Login</a> terlebih dahulu untuk memberikan ulasan.
        </div>
        <?php endif; ?>

        <!-- ══ DAFTAR ULASAN ══ -->
        <?php if ($ulasanList): ?>
        <div class="review-list" style="margin-top:24px;">
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
                <div class="rc-date"><?= date('d M Y', strtotime($ul['created_at'])) ?></div>
              </div>
            </div>
            <div class="rc-stars">
              <?= str_repeat('<i class="fa-solid fa-star"></i>', (int)$ul['rating']) ?>
              <?= str_repeat('<i class="fa-regular fa-star"></i>', 5-(int)$ul['rating']) ?>
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
        <div class="no-review" style="margin-top:16px;">
          <i class="fa-regular fa-comment-dots" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--border);"></i>
          Belum ada ulasan untuk produk ini.<br>
          <span style="font-size:.82rem;">Jadilah yang pertama memberikan ulasan!</span>
        </div>
        <?php endif; ?>

      </div>
    </div><!-- /review-section -->

  </div><!-- /detail-grid -->
</div><!-- /wrap -->

<!-- ══ MODAL BELI ══ -->
<div class="overlay" id="modalBeli">
  <div class="modal">
    <h2><i class="fa-solid fa-bag-shopping"></i> Konfirmasi Pembelian</h2>
    <div class="modal-prod">
      <img src="<?= htmlspecialchars($allFoto[0]) ?>"
           alt="produk"
           onerror="this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=200&auto=format&fit=crop'"/>
      <div>
        <div class="mp-name"><?= htmlspecialchars($produk['nama_produk']) ?></div>
        <div class="mp-price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></div>
      </div>
    </div>
    <label>Alamat Pengiriman / Lokasi COD</label>
    <textarea id="alamatInput" placeholder="Masukkan alamat lengkap atau titik COD..."></textarea>
    <label>Catatan untuk Penjual (opsional)</label>
    <input type="text" id="catatanInput" placeholder="Contoh: mohon dibungkus rapi"/>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeModal()">Batal</button>
      <button class="btn-confirm" onclick="konfirmasiBeli()">
        <i class="fa-solid fa-check"></i> Konfirmasi Beli
      </button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
const PRODUK_ID = <?= $produkId ?>;
const IS_LOGGED = <?= $isLoggedIn ? 'true' : 'false' ?>;
const USER_ID   = <?= $userId ?>;
const SELLER_ID = <?= (int)$produk['seller_id'] ?>;
const SELLER_WA = '<?= $sellerHpWa ?>';
const PROD_NAME = <?= json_encode($produk['nama_produk']) ?>;
let inWishlist  = <?= $diWishlist ? 'true' : 'false' ?>;

// ── GALLERY ──────────────────────────────────────────
const allFoto = <?= json_encode($allFoto) ?>;
let activeIdx = 0;
function changePhoto(idx, src) {
  activeIdx = idx;
  document.getElementById('mainImg').src = src;
  document.getElementById('imgBadge').textContent = `${idx+1} / ${allFoto.length}`;
  document.querySelectorAll('.thumb').forEach((t,i) => t.classList.toggle('active', i===idx));
}

// ── WISHLIST ──────────────────────────────────────────
function toggleWishlist() {
  if (!IS_LOGGED) { showToast('Silakan login dulu', 'red'); return; }
  const btn = document.getElementById('btnWL');
  fetch('wishlist_toggle.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({produk_id: PRODUK_ID})
  })
  .then(r => r.json())
  .then(d => {
    inWishlist = d.inWishlist;
    if (inWishlist) {
      btn.innerHTML = '<i class="fa-solid fa-heart"></i> Sudah di Wishlist';
      btn.classList.add('loved');
      showToast('❤️ Ditambahkan ke wishlist', 'green');
    } else {
      btn.innerHTML = '<i class="fa-regular fa-heart"></i> Tambah ke Wishlist';
      btn.classList.remove('loved');
      showToast('Dihapus dari wishlist');
    }
  })
  .catch(() => showToast('Gagal update wishlist', 'red'));
}

// ── CHAT PENJUAL ──────────────────────────────────────
function chatPenjual() {
  if (!IS_LOGGED) { window.location.href = 'login.html'; return; }
  sessionStorage.setItem('chatTarget', JSON.stringify({
    seller_id: SELLER_ID,
    product: PROD_NAME,
    produk_id: PRODUK_ID
  }));
  window.location.href = 'chat.php';
}

// ── MODAL BELI ────────────────────────────────────────
function openBeli()  { document.getElementById('modalBeli').classList.add('show'); }
function closeModal(){ document.getElementById('modalBeli').classList.remove('show'); }

function konfirmasiBeli() {
  const alamat  = document.getElementById('alamatInput').value.trim();
  const catatan = document.getElementById('catatanInput').value.trim();
  if (!alamat) { showToast('Masukkan alamat pengiriman', 'red'); return; }
  fetch('beli_produk.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({produk_id: PRODUK_ID, alamat, catatan})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      closeModal();
      showToast('✅ Pesanan berhasil! Hubungi penjual untuk konfirmasi.', 'green');
      setTimeout(() => location.reload(), 2000);
    } else {
      showToast(d.error || 'Gagal melakukan pembelian', 'red');
    }
  })
  .catch(() => showToast('Error koneksi', 'red'));
}

// ── REVIEW ────────────────────────────────────────────
let ratingVal = 0;
const starLabels = ['','Sangat Buruk 😞','Buruk 😕','Cukup 😐','Bagus 😊','Sangat Bagus 🤩'];

// Pasang event ke setiap bintang
document.querySelectorAll('#starPicker i').forEach(el => {
  const val = parseInt(el.dataset.val);

  // Hover masuk
  el.addEventListener('mouseenter', () => highlightStars(val, true));
  // Hover keluar
  el.addEventListener('mouseleave', () => highlightStars(ratingVal, false));
  // Klik
  el.addEventListener('click', () => {
    ratingVal = val;
    highlightStars(val, false);
    document.getElementById('starLabel').textContent = starLabels[val];
  });
});

function highlightStars(n, isHover) {
  document.querySelectorAll('#starPicker i').forEach((el, i) => {
    if (i < n) {
      el.className = isHover ? 'fa-regular fa-star lit' : 'fa-solid fa-star lit';
    } else {
      el.className = 'fa-regular fa-star';
    }
  });
}

// Counter karakter
function updateChar() {
  const len = document.getElementById('reviewText')?.value.length ?? 0;
  const el  = document.getElementById('charCount');
  if (!el) return;
  el.textContent = len + ' / 500';
  el.classList.toggle('warn', len >= 450);
}

function submitReview() {
  if (!ratingVal) {
    showToast('Pilih rating bintang dulu ⭐', 'red');
    document.getElementById('starPicker').style.animation = 'none';
    setTimeout(() => document.getElementById('starPicker').style.animation = '', 100);
    return;
  }
  const komentar = document.getElementById('reviewText')?.value.trim() ?? '';
  const btn = document.getElementById('btnSubmitReview');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';

  fetch('review_produk.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({produk_id: PRODUK_ID, rating: ratingVal, komentar})
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      showToast('⭐ Ulasan berhasil dikirim! Terima kasih.', 'green');
      // Sembunyikan form, tampilkan pesan sukses
      const form = document.getElementById('reviewForm');
      if (form) {
        form.innerHTML = `
          <div style="text-align:center;padding:16px 0;">
            <i class="fa-solid fa-circle-check" style="font-size:2rem;color:#16a34a;"></i>
            <p style="font-weight:700;margin-top:10px;color:#166534;">Ulasan berhasil dikirim!</p>
            <p style="font-size:.85rem;color:var(--muted);">Halaman akan dimuat ulang...</p>
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

// ── TOAST ─────────────────────────────────────────────
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.className = 'toast'; }, 3200);
}

// Close modal saat klik di luar
document.getElementById('modalBeli').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>
