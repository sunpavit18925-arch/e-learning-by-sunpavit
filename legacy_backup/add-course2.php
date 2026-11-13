<?php
session_start();
require_once 'connection/connection.php'; // ไฟล์นี้สร้างตัวแปร $pdo ไว้

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. รับค่าจาก Form (ไม่ต้องใช้ escape string เพราะใช้ prepare ด้านล่าง)
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $instructor_id = $_POST['instructor_id'];
    $category_id = $_POST['category_id'];
    $thumbnail_url = $_POST['thumbnail_url'];
    
    try {
        // 2. เตรียม SQL ด้วย PDO
        $sql = "INSERT INTO courses (title, description, price, instructor_id, category_id, thumbnail_url, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        // 3. ประมวลผลและส่งค่า (Execute)
        $stmt->execute([$title, $description, $price, $instructor_id, $category_id, $thumbnail_url]);

        echo "<script>
                alert('เพิ่มคอร์สเรียนสำเร็จ!');
                window.location.href='index.php'; 
              </script>";

    } catch (PDOException $e) {
        // เกิดข้อผิดพลาด
        echo "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มคอร์สเรียน - E-Learning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">

    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden w-full max-w-lg">
            
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-8 text-center relative">
                <button type="button" onclick="history.back()" class="absolute left-4 top-4 text-white flex items-center text-sm opacity-80 hover:opacity-100">
                    <span class="mr-1">❮</span> กลับ
                </button>
<!--                 
                <div class="w-24 h-24 bg-white rounded-full mx-auto border-4 border-white shadow-lg flex items-center justify-center overflow-hidden">
                    <img src="" alt="Course Thumbnail" id="preview_top" class="object-cover w-full h-full">
                </div> -->
                <h2 class="text-white text-xl font-bold mt-4">เพิ่มข้อมูลคอร์สเรียน</h2>
            </div>

            <form action="" method="POST" class="p-8 space-y-4">    
                
                <div>
                    <label class="block text-gray-500 text-sm mb-1">ชื่อคอร์ส (Course Title)</label>
                    <input type="text" name="title" required placeholder="เช่น Web Development Boot Camp" class="w-full px-4 py-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50">
                </div>

                <div>
                    <label class="block text-gray-500 text-sm mb-1">รายละเอียด (Description)</label>
                    <textarea name="description" rows="2" required placeholder="อธิบายเนื้อหาคอร์สสั้นๆ" class="w-full px-4 py-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">ราคา (Price)</label>
                        <input type="number" name="price" step="0.01" required placeholder="0.00" class="w-full px-4 py-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm mb-1">หมวดหมู่ (Category ID)</label>
                        <select name="category_id" class="w-full px-4 py-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50">
                            <option value="1">Development</option>
                            <option value="2">Design</option>
                            <option value="3">Marketing</option>
                            <option value="4">Business</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
  <div class="col-span-2">
    <label class="block text-gray-500 text-sm mb-1">อัปโหลดรูปหน้าปก (Thumbnail Upload)</label>
    <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
        <input type="file" 
               name="thumbnail_file" 
               id="thumbnail_input"
               accept="image/*"
               class="w-full px-4 py-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
               onchange="previewFile(this)">
    </div>

    <div class="mt-4">
        <div class="w-40 h-40 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden">
            <img id="preview_img" 
                 src="image/pic_default.jpg" 
                 class="w-full h-full object-cover"
                 onerror="this.src='image/pic_default.jpg'">
        </div>
        <p class="text-xs text-gray-400 mt-2">* รองรับไฟล์รูปภาพ .jpg, .png (ขนาดแนะนำ 1:1 หรือ 4:3)</p>
    </div>
</div>

                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-full mt-6 flex items-center justify-center transition duration-200 shadow-lg shadow-blue-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    บันทึกการเพิ่มข้อมูล
                </button>
            </form>
        </div>
    </div>

<script>
    // กำหนดรูป Default กรณีไม่ได้เลือกไฟล์หรือไฟล์มีปัญหา
const DEFAULT_IMAGE = "image/pic_default.jpg";

function previewFile(input) {
    const preview = document.getElementById('preview_img');
    const previewTop = document.getElementById('preview_top'); // ถ้ามีรูปด้านบน
    const file = input.files[0];
    const reader = new FileReader();

    reader.onloadend = function () {
        // เมื่ออ่านไฟล์เสร็จ ให้นำข้อมูลมาใส่ใน src
        if (preview) preview.src = reader.result;
        if (previewTop) previewTop.src = reader.result;
    }

    if (file) {
        // เริ่มต้นอ่านไฟล์ที่เลือก
        reader.readAsDataURL(file);
    } else {
        // ถ้าผู้ใช้ยกเลิกการเลือกไฟล์ ให้กลับไปใช้รูป Default
        if (preview) preview.src = DEFAULT_IMAGE;
        if (previewTop) previewTop.src = DEFAULT_IMAGE;
    }
}

// ฟังก์ชันรองรับกรณีรูป Default โหลดไม่ขึ้น
function handleImageError(img) {
    img.onerror = null;
    img.src = DEFAULT_IMAGE;
}
</script>
</body>
</html>