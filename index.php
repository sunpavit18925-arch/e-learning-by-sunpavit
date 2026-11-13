<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);

// ดึงคอร์สเรียนล่าสุด 6 คอร์สมาแสดงในหน้าแรก
$stmt_featured = $pdo->query("
    SELECT c.*, cat.name AS category_name, u.first_name, u.last_name 
    FROM courses c 
    JOIN users u ON c.instructor_id = u.user_id 
    LEFT JOIN categories cat ON c.category_id = cat.category_id 
    ORDER BY c.course_id DESC 
    LIMIT 6
");
$featured_courses = $stmt_featured->fetchAll(PDO::FETCH_ASSOC);

// สถิติส่วนตัวของผู้ใช้ (ถ้าล็อกอิน)
$my_enrollments_count = 0;
$my_passed_quizzes_count = 0;

if ($user_id > 0) {
    $stmt_ec = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt_ec->execute([$user_id]);
    $my_enrollments_count = (int)$stmt_ec->fetchColumn();

    $stmt_qc = $pdo->prepare("SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE user_id = ? AND is_passed = 1");
    $stmt_qc->execute([$user_id]);
    $my_passed_quizzes_count = (int)$stmt_qc->fetchColumn();
}
?>

<?php include 'header.php'; ?>

<main class="w-full flex-1">
    <!-- Hero Section -->
    <header class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 py-20 px-6 text-center text-white border-b border-indigo-950/50">
        <div class="relative z-10 max-w-4xl mx-auto">
            <div class="inline-flex items-center gap-2 bg-indigo-500/10 border border-indigo-400/20 text-indigo-300 px-4 py-1.5 rounded-full text-xs font-semibold mb-6 backdrop-blur-md">
                <span>🌟</span> ระบบการเรียนรู้ออนไลน์เพื่อการพัฒนาวิชาชีพ
            </div>
            
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-6 leading-tight tracking-tight">
                ก้าวสู่โลกแห่งการเรียนรู้ <br>
                <span class="bg-gradient-to-r from-indigo-400 via-purple-300 to-pink-400 bg-clip-text text-transparent">
                    ไร้ขีดจำกัด ทุกที่ ทุกเวลา
                </span>
            </h1>
            
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto mb-10 font-light leading-relaxed">
                ยกระดับองค์ความรู้ด้วยหลักสูตรมาตรฐาน พัฒนาทักษะที่จำเป็น พร้อมแบบทดสอบวัดผลสัมฤทธิ์และรับใบประกาศนียบัตรรับรอง
            </p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="courses_all.php" 
                   class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-4 rounded-2xl font-bold text-base shadow-xl shadow-indigo-600/30 transition hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>สำรวจคอร์สเรียนทั้งหมด</span>
                </a>
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="quiz-history.php" 
                       class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-8 py-4 rounded-2xl font-bold text-base backdrop-blur-sm transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-award text-amber-400"></i>
                        <span>ประวัติการสอบของฉัน</span>
                    </a>
                <?php else: ?>
                    <a href="register.php" 
                       class="bg-white/10 hover:bg-white/20 text-white border border-white/20 px-8 py-4 rounded-2xl font-bold text-base backdrop-blur-sm transition flex items-center justify-center gap-2">
                        <span>สมัครสมาชิกฟรี</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ambient Glow -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
    </header>

    <!-- User Dashboard Overview (If Logged In) -->
    <?php if (isset($_SESSION['username'])): ?>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 -mt-8 relative z-20">
        <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl shrink-0">
                    👋
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        ยินดีต้อนรับกลับมา, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>
                    </h2>
                    <p class="text-xs text-slate-400">
                        พร้อมที่จะเรียนรู้และต่อยอดทักษะใหม่ในวันนี้หรือยัง?
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4 sm:gap-8 w-full md:w-auto justify-around border-t md:border-t-0 pt-4 md:pt-0 border-slate-100">
                <div class="text-center">
                    <div class="text-2xl font-extrabold text-indigo-600"><?= $my_enrollments_count ?></div>
                    <div class="text-xs text-slate-500">คอร์สที่ลงทะเบียน</div>
                </div>
                <div class="h-8 w-px bg-slate-200"></div>
                <div class="text-center">
                    <div class="text-2xl font-extrabold text-emerald-600"><?= $my_passed_quizzes_count ?></div>
                    <div class="text-xs text-slate-500">สอบผ่านแล้ว</div>
                </div>
                <div class="h-8 w-px bg-slate-200"></div>
                <div>
                    <a href="quiz-history.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition inline-block">
                        ดูประวัติ
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Overview -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-indigo-600 text-xs font-bold uppercase tracking-wider">จุดเด่นของระบบ</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-1">
                การเรียนรู้ที่ตอบโจทย์และมีประสิทธิภาพ
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200 group">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition">
                    📚
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">บทเรียนเข้มข้น</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    คัดสรรเนื้อหาคุณภาพจากวิทยากรเฉพาะทาง พร้อมวิดีโอประกอบและเอกสารเพื่อความเข้าใจที่แท้จริง
                </p>
            </div>

            <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200 group">
                <div class="w-14 h-14 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition">
                    ⏱️
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">เรียนได้ตามสะดวก 24 ชม.</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    บริหารเวลาเรียนรู้ได้ด้วยตนเอง เข้าถึงผ่านมือถือ แท็บเล็ต หรือคอมพิวเตอร์ได้ตลอดเวลา
                </p>
            </div>

            <div class="bg-white p-8 rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200 group">
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition">
                    🎓
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">ใบประกาศนียบัตรรับรอง</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    ทำแบบทดสอบวัดผลเพื่อรับ Certificate อย่างเป็นทางการ สามารถดาวน์โหลดในรูปแบบ PDF ได้ทันที
                </p>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section class="bg-slate-100/60 py-16 border-t border-slate-200/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-10 gap-4">
                <div>
                    <span class="text-indigo-600 text-xs font-bold uppercase tracking-wider">หลักสูตรแนะนำ</span>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-1">คอร์สเรียนยอดนิยม</h2>
                </div>
                <a href="courses_all.php" class="text-indigo-600 hover:text-indigo-700 font-bold text-sm flex items-center gap-1">
                    <span>ดูทั้งหมด</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($featured_courses as $c): 
                    $thumbPath = 'uploads/' . $c['thumbnail_url'];
                    $thumbnail = (!empty($c['thumbnail_url']) && file_exists($thumbPath))
                        ? 'uploads/' . htmlspecialchars($c['thumbnail_url'])
                        : 'image/pic_default.jpg';
                ?>
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200 flex flex-col group">
                    <div class="relative w-full h-48 overflow-hidden bg-slate-100">
                        <img src="<?= $thumbnail ?>" alt="<?= htmlspecialchars($c['title']) ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                             onerror="this.src='image/pic_default.jpg'">
                        <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-indigo-300 text-xs font-semibold px-3 py-1 rounded-full border border-slate-700">
                            <?= htmlspecialchars($c['category_name'] ?? 'ทั่วไป') ?>
                        </span>
                    </div>

                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex items-center text-xs text-slate-500 mb-2">
                            <i class="fa-solid fa-chalkboard-user mr-1.5 text-indigo-500"></i>
                            <span class="font-medium truncate"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></span>
                        </div>

                        <h3 class="text-lg font-bold text-slate-800 line-clamp-1 mb-2 group-hover:text-indigo-600 transition">
                            <?= htmlspecialchars($c['title']) ?>
                        </h3>

                        <p class="text-slate-500 text-sm leading-relaxed mb-4 flex-1 line-clamp-2">
                            <?= mb_strimwidth(htmlspecialchars($c['description']), 0, 90, "...") ?>
                        </p>

                        <div class="pt-3 border-t border-slate-100 mt-auto">
                            <a href="courses.php?id=<?= $c['course_id'] ?>" 
                               class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-xl text-center flex items-center justify-center transition shadow-sm">
                                <span>เข้าสู่บทเรียน</span>
                                <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>