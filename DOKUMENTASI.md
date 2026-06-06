# 📦 LapakThriftUMSU – Dokumentasi Integrasi Frontend–Backend–Database

## Gambaran Arsitektur

```
userafterlogin.php  ──→  get_produk.php        (AJAX, ambil produk dari DB)
                    ──→  detail_produk.php       (klik produk → halaman detail)
                    ──→  chat.php                (tombol chat di nav)

detail_produk.php   ──→  wishlist_toggle.php     (AJAX, tambah/hapus wishlist)
                    ──→  beli_produk.php          (AJAX, proses pembelian)
                    ──→  review_produk.php        (AJAX, kirim ulasan/bintang)
                    ──→  chat.php                (tombol "Chat Penjual")
                    ──→  seller_profil.php        (tombol "Lihat Profil")

seller_profil.php   ──→  chat.php                (tombol "Chat Penjual")
                    ──→  detail_produk.php        (klik produk di profil seller)

chat.php            ──→  chat_handler.php         (AJAX polling 3 detik)
```

---

## 📁 Daftar File yang Dibuat / Diubah

| File | Fungsi |
|------|--------|
| `config.php` | Koneksi PDO ke MySQL, helper `getDB()`, `jsonResponse()` |
| `get_produk.php` | API GET produk untuk grid di `userafterlogin.php` |
| `detail_produk.php` | Halaman detail produk (ganti `detail.html`) |
| `seller_profil.php` | Profil penjual dari DB (ganti `sellerprofil.html`) |
| `chat.php` | Halaman chat real-time polling (ganti `chat.html`) |
| `chat_handler.php` | API backend: buat room, kirim pesan, get pesan, mark read |
| `beli_produk.php` | API proses pembelian → kurangi stok → buat chat room otomatis |
| `review_produk.php` | API submit ulasan & update rating seller |
| `wishlist_toggle.php` | API toggle wishlist (tambah/hapus) |
| `get_notifikasi.php` | API notifikasi untuk badge di navbar |

---

## 🗄️ Perubahan Database

Database kamu sudah lengkap. Tidak perlu ALTER TABLE.
Tabel yang digunakan:

- `users` – data pengguna
- `produk` – data produk
- `kategori` – nama kategori
- `seller_profiles` – profil toko penjual
- `foto_produk` – foto tambahan produk
- `chat_rooms` – room percakapan (pembeli ↔ penjual)
- `pesan` – isi pesan chat
- `ulasan` – review & rating produk
- `wishlist` – wishlist pembeli
- `notifikasi` – notifikasi sistem (chat, transaksi, review)

---

## 🔧 Cara Setup

### 1. Konfigurasi Database
Edit `config.php`, sesuaikan:
```php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');       // username MySQL kamu
define('DB_PASS', '');           // password MySQL kamu
define('DB_NAME', 'lapakthriftumsu');
```

### 2. Import Database
```bash
mysql -u root -p lapakthriftumsu < lapakthriftumsu.sql
```

### 3. Buat folder uploads
```bash
mkdir uploads
chmod 755 uploads
```

### 4. Isi data kategori (jika belum ada)
```sql
INSERT INTO kategori (nama) VALUES 
('Fashion'), ('Elektronik'), ('Buku'), ('Alat Kost');
```

### 5. Update link di `userafterlogin.php`
Di file `userafterlogin.php` yang sudah ada, **ganti** fungsi `viewProduct()` menjadi:
```javascript
function viewProduct(card) {
  window.location.href = 'detail_produk.php?id=' + card.dataset.id;
}
```

Dan fungsi `quickView()`:
```javascript
function quickView(id) {
  window.location.href = 'detail_produk.php?id=' + id;
}
```

### 6. Update link Chat di navbar `userafterlogin.php`
Ganti semua `href="chat.html"` menjadi `href="chat.php"`.

---

## 🔗 Alur Lengkap Sisi Pembeli

```
1. Login → userafterlogin.php
2. Lihat produk grid (diambil dari DB via get_produk.php)
3. Klik produk → detail_produk.php?id=XXX
4. Di detail:
   ├─ Klik "Beli Langsung" → modal alamat → beli_produk.php
   │   └─ Stok berkurang, chat room dibuat otomatis, notif ke penjual
   ├─ Klik "Chat Penjual" → chat_handler.php (buat room) → chat.php
   ├─ Klik "WhatsApp" → buka WA penjual
   ├─ Klik "Lihat Profil" → seller_profil.php?id=XXX
   ├─ Klik ❤️ Wishlist → wishlist_toggle.php
   └─ Tulis ulasan (setelah beli) → review_produk.php
5. Di seller_profil.php:
   ├─ Lihat semua produk penjual
   └─ Klik "Chat Penjual" → chat.php
6. Di chat.php:
   ├─ Daftar room muncul di sidebar
   ├─ Pesan real-time via polling 3 detik
   └─ Notif badge diupdate via get_notifikasi.php
```

---

## 🔔 Integrasi Notifikasi di `userafterlogin.php`

Tambahkan badge notifikasi di navbar dengan menambahkan ini ke `<script>` di `userafterlogin.php`:

```javascript
// Polling notifikasi setiap 30 detik
async function checkNotif() {
  try {
    const r = await fetch('get_notifikasi.php?action=count');
    const d = await r.json();
    const badge = document.getElementById('notifBadge');
    if (badge) badge.textContent = d.unread > 0 ? d.unread : '';
    
    // Juga update chat badge
    const chatBadge = document.getElementById('chatBadge');
    const cr = await fetch('chat_handler.php?action=unread_count');
    const cd = await cr.json();
    if (chatBadge) chatBadge.textContent = cd.unread > 0 ? cd.unread : '';
  } catch(e) {}
}
checkNotif();
setInterval(checkNotif, 30000);
```

---

## ✅ Checklist Fitur

- [x] Klik produk → `detail_produk.php` (dari DB, bukan sessionStorage)
- [x] Galeri foto (foto utama + foto tambahan dari `foto_produk`)
- [x] Tombol Beli → modal alamat → stok berkurang → notif penjual
- [x] Tombol Chat Penjual → buat room → `chat.php`
- [x] Tombol WhatsApp → buka WA dengan nomor dari DB
- [x] Tombol Lihat Profil → `seller_profil.php`
- [x] Wishlist toggle (tersimpan di DB)
- [x] Form review & bintang (hanya pembeli, satu kali per produk)
- [x] Rata-rata rating dengan bar chart per bintang
- [x] Chat real-time via polling 3 detik
- [x] Notifikasi: pesan baru, review baru, pesanan baru
- [x] Profil penjual: semua produk aktif dari DB
- [x] Penjual tidak bisa beli/review produk sendiri
- [x] Stok habis → tombol beli disabled
