<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role     = $_SESSION['role'] ?? 'pembeli';

// Jika bukan penjual, redirect
if ($role !== 'penjual' && $role !== 'admin') {
    header("Location: become_seller.php");
    exit();
}

// Ambil data seller_profile
$stmt = $pdo->prepare("SELECT sp.*, u.email, u.no_hp, u.foto_profil, u.alamat
    FROM seller_profiles sp
    JOIN users u ON u.id_users = sp.user_id
    WHERE sp.user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    // Buat profil jika belum ada
    $nama_toko = $username . "'s Shop";
    $pdo->prepare("INSERT INTO seller_profiles (user_id, nama_toko, deskripsi) VALUES (?, ?, ?)")
        ->execute([$user_id, $nama_toko, 'Toko preloved mahasiswa UMSU.']);
    header("Location: seller.php");
    exit();
}

// Ambil produk milik penjual ini
$stmtProd = $pdo->prepare("
    SELECT p.*, k.nama AS nama_kategori
    FROM produk p
    LEFT JOIN kategori k ON k.id = p.kategori_id
    WHERE p.seller_id = ?
    ORDER BY p.created_at DESC
");
$stmtProd->execute([$user_id]);
$produk_list = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

// Hitung stats
$total_produk  = count($produk_list);
$total_terjual = $seller['total_terjual'] ?? 0;
$rating        = $seller['rating'] ?? 0;
$joined        = date('Y', strtotime($seller['created_at'] ?? 'now'));

$isNew = isset($_GET['new']) && $_GET['new'] == '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Toko Saya – LapakThriftUMSU</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --primary:#4f8cff;--primary-dark:#1e3a8a;
  --bg:#f4f7fb;--text:#0f172a;--muted:#64748b;
  --border:#dbe4f0;--shadow:0 10px 30px rgba(79,140,255,.10);
  --radius:16px;--radius-lg:24px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--text);}
a{text-decoration:none;color:inherit;}

/* NAV */
nav{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.95);backdrop-filter:blur(14px);border-bottom:1px solid #eef2f7;}
.nav-inner{max-width:1200px;margin:auto;padding:14px 20px;display:flex;align-items:center;gap:12px;}
.logo{display:flex;align-items:center;gap:10px;font-size:1.2rem;font-weight:800;color:var(--primary-dark);}
.logo .icon{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:white;}
.logo span{color:var(--primary);}
.nav-spacer{flex:1;}
.pill{padding:9px 16px;border-radius:999px;border:1px solid var(--border);background:white;font-weight:700;display:flex;align-items:center;gap:6px;cursor:pointer;transition:.25s;font-family:inherit;font-size:.85rem;color:var(--text);}
.pill:hover{transform:translateY(-2px);box-shadow:var(--shadow);}
.pill-upload{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;border:none;}
.pill-upload:hover{filter:brightness(.95);}

/* WRAP */
.wrap{max-width:1200px;margin:28px auto;padding:0 20px;}

/* SUCCESS BANNER */
.success-banner{background:linear-gradient(135deg,#22c55e,#16a34a);color:white;border-radius:var(--radius-lg);padding:18px 24px;margin-bottom:24px;display:flex;align-items:center;gap:14px;animation:slideDown .5s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.success-banner i{font-size:1.8rem;}
.success-banner h3{font-size:1rem;font-weight:800;}
.success-banner p{font-size:.85rem;opacity:.9;}

/* PROFILE CARD */
.profile-card{background:white;border-radius:var(--radius-lg);padding:32px;box-shadow:var(--shadow);margin-bottom:24px;position:relative;}
.profile-header{display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;}
.avatar-wrap{position:relative;flex-shrink:0;}
.avatar{width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #e0ebff;background:#dde8ff;display:flex;align-items:center;justify-content:center;font-size:3rem;color:var(--primary);overflow:hidden;}
.avatar img{width:100%;height:100%;object-fit:cover;}
.avatar-edit{position:absolute;bottom:4px;right:4px;width:32px;height:32px;background:var(--primary);color:white;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;}
.profile-info{flex:1;min-width:220px;}
.store-name{font-size:1.8rem;font-weight:800;color:var(--text);margin-bottom:6px;}
.verified-badge{display:inline-flex;align-items:center;gap:6px;background:#eaf2ff;color:var(--primary-dark);padding:6px 12px;border-radius:999px;font-size:.82rem;font-weight:700;margin-bottom:10px;}
.bio-text{color:var(--muted);line-height:1.7;font-size:.9rem;margin-bottom:16px;}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
.stat-box{background:#f8fbff;border:1px solid var(--border);border-radius:14px;padding:14px;text-align:center;}
.stat-box h3{color:var(--primary-dark);font-size:1.4rem;font-weight:800;margin-bottom:2px;}
.stat-box p{color:var(--muted);font-size:.78rem;font-weight:600;}
.profile-actions{display:flex;gap:10px;flex-wrap:wrap;}
.btn{border:none;cursor:pointer;border-radius:12px;padding:11px 18px;font-weight:800;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:.25s;font-size:.88rem;}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;}
.btn-primary:hover{filter:brightness(.96);transform:translateY(-1px);}
.btn-outline{background:white;border:1.5px solid var(--border);color:var(--text);}
.btn-outline:hover{border-color:var(--primary);color:var(--primary-dark);transform:translateY(-1px);}
.btn-upload{background:linear-gradient(135deg,#f59e0b,#d97706);color:white;}
.btn-upload:hover{filter:brightness(.96);transform:translateY(-1px);}

/* EDIT MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;backdrop-filter:blur(4px);align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:white;border-radius:var(--radius-lg);padding:28px;width:min(540px,94vw);max-height:90vh;overflow-y:auto;box-shadow:0 25px 80px rgba(0,0,0,.2);}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
.modal-header h2{font-size:1.25rem;font-weight:800;}
.modal-close{border:none;background:none;font-size:1.3rem;cursor:pointer;color:var(--muted);width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:10px;transition:.2s;}
.modal-close:hover{background:#f1f5f9;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-weight:800;font-size:.88rem;margin-bottom:7px;}
.form-group input,.form-group textarea,.form-group select{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:11px 12px;font-family:inherit;font-size:.9rem;outline:none;transition:.2s;background:#fafcff;}
.form-group input:focus,.form-group textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,140,255,.12);background:white;}
.form-group textarea{min-height:90px;resize:vertical;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}

/* PRODUK SECTION */
.section-title{font-size:1.6rem;font-weight:800;margin-bottom:6px;}
.section-sub{color:var(--muted);font-size:.88rem;margin-bottom:20px;}
.produk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;}
.produk-card{background:white;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);transition:.3s;position:relative;}
.produk-card:hover{transform:translateY(-5px);}
.produk-img{width:100%;height:240px;object-fit:cover;display:block;}
.produk-body{padding:14px;}
.produk-name{font-weight:700;font-size:.95rem;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.produk-cat{color:var(--muted);font-size:.82rem;margin-bottom:6px;}
.produk-price{font-size:1.1rem;font-weight:800;color:var(--primary-dark);}
.produk-status{position:absolute;top:12px;left:12px;padding:5px 10px;border-radius:999px;font-size:.72rem;font-weight:800;}
.status-tersedia{background:#dcfce7;color:#16a34a;}
.status-menunggu{background:#fef9c3;color:#b45309;}
.status-terjual{background:#fee2e2;color:#dc2626;}
.produk-actions{display:flex;gap:8px;margin-top:12px;}
.produk-btn{flex:1;border:none;border-radius:8px;padding:8px;font-size:.78rem;font-weight:800;cursor:pointer;font-family:inherit;transition:.2s;}
.btn-edit-prod{background:#eff6ff;color:var(--primary-dark);}
.btn-edit-prod:hover{background:#dbeafe;}
.btn-del-prod{background:#fff1f2;color:#dc2626;}
.btn-del-prod:hover{background:#fee2e2;}
.empty-products{text-align:center;padding:60px 20px;color:var(--muted);}
.empty-products .emoji{font-size:3rem;margin-bottom:12px;}

/* TOAST */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:#0f172a;color:white;padding:14px 22px;border-radius:14px;font-weight:700;font-size:.9rem;z-index:9999;transition:transform .35s cubic-bezier(.4,0,.2,1);opacity:0;}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1;}

/* RESPONSIVE */
@media(max-width:768px){
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .produk-grid{grid-template-columns:1fr 1fr;gap:14px;}
  .form-grid{grid-template-columns:1fr;}
  .avatar{width:90px;height:90px;}
  .store-name{font-size:1.4rem;}
}
@media(max-width:480px){
  .produk-grid{grid-template-columns:1fr;}
  .profile-actions{flex-direction:column;}
  .btn{width:100%;justify-content:center;}
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
    <div class="nav-spacer"></div>
    <button class="pill pill-upload" onclick="window.location.href='upload.php'">
      <i class="fa-solid fa-plus"></i> Upload Produk
    </button>
    <button class="pill" onclick="window.location.href='userafterlogin.php'">
      <i class="fa-solid fa-arrow-left"></i> Beranda
    </button>
    <button class="pill" onclick="window.location.href='logout.php'" style="color:#ef4444;">
      <i class="fa-solid fa-right-from-bracket"></i> Logout
    </button>
  </div>
</nav>

<div class="wrap">

  <?php if ($isNew): ?>
  <div class="success-banner" id="successBanner">
    <i class="fa-solid fa-circle-check"></i>
    <div>
      <h3>🎉 Selamat! Kamu sekarang jadi Penjual di LapakThriftUMSU!</h3>
      <p>Lengkapi profil toko kamu dan mulai upload produk pertamamu sekarang.</p>
    </div>
  </div>
  <?php endif; ?>

  <!-- PROFILE CARD -->
  <div class="profile-card">
    <div class="profile-header">
      <div class="avatar-wrap">
        <div class="avatar" id="avatarEl">
          <?php if (!empty($seller['foto_toko'])): ?>
            <img src="<?php echo htmlspecialchars($seller['foto_toko']); ?>" alt="Foto Toko" id="avatarImg">
          <?php else: ?>
            <i class="fa-solid fa-store"></i>
          <?php endif; ?>
        </div>
        <button class="avatar-edit" onclick="document.getElementById('fotoInput').click()" title="Ganti foto">
          <i class="fa-solid fa-camera"></i>
        </button>
        <input type="file" id="fotoInput" accept="image/*" style="display:none" onchange="uploadFoto(this)">
      </div>
      <div class="profile-info">
        <div class="store-name" id="storeNameDisplay"><?php echo htmlspecialchars($seller['nama_toko']); ?></div>
        <div class="verified-badge"><i class="fa-solid fa-graduation-cap"></i> Penjual Terverifikasi UMSU</div>
        <div class="bio-text" id="bioDisplay"><?php echo nl2br(htmlspecialchars($seller['deskripsi'] ?? '-')); ?></div>
        <div class="stats-row">
          <div class="stat-box"><h3><?php echo number_format($rating, 1); ?> ★</h3><p>Rating</p></div>
          <div class="stat-box"><h3><?php echo $total_produk; ?></h3><p>Produk</p></div>
          <div class="stat-box"><h3><?php echo $total_terjual; ?></h3><p>Terjual</p></div>
          <div class="stat-box"><h3><?php echo $joined; ?></h3><p>Bergabung</p></div>
        </div>
        <div class="profile-actions">
          <button class="btn btn-upload" onclick="window.location.href='upload.php'">
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Produk
          </button>
          <button class="btn btn-primary" onclick="openEditModal()">
            <i class="fa-solid fa-pen-to-square"></i> Edit Profil Toko
          </button>
          <button class="btn btn-outline" onclick="window.location.href='myproducts.php'">
            <i class="fa-solid fa-boxes-stacked"></i> Semua Produkku
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- PRODUK SECTION -->
  <div class="section-title">📦 Produk Saya</div>
  <div class="section-sub"><?php echo $total_produk; ?> produk terdaftar</div>

  <?php if (empty($produk_list)): ?>
  <div class="empty-products">
    <div class="emoji">📦</div>
    <p style="font-weight:700;margin-bottom:8px;">Belum ada produk!</p>
    <p>Upload produk pertamamu sekarang dan mulai berjualan.</p>
    <button class="btn btn-upload" style="margin-top:16px;" onclick="window.location.href='upload.php'">
      <i class="fa-solid fa-plus"></i> Upload Produk Pertama
    </button>
  </div>
  <?php else: ?>
  <div class="produk-grid">
    <?php foreach ($produk_list as $p):
      $foto = !empty($p['foto_utama']) ? htmlspecialchars($p['foto_utama']) : 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=600&auto=format&fit=crop';
      $status = $p['status'] ?? 'tersedia';
      $statusLabel = ['tersedia'=>'Tersedia','terjual'=>'Terjual','dihapus'=>'Dihapus','menunggu'=>'Menunggu Review'][$status] ?? ucfirst($status);
      $statusClass  = in_array($status,['tersedia']) ? 'status-tersedia' : (($status === 'menunggu') ? 'status-menunggu' : 'status-terjual');
    ?>
    <div class="produk-card" id="produk-<?php echo $p['id']; ?>">
      <span class="produk-status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
      <img class="produk-img" src="<?php echo $foto; ?>" alt="<?php echo htmlspecialchars($p['nama_produk']); ?>"
           onerror="this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=600&auto=format&fit=crop'">
      <div class="produk-body">
        <div class="produk-name"><?php echo htmlspecialchars($p['nama_produk']); ?></div>
        <div class="produk-cat"><?php echo htmlspecialchars($p['nama_kategori'] ?? 'Lainnya'); ?> · Stok: <?php echo $p['stok']; ?></div>
        <div class="produk-price">Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></div>
        <div class="produk-actions">
          <button class="produk-btn btn-edit-prod" onclick="editProduk(<?php echo $p['id']; ?>)">
            <i class="fa-solid fa-pen"></i> Edit
          </button>
          <button class="produk-btn btn-del-prod" onclick="hapusProduk(<?php echo $p['id']; ?>)">
            <i class="fa-solid fa-trash"></i> Hapus
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /wrap -->

<!-- EDIT PROFIL MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h2>✏️ Edit Profil Toko</h2>
      <button class="modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="editProfileForm" onsubmit="submitEditProfile(event)">
      <div class="form-group">
        <label>Nama Toko <span style="color:#ef4444">*</span></label>
        <input type="text" name="nama_toko" id="inp_nama_toko" value="<?php echo htmlspecialchars($seller['nama_toko']); ?>" required>
      </div>
      <div class="form-group">
        <label>Deskripsi Toko</label>
        <textarea name="deskripsi" id="inp_deskripsi"><?php echo htmlspecialchars($seller['deskripsi'] ?? ''); ?></textarea>
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label>Kota / Area</label>
          <input type="text" name="kota" value="<?php echo htmlspecialchars($seller['kota'] ?? ''); ?>" placeholder="Contoh: Medan">
        </div>
        <div class="form-group">
          <label>No. HP</label>
          <input type="text" name="no_hp" value="<?php echo htmlspecialchars($seller['no_hp'] ?? ''); ?>" placeholder="08xxxxxxxxxx">
        </div>
      </div>
      <div class="form-footer">
        <button type="button" class="btn btn-outline" onclick="closeEditModal()">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toastEl"></div>

<script>
function showToast(msg, dur=3000) {
  const t = document.getElementById('toastEl');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), dur);
}

function openEditModal()  { document.getElementById('editModal').classList.add('open'); }
function closeEditModal() { document.getElementById('editModal').classList.remove('open'); }

// Klik di luar modal untuk tutup
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});

async function submitEditProfile(e) {
  e.preventDefault();
  const form = e.target;
  const data = new FormData(form);
  try {
    const res  = await fetch('update_seller_profile.php', { method: 'POST', body: data });
    const json = await res.json();
    if (json.success) {
      document.getElementById('storeNameDisplay').textContent = data.get('nama_toko');
      document.getElementById('bioDisplay').innerHTML = (data.get('deskripsi') || '').replace(/\n/g,'<br>');
      closeEditModal();
      showToast('✅ Profil toko berhasil diperbarui!');
    } else {
      showToast('❌ ' + (json.message || 'Gagal menyimpan.'));
    }
  } catch (err) {
    showToast('❌ Terjadi kesalahan: ' + err.message);
  }
}

async function uploadFoto(input) {
  const file = input.files[0];
  if (!file) return;
  const form = new FormData();
  form.append('foto', file);
  try {
    const res  = await fetch('upload_foto_toko.php', { method:'POST', body:form });
    const json = await res.json();
    if (json.success) {
      const avatarEl = document.getElementById('avatarEl');
      avatarEl.innerHTML = `<img src="${json.url}" alt="Foto Toko" id="avatarImg" style="width:100%;height:100%;object-fit:cover;">`;
      showToast('✅ Foto toko berhasil diperbarui!');
    } else {
      showToast('❌ Gagal upload foto: ' + (json.message || ''));
    }
  } catch (err) {
    showToast('❌ Error: ' + err.message);
  }
}

function editProduk(id) {
  window.location.href = 'upload.php?edit=' + id;
}

async function hapusProduk(id) {
  if (!confirm('Yakin hapus produk ini?')) return;
  try {
    const form = new FormData();
    form.append('produk_id', id);
    const res  = await fetch('hapus_produk.php', { method:'POST', body:form });
    const json = await res.json();
    if (json.success) {
      document.getElementById('produk-' + id)?.remove();
      showToast('🗑️ Produk berhasil dihapus.');
    } else {
      showToast('❌ ' + (json.message || 'Gagal menghapus.'));
    }
  } catch (err) {
    showToast('❌ Error: ' + err.message);
  }
}

// Auto-hide success banner
<?php if ($isNew): ?>
setTimeout(() => {
  const b = document.getElementById('successBanner');
  if (b) { b.style.transition='opacity .5s'; b.style.opacity='0'; setTimeout(()=>b.remove(),500); }
}, 6000);
<?php endif; ?>
</script>
</body>
</html>
