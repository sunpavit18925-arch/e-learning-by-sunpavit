<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'instructor'])) {
    die("คุณไม่มีสิทธิ์ในการจัดการแบบทดสอบ");
}

// 1. รับ course_id จาก GET หรือ POST
$course_id_url = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0);

if ($course_id_url <= 0) {
    header('Location: courses_all.php');
    exit();
}

// 2. จัดการการลบคำถาม (Delete Question Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_question') {
    $del_q_id = (int)$_POST['delete_question_id'];
    try {
        $stmt_del = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
        $stmt_del->execute([$del_q_id]);
        header("Location: edit-quiz.php?id=" . $course_id_url . "&deleted=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการลบคำถาม: " . $e->getMessage());
    }
}

// 3. จัดการบันทึกการแก้ไขแบบทดสอบ (UPDATE & INSERT LOGIC)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_id']) && (!isset($_POST['action']) || $_POST['action'] !== 'delete_question')) {
    $quiz_id = (int)$_POST['quiz_id'];
    $title = trim($_POST['title'] ?? '');
    $time_limit = max(1, (int)($_POST['time_limit'] ?? 30));
    $passing_score = max(1, min(100, (int)($_POST['passing_score'] ?? 60)));

    try {
        $pdo->beginTransaction();

        // 3.1 Update ข้อมูลพื้นฐาน Quiz
        $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, time_limit = ?, passing_score = ? WHERE quiz_id = ?");
        $stmt->execute([$title, $time_limit, $passing_score, $quiz_id]);

        // 3.2 จัดการคำถามที่มีอยู่เดิม (Update Existing Questions)
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $q_id => $q_data) {
                $q_id = (int)$q_id;
                $q_text = trim($q_data['text'] ?? '');
                $points = max(1, (int)($q_data['points'] ?? 1));

                $stmt_q = $pdo->prepare("UPDATE questions SET question_text = ?, points = ? WHERE question_id = ? AND quiz_id = ?");
                $stmt_q->execute([$q_text, $points, $q_id, $quiz_id]);

                // รีเซ็ตตัวเลือกของข้อนี้
                $pdo->prepare("UPDATE question_options SET is_correct = 0 WHERE question_id = ?")->execute([$q_id]);
                
                // กำหนดตัวเลือกที่ถูกต้อง
                if (isset($_POST['correct'][$q_id])) {
                    $correct_option_id = (int)$_POST['correct'][$q_id];
                    $pdo->prepare("UPDATE question_options SET is_correct = 1 WHERE option_id = ? AND question_id = ?")
                        ->execute([$correct_option_id, $q_id]);
                }
            }
        }

        // 3.3 จัดการข้อความตัวเลือกเดิม (Update Existing Options)
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            foreach ($_POST['options'] as $opt_id => $opt_text) {
                $opt_id = (int)$opt_id;
                $opt_text = trim($opt_text);
                $pdo->prepare("UPDATE question_options SET option_text = ? WHERE option_id = ?")->execute([$opt_text, $opt_id]);
            }
        }

        // 3.4 เพิ่มคำถามใหม่ (Insert New Questions)
        if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
            $stmt_new_q = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, points) VALUES (?, ?, ?)");
            $stmt_new_opt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

            foreach ($_POST['new_questions'] as $temp_idx => $q_data) {
                $new_text = trim($q_data['text'] ?? '');
                if (empty($new_text)) continue;

                $new_points = max(1, (int)($q_data['points'] ?? 1));
                $stmt_new_q->execute([$quiz_id, $new_text, $new_points]);
                $new_q_id = (int)$pdo->lastInsertId();

                if (isset($_POST['new_options'][$temp_idx]) && is_array($_POST['new_options'][$temp_idx])) {
                    foreach ($_POST['new_options'][$temp_idx] as $opt_idx => $opt_text) {
                        $opt_text_trimmed = trim($opt_text);
                        if ($opt_text_trimmed === '') continue;

                        $is_correct = (isset($_POST['new_correct'][$temp_idx]) && (int)$_POST['new_correct'][$temp_idx] === (int)$opt_idx) ? 1 : 0;
                        $stmt_new_opt->execute([$new_q_id, $opt_text_trimmed, $is_correct]);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: courses.php?id=" . $course_id_url . "&success=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage());
    }
}

// 4. ดึงข้อมูลแบบทดสอบและคำถามมาแสดงผล
try {
    $stmt_quiz = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ?");
    $stmt_quiz->execute([$course_id_url]);
    $quiz = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header("Location: add-quiz.php?id=" . $course_id_url);
        exit();
    }

    $quiz_id = (int)$quiz['quiz_id'];
    
    // ดึงชื่อคอร์ส
    $stmt_course = $pdo->prepare("SELECT title FROM courses WHERE course_id = ?");
    $stmt_course->execute([$course_id_url]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

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
        if (!empty($row['option_id'])) {
            $questions[$q_id]['options'][] = [
                'id' => $row['option_id'], 
                'text' => $row['option_text'], 
                'is_correct' => $row['is_correct']
            ];
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<?php include 'header.php'; ?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full flex-1">
    <!-- Breadcrumb & Header -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="courses.php?id=<?= $course_id_url ?>" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> กลับไปที่คอร์ส
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">จัดการแบบทดสอบ</span>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">แก้ไขและจัดการแบบทดสอบ</h1>
            <p class="text-slate-500 mt-1">คอร์ส: <span class="font-bold text-indigo-600"><?= htmlspecialchars($course['title'] ?? '') ?></span></p>
        </div>
        <a href="quiz_view.php?id=<?= $course_id_url ?>" target="_blank"
           class="inline-flex items-center bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-4 py-2.5 rounded-xl font-semibold text-sm transition">
            <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i> ทดลองทำข้อสอบ
        </a>
    </div>
    
    <form id="quizForm" method="POST">
        <input type="hidden" name="quiz_id" value="<?= $quiz_id; ?>">
        <input type="hidden" name="course_id" value="<?= $course_id_url; ?>">

        <!-- Quiz Settings Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8 mb-6">
            <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-sliders text-indigo-600"></i> ตั้งค่าแบบทดสอบ
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">ชื่อแบบทดสอบ <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="<?= htmlspecialchars($quiz['title']); ?>" 
                           class="w-full border border-slate-200 rounded-2xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white text-slate-800 transition" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">เวลาในการสอบ (นาที)</label>
                    <input type="number" name="time_limit" value="<?= (int)$quiz['time_limit']; ?>" min="1" max="180" 
                           class="w-full border border-slate-200 rounded-2xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white text-slate-800 transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">เกณฑ์คะแนนผ่าน (%)</label>
                    <input type="number" name="passing_score" value="<?= (int)$quiz['passing_score']; ?>" min="1" max="100" 
                           class="w-full border border-slate-200 rounded-2xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white text-slate-800 transition">
                </div>
            </div>
        </div>

        <!-- Questions List Container -->
        <div id="questionsContainer" class="space-y-6">
            <?php $idx = 1; foreach ($questions as $q_id => $q_data): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8 relative">
                    <div class="flex justify-between items-center mb-4">
                        <span class="font-bold text-indigo-600 text-base flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center"><?= $idx++; ?></span>
                            คำถามข้อที่ <?= ($idx - 1); ?>
                        </span>
                        <button type="button" onclick="confirmDeleteQuestion(<?= $q_id ?>)" 
                                class="text-slate-400 hover:text-red-500 text-sm font-semibold transition flex items-center gap-1">
                            <i class="fa-regular fa-trash-can"></i> ลบคำถามนี้
                        </button>
                    </div>

                    <div class="flex flex-col md:flex-row gap-4 mb-4">
                        <div class="flex-grow">
                            <input type="text" name="questions[<?= $q_id; ?>][text]" 
                                   value="<?= htmlspecialchars($q_data['text']); ?>" 
                                   class="w-full border border-slate-200 rounded-2xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white text-slate-800 font-medium" required>
                        </div>
                        <div class="w-full md:w-32 flex items-center justify-end">
                            <span class="text-xs text-slate-500 mr-2">คะแนน:</span>
                            <input type="number" name="questions[<?= $q_id; ?>][points]" 
                                   value="<?= (int)$q_data['points']; ?>" min="1" max="100" 
                                   class="w-20 border border-slate-200 rounded-xl p-2 text-center font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>

                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">ตัวเลือก (เลือกปุ่มวงกลมข้อที่ถูกต้อง):</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($q_data['options'] as $opt): ?>
                            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl border border-slate-200 hover:border-indigo-300 focus-within:border-indigo-500 focus-within:bg-white transition">
                                <input type="radio" name="correct[<?= $q_id; ?>]" value="<?= $opt['id']; ?>" 
                                       <?= $opt['is_correct'] == 1 ? 'checked' : ''; ?> 
                                       class="w-5 h-5 text-indigo-600 focus:ring-indigo-500 cursor-pointer" required>
                                <input type="text" name="options[<?= $opt['id']; ?>]" 
                                       value="<?= htmlspecialchars($opt['text']); ?>" 
                                       class="w-full bg-transparent outline-none text-sm text-slate-800" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Add Question Button -->
        <button type="button" id="addQuestionBtn" 
                class="mt-6 w-full py-5 border-2 border-dashed border-indigo-300 hover:border-indigo-500 rounded-2xl text-indigo-600 hover:bg-indigo-50/50 font-semibold transition flex items-center justify-center gap-2 shadow-sm">
            <i class="fa-solid fa-plus-circle text-lg"></i>
            <span>เพิ่มคำถามใหม่</span>
        </button>

        <!-- Submit Bar -->
        <div class="flex justify-between items-center gap-4 mt-10">
            <a href="courses.php?id=<?= $course_id_url ?>" 
               class="px-6 py-3 rounded-2xl text-slate-600 hover:bg-slate-200 font-semibold transition text-sm">
                ยกเลิก
            </a>
            <button type="submit" 
                    class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-bold shadow-lg shadow-indigo-600/30 transition flex items-center gap-2 text-sm">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>บันทึกการเปลี่ยนแปลงทั้งหมด</span>
            </button>
        </div>
    </form>

    <!-- Hidden Form for Deleting Question -->
    <form id="deleteQuestionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_question">
        <input type="hidden" name="course_id" value="<?= $course_id_url; ?>">
        <input type="hidden" name="delete_question_id" id="deleteQuestionId">
    </form>
</main>

<?php include 'footer.php'; ?>

<script>
let newQuestionCount = 0;
const container = document.getElementById('questionsContainer');
const addBtn = document.getElementById('addQuestionBtn');

// เพิ่มคำถามใหม่
addBtn.addEventListener('click', () => {
    newQuestionCount++;
    const currentIdx = document.querySelectorAll('#questionsContainer > div').length + 1;
    
    const html = `
        <div class="bg-indigo-50/70 rounded-3xl shadow-sm border-2 border-indigo-200 p-6 sm:p-8 relative">
            <div class="flex justify-between items-center mb-4">
                <span class="font-bold text-indigo-700 text-base flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-indigo-200 text-indigo-800 text-xs flex items-center justify-center">${currentIdx}</span>
                    ข้อที่ ${currentIdx} (เพิ่มใหม่)
                </span>
                <button type="button" onclick="this.closest('.bg-indigo-50\\/70').remove()" 
                        class="text-red-500 hover:text-red-700 text-sm font-semibold transition flex items-center gap-1">
                    <i class="fa-solid fa-xmark"></i> ยกเลิกข้อนี้
                </button>
            </div>
            <div class="flex flex-col md:flex-row gap-4 mb-4">
                <div class="flex-grow">
                    <input type="text" name="new_questions[${newQuestionCount}][text]" 
                           placeholder="พิมพ์โจทย์คำถามใหม่ที่นี่..." 
                           class="w-full border border-indigo-200 rounded-2xl p-3 focus:ring-2 focus:ring-indigo-500 outline-none bg-white text-slate-800 font-medium" required>
                </div>
                <div class="w-full md:w-32 flex items-center justify-end">
                    <span class="text-xs text-slate-500 mr-2">คะแนน:</span>
                    <input type="number" name="new_questions[${newQuestionCount}][points]" value="1" min="1" max="100" 
                           class="w-20 border border-indigo-200 rounded-xl p-2 text-center font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">ตัวเลือก (เลือกปุ่มวงกลมข้อที่ถูกต้อง):</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                ${[0, 1, 2, 3].map(i => `
                    <div class="flex items-center gap-3 p-3 bg-white rounded-2xl border border-indigo-100 hover:border-indigo-300 focus-within:border-indigo-500 transition">
                        <input type="radio" name="new_correct[${newQuestionCount}]" value="${i}" ${i === 0 ? 'checked' : ''} 
                               class="w-5 h-5 text-indigo-600 focus:ring-indigo-500 cursor-pointer" required>
                        <input type="text" name="new_options[${newQuestionCount}][${i}]" placeholder="ตัวเลือกที่ ${i+1}" 
                               class="w-full bg-transparent outline-none text-sm text-slate-800" required>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
});

// ฟังก์ชันยืนยันลบคำถามเดิม
function confirmDeleteQuestion(qId) {
    Swal.fire({
        title: 'ยืนยันการลบคำถาม?',
        text: "คำถามและตัวเลือกในข้อนี้จะถูกลบออกจากระบบทันที",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteQuestionId').value = qId;
            document.getElementById('deleteQuestionForm').submit();
        }
    });
}

// ยืนยันการบันทึกฟอร์ม
document.getElementById('quizForm').addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'ยืนยันการบันทึก?',
        text: "ระบบจะอัปเดตข้อมูลแบบทดสอบและคำถามทั้งหมดทันที",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'ยืนยัน, บันทึกเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

// ตรวจสอบ Success / Deleted notification
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('deleted')) {
    Swal.fire({
        icon: 'success',
        title: 'ลบสำเร็จ!',
        text: 'ลบคำถามเรียบร้อยแล้ว',
        timer: 1500,
        showConfirmButton: false
    });
    window.history.replaceState({}, document.title, window.location.pathname + '?id=<?= $course_id_url ?>');
}
</script>