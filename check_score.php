<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'connection/connection.php';
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
    $correct_count = 0;
    $total_possible_points = 0;
    $earned_points = 0;
    $answers_to_save = [];

    $pdo->beginTransaction();

    // 2. ตรวจคำตอบแต่ละข้อ
    foreach ($questions as $q) {
        $q_id = (int)$q['question_id'];
        $q_points = max(1, (int)$q['points']);
        $total_possible_points += $q_points;

        $post_key = 'q_' . $q_id;
        $selected_option_id = isset($_POST[$post_key]) ? (int)$_POST[$post_key] : null;
        $is_correct = 0;

        if ($selected_option_id !== null && $selected_option_id > 0) {
            $stmt_check = $pdo->prepare("SELECT is_correct FROM question_options WHERE option_id = ? AND question_id = ?");
            $stmt_check->execute([$selected_option_id, $q_id]);
            $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($result && (int)$result['is_correct'] === 1) {
                $correct_count++;
                $earned_points += $q_points;
                $is_correct = 1;
            }
        }

        $answers_to_save[] = [
            'question_id' => $q_id,
            'option_id' => $selected_option_id,
            'is_correct' => $is_correct
        ];
    }

    // 3. คำนวณเปอร์เซ็นต์คะแนน
    $score_percent = ($total_possible_points > 0) 
        ? round(($earned_points / $total_possible_points) * 100, 2) 
        : round(($correct_count / $total_questions) * 100, 2);

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

    // 5. บันทึกคำตอบรายข้อ
    $stmt_ans = $pdo->prepare("
        INSERT INTO student_answers (attempt_id, question_id, option_id, is_correct) 
        VALUES (?, ?, ?, ?)
    ");
    foreach ($answers_to_save as $ans) {
        $stmt_ans->execute([
            $attempt_id,
            $ans['question_id'],
            $ans['option_id'],
            $ans['is_correct']
        ]);
    }

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