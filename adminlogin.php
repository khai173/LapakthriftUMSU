<?php
// adminlogin.php – Proses login admin
// Letakkan file adminlogin.html terpisah untuk form-nya,
// atau gunakan file ini sekaligus sebagai form + proses.
session_start();
require_once 'koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND is_active = 1 LIMIT 1");
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session admin
            $_SESSION['user_id']   = $user['id_users'];
            $_SESSION['username']  = $user['nama'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = 'admin';
            $_SESSION['is_admin']  = true;

            header("Location: admin.php?tab=dashboard");
            exit();
        } else {
            $error = 'Email atau password salah, atau akun tidak memiliki akses admin.';
        }
    } else {
        $error = 'Email dan password wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Login – LapakThriftUMSU</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.box{background:#fff;border-radius:20px;padding:40px 36px;width:100%;max-width:420px;box-shadow:0 12px 40px rgba(15,23,42,.1);}
.logo{text-align:center;margin-bottom:28px;}
.logo .icon{width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,#4f8cff,#1e3a8a);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;margin:0 auto 12px;}
.logo h1{font-size:1.25rem;font-weight:800;color:#0f172a;}
.logo p{font-size:.85rem;color:#64748b;margin-top:4px;}
label{display:block;font-weight:700;font-size:.85rem;margin-bottom:7px;color:#374151;}
input{width:100%;padding:11px 13px;border:1.5px solid #dbe4f0;border-radius:10px;font-family:inherit;font-size:.9rem;outline:none;transition:.2s;margin-bottom:14px;}
input:focus{border-color:#4f8cff;box-shadow:0 0 0 3px rgba(79,140,255,.12);}
.btn{width:100%;padding:13px;background:linear-gradient(135deg,#4f8cff,#1e3a8a);color:#fff;border:none;border-radius:12px;font-weight:800;font-size:.95rem;cursor:pointer;font-family:inherit;margin-top:4px;}
.btn:hover{filter:brightness(.94);}
.error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 14px;border-radius:10px;font-size:.85rem;font-weight:600;margin-bottom:16px;}
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <div class="icon">🛡️</div>
    <h1>Admin LapakThrift</h1>
    <p>Masuk ke dashboard admin</p>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <label>Email Admin</label>
    <input type="email" name="email" placeholder="admin@umsu.ac.id" required autocomplete="username"/>
    <label>Password</label>
    <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password"/>
    <button type="submit" class="btn">Masuk ke Dashboard</button>
  </form>
</div>
</body>
</html>
