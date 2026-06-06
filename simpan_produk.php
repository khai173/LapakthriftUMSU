<?php
// simpan_produk.php – Menyimpan produk baru dari upload.php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

// ── Guard ──────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'pembeli';

if ($role !== 'penjual' && $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Hanya penjual yang dapat mengupload produk.']);
    exit();
}

// ── Ambil & validasi input ─────────────────────────────────────────────────
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
    // Cek duplikat
    $stmtCek = $pdo->prepare("SELECT id FROM kategori WHERE nama = ?");
    $stmtCek->execute([$new_kategori]);
    $existing = $stmtCek->fetch();
    if ($existing) {
        $kategori_id = (int)$existing['id'];
    } else {
        $pdo->prepare("INSERT INTO kategori (nama) VALUES (?)")->execute([$new_kategori]);
        $kategori_id = (int)$pdo->lastInsertId();
    }
}

// ── Upload foto ────────────────────────────────────────────────────────────
$uploadDir = 'uploads/produk/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$foto_utama   = null;
$foto_paths   = [];
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if (!empty($_FILES['fotos']['name'][0])) {
    foreach ($_FILES['fotos']['tmp_name'] as $idx => $tmpName) {
        if ($_FILES['fotos']['error'][$idx] !== UPLOAD_ERR_OK) continue;

        $mimeType = mime_content_type($tmpName);
        if (!in_array($mimeType, $allowedTypes)) continue;
        if ($_FILES['fotos']['size'][$idx] > 5 * 1024 * 1024) continue; // 5MB max

        $ext      = pathinfo($_FILES['fotos']['name'][$idx], PATHINFO_EXTENSION);
        $filename = 'prod_' . uniqid('', true) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($tmpName, $dest)) {
            $foto_paths[] = $dest;
            if ($idx === 0) $foto_utama = $dest;
        }
    }
}

if (empty($foto_paths)) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengupload foto. Pastikan foto dipilih.']);
    exit();
}

// ── Insert produk ──────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO produk
            (seller_id, kategori_id, nama_produk, deskripsi, harga, stok, kondisi, ukuran,
             foto_utama, status, is_approved, lokasi_cod, metode_transaksi, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, 'tersedia', 0, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $user_id, $kategori_id, $nama_produk, $deskripsi, $harga, $stok,
        $kondisi, $ukuran, $foto_utama, $lokasi_cod, $metode_transaksi
    ]);
    $produk_id = (int)$pdo->lastInsertId();

    // Simpan semua foto ke tabel foto_produk
    $stmtFoto = $pdo->prepare("INSERT INTO foto_produk (produk_id, url_foto, urutan) VALUES (?, ?, ?)");
    foreach ($foto_paths as $i => $path) {
        $stmtFoto->execute([$produk_id, $path, $i]);
    }

    // Kirim notifikasi ke semua admin
    $admins = $pdo->query("SELECT id_users FROM users WHERE role = 'admin'")->fetchAll();
    $stmtNotif = $pdo->prepare("
        INSERT INTO notifikasi (user_id, judul, pesan, tipe, referensi_id)
        VALUES (?, 'Produk Baru Menunggu Approval', ?, 'produk', ?)
    ");
    foreach ($admins as $admin) {
        $stmtNotif->execute([
            $admin['id_users'],
            "Produk \"{$nama_produk}\" telah diajukan dan menunggu persetujuan Anda.",
            $produk_id
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'produk_id' => $produk_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    // Hapus foto yang sudah terupload jika transaksi gagal
    foreach ($foto_paths as $path) {
        if (file_exists($path)) unlink($path);
    }
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan produk: ' . $e->getMessage()]);
}
