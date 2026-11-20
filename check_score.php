<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'connection/connection.php';
require_once __DIR__ . '/includes/quiz-data.php';
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid Request Method');
    }

    $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
    $time_spent = isset($_POST['time_spent']) ? (int)$_POST['time_spent'] : 0; 

    if ($quiz_id <= 0) {
        throw new Exception('ไม่พบรหัสแบบทดสอบ');
    }

    // 1. ดึงรายการคำถามทั้งหมดของ Quiz นี้
    $stmt = $pdo->prepare("SELECT question_id, points FROM questions WHERE quiz_id = ? ORDER BY question_id ASC");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$questions) {
        throw new Exception('ไม่พบรายการคำถามในแบบทดสอบนี้');
    }

    $total_questions = count($questions);
    $pdo->beginTransaction();
    $options_by_question = elearning_quiz_options($pdo, $quiz_id, true);
    $graded = elearning_grade_quiz($questions, $options_by_question, $_POST);
    $correct_count = $graded['correct_count'];
    $score_percent = $graded['score_percent'];
    $answers_to_save = $graded['answers'];

    $stmt_qinfo = $pdo->prepare("SELECT course_id, passing_score FROM quizzes WHERE quiz_id = ?");
    $stmt_qinfo->execute([$quiz_id]);
    $quiz_info = $stmt_qinfo->fetch(PDO::FETCH_ASSOC);

    $passing_score = isset($quiz_info['passing_score']) ? (int)$quiz_info['passing_score'] : 60;
    $course_id = isset($quiz_info['course_id']) ? (int)$quiz_info['course_id'] : 0;
    $is_passed = ($score_percent >= $passing_score) ? 1 : 0;

    // 4. บันทึกผลการทำแบบทดสอบ (Quiz Attempt)
    $stmt_attempt = $pdo->prepare("
        INSERT INTO quiz_attempts (user_id, quiz_id, score_obtained, is_passed, time_spent_seconds, attempt_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt_attempt->execute([
        $user_id, 
        $quiz_id, 
        $score_percent, 
        $is_passed, 
        $time_spent
    ]);

    $attempt_id = (int)$pdo->lastInsertId();

    // 5. บันทึกคำตอบเป็นกลุ่มใน transaction เดียวกับผลสอบ
    elearning_save_answers($pdo, $attempt_id, $answers_to_save);

    // 6. ถ้าสอบผ่าน และมีการลงทะเบียน ให้อัปเดต progress_percentage เป็น 100%
    if ($is_passed && $course_id > 0) {
        $stmt_prog = $pdo->prepare("UPDATE enrollments SET progress_percentage = 100 WHERE user_id = ? AND course_id = ?");
        $stmt_prog->execute([$user_id, $course_id]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'correct_count' => (int)$correct_count,
        'total_questions' => (int)$total_questions,
        'score_percent' => $score_percent,
        'passing_score' => $passing_score,
        'is_passed' => $is_passed,
        'course_id' => $course_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
