<?php
// beli_produk.php - Proses pembelian / order
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
$alamat  = trim($body['alamat']     ?? '');
$catatan = trim($body['catatan']    ?? '');

if (!$produkId || !$alamat) {
    jsonResponse(['error' => 'Data tidak lengkap'], 400);
}

$db = getDB();

// Ambil produk + lock
$stmt = $db->prepare("SELECT * FROM produk WHERE id=:id AND status='tersedia' FOR UPDATE");
$db->beginTransaction();
try {
    $stmt->execute([':id' => $produkId]);
    $produk = $stmt->fetch();

    if (!$produk) {
        $db->rollBack();
        jsonResponse(['error' => 'Produk tidak tersedia'], 400);
    }

    if ((int)$produk['seller_id'] === $userId) {
        $db->rollBack();
        jsonResponse(['error' => 'Tidak bisa membeli produk sendiri'], 403);
    }

    if ((int)$produk['stok'] <= 0) {
        $db->rollBack();
        jsonResponse(['error' => 'Stok habis'], 400);
    }

    // Kurangi stok
    $newStok = (int)$produk['stok'] - 1;
    $newStatus = $newStok <= 0 ? 'terjual' : 'tersedia';

    $db->prepare("UPDATE produk SET stok=:stok, status=:status WHERE id=:id")
       ->execute([':stok'=>$newStok, ':status'=>$newStatus, ':id'=>$produkId]);

    // Update total terjual seller
    $db->prepare("UPDATE seller_profiles SET total_terjual = total_terjual + 1 WHERE user_id=:uid")
       ->execute([':uid' => $produk['seller_id']]);

    // Otomatis buat chat room antara pembeli & penjual
    $findRoom = $db->prepare("SELECT id FROM chat_rooms WHERE pembeli_id=:bid AND penjual_id=:sid AND produk_id=:pid");
    $findRoom->execute([':bid'=>$userId, ':sid'=>$produk['seller_id'], ':pid'=>$produkId]);
    $room = $findRoom->fetch();
    if (!$room) {
        $db->prepare("INSERT INTO chat_rooms (pembeli_id, penjual_id, produk_id) VALUES (:bid,:sid,:pid)")
           ->execute([':bid'=>$userId, ':sid'=>$produk['seller_id'], ':pid'=>$produkId]);
        $roomId = (int)$db->lastInsertId();
    } else {
        $roomId = (int)$room['id'];
    }

    // Kirim pesan otomatis
    $myName  = $_SESSION['username'] ?? 'Pembeli';
    $autoMsg = "Halo! Saya sudah memesan produk *{$produk['nama_produk']}*.\nAlamat pengiriman: $alamat" . ($catatan ? "\nCatatan: $catatan" : '');
    $db->prepare("INSERT INTO pesan (room_id, pengirim_id, isi_pesan) VALUES (:rid,:uid,:isi)")
       ->execute([':rid'=>$roomId, ':uid'=>$userId, ':isi'=>$autoMsg]);

    // Notif ke penjual
    $db->prepare("INSERT INTO notifikasi (user_id, judul, pesan, tipe, referensi_id) VALUES (:uid,:judul,:pesan,'transaksi',:ref)")
       ->execute([
           ':uid'   => $produk['seller_id'],
           ':judul' => "🛍️ Pesanan baru dari $myName",
           ':pesan' => "Produk: {$produk['nama_produk']} | Alamat: " . mb_strimwidth($alamat,0,80,'...'),
           ':ref'   => $produkId,
       ]);

    $db->commit();
    jsonResponse(['success' => true, 'room_id' => $roomId, 'message' => 'Pesanan berhasil! Silakan lanjutkan di chat.']);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
}
