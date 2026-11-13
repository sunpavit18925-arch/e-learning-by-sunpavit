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

// 1. อัปเดตการแจ้งเตือนทั้งหมดของคนนี้ให้เป็น "อ่านแล้ว" (is_read = 1)
$update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([':uid' => $user_id]);

// 2. ดึงรายการแจ้งเตือนทั้งหมดมาแสดง
$sql = "SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $user_id]);
$all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="index.php" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-house"></i> หน้าหลัก
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">การแจ้งเตือนทั้งหมด</span>
    </div>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">การแจ้งเตือนทั้งหมด</h1>
            <p class="text-slate-500 text-sm mt-1">ข้อความแจ้งเตือนกิจกรรมและหลักสูตรใหม่ของคุณ</p>
        </div>
        <span class="text-xs bg-indigo-50 text-indigo-700 font-bold px-3 py-1.5 rounded-full border border-indigo-100">
            <?= count($all_notifications) ?> รายการ
        </span>
    </div>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <?php if (empty($all_notifications)): ?>
        <div class="p-16 text-center">
            <div class="text-5xl mb-3">🔔</div>
            <h3 class="text-lg font-bold text-slate-700 mb-1">ยังไม่มีการแจ้งเตือนในขณะนี้</h3>
            <p class="text-slate-400 text-sm mb-4">เมื่อมีหลักสูตรใหม่หรือกิจกรรม จะมีการแจ้งเตือนเข้ามาที่นี่</p>
            <a href="courses_all.php" class="inline-block px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">
                สำรวจคอร์สเรียน
            </a>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($all_notifications as $noti): ?>
            <div class="p-6 hover:bg-slate-50/70 transition flex items-start space-x-4">
                <div class="flex-shrink-0 w-11 h-11 bg-indigo-50 border border-indigo-100 rounded-2xl flex items-center justify-center text-indigo-600 text-lg">
                    <i class="fa-solid fa-bell"></i>
                </div>

                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-1">
                        <h3 class="font-bold text-slate-800 text-base"><?= htmlspecialchars($noti['title']) ?></h3>
                        <span class="text-xs text-slate-400">
                            <i class="fa-regular fa-clock mr-1"></i>
                            <?= date('d M Y | H:i', strtotime($noti['created_at'])) ?>
                        </span>
                    </div>
                    
                    <p class="text-slate-600 text-sm leading-relaxed mb-3">
                        <?= nl2br(htmlspecialchars($noti['message'])) ?>
                    </p>

                    <div class="flex items-center gap-3">
                        <a href="notification_detail.php?id=<?= $noti['noti_id'] ?>"
                            class="inline-flex items-center text-xs font-bold text-indigo-600 hover:text-indigo-800 transition">
                            <span>ดูรายละเอียด</span>
                            <i class="fa-solid fa-arrow-right ml-1 text-[10px]"></i>
                        </a>

                        <?php if (!empty($noti['link'])): ?>
                            <span class="text-slate-300">•</span>
                            <a href="<?= htmlspecialchars($noti['link']) ?>"
                                class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-800 transition">
                                <span>ไปยังหน้าที่เกี่ยวข้อง</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>