<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connection/connection.php';
?>

<?php include 'header.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-12 w-full flex-1">
    <!-- Breadcrumb -->
    <div class="flex items-center space-x-2 text-xs sm:text-sm text-slate-400 mb-6">
        <a href="index.php" class="hover:text-indigo-600 transition flex items-center gap-1">
            <i class="fa-solid fa-house"></i> หน้าหลัก
        </a>
        <span>/</span>
        <span class="text-slate-700 font-medium">เกี่ยวกับเรา</span>
    </div>

    <!-- Hero Card: ปรับเฉพาะสีพื้นหลังตามภาพ -->
    <div
        class="rounded-3xl p-8 sm:p-12 text-white shadow-xl mb-10 text-center relative overflow-hidden"
        style="background: linear-gradient(135deg, #2c2a78 0%, #0f182b 55%, #020919 100%);"
    >
        <div class="relative z-10 max-w-2xl mx-auto">
            <span class="inline-block px-4 py-1.5 bg-white/20 backdrop-blur-md rounded-full text-xs font-semibold tracking-wider uppercase mb-3">
                Department of Public Works and Town Planning
            </span>
            <h1 class="text-3xl sm:text-4xl font-extrabold mb-4 leading-tight">
                เกี่ยวกับระบบ E-Learning
            </h1>
            <p class="text-indigo-100 text-sm sm:text-base font-light leading-relaxed">
                แพลตฟอร์มการเรียนรู้ออนไลน์เพื่อพัฒนาทักษะวิชาชีพ โดย กรมโยธาธิการและผังเมือง ร่วมกับ ศูนย์เทคโนโลยีสารสนเทศ
            </p>
        </div>
        <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-white/5 rounded-full blur-2xl"></div>
    </div>

    <!-- Information Sections -->
    <div class="space-y-8">
        <!-- Section 1 -->
        <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-slate-200">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mb-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                    🎯
                </span>
                <span>พันธกิจและวัตถุประสงค์ (Mission & Objectives)</span>
            </h2>
            <div class="text-slate-600 leading-relaxed space-y-3 text-sm sm:text-base">
                <p>
                    ระบบการเรียนรู้ออนไลน์ (E-Learning) นี้ พัฒนาขึ้นโดยกรมโยธาธิการและผังเมือง ร่วมกับศูนย์เทคโนโลยีสารสนเทศ
                    เพื่อเป็นศูนย์กลางการถ่ายทอดองค์ความรู้ ข้อมูลวิชาการ และหลักสูตรการฝึกอบรมที่จำเป็นสำหรับบุคลากรและประชาชนทั่วไป
                </p>
                <p>
                    เรามุ่งมั่นที่จะนำเสนอหลักสูตรที่มีคุณภาพ เข้าถึงได้สะดวกทุกที่ทุกเวลา สนับสนุนการเรียนรู้ตลอดชีวิต (Lifelong Learning)
                    และยกระดับศักยภาพในการปฏิบัติงานด้านการผังเมืองและงานโยธาธิการอย่างยั่งยืน
                </p>
            </div>
        </div>

        <!-- Section 2: Info Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="text-3xl mb-3">🏢</div>
                <h3 class="text-base font-bold text-slate-800 mb-2">หน่วยงานผู้รับผิดชอบ</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    กรมโยธาธิการและผังเมือง กระทรวงมหาดไทย
                </p>
            </div>

            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="text-3xl mb-3">💻</div>
                <h3 class="text-base font-bold text-slate-800 mb-2">การสนับสนุนด้านเทคโนโลยี</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    ศูนย์เทคโนโลยีสารสนเทศ กรมโยธาธิการและผังเมือง
                </p>
            </div>

            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200 hover:shadow-md transition">
                <div class="text-3xl mb-3">📜</div>
                <h3 class="text-base font-bold text-slate-800 mb-2">การรับรองผลการศึกษา</h3>
                <p class="text-slate-500 text-sm leading-relaxed">
                    ระบบประเมินผลผ่านแบบทดสอบมาตรฐานและออกใบประกาศนียบัตรแบบดิจิทัล
                </p>
            </div>
        </div>

        <!-- Section 3: Vision -->
        <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-slate-200">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mb-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                    🚀
                </span>
                <span>วิสัยทัศน์ (Vision)</span>
            </h2>
            <div class="text-slate-600 leading-relaxed space-y-3 text-sm sm:text-base">
                <p>
                    มุ่งสู่การเป็นศูนย์กลางแพลตฟอร์มการเรียนรู้ด้านการพัฒนาเมืองและโครงสร้างพื้นฐานระดับแนวหน้า
                    ที่เสริมสร้างบุคลากรรุ่นใหม่ให้มีความเชี่ยวชาญ พร้อมรับมือกับการเปลี่ยนแปลงทางเทคโนโลยีและการพัฒนาประเทศอย่างมีประสิทธิภาพ
                </p>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>