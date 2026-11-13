<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'instructor'])) {
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการสร้างแบบทดสอบ']);
    exit;
}

// 2. รับและถอดรหัส JSON Payload
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง (Invalid JSON)']);
    exit;
}

$course_id = isset($data['course_id']) ? (int)$data['course_id'] : 0;
$title = trim($data['title'] ?? '');
$time_limit = isset($data['time_limit']) ? max(1, (int)$data['time_limit']) : 30;
$passing_score = isset($data['passing_score']) ? max(1, min(100, (int)$data['passing_score'])) : 60;
$questions = $data['questions'] ?? [];

if ($course_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสคอร์สเรียน']);
    exit;
}

if (empty($title)) {
    $title = "แบบทดสอบท้ายบทเรียน";
}

if (empty($questions) || !is_array($questions)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเพิ่มคำถามอย่างน้อย 1 ข้อ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. ตรวจสอบว่าคอร์สนี้มี Quiz อยู่แล้วหรือไม่
    $stmt_check = $pdo->prepare("SELECT quiz_id FROM quizzes WHERE course_id = ?");
    $stmt_check->execute([$course_id]);
    $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $quiz_id = (int)$existing['quiz_id'];
        $stmt_update_quiz = $pdo->prepare("UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ? WHERE quiz_id = ?");
        $stmt_update_quiz->execute([$title, $time_limit, $passing_score, $quiz_id]);
    } else {
        $stmt_new_quiz = $pdo->prepare("INSERT INTO quizzes (course_id, title, time_limit, passing_score, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_new_quiz->execute([$course_id, $title, $time_limit, $passing_score]);
        $quiz_id = (int)$pdo->lastInsertId();
    }

    // 4. บันทึกคำถามและตัวเลือก
    $stmt_insert_q = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, points) VALUES (?, ?, ?)");
    $stmt_insert_opt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

    foreach ($questions as $q) {
        $q_text = trim($q['text'] ?? '');
        $points = isset($q['points']) ? max(1, (int)$q['points']) : 1;
        $correct_idx = isset($q['correct']) ? (int)$q['correct'] : 0;
        $options = $q['options'] ?? [];

        if (empty($q_text)) continue;

        $stmt_insert_q->execute([$quiz_id, $q_text, $points]);
        $question_id = (int)$pdo->lastInsertId();

        foreach ($options as $opt_idx => $opt_text) {
            $opt_text_trimmed = trim($opt_text);
            if ($opt_text_trimmed === '') continue;

            $is_correct = ($opt_idx === $correct_idx) ? 1 : 0;
            $stmt_insert_opt->execute([$question_id, $opt_text_trimmed, $is_correct]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'บันทึกแบบทดสอบเรียบร้อยแล้ว',
        'quiz_id' => $quiz_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()
    ]);
}
exit;