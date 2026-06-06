<?php
/**
 * update_seller_profile.php – API update profil toko penjual
 * Method: POST | Returns: JSON
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Tidak terautentikasi.']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$nama_toko= trim($_POST['nama_toko'] ?? '');
$deskripsi= trim($_POST['deskripsi'] ?? '');
$kota     = trim($_POST['kota']      ?? '');
$no_hp    = trim($_POST['no_hp']     ?? '');

if (!$nama_toko) {
    echo json_encode(['success'=>false,'message'=>'Nama toko wajib diisi.']);
    exit();
}

// Update seller_profiles
$pdo->prepare("UPDATE seller_profiles SET nama_toko=?, deskripsi=?, kota=? WHERE user_id=?")
    ->execute([$nama_toko, $deskripsi, $kota, $user_id]);

// Update no_hp di users
if ($no_hp) {
    $pdo->prepare("UPDATE users SET no_hp=? WHERE id_users=?")->execute([$no_hp, $user_id]);
}

echo json_encode(['success'=>true,'message'=>'Profil toko berhasil diperbarui.']);
?>
