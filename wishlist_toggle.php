<?php
// wishlist_toggle.php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Tidak terautentikasi'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method tidak didukung'], 405);
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$userId  = (int)$_SESSION['user_id'];
$produkId= (int)($body['produk_id'] ?? 0);

if (!$produkId) jsonResponse(['error' => 'produk_id diperlukan'], 400);

$db = getDB();

$cek = $db->prepare("SELECT id FROM wishlist WHERE user_id=:uid AND produk_id=:pid");
$cek->execute([':uid'=>$userId, ':pid'=>$produkId]);
$existing = $cek->fetch();

if ($existing) {
    $db->prepare("DELETE FROM wishlist WHERE user_id=:uid AND produk_id=:pid")
       ->execute([':uid'=>$userId, ':pid'=>$produkId]);
    jsonResponse(['inWishlist' => false]);
} else {
    $db->prepare("INSERT INTO wishlist (user_id, produk_id) VALUES (:uid,:pid)")
       ->execute([':uid'=>$userId, ':pid'=>$produkId]);
    jsonResponse(['inWishlist' => true]);
}
