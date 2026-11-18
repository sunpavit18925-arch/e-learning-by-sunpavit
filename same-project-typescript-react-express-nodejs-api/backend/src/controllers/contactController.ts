import {Request,Response,NextFunction} from 'express';
import {RowDataPacket,ResultSetHeader} from 'mysql2/promise';
import {db} from '../config/database';
import {wrap,AuthRequest} from '../middlewares/auth';
export function adminOnly(req:AuthRequest,res:Response,next:NextFunction){db.execute<RowDataPacket[]>('SELECT role FROM users WHERE user_id=?',[req.user!.id]).then(([rows])=>{if(rows[0]?.role!=='admin'){res.status(403).json({message:'เฉพาะผู้ดูแลระบบเท่านั้น'});return}next()}).catch(next)}
export const sendMessage=wrap(async(req,res)=>{
 const fields=['name','email','subject','message'] as const;const data=Object.fromEntries(fields.map(k=>[k,typeof req.body[k]==='string'?req.body[k].trim():'']));
 if(fields.some(k=>!data[k])||data.name.length>120||data.email.length>254||data.subject.length>200||data.message.length>10000||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)){res.status(400).json({message:'กรุณากรอกข้อมูลให้ครบและระบุอีเมลที่ถูกต้อง'});return}
 const [result]=await db.execute<ResultSetHeader>('INSERT INTO contact_messages (name,email,subject,message) VALUES (?,?,?,?)',[data.name,data.email,data.subject,data.message]);
 res.status(201).json({message_id:result.insertId});
});
export const listMessages=wrap(async(req,res)=>{
 const status=['unread','read','resolved'].includes(String(req.query.status))?String(req.query.status):'';
 const search=String(req.query.search||'').slice(0,200),page=Math.min(1000000,Math.max(1,Math.floor(Number(req.query.page)||1))),size=20;
 const where:string[]=[];const params:string[]=[];if(status){where.push('status=?');params.push(status)}if(search){where.push('(name LIKE ? OR email LIKE ? OR subject LIKE ?)');params.push(...Array(3).fill('%'+search+'%'))}
 const filter=where.length?'WHERE '+where.join(' AND '):'';
 const [count]=await db.execute<RowDataPacket[]>('SELECT COUNT(*) total FROM contact_messages '+filter,params);
 const [items]=await db.execute('SELECT message_id,name,email,subject,status,created_at FROM contact_messages '+filter+` ORDER BY created_at DESC,message_id DESC LIMIT ${size} OFFSET ${(page-1)*size}`,params);
 const [summary]=await db.query<RowDataPacket[]>("SELECT COUNT(*) total,SUM(status='unread') unread,SUM(status='resolved') resolved FROM contact_messages");
 res.json({items,total:count[0].total,page,summary:summary[0]});
});
export const getMessage=wrap(async(req,res)=>{const [rows]=await db.execute<RowDataPacket[]>('SELECT * FROM contact_messages WHERE message_id=?',[req.params.id]);if(!rows.length){res.status(404).json({message:'ไม่พบข้อความ'});return}res.json(rows[0])});
export const updateMessage=wrap(async(req,res)=>{if(!['unread','read','resolved'].includes(req.body.status)){res.status(400).json({message:'สถานะไม่ถูกต้อง'});return}const [r]=await db.execute<ResultSetHeader>('UPDATE contact_messages SET status=? WHERE message_id=?',[req.body.status,req.params.id]);if(!r.affectedRows){res.status(404).json({message:'ไม่พบข้อความ'});return}res.json({status:req.body.status})});

