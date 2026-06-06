<?php
// upload.php – Halaman upload/edit produk untuk penjual
session_start();
require_once 'koneksi.php';   // menyediakan $pdo dan $conn

// ── Guard ─────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$role     = $_SESSION['role'] ?? 'pembeli';

if ($role !== 'penjual' && $role !== 'admin') {
    header("Location: become_seller.php");
    exit();
}

// ── Kategori ──────────────────────────────────────────────────────────────
$kategoris = $pdo->query("SELECT * FROM kategori ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);

// ── Mode edit ─────────────────────────────────────────────────────────────
$editMode   = false;
$editProduk = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmtE  = $pdo->prepare(
        "SELECT p.*, k.nama AS nama_kategori
         FROM produk p
         LEFT JOIN kategori k ON k.id = p.kategori_id
         WHERE p.id = ? AND p.seller_id = ?"
    );
    $stmtE->execute([$editId, $user_id]);
    $editProduk = $stmtE->fetch(PDO::FETCH_ASSOC);
    if ($editProduk) $editMode = true;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $editMode ? 'Edit Produk' : 'Upload Produk' ?> – LapakThriftUMSU</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --primary:#4f8cff;--primary-dark:#1e3a8a;
  --bg:#f4f7fb;--text:#0f172a;--muted:#64748b;
  --border:#dbe4f0;--shadow:0 10px 30px rgba(79,140,255,.10);
  --radius:14px;--radius-lg:22px;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Manrope',sans-serif;background:var(--bg);color:var(--text);}
a{text-decoration:none;color:inherit;}

nav{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.95);backdrop-filter:blur(14px);border-bottom:1px solid #eef2f7;}
.nav-inner{max-width:1200px;margin:auto;padding:14px 20px;display:flex;align-items:center;gap:12px;}
.logo{display:flex;align-items:center;gap:10px;font-size:1.2rem;font-weight:800;color:var(--primary-dark);}
.logo .icon{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;color:white;}
.logo span{color:var(--primary);}
.nav-spacer{flex:1;}
.pill{padding:9px 16px;border-radius:999px;border:1px solid var(--border);background:white;font-weight:700;display:flex;align-items:center;gap:6px;cursor:pointer;transition:.25s;font-family:inherit;font-size:.85rem;color:var(--text);}
.pill:hover{transform:translateY(-2px);box-shadow:var(--shadow);}

.wrap{max-width:860px;margin:28px auto;padding:0 20px;}

.hero-upload{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:22px;position:relative;overflow:hidden;}
.hero-upload::before{content:'';position:absolute;top:-60px;right:-60px;width:220px;height:220px;background:rgba(255,255,255,.08);border-radius:50%;}
.hero-upload h1{font-size:1.8rem;margin-bottom:6px;position:relative;}
.hero-upload p{opacity:.9;font-size:.92rem;position:relative;}
.status-pill{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.15);font-weight:700;font-size:.83rem;margin-top:12px;}

.card{background:white;border-radius:var(--radius-lg);padding:24px;box-shadow:var(--shadow);margin-bottom:16px;}
.card-title{font-size:1.1rem;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:8px;padding-bottom:14px;border-bottom:1px solid var(--border);}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.grid-1{grid-column:1/-1;}
@media(max-width:640px){.grid-2{grid-template-columns:1fr;}.grid-1{grid-column:unset;}}

.form-group label{display:block;font-weight:800;font-size:.86rem;margin-bottom:7px;color:#374151;}
.req{color:#ef4444;}
.form-group input,.form-group textarea,.form-group select{
  width:100%;border:1.5px solid var(--border);border-radius:10px;padding:11px 13px;
  font-family:inherit;font-size:.9rem;outline:none;transition:.2s;background:#fafcff;color:var(--text);
}
.form-group textarea{min-height:100px;resize:vertical;}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{
  border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,140,255,.12);background:white;
}
.hint{color:var(--muted);font-size:.78rem;margin-top:5px;}

.foto-zone{border:2px dashed var(--border);border-radius:var(--radius-lg);padding:20px;background:#fafcff;transition:.25s;cursor:pointer;}
.foto-zone:hover{border-color:var(--primary);background:#eff6ff;}
.foto-placeholder{text-align:center;color:var(--muted);}
.foto-placeholder i{font-size:2.5rem;margin-bottom:10px;color:var(--primary);display:block;}
.preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;}
.preview-item{position:relative;border-radius:10px;overflow:hidden;aspect-ratio:1;background:#f1f5f9;}
.preview-item img{width:100%;height:100%;object-fit:cover;}
.preview-item .remove-foto{position:absolute;top:4px;right:4px;width:22px;height:22px;background:#ef4444;color:white;border:none;border-radius:50%;cursor:pointer;font-size:.7rem;display:flex;align-items:center;justify-content:center;}
.preview-item .main-badge{position:absolute;bottom:4px;left:4px;background:var(--primary);color:white;font-size:.65rem;font-weight:800;padding:2px 6px;border-radius:4px;}

.footer-actions{display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;padding-top:6px;}
.btn{border:none;cursor:pointer;border-radius:12px;padding:12px 22px;font-weight:800;font-family:inherit;display:inline-flex;align-items:center;gap:8px;transition:.25s;font-size:.9rem;}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;}
.btn-primary:hover{filter:brightness(.96);transform:translateY(-1px);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-outline{background:white;border:1.5px solid var(--border);color:var(--text);}
.btn-outline:hover{border-color:var(--primary);color:var(--primary-dark);}
.btn-danger{background:white;border:1.5px solid #fecaca;color:#dc2626;}
.btn-danger:hover{background:#fff1f2;}

.loading-bar{height:3px;background:linear-gradient(90deg,var(--primary),var(--primary-dark),var(--primary));background-size:200% 100%;animation:loadBar 1.2s infinite;border-radius:2px;display:none;}
.loading-bar.show{display:block;}
@keyframes loadBar{0%{background-position:200% 0}100%{background-position:-200% 0}}

.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:#0f172a;color:white;padding:14px 22px;border-radius:14px;font-weight:700;font-size:.9rem;z-index:9999;transition:transform .35s,opacity .35s;opacity:0;max-width:90vw;text-align:center;}
.toast.show{transform:translateX(-50%) translateY(0);opacity:1;}
</style>
</head>
<body>

<nav>
  <div class="nav-inner">
    <a href="userafterlogin.php" class="logo">
      <div class="icon"><i class="fa-solid fa-shop"></i></div>
      lapakthrift<span>UMSU</span>
    </a>
    <div class="nav-spacer"></div>
    <button class="pill" onclick="window.location.href='seller.php'">
      <i class="fa-solid fa-store"></i> Toko Saya
    </button>
    <button class="pill" onclick="window.location.href='userafterlogin.php'">
      <i class="fa-solid fa-arrow-left"></i> Beranda
    </button>
  </div>
</nav>

<div class="wrap">
  <div class="hero-upload">
    <h1><i class="fa-solid fa-cloud-arrow-up"></i> <?= $editMode ? 'Edit Produk' : 'Upload Produk' ?></h1>
    <p><?= $editMode ? 'Perbarui informasi produk kamu. Produk akan di-review ulang oleh admin.' : 'Tambahkan produk preloved kamu. Produk akan ditampilkan setelah disetujui admin.' ?></p>
    <div class="status-pill">
      <i class="fa-solid fa-<?= $editMode ? 'pen-to-square' : 'user-shield' ?>"></i>
      Status: <?= $editMode ? 'Edit – Butuh Review Ulang' : 'Menunggu Persetujuan Admin' ?>
    </div>
  </div>

  <div class="loading-bar" id="loadingBar"></div>

  <form id="uploadForm" onsubmit="submitForm(event)" enctype="multipart/form-data">
    <?php if ($editMode): ?>
      <input type="hidden" name="produk_id" value="<?= $editProduk['id'] ?>">
    <?php endif; ?>

    <!-- FOTO -->
    <div class="card">
      <div class="card-title"><i class="fa-solid fa-image" style="color:var(--primary)"></i> Foto Produk</div>
      <div class="foto-zone" id="fotoZone" onclick="document.getElementById('fotoFileInput').click()"
           ondragover="handleDragover(event)" ondragleave="removeDragover()" ondrop="handleDrop(event)">
        <?php if ($editMode && !empty($editProduk['foto_utama'])): ?>
          <div class="preview-grid" id="previewGrid">
            <div class="preview-item">
              <img src="<?= escH($editProduk['foto_utama']) ?>" alt="Foto saat ini">
              <span class="main-badge">Utama</span>
            </div>
          </div>
          <p style="text-align:center;color:var(--muted);font-size:.8rem;margin-top:10px;">Upload foto baru untuk mengganti foto lama</p>
        <?php else: ?>
          <div class="foto-placeholder" id="fotoPlaceholder">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p><strong>Klik atau drag foto ke sini</strong></p>
            <small>JPG, PNG, WEBP – Maks 5MB per foto (maks 5 foto)</small>
          </div>
          <div class="preview-grid" id="previewGrid" style="display:none;"></div>
        <?php endif; ?>
      </div>
      <input type="file" id="fotoFileInput" name="fotos[]" accept="image/*" multiple style="display:none" onchange="handleFileSelect(this)">
      <p class="hint" style="margin-top:8px;"><i class="fa-solid fa-circle-info"></i> Foto pertama akan jadi foto utama produk.</p>
    </div>

    <!-- INFO PRODUK -->
    <div class="card">
      <div class="card-title"><i class="fa-solid fa-tag" style="color:var(--primary)"></i> Informasi Produk</div>
      <div class="grid-2">
        <div class="form-group grid-1">
          <label>Nama Produk <span class="req">*</span></label>
          <input type="text" name="nama_produk" placeholder="Contoh: Kemeja Flannel Preloved" required
                 value="<?= $editMode ? escH($editProduk['nama_produk']) : '' ?>">
        </div>
        <div class="form-group">
          <label>Kategori <span class="req">*</span></label>
          <select name="kategori_id" id="kategoriSelect" required>
            <?php foreach ($kategoris as $k): ?>
              <option value="<?= $k['id'] ?>" <?= ($editMode && $editProduk['kategori_id']==$k['id']) ? 'selected' : '' ?>>
                <?= escH($k['nama']) ?>
              </option>
            <?php endforeach; ?>
            <option value="__new__">+ Tambah Kategori Baru</option>
          </select>
        </div>
        <div class="form-group" id="newKategoriGroup" style="display:none;">
          <label>Nama Kategori Baru <span class="req">*</span></label>
          <input type="text" name="new_kategori" id="newKategoriInput" placeholder="Contoh: Aksesori">
        </div>
        <div class="form-group">
          <label>Kondisi <span class="req">*</span></label>
          <select name="kondisi" required>
            <?php foreach (['Bekas Layak','Bekas Baik','Hampir Baru','Baru'] as $k): ?>
              <option value="<?= $k ?>" <?= ($editMode && $editProduk['kondisi']===$k) ? 'selected' : '' ?>>
                <?= $k ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Harga (Rp) <span class="req">*</span></label>
          <input type="number" name="harga" placeholder="75000" min="1" required
                 value="<?= $editMode ? (int)$editProduk['harga'] : '' ?>">
          <div class="hint">Negosiasi bisa dilanjutkan via chat.</div>
        </div>
        <div class="form-group">
          <label>Stok <span class="req">*</span></label>
          <input type="number" name="stok" placeholder="1" min="0" required
                 value="<?= $editMode ? (int)$editProduk['stok'] : '' ?>">
        </div>
        <div class="form-group">
          <label>Ukuran (Opsional)</label>
          <input type="text" name="ukuran" placeholder="Contoh: M / 42 / 1 Liter"
                 value="<?= $editMode ? escH($editProduk['ukuran'] ?? '') : '' ?>">
        </div>
        <div class="form-group grid-1">
          <label>Deskripsi <span class="req">*</span></label>
          <textarea name="deskripsi" placeholder="Ceritakan kondisi barang, kelengkapan, alasan dijual, dll." required><?= $editMode ? escH($editProduk['deskripsi'] ?? '') : '' ?></textarea>
        </div>
      </div>
    </div>

    <!-- TRANSAKSI -->
    <div class="card">
      <div class="card-title"><i class="fa-solid fa-handshake" style="color:var(--primary)"></i> Informasi Transaksi</div>
      <div class="grid-2">
        <div class="form-group">
          <label>Lokasi COD <span class="req">*</span></label>
          <input type="text" name="lokasi_cod" placeholder="Contoh: Kampus UMSU / Lapangan" required
                 value="<?= $editMode ? escH($editProduk['lokasi_cod'] ?? '') : '' ?>">
        </div>
        <div class="form-group">
          <label>Metode Transaksi <span class="req">*</span></label>
          <select name="metode_transaksi">
            <?php foreach (['COD'=>'COD (Bayar di Tempat)','Transfer'=>'Transfer Bank','COD/Transfer'=>'COD / Transfer'] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($editMode && ($editProduk['metode_transaksi']??'')===$val) ? 'selected' : '' ?>>
                <?= $lbl ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="footer-actions">
      <button type="button" class="btn btn-outline" onclick="resetForm()">
        <i class="fa-solid fa-rotate-left"></i> Reset
      </button>
      <?php if ($editMode): ?>
        <button type="button" class="btn btn-danger" onclick="window.location.href='seller.php'">
          <i class="fa-solid fa-xmark"></i> Batal Edit
        </button>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary" id="submitBtn">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <?= $editMode ? 'Simpan Perubahan' : 'Upload Produk' ?>
      </button>
    </div>
  </form>
</div>

<div class="toast" id="toastEl"></div>

<script>
const isEdit = <?= $editMode ? 'true' : 'false' ?>;
let selectedFiles = [];

function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showToast(msg, dur=4000) {
  const t = document.getElementById('toastEl');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), dur);
}

document.getElementById('kategoriSelect').addEventListener('change', function() {
  const grp = document.getElementById('newKategoriGroup');
  const inp = document.getElementById('newKategoriInput');
  if (this.value === '__new__') { grp.style.display = 'block'; inp.required = true; }
  else { grp.style.display = 'none'; inp.required = false; inp.value = ''; }
});

function handleDragover(e) { e.preventDefault(); document.getElementById('fotoZone').classList.add('dragover'); }
function removeDragover() { document.getElementById('fotoZone').classList.remove('dragover'); }

function handleFileSelect(input) {
  Array.from(input.files).forEach(f => addFile(f));
  renderPreviews();
  input.value = '';   // reset agar bisa pilih file yang sama lagi
}
function handleDrop(e) {
  e.preventDefault(); removeDragover();
  Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')).forEach(f => addFile(f));
  renderPreviews();
}
function addFile(file) {
  if (file.size > 5 * 1024 * 1024) { showToast('⚠️ ' + file.name + ' terlalu besar (maks 5MB).'); return; }
  if (selectedFiles.length >= 5)    { showToast('⚠️ Maksimal 5 foto.'); return; }
  selectedFiles.push(file);
}
function renderPreviews() {
  const grid = document.getElementById('previewGrid');
  const placeholder = document.getElementById('fotoPlaceholder');
  grid.innerHTML = selectedFiles.map((f, i) => {
    const url = URL.createObjectURL(f);
    return `<div class="preview-item">
      <img src="${url}" alt="${escH(f.name)}">
      ${i === 0 ? '<span class="main-badge">Utama</span>' : ''}
      <button type="button" class="remove-foto" onclick="removeFile(${i})"><i class="fa-solid fa-xmark"></i></button>
    </div>`;
  }).join('');
  if (selectedFiles.length) { grid.style.display = 'grid'; if (placeholder) placeholder.style.display = 'none'; }
  else { grid.style.display = 'none'; if (placeholder) placeholder.style.display = 'block'; }
}
function removeFile(idx) { selectedFiles.splice(idx, 1); renderPreviews(); }

async function submitForm(e) {
  e.preventDefault();
  const submitBtn  = document.getElementById('submitBtn');
  const loadingBar = document.getElementById('loadingBar');

  if (!isEdit && selectedFiles.length === 0) {
    showToast('⚠️ Foto produk wajib diupload.'); return;
  }

  const data = new FormData(document.getElementById('uploadForm'));
  data.delete('fotos[]');
  selectedFiles.forEach(f => data.append('fotos[]', f));

  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
  loadingBar.classList.add('show');

  try {
    const endpoint = isEdit ? 'update_produk.php' : 'simpan_produk.php';
    const res  = await fetch(endpoint, { method: 'POST', body: data });
    const json = await res.json();
    if (json.success) {
      showToast(isEdit
        ? '✅ Produk berhasil diperbarui! Menunggu review ulang admin.'
        : '✅ Produk berhasil diupload! Menunggu persetujuan admin.'
      );
      setTimeout(() => window.location.href = 'seller.php', 2200);
    } else {
      showToast('❌ ' + (json.message || 'Gagal menyimpan produk.'));
      submitBtn.disabled = false;
      submitBtn.innerHTML = isEdit
        ? '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan'
        : '<i class="fa-solid fa-cloud-arrow-up"></i> Upload Produk';
    }
  } catch (err) {
    showToast('❌ Error: ' + err.message);
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload Produk';
  } finally {
    loadingBar.classList.remove('show');
  }
}

function resetForm() {
  document.getElementById('uploadForm').reset();
  selectedFiles = [];
  renderPreviews();
  document.getElementById('newKategoriGroup').style.display = 'none';
}
</script>
</body>
</html>

<?php
function escH($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
