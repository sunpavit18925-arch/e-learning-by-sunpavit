<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz = null;
$questions = [];
$error_message = null;

try {
    if ($course_id <= 0) {
        $error_message = "ไม่พบรหัสคอร์สเรียนที่ระบุ";
    } else {
        // 1. ดึงข้อมูลคอร์ส
        $stmt_c = $pdo->prepare("SELECT title FROM courses WHERE course_id = ?");
        $stmt_c->execute([$course_id]);
        $course = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            $error_message = "ไม่พบข้อมูลคอร์สเรียนนี้ในระบบ";
        } else {
            // 2. ดึงข้อมูล Quiz
            $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
            $stmt->execute([$course_id]);
            $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quiz) {
                $error_message = "ยังไม่มีแบบทดสอบสำหรับคอร์สเรียนนี้";
            } else {
                // 3. ดึงคำถาม
                $stmt_q = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY question_id ASC");
                $stmt_q->execute([$quiz['quiz_id']]);
                $questions = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

                if (empty($questions)) {
                    $error_message = "แบบทดสอบนี้ยังไม่มีคำถามในระบบ";
                } else {
                    // 4. ดึงตัวเลือกของแต่ละคำถาม
                    foreach ($questions as $key => $q) {
                        $stmt_opt = $pdo->prepare("SELECT option_id, option_text FROM question_options WHERE question_id = ? ORDER BY option_id ASC");
                        $stmt_opt->execute([$q['question_id']]);
                        $questions[$key]['options'] = $stmt_opt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการโหลดข้อมูล: " . $e->getMessage();
}
?>

<?php include 'header.php'; ?>

<main class="flex-grow w-full py-8 sm:py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <!-- Breadcrumb -->
        <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
            <a href="courses.php?id=<?= $course_id ?>" class="hover:text-indigo-600 transition flex items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> กลับไปที่คอร์ส
            </a>
            <span>/</span>
            <span class="text-slate-700 font-medium truncate max-w-xs"><?= htmlspecialchars($quiz['title'] ?? 'แบบทดสอบ') ?></span>
        </div>

        <?php if ($error_message): ?>
            <!-- Error / Empty State Card -->
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-xl mx-auto">
                <div class="text-5xl mb-4">📋</div>
                <h2 class="text-xl font-bold text-slate-800 mb-2"><?= htmlspecialchars($error_message) ?></h2>
                <p class="text-slate-500 text-sm mb-6">คุณสามารถกลับไปเรียนบทเรียนต่อ หรือลองเข้ามาทำแบบทดสอบอีกครั้งในภายหลัง</p>
                <a href="courses.php?id=<?= $course_id ?>" 
                   class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-2xl transition shadow-md">
                    <i class="fa-solid fa-arrow-left mr-2"></i> กลับหน้าหลักของคอร์ส
                </a>
            </div>
        <?php else: ?>
            <!-- Quiz Active Card -->
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-200">
                <!-- Quiz Header Bar -->
                <div class="bg-slate-900 p-6 sm:p-8 text-white flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div class="flex-1 pr-4">
                        <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider mb-1 block">
                            แบบทดสอบวัดผลการเรียนรู้
                        </span>
                        <h1 class="text-xl sm:text-2xl font-bold leading-snug">
                            <?= htmlspecialchars($quiz['title']) ?>
                        </h1>
                        <p class="text-xs text-slate-400 mt-1">
                            จำนวน <?= count($questions) ?> ข้อ | เกณฑ์ผ่าน <?= (int)$quiz['passing_score'] ?>%
                        </p>
                    </div>

                    <!-- Timer Badge -->
                    <div class="flex items-center gap-3 bg-white/10 px-5 py-3 rounded-2xl backdrop-blur-md border border-white/20 shrink-0">
                        <i class="fa-regular fa-clock text-indigo-300 text-xl"></i>
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase font-semibold">เวลาที่เหลือ</div>
                            <span id="timer" class="text-2xl font-mono font-bold text-white tracking-wider">--:--</span>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full bg-slate-100 h-2">
                    <div id="quizProgress" class="bg-indigo-600 h-full transition-all duration-300" style="width: 0%"></div>
                </div>

                <!-- Quiz Questions Form -->
                <form id="quizForm" class="p-6 sm:p-10 space-y-8">
                    <input type="hidden" name="quiz_id" value="<?= (int)$quiz['quiz_id'] ?>">

                    <?php foreach ($questions as $idx => $q): ?>
                    <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 transition hover:border-indigo-200">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <h3 class="text-base sm:text-lg font-bold text-slate-800 leading-relaxed">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-indigo-600 text-white text-xs font-extrabold mr-2 shrink-0">
                                    <?= $idx + 1 ?>
                                </span>
                                <?= htmlspecialchars($q['question_text']) ?>
                            </h3>
                            <span class="text-xs font-semibold bg-white border border-slate-200 px-2.5 py-1 rounded-lg text-slate-500 shrink-0">
                                <?= (int)$q['points'] ?> คะแนน
                            </span>
                        </div>

                        <!-- Options Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                            <?php foreach ($q['options'] as $opt): ?>
                            <label class="opt-card relative flex items-center p-4 rounded-2xl border-2 border-slate-200 cursor-pointer bg-white transition hover:border-indigo-300 hover:bg-indigo-50/20 active:scale-[0.99]">
                                <input type="radio" name="q_<?= $q['question_id'] ?>" value="<?= $opt['option_id'] ?>"
                                       onchange="updateProgress()" class="hidden" required>
                                
                                <div class="check-circle w-5 h-5 rounded-full border-2 border-slate-300 mr-3 flex items-center justify-center shrink-0 transition">
                                    <div class="w-2.5 h-2.5 bg-indigo-600 rounded-full scale-0 transition-transform"></div>
                                </div>
                                
                                <span class="text-slate-700 font-medium text-sm leading-relaxed">
                                    <?= htmlspecialchars($opt['option_text']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-slate-200">
                        <button type="submit" id="submitBtn"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-lg font-bold py-4 rounded-2xl shadow-xl shadow-indigo-600/30 transition active:scale-[0.98] flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>ส่งคำตอบเพื่อตรวจคะแนน</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>

<style>
.opt-card:has(input:checked) {
    border-color: #4f46e5 !important;
    background-color: #eef2ff !important;
    box-shadow: 0 0 0 1px #4f46e5;
}
.opt-card:has(input:checked) .check-circle {
    border-color: #4f46e5 !important;
    background-color: white;
}
.opt-card:has(input:checked) .check-circle div {
    transform: scale(1) !important;
}
</style>

<?php if (!$error_message && $quiz): ?>
<script>
const startTime = Date.now();
const totalQuestions = <?= count($questions) ?>;

// อัปเดต Progress Bar
function updateProgress() {
    const answered = document.querySelectorAll('input[type="radio"]:checked').length;
    const percent = Math.min(100, Math.round((answered / totalQuestions) * 100));
    document.getElementById('quizProgress').style.width = percent + '%';
}

// ระบบส่งคำตอบ (AJAX)
document.getElementById('quizForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const passingScore = <?= (int)($quiz['passing_score'] ?? 60) ?>;

    const result = await Swal.fire({
        title: 'ยืนยันการส่งคำตอบ?',
        text: "คุณจะไม่สามารถแก้ไขคำตอบได้หลังจากส่งแล้ว",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยัน, ส่งคำตอบ!',
        cancelButtonText: 'กลับไปตรวจทาน'
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'กำลังตรวจคะแนน...',
            text: 'ระบบกำลังประมวลผลคำตอบของคุณ',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const endTime = Date.now();
        const timeSpentSeconds = Math.max(1, Math.floor((endTime - startTime) / 1000));

        const formData = new FormData(this);
        formData.append('time_spent', timeSpentSeconds);

        try {
            const response = await fetch('check_score.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('ไม่สามารถติดต่อเซิร์ฟเวอร์ได้');

            const data = await response.json();

            if (data.status === 'success') {
                const isPassed = data.is_passed === 1;
                const minutes = Math.floor(timeSpentSeconds / 60);
                const seconds = timeSpentSeconds % 60;

                Swal.fire({
                    title: isPassed ? '🎉 ยินดีด้วย! คุณสอบผ่าน' : 'พยายามใหม่อีกครั้ง',
                    html: `
                        <div class="my-4 text-center">
                            <div class="text-4xl font-extrabold ${isPassed ? 'text-emerald-600' : 'text-rose-600'} mb-2">
                                ${data.score_percent}%
                            </div>
                            <p class="text-slate-600 text-sm">
                                ตอบถูก: <b>${data.correct_count} / ${data.total_questions}</b> ข้อ<br>
                                เกณฑ์ที่ผ่าน: <b>${passingScore}%</b><br>
                                เวลาที่ใช้: <b>${minutes} นาที ${seconds} วินาที</b>
                            </p>
                        </div>
                    `,
                    icon: isPassed ? 'success' : 'error',
                    showCancelButton: true,
                    confirmButtonText: isPassed ? '🎓 รับใบประกาศนียบัตร' : 'ทำใหม่อีกครั้ง',
                    cancelButtonText: 'กลับหน้าหลักคอร์ส',
                    confirmButtonColor: isPassed ? '#059669' : '#4f46e5',
                    cancelButtonColor: '#64748b'
                }).then((res) => {
                    if (res.isConfirmed) {
                        if (isPassed) {
                            window.open('generate_pdf.php?id=<?= $course_id ?>', '_blank');
                        }
                        window.location.href = 'courses.php?id=<?= $course_id ?>';
                    } else {
                        window.location.href = 'courses.php?id=<?= $course_id ?>';
                    }
                });
            } else {
                Swal.fire('ข้อผิดพลาด', data.message || 'เกิดข้อผิดพลาดในการตรวจคะแนน', 'error');
            }
        } catch (error) {
            Swal.fire('เกิดข้อผิดพลาด', 'เกิดปัญหาในการส่งข้อมูล: ' + error.message, 'error');
        }
    }
});

// ระบบตัวนับเวลา (Timer)
let timeLimitMinutes = <?= max(1, (int)($quiz['time_limit'] ?? 30)) ?>;
let timeLeft = timeLimitMinutes * 60;
const timerDisplay = document.getElementById('timer');

function updateTimerDisplay() {
    const mins = Math.floor(timeLeft / 60).toString().padStart(2, '0');
    const secs = (timeLeft % 60).toString().padStart(2, '0');
    if (timerDisplay) {
        timerDisplay.innerText = `${mins}:${secs}`;
        if (timeLeft <= 300) {
            timerDisplay.classList.add('text-rose-400');
        }
    }
}

updateTimerDisplay();

const countdown = setInterval(() => {
    timeLeft--;
    if (timeLeft <= 0) {
        clearInterval(countdown);
        updateTimerDisplay();
        Swal.fire({
            title: 'หมดเวลาสอบ!',
            text: 'ระบบจะส่งคำตอบที่ทำไว้โดยอัตโนมัติ',
            icon: 'warning',
            timer: 2500,
            showConfirmButton: false
        }).then(() => {
            document.getElementById('quizForm').requestSubmit();
        });
        return;
    }
    updateTimerDisplay();
}, 1000);
</script>
<?php endif; ?>