<?php
/**
 * upload_foto_toko.php – API upload foto profil toko penjual
 * Method: POST (multipart: foto) | Returns: JSON
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Tidak terautentikasi.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if (empty($_FILES['foto']['tmp_name'])) {
    echo json_encode(['success'=>false,'message'=>'File foto tidak ditemukan.']);
    exit();
}

$file = $_FILES['foto'];
$allowed = ['image/jpeg','image/png','image/webp','image/gif'];
$mime    = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Format file tidak didukung.']);
    exit();
}

if ($file['size'] > 3 * 1024 * 1024) {
    echo json_encode(['success'=>false,'message'=>'Ukuran file terlalu besar (maks 3MB).']);
    exit();
}

$upload_dir = __DIR__ . '/uploads/toko/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'toko_' . $user_id . '_' . time() . '.' . strtolower($ext);
$destPath = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success'=>false,'message'=>'Gagal menyimpan file.']);
    exit();
}

$url = 'uploads/toko/' . $filename;

// Update di seller_profiles
$pdo->prepare("UPDATE seller_profiles SET foto_toko=? WHERE user_id=?")->execute([$url, $user_id]);

echo json_encode(['success'=>true,'url'=>$url,'message'=>'Foto toko berhasil diperbarui.']);
?>
