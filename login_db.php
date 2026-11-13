<?php
// ป้องกัน PHP Warning ปะปนกับ JSON
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

function sendJson(array $data): void
{
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

// ตรวจสอบ Request Method
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendJson([
        'status' => 'error',
        'msg' => 'Method Not Allowed'
    ]);
}

// ตรวจสอบรูปแบบข้อมูล
if (
    !is_string($_POST['username'] ?? null) ||
    !is_string($_POST['password'] ?? null)
) {
    sendJson([
        'status' => 'error',
        'msg' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
    ]);
}

$username = trim($_POST['username']);
$password = $_POST['password']; // ไม่ตัดช่องว่างในรหัสผ่าน

if ($username === '' || $password === '') {
    sendJson([
        'status' => 'error',
        'msg' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
    ]);
}

try {
    if (!session_start()) {
        throw new RuntimeException('Unable to start session');
    }

    // เชื่อมต่อภายใน try เพื่อจับข้อผิดพลาดได้
    require_once __DIR__ . '/connection/connection.php';

    // ใช้ชื่อ parameter แยกกัน
    $sql = "SELECT user_id, username, password_hash,
                   first_name, last_name, role
            FROM users
            WHERE username = :username OR email = :email
            LIMIT 1";

    $stm = $pdo->prepare($sql);
    $stm->execute([
        ':username' => $username,
        ':email' => $username
    ]);

    $data = $stm->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        sendJson([
            'status' => 'error',
            'msg' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
        ]);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $passwordCorrect = password_verify(
        $password,
        $data['password_hash']
    );

    // บันทึกผลการตรวจสอบรหัสผ่าน
    $logSql = "INSERT INTO login_logs
                   (user_id, ip_address, user_agent, status)
               VALUES
                   (:uid, :ip, :ua, :status)";

    $logStm = $pdo->prepare($logSql);
    $logStm->execute([
        ':uid' => $data['user_id'],
        ':ip' => $ip,
        ':ua' => $ua,
        ':status' => $passwordCorrect ? 'success' : 'failed'
    ]);

    if (!$passwordCorrect) {
        sendJson([
            'status' => 'error',
            'msg' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
        ]);
    }

    // เปลี่ยน Session ID เมื่อเข้าสู่ระบบสำเร็จ
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate session ID');
    }

    $_SESSION['user_id'] = $data['user_id'];
    $_SESSION['username'] = $data['username'];
    $_SESSION['full_name'] = trim(
        ($data['first_name'] ?? '') . ' ' .
        ($data['last_name'] ?? '')
    );
    $_SESSION['role'] = $data['role'];

    session_write_close();

    sendJson([
        'status' => 'success',
        'msg' => 'เข้าสู่ระบบสำเร็จ',
        'redirect' => 'index.php'
    ]);

} catch (Throwable $e) {
    // รายละเอียดข้อผิดพลาดเก็บใน PHP error log
    error_log('login_db.php: ' . $e->getMessage());

    sendJson([
        'status' => 'error',
        'msg' => 'ระบบเข้าสู่ระบบขัดข้อง กรุณาติดต่อผู้ดูแลระบบ'
    ]);
}