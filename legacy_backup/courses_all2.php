<?php 
session_start();
require 'connection/connection.php'; 
if(!isset($_SESSION['username'])){
    header('Location: login.php');
    exit();
}

// 1. ดึงข้อมูลเตรียมไว้ก่อน (Logic การ Search เดิมของคุณ)
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search_query) {
    // เพิ่ม c.thumbnail_url เข้าไปใน SELECT
    $stmt = $pdo->prepare("SELECT c.*, c.thumbnail_url, u.first_name, u.last_name FROM courses c 
                           JOIN users u ON c.instructor_id = u.user_id 
                           WHERE c.title LIKE ? OR c.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?");
                           
    $search_term = "%{$search_query}%";
    $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    $courses = $stmt->fetchAll();
} else {
    // กรณีไม่ได้ search ให้ดึงปกติ และเพิ่ม c.thumbnail_url เช่นกัน
    $stmt = $pdo->query("SELECT c.*, c.thumbnail_url, u.first_name, u.last_name FROM courses c 
                         JOIN users u ON c.instructor_id = u.user_id");
    $courses = $stmt->fetchAll();
}

// Pagination setup
$items_per_page = 9;
$total_courses = count($courses);
$total_pages = ceil($total_courses / $items_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;
$courses = array_slice($courses, $offset, $items_per_page);

// 2. เมื่อได้ข้อมูล $courses มาแล้ว ค่อยมาเช็คว่าเป็น AJAX หรือเปล่า
if (isset($_GET['ajax'])) {
    foreach ($courses as $course) {
        echo '<div class="course-card">';
        
        // กำหนดพาธของรูปภาพ
        $thumbnailPath = 'uploads/' . $course['thumbnail_url'];
        
        // เช็ค 2 ชั้น: 1. มีชื่อไฟล์ใน DB ไหม 2. มีไฟล์จริงในโฟลเดอร์ไหม
        if (!empty($course['thumbnail_url']) && file_exists($thumbnailPath)) {
            $thumbnail = 'uploads/' . htmlspecialchars($course['thumbnail_url']);
            echo '<div class="course-thumbnail" style="width: 100%; height: 180px; overflow: hidden; border-radius: 8px; margin-bottom: 15px;">';
            echo '<img src="' . $thumbnail . '" alt="' . htmlspecialchars($course['title']) . '" style="width: 100%; height: 100%; object-fit: cover;">';
            echo '</div>';
        } else {
            // กรณีไม่มีรูปภาพ หรือรูปภาพเสีย (Optional: ใส่ Margin เพื่อความสวยงาม)
            echo '<div style="padding-top: 10px;"></div>'; 
        }

        echo '<span class="instructor-label">👨‍🏫 ' . htmlspecialchars($course['first_name'] . " " . $course['last_name']) . '</span>';
        echo '<h3 style="margin-top: 10px; font-size: 1.2rem;">' . htmlspecialchars($course['title']) . '</h3>';
        echo '<p style="color: #666; font-size: 0.9rem; line-height: 1.6; height: 3em; overflow: hidden;">' . mb_strimwidth(htmlspecialchars($course['description']), 0, 80, "...") . '</p>';
        echo '<div class="price-box" style="color: #28a745; font-weight: bold; font-size: 1.1rem; margin: 10px 0;">' . number_format($course['price'], 2) . ' บาท</div>';
        
        // ปุ่มกด
        echo '<a href="courses.php?id='.$course['course_id'].'" class="btn" style="text-decoration: none; display: block; text-align: center; margin-bottom: 8px; padding: 10px; border-radius: 8px; background-color: #0061f2; color: white; font-weight: bold;">เริ่มเรียนเลย</a>';
        echo '<a href="edit_course.php?id='.$course['course_id'].'" class="btn-edit" style="text-decoration: none; display: block; text-align: center; padding: 10px; border-radius: 8px; background-color: #ffb020; color: white; font-weight: bold;">แก้ไข</a>';
        // เพิ่มปุ่มลบต่อจากปุ่มแก้ไข (ในไฟล์เดิมของคุณ)
echo '<a href="delete_course.php?id='.$course['course_id'].'" 
         class="btn-delete" 
         style="text-decoration: none; display: block; text-align: center; padding: 10px; border-radius: 8px; background-color: #dc3545; color: white; font-weight: bold; margin-top: 8px;" 
         onclick="return confirm(\'คุณแน่ใจหรือไม่ว่าต้องการลบคอร์สเรียนนี้?\')">ลบข้อมูล</a>';
        echo '</div>';
    }

    // --- ส่วนของ Pagination ---
    if ($total_pages > 1) {
        echo '<div class="pagination"><ul>';
        
        if ($current_page > 1) {
            echo '<li><a href="?search='.urlencode($search_query).'&page='.($current_page - 1).'">&laquo;</a></li>';
        }

        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i === $current_page) ? 'active' : '';
            echo '<li class="'.$active.'"><a href="?search='.urlencode($search_query).'&page='.$i.'">'.$i.'</a></li>';
        }

        if ($current_page < $total_pages) {
            echo '<li><a href="?search='.urlencode($search_query).'&page='.($current_page + 1).'">&raquo;</a></li>';
        }

        echo '</ul></div>';
    }
    exit;
}

?>
  
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Learning System</title>
    <link rel="stylesheet" href="css/header.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- <link rel="stylesheet" href="css/style.css"> -->
    <link rel="stylesheet" href="css/pagination.css">
   <link rel="stylesheet" href="css/courses.css"> 
    <!-- <link rel="stylesheet" href="css/styles.css"> -->

    <style>
          /* จัดการเรื่องขอบข้าง (Padding) ให้ยืดหยุ่น */
    .content {
        max-width: 1200px;
        /* จำกัดความกว้างสูงสุด */
        margin: 0 auto;
        /* จัดกึ่งกลางหน้าจอ */
        padding: 0 20px;
        /* กันเนื้อหาชิดขอบจอในมือถือ */
    }

    /* หน้าจอคอมพิวเตอร์ขนาดใหญ่ ให้ขยับเข้ามาอีกนิดตามความต้องการเดิม */
    @media (min-width: 1200px) {
        .content {
            padding: 0 50px;
        }
    }

    .wrapper {
        max-width: 1200px;
        margin: 40px auto;
        padding: 50px;
        width: 100%;

        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.05);
        /* เส้นขอบบางมาก */
        border-radius: 16px;

        /* เพิ่มเงาละมุน */
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05),
            0 4px 10px rgba(0, 0, 0, 0.03);
    }

    .search-form {
        display: flex;
        justify-content: center;
        margin: 20px 0;

    }

    .search-form input[type="text"] {
        flex: 1;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        margin-right: 10px;
    }

    .search-form input[type="text"]:hover {
        flex: 1;
        padding: 10px;
        outline: 2px solid #ccccccb9;
        ;
        border-radius: 5px;
        margin-right: 10px;
    }

    .search-form input[type="text"]:focus {
        flex: 1;
        padding: 10px;
        border: 2px solid #add8e6;
        /* เปลี่ยนสีเส้นขอบเป็นสีฟ้าอ่อน */
        border-radius: 5px;
        margin-right: 10px;
        outline: none;

        /* ส่วนที่เพิ่มเข้ามา */
        background-color: #f0f8ff;
        /* สีพื้นหลังฟ้าอ่อน (AliceBlue) */
        box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
        /* เงาสีเทาบางๆ */
        /* transition: all 0.3s ease; เพิ่มความนุ่มนวลเวลาโฟกัส */
    }

    .search-form button {
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        background-color: #007bff;
        color: white;
        cursor: pointer;
    }

    .search-form button:hover {
        background-color: #0056b3;
    }

    .btn-warning-gradient {
        /* ใช้สีโทนเหลืองทอง-ส้ม เพื่อให้ดูเป็น Warning ที่ทันสมัย */
        background: linear-gradient(135deg, #ffca2c 0%, #fd7e14 100%);
        box-shadow: 0 4px 12px rgba(253, 126, 20, 0.3);

        /* ตกแต่งเพิ่มเติมเพื่อให้ดูเป็นปุ่ม */
        border: none;
        color: #fff;
        /* ใช้สีขาวจะดูพรีเมียมกว่า หรือใช้ #212529 ถ้าอยากได้ตัวอักษรเข้ม */
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom:10px;
    }

    .btn-warning-gradient:hover {
        /* เอฟเฟกต์ตอนชี้ให้เงามันชัดขึ้นและขยับขึ้นเล็กน้อย */
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(253, 126, 20, 0.4);
        background: linear-gradient(135deg, #ffd452 0%, #ff8c2f 100%);
    }

     /* สไตล์ปุ่มลบแดงอ่อนแบบ Gradient */
    .btn-danger-gradient {
        background: linear-gradient(135deg, #ff8a8a 0%, #f44336 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(244, 67, 54, 0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        margin-top: 5px;
        width: 100%;
        /* หรือปรับตามความเหมาะสม */
    }

    .btn-danger-gradient:hover {
        background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(244, 67, 54, 0.3);
    }

    .btn-danger-gradient:active {
        transform: translateY(0);
    }

    .btn-danger-gradient strong {
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    @media screen and (min-width: 1024px) {
        .btn2 {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            width: 20%;
            height: 65px;
        }

        .b2 {
            margin-top: 15px;
            margin-right: 55px;

        }

    }
/* ค้นหาส่วนนี้ในโค้ดของคุณแล้วแก้ไข */
.course-card {
    display: flex;
    flex-direction: column;
    height: fit-content; /* แก้ไขจาก 100% เป็น fit-content */
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #eee;
    transition: transform 0.2s ease;
    /* เพิ่มส่วนนี้เพื่อให้การจัดวางในแถวดูสมดุล */
    align-self: start; 
}
</style>
</head>

<body>

  <?php 
    include 'header.php'; //header.php - E-Learning System Header
    ?>

    <!-- Hero Section
    <section class="hero">
        <h1>Welcome to E-Learning System</h1>
        <p>Learn at your own pace with our comprehensive online courses</p>
        <a href="courses.php" class="cta-button">Explore Courses</a>
    </section> -->

    <!-- Features Section -->
    <!-- <section class="features">
        <div class="feature-box">
            <h3>📚 Quality Content</h3>
            <p>Access high-quality educational material from expert instructors.</p>
        </div>
        <div class="feature-box">
            <h3>🎯 Self-Paced Learning</h3>
            <p>Learn at your own speed with flexible scheduling.</p>
        </div>
        <div class="feature-box">
            <h3>🏆 Certifications</h3>
            <p>Earn recognized certificates upon course completion.</p>
        </div>
    </section> -->

    <div class="main-wrapper">
        <div class="section-header content">
            <h2 style="text-align: center; font-size:28px;"><strong>คอร์สเรียนแนะนำ</strong></h2>
            <?php
        if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
            ?>
            <!-- <div style="display: flex; justify-content: flex-end; margin-bottom: 15px; width:20%; height:65px;"> -->
            <button onclick="window.location.href='add-course.php'" class="btn btn-primary btn2">
                    <b class="b2" style="margin-top:10px;">เพิ่มข้อมูล</b></button>

            <!-- </div> -->
            <?php 
        }
        ?>
            <form method="GET" action="" class="search-form">
                <input type="text" name="search" placeholder="Search courses..."
                    value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fa fa-search"></i> Search</button>
            </form>

        </div>

        <div id="course-list-container" class="course-grid content wrapper">
            <?php foreach ($courses as $course): ?>
            <div class="course-card">
    <?php 
    $thumbnailPath = 'uploads/' . $course['thumbnail_url'];
    // ถ้ามีรูป ให้แสดง Div Thumbnail
    if (!empty($course['thumbnail_url']) && file_exists($thumbnailPath)): 
        $thumbnail = 'uploads/' . htmlspecialchars($course['thumbnail_url']);
    ?>
        <div class="course-thumbnail" style="width: 100%; height: 180px; overflow: hidden; border-radius: 8px; margin-bottom: 15px;">
            <img src="<?php echo $thumbnail; ?>" alt="<?php echo htmlspecialchars($course['title']); ?>"
                 style="width: 100%; height: 100%; object-fit: cover;">
        </div>
    <?php endif; ?>

                <span class="instructor-label">
                    👨‍🏫 <?php echo htmlspecialchars($course['first_name'] . " " . $course['last_name']); ?>
                </span>

                <h3><?php echo htmlspecialchars($course['title']); ?></h3>

                <p style="color: #666; font-size: 0.9rem; line-height: 1.6;">
                    <?php echo mb_strimwidth(htmlspecialchars($course['description']), 0, 100, "..."); ?>
                </p>

                <div class="price-box">
                    <?php echo number_format($course['price'], 2); ?> บาท
                </div>

                <a href="courses.php?id=<?php echo $course['course_id']; ?>" class="btn"
                    style="text-decoration: none; display: block; text-align: center; margin-top: 15px; padding: 12px; border-radius: 10px; color: white; font-weight: bold; margin-bottom:10px;">
                    เริ่มเรียนเลย
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
                ?>
                <button onclick="window.location.href='edit-course.php?course_id=<?php echo $course['course_id']; ?>'"
                    class="btn-warning-gradient">
                    <strong>แก้ไข</strong>
                </button>
                <button type="button" onclick="confirmDelete(<?php echo $course['course_id']; ?>)"
                    class="btn-danger-gradient">
                    <strong>ลบข้อมูล</strong>
                </button>

                <script>
                function confirmDelete(courseId) {
                    Swal.fire({
                        title: 'ยืนยันการลบ?',
                        text: "คุณจะไม่สามารถกู้คืนคอร์สเรียนนี้ได้!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#f44336',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'ใช่, ลบเลย!',
                        cancelButtonText: 'ยกเลิก'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // ส่งไปที่ไฟล์ delete_course.php (เช็คชื่อไฟล์ให้ตรงกับที่คุณสร้างจริง)
                            window.location.href = 'delete-course.php?id=' + courseId;
                        }
                    })
                }
                </script>

                <?php } ?>
            </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="width: 100%; grid-column: 1 / -1; margin-top: 20px;">
                <ul>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="<?php echo $i === $current_page ? 'active' : ''; ?>">
                        <a
                            href="?search=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php 
    include 'footer.php'; //footer.php - E-Learning System Footer
    ?>

    <script>
    document.querySelector('.search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const searchParams = new URLSearchParams(new FormData(this));

        // เติม &ajax=1 เพื่อให้ PHP ส่งกลับเฉพาะตัวการ์ด
        fetch('courses_all.php?' + searchParams.toString() + '&ajax=1')
            .then(response => response.text())
            .then(data => {
                document.getElementById('course-list-container').innerHTML = data;
            });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>