<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'guest',
        'unread_count' => 0,
        'notifications' => []
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // ดึงรายการแจ้งเตือน 5 อันล่าสุดของผู้ใช้
    $sql_noti = "SELECT noti_id, title, message, link, is_read, created_at 
                 FROM notifications 
                 WHERE user_id = :uid 
                 ORDER BY created_at DESC LIMIT 5";
    $stmt_noti = $pdo->prepare($sql_noti);
    $stmt_noti->execute([':uid' => $user_id]);
    $notifications = $stmt_noti->fetchAll(PDO::FETCH_ASSOC);

    // นับจำนวนที่ยังไม่ได้อ่าน
    $sql_unread = "SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0";
    $stmt_unread = $pdo->prepare($sql_unread);
    $stmt_unread->execute([':uid' => $user_id]);
    $unread_count = (int)$stmt_unread->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'unread_count' => 0,
        'notifications' => [],
        'message' => $e->getMessage()
    ]);
}
exit;