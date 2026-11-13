<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name)) $errors[] = 'กรุณากรอกชื่อผู้ติดต่อ';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'กรุณาระบุอีเมลที่ถูกต้อง';
    if (empty($subject)) $errors[] = 'กรุณาระบุหัวข้อเรื่อง';
    if (empty($message)) $errors[] = 'กรุณากรอกข้อความรายละเอียด';

    if (empty($errors)) {
        // หากส่งสำเร็จ (ในการใช้งานจริงสามารถส่งเมลหรือบันทึกลง DB ได้)
        $success = true;
    }
}
?>

<?php include 'header.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="index.php" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-house"></i> หน้าหลัก
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">ติดต่อเรา</span>
    </div>

    <!-- Header Banner -->
    <div class="text-center max-w-2xl mx-auto mb-10">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">ติดต่อสอบถามข้อมูล</h1>
        <p class="text-slate-500 text-sm mt-2">
            หากคุณมีข้อสงสัยเกี่ยวกับหลักสูตรการเรียน หรือพบปัญหาการใช้งานระบบ สามารถส่งข้อความถึงเราได้
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Contact Info Card -->
        <div class="lg:col-span-1 bg-gradient-to-br from-indigo-900 via-slate-900 to-slate-950 text-white rounded-3xl p-8 shadow-xl flex flex-col justify-between">
            <div>
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                    <span>🏢</span>
                    <span>ข้อมูลการติดต่อ</span>
                </h2>

                <div class="space-y-6 text-sm text-slate-300">
                    <div>
                        <div class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">หน่วยงาน</div>
                        <p class="font-semibold text-white">กรมโยธาธิการและผังเมือง</p>
                        <p class="text-xs text-slate-400 mt-0.5">ศูนย์เทคโนโลยีสารสนเทศ</p>
                    </div>

                    <div>
                        <div class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">สถานที่ตั้ง</div>
                        <p class="text-xs leading-relaxed text-slate-300">
                            ถนนพระราม 6 แขวงพญาไท เขตพญาไท กรุงเทพฯ 10400
                        </p>
                    </div>

                    <div>
                        <div class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">เวลาทำการ</div>
                        <p class="text-xs text-slate-300">วันจันทร์ - ศุกร์ : 08:30 - 16:30 น.</p>
                        <p class="text-[11px] text-slate-400">(เว้นวันหยุดราชการ)</p>
                    </div>

                    <div>
                        <div class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">เว็บไซต์หลัก</div>
                        <a href="https://www.dpt.go.th" target="_blank" class="text-indigo-300 hover:underline text-xs">
                            www.dpt.go.th
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-800 text-xs text-slate-500">
                Department of Public Works and Town Planning
            </div>
        </div>

        <!-- Form Card -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-slate-200">
            <?php if ($success): ?>
                <div class="p-6 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-800 mb-6 flex items-start gap-4">
                    <i class="fa-solid fa-circle-check text-2xl text-emerald-600 shrink-0 mt-0.5"></i>
                    <div>
                        <h3 class="font-bold text-base mb-1">ส่งข้อความเรียบร้อยแล้ว!</h3>
                        <p class="text-sm text-emerald-700 leading-relaxed">
                            เจ้าหน้าที่จะดำเนินการตรวจสอบและติดต่อกลับตามอีเมลที่คุณได้ระบุไว้โดยเร็วที่สุด
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm mb-6 space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-exclamation text-xs"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">ชื่อ-นามสกุล <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required
                               value="<?= htmlspecialchars($_POST['name'] ?? ($user['first_name'] . ' ' . $user['last_name'] ?? '')) ?>"
                               placeholder="ระบุชื่อของคุณ"
                               class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                    </div>

                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">อีเมลสำหรับติดต่อกลับ <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? ($user['email'] ?? '')) ?>"
                               placeholder="example@mail.com"
                               class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">หัวข้อเรื่อง <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" required
                           value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                           placeholder="เช่น สอบถามเกี่ยวกับคอร์สเรียน, ปัญหาการเข้าสู่ระบบ"
                           class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition">
                </div>

                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">ข้อความรายละเอียด <span class="text-red-500">*</span></label>
                    <textarea name="message" rows="5" required
                              placeholder="กรอกรายละเอียดข้อความที่ต้องการติดต่อ..."
                              class="w-full px-4 py-3 border border-slate-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white text-slate-800 text-sm transition resize-none"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                </div>

                <button type="submit" 
                        class="w-full sm:w-auto px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>ส่งข้อความ</span>
                </button>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>