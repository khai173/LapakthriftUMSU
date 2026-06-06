<?php
// toggle_wishlist.php
session_start();
require_once 'koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'login required']); exit();
}

$user_id   = (int)$_SESSION['user_id'];
$produk_id = (int)($_POST['produk_id'] ?? 0);
if ($produk_id <= 0) { echo json_encode(['error' => 'invalid']); exit(); }

$st = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND produk_id = ?");
$st->execute([$user_id, $produk_id]);
$exists = $st->fetch();

if ($exists) {
    $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND produk_id = ?")->execute([$user_id, $produk_id]);
    echo json_encode(['added' => false]);
} else {
    $pdo->prepare("INSERT INTO wishlist (user_id, produk_id) VALUES (?, ?)")->execute([$user_id, $produk_id]);
    echo json_encode(['added' => true]);
}
