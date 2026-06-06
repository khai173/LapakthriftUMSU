<?php
// login.php — Proses autentikasi user
session_start();
include 'koneksi.php';

// Tolak akses selain POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.html");
    exit();
}

// Ambil & sanitasi input
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validasi tidak kosong
if (empty($email) || empty($password)) {
    header("Location: login.html?error=empty");
    exit();
}

// Cari user berdasarkan email (kolom PK = id_users)
$stmt = mysqli_prepare($conn,
    "SELECT id_users, nama, email, password, role, is_active
     FROM users WHERE email = ? LIMIT 1"
);
if (!$stmt) {
    error_log("Prepare gagal: " . mysqli_error($conn));
    header("Location: login.html?error=server");
    exit();
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Email tidak ditemukan
if (mysqli_num_rows($result) === 0) {
    header("Location: login.html?error=email");
    exit();
}

$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Cek apakah akun aktif
if ((int)$user['is_active'] === 0) {
    header("Location: login.html?error=inactive");
    exit();
}

// Verifikasi password dengan hash
if (!password_verify($password, $user['password'])) {
    header("Location: login.html?error=password");
    exit();
}

// Regenerasi session ID untuk cegah session fixation
session_regenerate_id(true);

// Set session
$_SESSION['user_id']  = $user['id_users'];
$_SESSION['username'] = $user['nama'];
$_SESSION['email']    = $user['email'];
$_SESSION['role']     = $user['role'];

mysqli_close($conn);

// Redirect berdasarkan role
if ($user['role'] === 'admin') {
    header("Location: admin.html");
} else {
    header("Location: userafterlogin.php");
}
exit();
?>
