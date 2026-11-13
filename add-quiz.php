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
    die("คุณไม่มีสิทธิ์ในการสร้างแบบทดสอบ");
}

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0); 

if ($course_id <= 0) {
    header('Location: courses_all.php');
    exit();
}

// ดึงชื่อคอร์ส
$stmt_c = $pdo->prepare("SELECT title FROM courses WHERE course_id = ?");
$stmt_c->execute([$course_id]);
$course = $stmt_c->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: courses_all.php');
    exit();
}
?>

<?php include 'header.php'; ?>

<main class="max-w-4xl mx-auto py-8 sm:py-12 px-4 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="courses.php?id=<?= $course_id ?>" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> กลับไปที่คอร์ส
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">สร้างแบบทดสอบใหม่</span>
    </div>

    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">สร้างแบบทดสอบท้ายบทเรียน</h1>
        <p class="text-slate-500 mt-1">คอร์ส: <span class="font-bold text-indigo-600"><?= htmlspecialchars($course['title']) ?></span></p>
    </div>

    <!-- Quiz Settings Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-8 mb-6">
        <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-indigo-600"></i> ตั้งค่าทั่วไปของแบบทดสอบ
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-3">
                <label class="block text-sm font-semibold text-slate-700 mb-2">ชื่อหัวข้อแบบทดสอบ <span class="text-red-500">*</span></label>
                <input type="text" id="quiz_title"
                    value="แบบทดสอบประจำหลักสูตร <?= htmlspecialchars($course['title']) ?>"
                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white text-slate-800 transition"
                    placeholder="ระบุชื่อแบบทดสอบ..." required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">จำกัดเวลา (นาที)</label>
                <input type="number" id="time_limit" value="30" min="1" max="180"
                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 transition bg-slate-50 focus:bg-white text-slate-800">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">เกณฑ์ผ่าน (%)</label>
                <input type="number" id="passing_score" value="60" min="1" max="100"
                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-2 focus:ring-indigo-500 transition bg-slate-50 focus:bg-white text-slate-800">
            </div>
            <div class="flex items-end">
                <p class="text-xs text-slate-400 mb-3">
                    <i class="fa-solid fa-circle-info mr-1"></i> ผู้เรียนที่ได้คะแนนถึงเกณฑ์จะสามารถรับใบประกาศนียบัตรได้
                </p>
            </div>
        </div>
    </div>

    <!-- Questions Container -->
    <div id="questions-container" class="space-y-6"></div>

    <!-- Add Question Button -->
    <button type="button" onclick="addQuestion()"
        class="mt-6 w-full py-5 border-2 border-dashed border-indigo-300 hover:border-indigo-500 rounded-2xl text-indigo-600 hover:bg-indigo-50/50 font-semibold transition flex items-center justify-center gap-2 mb-10 shadow-sm">
        <i class="fa-solid fa-plus-circle text-lg"></i>
        <span>เพิ่มข้อคำถามใหม่</span>
    </button>

    <!-- Save Button -->
    <div class="sticky bottom-6 z-20">
        <button type="button" onclick="submitQuiz()"
            class="w-full bg-slate-900 hover:bg-slate-800 text-white py-4 px-6 rounded-2xl font-bold text-lg shadow-xl shadow-slate-900/20 transition active:scale-[0.98] flex items-center justify-center gap-2">
            <i class="fa-solid fa-floppy-disk"></i>
            <span>บันทึกแบบทดสอบทั้งหมด</span>
        </button>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
let qCount = 0;
const courseId = <?= $course_id ?>;

function addQuestion() {
    qCount++;
    const container = document.getElementById('questions-container');
    const div = document.createElement('div');
    div.className = "question-card bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 relative shadow-sm";
    
    let optionsHtml = '';
    for (let i = 0; i < 4; i++) {
        optionsHtml += `
            <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 hover:border-indigo-400 focus-within:border-indigo-500 focus-within:bg-white transition">
                <input type="radio" name="correct_${qCount}" value="${i}" ${i === 0 ? 'checked' : ''} 
                       class="w-5 h-5 text-indigo-600 focus:ring-indigo-500 cursor-pointer mr-3">
                <input type="text" placeholder="พิมพ์ข้อความตัวเลือกที่ ${i + 1}" 
                       class="opt-text flex-1 outline-none text-sm text-slate-800 bg-transparent" required>
            </div>
        `;
    }

    div.innerHTML = `
        <div class="flex justify-between items-center mb-4">
            <span class="font-bold text-indigo-600 text-base flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center">${qCount}</span>
                คำถามข้อที่ ${qCount}
            </span>
            <button type="button" onclick="this.closest('.question-card').remove(); renumberQuestions();" 
                    class="text-slate-400 hover:text-red-500 text-sm font-semibold transition flex items-center gap-1">
                <i class="fa-regular fa-trash-can"></i> ลบข้อนี้
            </button>
        </div>
        
        <textarea placeholder="พิมพ์โจทย์คำถามที่นี่..." 
                  class="q-text w-full px-4 py-3 rounded-2xl border border-slate-200 mb-4 bg-slate-50 focus:bg-white outline-none focus:ring-2 focus:ring-indigo-500 text-slate-800 min-h-[90px] text-sm" required></textarea>
        
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">ตัวเลือก (เลือกปุ่มวงกลมข้อที่ถูกต้อง):</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            ${optionsHtml}
        </div>
        
        <div class="flex justify-end items-center pt-3 border-t border-slate-100 text-sm text-slate-500">
            <span class="mr-2">คะแนนของข้อนี้:</span>
            <input type="number" value="1" min="1" max="100" 
                   class="q-points w-20 px-3 py-1.5 rounded-xl border border-slate-200 text-center font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
    `;
    container.appendChild(div);
}

function renumberQuestions() {
    const cards = document.querySelectorAll('.question-card');
    cards.forEach((card, idx) => {
        const titleSpan = card.querySelector('.font-bold.text-indigo-600');
        if (titleSpan) {
            titleSpan.innerHTML = `<span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs flex items-center justify-center">${idx + 1}</span> คำถามข้อที่ ${idx + 1}`;
        }
    });
}

async function submitQuiz() {
    const title = document.getElementById('quiz_title').value.trim();
    if (!title) {
        return Swal.fire('ข้อมูลไม่ครบ', 'กรุณาระบุชื่อแบบทดสอบ', 'warning');
    }

    const questionCards = document.querySelectorAll('.question-card');
    if (questionCards.length === 0) {
        return Swal.fire('ข้อมูลไม่ครบ', 'กรุณาเพิ่มคำถามอย่างน้อย 1 ข้อ', 'warning');
    }

    const questions = [];
    let hasEmptyField = false;

    questionCards.forEach((card) => {
        const questionText = card.querySelector('.q-text').value.trim();
        const points = parseInt(card.querySelector('.q-points').value) || 1;
        const optionsInputs = card.querySelectorAll('.opt-text');
        const options = Array.from(optionsInputs).map(input => input.value.trim());
        const checkedRadio = card.querySelector('input[type="radio"]:checked');
        const correctIndex = checkedRadio ? parseInt(checkedRadio.value) : 0;

        if (!questionText || options.some(opt => !opt)) {
            hasEmptyField = true;
        }

        questions.push({
            text: questionText,
            options: options,
            correct: correctIndex,
            points: points
        });
    });

    if (hasEmptyField) {
        return Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกโจทย์คำถามและตัวเลือกให้ครบทุกช่อง', 'warning');
    }

    const payload = {
        course_id: courseId,
        title: title,
        time_limit: parseInt(document.getElementById('time_limit').value) || 30,
        passing_score: parseInt(document.getElementById('passing_score').value) || 60,
        questions: questions
    };

    const confirmResult = await Swal.fire({
        title: 'ยืนยันการบันทึกแบบทดสอบ?',
        text: `จำนวนคำถามทั้งหมด ${questions.length} ข้อ`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'ใช่, บันทึกเลย!',
        cancelButtonText: 'ยกเลิก'
    });

    if (!confirmResult.isConfirmed) return;

    Swal.fire({ 
        title: 'กำลังบันทึกข้อมูล...', 
        didOpen: () => { Swal.showLoading(); }, 
        allowOutsideClick: false 
    });

    try {
        const res = await fetch('save_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: result.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'courses.php?id=' + courseId;
            });
        } else {
            Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + e.message, 'error');
    }
}

// สร้างคำถามข้อแรกทันทีเมื่อเปิดหน้า
addQuestion();
</script>