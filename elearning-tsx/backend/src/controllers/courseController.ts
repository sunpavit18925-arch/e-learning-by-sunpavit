import { RowDataPacket, ResultSetHeader } from 'mysql2/promise';
import { db } from '../config/database';
import { wrap } from '../middlewares/auth';
import { canManage } from '../middlewares/permissions';

const courseSelect = `SELECT c.*, cat.name category_name, u.first_name, u.last_name, u.image instructor_image FROM courses c JOIN users u ON u.user_id=c.instructor_id LEFT JOIN categories cat ON cat.category_id=c.category_id`;
export const listCourses = wrap(async (_req,res)=>{const [rows]=await db.query(`${courseSelect} ORDER BY c.course_id DESC`);res.json(rows)});
export const categories = wrap(async (_req,res)=>{const [rows]=await db.query('SELECT category_id,name FROM categories ORDER BY category_id');res.json(rows)});
export const instructors = wrap(async (_req,res)=>{const [rows]=await db.query("SELECT user_id,username,first_name,last_name FROM users WHERE role IN ('admin','instructor') ORDER BY first_name");res.json(rows)});
export const getCourse = wrap(async (req,res)=>{
 const [rows]=await db.execute<RowDataPacket[]>(`${courseSelect} WHERE c.course_id=?`,[req.params.id]);
 if(!rows.length){res.status(404).json({message:'ไม่พบคอร์ส'});return}
 const [lessons]=await db.execute('SELECT * FROM lessons WHERE course_id=? ORDER BY order_index,lesson_id',[req.params.id]);
 const [quizzes]=await db.execute<RowDataPacket[]>('SELECT * FROM quizzes WHERE course_id=? ORDER BY quiz_id LIMIT 1',[req.params.id]);
 const [completed]=await db.execute<RowDataPacket[]>('SELECT p.lesson_id FROM user_lesson_progress p JOIN lessons l ON p.lesson_id=l.lesson_id WHERE p.user_id=? AND l.course_id=? AND p.is_completed=1',[req.user!.id,req.params.id]);
 const [enrollment]=await db.execute<RowDataPacket[]>('SELECT * FROM enrollments WHERE user_id=? AND course_id=? LIMIT 1',[req.user!.id,req.params.id]);
 const [attempts]=quizzes.length ? await db.execute<RowDataPacket[]>('SELECT * FROM quiz_attempts WHERE user_id=? AND quiz_id=? ORDER BY attempt_date DESC,attempt_id DESC',[req.user!.id,quizzes[0].quiz_id]) : [[]];
 res.json({...rows[0],lessons,quiz:quizzes[0]||null,completed:completed.map(x=>x.lesson_id),enrolled:!!enrollment.length,latestAttempt:attempts[0]||null,hasPassed:attempts.some(a=>a.is_passed),canManage:await canManage(req,req.params.id)});
});
export const myCourses = wrap(async(req,res)=>{const [rows]=await db.execute(`SELECT c.*,cat.name category_name,e.enrollment_date,e.progress_percentage FROM enrollments e JOIN courses c ON c.course_id=e.course_id LEFT JOIN categories cat ON c.category_id=cat.category_id WHERE e.user_id=? ORDER BY e.enrollment_date DESC`,[req.user!.id]);res.json(rows)});
export const enroll = wrap(async(req,res)=>{const [course]=await db.execute<RowDataPacket[]>('SELECT course_id FROM courses WHERE course_id=?',[req.params.id]);if(!course.length){res.status(404).json({message:'ไม่พบคอร์ส'});return}const [existing]=await db.execute<RowDataPacket[]>('SELECT enrollment_id FROM enrollments WHERE user_id=? AND course_id=?',[req.user!.id,req.params.id]);if(existing.length){res.json({message:'คุณลงทะเบียนคอร์สนี้แล้ว'});return}await db.execute('INSERT INTO enrollments (user_id,course_id) VALUES (?,?)',[req.user!.id,req.params.id]);res.status(201).json({message:'ลงทะเบียนสำเร็จ'})});
export const history = wrap(async(req,res)=>{const [rows]=await db.execute('SELECT a.*,q.title,q.passing_score,q.time_limit,c.title course_title,c.course_id FROM quiz_attempts a JOIN quizzes q ON q.quiz_id=a.quiz_id JOIN courses c ON c.course_id=q.course_id WHERE a.user_id=? ORDER BY a.attempt_date DESC,a.attempt_id DESC',[req.user!.id]);res.json(rows)});
export const createCourse = wrap(async(req,res)=>{
 const [users]=await db.execute<RowDataPacket[]>('SELECT role FROM users WHERE user_id=?',[req.user!.id]);const role=users[0]?.role;if(!['admin','instructor'].includes(role)){res.status(403).json({message:'คุณไม่มีสิทธิ์เพิ่มคอร์ส'});return}
 const {title,description,category_id}=req.body;if(!title?.trim()||!description?.trim()){res.status(400).json({message:'กรุณากรอกชื่อคอร์สและรายละเอียดให้ครบถ้วน'});return}
 const instructor=role==='admin'&&Number(req.body.instructor_id)>0?Number(req.body.instructor_id):req.user!.id;
 const [instructorRows]=await db.execute<RowDataPacket[]>("SELECT user_id FROM users WHERE user_id=? AND role IN ('admin','instructor')",[instructor]);if(!instructorRows.length){res.status(400).json({message:'ไม่พบผู้สอน'});return}
 const conn=await db.getConnection();try{await conn.beginTransaction();const [result]=await conn.execute<ResultSetHeader>('INSERT INTO courses (title,description,price,instructor_id,category_id,thumbnail_url) VALUES (?,?,0,?,?,?)',[title.trim(),description.trim(),instructor,Number(category_id)||1,req.file?.filename||'pic_default.jpg']);const id=result.insertId;const [all]=await conn.query<RowDataPacket[]>('SELECT user_id FROM users');for(const u of all)await conn.execute('INSERT INTO notifications (user_id,title,message,link,is_read) VALUES (?,?,?,?,0)',[u.user_id,u.user_id===instructor?'สร้างคอร์สสำเร็จ!':'คอร์สเรียนใหม่แกะกล่อง!',u.user_id===instructor?`คอร์ส '${title}' ของคุณพร้อมให้เข้าเรียนแล้ว`:`เปิดตัวคอร์สใหม่: ${title} เริ่มเรียนได้แล้ววันนี้`,`/courses/${id}`]);await conn.commit();res.status(201).json({course_id:id});}catch(e){await conn.rollback();throw e}finally{conn.release()}
});
export const updateCourse = wrap(async(req,res)=>{
 if(!await canManage(req,req.params.id)){res.status(403).json({message:'คุณไม่มีสิทธิ์แก้ไขคอร์สนี้'});return}
 const {title,description,category_id}=req.body;if(!title?.trim()||!description?.trim()){res.status(400).json({message:'กรุณากรอกชื่อคอร์สและรายละเอียด'});return}
 const [users]=await db.execute<RowDataPacket[]>('SELECT role FROM users WHERE user_id=?',[req.user!.id]);
 const [courses]=await db.execute<RowDataPacket[]>('SELECT instructor_id,thumbnail_url FROM courses WHERE course_id=?',[req.params.id]);
 const instructor=users[0]?.role==='admin'&&Number(req.body.instructor_id)>0?Number(req.body.instructor_id):courses[0].instructor_id;
 const [available]=await db.execute<RowDataPacket[]>("SELECT user_id FROM users WHERE user_id=? AND role IN ('admin','instructor')",[instructor]);if(!available.length){res.status(400).json({message:'ไม่พบผู้สอน'});return}
 await db.execute('UPDATE courses SET title=?,description=?,category_id=?,instructor_id=?,thumbnail_url=? WHERE course_id=?',[title.trim(),description.trim(),Number(category_id),instructor,req.file?.filename||courses[0].thumbnail_url,req.params.id]);res.json({message:'บันทึกข้อมูลคอร์สเรียบร้อยแล้ว'})
});
export const deleteCourse = wrap(async(req,res)=>{
 const [u]=await db.execute<RowDataPacket[]>('SELECT role FROM users WHERE user_id=?',[req.user!.id]);if(u[0]?.role!=='admin'){res.status(403).json({message:'เฉพาะผู้ดูแลระบบ'});return}
 const conn=await db.getConnection();try{await conn.beginTransaction();await conn.execute('DELETE FROM enrollments WHERE course_id=?',[req.params.id]);await conn.execute('DELETE FROM courses WHERE course_id=?',[req.params.id]);await conn.commit();res.json({message:'ลบคอร์สเรียบร้อยแล้ว'})}catch(e){await conn.rollback();throw e}finally{conn.release()}
});
