import { db } from '../config/database';
import { RowDataPacket } from 'mysql2/promise';
import { AuthRequest } from '../middlewares/auth';
export async function canManage(req:AuthRequest,courseId:string|number) {
 const [user]=await db.execute<RowDataPacket[]>('SELECT role FROM users WHERE user_id=?',[req.user!.id]);
 const [course]=await db.execute<RowDataPacket[]>('SELECT instructor_id FROM courses WHERE course_id=?',[courseId]);
 return !!course.length && (user[0]?.role==='admin' || (user[0]?.role==='instructor' && Number(course[0].instructor_id)===Number(req.user!.id)));
}
