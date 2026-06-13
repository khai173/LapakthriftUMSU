<?php
// make_admin.php
// ⚠️  HAPUS FILE INI SETELAH SELESAI DIGUNAKAN!
// Jalankan sekali di browser: http://localhost/make_admin.php

require_once 'koneksi.php';

// ══════════════════════════════════════════
//  UBAH DATA INI SESUAI KEBUTUHAN KAMU
// ══════════════════════════════════════════
$nama     = 'Admin';
$email    = 'admin@lapakthrift.com';   // ← email login admin
$password = 'admin123';               // ← password plain text yang mau kamu pakai
// ══════════════════════════════════════════

$hashed = password_hash($password, PASSWORD_BCRYPT);

try {
    // Cek apakah email sudah ada
    $cek = $pdo->prepare("SELECT id_users FROM users WHERE email = ? LIMIT 1");
    $cek->execute([$email]);
    $existing = $cek->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update password & pastikan role = admin & is_active = 1
        $st = $pdo->prepare("
            UPDATE users
            SET password = ?, role = 'admin', is_active = 1, nama = ?
            WHERE email = ?
        ");
        $st->execute([$hashed, $nama, $email]);
        echo "<p style='color:green;font-family:sans-serif;'>
                ✅ Akun <strong>{$email}</strong> berhasil di-update sebagai admin.<br>
                Password baru sudah di-set.
              </p>";
    } else {
        // Buat akun admin baru
        $st = $pdo->prepare("
            INSERT INTO users (nama, email, password, role, is_active, created_at)
            VALUES (?, ?, ?, 'admin', 1, NOW())
        ");
        $st->execute([$nama, $email, $hashed]);
        echo "<p style='color:green;font-family:sans-serif;'>
                ✅ Akun admin <strong>{$email}</strong> berhasil dibuat.
              </p>";
    }

    echo "<p style='color:red;font-family:sans-serif;font-weight:bold;'>
            ⚠️ Segera hapus file make_admin.php dari server!
          </p>";
    echo "<p style='font-family:sans-serif;'>
            Sekarang kamu bisa login di 
            <a href='adminlogin.html'>adminlogin.html</a> 
            dengan email <strong>{$email}</strong> dan password yang kamu set.
          </p>";

} catch (PDOException $e) {
    echo "<p style='color:red;font-family:sans-serif;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
