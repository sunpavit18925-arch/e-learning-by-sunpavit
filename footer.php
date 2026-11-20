<footer class="bg-slate-900 text-gray-400 py-10 mt-auto border-t border-slate-800">
    <div class="max-w-7xl mx-auto px-6 text-center">
        <div class="space-y-2">
            <p class="text-sm">
                &copy; <?php echo date('Y'); ?> <span class="text-gray-200">Department of Public Works and Town Planning (DPT).</span> All rights reserved.
            </p>
            <p class="text-sm font-medium text-gray-400 tracking-wide">
                Information Technology Center | ศูนย์เทคโนโลยีสารสนเทศ กรมโยธาธิการและผังเมือง
            </p>
        </div>
        
        <div class="site-footer-links mt-6 flex justify-center space-x-4 text-xs text-gray-500">
            <a href="about.php" class="hover:text-white transition">เกี่ยวกับเรา</a>
            <span>•</span>
            <a href="contact.php" class="hover:text-white transition">ติดต่อสอบถาม</a>
            <span>•</span>
            <a href="courses_all.php" class="hover:text-white transition">คอร์สเรียนทั้งหมด</a>
        </div>
    </div>
</footer>

<script>
    // ตรวจสอบการแจ้งเตือนแบบเรียลไทม์ทุก 30 วินาที
    if (typeof window.notiInterval === 'undefined') {
        window.notiInterval = setInterval(function() {
            fetch('get_notifications.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(data => {
                    if (data && data.status === 'success') {
                        const badges = document.querySelectorAll('.noti-badge');
                        if (data.unread_count > 0) {
                            badges.forEach(b => {
                                b.textContent = data.unread_count;
                                b.style.display = 'flex';
                            });
                        } else {
                            badges.forEach(b => {
                                b.style.display = 'none';
                            });
                        }
                    }
                })
                .catch(() => {});
        }, 30000);
    }
</script>

</body>
</html>