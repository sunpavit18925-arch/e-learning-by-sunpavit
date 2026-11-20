import { RowDataPacket, ResultSetHeader } from 'mysql2/promise';
import PDFDocument from 'pdfkit';
import fs from 'fs';
import { db } from '../config/database';
import { wrap } from '../middlewares/auth';
import { canManage } from '../middlewares/permissions';

export const progress=wrap(async(req,res)=>{const [lessons]=await db.execute<RowDataPacket[]>('SELECT course_id FROM lessons WHERE lesson_id=?',[req.params.id]);if(!lessons.length){res.status(404).json({message:'ไม่พบบทเรียน'});return}const course=lessons[0].course_id;const conn=await db.getConnection();try{await conn.beginTransaction();const [enrolled]=await conn.execute<RowDataPacket[]>('SELECT enrollment_id FROM enrollments WHERE user_id=? AND course_id=?',[req.user!.id,course]);if(!enrolled.length)await conn.execute('INSERT INTO enrollments (user_id,course_id) VALUES (?,?)',[req.user!.id,course]);const [old]=await conn.execute<RowDataPacket[]>('SELECT progress_id,is_completed FROM user_lesson_progress WHERE user_id=? AND lesson_id=? LIMIT 1',[req.user!.id,req.params.id]);const completed=old.length?!old[0].is_completed:true;if(old.length)await conn.execute('UPDATE user_lesson_progress SET is_completed=?,completed_at=NOW() WHERE progress_id=?',[completed?1:0,old[0].progress_id]);else await conn.execute('INSERT INTO user_lesson_progress (user_id,lesson_id,is_completed,completed_at) VALUES (?,?,1,NOW())',[req.user!.id,req.params.id]);const [stats]=await conn.execute<RowDataPacket[]>(`SELECT COUNT(*) total, SUM(EXISTS(SELECT 1 FROM user_lesson_progress p WHERE p.lesson_id=l.lesson_id AND p.user_id=? AND p.is_completed=1)) completed FROM lessons l WHERE l.course_id=?`,[req.user!.id,course]);const percentage=stats[0].total?Math.round(Number(stats[0].completed)/stats[0].total*100):0;await conn.execute('UPDATE enrollments SET progress_percentage=? WHERE user_id=? AND course_id=?',[percentage,req.user!.id,course]);await conn.commit();res.json({is_completed:completed,progress_percentage:percentage});}catch(e){await conn.rollback();throw e}finally{conn.release()}});
export const saveLesson=wrap(async(req,res)=>{if(!await canManage(req,req.params.id)){res.status(403).json({message:'คุณไม่มีสิทธิ์จัดการบทเรียน'});return}const {title,content_url,content_type,order_index}=req.body;if(!title?.trim()||!['video','text','quiz'].includes(content_type)){res.status(400).json({message:'ข้อมูลบทเรียนไม่ถูกต้อง'});return}if(req.params.lessonId)await db.execute('UPDATE lessons SET title=?,content_type=?,content_url=?,order_index=? WHERE lesson_id=? AND course_id=?',[title.trim(),content_type,content_url||'',Number(order_index)||1,req.params.lessonId,req.params.id]);else await db.execute('INSERT INTO lessons (course_id,title,content_type,content_url,order_index) VALUES (?,?,?,?,?)',[req.params.id,title.trim(),content_type,content_url||'',Number(order_index)||1]);res.json({message:'บันทึกบทเรียนเรียบร้อยแล้ว'})});
export const deleteLesson=wrap(async(req,res)=>{if(!await canManage(req,req.params.id)){res.status(403).json({message:'คุณไม่มีสิทธิ์จัดการบทเรียน'});return}await db.execute('DELETE FROM lessons WHERE lesson_id=? AND course_id=?',[req.params.lessonId,req.params.id]);res.json({message:'ลบบทเรียนเรียบร้อยแล้ว'})});
async function getQuiz(courseId:string, includeAnswers:boolean) {
 const [quizzes]=await db.execute<RowDataPacket[]>('SELECT * FROM quizzes WHERE course_id=? ORDER BY quiz_id LIMIT 1',[courseId]);
 if(!quizzes.length)return null;
 const [questions]=await db.execute<RowDataPacket[]>('SELECT question_id,quiz_id,question_text,question_type,points FROM questions WHERE quiz_id=? ORDER BY question_id',[quizzes[0].quiz_id]);
 const [options]=await db.execute<RowDataPacket[]>(
  'SELECT o.option_id,o.question_id,o.option_text'+(includeAnswers?',o.is_correct':'')+' FROM question_options o JOIN questions q ON q.question_id=o.question_id WHERE q.quiz_id=? ORDER BY o.question_id,o.option_id',
  [quizzes[0].quiz_id]);
 const grouped=new Map<number,RowDataPacket[]>();
 for(const option of options){const group=grouped.get(option.question_id)||[];group.push(option);grouped.set(option.question_id,group)}
 return {...quizzes[0],questions:questions.map(q=>({...q,options:grouped.get(q.question_id)||[]}))};
}
export const quiz=wrap(async(req,res)=>{const data=await getQuiz(req.params.id,false);if(!data){res.status(404).json({message:'คอร์สเรียนนี้ยังไม่มีแบบทดสอบ'});return}res.json(data)});
export const editQuiz=wrap(async(req,res)=>{if(!await canManage(req,req.params.id)){res.status(403).json({message:'คุณไม่มีสิทธิ์จัดการแบบทดสอบ'});return}res.json(await getQuiz(req.params.id,true))});
export const saveQuiz=wrap(async(req,res)=>{if(!await canManage(req,req.params.id)){res.status(403).json({message:'คุณไม่มีสิทธิ์จัดการแบบทดสอบ'});return}const {title,time_limit,passing_score,questions,deleted_questions,deleted_options}=req.body;if(!Array.isArray(questions)||!questions.length||!title?.trim()){res.status(400).json({message:'กรุณาระบุชื่อแบบทดสอบและคำถาม'});return}for(const q of questions){if(!q.question_text?.trim()||!Array.isArray(q.options)||q.options.length<2||q.options.some((o:{option_text:string})=>!o.option_text?.trim())||q.options.filter((o:{is_correct:boolean})=>o.is_correct).length!==1){res.status(400).json({message:'แต่ละคำถามต้องมีตัวเลือกอย่างน้อย 2 ข้อและเฉลย 1 ข้อ'});return}}const conn=await db.getConnection();try{await conn.beginTransaction();const [found]=await conn.execute<RowDataPacket[]>('SELECT quiz_id FROM quizzes WHERE course_id=? ORDER BY quiz_id LIMIT 1',[req.params.id]);let id:number;if(found.length){id=found[0].quiz_id;await conn.execute('UPDATE quizzes SET title=?,time_limit=?,passing_score=? WHERE quiz_id=?',[title.trim(),Math.max(0,Number(time_limit)||0),Math.max(1,Math.min(100,Number(passing_score)||60)),id])}else{const [r]=await conn.execute<ResultSetHeader>('INSERT INTO quizzes (course_id,title,time_limit,passing_score) VALUES (?,?,?,?)',[req.params.id,title.trim(),Math.max(0,Number(time_limit)||0),Math.max(1,Math.min(100,Number(passing_score)||60))]);id=r.insertId}for(const qid of Array.isArray(deleted_questions)?deleted_questions:[])await conn.execute('DELETE FROM questions WHERE question_id=? AND quiz_id=?',[qid,id]);for(const oid of Array.isArray(deleted_options)?deleted_options:[])await conn.execute('DELETE o FROM question_options o JOIN questions q ON q.question_id=o.question_id WHERE o.option_id=? AND q.quiz_id=?',[oid,id]);for(const q of questions){let qid=Number(q.question_id)||0;if(qid){const [owned]=await conn.execute<RowDataPacket[]>('SELECT question_id FROM questions WHERE question_id=? AND quiz_id=?',[qid,id]);if(!owned.length)throw Object.assign(new Error('ไม่พบคำถาม'),{status:400});await conn.execute('UPDATE questions SET question_text=?,points=? WHERE question_id=?',[q.question_text.trim(),Math.max(1,Number(q.points)||1),qid])}else{const [r]=await conn.execute<ResultSetHeader>('INSERT INTO questions (quiz_id,question_text,points) VALUES (?,?,?)',[id,q.question_text.trim(),Math.max(1,Number(q.points)||1)]);qid=r.insertId}for(const o of q.options){if(o.option_id){await conn.execute('UPDATE question_options SET option_text=?,is_correct=? WHERE option_id=? AND question_id=?',[o.option_text.trim(),o.is_correct?1:0,o.option_id,qid])}else await conn.execute('INSERT INTO question_options (question_id,option_text,is_correct) VALUES (?,?,?)',[qid,o.option_text.trim(),o.is_correct?1:0])}}await conn.commit();res.json({message:'บันทึกแบบทดสอบเรียบร้อยแล้ว',quiz_id:id})}catch(e){await conn.rollback();throw e}finally{conn.release()}});
export const submitQuiz=wrap(async(req,res)=>{
 const [quizzes]=await db.execute<RowDataPacket[]>('SELECT * FROM quizzes WHERE quiz_id=?',[req.params.id]);
 if(!quizzes.length){res.status(404).json({message:'ไม่พบแบบทดสอบ'});return}
 const [questions]=await db.execute<RowDataPacket[]>('SELECT question_id,points FROM questions WHERE quiz_id=? ORDER BY question_id',[req.params.id]);
 if(!questions.length){res.status(400).json({message:'แบบทดสอบนี้ยังไม่มีคำถาม'});return}
 const answers=req.body.answers||{};
 const conn=await db.getConnection();
 try{
  await conn.beginTransaction();
  const [options]=await conn.execute<RowDataPacket[]>('SELECT o.option_id,o.question_id,o.is_correct FROM question_options o JOIN questions q ON q.question_id=o.question_id WHERE q.quiz_id=?',[req.params.id]);
  const byId=new Map(options.map(o=>[Number(o.option_id),o]));
  let earned=0,total=0,correct=0;
  const saves:{question:number;option:number;correct:number}[]=[];
  for(const q of questions){
   const points=Math.max(1,Number(q.points)||1);total+=points;
   const option=Number(answers[q.question_id]);
   if(option){
    const found=byId.get(option);
    if(!found||Number(found.question_id)!==Number(q.question_id))throw Object.assign(new Error('ตัวเลือกไม่ถูกต้อง'),{status:400});
    const isCorrect=Number(found.is_correct)===1;
    if(isCorrect){earned+=points;correct++}
    saves.push({question:q.question_id,option,correct:isCorrect?1:0});
   }
  }
  const score=Math.round(earned/total*100),passed=score>=Number(quizzes[0].passing_score);
  const [result]=await conn.execute<ResultSetHeader>('INSERT INTO quiz_attempts (user_id,quiz_id,score_obtained,is_passed,time_spent_seconds) VALUES (?,?,?,?,?)',[req.user!.id,req.params.id,score,passed?1:0,Math.max(0,Math.round(Number(req.body.time_spent)||0))]);
  for(let start=0;start<saves.length;start+=500){
   const batch=saves.slice(start,start+500);
   await conn.execute('INSERT INTO student_answers (attempt_id,question_id,option_id,is_correct) VALUES '+batch.map(()=>'(?,?,?,?)').join(','),batch.flatMap(a=>[result.insertId,a.question,a.option,a.correct]));
  }
  if(passed)await conn.execute('UPDATE enrollments SET progress_percentage=100 WHERE user_id=? AND course_id=?',[req.user!.id,quizzes[0].course_id]);
  await conn.commit();
  res.json({score_percent:score,passing_score:quizzes[0].passing_score,is_passed:passed,correct_count:correct,total_questions:questions.length,course_id:quizzes[0].course_id});
 }catch(e){await conn.rollback();throw e}finally{conn.release()}
});
export const leaderboard=wrap(async(req,res)=>{
 const [quiz]=await db.execute<RowDataPacket[]>('SELECT q.*,c.title course_title FROM quizzes q JOIN courses c ON c.course_id=q.course_id WHERE q.course_id=? ORDER BY q.quiz_id LIMIT 1',[req.params.id]);
 if(!quiz.length){res.status(404).json({message:'คอร์สนี้ยังไม่มีแบบทดสอบ'});return}
 const [rows]=await db.execute(
  'SELECT a.user_id,u.username,u.first_name,u.last_name,u.image,a.score_obtained top_score,a.time_spent_seconds best_time,a.has_passed,a.last_attempt_date FROM ('+
  'SELECT user_id,score_obtained,time_spent_seconds,MAX(is_passed) OVER(PARTITION BY user_id) has_passed,MAX(attempt_date) OVER(PARTITION BY user_id) last_attempt_date,'+
  'ROW_NUMBER() OVER(PARTITION BY user_id ORDER BY score_obtained DESC,time_spent_seconds ASC,attempt_date DESC,attempt_id DESC) position '+
  'FROM quiz_attempts WHERE quiz_id=?) a JOIN users u ON u.user_id=a.user_id WHERE a.position=1 '+
  'ORDER BY top_score DESC,best_time ASC,last_attempt_date ASC,a.user_id ASC LIMIT 10',[quiz[0].quiz_id]);
 res.json({quiz:quiz[0],rows});
});
export const certificate=wrap(async(req,res)=>{const [rows]=await db.execute<RowDataPacket[]>(`SELECT u.first_name,u.last_name,u.role,c.title,(SELECT a.attempt_date FROM quiz_attempts a JOIN quizzes q ON q.quiz_id=a.quiz_id WHERE a.user_id=u.user_id AND q.course_id=c.course_id AND a.is_passed=1 ORDER BY a.attempt_date DESC LIMIT 1) passed_at FROM users u JOIN courses c ON c.course_id=? WHERE u.user_id=?`,[req.params.id,req.user!.id]);const data=rows[0];if(!data){res.status(404).json({message:'ไม่พบข้อมูลคอร์ส'});return}if(!data.passed_at&&data.role!=='admin'){res.status(403).json({message:'คุณยังไม่ผ่านเกณฑ์การทดสอบสำหรับหลักสูตรนี้'});return}const doc=new PDFDocument({size:'A4',layout:'landscape',margin:60});const font='C:/Windows/Fonts/tahoma.ttf';if(fs.existsSync(font))doc.font(font);res.setHeader('Content-Type','application/pdf');res.setHeader('Content-Disposition',`inline; filename="certificate-${req.params.id}.pdf"`);doc.pipe(res);doc.rect(25,25,792-50,595-50).lineWidth(4).stroke('#1e3a8a');doc.fillColor('#1e3a8a').fontSize(26).text('E-LEARNING',60,135,{align:'center'});doc.fillColor('#334155').fontSize(16).text('ใบประกาศนียบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า',60,210,{align:'center'});doc.fillColor('#0f172a').fontSize(32).text(`${data.first_name} ${data.last_name}`,60,260,{align:'center'});doc.fillColor('#334155').fontSize(16).text('ได้ผ่านการอบรมหลักสูตร',60,330,{align:'center'});doc.fillColor('#1e3a8a').fontSize(23).text(data.title,60,365,{align:'center'});doc.fillColor('#64748b').fontSize(13).text(new Date(data.passed_at||Date.now()).toLocaleDateString('th-TH',{day:'numeric',month:'long',year:'numeric'}),60,455,{align:'center'});doc.end()});

