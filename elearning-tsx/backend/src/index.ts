import 'dotenv/config';
import app from './app';
import {ensureSchema} from './config/schema';
ensureSchema().then(()=>app.listen(Number(process.env.PORT || 4000),()=>console.log('API listening on port '+(process.env.PORT || 4000)))).catch(e=>{console.error('Database startup failed:',e.message);process.exitCode=1});
