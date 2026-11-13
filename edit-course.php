<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($course_id <= 0) {
    header('Location: courses_all.php');
    exit();
}

// 1. ดึงข้อมูลคอร์สปัจจุบัน
try {
    $sql = "SELECT * FROM courses WHERE course_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        header('Location: courses_all.php');
        exit();
    }

    // ตรวจสอบสิทธิ์ (Admin หรือ เจ้าของคอร์ส)
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $isOwner = ((int)$_SESSION['user_id'] === (int)$course['instructor_id']);

    if (!$isAdmin && !$isOwner) {
        die("คุณไม่มีสิทธิ์ในการแก้ไขคอร์สเรียนนี้");
    }

    // ดึงหมวดหมู่
    $categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY category_id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // ดึงรายชื่อผู้สอน
    $instructors = $pdo->query("SELECT user_id, first_name, last_name, username FROM users WHERE role IN ('admin', 'instructor') ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error_msg = '';

// 2. จัดการบันทึกการแก้ไขข้อมูล (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 1);
    
    // ผู้สอน
    $instructor_id = $isAdmin && isset($_POST['instructor_id']) 
        ? (int)$_POST['instructor_id'] 
        : (int)$course['instructor_id'];

    $price = $course['price'] ?? 0.00;
    $thumbnail_name = $_POST['old_thumbnail'] ?? $course['thumbnail_url']; 

    if (empty($title) || empty($description)) {
        $error_msg = "กรุณากรอกชื่อคอร์สและรายละเอียดให้ครบถ้วน";
    } else {
        // อัปโหลดรูปใหม่ถ้ามี
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === 0) {
            $upload_path = "uploads/";
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES["thumbnail_file"]["name"], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_ext)) {
                $new_filename = "course_" . $course_id . "_" . time() . "." . $file_extension;
                $target_file = $upload_path . $new_filename;

                if (move_uploaded_file($_FILES["thumbnail_file"]["tmp_name"], $target_file)) {
                    // ลบไฟล์เก่าถ้าไม่ใช่ไฟล์เริ่มต้น
                    $defaultFiles = ['pic_default.jpg', 'logo.png', 'logo.jpg', 'pom.jpg', 'pom2.jpg'];
                    if (!empty($course['thumbnail_url']) && !in_array($course['thumbnail_url'], $defaultFiles)) {
                        $oldPath = $upload_path . $course['thumbnail_url'];
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $thumbnail_name = $new_filename; 
                }
            } else {
                $error_msg = "ไฟล์รูปภาพไม่ถูกต้อง (อนุญาตเฉพาะ JPG, PNG, GIF, WebP)";
            }
        }

        if (empty($error_msg)) {
            try {
                $sql = "UPDATE courses SET title = ?, description = ?, price = ?, instructor_id = ?, category_id = ?, thumbnail_url = ? WHERE course_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $description, $price, $instructor_id, $category_id, $thumbnail_name, $course_id]);

                header("Location: courses.php?id=" . $course_id);
                exit();
            } catch (PDOException $e) {
                $error_msg = 'เกิดข้อผิดพลาดในการอัปเดต: ' . $e->getMessage();
            }
        }
    }
}
?>

<?php include 'header.php'; ?>

<main class="max-w-2xl mx-auto py-8 sm:py-12 px-4 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="courses.php?id=<?= $course_id ?>" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> กลับไปที่คอร์ส
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">แก้ไขคอร์สเรียน</span>
    </div>

    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-200">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 sm:p-8 text-white text-center">
            <h1 class="text-2xl font-bold">แก้ไขข้อมูลคอร์สเรียน</h1>
            <span class="inline-block mt-2 px-3 py-1 bg-white/20 text-white text-xs font-semibold rounded-full">
                COURSE ID: #<?= $course['course_id']; ?>
            </span>
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
                <label class="block text-slate-700 text-sm font-semibold mb-1.5">ชื่อคอร์สเรียน <span class="text-red-500">*</span></label>
                <input type="text" name="title" required 
                       value="<?= htmlspecialchars($course['title']); ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
            </div>

            <!-- Description -->
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-1.5">รายละเอียดคอร์ส <span class="text-red-500">*</span></label>
                <textarea name="description" rows="4" required 
                          class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition resize-none"><?= htmlspecialchars($course['description']); ?></textarea>
            </div>

            <!-- Category & Instructor -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">หมวดหมู่</label>
                    <select name="category_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $course['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">ผู้สอน (Instructor)</label>
                    <?php if ($isAdmin): ?>
                        <select name="instructor_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                            <?php foreach ($instructors as $inst): ?>
                                <option value="<?= $inst['user_id'] ?>" <?= $course['instructor_id'] == $inst['user_id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($inst['first_name'] . ' ' . $inst['last_name']) ?> (@<?= htmlspecialchars($inst['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" readonly 
                               value="<?= htmlspecialchars($_SESSION['username']) ?>"
                               class="w-full px-4 py-3 border border-slate-200 rounded-2xl bg-slate-100 text-slate-500 text-sm">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thumbnail Upload -->
            <div>
                <label class="block text-slate-700 text-sm font-semibold mb-1.5">รูปภาพหน้าปกคอร์ส</label>
                <div class="flex flex-col sm:flex-row items-center gap-4 p-4 border border-slate-200 rounded-2xl bg-slate-50">
                    <div class="w-36 h-24 bg-white rounded-xl border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                        <?php 
                            $thumbPath = 'uploads/' . $course['thumbnail_url'];
                            $thumbImg = (!empty($course['thumbnail_url']) && file_exists($thumbPath))
                                ? 'uploads/' . htmlspecialchars($course['thumbnail_url'])
                                : 'image/pic_default.jpg';
                        ?>
                        <img id="preview_img" src="<?= $thumbImg ?>" class="w-full h-full object-cover" onerror="this.src='image/pic_default.jpg'">
                    </div>
                    <div class="flex-1 w-full text-left">
                        <input type="file" name="thumbnail_file" id="thumbnail_file" accept="image/*"
                               onchange="previewImage(event)"
                               class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                        <p class="mt-1.5 text-xs text-slate-400">อัปโหลดไฟล์ใหม่เพื่อเปลี่ยนรูปหน้าปก (JPG, PNG, WebP)</p>
                        <input type="hidden" name="old_thumbnail" value="<?= htmlspecialchars($course['thumbnail_url'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 flex gap-3">
                <a href="courses.php?id=<?= $course_id ?>" 
                   class="w-1/3 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-2xl transition text-center text-sm">
                    ยกเลิก
                </a>
                <button type="submit" 
                        class="w-2/3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-2xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>บันทึกการแก้ไข</span>
                </button>
            </div>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function() {
        const output = document.getElementById('preview_img');
        output.src = reader.result;
    };
    if (event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}
</script>