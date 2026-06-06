<?php
// register.php — Proses pendaftaran user baru
session_start();
include 'koneksi.php';

// Tolak akses selain POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.html");
    exit();
}

// Ambil & sanitasi input
$nama     = isset($_POST['nama'])     ? trim($_POST['nama'])     : '';
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$no_hp    = isset($_POST['no_hp'])    ? trim($_POST['no_hp'])    : '';
$alamat   = isset($_POST['alamat'])   ? trim($_POST['alamat'])   : '';
$role     = 'pembeli'; // default role

// ── Validasi input ────────────────────────────────────────────────────────────
$errors = [];

if (empty($nama))     $errors[] = "Nama wajib diisi.";
if (empty($email))    $errors[] = "Email wajib diisi.";
if (empty($password)) $errors[] = "Password wajib diisi.";
if (empty($no_hp))    $errors[] = "Nomor HP wajib diisi.";
if (empty($alamat))   $errors[] = "Alamat wajib diisi.";

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Format email tidak valid.";
}

if (!empty($password) && strlen($password) < 6) {
    $errors[] = "Password minimal 6 karakter.";
}

if (!empty($errors)) {
    $_SESSION['reg_error'] = implode(' ', $errors);
    header("Location: register.html?error=validasi");
    exit();
}

// ── Cek apakah email sudah terdaftar ─────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT id_users FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    error_log("Prepare gagal: " . mysqli_error($conn));
    header("Location: register.html?error=server");
    exit();
}
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    mysqli_stmt_close($stmt);
    header("Location: register.html?error=email_exists");
    exit();
}
mysqli_stmt_close($stmt);

// ── Hash password & simpan user baru ─────────────────────────────────────────
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt2 = mysqli_prepare($conn,
    "INSERT INTO users (nama, email, password, no_hp, alamat, role, is_active)
     VALUES (?, ?, ?, ?, ?, ?, 1)"
);
if (!$stmt2) {
    error_log("Prepare insert gagal: " . mysqli_error($conn));
    header("Location: register.html?error=server");
    exit();
}
mysqli_stmt_bind_param($stmt2, "ssssss", $nama, $email, $hash, $no_hp, $alamat, $role);

if (!mysqli_stmt_execute($stmt2)) {
    error_log("Execute insert gagal: " . mysqli_stmt_error($stmt2));
    header("Location: register.html?error=server");
    exit();
}

mysqli_stmt_close($stmt2);
mysqli_close($conn);

// ── Setelah registrasi sukses: arahkan ke login dengan notifikasi sukses ──────
// Tidak langsung auto-login karena user perlu verifikasi manual dulu
header("Location: login.html?success=registered");
exit();
?>
