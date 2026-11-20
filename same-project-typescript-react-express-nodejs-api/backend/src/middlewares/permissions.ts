import { db } from '../config/database';
import { RowDataPacket } from 'mysql2/promise';
import { AuthRequest } from '../middlewares/auth';
export async function canManage(req:AuthRequest,courseId:string|number) {
 const [rows]=await db.execute<RowDataPacket[]>('SELECT u.role,c.instructor_id FROM users u JOIN courses c ON c.course_id=? WHERE u.user_id=?',[courseId,req.user!.id]);
 return !!rows.length && (rows[0].role==='admin' || (rows[0].role==='instructor' && Number(rows[0].instructor_id)===Number(req.user!.id)));
}
