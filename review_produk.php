<?php
// review_produk.php — endpoint POST ulasan produk
// Dipakai oleh detail_produk.php DAN seller_profil.php
// Sesuai skema DB: tabel `ulasan` dengan UNIQUE KEY (produk_id, user_id)

session_start();
require_once 'config.php';

header('Content-Type: application/json');

/* ── Auth ── */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$userId   = (int)$_SESSION['user_id'];
$produkId = (int)($data['produk_id'] ?? 0);
$rating   = (int)($data['rating']    ?? 0);
$komentar = trim($data['komentar']   ?? '');

/* ── Validasi input ── */
if (!$produkId || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Data tidak valid.']);
    exit;
}

$db = getDB();

/* ── Ambil data produk (cek kepemilikan & status) ── */
$stmtProd = $db->prepare("SELECT id, seller_id, status FROM produk WHERE id = :id");
$stmtProd->execute([':id' => $produkId]);
$produk = $stmtProd->fetch();

if (!$produk) {
    echo json_encode(['success' => false, 'error' => 'Produk tidak ditemukan.']);
    exit;
}

/* ── Seller tidak bisa review produknya sendiri ── */
if ($userId === (int)$produk['seller_id']) {
    echo json_encode(['success' => false, 'error' => 'Tidak bisa mereview produk milik sendiri.']);
    exit;
}

/* ── Cek sudah pernah review produk ini? ── */
$cekDuplikat = $db->prepare("
    SELECT id FROM ulasan WHERE produk_id = :pid AND user_id = :uid
");
$cekDuplikat->execute([':pid' => $produkId, ':uid' => $userId]);
if ($cekDuplikat->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Kamu sudah pernah memberi ulasan untuk produk ini.']);
    exit;
}

/* ── Insert ulasan ── */
$insert = $db->prepare("
    INSERT INTO ulasan (produk_id, user_id, rating, komentar, created_at)
    VALUES (:pid, :uid, :rating, :komentar, NOW())
");
$ok = $insert->execute([
    ':pid'      => $produkId,
    ':uid'      => $userId,
    ':rating'   => $rating,
    ':komentar' => mb_substr($komentar, 0, 500),
]);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan ulasan.']);
    exit;
}

/* ── Update rating toko di seller_profiles ──
   Hitung avg dari seluruh ulasan semua produk seller tersebut
   Kolom: seller_profiles.rating DECIMAL(2,1) — sudah ada di DB
*/
$updRating = $db->prepare("
    UPDATE seller_profiles
    SET rating = (
        SELECT ROUND(AVG(ul.rating), 1)
        FROM ulasan ul
        JOIN produk p ON p.id = ul.produk_id
        WHERE p.seller_id = seller_profiles.user_id
    )
    WHERE user_id = :sid
");
$updRating->execute([':sid' => (int)$produk['seller_id']]);

echo json_encode(['success' => true]);
