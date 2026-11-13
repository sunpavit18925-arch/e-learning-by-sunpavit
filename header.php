<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

$user = null;
$notifications = [];
$unread_count = 0;

if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, image, first_name, last_name, email, role, created_at FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_destroy();
            header("Location: login.php");
            exit();
        }

        // อัปเดต session user_id และ role ให้ตรงกับ DB เสมอ
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

        // ดึงรายการแจ้งเตือน 5 อันล่าสุด
        $stmt_noti = $pdo->prepare("SELECT noti_id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt_noti->execute([$user['user_id']]);
        $notifications = $stmt_noti->fetchAll(PDO::FETCH_ASSOC);

        // นับจำนวนแจ้งเตือนที่ยังไม่ได้อ่าน
        $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt_unread->execute([$user['user_id']]);
        $unread_count = (int)$stmt_unread->fetchColumn();

    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern E-Learning | DPT</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Kanit', sans-serif !important; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        main {
            flex: 1;
        }

        .noti-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: bold;
            color: white;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid #0f172a;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slideDown {
            animation: slideDown 0.2s ease-out forwards;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 10px;
        }

        .group:hover .group-hover\:block {
            display: block !important;
        }

        .dropdown-bridge {
            padding-top: 10px;
            top: 100%;
        }

        .main-nav-link {
            font-weight: 500;
            color: #d1d5db;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .main-nav-link:hover {
            color: #ffffff;
            background-color: rgba(51, 65, 85, 0.5);
            transform: translateY(-1px);
        }

        #menu-btn {
            width: 40px !important;
            height: 40px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            background-color: #1e293b !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer;
        }

        #menu-btn svg {
            width: 24px !important;
            height: 24px !important;
            stroke: white !important;
            stroke-width: 2.2;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen text-slate-800">
<nav class="sticky top-0 z-50 bg-slate-900 shadow-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex justify-between items-center">
        <!-- Logo -->
        <a href="index.php" class="flex items-center space-x-3 text-2xl font-bold bg-gradient-to-r from-indigo-400 via-purple-300 to-pink-400 bg-clip-text text-transparent hover:opacity-90 transition">
            <span class="text-2xl">🎓</span>
            <span>E-Learning DPT</span>
        </a>

        <!-- Desktop Navigation Links -->
        <div class="hidden md:flex items-center space-x-1 lg:space-x-2">
            <a href="index.php" class="main-nav-link">หน้าหลัก</a>
            <a href="courses_all.php" class="main-nav-link">คอร์สทั้งหมด</a>
            <a href="about.php" class="main-nav-link">เกี่ยวกับเรา</a>
            <a href="contact.php" class="main-nav-link">ติดต่อ</a>
            <?php if ($user && ($user['role'] === 'admin' || $user['role'] === 'instructor')): ?>
                <a href="add-course.php" class="main-nav-link text-indigo-400 hover:text-indigo-300 font-semibold">
                    <i class="fa-solid fa-plus-circle mr-1"></i> เพิ่มคอร์ส
                </a>
            <?php endif; ?>
        </div>

        <!-- Right Side: User Controls or Login/Register -->
        <div class="flex items-center space-x-3 md:space-x-4">
            <?php if ($user): ?>
                <!-- Notifications Dropdown -->
                <div class="relative group hidden md:block">
                    <button class="relative p-2.5 text-gray-300 hover:text-white transition focus:outline-none rounded-xl hover:bg-slate-800" aria-label="Notifications">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($unread_count > 0): ?>
                            <span class="noti-badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="absolute right-0 w-80 pt-3 hidden group-hover:block z-[9999] animate-slideDown">
                        <div class="bg-slate-800 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-700 flex justify-between items-center bg-slate-850">
                                <span class="text-white font-semibold text-sm">การแจ้งเตือน</span>
                                <span class="text-xs bg-indigo-600 text-white px-2 py-0.5 rounded-full"><?= $unread_count ?> ใหม่</span>
                            </div>
                            
                            <div class="max-h-80 overflow-y-auto custom-scrollbar bg-slate-800 divide-y divide-gray-700/50">
                                <?php if (empty($notifications)): ?>
                                    <div class="px-4 py-8 text-center text-gray-400 text-sm">ไม่มีการแจ้งเตือนในขณะนี้</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $noti): ?>
                                        <a href="<?= !empty($noti['link']) ? htmlspecialchars($noti['link']) : 'notifications_all.php' ?>" class="block px-4 py-3 hover:bg-slate-700/60 transition <?= $noti['is_read'] == 0 ? 'bg-slate-700/30' : '' ?>">
                                            <div class="text-sm font-medium <?= $noti['is_read'] == 0 ? 'text-indigo-400' : 'text-gray-200' ?>">
                                                <?= htmlspecialchars($noti['title']) ?>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-1 line-clamp-2 leading-relaxed">
                                                <?= htmlspecialchars($noti['message']) ?>
                                            </div>
                                            <div class="text-[10px] text-gray-500 mt-1.5 flex items-center">
                                                <i class="fa-regular fa-clock mr-1"></i>
                                                <?= date('d M H:i', strtotime($noti['created_at'])) ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <a href="notifications_all.php" class="block py-2.5 text-center text-xs text-indigo-400 hover:bg-slate-750 font-medium transition border-t border-gray-700">ดูทั้งหมด</a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="hidden md:block relative group">
                    <button class="flex items-center text-gray-300 hover:text-white font-medium transition p-1.5 rounded-xl hover:bg-slate-800 focus:outline-none">
                        <div class="w-9 h-9 mr-2 overflow-hidden rounded-full border border-gray-700 flex-shrink-0 group-hover:border-indigo-400 transition shadow-sm bg-slate-700">
                            <?php 
                                $userAvatar = (!empty($user['image']) && file_exists('uploads/' . $user['image'])) 
                                    ? 'uploads/' . htmlspecialchars($user['image']) 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=6366f1&color=fff';
                            ?>
                            <img src="<?= $userAvatar ?>" class="w-full h-full object-cover" alt="User Avatar">
                        </div>
                        <div class="text-left hidden lg:block mr-1">
                            <div class="text-sm font-semibold text-gray-200"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="text-[10px] uppercase font-bold text-indigo-400 tracking-wider"><?= htmlspecialchars($user['role']) ?></div>
                        </div>
                        <svg class="w-4 h-4 ml-1 transition-transform group-hover:rotate-180 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div class="absolute right-0 w-64 pt-2 hidden group-hover:block z-[9999] animate-slideDown">
                        <div class="bg-slate-800 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden py-1.5">
                            <div class="px-4 py-3 border-b border-gray-700">
                                <p class="text-sm font-bold text-white"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($user['email']) ?></p>
                                <span class="inline-block mt-1 px-2 py-0.5 text-[10px] font-bold uppercase rounded-full bg-indigo-900/60 text-indigo-300 border border-indigo-700">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </div>

                            <div class="py-1">
                                <?php if ($user['role'] === 'admin' || $user['role'] === 'instructor'): ?>
                                    <a href="add-course.php" class="flex items-center px-4 py-2.5 text-sm text-indigo-300 hover:bg-indigo-600 hover:text-white transition">
                                        <i class="fa-solid fa-plus-circle w-5 mr-2"></i> เพิ่มคอร์สเรียน
                                    </a>
                                <?php endif; ?>
                                <a href="profile.php" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-indigo-600 hover:text-white transition">
                                    <i class="fa-regular fa-user w-5 mr-2"></i> โปรไฟล์ของฉัน
                                </a>
                                <a href="quiz-history.php" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-indigo-600 hover:text-white transition">
                                    <i class="fa-solid fa-clock-rotate-left w-5 mr-2"></i> ประวัติการทำแบบทดสอบ
                                </a>
                            </div>

                            <div class="border-t border-gray-700 my-1"></div>
                            <a href="logout.php" class="flex items-center px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/20 transition font-medium">
                                <i class="fa-solid fa-arrow-right-from-bracket w-5 mr-2"></i> ออกจากระบบ
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Guest Buttons -->
                <div class="hidden md:flex items-center space-x-3">
                    <a href="login.php" class="text-gray-300 hover:text-white font-medium px-4 py-2 rounded-xl transition hover:bg-slate-800">
                        เข้าสู่ระบบ
                    </a>
                    <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-xl transition shadow-md shadow-indigo-600/30">
                        ลงทะเบียน
                    </a>
                </div>
            <?php endif; ?>

            <!-- Mobile Notification & Menu Toggle -->
            <div class="md:hidden flex items-center space-x-2">
                <?php if ($user): ?>
                    <a href="notifications_all.php" class="relative p-2 text-gray-300 hover:text-white transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <?php if ($unread_count > 0): ?>
                            <span class="noti-badge" style="width:14px; height:14px; font-size:8px; top:4px; right:4px; border-width: 1px;"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <button id="menu-btn" class="p-2 text-gray-300" aria-label="Open mobile menu">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            
        </div>
    </div>
</nav>

<!-- Mobile Overlay & Menu -->
<div id="menu-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60] hidden transition-opacity duration-300 opacity-0"></div>

<div id="mobile-menu" class="fixed top-0 left-0 h-full w-[75%] max-w-[320px] bg-slate-900 z-[70] transform -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl border-r border-gray-800 flex flex-col justify-between">
    <div class="p-6">
        <div class="flex justify-between items-center mb-8">
            <div class="text-xl font-bold bg-gradient-to-r from-indigo-400 to-purple-400 bg-clip-text text-transparent">
                E-Learning
            </div>
            <button id="close-btn" class="text-gray-400 hover:text-white focus:outline-none" aria-label="Close menu">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="flex flex-col space-y-4">
            <a href="index.php" class="text-gray-300 hover:text-white text-base py-2 flex items-center border-b border-gray-800">
                <i class="fa-solid fa-house w-6 text-indigo-400 mr-2"></i> หน้าหลัก
            </a>
            <a href="courses_all.php" class="text-gray-300 hover:text-white text-base py-2 flex items-center border-b border-gray-800">
                <i class="fa-solid fa-graduation-cap w-6 text-indigo-400 mr-2"></i> คอร์สทั้งหมด
            </a>
            <a href="about.php" class="text-gray-300 hover:text-white text-base py-2 flex items-center border-b border-gray-800">
                <i class="fa-solid fa-circle-info w-6 text-indigo-400 mr-2"></i> เกี่ยวกับเรา
            </a>
            <a href="contact.php" class="text-gray-300 hover:text-white text-base py-2 flex items-center border-b border-gray-800">
                <i class="fa-solid fa-envelope w-6 text-indigo-400 mr-2"></i> ติดต่อ
            </a>
            <?php if ($user && ($user['role'] === 'admin' || $user['role'] === 'instructor')): ?>
                <a href="add-course.php" class="text-indigo-400 hover:text-indigo-300 text-base py-2 flex items-center border-b border-gray-800 font-semibold">
                    <i class="fa-solid fa-plus-circle w-6 mr-2"></i> เพิ่มคอร์สเรียน
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-6 border-t border-gray-800">
        <?php if ($user): ?>
            <div class="mb-4 flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-700">
                    <img src="<?= $userAvatar ?>" class="w-full h-full object-cover">
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-white truncate"><?= htmlspecialchars($user['username']) ?></p>
                    <span class="text-[10px] text-indigo-400 uppercase font-semibold"><?= htmlspecialchars($user['role']) ?></span>
                </div>
            </div>
            <div class="space-y-2">
                <a href="profile.php" class="block w-full py-2.5 px-3 rounded-xl bg-slate-800 text-gray-300 hover:text-white text-sm text-center">
                    โปรไฟล์ของฉัน
                </a>
                <a href="quiz-history.php" class="block w-full py-2.5 px-3 rounded-xl bg-slate-800 text-gray-300 hover:text-white text-sm text-center">
                    ประวัติการสอบ
                </a>
                <a href="logout.php" class="block w-full py-2.5 px-3 rounded-xl bg-red-600/90 hover:bg-red-600 text-white text-sm text-center font-bold">
                    ออกจากระบบ
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-2">
                <a href="login.php" class="block w-full py-2.5 px-3 rounded-xl bg-slate-800 text-white text-sm text-center font-medium">
                    เข้าสู่ระบบ
                </a>
                <a href="register.php" class="block w-full py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm text-center font-medium">
                    ลงทะเบียน
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const menuBtn = document.getElementById('menu-btn');
    const closeBtn = document.getElementById('close-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('menu-overlay');

    if (menuBtn && closeBtn && mobileMenu && overlay) {
        function openMenu() {
            overlay.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.add('opacity-100');
                mobileMenu.classList.remove('-translate-x-full');
            }, 10);
            document.body.style.overflow = 'hidden'; 
        }

        function closeMenu() {
            overlay.classList.remove('opacity-100');
            mobileMenu.classList.add('-translate-x-full');
            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
            document.body.style.overflow = ''; 
        }

        menuBtn.addEventListener('click', openMenu);
        closeBtn.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);
    }
</script>