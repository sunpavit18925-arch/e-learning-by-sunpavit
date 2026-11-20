// node scripts/migrate-performance.cjs [--apply]
const path = require('node:path');
const {createRequire} = require('node:module');
const backendRequire = createRequire(path.resolve(__dirname, '../backend/package.json'));
backendRequire('dotenv').config({path:path.resolve(__dirname, '../backend/.env'),quiet:true});
const mysql = backendRequire('mysql2/promise');
const manifest = require('../../database/performance-indexes.json');

(async()=>{
 const conn=await mysql.createConnection({host:process.env.DB_HOST||'127.0.0.1',port:Number(process.env.DB_PORT||3306),user:process.env.DB_USER||'root',password:process.env.DB_PASSWORD||'',database:process.env.DB_NAME||'elearning_db',connectTimeout:5000});
 try {
  const [existing]=await conn.query('SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() GROUP BY TABLE_NAME,INDEX_NAME');
  for(const index of manifest){
   if(![index.table,index.name,...index.columns].every(value=>/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(value)))throw Error('Invalid index identifier');
   const indexes=existing.filter(row=>row.TABLE_NAME===index.table);
   const conflict=indexes.find(row=>row.INDEX_NAME===index.name&&row.columns_list!==index.columns.join(','));
   if(conflict)throw Error('Existing index name has different columns: '+index.name);
   const covered=indexes.some(row=>row.columns_list.split(',').slice(0,index.columns.length).join(',')===index.columns.join(','));
   if(covered){console.log('Already covered: '+index.name);continue;}
   if(process.argv.includes('--apply')){
    await conn.query('CREATE INDEX ?? ON ?? (??)',[index.name,index.table,index.columns]);
    console.log('Created: '+index.name);
   }else console.log('Pending: '+index.name);
  }
 }finally{await conn.end()}
})().catch(error=>{console.error(error.message);process.exitCode=1});
