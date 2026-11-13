<?php

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}

require_once 'connection/connection.php';



if (!isset($_SESSION['username'], $_SESSION['user_id'])) {

    header('Location: login.php');

    exit();

}



$user_id = (int)$_SESSION['user_id'];



// ดึงสถิติการเรียน

$stmt_enrolled = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");

$stmt_enrolled->execute([$user_id]);

$enrolled_count = (int)$stmt_enrolled->fetchColumn();



$stmt_passed = $pdo->prepare("SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE user_id = ? AND is_passed = 1");

$stmt_passed->execute([$user_id]);

$passed_count = (int)$stmt_passed->fetchColumn();



$stmt_latest = $pdo->prepare("

    SELECT qa.*, q.title as quiz_title, c.title as course_title 

    FROM quiz_attempts qa 

    JOIN quizzes q ON qa.quiz_id = q.quiz_id 

    JOIN courses c ON q.course_id = c.course_id 

    WHERE qa.user_id = ? 

    ORDER BY qa.attempt_date DESC 

    LIMIT 1

");

$stmt_latest->execute([$user_id]);

$latest_exam = $stmt_latest->fetch(PDO::FETCH_ASSOC);



// ดึงรายการคอร์สที่ลงทะเบียนไว้

$stmt_my_courses = $pdo->prepare("

    SELECT c.course_id, c.title, c.thumbnail_url, e.enrollment_date, e.progress_percentage, cat.name AS category_name

    FROM enrollments e

    JOIN courses c ON e.course_id = c.course_id

    LEFT JOIN categories cat ON c.category_id = cat.category_id

    WHERE e.user_id = ?

    ORDER BY e.enrollment_date DESC

");

$stmt_my_courses->execute([$user_id]);

$my_courses = $stmt_my_courses->fetchAll(PDO::FETCH_ASSOC);

?>



<?php include 'header.php'; ?>
<?php
// header.php supplies the current $user, as in the original page.
$profile = $user ?? [];
$escape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
$username = $profile['username'] ?? $_SESSION['username'];
$name = $name !== '' ? $name : $username;
$role = $profile['role'] ?? 'student';
$roles = ['student' => 'ผู้เรียน', 'admin' => 'ผู้ดูแลระบบ', 'super_admin' => 'ผู้ดูแลระบบสูงสุด', 'instructor' => 'ผู้สอน'];
$roleLabel = $roles[$role] ?? $role;
$avatar = !empty($profile['image']) && is_file('uploads/' . $profile['image']) ? 'uploads/' . $profile['image'] : null;
$joinedTimestamp = !empty($profile['created_at']) ? strtotime($profile['created_at']) : false;
$months = [1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$joined = $joinedTimestamp ? date('j', $joinedTimestamp) . ' ' . $months[(int)date('n', $joinedTimestamp)] . ' ' . ((int)date('Y', $joinedTimestamp) + 543) : 'ไม่ระบุ';
$initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : 'U';
?>
<style>
.dpt-profile{--ink:#19253e;--muted:#64748b;--line:#e7ebf3;--purple:#6454dc;--soft:#f3f1ff;font-family:inherit;color:var(--ink);background:#f5f7fb;flex:1;width:100%;padding:32px 24px 56px;box-sizing:border-box}
.dpt-profile *{box-sizing:border-box}.dpt-profile a{text-decoration:none}.dpt-profile button,.dpt-profile a{-webkit-tap-highlight-color:transparent}.dpt-profile a:focus-visible{outline:3px solid #aa9cf8;outline-offset:4px}.dpt-profile .dp-wrap{max-width:1120px;margin:auto}.dpt-profile .dp-crumb{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;margin-bottom:22px}.dpt-profile .dp-crumb a{color:var(--muted)}.dpt-profile .dp-crumb span:last-child{color:var(--ink)}
.dpt-profile .dp-hero{position:relative;isolation:isolate;overflow:hidden;border-radius:24px;background:linear-gradient(115deg,#182541 0%,#333267 60%,#6454b9 100%);padding:30px 34px;color:white;box-shadow:0 12px 32px #24345612}.dpt-profile .dp-hero:after{content:'';position:absolute;width:330px;height:330px;right:-90px;top:-190px;border:52px solid #ffffff08;border-radius:50%;z-index:-1}.dpt-profile .dp-eyebrow{color:#cdc9f6;font-size:12px;letter-spacing:1.8px;font-weight:600;margin:0 0 22px}.dpt-profile .dp-identity{display:flex;align-items:center;gap:22px}.dpt-profile .dp-avatar{width:100px;height:100px;flex:0 0 100px;display:grid;place-items:center;overflow:hidden;background:#8172df;border:4px solid #ffffff30;border-radius:24px;font-size:40px;font-weight:700}.dpt-profile .dp-avatar img{width:100%;height:100%;object-fit:cover}.dpt-profile .dp-person{flex:1;min-width:0}.dpt-profile h1{font-size:clamp(24px,3vw,32px);line-height:1.4;letter-spacing:-.5px;margin:0 0 5px;font-weight:700;overflow-wrap:anywhere}.dpt-profile .dp-handle{color:#d3d7ea;font-size:14px;margin:0}.dpt-profile .dp-role{display:inline-flex;align-items:center;gap:7px;background:#ffffff14;border:1px solid #ffffff25;border-radius:30px;padding:4px 11px;font-size:12px;color:#edeaff;margin-top:12px}.dpt-profile .dp-dot{width:6px;height:6px;background:#b8afff;border-radius:50%}.dpt-profile .dp-hero-actions{display:flex;flex-direction:column;align-items:flex-end;gap:14px}.dpt-profile .dp-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border-radius:12px;padding:11px 17px;font-weight:600;font-size:14px;min-height:44px;line-height:1.5;transition:background .15s,box-shadow .15s;color:var(--ink);border:1px solid var(--line);background:white}.dpt-profile .dp-btn:hover{background:#f4f3ff}.dpt-profile .dp-btn-primary{background:var(--purple);color:white;border-color:var(--purple)}.dpt-profile .dp-btn-primary:hover{background:#5342c6}.dpt-profile .dp-btn-light{background:white;color:#363158;border-color:white}.dpt-profile .dp-back{font-size:12px;color:#dddaf6;display:inline-flex;align-items:center;gap:7px}.dpt-profile .dp-back:hover{color:white}.dpt-profile svg{width:20px;height:20px;flex-shrink:0;vertical-align:middle;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.dpt-profile .dp-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:22px 0 26px}.dpt-profile .dp-stat{display:flex;gap:15px;align-items:center;background:white;border:1px solid var(--line);padding:22px;border-radius:18px}.dpt-profile .dp-icon{display:grid;place-items:center;flex:0 0 46px;width:46px;height:46px;background:var(--soft);color:var(--purple);border-radius:13px}.dpt-profile .dp-icon-green{background:#eaf8f2;color:#23996c}.dpt-profile .dp-icon-amber{background:#fff5e7;color:#b97b28}.dpt-profile .dp-number{font-size:28px;line-height:1.25;font-weight:700;letter-spacing:-.5px}.dpt-profile .dp-stat-label{color:var(--muted);font-size:12px;margin-top:5px}
.dpt-profile .dp-layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:22px;align-items:start}.dpt-profile .dp-panel{background:white;border:1px solid var(--line);border-radius:20px;overflow:hidden}.dpt-profile .dp-panel-head{padding:23px 24px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px}.dpt-profile h2{margin:0;font-size:17px;font-weight:700;line-height:1.5}.dpt-profile .dp-caption{color:var(--muted);font-size:12px;margin:4px 0 0}.dpt-profile .dp-count{background:var(--soft);color:var(--purple);font-size:12px;border-radius:20px;padding:5px 10px;white-space:nowrap}.dpt-profile .dp-account{padding:0 24px;margin:0}.dpt-profile .dp-detail{padding:17px 0;border-top:1px solid var(--line)}.dpt-profile dt{font-size:12px;color:var(--muted);margin-bottom:7px}.dpt-profile dd{font-size:14px;line-height:1.6;font-weight:500;margin:0;overflow-wrap:anywhere}.dpt-profile .dp-side-links{padding:8px 14px 14px}.dpt-profile .dp-side-link{display:flex;align-items:center;gap:10px;padding:12px 10px;border-radius:10px;color:#55627a;font-size:13px;min-height:44px}.dpt-profile .dp-side-link:hover{background:var(--soft);color:var(--purple)}.dpt-profile .dp-side-link svg:last-child{margin-left:auto;width:16px}.dpt-profile .dp-logout{color:#ac4b5b}.dpt-profile .dp-logout:hover{background:#fff1f3;color:#a33247}
.dpt-profile .dp-course-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding:0 24px 24px}.dpt-profile .dp-course{border:1px solid var(--line);border-radius:15px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .15s}.dpt-profile .dp-course:hover{box-shadow:0 7px 20px #1a25400a}.dpt-profile .dp-thumb{height:132px;background:linear-gradient(135deg,#f0edff,#e1e9fb);display:grid;place-items:center;color:#8175c6;overflow:hidden}.dpt-profile .dp-thumb>svg{width:42px;height:42px}.dpt-profile .dp-thumb img{width:100%;height:100%;object-fit:cover}.dpt-profile .dp-course-body{padding:16px;display:flex;flex-direction:column;flex:1}.dpt-profile .dp-category{font-size:11px;color:var(--purple);line-height:1.5}.dpt-profile h3{font-size:15px;line-height:1.65;margin:7px 0 16px;font-weight:600;overflow-wrap:anywhere}.dpt-profile .dp-course-bottom{margin-top:auto}.dpt-profile .dp-progress-label{display:flex;justify-content:space-between;gap:8px;color:var(--muted);font-size:11px;margin-bottom:8px}.dpt-profile .dp-progress-label strong{color:var(--purple);font-weight:600}.dpt-profile progress{display:block;appearance:none;width:100%;height:6px;border:0;border-radius:10px;overflow:hidden;background:#eeedf6;margin-bottom:15px}.dpt-profile progress::-webkit-progress-bar{background:#eeedf6;border-radius:10px}.dpt-profile progress::-webkit-progress-value{background:var(--purple);border-radius:10px}.dpt-profile progress::-moz-progress-bar{background:var(--purple);border-radius:10px}.dpt-profile .dp-course .dp-btn{width:100%;font-size:12px;background:var(--soft);border:0;color:var(--purple)}.dpt-profile .dp-course .dp-btn:hover{background:#e7e2ff}.dpt-profile .dp-empty{text-align:center;padding:32px 24px 42px;margin:0 24px 24px;border-radius:16px;background:#fafaff;border:1px dashed #dbd7f0}.dpt-profile .dp-empty-icon{display:grid;place-items:center;width:76px;height:76px;border-radius:24px;background:#eeebfc;color:var(--purple);margin:0 auto 20px;transform:rotate(-5deg)}.dpt-profile .dp-empty-icon svg{width:34px;height:34px;transform:rotate(5deg)}.dpt-profile .dp-empty h3{font-size:18px;margin:0 0 8px}.dpt-profile .dp-empty p{font-size:13px;line-height:1.9;color:var(--muted);margin:0 auto 22px;max-width:340px}.dpt-profile .dp-note{padding:17px 24px;background:#fbfcfe;border-top:1px solid var(--line);display:flex;align-items:center;gap:9px;color:var(--muted);font-size:12px}.dpt-profile .dp-note svg{color:#8b80cf;width:17px}
@media(max-width:900px){.dpt-profile .dp-layout{grid-template-columns:260px minmax(0,1fr)}.dpt-profile .dp-course-grid{grid-template-columns:1fr}.dpt-profile .dp-stat{padding:18px 14px;gap:10px}.dpt-profile .dp-hero{padding:28px}.dpt-profile .dp-hero-actions{align-items:flex-start;margin-top:20px;flex-direction:row;align-items:center}.dpt-profile .dp-identity{flex-wrap:wrap}.dpt-profile .dp-hero-actions{width:100%}}
@media(max-width:640px){.dpt-profile{padding:20px 16px 32px}.dpt-profile .dp-crumb{font-size:12px;margin-bottom:16px}.dpt-profile .dp-hero{border-radius:20px;padding:24px}.dpt-profile .dp-eyebrow{margin-bottom:18px;font-size:10px}.dpt-profile .dp-avatar{width:72px;height:72px;flex-basis:72px;border-radius:19px;font-size:30px}.dpt-profile .dp-identity{gap:15px}.dpt-profile h1{font-size:23px}.dpt-profile .dp-handle{font-size:12px}.dpt-profile .dp-hero-actions{margin-top:7px;justify-content:space-between;gap:10px}.dpt-profile .dp-hero-actions .dp-btn{font-size:12px;padding:10px 12px}.dpt-profile .dp-back{font-size:11px}.dpt-profile .dp-stats{gap:8px;margin:16px 0 20px}.dpt-profile .dp-stat{display:block;text-align:center;padding:16px 7px;border-radius:14px}.dpt-profile .dp-stat .dp-icon{width:34px;height:34px;margin:0 auto 10px;border-radius:10px}.dpt-profile .dp-stat .dp-icon svg{width:18px;height:18px}.dpt-profile .dp-number{font-size:24px}.dpt-profile .dp-stat-label{font-size:10px;line-height:1.7}.dpt-profile .dp-layout{display:flex;flex-direction:column;gap:18px}.dpt-profile .dp-sidebar{width:100%;order:2}.dpt-profile .dp-courses{width:100%;order:1}.dpt-profile .dp-panel-head{padding:20px 18px 16px}.dpt-profile .dp-empty{margin:0 16px 16px;padding:30px 18px}.dpt-profile .dp-empty h3{font-size:17px}.dpt-profile .dp-course-grid{padding:0 16px 16px}.dpt-profile .dp-account{padding:0 20px}.dpt-profile .dp-note{padding:15px 18px;align-items:flex-start}.dpt-profile .dp-note svg{margin-top:2px}}
@media(prefers-reduced-motion:reduce){.dpt-profile *{transition:none!important}}
</style>
<main class="dpt-profile">
<div class="dp-wrap">
<nav class="dp-crumb" aria-label="เส้นทางนำทาง"><a href="index.php">หน้าหลัก</a><span aria-hidden="true">/</span><span aria-current="page">โปรไฟล์ของฉัน</span></nav>
<section class="dp-hero" aria-labelledby="profile-name">
<p class="dp-eyebrow">MY LEARNING SPACE</p>
<div class="dp-identity">
<div class="dp-avatar"><?php if ($avatar): ?><img src="<?= $escape($avatar) ?>" alt="รูปโปรไฟล์ของ <?= $escape($name) ?>"><?php else: ?><span aria-hidden="true"><?= $escape($initial) ?></span><?php endif; ?></div>
<div class="dp-person"><h1 id="profile-name"><?= $escape($name) ?></h1><p class="dp-handle">@<?= $escape($username) ?></p><span class="dp-role"><span class="dp-dot" aria-hidden="true"></span><?= $escape($roleLabel) ?></span></div>
<div class="dp-hero-actions"><a href="edit_profile.php" class="dp-btn dp-btn-light"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 4 5 5M4 20l5-1L20 8a2 2 0 0 0-5-5L4 14z"/></svg>แก้ไขโปรไฟล์</a><a href="courses_all.php" class="dp-back"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 7-5 5 5 5M9 12h12"/></svg>กลับหน้ารวมคอร์ส</a></div>
</div></section>
<section class="dp-stats" aria-label="สถิติการเรียน">
<div class="dp-stat"><div class="dp-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4zM13 7a3 3 0 0 1 3-3h5v14h-5a3 3 0 0 0-3 3"/></svg></div><div><div class="dp-number"><?= $enrolled_count ?></div><div class="dp-stat-label">คอร์สที่ลงทะเบียน</div></div></div>
<div class="dp-stat"><div class="dp-icon dp-icon-green"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></div><div><div class="dp-number"><?= $passed_count ?></div><div class="dp-stat-label">แบบทดสอบที่ผ่าน</div></div></div>
<div class="dp-stat"><div class="dp-icon dp-icon-amber"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19h16M7 15v-4M12 15V5M17 15V8"/></svg></div><div><div class="dp-number"><?= $latest_exam ? $escape(number_format((float)$latest_exam['score_obtained'], 0)) : '—' ?></div><div class="dp-stat-label">คะแนนสอบล่าสุด</div></div></div>
</section>
<div class="dp-layout">
<aside class="dp-sidebar dp-panel" aria-labelledby="account-title"><div class="dp-panel-head"><div><h2 id="account-title">ข้อมูลบัญชี</h2><p class="dp-caption">รายละเอียดโปรไฟล์ของคุณ</p></div></div>
<dl class="dp-account"><div class="dp-detail"><dt>อีเมล</dt><dd><?= $escape($profile['email'] ?? 'ไม่ระบุ') ?></dd></div><div class="dp-detail"><dt>ชื่อผู้ใช้</dt><dd>@<?= $escape($username) ?></dd></div><div class="dp-detail"><dt>วันที่สมัครสมาชิก</dt><dd><?= $escape($joined) ?></dd></div></dl>
<nav class="dp-side-links" aria-label="เมนูบัญชี"><a href="quiz-history.php" class="dp-side-link"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10a9 9 0 1 1 1 8M3 4v6h6M12 7v5l3 2"/></svg>ประวัติการทำแบบทดสอบ<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a><a href="courses_all.php" class="dp-side-link"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4zM13 7a3 3 0 0 1 3-3h5v14h-5a3 3 0 0 0-3 3"/></svg>สำรวจหลักสูตรใหม่<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a><a href="logout.php" class="dp-side-link dp-logout"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4H4v16h5M9 12h12m-4-4 4 4-4 4"/></svg>ออกจากระบบ<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a></nav></aside>
<section class="dp-panel dp-courses" aria-labelledby="courses-title"><div class="dp-panel-head"><div><h2 id="courses-title">คอร์สเรียนของฉัน</h2><p class="dp-caption">เรียนรู้ต่อจากจุดที่คุณค้างไว้</p></div><span class="dp-count"><?= count($my_courses) ?> คอร์ส</span></div>
<?php if (!empty($my_courses)): ?>
<div class="dp-course-grid">
<?php foreach ($my_courses as $mc): ?>
<?php
$pct = max(0, min(100, (int)($mc['progress_percentage'] ?? 0)));
$thumb = !empty($mc['thumbnail_url']) && is_file('uploads/' . $mc['thumbnail_url']) ? 'uploads/' . $mc['thumbnail_url'] : null;
?>
<article class="dp-course"><div class="dp-thumb"><?php if ($thumb): ?><img src="<?= $escape($thumb) ?>" alt="" loading="lazy"><?php else: ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4zM13 7a3 3 0 0 1 3-3h5v14h-5a3 3 0 0 0-3 3"/></svg><?php endif; ?></div><div class="dp-course-body"><span class="dp-category"><?= $escape($mc['category_name'] ?? 'ทั่วไป') ?></span><h3><?= $escape($mc['title']) ?></h3><div class="dp-course-bottom"><div class="dp-progress-label"><span><?= $pct >= 100 ? 'เรียนครบแล้ว' : 'ความคืบหน้าการเรียน' ?></span><strong><?= $pct ?>%</strong></div><progress value="<?= $pct ?>" max="100" aria-label="ความคืบหน้าคอร์ส <?= $escape($mc['title']) ?>"><?= $pct ?>%</progress><a href="courses.php?id=<?= (int)$mc['course_id'] ?>" class="dp-btn" aria-label="<?= $pct >= 100 ? 'ทบทวนบทเรียน' : 'เข้าเรียนต่อ' ?>: <?= $escape($mc['title']) ?>"><?= $pct >= 100 ? 'ทบทวนบทเรียน' : 'เข้าเรียนต่อ' ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a></div></div></article>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="dp-empty"><div class="dp-empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4zM13 7a3 3 0 0 1 3-3h5v14h-5a3 3 0 0 0-3 3"/></svg></div><h3>เริ่มต้นการเรียนรู้ในแบบของคุณ</h3><p>คุณยังไม่ได้ลงทะเบียนคอร์สเรียน<br>ค้นหาหลักสูตรที่สนใจ แล้วเริ่มเรียนได้เลย</p><a href="courses_all.php" class="dp-btn dp-btn-primary">สำรวจคอร์สเรียน<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a></div>
<?php endif; ?>
<div class="dp-note"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z"/></svg><span>พัฒนาทักษะทีละนิด เพื่อโอกาสใหม่ในทุกวัน</span></div>
</section></div></div>
</main>
<?php include 'footer.php'; ?>
