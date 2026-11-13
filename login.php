<?php
session_start();
require_once 'connection/connection.php';
?>
<!doctype html>
<html lang='th'>

<head>
    <meta charset='utf-8' />
    <meta name='viewport' content='width=device-width,initial-scale=1' />
    <title>Login E-Learning</title>
    <link rel='stylesheet' href='css/style.css'>
	<link rel="stylesheet" href="css/responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    
</style>
</head>

<body>

    <div class='wrap'>
        <!-- LEFT: big image -->
        <div class='left' aria-hidden='true'>
            <div class='imgcard'>
                <!-- ใช้พาธไฟล์ภาพที่คุณให้ไว้ด้านล่าง -->
                <img src='image/com123.png' alt='E-Learning image'>
            </div>
        </div>

        <!-- RIGHT: login form -->
        <div class='right'>
            <div class='login-panel' role='form' aria-labelledby='login-title'>
                <div class='logo-wrap'>
                    <!-- เปลี่ยน path ถ้าโลโก้ของคุณอยู่ที่อื่น -->
                    <img src='image/logo.png' alt='logo'>
                </div>

                <h1 id='login-title' class='title'>ระบบอีเลิร์นนิ่ง<br>เข้าสู่ระบบ</h1>

                <div class='field'>
                    <label class='field-label' for='user'>ชื่อผู้ใช้งาน</label>
                    <input id='user' class='input' type='text' placeholder='ชื่อผู้ใช้งาน หรือ e-mail'>
                    <div class='underline' style='width:100%'></div>
                </div>

                <div class='field pw-wrap'>
                    <label class='field-label' for='pass'>รหัสผ่าน</label>
                    <input id='pass' class='input' type='password' placeholder='รหัสผ่าน'>
                    <div class='pw-eye' onclick='togglePass()' title='แสดง/ซ่อนรหัส'>

                        <!-- inline SVG eye -->
                        <svg width='18' height='18' viewBox='0 0 24 24' fill='none' aria-hidden='true'
                            xmlns='http://www.w3.org/2000/svg'>
                            <path
                                d='M12 5C7 5 2.73 8.11 1 12C2.73 15.89 7 19 12 19C17 19 21.27 15.89 23 12C21.27 8.11 17 5 12 5Z'
                                stroke='#444' stroke-width='1.2' stroke-linecap='round' stroke-linejoin='round' />
                            <circle cx='12' cy='12' r='3' stroke='#444' stroke-width='1.2' />
                        </svg>
                    </div>
                </div>

                <div class='row-between'>
                    <label class='checkbox'>
                        <input type='checkbox' id='remember'> <span>จำรหัสผ่าน</span>
                    </label>
                    <a class='forgot' href='#' onclick="Swal.fire({icon: 'info', title: 'ลืมรหัสผ่าน?', text: 'กรุณาติดต่อผู้ดูแลระบบศูนย์เทคโนโลยีสารสนเทศเพื่อรีเซ็ตรหัสผ่าน'}); return false;">ลืมรหัสผ่าน?</a>
                </div>

                <button class='btn yellow' type='button' onclick='doLogin()'>เข้าสู่ระบบ</button>

                <div class='or-sep'>หรือ</div>

                <button class="btn sso" type="button" onclick="window.open('https://sso.dpt.go.th/', '_blank');">
                    SSO DPT 
                </button>

                <button  class='btn register' type='button'
                    onclick="window.location.href='register.php'">ลงทะเบียน</button>

            </div>
        </div>
    </div>
<script>
function doLogin() {
    // 1. ดึงค่าจาก Element ID
    const u = document.getElementById('user').value.trim();
    const p = document.getElementById('pass').value;

    // 2. Validation เบื้องต้น
    if (!u || !p) {
        Swal.fire({
            icon: 'warning',
            title: 'ข้อมูลไม่ครบ',
            text: 'กรุณากรอกชื่อผู้ใช้งานหรืออีเมล และรหัสผ่าน'
        });
        return;
    }

    // 3. เตรียมข้อมูลส่งไปที่ login_db.php
    const formData = new FormData();
    formData.append("username", u);
    formData.append("password", p);

    const loginBtn = document.querySelector('.btn.yellow');
    const originalText = loginBtn.innerText;
    loginBtn.innerText = "กำลังตรวจสอบ...";
    loginBtn.disabled = true;

    fetch('login_db.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            if (data.status === "success") {
                window.location.href = data.redirect || "index.php"; 
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เข้าสู่ระบบไม่สำเร็จ',
                    text: data.msg || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'
                });
                loginBtn.innerText = originalText;
                loginBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            Swal.fire({
                icon: 'error',
                title: 'การเชื่อมต่อผิดพลาด',
                text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง'
            });
            loginBtn.innerText = originalText;
            loginBtn.disabled = false;
        });
}

// ฟังก์ชัน Toggle Password แบบประหยัดทรัพยากร
function togglePass() {
    const passInput = document.getElementById("pass");
    passInput.type = passInput.type === "password" ? "text" : "password";
}

// ตรวจจับปุ่ม Enter
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        // ตรวจสอบว่าไม่ได้อยู่ในปุ่มอื่นๆ
        if (document.activeElement.tagName !== 'BUTTON') {
            doLogin();
        }
    }
});
</script>

</body>

</html>