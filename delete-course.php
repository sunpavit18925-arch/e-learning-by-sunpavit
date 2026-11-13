<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

// 1. ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะ Admin เท่านั้น)
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("คุณไม่มีสิทธิ์ในการลบคอร์สเรียนนี้ (เฉพาะผู้ดูแลระบบ)");
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($course_id > 0) {
    try {
        $pdo->beginTransaction();

        // 3. ดึงชื่อไฟล์รูปภาพจาก DB ก่อนลบ Record
        $stmt_img = $pdo->prepare("SELECT thumbnail_url FROM courses WHERE course_id = ?");
        $stmt_img->execute([$course_id]);
        $course = $stmt_img->fetch(PDO::FETCH_ASSOC);

        if ($course && !empty($course['thumbnail_url'])) {
            $defaultFiles = ['pic_default.jpg', 'logo.png', 'logo.jpg', 'pom.jpg', 'pom2.jpg'];
            if (!in_array($course['thumbnail_url'], $defaultFiles)) {
                $filePath = 'uploads/' . $course['thumbnail_url'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        // 4. ลบข้อมูลในตารางที่เกี่ยวข้อง (เพื่อความปลอดภัยหากไม่มี cascade)
        $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$course_id]);
        $pdo->prepare("DELETE FROM user_lesson_progress WHERE lesson_id IN (SELECT lesson_id FROM lessons WHERE course_id = ?)")->execute([$course_id]);
        $pdo->prepare("DELETE FROM lessons WHERE course_id = ?")->execute([$course_id]);

        // 5. ลบคอร์สเรียน (Quizzes, Questions จะ cascade ตาม FK)
        $stmt_del = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt_del->execute([$course_id]);

        $pdo->commit();

        header("Location: courses_all.php?deleted=1");
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("ไม่สามารถลบข้อมูลได้: " . $e->getMessage());
    }
} else {
    header('Location: courses_all.php');
    exit();
}
?>