<?php
session_start();
// ตรวจสอบ: ถ้าไฟล์ connection.php อยู่ในโฟลเดอร์เดียวกัน ให้ลบ connection/ ออก
require_once 'connection/connection.php'; 
header('Content-Type: application/json; charset=utf-8');

// 1. เช็ค Session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

// 2. เช็คค่าที่ส่งมา
$course_id = $_POST['course_id'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($course_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสคอร์สเรียน']);
    exit;
}

try {
    // 3. ตรวจสอบการสมัครซ้ำ
    $check = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $check->execute([$user_id, $course_id]);
    
    if ($check->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'คุณสมัครคอร์สนี้ไปแล้ว']);
        exit;
    }

    // 4. บันทึกข้อมูล
    $sql = "INSERT INTO enrollments (user_id, course_id, enrollment_date) VALUES (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $course_id]);

    echo json_encode(['status' => 'success', 'message' => 'สมัครเรียนสำเร็จแล้ว!']);

} catch (PDOException $e) {
    // ใช้ PDOException เพื่อดักจับ Error จากฐานข้อมูลโดยตรง
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>