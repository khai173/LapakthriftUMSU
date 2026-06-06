<?php
// get_notifikasi.php - Ambil notifikasi user
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Tidak terautentikasi'], 401);
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$action = $_GET['action'] ?? 'list';

if ($action === 'count') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id=:uid AND is_read=0");
    $stmt->execute([':uid' => $userId]);
    jsonResponse(['unread' => (int)$stmt->fetchColumn()]);
}

if ($action === 'list') {
    $stmt = $db->prepare("
        SELECT id, judul, pesan, tipe, referensi_id, is_read, created_at
        FROM notifikasi
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([':uid' => $userId]);
    jsonResponse(['notifikasi' => $stmt->fetchAll()]);
}

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare("UPDATE notifikasi SET is_read=1 WHERE user_id=:uid")
       ->execute([':uid' => $userId]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Action tidak valid'], 400);
