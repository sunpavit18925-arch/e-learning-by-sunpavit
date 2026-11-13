<?php
session_start();
// ตรวจสอบ Path ให้ตรงกับไฟล์เชื่อมต่อของคุณ
require_once 'connection/connection.php'; 
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ลงทะเบียนเข้าเรียน - E-Learning</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

<div class="wrap">

    <div class="left" aria-hidden="true">
        <div class="imgcard">
            <img src="image/lady-work-com.jpg" alt="Register image">
        </div>
    </div>

    <div class="right">
        <div class="login-panel">

            <div class="logo-wrap">
                <img src="image/logo.png" alt="logo">
            </div>

            <h1 class="title">ลงทะเบียนสมาชิกใหม่</h1>

            <div class="field-row" style="display: flex; gap: 10px;">
                <div class="field" style="flex: 1;">
                    <label class="field-label" for="first_name">ชื่อจริง</label>
                    <input id="first_name" class="input" type="text" placeholder="ชื่อ"> 
                </div>
                <div class="field" style="flex: 1;">
                    <label class="field-label" for="last_name">นามสกุล</label>
                    <input id="last_name" class="input" type="text" placeholder="นามสกุล"> 
                </div>
            </div>

            <div class="field">
                <label class="field-label" for="email">อีเมล</label>
                <input id="email" class="input" type="email" placeholder="example@mail.com"> 
            </div>

            <div class="field">
                <label class="field-label" for="username">ชื่อผู้ใช้งาน (Username)</label>
                <input id="username" class="input" type="text" placeholder="ภาษาอังกฤษเท่านั้น"> 
            </div>

            <div class="field pw-wrap">
                <label class="field-label" for="pass">รหัสผ่าน</label>
                <input id="pass" class="input" type="password" placeholder="อย่างน้อย 8 ตัวอักษร">
                <div class="pw-eye" onclick="togglePass()" title="แสดง/ซ่อนรหัส" style="cursor:pointer; position:absolute; right:10px; top:35px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 5C7 5 2.73 8.11 1 12C2.73 15.89 7 19 12 19C17 19 21.27 15.89 23 12C21.27 8.11 17 5 12 5Z"
                            stroke="#444" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="3" stroke="#444" stroke-width="1.2" />
                    </svg>
                </div>
            </div>

            <button class="btn yellow" type="button" onclick="doRegister()">สมัครสมาชิก</button>
            <button class="btn sso" type="button" onclick="window.location='login.php'">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</button>

        </div>
    </div>

</div>

<script>
function togglePass() {
    const pass = document.getElementById("pass");
    pass.type = pass.type === "password" ? "text" : "password";
}

function doRegister() {
    // 1. ดึงค่าจากทุก Input
    const first_name = document.getElementById("first_name").value.trim();
    const last_name  = document.getElementById("last_name").value.trim();
    const email      = document.getElementById("email").value.trim();
    const username   = document.getElementById("username").value.trim();
    const pass       = document.getElementById("pass").value;

    // 2. ตรวจสอบเบื้องต้น (Client-side validation)
    if (!first_name || !last_name || !email || !username || !pass) {
        Swal.fire({
            icon: 'warning',
            title: 'ข้อมูลไม่ครบ',
            text: 'กรุณากรอกข้อมูลให้ครบทุกช่อง'
        });
        return;
    }

    // 3. เตรียม Data ส่งไปยัง PHP
    const fd = new FormData();
    fd.append("first_name", first_name);
    fd.append("last_name", last_name);
    fd.append("email", email);
    fd.append("username", username);
    fd.append("password", pass);

    fetch('register_db.php', {
        method: "POST",
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: data.msg || 'ลงทะเบียนสำเร็จ กำลังพาไปยังหน้าเข้าสู่ระบบ...',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location = "login.php";
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'การลงทะเบียนไม่สำเร็จ',
                text: data.msg || 'เกิดข้อผิดพลาดในการลงทะเบียน'
            });
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'การเชื่อมต่อผิดพลาด',
            text: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์'
        });
    });
}

// รองรับการกด Enter
document.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        doRegister();
    }
});

</script>


</body>
</html>