<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';
require_once __DIR__ . '/includes/quiz-data.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

$quiz = null;
$my_best = null;
$leaderboard = [];
$error_message = null;

try {
    if ($course_id <= 0) {
        $error_message = "ไม่พบรหัสคอร์สเรียน";
    } else {
        // 1. ดึงข้อมูล Quiz และ คอร์ส
        $stmt_quiz = $pdo->prepare("
            SELECT q.*, c.title AS course_title 
            FROM quizzes q 
            JOIN courses c ON q.course_id = c.course_id 
            WHERE q.course_id = ? 
            LIMIT 1
        ");
        $stmt_quiz->execute([$course_id]);
        $quiz = $stmt_quiz->fetch(PDO::FETCH_ASSOC);

        if (!$quiz) {
            $error_message = "คอร์สเรียนนี้ยังไม่มีแบบทดสอบ";
        } else {
            $quiz_id = (int)$quiz['quiz_id'];

            // 2. ดึงคะแนนที่ดีที่สุดของผู้ใช้คนปัจจุบัน
            $stmt_my = $pdo->prepare("
                SELECT * FROM quiz_attempts 
                WHERE quiz_id = ? AND user_id = ? 
                ORDER BY score_obtained DESC, time_spent_seconds ASC, attempt_date DESC 
                LIMIT 1
            ");
            $stmt_my->execute([$quiz_id, $current_user_id]);
            $my_best = $stmt_my->fetch(PDO::FETCH_ASSOC);

            // 3. ดึง Top 10 Leaderboard (คะแนนสูงสุดของแต่ละคน)
            $stmt_top = $pdo->prepare(elearning_leaderboard_sql());
            $stmt_top->execute([$quiz_id]);
            $leaderboard = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการโหลดตารางคะแนน: " . $e->getMessage();
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
        <span class="text-slate-700 font-medium">ตารางคะแนน (Leaderboard)</span>
    </div>

    <?php if ($error_message): ?>
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-md mx-auto">
            <div class="text-5xl mb-4">🏆</div>
            <h2 class="text-xl font-bold text-slate-800 mb-2"><?= htmlspecialchars($error_message) ?></h2>
            <p class="text-slate-500 text-sm mb-6">ยังไม่มีข้อมูลตารางคะแนนสำหรับหลักสูตรนี้</p>
            <a href="courses.php?id=<?= $course_id ?>" 
               class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-2xl transition shadow-md">
                กลับหน้าหลักของคอร์ส
            </a>
        </div>
    <?php else: ?>
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 bg-amber-50 text-amber-700 border border-amber-200 px-3.5 py-1 rounded-full text-xs font-bold mb-2">
                <i class="fa-solid fa-trophy text-amber-500"></i> ทำเนียบผู้สอบคะแนนสูงสุด
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
                <?= htmlspecialchars($quiz['title']) ?>
            </h1>
            <p class="text-slate-500 text-sm mt-1">คอร์ส: <?= htmlspecialchars($quiz['course_title']) ?></p>
        </div>

        <!-- Current User's Best Score Card -->
        <?php if ($my_best): ?>
        <div class="bg-gradient-to-r from-indigo-900 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl mb-8 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 bg-indigo-500/30 rounded-2xl flex items-center justify-center text-indigo-300 text-2xl border border-indigo-400/30 shrink-0">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div>
                    <span class="text-xs text-indigo-300 uppercase tracking-wider font-semibold">คะแนนที่ดีที่สุดของคุณ</span>
                    <h2 class="text-xl font-bold text-white"><?= htmlspecialchars($_SESSION['username']) ?></h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <i class="fa-regular fa-clock mr-1"></i>
                        สอบล่าสุดเมื่อ: <?= date('d M Y | H:i', strtotime($my_best['attempt_date'])) ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-6 text-center md:text-right">
                <div>
                    <div class="text-xs text-slate-400 mb-1">เวลาที่ใช้</div>
                    <div class="text-sm font-bold text-slate-200">
                        <?= $my_best['time_spent_seconds'] > 0 
                            ? (int)($my_best['time_spent_seconds'] / 60) . ' นาที ' . ($my_best['time_spent_seconds'] % 60) . ' วิ' 
                            : '-' ?>
                    </div>
                </div>

                <div class="border-l border-slate-700 pl-6">
                    <div class="text-3xl sm:text-4xl font-extrabold <?= $my_best['is_passed'] ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= number_format($my_best['score_obtained'], 2) ?>%
                    </div>
                    <span class="inline-block mt-1 px-3 py-0.5 rounded-full text-xs font-bold uppercase <?= $my_best['is_passed'] ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
                        <?= $my_best['is_passed'] ? 'Passed (ผ่าน)' : 'Failed (ไม่ผ่าน)' ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Leaderboard Table Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-900 p-5 sm:p-6 text-white font-bold flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-award text-amber-400 text-lg"></i>
                    <span>อันดับคะแนนสูงสุด 10 อันดับแรก</span>
                </div>
                <span class="text-xs bg-slate-800 text-slate-300 px-3 py-1 rounded-full border border-slate-700">
                    เกณฑ์ผ่าน <?= (int)$quiz['passing_score'] ?>%
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="py-4 px-6 text-center w-20">อันดับ</th>
                            <th class="py-4 px-6">ผู้เรียน</th>
                            <th class="py-4 px-6 text-center">คะแนนสูงสุด</th>
                            <th class="py-4 px-6 text-center">เวลาที่ใช้</th>
                            <th class="py-4 px-6 text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="5" class="py-16 text-center text-slate-400">
                                    <i class="fa-regular fa-clipboard text-3xl mb-2 block text-slate-300"></i>
                                    ยังไม่มีผู้ทำแบบทดสอบนี้ในระบบ
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leaderboard as $index => $row): 
                                $isMe = ($row['user_id'] == $current_user_id);
                                $rank = $index + 1;
                            ?>
                            <tr class="<?= $isMe ? 'bg-indigo-50/60 font-semibold' : 'hover:bg-slate-50/70' ?> transition">
                                <!-- Rank -->
                                <td class="py-4 px-6 text-center">
                                    <?php if ($rank === 1): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-600 font-extrabold text-sm shadow-sm">🥇</span>
                                    <?php elseif ($rank === 2): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-200 text-slate-600 font-extrabold text-sm shadow-sm">🥈</span>
                                    <?php elseif ($rank === 3): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-200/60 text-amber-800 font-extrabold text-sm shadow-sm">🥉</span>
                                    <?php else: ?>
                                        <span class="text-slate-500 font-bold"><?= $rank ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- User Name -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 overflow-hidden shrink-0">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['username']) ?>&background=6366f1&color=fff" 
                                                 class="w-full h-full object-cover">
                                        </div>
                                        <div>
                                            <div class="text-slate-800 font-medium <?= $isMe ? 'text-indigo-700 font-bold' : '' ?>">
                                                <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                                <?php if ($isMe): ?>
                                                    <span class="ml-1 text-[10px] bg-indigo-600 text-white px-2 py-0.5 rounded-full">คุณ</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-slate-400">@<?= htmlspecialchars($row['username']) ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Score -->
                                <td class="py-4 px-6 text-center font-mono font-extrabold text-base <?= $row['has_passed'] ? 'text-indigo-600' : 'text-slate-700' ?>">
                                    <?= number_format($row['top_score'], 2) ?>%
                                </td>

                                <!-- Time Spent -->
                                <td class="py-4 px-6 text-center text-slate-500 text-xs">
                                    <?= $row['best_time'] > 0 
                                        ? (int)($row['best_time'] / 60) . ' นาที ' . ($row['best_time'] % 60) . ' วิ' 
                                        : '-' ?>
                                </td>

                                <!-- Status -->
                                <td class="py-4 px-6 text-center">
                                    <?php if ($row['has_passed']): ?>
                                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">
                                            ผ่านเกณฑ์
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-rose-100 text-rose-700 text-xs font-bold rounded-full">
                                            ยังไม่ผ่าน
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="quiz_view.php?id=<?= $course_id ?>" 
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-2xl shadow-lg shadow-indigo-600/30 transition">
                <i class="fa-solid fa-play text-xs"></i>
                <span>เข้าทำแบบทดสอบเพื่อจัดอันดับ</span>
            </a>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
