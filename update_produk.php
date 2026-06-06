<?php
// update_produk.php – Memperbarui produk yang sudah ada
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$produk_id = (int)($_POST['produk_id'] ?? 0);

if ($produk_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID produk tidak valid.']);
    exit();
}

// Pastikan produk milik seller ini
$stmtCek = $pdo->prepare("SELECT * FROM produk WHERE id = ? AND seller_id = ?");
$stmtCek->execute([$produk_id, $user_id]);
$produkLama = $stmtCek->fetch();

if (!$produkLama) {
    echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan atau bukan milik Anda.']);
    exit();
}

// ── Ambil input ────────────────────────────────────────────────────────────
$nama_produk      = trim($_POST['nama_produk']      ?? '');
$deskripsi        = trim($_POST['deskripsi']        ?? '');
$harga            = (int)($_POST['harga']           ?? 0);
$stok             = (int)($_POST['stok']            ?? 1);
$kondisi          = $_POST['kondisi']               ?? 'Bekas Baik';
$ukuran           = trim($_POST['ukuran']           ?? '');
$lokasi_cod       = trim($_POST['lokasi_cod']       ?? '');
$metode_transaksi = trim($_POST['metode_transaksi'] ?? 'COD');
$kategori_id      = (int)($_POST['kategori_id']     ?? 0);
$new_kategori     = trim($_POST['new_kategori']     ?? '');

if (!$nama_produk || !$deskripsi || $harga <= 0 || !$lokasi_cod) {
    echo json_encode(['success' => false, 'message' => 'Field wajib tidak boleh kosong.']);
    exit();
}

// ── Kategori baru ──────────────────────────────────────────────────────────
if ($_POST['kategori_id'] === '__new__' || $kategori_id === 0) {
    if (!$new_kategori) {
        echo json_encode(['success' => false, 'message' => 'Nama kategori baru wajib diisi.']);
        exit();
    }
    $stmtKat = $pdo->prepare("SELECT id FROM kategori WHERE nama = ?");
    $stmtKat->execute([$new_kategori]);
    $ex = $stmtKat->fetch();
    $kategori_id = $ex ? (int)$ex['id'] : (function() use ($pdo, $new_kategori) {
        $pdo->prepare("INSERT INTO kategori (nama) VALUES (?)")->execute([$new_kategori]);
        return (int)$pdo->lastInsertId();
    })();
}

// ── Upload foto baru (opsional) ────────────────────────────────────────────
$foto_utama = $produkLama['foto_utama']; // default foto lama
$uploadDir  = 'uploads/produk/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$new_foto_paths = [];

if (!empty($_FILES['fotos']['name'][0])) {
    foreach ($_FILES['fotos']['tmp_name'] as $idx => $tmpName) {
        if ($_FILES['fotos']['error'][$idx] !== UPLOAD_ERR_OK) continue;
        $mimeType = mime_content_type($tmpName);
        if (!in_array($mimeType, $allowedTypes)) continue;
        if ($_FILES['fotos']['size'][$idx] > 5 * 1024 * 1024) continue;

        $ext      = pathinfo($_FILES['fotos']['name'][$idx], PATHINFO_EXTENSION);
        $filename = 'prod_' . uniqid('', true) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($tmpName, $dest)) {
            $new_foto_paths[] = $dest;
            if ($idx === 0) $foto_utama = $dest;
        }
    }
}

// ── Update produk ──────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Reset is_approved = 0 agar admin review ulang setelah edit
    $stmt = $pdo->prepare("
        UPDATE produk SET
            kategori_id = ?, nama_produk = ?, deskripsi = ?, harga = ?, stok = ?,
            kondisi = ?, ukuran = ?, foto_utama = ?, lokasi_cod = ?,
            metode_transaksi = ?, is_approved = 0, updated_at = NOW()
        WHERE id = ? AND seller_id = ?
    ");
    $stmt->execute([
        $kategori_id, $nama_produk, $deskripsi, $harga, $stok,
        $kondisi, $ukuran, $foto_utama, $lokasi_cod,
        $metode_transaksi, $produk_id, $user_id
    ]);

    // Jika ada foto baru, tambahkan ke foto_produk
    if (!empty($new_foto_paths)) {
        // Hapus foto lama dari tabel foto_produk
        $pdo->prepare("DELETE FROM foto_produk WHERE produk_id = ?")->execute([$produk_id]);

        $stmtFoto = $pdo->prepare("INSERT INTO foto_produk (produk_id, url_foto, urutan) VALUES (?, ?, ?)");
        foreach ($new_foto_paths as $i => $path) {
            $stmtFoto->execute([$produk_id, $path, $i]);
        }
    }

    // Notifikasi admin bahwa produk diedit & perlu review ulang
    $admins = $pdo->query("SELECT id_users FROM users WHERE role = 'admin'")->fetchAll();
    $stmtNotif = $pdo->prepare("
        INSERT INTO notifikasi (user_id, judul, pesan, tipe, referensi_id)
        VALUES (?, 'Produk Diperbarui – Review Diperlukan', ?, 'produk', ?)
    ");
    foreach ($admins as $admin) {
        $stmtNotif->execute([
            $admin['id_users'],
            "Produk \"{$nama_produk}\" telah diperbarui dan memerlukan review ulang.",
            $produk_id
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui: ' . $e->getMessage()]);
}
