<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$role     = $_SESSION['role'] ?? 'pembeli';
$user_id  = (int)$_SESSION['user_id'];

// Ambil statistik
$total_produk = 0;
$total_seller = 0;

$r = mysqli_query($conn, "SELECT COUNT(*) AS total FROM produk WHERE status='tersedia' AND is_approved=1");
if ($r) $total_produk = mysqli_fetch_assoc($r)['total'];

$r2 = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) AS total FROM seller_profiles");
if ($r2) $total_seller = mysqli_fetch_assoc($r2)['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>LapakThriftUMSU</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --primary:#4f8cff;
  --primary-dark:#1e3a8a;
  --bg:#f4f7fb;
  --text:#0f172a;
  --text-muted:#64748b;
  --border:#dbe4f0;
  --shadow:0 10px 30px rgba(79,140,255,.10);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--text);}
a{text-decoration:none;color:inherit;}

/* NAV */
nav{position:sticky;top:0;z-index:1000;background:rgba(255,255,255,.95);backdrop-filter:blur(14px);border-bottom:1px solid #eef2f7;}
.nav-inner{max-width:1300px;margin:auto;padding:14px 20px;display:flex;align-items:center;gap:14px;}
.logo{display:flex;align-items:center;gap:10px;font-size:1.25rem;font-weight:800;color:var(--primary-dark);white-space:nowrap;flex-shrink:0;}
.logo .icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:white;font-size:.95rem;flex-shrink:0;}
.logo span{color:var(--primary);}
.search-wrap{flex:1;min-width:0;position:relative;}
.search-bar{display:flex;align-items:center;gap:8px;background:white;border-radius:50px;padding:10px 14px;border:1.5px solid var(--border);box-shadow:var(--shadow);transition:all .3s;}
.search-bar.expanded{border-color:var(--primary);box-shadow:0 0 0 4px rgba(79,140,255,.15);}
.search-bar i{color:var(--text-muted);font-size:.95rem;flex-shrink:0;}
.search-bar input{width:100%;border:none;outline:none;background:transparent;font-family:inherit;font-size:.93rem;color:var(--text);}
.search-bar button{border:none;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;padding:8px 16px;border-radius:50px;font-weight:800;cursor:pointer;font-size:.85rem;white-space:nowrap;transition:opacity .2s;flex-shrink:0;}
.search-bar button:hover{opacity:.9;}
.search-results{display:none;position:absolute;top:calc(100% + 8px);left:0;right:0;background:white;border-radius:18px;border:1px solid var(--border);box-shadow:0 20px 50px rgba(79,140,255,.15);z-index:999;overflow:hidden;}
.search-results.show{display:block;}
.search-result-item{padding:12px 18px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:background .2s;}
.search-result-item:hover{background:#f0f6ff;}
.search-result-item .thumb{width:42px;height:42px;border-radius:10px;object-fit:cover;flex-shrink:0;}
.search-result-item .info .name{font-weight:700;font-size:.88rem;}
.search-result-item .info .price{font-size:.83rem;color:var(--primary-dark);font-weight:800;}
.search-empty{padding:20px;text-align:center;color:var(--text-muted);font-size:.88rem;}

/* DESKTOP NAV LINKS */
.nav-links{display:flex;align-items:center;gap:8px;flex-shrink:0;}
.pill{padding:9px 14px;border-radius:999px;border:1px solid var(--border);background:white;font-weight:700;display:flex;align-items:center;gap:6px;cursor:pointer;transition:.25s;font-family:inherit;font-size:.85rem;color:var(--text);white-space:nowrap;}
.pill:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
.pill-sell{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;padding:9px 16px;}
.pill-sell:hover{filter:brightness(1.05);transform:translateY(-2px);}
.user-box{display:flex;align-items:center;gap:8px;background:white;padding:7px 12px;border-radius:999px;border:1px solid var(--border);flex-shrink:0;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:white;font-size:.85rem;flex-shrink:0;}
.user-name{font-weight:700;color:var(--primary-dark);font-size:.88rem;}
.logout-btn{border:none;background:#ef4444;color:white;padding:8px 12px;border-radius:999px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:inherit;font-size:.83rem;flex-shrink:0;}
.logout-btn:hover{opacity:.88;}
.wishlist-btn{position:relative;}
#wishlistCount{background:#ef4444;color:white;width:18px;height:18px;border-radius:50%;font-size:.68rem;display:flex;align-items:center;justify-content:center;font-weight:800;}

/* HAMBURGER */
.hamburger{display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:42px;height:42px;border-radius:12px;border:1.5px solid var(--border);background:white;cursor:pointer;flex-shrink:0;transition:.2s;}
.hamburger:hover{box-shadow:var(--shadow);}
.hamburger span{display:block;width:20px;height:2px;background:var(--text);border-radius:2px;transition:all .3s;transform-origin:center;}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg);}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0);}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}

/* MOBILE DRAWER */
.mobile-menu{display:none;position:fixed;top:0;right:-100%;width:min(320px,85vw);height:100dvh;background:white;z-index:2000;box-shadow:-10px 0 40px rgba(0,0,0,.15);transition:right .35s cubic-bezier(.4,0,.2,1);flex-direction:column;padding:24px 20px;overflow-y:auto;}
.mobile-menu.open{right:0;}
.mm-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;padding-bottom:18px;border-bottom:1px solid var(--border);}
.mm-header .mm-logo{font-weight:800;font-size:1.1rem;color:var(--primary-dark);}
.mm-close{border:none;background:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted);width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;transition:.2s;}
.mm-close:hover{background:#f1f5f9;}
.mm-hello{display:flex;align-items:center;gap:12px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));border-radius:16px;padding:16px;margin-bottom:20px;color:white;}
.mm-hello .av{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.mm-hello .htext{font-size:.78rem;opacity:.8;}
.mm-hello .hname{font-weight:800;font-size:1rem;}
.mm-items{display:flex;flex-direction:column;gap:6px;flex:1;}
.mm-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:14px;cursor:pointer;font-weight:700;font-size:.95rem;color:var(--text);border:none;background:none;font-family:inherit;width:100%;text-align:left;transition:.2s;}
.mm-item:hover{background:#f0f6ff;color:var(--primary-dark);}
.mm-item .mm-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.mm-item .mm-label{flex:1;}
.mm-item .mm-badge{background:#ef4444;color:white;font-size:.7rem;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;}
.mm-logout{margin-top:auto;padding-top:16px;border-top:1px solid var(--border);}
.mm-logout-btn{display:flex;align-items:center;gap:12px;width:100%;padding:14px 16px;background:#fff1f1;border:1.5px solid #fecaca;border-radius:14px;cursor:pointer;font-weight:800;font-size:.93rem;color:#dc2626;font-family:inherit;transition:.2s;}
.mm-logout-btn:hover{background:#fee2e2;}

/* OVERLAY */
.drawer-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1999;backdrop-filter:blur(2px);opacity:0;transition:opacity .35s;}
.drawer-overlay.open{display:block;opacity:1;}

/* BANNER JADI PENJUAL */
.seller-banner{max-width:1300px;margin:24px auto 0;padding:0 20px;}
.seller-banner-inner{background:linear-gradient(135deg,#f59e0b 0%,#d97706 50%,#b45309 100%);border-radius:22px;padding:22px 28px;display:flex;align-items:center;gap:20px;box-shadow:0 8px 30px rgba(245,158,11,.3);position:relative;overflow:hidden;}
.seller-banner-inner::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;background:rgba(255,255,255,.1);border-radius:50%;}
.sb-icon{width:60px;height:60px;background:rgba(255,255,255,.2);border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;}
.sb-text{flex:1;color:white;}
.sb-text h3{font-size:1.2rem;font-weight:800;margin-bottom:4px;}
.sb-text p{font-size:.88rem;opacity:.9;}
.sb-btn{border:none;background:white;color:#d97706;padding:12px 22px;border-radius:14px;font-weight:800;cursor:pointer;font-family:inherit;font-size:.9rem;display:flex;align-items:center;gap:8px;white-space:nowrap;transition:.25s;flex-shrink:0;}
.sb-btn:hover{transform:scale(1.04);box-shadow:0 6px 20px rgba(0,0,0,.15);}

/* CONTENT */
.wrap{max-width:1300px;margin:24px auto;padding:0 20px;}
.hero{border-radius:28px;padding:46px 50px;color:white;margin-bottom:28px;position:relative;overflow:hidden;}
.hero-bg{position:absolute;inset:0;background-image:url('https://images.unsplash.com/photo-1562774053-701939374585?w=1200&auto=format&fit=crop');background-size:cover;background-position:center;filter:brightness(.5) saturate(1.1);}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(135deg,rgba(30,58,138,.85) 0%,rgba(79,140,255,.6) 100%);}
.hero-content{position:relative;z-index:2;}
.hero-flex{display:flex;align-items:center;gap:36px;flex-wrap:wrap;}
.hero-left{flex:1;min-width:260px;}
.hero h1{font-size:3.2rem;line-height:1.05;margin-bottom:16px;font-weight:800;}
.hero h1 span{color:#7dd3fc;}
.hero p{max-width:560px;line-height:1.8;opacity:.95;font-size:.97rem;}
.hero-buttons{margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;}
.hero-btn{border:none;cursor:pointer;padding:13px 22px;border-radius:14px;font-size:.9rem;font-weight:800;transition:.3s;display:flex;align-items:center;gap:10px;font-family:inherit;}
.primary-btn{background:white;color:var(--primary-dark);}
.primary-btn:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.15);}
.secondary-btn{background:rgba(255,255,255,.15);color:white;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(10px);}
.secondary-btn:hover{background:rgba(255,255,255,.22);transform:translateY(-3px);}
.hero-slide{flex:0 0 290px;position:relative;height:290px;}
.slide-img{position:absolute;inset:0;border-radius:22px;overflow:hidden;opacity:0;transform:scale(.94) translateX(30px);transition:opacity .7s ease,transform .7s ease;box-shadow:0 20px 50px rgba(0,0,0,.35);}
.slide-img.active{opacity:1;transform:scale(1) translateX(0);}
.slide-img img{width:100%;height:100%;object-fit:cover;display:block;}
.slide-caption{position:absolute;bottom:14px;left:14px;right:14px;background:rgba(15,23,42,.6);backdrop-filter:blur(10px);border-radius:12px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;}
.slide-caption span{font-weight:800;font-size:.8rem;}
.slide-badge{background:rgba(255,255,255,.2);border-radius:999px;padding:4px 10px;font-size:.7rem;font-weight:700;}
.slide-dots{position:absolute;top:14px;right:14px;display:flex;gap:6px;}
.sdot{height:8px;border-radius:999px;background:rgba(255,255,255,.4);cursor:pointer;transition:.35s;width:8px;}
.sdot.active{background:white;width:22px;}
.hero-stats{margin-top:28px;display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.hero-stat{background:rgba(255,255,255,.12);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.15);padding:16px;border-radius:18px;display:flex;align-items:center;gap:12px;transition:.3s;}
.hero-stat:hover{transform:translateY(-4px);background:rgba(255,255,255,.18);}
.hero-stat .stat-icon{font-size:1.6rem;}
.hero-stat small{display:block;opacity:.8;font-size:.76rem;}
.hero-stat strong{font-size:1.05rem;font-weight:800;}

/* SECTIONS */
.section-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:20px;gap:16px;flex-wrap:wrap;}
.section-title{font-size:1.9rem;font-weight:800;color:var(--text);margin-bottom:4px;}
.section-subtitle{color:var(--text-muted);font-size:.92rem;}
.product-info{background:white;padding:10px 16px;border-radius:999px;border:1px solid var(--border);box-shadow:var(--shadow);font-weight:700;color:var(--primary-dark);font-size:.88rem;white-space:nowrap;}
.chips{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;}
.chip{padding:10px 18px;border-radius:999px;border:1px solid var(--border);background:white;font-weight:700;cursor:pointer;font-family:inherit;font-size:.85rem;transition:.25s;}
.chip.active{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;border:none;}
.chip:hover:not(.active){box-shadow:var(--shadow);transform:translateY(-1px);}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;}

/* Skeleton */
.skeleton-card{border-radius:18px;overflow:hidden;background:white;box-shadow:0 2px 12px rgba(0,0,0,.06);}
.skeleton-img{height:320px;background:linear-gradient(90deg,#f0f4f8 25%,#e2e8f0 50%,#f0f4f8 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;}
.skeleton-line{height:14px;margin:12px 16px 6px;border-radius:6px;background:linear-gradient(90deg,#f0f4f8 25%,#e2e8f0 50%,#f0f4f8 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;}
.skeleton-line.short{width:60%;height:12px;margin:0 16px 12px;}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

.product-card{cursor:pointer;transition:.3s;}
.product-card:hover{transform:translateY(-5px);}
.product-image{position:relative;width:100%;height:320px;border-radius:18px;overflow:hidden;background:#ddd;}
.product-image img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s;}
.product-card.sold .product-image img{filter:brightness(.75) saturate(.8);}
.sold-badge{position:absolute;top:14px;left:14px;background:#dc2626;color:white;padding:7px 12px;border-radius:999px;font-size:.78rem;font-weight:800;}
.product-card:hover .product-image img{transform:scale(1.04);}
.wishlist-heart{position:absolute;right:12px;bottom:12px;width:46px;height:46px;border:none;border-radius:50%;background:white;box-shadow:0 8px 24px rgba(0,0,0,.18);font-size:1.15rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .2s;}
.wishlist-heart:hover{transform:scale(1.1);}
.wishlist-heart i{transition:color .3s,transform .3s;}
.wishlist-heart.loved i{color:#ef4444;transform:scale(1.2);}
.heart-anim{position:fixed;pointer-events:none;font-size:1.8rem;color:#ef4444;animation:heartPop .6s ease forwards;}
@keyframes heartPop{0%{transform:scale(0) translate(-50%,-50%);opacity:1;}60%{transform:scale(1.5) translate(-50%,-50%);opacity:1;}100%{transform:scale(1.2) translate(-50%,-50%) translateY(-40px);opacity:0;}}
.product-content{padding:12px 4px 0;}
.brand-name{font-size:.97rem;font-weight:700;color:#334155;margin-bottom:2px;}
.product-detail{color:#64748b;margin-bottom:6px;font-size:.86rem;}
.product-price{font-size:1.15rem;font-weight:800;}

/* Empty state */
.empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
.empty-state .emoji{font-size:3rem;margin-bottom:12px;}
.empty-state p{font-size:.95rem;}

/* WISHLIST PANEL */
#wishlistPanel{position:fixed;right:20px;top:80px;width:340px;background:white;padding:20px;border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.18);z-index:2000;transform:translateX(120%);transition:transform .4s cubic-bezier(.4,0,.2,1);}
#wishlistPanel.open{transform:translateX(0);}
.panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
.panel-header h2{font-size:1.1rem;font-weight:800;}
.panel-close{border:none;background:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);}
.wishlist-items{max-height:380px;overflow-y:auto;}
.wishlist-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);}
.wishlist-item img{width:50px;height:50px;border-radius:10px;object-fit:cover;flex-shrink:0;}
.wi-info{flex:1;}
.wi-brand{font-weight:700;font-size:.88rem;}
.wi-price{color:var(--primary-dark);font-weight:800;font-size:.85rem;}
.wi-remove{border:none;background:none;color:#ef4444;cursor:pointer;font-size:.95rem;}
.empty-wishlist{text-align:center;color:var(--text-muted);padding:28px 0;font-size:.88rem;}
.close-btn{width:100%;border:none;padding:12px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;font-weight:800;cursor:pointer;margin-top:14px;font-family:inherit;font-size:.92rem;}

/* Badge penjual di nav */
.seller-badge-pill{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border:none;}
.seller-badge-pill:hover{filter:brightness(1.05);}

/* FOOTER */
footer{margin-top:60px;background:#0f172a;color:white;padding:40px 20px;}
.footer-inner{max-width:1200px;margin:auto;}
.footer-bottom{margin-top:20px;padding-top:20px;border-top:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;flex-wrap:wrap;gap:16px;}
.social{display:flex;gap:10px;}
.social a{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;transition:.2s;}
.social a:hover{background:rgba(255,255,255,.2);}

/* RESPONSIVE */
@media (max-width:900px){
  .nav-links{display:none;}
  .hamburger{display:flex;}
  .mobile-menu{display:flex;}
  .search-bar button{display:none;}
  .hero{padding:32px 28px;}
  .hero h1{font-size:2.6rem;}
  .hero-slide{flex:0 0 240px;height:240px;}
  .grid{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;}
  .product-image{height:260px;}
  #wishlistPanel{width:calc(100vw - 30px);right:15px;top:80px;}
  .seller-banner-inner{flex-direction:column;text-align:center;}
  .sb-btn{width:100%;justify-content:center;}
}
@media (max-width:600px){
  .nav-inner{padding:12px 14px;}
  .hero{padding:22px 18px;border-radius:20px;margin-bottom:20px;}
  .hero h1{font-size:1.85rem;line-height:1.1;}
  .hero-flex{flex-direction:column;gap:20px;}
  .hero-slide{flex:none;width:100%;height:220px;}
  .hero-buttons{flex-direction:column;}
  .hero-stats{grid-template-columns:1fr;gap:10px;}
  .section-title{font-size:1.45rem;}
  .chips{flex-wrap:nowrap;overflow-x:auto;padding-bottom:6px;}
  .chips::-webkit-scrollbar{display:none;}
  .chip{flex-shrink:0;}
  .grid{grid-template-columns:1fr 1fr;gap:12px;}
  .product-image{height:210px;}
  .wrap{padding:0 14px;margin:16px auto;}
  .skeleton-img{height:210px;}
}
</style>
</head>
<body>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeMobileMenu()"></div>

<!-- MOBILE DRAWER -->
<div class="mobile-menu" id="mobileMenu">
  <div class="mm-header">
    <div class="mm-logo"><i class="fa-solid fa-shop" style="color:var(--primary);margin-right:6px;"></i>lapakthriftUMSU</div>
    <button class="mm-close" onclick="closeMobileMenu()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="mm-hello">
    <div class="av"><i class="fa-solid fa-user"></i></div>
    <div>
      <div class="htext">Halo, selamat datang 👋</div>
      <div class="hname"><?php echo $username; ?></div>
    </div>
  </div>
  <div class="mm-items">
    <?php if ($role === 'penjual' || $role === 'admin'): ?>
    <button class="mm-item" onclick="closeMobileMenu();window.location.href='upload.php'">
      <div class="mm-icon" style="background:#eff6ff;color:var(--primary)"><i class="fa-solid fa-plus"></i></div>
      <span class="mm-label">Upload Produk</span>
    </button>
    <?php else: ?>
    <button class="mm-item" onclick="closeMobileMenu();mulaiJualan()">
      <div class="mm-icon" style="background:#fef3c7;color:#d97706"><i class="fa-solid fa-store"></i></div>
      <span class="mm-label">Mulai Berjualan</span>
    </button>
    <?php endif; ?>
    <button class="mm-item" onclick="closeMobileMenu();toggleWishlist()">
      <div class="mm-icon" style="background:#fff1f2;color:#ef4444"><i class="fa-regular fa-heart"></i></div>
      <span class="mm-label">Wishlist</span>
      <span class="mm-badge" id="mmWishlistCount">0</span>
    </button>
  </div>
  <div class="mm-logout">
    <button class="mm-logout-btn" onclick="logoutUser()">
      <i class="fa-solid fa-right-from-bracket"></i>
      Logout dari Akun
    </button>
  </div>
</div>

<!-- NAV -->
<nav>
  <div class="nav-inner">
    <a href="userafterlogin.php" class="logo">
      <div class="icon"><i class="fa-solid fa-shop"></i></div>
      lapakthrift<span>UMSU</span>
    </a>
    <div class="search-wrap">
      <div class="search-bar" id="searchBar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Cari barang, buku, alat kost..." oninput="handleSearch()" onfocus="expandSearch()" onblur="collapseSearch()"/>
        <button onclick="doSearch()">Cari</button>
      </div>
      <div class="search-results" id="searchResults"></div>
    </div>
    <div class="nav-links">
      <?php if ($role === 'penjual' || $role === 'admin'): ?>
        <button class="pill seller-badge-pill" onclick="window.location.href='seller.php'"><i class="fa-solid fa-store"></i> Toko Saya</button>
        <button class="pill" onclick="window.location.href='upload.php'"><i class="fa-solid fa-plus"></i> Upload</button>
      <?php else: ?>
        <button class="pill pill-sell" onclick="mulaiJualan()"><i class="fa-solid fa-store"></i> Mulai Berjualan</button>
      <?php endif; ?>
      <button class="pill wishlist-btn" onclick="toggleWishlist()">
        <i class="fa-regular fa-heart"></i> Wishlist
        <span id="wishlistCount">0</span>
      </button>
      <div class="user-box">
        <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
        <div class="user-name"><?php echo $username; ?></div>
      </div>
      <button class="logout-btn" onclick="logoutUser()">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </button>
    </div>
    <button class="hamburger" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- BANNER MULAI BERJUALAN (hanya untuk pembeli) -->
<?php if ($role === 'pembeli'): ?>
<div class="seller-banner">
  <div class="seller-banner-inner">
    <div class="sb-icon">🛍️</div>
    <div class="sb-text">
      <h3>Punya barang yang ingin dijual?</h3>
      <p>Bergabung sebagai penjual di LapakThriftUMSU dan raih penghasilan tambahan dari barang preloved kamu!</p>
    </div>
    <button class="sb-btn" onclick="mulaiJualan()">
      <i class="fa-solid fa-store"></i> Mulai Berjualan
    </button>
  </div>
</div>
<?php endif; ?>

<!-- MAIN -->
<div class="wrap">
  <section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <div class="hero-flex">
        <div class="hero-left">
          <h1>LAPAK THRIFT<br>FOR <span>UMSU</span></h1>
          <p>Platform jual beli barang preloved mahasiswa UMSU dengan harga lebih hemat dan terjangkau.</p>
          <div class="hero-buttons">
            <?php if ($role === 'penjual' || $role === 'admin'): ?>
              <button class="hero-btn primary-btn" onclick="window.location.href='seller.php'"><i class="fa-solid fa-store"></i> Toko Saya</button>
            <?php else: ?>
              <button class="hero-btn primary-btn" onclick="mulaiJualan()"><i class="fa-solid fa-store"></i> Mulai Berjualan</button>
            <?php endif; ?>
            <button class="hero-btn secondary-btn" onclick="showHowItWorks()"><i class="fa-solid fa-circle-play"></i> Cara Kerjanya</button>
          </div>
        </div>
        <div class="hero-slide">
          <div class="slide-img active" id="slide-0">
            <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=600&auto=format&fit=crop" alt="Fashion">
            <div class="slide-caption"><span>👗 Fashion Preloved</span><span class="slide-badge">Hemat s/d 70%</span></div>
          </div>
          <div class="slide-img" id="slide-1">
            <img src="https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=600&auto=format&fit=crop" alt="Thrift">
            <div class="slide-caption"><span>🛍️ Belanja Thrift</span><span class="slide-badge">Pilihan Terlengkap</span></div>
          </div>
          <div class="slide-img" id="slide-2">
            <img src="https://images.unsplash.com/photo-1601924994987-69e26d50dc26?w=600&auto=format&fit=crop" alt="Buku">
            <div class="slide-caption"><span>📚 Buku & Alat Kuliah</span><span class="slide-badge">Mulai Rp 10.000</span></div>
          </div>
          <div class="slide-dots">
            <div class="sdot active" id="sd0" onclick="goSlide(0)"></div>
            <div class="sdot" id="sd1" onclick="goSlide(1)"></div>
            <div class="sdot" id="sd2" onclick="goSlide(2)"></div>
          </div>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="stat-icon">🕒</div><div><small>Waktu Lokal</small><strong id="clockDisplay">00:00:00</strong></div></div>
        <div class="hero-stat"><div class="stat-icon">👨‍🎓</div><div><small>Penjual Aktif</small><strong id="sellerCount"><?php echo $total_seller; ?></strong></div></div>
        <div class="hero-stat"><div class="stat-icon">📦</div><div><small>Total Produk</small><strong id="productCount"><?php echo $total_produk; ?></strong></div></div>
      </div>
    </div>
  </section>

  <div class="section-header">
    <div>
      <h2 class="section-title">Produk Terbaru</h2>
      <p class="section-subtitle">Temukan barang preloved terbaru dari mahasiswa UMSU</p>
    </div>
    <div class="product-info"><span id="totalProductText">Memuat...</span></div>
  </div>

  <div class="chips">
    <button class="chip active" onclick="filterChip(this,'Semua')">Semua</button>
    <button class="chip" onclick="filterChip(this,'Fashion')">Fashion</button>
    <button class="chip" onclick="filterChip(this,'Elektronik')">Elektronik</button>
    <button class="chip" onclick="filterChip(this,'Buku')">Buku</button>
    <button class="chip" onclick="filterChip(this,'Alat Kost')">Alat Kost</button>
  </div>

  <div class="grid" id="productGrid">
    <?php for($i=0;$i<8;$i++): ?>
    <div class="skeleton-card">
      <div class="skeleton-img"></div>
      <div class="skeleton-line"></div>
      <div class="skeleton-line short"></div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- WISHLIST PANEL -->
<div id="wishlistPanel">
  <div class="panel-header">
    <h2>❤️ Wishlist ku</h2>
    <button class="panel-close" onclick="toggleWishlist()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="wishlist-items" id="wishlistItems">
    <div class="empty-wishlist">Belum ada produk di wishlist<br><span style="font-size:1.5rem">🛍️</span></div>
  </div>
  <button class="close-btn" onclick="toggleWishlist()">Tutup</button>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <h2 style="margin-bottom:10px;"><i class="fa-solid fa-shop" style="color:var(--primary);margin-right:8px;"></i>LapakThriftUMSU</h2>
    <p style="opacity:.7;">Marketplace preloved mahasiswa dengan tampilan modern.</p>
    <div class="footer-bottom">
      <div style="opacity:.6;font-size:.88rem;">© 2025 LapakThriftUMSU</div>
      <div class="social">
        <a href="#"><i class="fa-brands fa-instagram"></i></a>
        <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
        <a href="#"><i class="fa-brands fa-tiktok"></i></a>
      </div>
    </div>
  </div>
</footer>

<script>
const CURRENT_USER_ID  = <?php echo json_encode($user_id); ?>;
const CURRENT_USERNAME = <?php echo json_encode($username); ?>;
const CURRENT_ROLE     = <?php echo json_encode($role); ?>;

let products = [];
let wishlist = [];

/* ── Mulai Berjualan ── */
function mulaiJualan() {
  if (confirm('Apakah kamu ingin mendaftarkan diri sebagai penjual di LapakThriftUMSU?\n\nKamu akan diarahkan ke halaman profil toko.')) {
    window.location.href = 'become_seller.php';
  }
}

/* ── Fetch produk dari get_produk.php ── */
async function loadProducts(kategori = '') {
  try {
    const url = 'get_produk.php' + (kategori && kategori !== 'Semua' ? `?kategori=${encodeURIComponent(kategori)}` : '');
    const res  = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    products = data.products || [];
    document.getElementById('productCount').textContent = data.total_produk ?? products.length;
    document.getElementById('sellerCount').textContent  = data.total_seller ?? '-';
    renderGrid(products);
  } catch (err) {
    console.error('Gagal memuat produk:', err);
    document.getElementById('productGrid').innerHTML =
      `<div class="empty-state" style="grid-column:1/-1">
        <div class="emoji">😕</div>
        <p>Gagal memuat produk. Pastikan database terhubung.<br><small>${err.message}</small></p>
      </div>`;
    document.getElementById('totalProductText').textContent = '0 Produk';
  }
}

/* ── Render grid ── */
function renderGrid(list) {
  const grid = document.getElementById('productGrid');
  document.getElementById('totalProductText').textContent = `${list.length} Produk Tersedia`;
  if (!list.length) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
      <div class="emoji">🛍️</div><p>Belum ada produk di kategori ini.</p></div>`;
    return;
  }
  grid.innerHTML = list.map(p => {
    const soldOut = p.stok <= 0;
    const loved   = wishlist.find(w => w.id === p.id);
    return `<div class="product-card ${soldOut ? 'sold' : ''}"
      data-id="${p.id}" onclick="viewProduct(this)">
      <div class="product-image">
        <img src="${p.img}" alt="${p.brand}" loading="lazy"
             onerror="this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=600&auto=format&fit=crop'">
        ${soldOut ? '<div class="sold-badge">Habis</div>' : ''}
        <button type="button" class="wishlist-heart ${loved ? 'loved' : ''}" id="heart-${p.id}"
                onclick="event.stopPropagation();toggleHeart(event,${p.id})">
          <i class="${loved ? 'fa-solid' : 'fa-regular'} fa-heart" style="${loved ? 'color:#ef4444' : ''}"></i>
        </button>
      </div>
      <div class="product-content">
        <div class="brand-name">${p.brand}</div>
        <div class="product-detail">${p.detail} · ${p.cat}</div>
        <div class="product-price">${p.price}</div>
        <div style="margin-top:6px;font-size:.85rem;color:${soldOut ? '#dc2626' : '#0f172a'};font-weight:700;">
          ${soldOut ? 'Stok habis' : `Stok ${p.stok}`}
        </div>
      </div>
    </div>`;
  }).join('');
}

function viewProduct(card) {
  const productId = card.dataset.id;
  if (!productId) return;
  window.location.href = 'detail_produk.php?id=' + productId;
}

/* ── Wishlist ── */
function toggleHeart(e, id) {
  const btn = document.getElementById('heart-' + id);
  const product = products.find(p => p.id === id);
  const idx = wishlist.findIndex(w => w.id === id);
  if (idx === -1) {
    wishlist.push(product); btn.classList.add('loved');
    btn.innerHTML = '<i class="fa-solid fa-heart" style="color:#ef4444"></i>';
    spawnHeart(btn);
  } else {
    wishlist.splice(idx, 1); btn.classList.remove('loved');
    btn.innerHTML = '<i class="fa-regular fa-heart"></i>';
  }
  updateWishlistCount(); renderWishlistItems();
}
function spawnHeart(btn) {
  const r = btn.getBoundingClientRect();
  const el = document.createElement('div');
  el.className = 'heart-anim'; el.textContent = '❤️';
  el.style.cssText = `position:fixed;left:${r.left+r.width/2}px;top:${r.top+r.height/2}px;z-index:9999;pointer-events:none;font-size:1.8rem;`;
  document.body.appendChild(el); setTimeout(() => el.remove(), 700);
}
function updateWishlistCount() {
  const n = wishlist.length;
  document.getElementById('wishlistCount').textContent = n;
  document.getElementById('mmWishlistCount').textContent = n;
}
function renderWishlistItems() {
  const el = document.getElementById('wishlistItems');
  if (!wishlist.length) {
    el.innerHTML = '<div class="empty-wishlist">Belum ada produk di wishlist<br><span style="font-size:1.5rem">🛍️</span></div>';
    return;
  }
  el.innerHTML = wishlist.map(w => `<div class="wishlist-item">
    <img src="${w.img}" alt="${w.brand}">
    <div class="wi-info"><div class="wi-brand">${w.brand}</div><div class="wi-price">${w.price}</div></div>
    <button class="wi-remove" onclick="removeWishlist(${w.id})"><i class="fa-solid fa-trash-can"></i></button>
  </div>`).join('');
}
function removeWishlist(id) {
  wishlist = wishlist.filter(w => w.id !== id);
  updateWishlistCount(); renderWishlistItems();
  const btn = document.getElementById('heart-' + id);
  if (btn) { btn.classList.remove('loved'); btn.innerHTML = '<i class="fa-regular fa-heart"></i>'; }
}
function toggleWishlist() { document.getElementById('wishlistPanel').classList.toggle('open'); }

/* ── Filter chips ── */
function filterChip(el, cat) {
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  loadProducts(cat);
}

/* ── Search ── */
function expandSearch()  { document.getElementById('searchBar').classList.add('expanded'); }
function collapseSearch(){ setTimeout(()=>{ document.getElementById('searchBar').classList.remove('expanded'); document.getElementById('searchResults').classList.remove('show'); }, 200); }
function handleSearch() {
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  const res = document.getElementById('searchResults');
  if (!q) { res.classList.remove('show'); return; }
  const matches = products.filter(p => p.brand.toLowerCase().includes(q) || p.cat.toLowerCase().includes(q));
  res.innerHTML = matches.length === 0
    ? '<div class="search-empty">Produk tidak ditemukan 🙁</div>'
    : matches.map(p => `<div class="search-result-item" onclick="window.location.href='detail_produk.php?id=${p.id}'">
        <img class="thumb" src="${p.img}" alt="${p.brand}">
        <div class="info"><div class="name">${p.brand}</div><div class="price">${p.price}</div></div>
      </div>`).join('');
  res.classList.add('show');
}
function doSearch() { handleSearch(); }

/* ── Mobile menu ── */
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  const btn  = document.getElementById('hamburgerBtn');
  const overlay = document.getElementById('drawerOverlay');
  const isOpen = menu.classList.contains('open');
  if (isOpen) { menu.classList.remove('open'); btn.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
  else { menu.classList.add('open'); btn.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeMobileMenu() {
  document.getElementById('mobileMenu').classList.remove('open');
  document.getElementById('hamburgerBtn').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

/* ── Slideshow ── */
let slideIdx = 0;
function goSlide(n) {
  document.querySelectorAll('.slide-img').forEach((s, i) => s.classList.toggle('active', i === n));
  document.querySelectorAll('.sdot').forEach((d, i) => d.classList.toggle('active', i === n));
  slideIdx = n;
}
setInterval(() => goSlide((slideIdx + 1) % 3), 3500);

/* ── Clock ── */
function updateClock() {
  const now = new Date();
  document.getElementById('clockDisplay').textContent =
    `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')} WIB`;
}
setInterval(updateClock, 1000); updateClock();

/* ── Helpers ── */
function goProfile()     { window.location.href = 'profil.php'; }
function logoutUser()    { window.location.href = 'logout.php'; }
function showHowItWorks(){ alert('Cara Berjualan di LapakThriftUMSU\n\n1. Klik "Mulai Berjualan"\n2. Lengkapi profil toko\n3. Upload Produk\n4. Pembeli menghubungi via chat\n\nSelamat berjualan! 🚀'); }

/* ── Init ── */
window.addEventListener('DOMContentLoaded', async function () {
  const params = new URLSearchParams(window.location.search);
  const cat = params.get('category');
  if (cat) {
    const decoded = decodeURIComponent(cat);
    const chip = Array.from(document.querySelectorAll('.chip')).find(c => c.innerText === decoded);
    if (chip) { chip.click(); return; }
  }
  await loadProducts();
});
</script>
</body>
</html>
