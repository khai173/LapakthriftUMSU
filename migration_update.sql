-- ============================================================
-- LapakThriftUMSU – SQL UPDATE / MIGRATION
-- Jalankan file ini di phpMyAdmin atau MySQL CLI
-- SETELAH import lapakthriftumsu.sql yang sudah ada
-- ============================================================

USE `lapakthriftumsu`;

-- ── 1. Tambah kolom baru ke tabel produk ─────────────────────
-- is_approved: 0=menunggu, 1=disetujui, -1=ditolak
ALTER TABLE `produk`
  ADD COLUMN IF NOT EXISTS `is_approved` tinyint(1) NOT NULL DEFAULT 0 AFTER `views`,
  ADD COLUMN IF NOT EXISTS `lokasi_cod` varchar(150) DEFAULT NULL AFTER `is_approved`,
  ADD COLUMN IF NOT EXISTS `metode_transaksi` enum('COD','Transfer','COD/Transfer') DEFAULT 'COD' AFTER `lokasi_cod`;

-- ── 2. Index baru untuk performa query beranda ───────────────
ALTER TABLE `produk`
  ADD INDEX IF NOT EXISTS `idx_produk_approved` (`is_approved`, `status`);

-- ── 3. Tambah kategori default ───────────────────────────────
INSERT IGNORE INTO `kategori` (`nama`) VALUES
  ('Fashion'),
  ('Elektronik'),
  ('Buku'),
  ('Alat Kost'),
  ('Seni & Hobi'),
  ('Aksesori'),
  ('Makanan & Minuman'),
  ('Lainnya');

-- ── 4. Tambah kolom lokasi_cod dan metode ke produk jika perlu
-- (Untuk kompatibilitas MySQL versi lama yang tidak support ADD COLUMN IF NOT EXISTS)
-- Jalankan manual jika error:
-- ALTER TABLE `produk` ADD COLUMN `is_approved` tinyint(1) NOT NULL DEFAULT 0;
-- ALTER TABLE `produk` ADD COLUMN `lokasi_cod` varchar(150) DEFAULT NULL;
-- ALTER TABLE `produk` ADD COLUMN `metode_transaksi` enum('COD','Transfer','COD/Transfer') DEFAULT 'COD';

-- ── 5. Update get_produk.php hanya tampilkan yang is_approved=1
-- Perlu update WHERE di get_produk.php:
-- WHERE p.status = 'tersedia' AND p.is_approved = 1

-- ── 6. Contoh data penjual untuk testing ────────────────────
-- (Opsional, hapus kalau tidak perlu)
-- INSERT INTO `users` (nama, email, password, role, is_active) VALUES
--   ('Rizky Preloved', 'rizky@umsu.ac.id', MD5('rizky123'), 'penjual', 1);
