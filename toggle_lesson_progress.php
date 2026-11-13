<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$lesson_id = (int)($_POST['lesson_id'] ?? 0);

if ($lesson_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสบทเรียนไม่ถูกต้อง']);
    exit;
}

try {
    // 2. ดึงข้อมูลบทเรียน และคอร์ส
    $stmt_lesson = $pdo->prepare("SELECT lesson_id, course_id, title FROM lessons WHERE lesson_id = ?");
    $stmt_lesson->execute([$lesson_id]);
    $lesson = $stmt_lesson->fetch(PDO::FETCH_ASSOC);

    if (!$lesson) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบบทเรียนที่ระบุ']);
        exit;
    }

    $course_id = (int)$lesson['course_id'];

    // 3. ตรวจสอบหรือสร้างการลงทะเบียนเรียนอัตโนมัติหากยังไม่ได้ลงทะเบียน
    $stmt_enroll = $pdo->prepare("SELECT enrollment_id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt_enroll->execute([$user_id, $course_id]);
    $enrollment = $stmt_enroll->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment) {
        $stmt_new_enroll = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, enrollment_date, progress_percentage) VALUES (?, ?, NOW(), 0)");
        $stmt_new_enroll->execute([$user_id, $course_id]);
    }

    // 4. ตรวจสอบสถานะการเรียนเดิมใน user_lesson_progress
    $stmt_prog = $pdo->prepare("SELECT progress_id, is_completed FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?");
    $stmt_prog->execute([$user_id, $lesson_id]);
    $progress = $stmt_prog->fetch(PDO::FETCH_ASSOC);

    $new_status = 1;
    if ($progress) {
        $new_status = $progress['is_completed'] ? 0 : 1;
        $stmt_update = $pdo->prepare("UPDATE user_lesson_progress SET is_completed = ?, completed_at = NOW() WHERE progress_id = ?");
        $stmt_update->execute([$new_status, $progress['progress_id']]);
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO user_lesson_progress (user_id, lesson_id, is_completed, completed_at) VALUES (?, ?, 1, NOW())");
        $stmt_insert->execute([$user_id, $lesson_id]);
        $new_status = 1;
    }

    // 5. คำนวณความคืบหน้าของคอร์ส (Progress Percentage)
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
    $stmt_total->execute([$course_id]);
    $total_lessons = (int)$stmt_total->fetchColumn();

    $stmt_completed = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_lesson_progress ulp
        JOIN lessons l ON ulp.lesson_id = l.lesson_id
        WHERE ulp.user_id = ? AND l.course_id = ? AND ulp.is_completed = 1
    ");
    $stmt_completed->execute([$user_id, $course_id]);
    $completed_lessons = (int)$stmt_completed->fetchColumn();

    $percentage = $total_lessons > 0 ? (int)round(($completed_lessons / $total_lessons) * 100) : 0;

    // อัปเดตความคืบหน้าใน enrollments
    $stmt_update_enroll = $pdo->prepare("UPDATE enrollments SET progress_percentage = ? WHERE user_id = ? AND course_id = ?");
    $stmt_update_enroll->execute([$percentage, $user_id, $course_id]);

    echo json_encode([
        'status' => 'success',
        'is_completed' => (bool)$new_status,
        'progress_percentage' => $percentage,
        'completed_count' => $completed_lessons,
        'total_count' => $total_lessons,
        'message' => $new_status ? 'บันทึกว่าเรียนบทนี้เรียบร้อยแล้ว' : 'ยกเลิกสถานะการเรียนบทนี้แล้ว'
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

