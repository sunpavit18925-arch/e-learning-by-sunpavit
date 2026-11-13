<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';
require_once 'TCPDF-main/tcpdf.php';

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    die("กรุณาเข้าสู่ระบบก่อนใช้งาน");
}

$user_id = (int)$_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($course_id <= 0) {
    die("ไม่พบรหัสคอร์สเรียน");
}

try {
    // 2. ดึงข้อมูลผู้เรียน
    $stmt_user = $pdo->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 3. ดึงชื่อคอร์สเรียน
    $stmt_course = $pdo->prepare("SELECT title FROM courses WHERE course_id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$course) {
        die("ไม่พบข้อมูลผู้ใช้งานหรือคอร์สเรียน");
    }

    // 4. ตรวจสอบสิทธิ์ว่าสอบผ่านหลักสูตรนี้แล้วจริงหรือไม่ (ป้องกันการเข้าถึงตรงโดยไม่สอบ)
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    
    $stmt_check_pass = $pdo->prepare("
        SELECT qa.attempt_id, qa.attempt_date 
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        WHERE qa.user_id = ? AND q.course_id = ? AND qa.is_passed = 1
        ORDER BY qa.attempt_date DESC 
        LIMIT 1
    ");
    $stmt_check_pass->execute([$user_id, $course_id]);
    $passRecord = $stmt_check_pass->fetch(PDO::FETCH_ASSOC);

    if (!$passRecord && !$isAdmin) {
        die("ขออภัย คุณยังไม่ผ่านเกณฑ์การทดสอบสำหรับหลักสูตรนี้ จึงไม่สามารถออกใบประกาศนียบัตรได้");
    }

    $cert_date_ts = $passRecord ? strtotime($passRecord['attempt_date']) : time();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// 5. จัดการวันที่แบบไทย
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

$thai_day = date("j", $cert_date_ts);
$thai_month = $thai_months[(int)date("n", $cert_date_ts)];
$thai_year = date("Y", $cert_date_ts) + 543; // แปลงเป็น พ.ศ.

$full_thai_date = "วันที่ $thai_day $thai_month พ.ศ. $thai_year";

// 6. เตรียมเนื้อหา HTML ของประกาศนียบัตร
$html = '
<style>
    .cert-container { 
        text-align: center; 
        border: 10px double #1e3a8a; 
        padding: 25px; 
    }
    .dept-name { 
        font-size: 24pt; 
        color: #1e3a8a; 
        font-weight: bold; 
    }
    .user-name { 
        font-size: 36pt; 
        color: #0f172a; 
        margin-top: 15px; 
        font-weight: bold;
    }
    .course-text { 
        font-size: 16pt; 
        color: #334155;
        margin-top: 10px; 
    }
    .course-title {
        font-weight: bold; 
        font-size: 20pt;
        color: #1e3a8a;
    }
    .date-text { 
        font-size: 13pt; 
        color: #64748b; 
    }
</style>

<div class="cert-container">
    <br><br><br><br>
    <div class="dept-name">กรมโยธาธิการและผังเมือง</div>
    <p class="course-text">ใบประกาศนียบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า</p>
    
    <div class="user-name">' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</div>
    
    <p class="course-text">ได้ผ่านการทดสอบและสำเร็จหลักสูตรการเรียนรู้ออนไลน์</p>
    <div class="course-title">"' . htmlspecialchars($course['title']) . '"</div>
    
    <br><br>
    <p class="date-text">ให้ไว้ ณ ' . $full_thai_date . '</p>
</div>';

// 7. สร้าง PDF ด้วย TCPDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('DPT E-Learning');
$pdf->SetAuthor('กรมโยธาธิการและผังเมือง');
$pdf->SetTitle('ใบประกาศนียบัตร - ' . $course['title']);

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false, 0);

$pdf->AddPage();

// ใช้ฟอนต์ freeserif ซึ่งรองรับอักขระภาษาไทย
$pdf->SetFont('freeserif', '', 18);
$pdf->writeHTML($html, true, false, true, false, '');

// วางโลโก้กรมฯ
$logo_path = __DIR__ . '/image/logo.jpg'; 
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 133.5, 15, 30, 0, 'JPG'); 
}

// ส่งออกไฟล์ PDF ให้เปิดดูในบราวเซอร์
$pdf->Output('Certificate_' . $user['first_name'] . '.pdf', 'I');
exit;