import jwt from 'jsonwebtoken';
import {RowDataPacket} from 'mysql2/promise';
import {db} from '../config/database';
import {secret} from '../config/auth';
import {wrap} from '../middlewares/auth';
// Explicit demo identities from the supplied database copy. Never expose arbitrary accounts.
const accounts=['sunpavit','teacher_jane','teacher_mike','teacher_harvey','poppapon','sunpawit'];
const select=`SELECT user_id,username,first_name,last_name,role,image FROM users WHERE username IN (${accounts.map(()=>'?').join(',')})`;
export const demoUsers=wrap(async(_req,res)=>{
 if(process.env.DEMO_MODE!=='true'){res.status(404).json({message:'Demo mode is disabled'});return}
 const [rows]=await db.execute<RowDataPacket[]>(select,accounts);
 res.json(rows);
});
export const demoSession=wrap(async(req,res)=>{
 if(process.env.DEMO_MODE!=='true'){res.status(404).json({message:'Demo mode is disabled'});return}
 const id=Number(req.body.user_id);
 if(!Number.isSafeInteger(id)||id<=0){res.status(400).json({message:'กรุณาเลือกบัญชีทดสอบ'});return}
 const [rows]=await db.execute<RowDataPacket[]>(select.replace('role,image FROM','role,image,email,created_at FROM')+' AND user_id=?',[...accounts,id]);
 const user=rows[0];if(!user){res.status(404).json({message:'ไม่พบบัญชีทดสอบนี้'});return}
 const token=jwt.sign({id:user.user_id,role:user.role},secret!,{expiresIn:'8h'});
 res.json({token,user});
});

