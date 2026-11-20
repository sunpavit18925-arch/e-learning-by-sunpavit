<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';
require_once __DIR__ . '/includes/pagination.php';

// 1. ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? ''; 

try {
    $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts qa JOIN quizzes q ON q.quiz_id=qa.quiz_id JOIN courses c ON c.course_id=q.course_id WHERE qa.user_id=?');
    $count_stmt->execute([$user_id]);
    $paging = elearning_pagination((int)$count_stmt->fetchColumn());
    // 2. ดึงข้อมูลประวัติการสอบเฉพาะหน้าปัจจุบัน
    $stmt = $pdo->prepare("
        SELECT 
            qa.attempt_id,
            q.quiz_id,
            q.course_id,
            q.title AS quiz_title, 
            c.title AS course_title,
            q.time_limit,
            q.passing_score,
            qa.score_obtained, 
            qa.is_passed, 
            qa.attempt_date,
            qa.time_spent_seconds
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        JOIN courses c ON q.course_id = c.course_id
        WHERE qa.user_id = ?
        ORDER BY qa.attempt_date DESC, qa.attempt_id DESC LIMIT {$paging['size']} OFFSET {$paging['offset']}
    ");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<?php include 'header.php'; ?>

<main class="max-w-6xl mx-auto py-8 sm:py-12 px-4 w-full flex-1">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">ประวัติการทำแบบทดสอบ</h1>
            <p class="text-slate-500 mt-1">
                บันทึกคะแนนและผลการทดสอบของคุณ <span class="font-bold text-indigo-600">@<?= htmlspecialchars($username) ?></span>
            </p>
        </div>
        <a href="courses_all.php" class="bg-white border border-slate-200 px-5 py-2.5 rounded-2xl hover:bg-slate-50 transition shadow-sm font-semibold text-slate-700 text-sm flex items-center gap-2">
            <i class="fa-solid fa-graduation-cap text-indigo-600"></i> ดูคอร์สเรียนทั้งหมด
        </a>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900 text-white text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-semibold">แบบทดสอบ / คอร์ส</th>
                        <th class="px-6 py-4 font-semibold text-center">เกณฑ์ผ่าน</th>
                        <th class="px-6 py-4 font-semibold text-center">เวลาที่ใช้</th>
                        <th class="px-6 py-4 font-semibold">วันที่สอบ</th>
                        <th class="px-6 py-4 font-semibold text-center">คะแนนที่ได้</th>
                        <th class="px-6 py-4 font-semibold text-center">ผลลัพธ์</th>
                        <th class="px-6 py-4 font-semibold text-center">การกระทำ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($history) > 0): ?>
                        <?php foreach ($history as $row): ?>
                        <tr class="hover:bg-indigo-50/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-slate-800">
                                <div class="font-bold text-slate-800"><?= htmlspecialchars($row['quiz_title']) ?></div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    <i class="fa-solid fa-book-open text-indigo-400 mr-1"></i>
                                    <?= htmlspecialchars($row['course_title']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-slate-100 px-2.5 py-1 rounded-lg text-slate-600 text-xs font-bold">
                                    <?= (int)$row['passing_score'] ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-600 text-xs font-medium">
                                <?= $row['time_spent_seconds'] > 0 
                                    ? (int)($row['time_spent_seconds'] / 60) . ' นาที ' . ($row['time_spent_seconds'] % 60) . ' วิ' 
                                    : '-' ?>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs whitespace-nowrap">
                                <?= date('d/m/Y H:i', strtotime($row['attempt_date'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-base font-mono font-extrabold <?= $row['is_passed'] ? 'text-emerald-600' : 'text-rose-500' ?>">
                                    <?= number_format($row['score_obtained'], 2) ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($row['is_passed']): ?>
                                    <span class="inline-flex items-center px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">
                                        <i class="fa-solid fa-check mr-1 text-[10px]"></i> ผ่านเกณฑ์
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 bg-rose-100 text-rose-700 rounded-full text-xs font-bold">
                                        <i class="fa-solid fa-xmark mr-1 text-[10px]"></i> ไม่ผ่าน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($row['is_passed']): ?>
                                        <a href="generate_pdf.php?id=<?= $row['course_id'] ?>" target="_blank"
                                           class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-semibold transition flex items-center gap-1"
                                           title="ดาวน์โหลดใบประกาศนียบัตร">
                                            <i class="fa-solid fa-certificate"></i> ใบประกาศฯ
                                        </a>
                                    <?php endif; ?>
                                    <a href="quiz_view.php?id=<?= $row['course_id'] ?>" 
                                       class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-semibold transition flex items-center gap-1"
                                       title="สอบใหม่">
                                        <i class="fa-solid fa-rotate-right"></i> สอบใหม่
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="text-4xl text-slate-300 mb-3">📝</div>
                                    <p class="text-slate-600 font-bold text-base mb-1">ยังไม่มีประวัติการทำแบบทดสอบ</p>
                                    <p class="text-slate-400 text-xs mb-4">เข้าสู่บทเรียนและทำแบบทดสอบเพื่อดูคะแนนของคุณที่นี่</p>
                                    <a href="courses_all.php" class="px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">
                                        เลือกคอร์สเรียนเลย
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elearning_render_pagination($paging); ?>
</main>

<?php include 'footer.php'; ?>