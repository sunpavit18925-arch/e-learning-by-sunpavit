<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$course_id = isset($_REQUEST['course_id']) ? (int)$_REQUEST['course_id'] : 0;

if ($course_id <= 0) {
    header('Location: courses_all.php');
    exit();
}

// ตรวจสอบข้อมูลคอร์ส และสิทธิ์การจัดการ
try {
    $stmt_course = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header('Location: courses_all.php');
        exit();
    }

    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $isInstructor = ($isAdmin || ($user_id > 0 && $user_id === (int)$course['instructor_id']));

    if (!$isInstructor) {
        header('Location: courses.php?id=' . $course_id);
        exit();
    }

    $message = '';
    $messageType = '';

    // การจัดการ Action ต่างๆ (Add, Edit, Delete)
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $content_type = $_POST['content_type'] ?? 'video';
            $content_url = trim($_POST['content_url'] ?? '');
            $order_index = (int)($_POST['order_index'] ?? 1);

            if (empty($title)) {
                $message = 'กรุณากรอกชื่อบทเรียน';
                $messageType = 'error';
            } else {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO lessons (course_id, title, content_type, content_url, order_index) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_insert->execute([$course_id, $title, $content_type, $content_url, $order_index]);
                $message = 'เพิ่มบทเรียนใหม่เรียบร้อยแล้ว!';
                $messageType = 'success';
            }
        } elseif ($action === 'edit') {
            $lesson_id = (int)($_POST['lesson_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content_type = $_POST['content_type'] ?? 'video';
            $content_url = trim($_POST['content_url'] ?? '');
            $order_index = (int)($_POST['order_index'] ?? 1);

            if (empty($title) || $lesson_id <= 0) {
                $message = 'ข้อมูลบทเรียนไม่ถูกต้อง';
                $messageType = 'error';
            } else {
                $stmt_update = $pdo->prepare("
                    UPDATE lessons 
                    SET title = ?, content_type = ?, content_url = ?, order_index = ? 
                    WHERE lesson_id = ? AND course_id = ?
                ");
                $stmt_update->execute([$title, $content_type, $content_url, $order_index, $lesson_id, $course_id]);
                $message = 'แก้ไขข้อมูลบทเรียนเรียบร้อยแล้ว!';
                $messageType = 'success';
            }
        } elseif ($action === 'delete') {
            $lesson_id = (int)($_POST['lesson_id'] ?? 0);
            if ($lesson_id > 0) {
                // ลบความคืบหน้าของบทเรียนนี้ก่อน
                $stmt_del_prog = $pdo->prepare("DELETE FROM user_lesson_progress WHERE lesson_id = ?");
                $stmt_del_prog->execute([$lesson_id]);

                // ลบบทเรียน
                $stmt_delete = $pdo->prepare("DELETE FROM lessons WHERE lesson_id = ? AND course_id = ?");
                $stmt_delete->execute([$lesson_id, $course_id]);
                $message = 'ลบบทเรียนเรียบร้อยแล้ว!';
                $messageType = 'success';
            }
        }
    }

    // ดึงรายการบทเรียนทั้งหมดของคอร์สนี้
    $stmt_lessons = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC, lesson_id ASC");
    $stmt_lessons->execute([$course_id]);
    $lessons = $stmt_lessons->fetchAll(PDO::FETCH_ASSOC);

    // หา order_index ถัดไป
    $next_order = 1;
    if (!empty($lessons)) {
        $max_order = max(array_column($lessons, 'order_index'));
        $next_order = $max_order + 1;
    }

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

include 'header.php';
?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 w-full flex-1">
    <!-- Breadcrumb & Back button -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-4">
        <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400">
            <a href="index.php" class="hover:text-indigo-600 transition">หน้าหลัก</a>
            <span>/</span>
            <a href="courses_all.php" class="hover:text-indigo-600 transition">คอร์สทั้งหมด</a>
            <span>/</span>
            <a href="courses.php?id=<?= $course_id ?>" class="hover:text-indigo-600 transition truncate max-w-xs">
                <?= htmlspecialchars($course['title']) ?>
            </a>
            <span>/</span>
            <span class="text-slate-700 font-medium">จัดการบทเรียน</span>
        </div>
        <a href="courses.php?id=<?= $course_id ?>" 
           class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-semibold bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl transition">
            <i class="fa-solid fa-arrow-left"></i> กลับไปยังหน้ารายละเอียดคอร์ส
        </a>
    </div>

    <!-- Course Banner Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200 mb-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-600 text-xs font-bold px-3 py-1 rounded-full border border-indigo-100 mb-2">
                <i class="fa-solid fa-layer-group"></i> จัดการเนื้อหาบทเรียน
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
                <?= htmlspecialchars($course['title']) ?>
            </h1>
            <p class="text-slate-500 text-sm mt-1">
                มีบทเรียนทั้งหมด <span class="font-bold text-indigo-600"><?= count($lessons) ?></span> ตอน | กำหนดลำดับและลิงก์วิดีโอ YouTube ประกอบการสอน
            </p>
        </div>
        <button onclick="openAddModal()" 
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-2xl font-bold text-sm transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-plus-circle text-base"></i> เพิ่มบทเรียนใหม่
        </button>
    </div>

    <!-- Notification Alert -->
    <?php if ($message): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    icon: '<?= $messageType === 'success' ? 'success' : 'error' ?>',
                    title: '<?= $messageType === 'success' ? 'สำเร็จ!' : 'แจ้งเตือน' ?>',
                    text: '<?= addslashes($message) ?>',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        </script>
    <?php endif; ?>

    <!-- Lessons List -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200">
        <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-ol text-indigo-600"></i>
                <span>รายการบทเรียนเรียงตามลำดับ</span>
            </div>
            <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-semibold">
                <?= count($lessons) ?> บทเรียน
            </span>
        </h2>

        <?php if (!empty($lessons)): ?>
            <div class="space-y-4">
                <?php foreach ($lessons as $item): ?>
                    <?php 
                        // YouTube ID parsing for thumbnail
                        $yt_id = "";
                        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $item['content_url'] ?? '', $m)) {
                            $yt_id = $m[1];
                        }
                    ?>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl border border-slate-200 hover:border-indigo-300 hover:shadow-sm transition bg-slate-50/50">
                        <div class="flex items-start sm:items-center gap-4">
                            <!-- Order badge -->
                            <span class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center shrink-0 shadow-sm text-sm">
                                <?= (int)$item['order_index'] ?>
                            </span>

                            <!-- Thumbnail Preview (if video) -->
                            <?php if ($yt_id): ?>
                                <div class="w-24 h-14 rounded-lg overflow-hidden bg-black shrink-0 relative hidden sm:block border border-slate-200">
                                    <img src="https://img.youtube.com/vi/<?= $yt_id ?>/hqdefault.jpg" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black/20 flex items-center justify-center">
                                        <i class="fa-solid fa-play text-white/90 text-xs"></i>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Lesson Details -->
                            <div>
                                <h3 class="text-base font-bold text-slate-800">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h3>
                                <div class="flex items-center gap-3 text-xs text-slate-400 mt-1">
                                    <span class="capitalize px-2 py-0.5 rounded bg-slate-200/60 text-slate-600 font-medium">
                                        <i class="fa-solid <?= $item['content_type'] === 'video' ? 'fa-video' : 'fa-file-lines' ?> mr-1"></i>
                                        <?= htmlspecialchars($item['content_type']) ?>
                                    </span>
                                    <?php if (!empty($item['content_url'])): ?>
                                        <a href="<?= htmlspecialchars($item['content_url']) ?>" target="_blank" 
                                           class="text-indigo-600 hover:underline flex items-center gap-1 truncate max-w-xs">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i> ดูลิงก์วิดีโอ
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                            <button onclick='openEditModal(<?= json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' 
                                    class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-pen-to-square text-indigo-600"></i> แก้ไข
                            </button>
                            <button onclick="confirmDelete(<?= (int)$item['lesson_id'] ?>, '<?= addslashes(htmlspecialchars($item['title'])) ?>')" 
                                    class="bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-trash-can"></i> ลบ
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="text-5xl mb-3">🎬</div>
                <h3 class="text-base font-bold text-slate-700">ยังไม่มีบทเรียนในคอร์สนี้</h3>
                <p class="text-slate-400 text-sm mt-1 mb-6">คลิกปุ่มด้านล่างเพื่อเริ่มเพิ่มบทเรียนแรกของคุณ</p>
                <button onclick="openAddModal()" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition shadow-md">
                    <i class="fa-solid fa-plus-circle mr-1"></i> เพิ่มบทเรียนแรก
                </button>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal เพิ่ม/แก้ไขบทเรียน -->
<div id="lessonModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 relative animate-slideDown">
        <button onclick="closeModal()" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h3 id="modalTitle" class="text-xl font-extrabold text-slate-800 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-film text-indigo-600"></i>
            <span>เพิ่มบทเรียนใหม่</span>
        </h3>

        <form id="lessonForm" method="POST" action="manage-lessons.php?course_id=<?= $course_id ?>" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="lesson_id" id="formLessonId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ชื่อบทเรียน <span class="text-rose-500">*</span></label>
                <input type="text" name="title" id="formTitle" required
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition"
                       placeholder="เช่น บทนำและพื้นฐานการใช้งาน">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">ประเภทเนื้อหา</label>
                    <select name="content_type" id="formContentType" 
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition bg-white">
                        <option value="video">วิดีโอ (Video)</option>
                        <option value="text">บทความ (Text)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">ลำดับที่ (Order) <span class="text-rose-500">*</span></label>
                    <input type="number" name="order_index" id="formOrderIndex" min="1" required
                           value="<?= $next_order ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">ลิงก์วิดีโอ YouTube (URL)</label>
                <input type="url" name="content_url" id="formContentUrl"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition"
                       placeholder="https://www.youtube.com/watch?v=...">
                <p class="text-[11px] text-slate-400 mt-1">รองรับลิงก์เต็ม และ short link (youtu.be)</p>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeModal()" 
                        class="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 hover:bg-slate-100 transition">
                    ยกเลิก
                </button>
                <button type="submit" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-xl text-sm transition shadow-md shadow-indigo-600/30">
                    บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" action="manage-lessons.php?course_id=<?= $course_id ?>" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="lesson_id" id="deleteLessonId" value="">
</form>

<?php include 'footer.php'; ?>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-plus-circle text-indigo-600"></i> เพิ่มบทเรียนใหม่';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formLessonId').value = '';
    document.getElementById('formTitle').value = '';
    document.getElementById('formContentType').value = 'video';
    document.getElementById('formOrderIndex').value = '<?= $next_order ?>';
    document.getElementById('formContentUrl').value = '';
    document.getElementById('lessonModal').classList.remove('hidden');
}

function openEditModal(lesson) {
    document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-600"></i> แก้ไขบทเรียน';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formLessonId').value = lesson.lesson_id;
    document.getElementById('formTitle').value = lesson.title || '';
    document.getElementById('formContentType').value = lesson.content_type || 'video';
    document.getElementById('formOrderIndex').value = lesson.order_index || 1;
    document.getElementById('formContentUrl').value = lesson.content_url || '';
    document.getElementById('lessonModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('lessonModal').classList.add('hidden');
}

function confirmDelete(lessonId, lessonTitle) {
    Swal.fire({
        title: 'ยืนยันการลบบทเรียน?',
        text: `คุณต้องการลบ "${lessonTitle}" หรือไม่? ข้อมูลความคืบหน้าของบทเรียนนี้จะถูกลบไปด้วย`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteLessonId').value = lessonId;
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>
