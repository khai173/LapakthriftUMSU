# LapakThriftUMSU – Panduan File & Alur Sistem

## Struktur File yang Diperbaiki

```
lapakthrift/
├── koneksi.php          ← SATU file koneksi (mysqli + PDO)
├── db.php               ← Alias require ke koneksi.php
│
├── upload.php           ← Form upload/edit produk (penjual)
├── simpan_produk.php    ← Backend: simpan produk baru (dipanggil AJAX)
├── update_produk.php    ← Backend: update produk (dipanggil AJAX)
│
├── admin.php            ← Dashboard admin (approve/tolak/hapus)
├── adminlogin.php       ← Login admin (gantikan adminlogin.html)
│
├── userafterlogin.php   ← Halaman belanja (hanya produk approved)
│
├── mark_notif.php       ← Tandai notifikasi dibaca
└── toggle_wishlist.php  ← Toggle wishlist produk
```

---

## Alur Lengkap

### 1. Penjual Upload Produk
```
upload.php
  → (submit AJAX) → simpan_produk.php
    ✓ Foto disimpan ke uploads/produk/
    ✓ Produk masuk DB: is_approved = 0, status = 'tersedia'
    ✓ Notifikasi dikirim ke semua admin
    → Redirect ke seller.php
```

### 2. Admin Review & Approve
```
admin.php?tab=produk
  → Muncul di tabel "Menunggu Approval"
  → Klik [Approve]
    ✓ UPDATE produk SET is_approved = 1, status = 'tersedia'
    ✓ Notifikasi dikirim ke penjual: "Produk Disetujui"
  → Klik [Tolak]
    ✓ UPDATE produk SET status = 'dihapus', is_approved = 0
    ✓ Notifikasi dikirim ke penjual: "Produk Ditolak"
```

### 3. Produk Tampil di Halaman Belanja
```
userafterlogin.php
  → Query: WHERE is_approved = 1 AND status = 'tersedia'
  → Hanya produk yang sudah di-approve yang tampil
  → Fitur: search, filter kategori, filter kondisi, sort
  → Fitur: wishlist (toggle_wishlist.php)
  → Fitur: notifikasi (mark_notif.php)
  → Fitur: chat seller (chat.php)
```

### 4. Penjual Edit Produk
```
upload.php?edit={id}
  → (submit AJAX) → update_produk.php
    ✓ is_approved di-reset ke 0 (butuh review ulang)
    ✓ Notifikasi ke admin: "Produk Diperbarui – Review Diperlukan"
```

---

## Perbaikan Utama yang Dilakukan

| Masalah | Solusi |
|---|---|
| `koneksi.php` & `db.php` terpisah, beda style | Dijadikan satu: `koneksi.php` menyediakan `$conn` (mysqli) & `$pdo` |
| `simpan_produk.php` tidak ada | Dibuat lengkap dengan validasi, upload foto, insert DB, notifikasi |
| `update_produk.php` tidak ada | Dibuat dengan reset `is_approved=0` & notifikasi admin |
| `admin.php` approve tidak set `status='tersedia'` | Diperbaiki: approve → `is_approved=1` + `status='tersedia'` |
| `admin.php` tidak kirim notifikasi ke penjual | Ditambahkan notifikasi approve/tolak ke penjual |
| `userafterlogin.php` tidak ada/tidak filter approved | Dibuat baru: filter `is_approved=1 AND status='tersedia'` |
| `adminlogin.html` (static) tidak proses login | Diganti `adminlogin.php` yang memproses session |
| `upload.php` pakai `require_once 'db.php'` | Tetap kompatibel via `db.php` alias |

---

## Setup Database

Jalankan SQL dump yang sudah ada, lalu tambahkan admin:

```sql
INSERT INTO users (nama, email, password, role, is_active)
VALUES (
  'Admin',
  'admin@umsu.ac.id',
  '$2y$10$...', -- generate dengan: password_hash('password_admin', PASSWORD_DEFAULT)
  'admin',
  1
);
```

Atau buat file `create_admin.php` sementara:
```php
<?php
require 'koneksi.php';
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (nama, email, password, role, is_active) VALUES (?,?,?,'admin',1)")
    ->execute(['Admin', 'admin@umsu.ac.id', $hash]);
echo "Admin dibuat!";
```

---

## Folder Upload

Buat folder ini di root project dan berikan permission write:
```bash
mkdir -p uploads/produk
chmod 755 uploads/produk
```

---

## Session Keys yang Dipakai

| Key | Isi | Diset oleh |
|---|---|---|
| `$_SESSION['user_id']` | ID user | login.php |
| `$_SESSION['username']` | Nama user | login.php |
| `$_SESSION['email']` | Email user | login.php |
| `$_SESSION['role']` | `pembeli`/`penjual`/`admin` | login.php |
| `$_SESSION['is_admin']` | `true` (bool) | adminlogin.php |

Pastikan `login.php` kamu menyimpan semua key di atas.
