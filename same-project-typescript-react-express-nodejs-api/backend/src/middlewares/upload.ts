import multer from 'multer';
import path from 'path';
import crypto from 'crypto';
const allowed = new Set(['.jpg','.jpeg','.png','.gif','.webp']);
export const upload = multer({
 storage: multer.diskStorage({destination:path.resolve(__dirname,'../../uploads'),filename:(_req,file,done)=>done(null,`${Date.now()}_${crypto.randomBytes(8).toString('hex')}${path.extname(file.originalname).toLowerCase()}`)}),
 limits:{fileSize:5*1024*1024},
 fileFilter:(_req,file,done)=>{const ext=path.extname(file.originalname).toLowerCase();if(!allowed.has(ext)||!['image/jpeg','image/png','image/gif','image/webp'].includes(file.mimetype)){done(Object.assign(new Error('รองรับเฉพาะไฟล์รูปภาพ jpg, png, gif, webp'),{status:400}));return;}done(null,true);}
});
