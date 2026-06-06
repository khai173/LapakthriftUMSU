<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Belum login']);
    exit;
}

$seller_id   = $_SESSION['user_id'];
$nama_produk = trim($_POST['nama_produk'] ?? '');
$deskripsi   = trim($_POST['deskripsi'] ?? '');
$harga       = $_POST['harga'] ?? 0;
$stok        = $_POST['stok'] ?? 1;
$kondisi     = $_POST['kondisi'] ?? 'Bekas Baik';
$ukuran      = $_POST['ukuran'] ?? '';
$kategori_id = $_POST['kategori_id'] ?? 1;

if (!$nama_produk || !$harga) {
    echo json_encode(['status' => 'error', 'message' => 'Nama produk dan harga wajib diisi']);
    exit;
}

// Upload foto
$foto_utama = '';
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
    $ext        = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $nama_file  = uniqid('produk_') . '.' . $ext;
    $tujuan     = '../uploads/produk/' . $nama_file;
    
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $tujuan)) {
        $foto_utama = 'uploads/produk/' . $nama_file;
    }
}

// Cek seller profile, kalau belum ada buat dulu
$cek = $pdo->prepare("SELECT id FROM seller_profiles WHERE user_id = ?");
$cek->execute([$seller_id]);
if (!$cek->fetch()) {
    $pdo->prepare("INSERT INTO seller_profiles (user_id, nama_toko) VALUES (?, ?)")
        ->execute([$seller_id, 'Toko Saya']);
}

// Simpan produk
$stmt = $pdo->prepare("INSERT INTO produk 
    (seller_id, kategori_id, nama_produk, deskripsi, harga, stok, kondisi, ukuran, foto_utama) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $seller_id, $kategori_id, $nama_produk,
    $deskripsi, $harga, $stok, $kondisi, $ukuran, $foto_utama
]);

echo json_encode([
    'status'  => 'success',
    'message' => 'Produk berhasil diupload'
]);
?>