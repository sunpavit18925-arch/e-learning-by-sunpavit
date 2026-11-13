<?php
session_start();
require_once 'connection/connection.php'; 

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid method']);
    exit;
}

// 2. รับค่าและล้างข้อมูล
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$username   = trim($_POST['username'] ?? ''); // ตรวจสอบว่าใน DB มีคอลัมน์นี้แล้ว
$email      = trim($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';

// --- 3. ตรวจสอบค่าว่าง ---
if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'msg' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง']);
    exit;
}

// --- 4. ตรวจสอบรูปแบบอีเมล ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'msg' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

// --- 5. ตรวจสอบความปลอดภัยของรหัสผ่าน ---
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
    echo json_encode(['status' => 'error', 'msg' => 'รหัสผ่านต้องมี 8 ตัวขึ้นไป ประกอบด้วยตัวพิมพ์ใหญ่ พิมพ์เล็ก และตัวเลข']);
    exit;
}

try {
    // --- 6. ตรวจสอบ username หรือ email ซ้ำ ---
    // หมายเหตุ: ถ้าใน DB ไม่มีคอลัมน์ username ให้เอา "username = :u OR" ออก
    $sql = "SELECT user_id FROM users WHERE username = :u OR email = :e LIMIT 1"; 
    $stm = $pdo->prepare($sql);
    $stm->execute([':u' => $username, ':e' => $email]); 

    if ($stm->fetch()) {
        echo json_encode(['status' => 'error', 'msg' => 'ชื่อผู้ใช้งานหรืออีเมลนี้ถูกใช้แล้ว']); 
        exit;
    }

    // --- 7. สร้าง hash และบันทึกข้อมูล ---
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // ตรวจสอบชื่อคอลัมน์ใน SQL ให้ตรงกับในรูปภาพ (user_id, first_name, last_name, email, password_hash, role, created_at)
    $sql = "INSERT INTO users (username, password_hash, first_name, last_name, email, role)
            VALUES (:u, :p, :fname, :lname, :e, 'student')";
    
    $stm = $pdo->prepare($sql);
    $stm->execute([
        ':u'     => $username,
        ':p'     => $hash,
        ':fname' => $first_name,
        ':lname' => $last_name,
        ':e'     => $email
    ]);

    echo json_encode(['status' => 'success', 'msg' => 'ลงทะเบียนสำเร็จ']);

} catch (PDOException $e) {
    // แสดง Error เพื่อการ Debug (ในระบบจริงควรเก็บ log แทนการ echo ออกมา)
    echo json_encode(['status' => 'error', 'msg' => 'Database Error: ' . $e->getMessage()]);
}
exit;