<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; 

if ($course_id <= 0) {
    header('Location: courses_all.php');
    exit();
}

try {
    // 1. ดึงข้อมูลคอร์ส และผู้สอน
    $stmt_course = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.image as instructor_image, cat.name AS category_name 
        FROM courses c 
        JOIN users u ON c.instructor_id = u.user_id 
        LEFT JOIN categories cat ON c.category_id = cat.category_id
        WHERE c.course_id = ?
    ");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header('Location: courses_all.php');
        exit();
    }

    // 2. ดึงรายการบทเรียน
    $stmt_lessons = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC");
    $stmt_lessons->execute([$course_id]);
    $lessons = $stmt_lessons->fetchAll(PDO::FETCH_ASSOC);

    // 2.1 ดึงสถานะบทเรียนที่เรียนจบแล้วของผู้ใช้
    $completed_lesson_ids = [];
    if ($user_id > 0) {
        $stmt_prog = $pdo->prepare("SELECT DISTINCT p.lesson_id FROM user_lesson_progress p JOIN lessons l ON l.lesson_id=p.lesson_id WHERE p.user_id = ? AND l.course_id = ? AND p.is_completed = 1");
        $stmt_prog->execute([$user_id, $course_id]);
        $completed_lesson_ids = $stmt_prog->fetchAll(PDO::FETCH_COLUMN);
    }
    $completed_lessons = array_fill_keys($completed_lesson_ids, true);
    $completed_count = count($completed_lessons);
    $total_count = count($lessons);
    $progress_pct = $total_count > 0 ? (int)round(($completed_count / $total_count) * 100) : 0;

    // 3. ตรวจสอบ Quiz ของคอร์สนี้
    $stmt_quiz = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
    $stmt_quiz->execute([$course_id]);
    $quiz = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

    // 4. ตรวจสอบการลงทะเบียน (Enrollment)
    $stmt_enroll = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt_enroll->execute([$user_id, $course_id]);
    $enrollment = $stmt_enroll->fetch(PDO::FETCH_ASSOC);
    $isEnrolled = !empty($enrollment);

    // 5. ตรวจสอบผลการสอบของผู้ใช้ (ถ้ามี Quiz)
    $hasPassedQuiz = false;
    $latestAttempt = null;
    if ($quiz) {
        $stmt_attempt = $pdo->prepare("
            SELECT * FROM quiz_attempts 
            WHERE user_id = ? AND quiz_id = ? 
            ORDER BY attempt_date DESC, attempt_id DESC LIMIT 1
        ");
        $stmt_attempt->execute([$user_id, $quiz['quiz_id']]);
        $latestAttempt = $stmt_attempt->fetch(PDO::FETCH_ASSOC);

        $stmt_pass = $pdo->prepare("
            SELECT 1 FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND is_passed = 1 LIMIT 1
        ");
        $stmt_pass->execute([$user_id, $quiz['quiz_id']]);
        $passedRecord = $stmt_pass->fetch(PDO::FETCH_ASSOC);
        $hasPassedQuiz = !empty($passedRecord);
    }

    // 6. ตรวจสอบสิทธิ์ Admin หรือ Instructor เจ้าของคอร์ส
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $isInstructor = ($isAdmin || ($user_id > 0 && $user_id === (int)$course['instructor_id']));

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>

<?php include 'header.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="index.php" class="hover:text-indigo-600 transition">หน้าหลัก</a>
        <span>/</span>
        <a href="courses_all.php" class="hover:text-indigo-600 transition">คอร์สทั้งหมด</a>
        <span>/</span>
        <span class="text-slate-700 font-medium truncate max-w-xs"><?= htmlspecialchars($course['title']) ?></span>
    </div>

    <!-- Course Header Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200 mb-8">
        <div class="flex flex-col md:flex-row gap-6 items-start">
            <!-- Course Image -->
            <div class="w-full md:w-72 h-44 rounded-2xl overflow-hidden bg-slate-100 shrink-0 border border-slate-200">
                <?php 
                    $thumbPath = 'uploads/' . $course['thumbnail_url'];
                    $thumbnail = (!empty($course['thumbnail_url']) && file_exists($thumbPath))
                        ? 'uploads/' . htmlspecialchars($course['thumbnail_url'])
                        : 'image/pic_default.jpg';
                ?>
                <img src="<?= $thumbnail ?>" alt="<?= htmlspecialchars($course['title']) ?>" 
                     class="w-full h-full object-cover" onerror="this.src='image/pic_default.jpg'">
            </div>

            <!-- Details -->
            <div class="flex-1 w-full">
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-bold rounded-full border border-indigo-100">
                        <?= htmlspecialchars($course['category_name'] ?? 'ทั่วไป') ?>
                    </span>
                    <?php if ($hasPassedQuiz): ?>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-600 text-xs font-bold rounded-full border border-emerald-100 flex items-center gap-1">
                            <i class="fa-solid fa-circle-check"></i> ผ่านหลักสูตรแล้ว
                        </span>
                    <?php elseif ($isEnrolled): ?>
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 text-xs font-bold rounded-full border border-blue-100 flex items-center gap-1">
                            <i class="fa-solid fa-book-open-reader"></i> กำลังศึกษา
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mb-3 leading-tight">
                    <?= htmlspecialchars($course['title']) ?>
                </h1>

                <p class="text-slate-600 text-sm leading-relaxed mb-4">
                    <?= nl2br(htmlspecialchars($course['description'])) ?>
                </p>

                <!-- Instructor & Actions -->
                <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-slate-200 overflow-hidden border border-slate-300">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($course['first_name'] . ' ' . $course['last_name']) ?>&background=6366f1&color=fff" 
                                 class="w-full h-full object-cover">
                        </div>
                        <div>
                            <div class="text-xs text-slate-400">ผู้สอน</div>
                            <div class="text-sm font-bold text-slate-800">
                                👨‍🏫 <?= htmlspecialchars($course['first_name'] . ' ' . $course['last_name']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php if (!$isEnrolled): ?>
                            <button id="enrollBtn" onclick="enrollCourse(<?= $course_id ?>)"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition shadow-md shadow-indigo-600/30 flex items-center gap-2">
                                <i class="fa-solid fa-graduation-cap"></i> สมัครเรียนคอร์สนี้
                            </button>
                        <?php endif; ?>

                        <?php if ($isInstructor): ?>
                            <a href="edit-course.php?course_id=<?= $course_id ?>" 
                               class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-semibold text-sm transition flex items-center gap-1.5">
                                <i class="fa-solid fa-pen-to-square"></i> แก้ไขคอร์ส
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Progress (Enrolled Students) -->
                <?php if ($isEnrolled && $total_count > 0): ?>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                            <span class="text-slate-600 flex items-center gap-1.5">
                                <i class="fa-solid fa-bars-progress text-indigo-600"></i> ความคืบหน้าการเรียน
                            </span>
                            <span id="progressSummary" class="text-indigo-600 font-extrabold"><?= $completed_count ?> / <?= $total_count ?> บทเรียน (<?= $progress_pct ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                            <div id="progressBar" class="bg-gradient-to-r from-indigo-500 to-emerald-500 h-full rounded-full transition-all duration-500" style="width: <?= $progress_pct ?>%;"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lessons Section -->
    <div class="mb-10">
        <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
            <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-video text-indigo-600"></i>
                <span>บทเรียนและวิดีโอประกอบการสอน</span>
                <span class="text-xs bg-slate-200 text-slate-700 font-semibold px-2 py-0.5 rounded-full">
                    <?= count($lessons) ?> ตอน
                </span>
            </h2>
            <?php if ($isInstructor): ?>
                <a href="manage-lessons.php?course_id=<?= $course_id ?>" 
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl font-bold text-xs transition shadow-md shadow-indigo-600/30 flex items-center gap-1.5">
                    <i class="fa-solid fa-pen-to-square"></i> จัดการบทเรียน
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($lessons)): ?>
            <div class="space-y-6">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 transition hover:shadow-md">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                            <h3 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-extrabold flex items-center justify-center shrink-0">
                                    <?= (int)$lesson['order_index'] ?>
                                </span>
                                <span><?= htmlspecialchars($lesson['title']) ?></span>
                            </h3>

                            <div class="flex items-center gap-2 self-start sm:self-auto">
                                <?php if ($user_id > 0): ?>
                                    <?php $isDone = isset($completed_lessons[(int)$lesson['lesson_id']]); ?>
                                    <button id="lesson-btn-<?= $lesson['lesson_id'] ?>" 
                                            onclick="toggleLesson(<?= (int)$lesson['lesson_id'] ?>)" 
                                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 <?= $isDone ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                        <i id="lesson-icon-<?= $lesson['lesson_id'] ?>" class="fa-solid <?= $isDone ? 'fa-circle-check text-emerald-500' : 'fa-regular fa-circle text-slate-400' ?>"></i>
                                        <span id="lesson-text-<?= $lesson['lesson_id'] ?>"><?= $isDone ? 'เรียนจบแล้ว' : 'ทำเครื่องหมายว่าเรียนจบแล้ว' ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php 
                            // Extract YouTube Video ID
                            $raw_url = $lesson['content_url'] ?? '';
                            $video_id = "";
                            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $raw_url, $match)) {
                                $video_id = $match[1];
                            }
                        ?>

                        <?php if ($video_id): ?>
                            <div class="relative w-full overflow-hidden rounded-2xl bg-black aspect-video max-w-3xl mx-auto shadow-inner">
                                <iframe loading="lazy" src="https://www.youtube.com/embed/<?= $video_id ?>?rel=0"
                                        class="absolute top-0 left-0 w-full h-full border-0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen>
                                </iframe>
                            </div>
                        <?php else: ?>
                            <div class="bg-amber-50 border border-amber-200 text-amber-700 p-4 rounded-2xl text-sm flex items-center gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                                <span>เนื้อหาบทเรียนนี้ยังไม่มีวิดีโอ หรือลิงก์วิดีโอไม่ถูกต้อง</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200">
                <div class="text-4xl mb-3">🎬</div>
                <h3 class="text-lg font-bold text-slate-700 mb-1">ยังไม่มีบทเรียนในคอร์สนี้</h3>
                <p class="text-slate-400 text-sm">ผู้สอนกำลังจัดเตรียมเนื้อหาบทเรียน กรุณาตรวจสอบใหม่อีกครั้งในภายหลัง</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quiz & Assessment Section -->
    <div class="bg-gradient-to-br from-indigo-900 to-slate-900 rounded-3xl p-6 sm:p-10 text-white shadow-xl relative overflow-hidden mb-8">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="max-w-xl">
                <div class="inline-flex items-center gap-2 bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 px-3 py-1 rounded-full text-xs font-semibold mb-3">
                    <i class="fa-solid fa-flag-checkered"></i> แบบทดสอบวัดผลการเรียนรู้
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold mb-2">
                    <?= $quiz ? htmlspecialchars($quiz['title']) : 'แบบทดสอบท้ายบทเรียน' ?>
                </h2>
                <p class="text-slate-300 text-sm leading-relaxed mb-3">
                    <?= $quiz 
                        ? 'เกณฑ์คะแนนผ่าน: ' . $quiz['passing_score'] . '% | เวลาในการทำข้อสอบ: ' . $quiz['time_limit'] . ' นาที' 
                        : 'คอร์สเรียนนี้ยังไม่มีแบบทดสอบ' ?>
                </p>

                <?php if ($latestAttempt): ?>
                    <div class="text-xs text-indigo-200 flex items-center gap-2 mt-2 bg-white/10 p-2.5 rounded-xl border border-white/10 inline-block">
                        <i class="fa-solid fa-chart-simple"></i>
                        <span>คะแนนล่าสุดของคุณ: <b><?= number_format($latestAttempt['score_obtained'], 2) ?>%</b></span>
                        <span class="font-bold <?= $latestAttempt['is_passed'] ? 'text-emerald-300' : 'text-rose-300' ?>">
                            (<?= $latestAttempt['is_passed'] ? 'สอบผ่านแล้ว' : 'ยังไม่ผ่าน' ?>)
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <?php if ($quiz): ?>
                    <!-- ปุ่มสำหรับผู้เรียน -->
                    <a href="quiz_view.php?id=<?= $course_id ?>" 
                       class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-6 py-3.5 rounded-2xl transition shadow-lg shadow-indigo-600/40 flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-play"></i> เริ่มทำแบบทดสอบ
                    </a>

                    <a href="quiz_score_board.php?course_id=<?= $course_id ?>" 
                       class="bg-white/10 hover:bg-white/20 text-white font-semibold px-5 py-3.5 rounded-2xl transition border border-white/20 flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-trophy text-amber-400"></i> ตารางคะแนน
                    </a>

                    <?php if ($hasPassedQuiz || $isAdmin): ?>
                        <a href="generate_pdf.php?id=<?= $course_id ?>" target="_blank"
                           class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 py-3.5 rounded-2xl transition shadow-lg shadow-emerald-600/30 flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-certificate"></i> รับใบประกาศนียบัตร
                        </a>
                    <?php endif; ?>

                    <!-- ปุ่มสำหรับผู้สอน/แอดมิน -->
                    <?php if ($isInstructor): ?>
                        <a href="edit-quiz.php?id=<?= $course_id ?>" 
                           class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-3.5 rounded-2xl transition flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-pen-to-square"></i> แก้ไขแบบทดสอบ
                        </a>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($isInstructor): ?>
                        <a href="add-quiz.php?id=<?= $course_id ?>" 
                           class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-3.5 rounded-2xl transition shadow-lg flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-plus-circle"></i> สร้างแบบทดสอบใหม่
                        </a>
                    <?php else: ?>
                        <span class="text-slate-400 text-sm">ยังไม่มีแบบทดสอบสำหรับคอร์สนี้</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
// ฟังก์ชันสมัครเรียนคอร์สนี้ (AJAX)
function enrollCourse(courseId) {
    const formData = new FormData();
    formData.append('course_id', courseId);

    fetch('enrollments.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('แจ้งเตือน', data.message, 'info');
        }
    })
    .catch(() => {
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    });
}

// ฟังก์ชันบันทึกสถานะการเรียนจบของบทเรียน (AJAX)
function toggleLesson(lessonId) {
    const formData = new FormData();
    formData.append('lesson_id', lessonId);

    fetch('toggle_lesson_progress.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const btn = document.getElementById('lesson-btn-' + lessonId);
            const icon = document.getElementById('lesson-icon-' + lessonId);
            const text = document.getElementById('lesson-text-' + lessonId);

            if (data.is_completed) {
                if (btn) btn.className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100';
                if (icon) icon.className = 'fa-solid fa-circle-check text-emerald-500';
                if (text) text.textContent = 'เรียนจบแล้ว';
            } else {
                if (btn) btn.className = 'px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0 bg-slate-100 text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600';
                if (icon) icon.className = 'fa-solid fa-regular fa-circle text-slate-400';
                if (text) text.textContent = 'ทำเครื่องหมายว่าเรียนจบแล้ว';
            }

            // อัปเดต Progress Bar
            const bar = document.getElementById('progressBar');
            const summary = document.getElementById('progressSummary');
            if (bar) bar.style.width = data.progress_percentage + '%';
            if (summary) summary.textContent = `${data.completed_count} / ${data.total_count} บทเรียน (${data.progress_percentage}%)`;

            // Toast alert
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: data.message
            });
        } else {
            Swal.fire('แจ้งเตือน', data.message, 'info');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
    });
}
</script>
