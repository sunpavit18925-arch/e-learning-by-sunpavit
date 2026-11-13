'use client';
import {useEffect} from 'react';
import {useSession} from '../../components/Application';
export default function Page(){const {logout}=useSession();useEffect(()=>{logout()},[]);return <main className="p-8 text-slate-500 dark:text-slate-400 flex-1">กำลังออกจากระบบ...</main>}
