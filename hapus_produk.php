<?php
/**
 * hapus_produk.php – API hapus produk milik penjual
 * Method: POST | Returns: JSON
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Tidak terautentikasi.']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$produk_id = (int)($_POST['produk_id'] ?? 0);

if (!$produk_id) {
    echo json_encode(['success'=>false,'message'=>'ID produk tidak valid.']);
    exit();
}

// Verifikasi kepemilikan
$stmt = $pdo->prepare("SELECT id, foto_utama FROM produk WHERE id=? AND seller_id=?");
$stmt->execute([$produk_id, $user_id]);
$prod = $stmt->fetch();

if (!$prod) {
    echo json_encode(['success'=>false,'message'=>'Produk tidak ditemukan.']);
    exit();
}

// Soft delete: ubah status jadi 'dihapus'
$pdo->prepare("UPDATE produk SET status='dihapus' WHERE id=?")->execute([$produk_id]);

echo json_encode(['success'=>true,'message'=>'Produk berhasil dihapus.']);
?>
