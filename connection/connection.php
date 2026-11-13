<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = '192.168.100.55';      // หรือ IP ของ Server กรมฯ
$db   = 'elearning_db';    // ชื่อฐานข้อมูลที่คุณจะสร้าง
$user = 'root';          // username ของ database
$pass = 'Data#1@DP!';              // password ของ database
$charset = 'utf8mb4';    // รองรับภาษาไทยสมบูรณ์แบบ

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ให้แจ้งเตือนเมื่อเกิด Error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ให้ดึงข้อมูลออกมาเป็น Array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลอง Prepared Statement เพื่อความปลอดภัย
];

try {
     // 1. สร้างตัวแปร $pdo เพื่อให้หน้า header.php และหน้าอื่นๆ ใช้งานได้
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // 2. สร้างตัวแปร $conn มารับค่าเดียวกัน เผื่อมีบางหน้าอ้างอิงชื่อนี้ ระบบจะได้ไม่พัง
     $conn = $pdo;
     
} catch (\PDOException $e) {
     // หากเชื่อมต่อไม่ได้ ให้แสดงข้อความ Error
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>