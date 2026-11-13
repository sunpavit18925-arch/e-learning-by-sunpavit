<?php 
// ต้องอยู่บรรทัดแรกสุด เพื่อรับ ID จาก URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; 
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Builder</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Sarabun', sans-serif;
    }
    </style>
</head>

<body class="bg-slate-50 antialiased text-slate-900">

    <div class="max-w-4xl mx-auto py-12 px-4">
        <div class="mb-8 flex justify-between items-center">
            <h1 class="text-3xl font-bold">สร้างแบบทดสอบ</h1>
            <a href="courses.php?id=<?= $course_id ?>"
                class="text-slate-500 hover:text-blue-600 transition">กลับหน้าคอร์ส</a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border p-8 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-3">
                    <label class="block text-sm font-semibold mb-2">ชื่อหัวข้อแบบทดสอบ</label>
                    <input type="text" id="quiz_title"
                        class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 transition"
                        placeholder="ระบุชื่อแบบทดสอบ...">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2 text-slate-700">จำกัดเวลา (นาที)</label>
                    <input type="number" id="time_limit" value="30"
                        class="w-full px-4 py-3 rounded-xl border outline-none transition bg-slate-50">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2 text-slate-700">คะแนนที่ผ่าน (%)</label>
                    <input type="number" id="passing_score" value="60"
                        class="w-full px-4 py-3 rounded-xl border outline-none transition bg-slate-50">
                </div>
            </div>
        </div>

        <div id="questions-container"></div>

        <button onclick="addQuestion()"
            class="w-full py-6 border-2 border-dashed rounded-2xl text-slate-400 hover:border-blue-400 hover:text-blue-500 transition-all mb-10">
            + เพิ่มข้อคำถามใหม่
        </button>

        <div class="sticky bottom-6">
            <button onclick="submitQuiz()"
                class="w-full bg-slate-900 text-white py-4 rounded-2xl font-bold shadow-xl hover:bg-slate-800 transition active:scale-[0.98]">
                บันทึกแบบทดสอบ
            </button>
        </div>
    </div>

    <script>
 
let qCount = 0;
const courseId = <?= $course_id ?>;

// ฟังก์ชันสร้างข้อคำถาม
function addQuestion() {
    qCount++;
    const container = document.getElementById('questions-container');
    const div = document.createElement('div');
    div.className = "question-card bg-white rounded-2xl border p-8 mb-6 relative shadow-sm";
    
    // สร้างตัวเลือก 4 ข้อที่พิมพ์ข้อความคำตอบลงไปได้จริง
    let optionsHtml = '';
    for (let i = 0; i < 4; i++) {
        optionsHtml += `
            <div class="relative flex items-center bg-white border border-slate-200 rounded-xl px-4 py-3 hover:border-blue-400 transition">
                <input type="radio" name="correct_${qCount}" value="${i}" ${i === 0 ? 'checked' : ''} class="w-4 h-4 text-blue-600">
                <input type="text" placeholder="พิมพ์ข้อความคำตอบที่ ${i + 1}" 
                       class="opt-text flex-1 ml-3 outline-none text-sm text-slate-700 bg-transparent" required>
            </div>
        `;
    }

    div.innerHTML = `
        <div class="flex justify-between items-center mb-6">
            <span class="font-bold text-blue-600 text-lg">คำถามข้อที่ ${qCount}</span>
            <button type="button" onclick="this.closest('.question-card').remove()" class="text-slate-400 hover:text-red-500">ลบคำถาม</button>
        </div>
        <textarea placeholder="พิมพ์โจทย์คำถามที่นี่..." class="q-text w-full px-4 py-3 rounded-xl border mb-4 bg-slate-50 outline-none focus:ring-2 focus:ring-blue-500 min-h-[80px]" required></textarea>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            ${optionsHtml}
        </div>
        <div class="mt-4 flex justify-end">
            <div class="flex items-center text-sm text-slate-500">
                <span>คะแนนข้อนี้: </span>
                <input type="number" value="1" class="q-points w-16 ml-2 px-2 py-1 border-b border-slate-200 focus:outline-none focus:border-blue-500 text-center">
            </div>
        </div>
    `;
    container.appendChild(div);
}

// ฟังก์ชันส่งข้อมูลไปบันทึก
async function submitQuiz() {
    const questions = [];
    const questionCards = document.querySelectorAll('.question-card');

    questionCards.forEach((card, idx) => {
        const questionText = card.querySelector('.q-text').value;
        const points = card.querySelector('.q-points').value;
        
        // ดึงข้อความที่เราพิมพ์ในแต่ละช้อยส์
        const options = Array.from(card.querySelectorAll('.opt-text')).map(input => input.value);
        
        // หาว่าเลือก Radio ข้อไหน (0, 1, 2, 3)
        const checkedRadio = card.querySelector('input[type="radio"]:checked');
        const correctIndex = checkedRadio ? checkedRadio.value : 0;

        if (questionText.trim() !== "") {
            questions.push({
                text: questionText,
                options: options,
                correct: correctIndex,
                points: points
            });
        }
    });

    if (questions.length === 0) return Swal.fire('ข้อมูลไม่ครบ', 'กรุณาเพิ่มคำถามอย่างน้อย 1 ข้อ', 'warning');

    const payload = {
        course_id: courseId,
        title: document.getElementById('quiz_title').value || "แบบทดสอบไม่มีชื่อ",
        time_limit: document.getElementById('time_limit').value,
        passing_score: document.getElementById('passing_score').value,
        questions: questions
    };

    Swal.fire({ title: 'กำลังบันทึก...', didOpen: () => { Swal.showLoading() }, allowOutsideClick: false });

    try {
        const res = await fetch('save_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.status === 'success') {
            Swal.fire('สำเร็จ!', result.message, 'success').then(() => {
                window.location.href = 'courses.php?id=' + courseId;
            });
        } else {
            Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    }
}

// เรียกให้มีข้อแรกทันทีเมื่อโหลดหน้า
addQuestion();
</script>
</body>

</html>