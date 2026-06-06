<?php
// mark_notif.php – Tandai notifikasi sebagai sudah dibaca
session_start();
require_once 'koneksi.php';
if (!isset($_SESSION['user_id'])) exit();

$id      = (int)($_POST['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];
if ($id > 0) {
    $st = $pdo->prepare("UPDATE notifikasi SET is_read = 1 WHERE id = ? AND user_id = ?");
    $st->execute([$id, $user_id]);
}
echo json_encode(['ok' => true]);
