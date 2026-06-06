<?php
// get_produk.php - API produk untuk userafterlogin.php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db  = getDB();
$kat = trim($_GET['kategori'] ?? '');

$baseWhere = "p.status = 'tersedia'";
$params    = [];

if ($kat && $kat !== 'Semua') {
    $baseWhere .= " AND k.nama = :kategori";
    $params[':kategori'] = $kat;
}

$sql = "
    SELECT
        p.id,
        p.nama_produk   AS brand,
        p.deskripsi,
        p.harga,
        p.stok,
        p.kondisi       AS detail,
        p.ukuran,
        p.foto_utama    AS img,
        p.views,
        p.created_at,
        k.nama          AS cat,
        u.nama          AS seller,
        u.id_users      AS seller_id,
        sp.nama_toko,
        sp.foto_toko
    FROM produk p
    JOIN users u     ON u.id_users  = p.seller_id
    JOIN kategori k  ON k.id        = p.kategori_id
    LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
    WHERE $baseWhere
    ORDER BY p.created_at DESC
    LIMIT 60
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$products = array_map(function($r) {
    $img = trim($r['img'] ?? '');

    if (!$img) {
        // Tidak ada foto, pakai placeholder
        $img = 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?q=80&w=600&auto=format&fit=crop';
    } elseif (!str_starts_with($img, 'http://') && !str_starts_with($img, 'https://')) {
        // Path lokal dari DB sudah 'uploads/produk/namafile.jpg'
        // Cukup bersihkan leading slash saja
        $img = ltrim($img, '/');
    }
    // Jika http/https, biarkan apa adanya

    return [
        'id'         => (int)$r['id'],
        'brand'      => $r['brand'],
        'detail'     => $r['detail'] . ($r['ukuran'] ? ' · ' . $r['ukuran'] : ''),
        'harga_raw'  => (float)$r['harga'],
        'price'      => 'Rp ' . number_format((float)$r['harga'], 0, ',', '.'),
        'stok'       => (int)$r['stok'],
        'cat'        => $r['cat'],
        'img'        => $img,
        'seller'     => $r['nama_toko'] ?: $r['seller'],
        'seller_id'  => (int)$r['seller_id'],
        'deskripsi'  => $r['deskripsi'] ?? '',
        'views'      => (int)$r['views'],
        'created_at' => $r['created_at'],
    ];
}, $rows);

$totalProduk = $db->query("SELECT COUNT(*) FROM produk WHERE status='tersedia'")->fetchColumn();
$totalSeller = $db->query("SELECT COUNT(DISTINCT seller_id) FROM produk WHERE status='tersedia'")->fetchColumn();

echo json_encode([
    'products'     => $products,
    'total_produk' => (int)$totalProduk,
    'total_seller' => (int)$totalSeller,
], JSON_UNESCAPED_UNICODE);