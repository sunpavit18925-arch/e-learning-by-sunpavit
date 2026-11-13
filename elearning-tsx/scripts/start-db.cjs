const fs=require('fs');
const path=require('path');
const {spawn,spawnSync}=require('child_process');
const mysql=require('../backend/node_modules/mysql2/promise');
const root=path.resolve(__dirname,'..');
const data=path.join(root,'.local-db');
const dump=path.join(root,'database','elearning_db.sql');
const bin=process.env.MYSQL_BIN || 'C:\\xampp\\mysql\\bin';
const exe=name=>path.join(bin,name+'.exe');
const opts={host:'127.0.0.1',port:3308,user:'root',password:'',multipleStatements:true};
async function connection(){try{return await mysql.createConnection(opts)}catch{return null}}
async function main(){
 let conn=await connection();
 if(!conn){
  if(!fs.existsSync(exe('mysqld')))throw Error('ไม่พบ MariaDB ของ XAMPP ที่ '+bin+' ตั้งค่า MYSQL_BIN ให้ตรงกับเครื่อง');
  if(!fs.existsSync(data)){
   if(!fs.existsSync(dump))throw Error('ไม่พบไฟล์ database/elearning_db.sql');
   const result=spawnSync(exe('mysql_install_db'),[`--datadir=${data}`,'--port=3308'],{windowsHide:true,encoding:'utf8'});
   if(result.status!==0)throw Error(result.stderr||result.stdout||'สร้างฐานข้อมูลไม่สำเร็จ');
  }
  const server=spawn(exe('mysqld'),[`--defaults-file=${path.join(data,'my.ini')}`,'--bind-address=127.0.0.1'],{detached:true,windowsHide:true,stdio:'ignore'});server.unref();
  for(let i=0;i<40&&!conn;i++){await new Promise(r=>setTimeout(r,250));conn=await connection()}
  if(!conn)throw Error('ไม่สามารถเริ่ม MariaDB ที่พอร์ต 3308');
 }
 try{
  const [r]=await conn.query('SELECT @@datadir AS datadir');
  const expected=path.resolve(data).replace(/[\\/]+$/,'').toLowerCase();
  const actual=path.resolve(r[0].datadir).replace(/[\\/]+$/,'').toLowerCase();
  if(actual!==expected)throw Error('พอร์ต 3308 ถูกใช้โดยฐานข้อมูลอื่น กรุณาตรวจสอบก่อนเริ่มระบบ');
  const [dbs]=await conn.query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='elearning_tsx'");
  if(!dbs.length){if(!fs.existsSync(dump))throw Error('ไม่พบไฟล์ database/elearning_db.sql');await conn.query('CREATE DATABASE elearning_tsx CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');await conn.query('USE elearning_tsx');await conn.query(fs.readFileSync(dump,'utf8'));console.log('นำเข้าข้อมูล SQL ลงฐานข้อมูลแยกเรียบร้อย');}
  console.log('ฐานข้อมูลโปรเจกต์พร้อมใช้งานที่ 127.0.0.1:3308');
 }finally{await conn.end()}
}
main().catch(e=>{console.error(e.message);process.exitCode=1});

