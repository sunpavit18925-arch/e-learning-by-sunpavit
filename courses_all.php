<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php'; 

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// 1. ดึงข้อมูลหมวดหมู่ทั้งหมด
$categories = [];
try {
    $cat_stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY category_id ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// 2. Logic การ Search และ กรองหมวดหมู่
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

$where_clauses = [];
$params = [];

if (!empty($search_query)) {
    $where_clauses[] = "(c.title LIKE ? OR c.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_term = "%{$search_query}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($selected_category > 0) {
    $where_clauses[] = "c.category_id = ?";
    $params[] = $selected_category;
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// นับจำนวนทั้งหมดเพื่อทำ Pagination
$count_sql = "SELECT COUNT(*) FROM courses c 
              JOIN users u ON c.instructor_id = u.user_id 
              {$where_sql}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_courses = (int)$count_stmt->fetchColumn();

// Setup Pagination
$items_per_page = 9;
$total_pages = ceil($total_courses / $items_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($total_pages > 0 && $current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $items_per_page;

// ดึงข้อมูล Course ตามเงื่อนไขและ Page
$query_sql = "SELECT c.*, cat.name AS category_name, u.first_name, u.last_name 
              FROM courses c 
              JOIN users u ON c.instructor_id = u.user_id 
              LEFT JOIN categories cat ON c.category_id = cat.category_id
              {$where_sql}
              ORDER BY c.course_id DESC 
              LIMIT {$items_per_page} OFFSET {$offset}";

$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบสิทธิ์ Admin
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// ฟังก์ชันเรนเดอร์การ์ดคอร์ส (ใช้ร่วมกันทั้งหน้าปกติและ AJAX)
function renderCourseCard($course, $isAdmin) {
    $thumbnailPath = 'uploads/' . $course['thumbnail_url'];
    $thumbnail = (!empty($course['thumbnail_url']) && file_exists($thumbnailPath))
        ? 'uploads/' . htmlspecialchars($course['thumbnail_url'])
        : 'image/pic_default.jpg';
    
    $categoryName = !empty($course['category_name']) ? htmlspecialchars($course['category_name']) : 'ทั่วไป';
    $title = htmlspecialchars($course['title']);
    $instructor = htmlspecialchars($course['first_name'] . ' ' . $course['last_name']);
    $description = htmlspecialchars($course['description']);
    $shortDesc = mb_strimwidth($description, 0, 110, "...");
    $courseId = (int)$course['course_id'];

    ob_start();
    ?>
    <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200 flex flex-col group">
        <!-- Thumbnail -->
        <div class="relative w-full h-48 overflow-hidden bg-slate-100">
            <img src="<?= $thumbnail ?>" alt="<?= $title ?>" 
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                 onerror="this.src='image/pic_default.jpg'">
            <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur-md text-indigo-300 text-xs font-semibold px-3 py-1 rounded-full border border-slate-700">
                <?= $categoryName ?>
            </span>
        </div>

        <!-- Content -->
        <div class="p-5 flex-1 flex flex-col">
            <div class="flex items-center text-xs text-slate-500 mb-2">
                <i class="fa-solid fa-chalkboard-user mr-1.5 text-indigo-500"></i>
                <span class="font-medium truncate"><?= $instructor ?></span>
            </div>

            <h3 class="text-lg font-bold text-slate-800 line-clamp-1 mb-2 group-hover:text-indigo-600 transition" title="<?= $title ?>">
                <?= $title ?>
            </h3>

            <p class="text-slate-500 text-sm leading-relaxed mb-4 flex-1 line-clamp-3">
                <?= $shortDesc ?>
            </p>

            <!-- Buttons -->
            <div class="pt-3 border-t border-slate-100 space-y-2 mt-auto">
                <a href="courses.php?id=<?= $courseId ?>" 
                   class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-xl text-center flex items-center justify-center transition shadow-sm hover:shadow-md">
                    <span>เริ่มเรียนรู้</span>
                    <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                </a>

                <?php if ($isAdmin): ?>
                <div class="grid grid-cols-2 gap-2 pt-1">
                    <a href="edit-course.php?course_id=<?= $courseId ?>" 
                       class="py-2 px-3 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 font-medium text-xs rounded-xl text-center transition flex items-center justify-center">
                        <i class="fa-solid fa-pen-to-square mr-1.5"></i> แก้ไข
                    </a>
                    <button type="button" onclick="confirmDelete(<?= $courseId ?>)" 
                            class="py-2 px-3 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-medium text-xs rounded-xl text-center transition flex items-center justify-center">
                        <i class="fa-regular fa-trash-can mr-1.5"></i> ลบ
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 3. ตรวจสอบ AJAX Request
if (isset($_GET['ajax'])) {
    if (empty($courses)) {
        echo '<div class="col-span-full py-16 text-center">';
        echo '<div class="text-5xl mb-3">🔍</div>';
        echo '<h3 class="text-xl font-bold text-slate-700 mb-1">ไม่พบคอร์สเรียนที่ค้นหา</h3>';
        echo '<p class="text-slate-400 text-sm">ลองเปลี่ยนคำค้นหาหรือเลือกหมวดหมู่อื่นดูอีกครั้ง</p>';
        echo '</div>';
    } else {
        foreach ($courses as $course) {
            echo renderCourseCard($course, $isAdmin);
        }
    }

    // Pagination AJAX
    if ($total_pages > 1) {
        echo '<div class="col-span-full mt-8 flex justify-center">';
        echo '<nav class="flex items-center space-x-1">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $activeClass = ($i === $current_page) 
                ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-200' 
                : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200';
            echo '<a href="?search=' . urlencode($search_query) . '&category=' . $selected_category . '&page=' . $i . '" ';
            echo '   class="w-10 h-10 flex items-center justify-center rounded-xl text-sm transition ' . $activeClass . '">';
            echo $i;
            echo '</a>';
        }
        echo '</nav>';
        echo '</div>';
    }
    exit;
}
?>

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-10 w-full flex-1">
    <!-- Header & Action Bar -->
    <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight">คอร์สเรียนทั้งหมด</h1>
            <p class="text-slate-500 mt-1">เลือกเรียนรู้บทเรียนที่น่าสนใจจากวิทยากรผู้เชี่ยวชาญ</p>
        </div>

        <?php if ($isAdmin): ?>
        <div>
            <a href="add-course.php" 
               class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-3 rounded-2xl shadow-lg shadow-indigo-600/30 transition hover:-translate-y-0.5">
                <i class="fa-solid fa-plus-circle mr-2 text-lg"></i>
                <span>เพิ่มคอร์สเรียนใหม่</span>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-white p-4 sm:p-6 rounded-3xl border border-slate-200 shadow-sm mb-8 space-y-4">
        <form id="searchFilterForm" method="GET" action="" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" id="searchInput" 
                       placeholder="ค้นหาชื่อคอร์ส, รายละเอียด, หรือชื่อผู้สอน..."
                       value="<?= htmlspecialchars($search_query) ?>"
                       class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white text-slate-800 text-sm transition">
            </div>
            
            <input type="hidden" name="category" id="categoryInput" value="<?= $selected_category ?>">

            <button type="submit" 
                    class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-2xl font-semibold text-sm transition flex items-center justify-center shrink-0">
                <i class="fa-solid fa-search mr-2"></i> ค้นหา
            </button>
            <?php if (!empty($search_query) || $selected_category > 0): ?>
                <a href="courses_all.php" 
                   class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-3 rounded-2xl font-medium text-sm transition flex items-center justify-center shrink-0"
                   title="ล้างการค้นหา">
                    <i class="fa-solid fa-rotate-left mr-1.5"></i> รีเซ็ต
                </a>
            <?php endif; ?>
        </form>

        <!-- Category Pills -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-sm">
            <span class="text-slate-400 text-xs font-semibold uppercase shrink-0 mr-1">หมวดหมู่:</span>
            
            <button type="button" onclick="setCategoryFilter(0)"
                    class="category-pill px-4 py-1.5 rounded-full text-xs font-semibold transition shrink-0 <?= $selected_category === 0 ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-300' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' ?>">
                ทั้งหมด (<?= $total_courses ?>)
            </button>

            <?php foreach ($categories as $cat): ?>
                <button type="button" onclick="setCategoryFilter(<?= $cat['category_id'] ?>)"
                        class="category-pill px-4 py-1.5 rounded-full text-xs font-semibold transition shrink-0 <?= $selected_category === (int)$cat['category_id'] ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-300' : 'bg-slate-100 hover:bg-slate-200 text-slate-600' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Course Grid Container -->
    <div id="course-list-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($courses)): ?>
            <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-slate-200">
                <div class="text-5xl mb-3">📚</div>
                <h3 class="text-xl font-bold text-slate-700 mb-1">ไม่พบคอร์สเรียนในขณะนี้</h3>
                <p class="text-slate-400 text-sm">ยังไม่มีข้อมูลคอร์สเรียนในระบบ หรือผลการค้นหาไม่ตรงกับเงื่อนไข</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses as $course): ?>
                <?= renderCourseCard($course, $isAdmin) ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="col-span-full mt-10 flex justify-center">
            <nav class="flex items-center space-x-1.5">
                <?php if ($current_page > 1): ?>
                    <a href="?search=<?= urlencode($search_query) ?>&category=<?= $selected_category ?>&page=<?= $current_page - 1 ?>" 
                       class="w-10 h-10 flex items-center justify-center rounded-xl bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 text-sm transition">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?search=<?= urlencode($search_query) ?>&category=<?= $selected_category ?>&page=<?= $i ?>"
                       class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold transition <?= $i === $current_page ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?search=<?= urlencode($search_query) ?>&category=<?= $selected_category ?>&page=<?= $current_page + 1 ?>" 
                       class="w-10 h-10 flex items-center justify-center rounded-xl bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 text-sm transition">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
// ฟังก์ชันยืนยันการลบคอร์สเรียน
function confirmDelete(courseId) {
    Swal.fire({
        title: 'ยืนยันการลบคอร์สเรียน?',
        text: 'คอร์สเรียนนี้รวมถึงแบบทดสอบและบทเรียนทั้งหมดจะถูกลบถาวร!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ต้องการลบ!',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete-course.php?id=' + courseId;
        }
    });
}

// ตรวจสอบแจ้งเตือนหลังจากลบคอร์สสำเร็จ
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('deleted') === '1') {
    Swal.fire({
        icon: 'success',
        title: 'ลบสำเร็จ!',
        text: 'ลบข้อมูลคอร์สเรียนเรียบร้อยแล้ว',
        timer: 2000,
        showConfirmButton: false
    });
    // ล้าง query string เพื่อความสะอาด
    window.history.replaceState({}, document.title, window.location.pathname);
}

// ระบบ Filter ตามหมวดหมู่
function setCategoryFilter(catId) {
    document.getElementById('categoryInput').value = catId;
    fetchCoursesAJAX();
}

// ระบบค้นหาและโหลดข้อมูลผ่าน AJAX
function fetchCoursesAJAX() {
    const form = document.getElementById('searchFilterForm');
    const params = new URLSearchParams(new FormData(form));
    params.set('ajax', '1');

    const container = document.getElementById('course-list-container');
    container.style.opacity = '0.5';

    fetch('courses_all.php?' + params.toString())
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
            container.style.opacity = '1';
            
            // อัปเดต URL ในบราวเซอร์
            params.delete('ajax');
            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({}, '', newUrl);
        })
        .catch(() => {
            container.style.opacity = '1';
        });
}

document.getElementById('searchFilterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetchCoursesAJAX();
});
</script>