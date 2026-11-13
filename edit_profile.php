<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';
// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = (int)$_SESSION['user_id'];
$success_msg = '';
$error_msg = '';
// 1. ดึงข้อมูลปัจจุบันของผู้ใช้
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :id');
    $stmt->execute(['id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) {
        die('ไม่พบข้อมูลผู้ใช้');
    }
} catch (PDOException $e) {
    die('เกิดข้อผิดพลาด: ' . $e->getMessage());
}
// 2. จัดการเมื่อกดบันทึก (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $new_pass   = $_POST['new_password'] ?? '';
    $image_name = $user_data['image'];
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_msg = 'กรุณากรอกชื่อ นามสกุล และอีเมลให้ครบถ้วน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'รูปแบบอีเมลไม่ถูกต้อง';
    } else {
        try {
            // ตรวจสอบอีเมลซ้ำกับผู้อื่นหรือไม่
            $stmt_email = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt_email->execute([$email, $user_id]);
            if ($stmt_email->fetch()) {
                $error_msg = 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว';
            } else {
                // จัดการอัปโหลดรูปภาพใหม่
                if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $filename = $_FILES['image']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                        $upload_path = 'uploads/' . $new_filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                            // ลบรูปเดิมที่ไม่ใช่ default
                            $defaultFiles = ['pic_default.jpg', 'logo.png', 'logo.jpg', 'pom.jpg', 'pom2.jpg'];
                            if (!empty($user_data['image']) && !in_array($user_data['image'], $defaultFiles)) {
                                $oldPath = 'uploads/' . $user_data['image'];
                                if (file_exists($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                            $image_name = $new_filename;
                        }
                    } else {
                        $error_msg = 'รูปแบบไฟล์รูปภาพไม่ถูกต้อง (รองรับเฉพาะ jpg, png, gif, webp)';
                    }
                }
                if (empty($error_msg)) {
                    // ถ้ามีการเปลี่ยนรหัสผ่าน
                    if (!empty($new_pass)) {
                        if (strlen($new_pass) < 6) {
                            $error_msg = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
                        } else {
                            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                            $sql = 'UPDATE users SET first_name = :fname, last_name = :lname, email = :email, image = :img, password_hash = :p WHERE user_id = :id';
                            $stmt_up = $pdo->prepare($sql);
                            $stmt_up->execute([
                                'fname' => $first_name,
                                'lname' => $last_name,
                                'email' => $email,
                                'img'   => $image_name,
                                'p'     => $hash,
                                'id'    => $user_id
                            ]);
                        }
                    } else {
                        $sql = 'UPDATE users SET first_name = :fname, last_name = :lname, email = :email, image = :img WHERE user_id = :id';
                        $stmt_up = $pdo->prepare($sql);
                        $stmt_up->execute([
                            'fname' => $first_name,
                            'lname' => $last_name,
                            'email' => $email,
                            'img'   => $image_name,
                            'id'    => $user_id
                        ]);
                    }
                    if (empty($error_msg)) {
                        $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                        $success_msg = 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว!';
                        // รีโหลดข้อมูลล่าสุด
                        $stmt->execute(['id' => $user_id]);
                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }
        } catch (PDOException $e) {
            $error_msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}
?>
<?php
// Keep entered values after a validation error; never re-populate passwords.
$ui_escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$ui_first = $error_msg !== '' ? ($_POST['first_name'] ?? $user_data['first_name']) : $user_data['first_name'];
$ui_last = $error_msg !== '' ? ($_POST['last_name'] ?? $user_data['last_name']) : $user_data['last_name'];
$ui_email = $error_msg !== '' ? ($_POST['email'] ?? $user_data['email']) : $user_data['email'];
$ui_avatar = !empty($user_data['image']) && is_file('uploads/' . $user_data['image']) ? 'uploads/' . $user_data['image'] : '';
$ui_initial = function_exists('mb_substr') ? mb_substr($user_data['first_name'] ?: $user_data['username'], 0, 1, 'UTF-8') : 'U';
?>
<?php include 'header.php'; ?>
<style>
.dpt-edit {
    --ep-ink: #1d2942;
    --ep-muted: #66758c;
    --ep-line: #e6eaf2;
    --ep-accent: #6454dc;
    --ep-soft: #f2efff;
    box-sizing: border-box;
    background: #f5f7fb;
    color: var(--ep-ink);
    font-family: inherit;
    width: 100%;
    flex: 1;
    padding: 30px 24px 56px
}

.dpt-edit * {
    box-sizing: border-box
}

.dpt-edit [hidden] {
    display: none !important
}

.dpt-edit a {
    text-decoration: none
}

.dpt-edit button,
.dpt-edit input {
    font: inherit
}

.dpt-edit button {
    cursor: pointer
}

.dpt-edit svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
    flex-shrink: 0
}

.dpt-edit :is(a, button, input):focus-visible {
    outline: 3px solid #a99cf2;
    outline-offset: 3px
}

.dpt-edit .ep-wrap {
    max-width: 1080px;
    margin: auto
}

.dpt-edit .ep-crumb {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: var(--ep-muted);
    margin-bottom: 22px
}

.dpt-edit .ep-crumb a {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--ep-muted);
    min-height: 32px
}

.dpt-edit .ep-crumb svg {
    width: 16px
}

.dpt-edit .ep-crumb span:last-child {
    color: var(--ep-ink)
}

.dpt-edit .ep-heading {
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px
}

.dpt-edit .ep-heading h1 {
    font-size: clamp(25px, 3vw, 32px);
    line-height: 1.4;
    letter-spacing: -.7px;
    font-weight: 700;
    margin: 0 0 7px
}

.dpt-edit .ep-heading p {
    margin: 0;
    font-size: 14px;
    line-height: 1.8;
    color: var(--ep-muted)
}

.dpt-edit .ep-heading-mark {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    border-radius: 15px;
    background: #eae5ff;
    color: var(--ep-accent)
}

.dpt-edit .ep-layout {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    gap: 24px;
    align-items: start
}

.dpt-edit .ep-sidebar {
    border-radius: 22px;
    overflow: hidden;
    background: white;
    border: 1px solid var(--ep-line)
}

.dpt-edit .ep-profile-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    background: linear-gradient(140deg, #1a2947, #3d386f 70%, #6753ad);
    padding: 30px 22px;
    color: white;
    text-align: center
}

.dpt-edit .ep-profile-card:before {
    content: '';
    position: absolute;
    top: -80px;
    right: -75px;
    width: 210px;
    height: 210px;
    border: 35px solid #ffffff08;
    border-radius: 50%;
    z-index: -1
}

.dpt-edit .ep-eyebrow {
    color: #d3cafa;
    letter-spacing: 1.8px;
    font-size: 10px;
    font-weight: 600;
    margin: 0 0 23px
}

.dpt-edit .ep-avatar {
    display: grid;
    place-items: center;
    width: 110px;
    height: 110px;
    border-radius: 30px;
    background: #8a7cdd;
    border: 4px solid #ffffff30;
    margin: 0 auto 18px;
    overflow: hidden;
    font-size: 42px;
    font-weight: 700
}

.dpt-edit .ep-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover
}

.dpt-edit .ep-person-name {
    font-size: 19px;
    line-height: 1.6;
    font-weight: 600;
    margin: 0;
    overflow-wrap: anywhere
}

.dpt-edit .ep-handle {
    color: #d2d5e7;
    font-size: 12px;
    margin: 5px 0 18px;
    overflow-wrap: anywhere
}

.dpt-edit .ep-file {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    border: 0;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap
}

.dpt-edit .ep-profile-card:has(.ep-file:focus-visible) .ep-upload-btn {
    outline: 3px solid #cfc5ff;
    outline-offset: 4px
}

.dpt-edit .ep-upload-btn {
    cursor: pointer;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 15px;
    border: 1px solid #ffffff36;
    background: #ffffff14;
    color: white;
    border-radius: 11px;
    font-size: 12px;
    font-weight: 600
}

.dpt-edit .ep-upload-btn:hover {
    background: #ffffff24
}

.dpt-edit .ep-upload-hint {
    font-size: 10px;
    line-height: 1.8;
    color: #d5d3e8;
    margin: 12px 0 0
}

.dpt-edit .ep-image-status {
    font-size: 11px;
    line-height: 1.7;
    margin: 10px 0 0;
    color: #f1edff;
    overflow-wrap: anywhere
}

.dpt-edit .ep-clear-image {
    border: 0;
    background: none;
    color: #ece8ff;
    font-size: 11px;
    text-decoration: underline;
    padding: 8px;
    min-height: 36px
}

.dpt-edit .ep-upload-error {
    color: #ffe3dc;
    font-size: 12px;
    margin: 10px 0 0;
    line-height: 1.7
}

.dpt-edit .ep-side-content {
    padding: 23px 20px
}

.dpt-edit .ep-side-title {
    font-size: 12px;
    font-weight: 600;
    color: #657087;
    margin: 0 0 15px
}

.dpt-edit .ep-side-nav {
    display: flex;
    flex-direction: column;
    gap: 7px
}

.dpt-edit .ep-side-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border-radius: 11px;
    color: var(--ep-muted);
    font-size: 13px;
    min-height: 44px
}

/*
.dpt-edit .ep-side-nav a:hover {
    background: var(--ep-soft);
    color: var(--ep-accent)
}
*/

/*
.dpt-edit .ep-side-nav a:first-child {
    background: var(--ep-soft);
    color: var(--ep-accent);
    font-weight: 600
} */

.dpt-edit .ep-side-nav svg {
    width: 18px
}

.dpt-edit .ep-side-note {
    font-size: 12px;
    line-height: 1.9;
    color: var(--ep-muted);
    border-top: 1px solid var(--ep-line);
    padding-top: 18px;
    margin: 20px 0 0
}

.dpt-edit .ep-main {
    min-width: 0
}

.dpt-edit .ep-panel {
    background: white;
    border: 1px solid var(--ep-line);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 18px
}

.dpt-edit .ep-panel-heading {
    padding: 25px 28px 0;
    display: flex;
    gap: 13px;
    align-items: center
}

.dpt-edit .ep-section-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: var(--ep-soft);
    color: var(--ep-accent);
    flex-shrink: 0
}

.dpt-edit h2 {
    font-size: 17px;
    line-height: 1.5;
    margin: 0;
    font-weight: 700
}

.dpt-edit .ep-panel-heading p {
    font-size: 12px;
    color: var(--ep-muted);
    margin: 4px 0 0;
    line-height: 1.7
}

.dpt-edit .ep-fields {
    padding: 24px 28px 28px
}

.dpt-edit .ep-field {
    margin-bottom: 20px;
    min-width: 0
}

.dpt-edit .ep-field:last-child {
    margin-bottom: 0
}

.dpt-edit .ep-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 20px
}

.dpt-edit .ep-row .ep-field {
    margin-bottom: 0
}

.dpt-edit label.ep-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 9px;
    color: #344158
}

.dpt-edit .ep-required {
    color: #c75267;
    margin-left: 3px
}

.dpt-edit .ep-input {
    display: block;
    width: 100%;
    height: 49px;
    border: 1px solid #dce2ee;
    border-radius: 11px;
    background: #fcfdff;
    padding: 12px 14px;
    color: var(--ep-ink);
    font-size: 14px;
    transition: border-color .15s, box-shadow .15s;
    min-width: 0
}

.dpt-edit .ep-input::placeholder {
    color: #99a2b1;
    font-size: 13px
}

.dpt-edit .ep-input:focus {
    border-color: #8a7ae7;
    background: white;
    box-shadow: 0 0 0 3px #6454dc12;
    outline: none
}

.dpt-edit .ep-input[readonly] {
    background: #f4f6fa;
    color: #67758a
}

.dpt-edit .ep-hint {
    font-size: 11px;
    line-height: 1.8;
    color: var(--ep-muted);
    margin: 8px 0 0
}

.dpt-edit .ep-input-wrap {
    position: relative
}

.dpt-edit .ep-input-wrap .ep-input {
    padding-right: 56px
}

.dpt-edit .ep-input-icon {
    position: absolute;
    right: 15px;
    top: 15px;
    color: #98a2b6;
    width: 18px;
    height: 18px;
    pointer-events: none
}

.dpt-edit .ep-eye {
    position: absolute;
    right: 5px;
    top: 3px;
    width: 44px;
    height: 43px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #6f7b90
}

.dpt-edit .ep-eye:hover {
    background: var(--ep-soft);
    color: var(--ep-accent)
}

.dpt-edit .ep-password-note {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    background: #f7f8fc;
    padding: 13px 15px;
    border-radius: 11px;
    color: var(--ep-muted);
    font-size: 12px;
    line-height: 1.8;
    margin-bottom: 20px
}

.dpt-edit .ep-password-note svg {
    width: 17px;
    margin-top: 3px;
    color: #8f81cc
}

.dpt-edit .ep-optional {
    font-size: 10px;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 20px;
    background: #f1f3f8;
    color: #6b7690;
    margin-left: 8px;
    vertical-align: middle
}

.dpt-edit .ep-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    background: white;
    padding: 20px 24px;
    border: 1px solid var(--ep-line);
    border-radius: 16px
}

.dpt-edit .ep-save-state {
    font-size: 11px;
    color: var(--ep-muted);
    line-height: 1.8;
    max-width: 180px
}

.dpt-edit .ep-actions-buttons {
    display: flex;
    align-items: center;
    gap: 10px
}

.dpt-edit .ep-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 17px;
    min-height: 46px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 11px;
    border: 1px solid var(--ep-line);
    background: #fff;
    color: #526078;
    line-height: 1.5;
    white-space: nowrap;
    transition: background .15s
}

.dpt-edit .ep-btn:hover {
    background: #f4f5fa
}

.dpt-edit .ep-btn-primary {
    background: var(--ep-accent);
    color: white;
    border-color: var(--ep-accent);
    box-shadow: 0 4px 12px #6454dc20
}

.dpt-edit .ep-btn-primary:hover {
    background: #5544c8
}

.dpt-edit .ep-btn:disabled {
    cursor: wait;
    opacity: .7
}

.dpt-edit .ep-alert {
    padding: 15px 18px;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    border-radius: 13px;
    margin-bottom: 18px;
    font-size: 13px;
    line-height: 1.8;
    border: 1px solid
}

.dpt-edit .ep-alert svg {
    margin-top: 2px
}

.dpt-edit .ep-success {
    background: #edf9f3;
    color: #257856;
    border-color: #c8ead9
}

.dpt-edit .ep-error {
    background: #fff2f3;
    color: #a43e50;
    border-color: #f3cbd1
}

/*
.dpt-edit .ep-side-nav a:hover,
.dpt-edit .ep-side-nav a.is-active {
    background: var(--ep-soft);
    color: var(--ep-accent);
    font-weight: 600;
} */

/* เปลี่ยนสีเมื่อชี้เมาส์ และค้างสีที่เมนูที่คลิกเลือก */
.dpt-edit .ep-side-nav a:hover,
.dpt-edit .ep-side-nav a.is-active {
    background: var(--ep-soft);
    color: var(--ep-accent);
    font-weight: 600;
}

@media(max-width:900px) {
    .dpt-edit .ep-layout {
        grid-template-columns: 240px minmax(0, 1fr);
        gap: 18px
    }

    .dpt-edit .ep-panel-heading {
        padding: 22px 22px 0
    }

    .dpt-edit .ep-fields {
        padding: 22px
    }

    .dpt-edit .ep-actions {
        align-items: flex-start;
        flex-direction: column
    }

    .dpt-edit .ep-actions-buttons {
        width: 100%;
        justify-content: flex-end
    }

    .dpt-edit .ep-save-state {
        max-width: none
    }
}

@media(max-width:640px) {
    .dpt-edit {
        padding: 18px 16px 32px
    }

    .dpt-edit .ep-crumb {
        font-size: 11px;
        gap: 8px;
        margin-bottom: 16px;
        flex-wrap: wrap
    }

    .dpt-edit .ep-heading {
        margin-bottom: 20px
    }

    .dpt-edit .ep-heading h1 {
        font-size: 26px
    }

    .dpt-edit .ep-heading p {
        font-size: 12px
    }

    .dpt-edit .ep-heading-mark {
        display: none
    }

    .dpt-edit .ep-layout {
        grid-template-columns: 1fr;
        gap: 18px
    }

    .dpt-edit .ep-sidebar {
        border-radius: 18px
    }

    .dpt-edit .ep-profile-card {
        padding: 24px 20px
    }

    .dpt-edit .ep-eyebrow {
        margin-bottom: 18px
    }

    .dpt-edit .ep-avatar {
        width: 88px;
        height: 88px;
        border-radius: 24px;
        font-size: 35px;
        margin-bottom: 12px
    }

    .dpt-edit .ep-person-name {
        font-size: 18px
    }

    .dpt-edit .ep-handle {
        margin-bottom: 14px
    }

    .dpt-edit .ep-side-content {
        padding: 14px 16px
    }

    .dpt-edit .ep-side-title,
    .dpt-edit .ep-side-note {
        display: none
    }

    .dpt-edit .ep-side-nav {
        flex-direction: row;
        gap: 8px
    }

    .dpt-edit .ep-side-nav a {
        flex: 1;
        font-size: 12px;
        justify-content: center;
        padding: 10px 8px
    }

    .dpt-edit .ep-panel {
        border-radius: 18px
    }

    .dpt-edit .ep-panel-heading {
        padding: 20px 20px 0
    }

    .dpt-edit .ep-fields {
        padding: 22px 20px
    }

    .dpt-edit .ep-row {
        grid-template-columns: 1fr;
        gap: 20px
    }

    .dpt-edit .ep-input {
        font-size: 16px
    }

    .dpt-edit .ep-actions {
        padding: 17px 18px;
        gap: 12px
    }

    .dpt-edit .ep-actions-buttons {
        gap: 8px
    }

    .dpt-edit .ep-btn {
        padding: 12px;
        font-size: 12px
    }

    .dpt-edit .ep-btn-primary {
        flex: 1
    }

    .dpt-edit .ep-save-state {
        font-size: 11px
    }
}

@media(prefers-reduced-motion:reduce) {
    .dpt-edit * {
        transition: none !important
    }
}
</style>
<main class="dpt-edit">
    <div class="ep-wrap">
        <nav class="ep-crumb" aria-label="เส้นทางนำทาง"><a href="profile.php"><svg viewBox="0 0 24 24"
                    aria-hidden="true">
                    <path d="m10 6-6 6 6 6M4 12h16" />
                </svg>โปรไฟล์ของฉัน</a><span aria-hidden="true">/</span><span
                aria-current="page">แก้ไขข้อมูลส่วนตัว</span></nav>
        <div class="ep-heading">
            <div>
                <h1>แก้ไขข้อมูลส่วนตัว</h1>
                <p>จัดการโปรไฟล์และข้อมูลบัญชีของคุณได้ในที่เดียว</p>
            </div>
            <div class="ep-heading-mark" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m14 5 5 5M4 20l5-1L20 8a2 2 0 0 0-5-5L4 14z" />
                </svg></div>
        </div>
        <form id="ep-form" action="" method="POST" enctype="multipart/form-data">
            <div class="ep-layout">
                <aside class="ep-sidebar" aria-label="รูปโปรไฟล์และเมนู">
                    <div class="ep-profile-card">
                        <p class="ep-eyebrow">YOUR PROFILE</p>
                        <div class="ep-avatar"><img id="ep-preview"
                                <?php if ($ui_avatar !== ''): ?>src="<?= $ui_escape($ui_avatar) ?>" <?php endif; ?>
                                alt="รูปโปรไฟล์ปัจจุบัน" <?= $ui_avatar === '' ? 'hidden' : '' ?>><span id="ep-initial"
                                aria-hidden="true"
                                <?= $ui_avatar !== '' ? 'hidden' : '' ?>><?= $ui_escape($ui_initial) ?></span></div>
                        <p class="ep-person-name">
                            <?= $ui_escape(trim($user_data['first_name'] . ' ' . $user_data['last_name']) ?: $user_data['username']) ?>
                        </p>
                        <p class="ep-handle">@<?= $ui_escape($user_data['username']) ?></p>
                        <label for="ep-image" id="ep-upload-label" class="ep-upload-btn"><svg viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path d="M4 7h3l2-3h6l2 3h3v13H4z" />
                                <circle cx="12" cy="13" r="4" />
                            </svg>เปลี่ยนรูปโปรไฟล์</label>
                        <input type="file" id="ep-image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp"
                            aria-labelledby="ep-upload-label" aria-describedby="ep-image-hint" class="ep-file">
                        <p id="ep-image-hint" class="ep-upload-hint">รองรับ JPG, PNG, GIF และ WebP</p>
                        <p class="ep-image-status" id="ep-image-status" aria-live="polite"></p><button type="button"
                            class="ep-clear-image" id="ep-clear-image" hidden>ยกเลิกรูปที่เลือก</button>
                        <p class="ep-upload-error" id="ep-upload-error" role="alert" hidden></p>
                    </div>
                    <div class="ep-side-content">
                        <p class="ep-side-title">ตั้งค่าบัญชี</p>
                        <nav class="ep-side-nav" aria-label="ส่วนของแบบฟอร์ม"><a href="#ep-details"><svg
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                                </svg>ข้อมูลส่วนตัว</a><a href="#ep-security"><svg viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <rect x="5" y="10" width="14" height="11" rx="2" />
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />
                                </svg>รหัสผ่าน</a></nav>
                        <p class="ep-side-note">ตรวจสอบข้อมูลให้ถูกต้องก่อนบันทึก เพื่อให้ข้อมูลบัญชีของคุณเป็นปัจจุบัน
                        </p>
                    </div>
                </aside>
                <div class="ep-main">
                    <?php if ($success_msg): ?><div class="ep-alert ep-success" role="status"><svg viewBox="0 0 24 24"
                            aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="m8 12 3 3 5-6" />
                        </svg><span><?= $ui_escape($success_msg) ?></span></div><?php endif; ?>
                    <?php if ($error_msg): ?><div class="ep-alert ep-error" role="alert"><svg viewBox="0 0 24 24"
                            aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 11v6M12 7h.01" />
                        </svg><span><?= $ui_escape($error_msg) ?></span></div><?php endif; ?>
                    <section class="ep-panel" id="ep-details" aria-labelledby="ep-details-title">
                        <div class="ep-panel-heading"><span class="ep-section-icon"><svg viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                                </svg></span>
                            <div>
                                <h2 id="ep-details-title">ข้อมูลส่วนตัว</h2>
                                <p>ข้อมูลพื้นฐานสำหรับบัญชีของคุณ</p>
                            </div>
                        </div>
                        <div class="ep-fields">
                            <div class="ep-field"><label class="ep-label" for="ep-username">ชื่อผู้ใช้</label>
                                <div class="ep-input-wrap"><input class="ep-input" id="ep-username" type="text" readonly
                                        value="@<?= $ui_escape($user_data['username']) ?>"
                                        aria-describedby="ep-username-hint"><svg class="ep-input-icon"
                                        viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="5" y="10" width="14" height="11" rx="2" />
                                        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />
                                    </svg></div>
                                <p class="ep-hint" id="ep-username-hint">ชื่อผู้ใช้ของบัญชีนี้ไม่สามารถแก้ไขได้</p>
                            </div>
                            <div class="ep-row">
                                <div class="ep-field"><label class="ep-label" for="ep-first">ชื่อจริง<span
                                            class="ep-required" aria-hidden="true">*</span></label><input
                                        class="ep-input" id="ep-first" type="text" name="first_name"
                                        autocomplete="given-name" required value="<?= $ui_escape($ui_first) ?>"
                                        placeholder="กรอกชื่อจริง"></div>
                                <div class="ep-field"><label class="ep-label" for="ep-last">นามสกุล<span
                                            class="ep-required" aria-hidden="true">*</span></label><input
                                        class="ep-input" id="ep-last" type="text" name="last_name"
                                        autocomplete="family-name" required value="<?= $ui_escape($ui_last) ?>"
                                        placeholder="กรอกนามสกุล"></div>
                            </div>
                            <div class="ep-field"><label class="ep-label" for="ep-email">อีเมล<span class="ep-required"
                                        aria-hidden="true">*</span></label><input class="ep-input" id="ep-email"
                                    type="email" name="email" autocomplete="email" required
                                    value="<?= $ui_escape($ui_email) ?>" placeholder="name@example.com"
                                    aria-describedby="ep-email-hint">
                                <p class="ep-hint" id="ep-email-hint">ใช้อีเมลที่คุณสามารถเข้าถึงได้</p>
                            </div>
                        </div>
                    </section>
                    <section class="ep-panel" id="ep-security" aria-labelledby="ep-security-title">
                        <div class="ep-panel-heading"><span class="ep-section-icon"><svg viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <rect x="5" y="10" width="14" height="11" rx="2" />
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />
                                </svg></span>
                            <div>
                                <h2 id="ep-security-title">เปลี่ยนรหัสผ่าน<span class="ep-optional">ไม่บังคับ</span>
                                </h2>
                                <p>ตั้งรหัสผ่านใหม่เมื่อคุณต้องการ</p>
                            </div>
                        </div>
                        <div class="ep-fields">
                            <div class="ep-password-note"><svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 11v6M12 7h.01" />
                                </svg><span>เว้นช่องนี้ว่างไว้ หากต้องการใช้รหัสผ่านเดิม</span></div>
                            <div class="ep-field"><label class="ep-label" for="ep-password">รหัสผ่านใหม่</label>
                                <div class="ep-input-wrap"><input class="ep-input" id="ep-password" type="password"
                                        name="new_password" autocomplete="new-password" minlength="6"
                                        placeholder="กรอกรหัสผ่านใหม่" aria-describedby="ep-password-hint"><button
                                        type="button" class="ep-eye" id="ep-toggle-password" aria-label="แสดงรหัสผ่าน"
                                        aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg></button></div>
                                <p class="ep-hint" id="ep-password-hint">อย่างน้อย 6 ตัวอักษร ตามเงื่อนไขของระบบ</p>
                            </div>
                        </div>
                    </section>
                    <div class="ep-actions"><span class="ep-save-state" id="ep-save-state"
                            aria-live="polite">ข้อมูลจะอัปเดตเมื่อกดบันทึก</span>
                        <div class="ep-actions-buttons"><a href="profile.php" class="ep-btn">ยกเลิก</a><button
                                type="submit" class="ep-btn ep-btn-primary" id="ep-save"><svg viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path d="M4 3h13l4 4v14H4zM8 3v6h8V3M8 21v-7h9v7" />
                                </svg><span>บันทึกการเปลี่ยนแปลง</span></button></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
<script>
(() => {
    const form = document.getElementById('ep-form');
    if (!form) return;
    const imageInput = document.getElementById('ep-image');
    const preview = document.getElementById('ep-preview');
    const initial = document.getElementById('ep-initial');
    const imageStatus = document.getElementById('ep-image-status');
    const imageError = document.getElementById('ep-upload-error');
    const clearImage = document.getElementById('ep-clear-image');
    const state = document.getElementById('ep-save-state');
    const originalSrc = preview.getAttribute('src');
    const originalHidden = preview.hidden;
    const tracked = ['ep-first', 'ep-last', 'ep-email', 'ep-password'].map(id => document.getElementById(id));
    const originalValues = tracked.map(field => field.value);
    let imageURL = null;
    let imageGeneration = 0;

    function updateState() {
        const changed = tracked.some((field, i) => field.value !== originalValues[i]) || imageInput.files.length >
            0;
        state.textContent = changed ? 'มีข้อมูลที่ยังไม่ได้บันทึก' : 'ข้อมูลจะอัปเดตเมื่อกดบันทึก';
    }

    function restoreImage() {
        imageGeneration++;
        if (imageURL) URL.revokeObjectURL(imageURL);
        imageURL = null;
        preview.setAttribute('src', originalSrc);
        preview.hidden = originalHidden;
        initial.hidden = !originalHidden;
        imageInput.value = '';
        imageStatus.textContent = '';
        clearImage.hidden = true;
        updateState();
    }
    imageInput.addEventListener('change', () => {
        imageError.hidden = true;
        const file = imageInput.files[0];
        if (!file) {
            restoreImage();
            return;
        }
        if (!/\.(jpe?g|png|gif|webp)$/i.test(file.name)) {
            restoreImage();
            imageError.textContent = 'กรุณาเลือกรูป JPG, PNG, GIF หรือ WebP';
            imageError.hidden = false;
            return;
        }
        if (imageURL) URL.revokeObjectURL(imageURL);
        const generation = ++imageGeneration;
        imageURL = URL.createObjectURL(file);
        const candidate = new Image();
        candidate.onload = () => {
            if (generation !== imageGeneration) return;
            preview.src = imageURL;
            preview.hidden = false;
            initial.hidden = true;
            imageStatus.textContent = 'รูปที่เลือก: ' + file.name;
            clearImage.hidden = false;
            updateState();
        };
        candidate.onerror = () => {
            if (generation !== imageGeneration) return;
            restoreImage();
            imageError.textContent = 'เปิดรูปนี้ไม่ได้ กรุณาเลือกไฟล์รูปภาพใหม่';
            imageError.hidden = false;
        };
        candidate.src = imageURL;
    });
    clearImage.addEventListener('click', () => {
        imageError.hidden = true;
        restoreImage();
        imageInput.focus();
    });
    const password = document.getElementById('ep-password');
    const toggle = document.getElementById('ep-toggle-password');
    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
    });
    tracked.forEach(field => field.addEventListener('input', updateState));
    form.addEventListener('submit', () => {
        const save = document.getElementById('ep-save');
        save.disabled = true;
        save.querySelector('span').textContent = 'กำลังบันทึก…';
        state.textContent = 'กำลังบันทึกข้อมูล';
    });
    window.addEventListener('pageshow', () => {
        const save = document.getElementById('ep-save');
        save.disabled = false;
        save.querySelector('span').textContent = 'บันทึกการเปลี่ยนแปลง';
        updateState();
    });
})();


(() => {
    const links = document.querySelectorAll(
        '.dpt-edit .ep-side-nav a'
    );

    function setActive(hash) {
        links.forEach(link => {
            const active = link.getAttribute('href') === hash;
            link.classList.toggle('is-active', active);

            if (active) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    links.forEach(link => {
        link.addEventListener('click', event => {
            const hash = link.getAttribute('href');
            const target = document.getElementById(hash.slice(1));

            if (!target) return;

            event.preventDefault();
            setActive(hash);

            history.replaceState(null, '', hash);
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });

    setActive(
        location.hash === '#ep-security'
            ? '#ep-security'
            : '#ep-details'
    );

    window.addEventListener('hashchange', () => {
        setActive(location.hash || '#ep-details');
    });
})();

</script>

<?php include 'footer.php'; ?>