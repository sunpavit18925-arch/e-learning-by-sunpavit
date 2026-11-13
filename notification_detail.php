<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'header.php';

if (!$user) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$user['user_id'];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$notification = null;
$course_info = null;
$course_id = null;
$error_msg = null;

if ($notification_id <= 0) {
    $error_msg = "ไม่พบรหัสการแจ้งเตือนที่ถูกต้อง";
} else {
    // ดึงข้อมูลการแจ้งเตือนเฉพาะของ user คนนี้
    $sql = "SELECT * FROM notifications WHERE noti_id = :nid AND user_id = :uid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nid' => $notification_id, ':uid' => $user_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        $error_msg = "ไม่พบการแจ้งเตือน หรือคุณไม่มีสิทธิ์เข้าถึงข้อความนี้";
    } else {
        // อัปเดตสถานะเป็น "อ่านแล้ว"
        if ($notification['is_read'] == 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE noti_id = ?")->execute([$notification_id]);
        }

        // ตรวจสอบลิงก์เพื่อดึงข้อมูลคอร์สที่เกี่ยวข้อง
        if (!empty($notification['link'])) {
            $url_parts = parse_url($notification['link']);
            if (isset($url_parts['path']) && basename($url_parts['path']) == 'courses.php' && isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query_params);
                if (isset($query_params['id'])) {
                    $course_id = (int)$query_params['id'];
                    $course_sql = "SELECT c.title, c.thumbnail_url, u.first_name, u.last_name FROM courses c JOIN users u ON c.instructor_id = u.user_id WHERE c.course_id = :cid";
                    $course_stmt = $pdo->prepare($course_sql);
                    $course_stmt->execute([':cid' => $course_id]);
                    $course_info = $course_stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        }
    }
}
?>

<main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full flex-1">
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="notifications_all.php" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> กลับไปหน้ารวมการแจ้งเตือน
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">รายละเอียด</span>
    </div>

    <?php if ($error_msg): ?>
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm max-w-md mx-auto">
            <div class="text-5xl mb-4">⚠️</div>
            <h2 class="text-xl font-bold text-slate-800 mb-2"><?= htmlspecialchars($error_msg) ?></h2>
            <a href="notifications_all.php" class="mt-4 inline-block px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">
                กลับหน้ารายการแจ้งเตือน
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 sm:p-10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-5">
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 leading-snug">
                    <?= htmlspecialchars($notification['title']) ?>
                </h1>
                <span class="text-xs text-slate-400 shrink-0 flex items-center">
                    <i class="fa-regular fa-clock mr-1"></i>
                    <?= date('d M Y, H:i', strtotime($notification['created_at'])) ?>
                </span>
            </div>

            <div class="py-6 text-slate-700 text-base leading-relaxed">
                <?= nl2br(htmlspecialchars($notification['message'])) ?>
            </div>

            <?php if ($course_info): ?>
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">คอร์สที่เกี่ยวข้อง:</h3>
                    <a href="courses.php?id=<?= $course_id ?>" 
                       class="flex items-center bg-slate-50 hover:bg-indigo-50/50 p-4 rounded-2xl border border-slate-200 hover:border-indigo-200 transition group">
                        <?php
                            $thumbPath = 'uploads/' . $course_info['thumbnail_url'];
                            $thumbImg = (!empty($course_info['thumbnail_url']) && file_exists($thumbPath))
                                ? $thumbPath
                                : 'image/pic_default.jpg';
                        ?>
                        <div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-200 shrink-0 mr-4 border border-slate-200">
                            <img src="<?= htmlspecialchars($thumbImg) ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-slate-900 group-hover:text-indigo-600 transition truncate text-sm sm:text-base">
                                <?= htmlspecialchars($course_info['title']) ?>
                            </h4>
                            <p class="text-xs text-slate-400 mt-0.5">
                                ผู้สอน: <?= htmlspecialchars($course_info['first_name'] . ' ' . $course_info['last_name']) ?>
                            </p>
                        </div>
                        <i class="fa-solid fa-chevron-right text-slate-400 group-hover:text-indigo-600 transition ml-3 text-sm"></i>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!empty($notification['link']) && $notification['link'] !== '#'): ?>
                <div class="mt-8 pt-6 border-t border-slate-100 flex justify-end">
                    <a href="<?= htmlspecialchars($notification['link']) ?>" 
                       class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl transition shadow-md shadow-indigo-600/30 flex items-center gap-2 text-sm">
                        <span>ไปยังหน้าที่เกี่ยวข้อง</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>