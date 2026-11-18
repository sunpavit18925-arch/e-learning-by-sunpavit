import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { secret } from '../config/auth';
export type AuthRequest = Request & { user?: {id:number; role:string} };
export function auth(req:AuthRequest,res:Response,next:NextFunction) { try { const token=req.header('authorization')?.replace(/^Bearer /,''); if (!token) throw Error(); req.user=jwt.verify(token,secret!) as {id:number;role:string}; next(); } catch { res.status(401).json({message:'กรุณาเข้าสู่ระบบ'}); } }
export const wrap=(fn:(req:AuthRequest,res:Response)=>Promise<void>) => (req:AuthRequest,res:Response,next:NextFunction)=>{fn(req,res).catch(next)};
