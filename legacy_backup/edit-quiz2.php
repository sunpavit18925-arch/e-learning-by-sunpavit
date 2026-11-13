<?php
require_once 'connection/connection.php';

// --- PART 1: UPDATE LOGIC (เช็กด้วย REQUEST_METHOD เพื่อความชัวร์) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quiz_id'])) {
    $quiz_id = $_POST['quiz_id'];
    $course_id = $_POST['course_id']; 
    $title = $_POST['title'];
    $time_limit = $_POST['time_limit'];
    $passing_score = $_POST['passing_score'];

    try {
        $pdo->beginTransaction();

        if (empty($quiz_id)) {
            throw new Exception("ไม่พบ Quiz ID");
        }

        // 1. Update ข้อมูลพื้นฐาน Quiz
        $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ? WHERE quiz_id = ?");
        $stmt->execute([$title, $time_limit, $passing_score, $quiz_id]);

        // 2. Update คำถามและตัวเลือกที่ถูกต้อง
        if (isset($_POST['questions'])) {
            foreach ($_POST['questions'] as $q_id => $q_data) {
                // อัปเดตเนื้อหาคำถามและคะแนน
                $stmt_q = $pdo->prepare("UPDATE questions SET question_text = ?, points = ? WHERE question_id = ?");
                $stmt_q->execute([$q_data['text'], $q_data['points'], $q_id]);

                // รีเซ็ตตัวเลือกทั้งหมดของข้อนี้ให้เป็น "ผิด" ก่อน (0)
                $stmt_reset = $pdo->prepare("UPDATE question_options SET is_correct = 0 WHERE question_id = ?");
                $stmt_reset->execute([$q_id]);

                // ตั้งค่าตัวเลือกที่เลือกผ่าน Radio ให้เป็น "ถูก" (1)
                if (isset($_POST['correct'][$q_id])) {
                    $correct_option_id = $_POST['correct'][$q_id];
                    $stmt_correct = $pdo->prepare("UPDATE question_options SET is_correct = 1 WHERE option_id = ?");
                    $stmt_correct->execute([$correct_option_id]);
                }
            }
        }

        // 3. Update ข้อความของทุกตัวเลือก (Options)
        if (isset($_POST['options'])) {
            foreach ($_POST['options'] as $opt_id => $opt_text) {
                $stmt_opt = $pdo->prepare("UPDATE question_options SET option_text = ? WHERE option_id = ?");
                $stmt_opt->execute([$opt_text, $opt_id]);
            }
        }

        $pdo->commit();
        // ส่งกลับไปหน้าเดิมพร้อม Parameter success
        header("Location: edit-quiz.php?id=" . $course_id . "&success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        // ถ้าผิดพลาด ให้แสดง Error เพื่อการ Debug
        die("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage());
    }
}

// --- PART 2: FETCH LOGIC (เหมือนเดิม) ---
if (!isset($_GET['id'])) { die("Error: No ID provided."); }
$course_id_url = (int)$_GET['id'];

try {
    $stmt_quiz = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
    $stmt_quiz->execute([$course_id_url]);
    $quiz = $stmt_quiz->fetch();

    if (!$quiz) { die("<h3>ไม่พบข้อมูลแบบทดสอบสำหรับ Course ID: $course_id_url</h3>"); }

    $quiz_id = $quiz['quiz_id'];
    $sql_questions = "SELECT q.question_id, q.question_text, q.points, 
                             o.option_id, o.option_text, o.is_correct 
                      FROM questions q 
                      LEFT JOIN question_options o ON q.question_id = o.question_id 
                      WHERE q.quiz_id = ? 
                      ORDER BY q.question_id ASC, o.option_id ASC";
    
    $stmt_questions = $pdo->prepare($sql_questions);
    $stmt_questions->execute([$quiz_id]);
    $rows = $stmt_questions->fetchAll(PDO::FETCH_ASSOC);

    $questions = [];
    foreach ($rows as $row) {
        $q_id = $row['question_id'];
        if (!isset($questions[$q_id])) {
            $questions[$q_id] = [
                'text' => $row['question_text'],
                'points' => $row['points'],
                'options' => []
            ];
        }
        if ($row['option_id']) {
            $questions[$q_id]['options'][] = [
                'id' => $row['option_id'],
                'text' => $row['option_text'],
                'is_correct' => $row['is_correct']
            ];
        }
    }
} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขแบบทดสอบ - <?= htmlspecialchars($quiz['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen pb-12">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800">
                แก้ไขแบบทดสอบ: <span class="text-blue-600"><?= htmlspecialchars($quiz['title']); ?></span>
            </h2>
        </div>
        
        <form id="quizForm" method="POST" class="space-y-6">
            <input type="hidden" name="quiz_id" value="<?= $quiz_id; ?>">
            <input type="hidden" name="course_id" value="<?= $course_id_url; ?>">

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-600 mb-1">ชื่อแบบทดสอบ</label>
                        <input type="text" name="title" value="<?= htmlspecialchars($quiz['title']); ?>" class="w-full border rounded-lg p-2" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">เวลา (นาที)</label>
                        <input type="number" name="time_limit" value="<?= $quiz['time_limit']; ?>" class="w-full border rounded-lg p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1">เกณฑ์ผ่าน (%)</label>
                        <input type="number" name="passing_score" value="<?= $quiz['passing_score']; ?>" class="w-full border rounded-lg p-2">
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <?php $idx = 1; foreach ($questions as $q_id => $q_data): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex flex-col md:flex-row gap-4 mb-4">
                            <div class="flex-grow">
                                <label class="block text-sm font-semibold text-gray-500 mb-1">ข้อที่ <?= $idx++; ?></label>
                                <input type="text" name="questions[<?= $q_id; ?>][text]" value="<?= htmlspecialchars($q_data['text']); ?>" class="w-full border rounded-lg p-2">
                            </div>
                            <div class="w-full md:w-32">
                                <label class="block text-sm font-semibold text-gray-500 mb-1 text-center">คะแนน</label>
                                <input type="number" name="questions[<?= $q_id; ?>][points]" value="<?= $q_data['points']; ?>" class="w-full border rounded-lg p-2 text-center">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                            <?php foreach ($q_data['options'] as $opt): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <input type="radio" name="correct[<?= $q_id; ?>]" value="<?= $opt['id']; ?>" <?= $opt['is_correct'] == 1 ? 'checked' : ''; ?>>
                                    <input type="text" name="options[<?= $opt['id']; ?>]" value="<?= htmlspecialchars($opt['text']); ?>" class="w-full bg-transparent border-b border-gray-300 focus:outline-none py-1">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex justify-end gap-4 mt-10">
                <a href="courses.php?id=<?= $course_id_url ?>" class="px-6 py-2.5 rounded-lg text-gray-600 hover:bg-gray-200">ยกเลิก</a>
                <button type="submit" class="px-8 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>

    <script>
        const form = document.getElementById('quizForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                text: "ข้อมูลเดิมจะถูกอัปเดตทันที",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, บันทึกเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ใช้ submit() โดยตรงเพื่อให้แน่ใจว่า Data ทั้งหมดถูกส่งไป
                    form.submit();
                }
            });
        });

        // จัดการ Success Alert
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            Swal.fire({
                title: 'สำเร็จ!',
                text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                const cid = urlParams.get('id');
                window.location.href = 'courses.php?id=' + cid;
            });
        }
    </script>
</body>
</html>