<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isInstructor = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'instructor']);

if (!$isInstructor) {
    die("คุณไม่มีสิทธิ์ในการเพิ่มคอร์สเรียน (เฉพาะผู้สอนหรือผู้ดูแลระบบ)");
}

// 1. ดึงข้อมูลหมวดหมู่จากฐานข้อมูล
$cat_stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY category_id ASC");
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. ดึงรายชื่อผู้สอน (สำหรับแอดมินเลือก)
$inst_stmt = $pdo->query("SELECT user_id, first_name, last_name, username FROM users WHERE role IN ('admin', 'instructor') ORDER BY first_name ASC");
$instructors = $inst_stmt->fetchAll(PDO::FETCH_ASSOC);

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 1);
    
    // ผู้สอน: แอดมินสามารถเลือกได้ แต่ถ้าเป็นผู้สอนทั่วไปจะใช้ ID ตัวเอง
    if ($isAdmin && isset($_POST['instructor_id']) && (int)$_POST['instructor_id'] > 0) {
        $instructor_id = (int)$_POST['instructor_id'];
    } else {
        $instructor_id = (int)$_SESSION['user_id'];
    }

    $price = 0.00; // สามารถกำหนดเพิ่มเติมได้ในอนาคต

    if (empty($title) || empty($description)) {
        $error_msg = "กรุณากรอกชื่อคอร์สและรายละเอียดให้ครบถ้วน";
    } else {
        // จัดการรูปภาพหน้าปก
        $thumbnail_db_name = "pic_default.jpg";

        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === 0) {
            $upload_dir = "uploads/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = $_FILES['thumbnail_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = uniqid('course_') . "." . $file_ext;
                $target_destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $target_destination)) {
                    $thumbnail_db_name = $new_file_name;
                }
            } else {
                $error_msg = "ไฟล์รูปภาพไม่ถูกต้อง (รองรับเฉพาะ jpg, png, gif, webp)";
            }
        }

        if (empty($error_msg)) {
            try {
                $pdo->beginTransaction();

                // บันทึกคอร์สเรียนใหม่
                $sql = "INSERT INTO courses (title, description, price, instructor_id, category_id, thumbnail_url, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $description, $price, $instructor_id, $category_id, $thumbnail_db_name]);
                
                $course_id = (int)$pdo->lastInsertId();

                // สร้างการแจ้งเตือน (Notification) ให้ผู้ใช้ทุกคนในระบบ
                $stmt_users = $pdo->query("SELECT user_id FROM users");
                $all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

                if ($all_users) {
                    $noti_link = "courses.php?id=" . $course_id;
                    $sql_noti = "INSERT INTO notifications (user_id, title, message, link, is_read, created_at) 
                                 VALUES (?, ?, ?, ?, 0, NOW())";
                    $stmt_insert_noti = $pdo->prepare($sql_noti);

                    foreach ($all_users as $u) {
                        $current_title = ($u['user_id'] == $instructor_id) ? "สร้างคอร์สสำเร็จ!" : "คอร์สเรียนใหม่แกะกล่อง!";
                        $current_msg = ($u['user_id'] == $instructor_id) 
                            ? "คอร์ส '$title' ของคุณพร้อมให้เข้าเรียนแล้ว" 
                            : "เปิดตัวคอร์สใหม่: $title เริ่มเรียนได้แล้ววันนี้";

                        $stmt_insert_noti->execute([
                            $u['user_id'],
                            $current_title,
                            $current_msg,
                            $noti_link
                        ]);
                    }
                }

                $pdo->commit();

                header("Location: courses.php?id=" . $course_id);
                exit();

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_msg = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
            }
        }
    }
}
?>

<?php include 'header.php'; ?>

<main class="max-w-2xl mx-auto py-8 sm:py-12 px-4 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="courses_all.php" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> กลับไปหน้ารวมคอร์ส
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">เพิ่มคอร์สเรียนใหม่</span>
    </div>

    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-200">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-6 sm:p-8 text-white text-center">
            <h1 class="text-2xl font-bold">เพิ่มคอร์สเรียนใหม่</h1>
            <p class="text-indigo-100 text-xs sm:text-sm mt-1">กรอกข้อมูลหลักสูตรและอัปโหลดหน้าปกเพื่อเปิดสอน</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="m-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-lg shrink-0"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-5">
            <!-- Title -->
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-1.5">
                    ชื่อคอร์สเรียน <span class="text-red-500">*</span>
                </label>
                <input type="text" name="title" required 
                       placeholder="เช่น Web Development Boot Camp, พื้นฐาน Python" 
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-1.5">
                    รายละเอียดคอร์ส <span class="text-red-500">*</span>
                </label>
                <textarea name="description" rows="4" required 
                          placeholder="อธิบายเนื้อหา วัตถุประสงค์ และสิ่งที่ผู้เรียนจะได้รับ..." 
                          class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition resize-none"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Category & Instructor -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">หมวดหมู่</label>
                    <select name="category_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isAdmin): ?>
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">ผู้สอน (Instructor)</label>
                    <select name="instructor_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                        <?php foreach ($instructors as $inst): ?>
                            <option value="<?= $inst['user_id'] ?>" <?= $inst['user_id'] == $_SESSION['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['first_name'] . ' ' . $inst['last_name']) ?> (@<?= htmlspecialchars($inst['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Thumbnail Upload -->
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-1.5">รูปภาพหน้าปกคอร์ส (Thumbnail)</label>
                <div class="flex flex-col sm:flex-row items-center gap-4 p-4 border border-slate-200 rounded-2xl bg-slate-50">
                    <div class="w-36 h-24 bg-white rounded-xl border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                        <img id="preview_img" src="image/pic_default.jpg" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 w-full text-left">
                        <input type="file" name="thumbnail_file" id="thumbnail_input" accept="image/*"
                               onchange="previewFile(this)"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                        <p class="mt-1.5 text-xs text-slate-400">แนะนำไฟล์ JPG, PNG หรือ WebP ขนาด 800x450 พิกเซล (16:9)</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-2xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>บันทึกและสร้างคอร์สเรียน</span>
                </button>
            </div>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
function previewFile(input) {
    const preview = document.getElementById('preview_img');
    const file = input.files[0];
    const reader = new FileReader();

    reader.onloadend = function () {
        if (preview) preview.src = reader.result;
    };

    if (file) {
        reader.readAsDataURL(file);
    } else {
        if (preview) preview.src = "image/pic_default.jpg";
    }
}
</script>