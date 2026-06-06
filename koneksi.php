<?php
// koneksi.php – LapakThriftUMSU
// Digunakan oleh admin.php (mysqli) dan file lain yang pakai $conn

$host   = 'localhost';
$db     = 'lapakthriftumsu';
$user   = 'root';
$pass   = '';
$charset = 'utf8mb4';

// ── mysqli (dipakai admin.php) ────────────────────────────────────────────
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, $charset);

// ── PDO (dipakai upload.php, simpan_produk.php, update_produk.php, dll.) ──
try {
    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]));
}
