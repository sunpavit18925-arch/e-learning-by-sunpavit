function enrollCourse(courseId) {
    if(confirm("คุณต้องการสมัครเรียนคอร์สนี้ใช่หรือไม่?")) {
        
        // ใช้ FormData แทนการเขียน string ต่อกันเอง
        const formData = new FormData();
        formData.append('course_id', courseId);
        
        fetch('enrollments.php', {
            method: 'POST',
            body: formData // ไม่ต้องใส่ Headers 'Content-Type' เพราะ FormData จัดการให้เอง
        })
        .then(response => {
            // เช็คว่าไฟล์ PHP มีอยู่จริงไหม (ถ้า 404 จะตกไปที่ catch)
            if (!response.ok) throw new Error('ไม่พบไฟล์ enrollments.php หรือ Server ขัดข้อง');
            return response.json();
        })
        .then(data => {
            if(data.status === 'success') {
                alert(data.message);
                window.location.reload(); // สมัครสำเร็จแล้วรีโหลดหน้า
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("ผิดพลาด: " + error.message);
        });
    }
}