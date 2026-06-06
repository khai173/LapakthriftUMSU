<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Update role user menjadi penjual
$stmt = $pdo->prepare("UPDATE users SET role = 'penjual' WHERE id_users = ?");
$stmt->execute([$user_id]);

// Update session
$_SESSION['role'] = 'penjual';

// Cek apakah sudah ada seller_profile
$stmt2 = $pdo->prepare("SELECT id FROM seller_profiles WHERE user_id = ?");
$stmt2->execute([$user_id]);
$exists = $stmt2->fetch();

if (!$exists) {
    // Buat profil toko default
    $nama_toko = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') . "'s Shop";
    $stmt3 = $pdo->prepare("INSERT INTO seller_profiles (user_id, nama_toko, deskripsi) VALUES (?, ?, ?)");
    $stmt3->execute([$user_id, $nama_toko, 'Toko preloved mahasiswa UMSU.']);
}

// Redirect ke halaman seller
header("Location: seller.php?new=1");
exit();
?>
