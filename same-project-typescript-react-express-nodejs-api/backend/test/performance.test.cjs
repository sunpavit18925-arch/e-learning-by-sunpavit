const {test,before,beforeEach,after}=require('node:test');
const assert=require('node:assert/strict');
require('dotenv').config({quiet:true});
const jwt=require('jsonwebtoken');
const {db}=require('../dist/config/database');
const app=require('../dist/app').default;

// Copy table structures into a fresh, isolated database. Application data is never
// used for writes; only this generated fixture database is removed in cleanup.
const tables=['categories','users','courses','lessons','quizzes','questions','question_options','enrollments','user_lesson_progress','quiz_attempts','student_answers','notifications'];
let conn,server,base,testDatabase,statements=[];
const originals={execute:db.execute,query:db.query,getConnection:db.getConnection};
before(async()=>{
 conn=await db.getConnection();
 const [source]=await conn.query('SELECT DATABASE() name');
 testDatabase=`elearning_perf_test_${process.pid}_${Date.now()}`;
 await conn.query('CREATE DATABASE ?? CHARACTER SET utf8mb4',[testDatabase]);
 for(const table of tables)await conn.query('CREATE TABLE ??.?? LIKE ??.??',[testDatabase,table,source[0].name,table]);
 await conn.query('USE ??',[testDatabase]);
 for(const name of ['execute','query']){
  const original=conn[name].bind(conn);
  conn[name]=(...args)=>{statements.push(args[0]);return original(...args)};
  db[name]=(...args)=>conn[name](...args);
 }
 conn.release=()=>{};
 db.getConnection=async()=>conn;
 server=app.listen(0,'127.0.0.1');
 await new Promise(resolve=>server.once('listening',resolve));
 base='http://127.0.0.1:'+server.address().port+'/api';
});
beforeEach(async()=>{
 assert.equal((await conn.query('SELECT DATABASE() name'))[0][0].name,testDatabase);
 for(const table of [...tables].reverse())await conn.query(`DELETE FROM \`${table}\``);
 await conn.query("INSERT INTO categories(category_id,name) VALUES (1,'Category A'),(2,'Category B')");
 await conn.query("INSERT INTO users(user_id,username,password_hash,first_name,last_name,email,role) VALUES (1,'admin','test','Admin','User','a@test.invalid','admin'),(2,'teacher','test','Alice','Teacher','b@test.invalid','instructor'),(3,'student','test','Student','One','c@test.invalid','student'),(4,'other','test','Other','User','d@test.invalid','student')");
 const courses=Array.from({length:31},(_,i)=>[i+1,i===0?'100%_ literal course':'Course '+(i+1),'Description',2,i%2+1]);
 await conn.query('INSERT INTO courses(course_id,title,description,instructor_id,category_id) VALUES ?',[courses]);
 await conn.query("INSERT INTO quizzes(quiz_id,course_id,title,time_limit,passing_score) VALUES (1,1,'Quiz',10,60),(2,2,'Other quiz',0,60)");
 await seedQuestions(4);
 await conn.query("INSERT INTO lessons(lesson_id,course_id,title,content_type,content_url,order_index) VALUES (1,1,'One','text','',1),(2,1,'Two','text','',2)");
 await conn.query('INSERT INTO enrollments(user_id,course_id,progress_percentage) VALUES (3,1,0)');
 const notices=Array.from({length:26},(_,i)=>[i+1,3,'Notice '+i,'Message',i<8?0:1,'2026-01-01 00:00:00']);
 notices.push([999,4,'Private','Private message',0,'2026-01-01 00:00:00']);
 await conn.query('INSERT INTO notifications(noti_id,user_id,title,message,is_read,created_at) VALUES ?',[notices]);
});
after(async()=>{
 if(server){server.closeAllConnections();await new Promise(resolve=>server.close(resolve))}
 Object.assign(db,originals);
 if(conn){
  if(testDatabase&&/^elearning_perf_test_[0-9]+_[0-9]+$/.test(testDatabase))await conn.query('DROP DATABASE ??',[testDatabase]);
  conn.destroy();
 }
 await db.end();
});
async function seedQuestions(count){
 await conn.query('DELETE FROM question_options');await conn.query('DELETE FROM questions');
 await conn.query('INSERT INTO questions(question_id,quiz_id,question_text,points) VALUES ?',[Array.from({length:count},(_,i)=>[i+1,1,'Question '+i,i===0?3:1])]);
 const options=[];
 for(let i=0;i<count;i++)for(let j=0;j<4;j++)options.push([i*4+j+1,i+1,'Option '+j,j===0?1:0]);
 await conn.query('INSERT INTO question_options(option_id,question_id,option_text,is_correct) VALUES ?',[options]);
}
async function request(path,{user=3,role='student',method='GET',body}={}){
 statements=[];
 const response=await fetch(base+path,{method,signal:AbortSignal.timeout(10000),headers:{Authorization:'Bearer '+jwt.sign({id:user,role},process.env.JWT_SECRET),...(body?{'Content-Type':'application/json'}:{})},body:body?JSON.stringify(body):undefined});
 return {status:response.status,data:await response.json(),sql:[...statements]};
}
test('catalog paginates, clamps pages and searches literal wildcard characters',async()=>{
 const first=await request('/courses');assert.equal(first.data.items.length,9);assert.equal(first.data.total,31);
 const second=await request('/courses?page=2');assert.equal(second.data.items.length,9);
 assert.equal(new Set([...first.data.items,...second.data.items].map(c=>c.course_id)).size,18);
 const last=await request('/courses?page=999');assert.equal(last.data.page,4);assert.equal(last.data.items.length,4);
 const literal=await request('/courses?search='+encodeURIComponent('%_'));assert.equal(literal.data.total,1);assert.equal(literal.data.items[0].course_id,1);
 const filtered=await request('/courses?category=2&search=course');assert.equal(filtered.data.total,15);
 const limited=await request('/courses?limit=100000&page=Infinity');assert.equal(limited.data.pageSize,50);assert.equal(limited.data.page,1);
 assert.equal((await request('/courses?search=does-not-exist')).data.totalPages,0);
});
test('quiz reads use three queries at both four and 501 questions and hide answers',async()=>{
 for(const size of [4,501]){
  if(size!==4)await seedQuestions(size);
  const result=await request('/courses/1/quiz');assert.equal(result.status,200);assert.equal(result.sql.length,3);
  assert.equal(result.data.questions.length,size);
  for(const q of result.data.questions){assert.equal(q.options.length,4);for(const option of q.options)assert.equal('is_correct' in option,false)}
 }
 const edit=await request('/courses/1/quiz/edit',{user:2,role:'instructor'});
 assert.equal(edit.status,200);assert.equal(edit.data.questions[0].options[0].is_correct,1);
 assert.equal((await request('/courses/1/quiz/edit')).status,403);
});
test('weighted grading preserves unanswered questions and checks option ownership',async()=>{
 const mixed=await request('/quizzes/1/attempts',{method:'POST',body:{answers:{1:1,2:5,3:10},time_spent:12}});
 assert.equal(mixed.status,200);assert.equal(mixed.data.score_percent,67);assert.equal(mixed.data.correct_count,2);
 const [answers]=await conn.query('SELECT * FROM student_answers');assert.equal(answers.length,3);
 const [attempts]=await conn.query('SELECT COUNT(*) n FROM quiz_attempts');
 const invalid=await request('/quizzes/1/attempts',{method:'POST',body:{answers:{1:5}}});assert.equal(invalid.status,400);
 const [after]=await conn.query('SELECT COUNT(*) n FROM quiz_attempts');assert.equal(after[0].n,attempts[0].n);
 const empty=await request('/quizzes/1/attempts',{method:'POST',body:{answers:{}}});assert.equal(empty.data.score_percent,0);
});
test('501 answers are inserted in two bounded batches inside the attempt transaction',async()=>{
 await seedQuestions(501);
 const answers=Object.fromEntries(Array.from({length:501},(_,i)=>[i+1,i*4+1]));
 const result=await request('/quizzes/1/attempts',{method:'POST',body:{answers,time_spent:100}});
 assert.equal(result.status,200);assert.equal(result.data.score_percent,100);
 assert.equal(result.sql.filter(sql=>sql.startsWith('INSERT INTO student_answers')).length,2);
 assert.equal(result.sql.filter(sql=>sql.startsWith('SELECT o.option_id')).length,1);
 const [saved]=await conn.query('SELECT COUNT(*) n FROM student_answers');assert.equal(saved[0].n,501);
 const [enrollment]=await conn.query('SELECT progress_percentage FROM enrollments WHERE user_id=3 AND course_id=1');assert.equal(Number(enrollment[0].progress_percentage),100);
});
test('course details keep latest attempt and prior pass using only five queries',async()=>{
 await conn.query("INSERT INTO quiz_attempts(user_id,quiz_id,score_obtained,is_passed,time_spent_seconds,attempt_date) VALUES (3,1,100,1,120,'2026-01-01'),(3,1,10,0,20,'2026-01-02')");
 const result=await request('/courses/1');assert.equal(result.sql.length,5);assert.equal(result.data.hasPassed,true);
 assert.equal(Number(result.data.latestAttempt.score_obtained),10);assert.equal(result.data.enrolled,true);assert.equal(result.data.canManage,false);
 assert.equal((await request('/courses/1',{user:2,role:'instructor'})).data.canManage,true);
 await conn.query("UPDATE users SET role='student' WHERE user_id=2");
 assert.equal((await request('/courses/1',{user:2,role:'instructor'})).data.canManage,false);
});
test('leaderboard pairs the best score with its own time and returns one row per user',async()=>{
 await conn.query("INSERT INTO quiz_attempts(user_id,quiz_id,score_obtained,is_passed,time_spent_seconds,attempt_date) VALUES (3,1,100,1,120,'2026-01-01'),(3,1,10,0,20,'2026-01-02'),(4,1,100,1,90,'2026-01-01')");
 const result=await request('/courses/1/leaderboard');assert.equal(result.status,200);assert.equal(result.data.rows.length,2);
 assert.deepEqual(result.data.rows.map(row=>[row.user_id,Number(row.top_score),row.best_time]),[[4,100,90],[3,100,120]]);
});
test('notification preview is bounded, unread counts include other pages, and users stay isolated',async()=>{
 const result=await request('/notifications?limit=5');assert.equal(result.data.items.length,5);assert.equal(result.data.total,26);assert.equal(result.data.unread,8);
 assert.equal(result.data.items[0].noti_id,26);assert.equal(result.data.items.every(n=>n.is_read===1),true);
 const next=await request('/notifications?limit=5&page=2');assert.equal(next.data.items[0].noti_id,21);
 assert.equal((await request('/notifications/999')).status,404);
 await request('/notifications/read',{method:'POST'});
 assert.equal((await request('/notifications?limit=5')).data.unread,0);
 const [other]=await conn.query('SELECT is_read FROM notifications WHERE noti_id=999');assert.equal(other[0].is_read,0);
});
test('history pagination has stable ordering for equal timestamps',async()=>{
 const rows=Array.from({length:23},()=>[3,1,50,0,10,'2026-01-01']);rows.push([4,1,100,1,10,'2026-01-01']);
 await conn.query('INSERT INTO quiz_attempts(user_id,quiz_id,score_obtained,is_passed,time_spent_seconds,attempt_date) VALUES ?',[rows]);
 const first=await request('/history'),last=await request('/history?page=999');
 assert.equal(first.data.total,23);assert.equal(first.data.items.length,20);assert.equal(last.data.page,2);assert.equal(last.data.items.length,3);
 assert.equal(new Set([...first.data.items,...last.data.items].map(a=>a.attempt_id)).size,23);
});
test('course creation broadcasts in one INSERT SELECT and permissions remain enforced',async()=>{
 const result=await request('/courses',{user:2,role:'instructor',method:'POST',body:{title:'New course',description:'Test',category_id:1}});
 assert.equal(result.status,201);assert.equal(result.sql.filter(sql=>sql.includes('INSERT INTO notifications')).length,1);
 assert.equal(result.sql.some(sql=>sql==='SELECT user_id FROM users'),false);
 const [notices]=await conn.query('SELECT user_id,title,link FROM notifications WHERE link=?',['/courses/'+result.data.course_id]);
 assert.equal(notices.length,4);assert.equal(notices.find(n=>n.user_id===2).title,'สร้างคอร์สสำเร็จ!');
 assert.equal((await request('/courses',{method:'POST',body:{title:'Denied',description:'Test'}})).status,403);
});
