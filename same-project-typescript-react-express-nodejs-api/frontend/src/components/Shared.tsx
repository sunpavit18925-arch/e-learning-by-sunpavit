'use client';
import Link from 'next/link';

import {useEffect,useRef,useState} from 'react';
import {usePathname} from 'next/navigation';
import type {User,Notice} from '../types';
import {apiRequest,imageUrl,formatDate} from '../services/api';
import ThemeToggle from './ThemeToggle';
export function NoticeBox({text}:{text:string}){return text?<div role="alert" className="max-w-5xl mx-auto mt-5 px-5 py-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-200 text-sm">{text}</div>:null}
export const inputClass='min-w-0 w-full px-4 py-3 border border-slate-200 dark:border-slate-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 dark:bg-slate-800/60 focus:bg-white dark:focus:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm transition';
export const buttonClass='inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl transition shadow-md text-sm';
export function Breadcrumb({items}:{items:{label:string;href?:string}[]}){return <div className="flex flex-wrap items-center gap-2 text-xs sm:text-sm text-slate-400 mb-6"><Link href="/" className="hover:text-indigo-600 dark:hover:text-indigo-300">หน้าหลัก</Link>{items.map((x,i)=><span key={i} className="flex min-w-0 gap-2 items-center"><span>/</span>{x.href?<Link href={x.href} className="hover:text-indigo-600 dark:hover:text-indigo-300">{x.label}</Link>:<span className="text-slate-700 dark:text-slate-200 font-medium">{x.label}</span>}</span>)}</div>}
export function Shell({children,user,onLogout}:{children:React.ReactNode;user:User|null;onLogout:()=>void}){
 const [menu,setMenu]=useState(false),[notices,setNotices]=useState<Notice[]>([]),[unread,setUnread]=useState(0);
 useEffect(()=>{
  setNotices([]);setUnread(0);if(!user)return;
  const controller=new AbortController();let pending=false;
  const get=async()=>{if(document.hidden||pending)return;pending=true;try{const value=await apiRequest<{items:Notice[];unread:number}>('/notifications?limit=5',{signal:controller.signal});if(!controller.signal.aborted){setNotices(value.items);setUnread(value.unread)}}catch{}finally{pending=false}};
  get();const timer=setInterval(get,30000);
  document.addEventListener('visibilitychange',get);window.addEventListener('notifications-updated',get);
  return()=>{controller.abort();clearInterval(timer);document.removeEventListener('visibilitychange',get);window.removeEventListener('notifications-updated',get)};
 },[user?.user_id]);
 const pathname=usePathname();
 const menuButton=useRef<HTMLButtonElement>(null);
 useEffect(()=>{setMenu(false)},[pathname]);
 useEffect(()=>{
  const desktop=window.matchMedia('(min-width: 1280px)');
  const close=()=>{if(desktop.matches)setMenu(false)};
  desktop.addEventListener('change',close);
  return()=>desktop.removeEventListener('change',close);
 },[]);
 useEffect(()=>{
  if(!menu)return;
  const close=(event:KeyboardEvent)=>{if(event.key==='Escape'){setMenu(false);menuButton.current?.focus()}};
  document.addEventListener('keydown',close);
  return()=>document.removeEventListener('keydown',close);
 },[menu]);
 const links=[['/','หน้าหลัก'],['/courses','คอร์สทั้งหมด'],['/about','เกี่ยวกับเรา'],[user?.role==='admin'?'/admin/messages':'/contact',user?.role==='admin'?'ข้อความติดต่อ':'ติดต่อ']];
 const logout=onLogout;
 return <div className="min-h-screen flex flex-col"><nav className="site-nav sticky top-0 z-50 bg-slate-900 shadow-md border-b border-gray-800"><div className="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex justify-between items-center"><Link href="/" className="site-brand flex items-center space-x-3 text-2xl font-bold bg-gradient-to-r from-indigo-400 via-purple-300 to-pink-400 bg-clip-text text-transparent hover:opacity-90 transition"><span>🎓</span><span>E-LEARNING</span></Link><div className="hidden xl:flex items-center space-x-1 lg:space-x-2">{links.map(([href,label])=><Link key={href} href={href} className="main-nav-link">{label}</Link>)}{user&&user.role!=='student'&&<Link href="/courses/new" className="main-nav-link text-indigo-400 font-semibold"><i className="fa-solid fa-plus-circle mr-1"/> เพิ่มคอร์ส</Link>}</div><div className="flex items-center gap-2 xl:gap-3"><ThemeToggle/>{user?<><div className="relative group hidden xl:block"><Link href="/notifications" className="relative p-2.5 text-gray-300 hover:text-white text-xl" aria-label="การแจ้งเตือน"><i className="fa-regular fa-bell"/>{unread>0&&<span className="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] rounded-full px-1.5">{unread}</span>}</Link><div className="absolute right-0 w-80 pt-3 hidden group-hover:block"><div className="bg-slate-800 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden"><div className="px-4 py-3 text-white text-sm font-semibold">การแจ้งเตือน <span className="text-indigo-300">{unread} ใหม่</span></div>{notices.length?notices.map(n=><Link href={`/notifications/${n.noti_id}`} key={n.noti_id} className="block px-4 py-3 hover:bg-slate-700 text-gray-300 text-xs border-t border-gray-700"><b className="text-white">{n.title}</b><p className="mt-1">{n.message}</p><p className="mt-1 text-gray-500">{formatDate(n.created_at)}</p></Link>):<p className="p-4 text-gray-400 text-xs">ไม่มีการแจ้งเตือน</p>}<Link href="/notifications" className="block p-3 text-center text-xs text-indigo-400">ดูทั้งหมด</Link></div></div></div><div className="hidden xl:block relative group"><button className="flex items-center text-gray-300 hover:text-white p-1.5 rounded-xl hover:bg-slate-800"><img src={imageUrl(user.image)} onError={e=>{e.currentTarget.src='/image/pic_default.jpg'}} alt="" className="w-9 h-9 mr-2 rounded-full object-cover border border-gray-700"/><div className="text-left hidden lg:block"><div className="text-sm font-semibold">{user.username}</div><div className="text-[10px] uppercase font-bold text-indigo-400">{user.role}</div></div><span className="ml-2">⌄</span></button><div className="absolute right-0 w-64 pt-2 hidden group-hover:block"><div className="bg-slate-800 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden text-sm text-gray-300"><div className="px-4 py-3 border-b border-gray-700"><b className="text-white">{user.first_name} {user.last_name}</b><div className="text-xs text-gray-400">{user.email}</div></div>{user.role!=='student'&&<Link href="/courses/new" className="block px-4 py-2.5 hover:bg-indigo-600">เพิ่มคอร์สเรียน</Link>}<Link href="/demo-roles" className="block px-4 py-2.5 hover:bg-indigo-600 text-indigo-300">⇄ เปลี่ยนบทบาททดสอบ</Link>{user.role==='admin'&&<Link href="/admin/messages" className="block px-4 py-2.5 hover:bg-indigo-600">ข้อความติดต่อ</Link>}<Link href="/profile" className="block px-4 py-2.5 hover:bg-indigo-600">โปรไฟล์ของฉัน</Link><Link href="/quiz-history" className="block px-4 py-2.5 hover:bg-indigo-600">ประวัติการทำแบบทดสอบ</Link><button onClick={logout} className="block px-4 py-2.5 text-red-400">ออกจากระบบ</button></div></div></div></>:<div className="hidden xl:flex items-center"><Link href="/demo-roles" className="bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-4 py-2.5 rounded-xl transition text-sm"><i className="fa-solid fa-user-group mr-2"/>เลือกบทบาททดสอบ</Link></div>}<button ref={menuButton} type="button" aria-controls="mobile-navigation" aria-expanded={menu} onClick={()=>setMenu(!menu)} className="mobile-menu-toggle xl:hidden text-gray-300 text-2xl" aria-label="เปิดเมนู">☰</button></div></div>{menu&&<div id="mobile-navigation" onClick={event=>{if((event.target as HTMLElement).closest('a, button'))setMenu(false)}} className="xl:hidden px-6 pb-6 flex flex-col gap-3 text-gray-300">{links.map(([href,label])=><Link key={href} href={href}>{label}</Link>)}{user?<><Link href="/demo-roles">เปลี่ยนบทบาททดสอบ</Link><Link href="/profile">โปรไฟล์ของฉัน</Link><Link href="/notifications">การแจ้งเตือน ({unread})</Link><Link href="/quiz-history">ประวัติการสอบ</Link>{user.role!=='student'&&<Link href="/courses/new">เพิ่มคอร์สเรียน</Link>}<button className="text-left text-red-400" onClick={logout}>ออกจากระบบ</button></>:<><Link href="/demo-roles">เลือกบทบาททดสอบ</Link></>}</div>}</nav>{children}<footer className="bg-slate-900 text-gray-400 py-10 mt-auto border-t border-slate-800"><div className="max-w-7xl mx-auto px-6 text-center"><div className="space-y-2"><p className="text-sm">© {new Date().getFullYear()} <span className="text-gray-200">E-LEARNING.</span> All rights reserved.</p><p className="text-sm font-medium text-gray-400 tracking-wide">เรียนรู้ ฝึกฝน และพัฒนาทักษะในทุกวัน</p></div><div className="site-footer-links mt-6 flex justify-center space-x-4 text-xs text-gray-500"><Link href="/about">เกี่ยวกับเรา</Link><span>•</span><Link href={user?.role==='admin'?'/admin/messages':'/contact'}>{user?.role==='admin'?'ข้อความติดต่อ':'ติดต่อสอบถาม'}</Link><span>•</span><Link href="/courses">คอร์สเรียนทั้งหมด</Link></div></div></footer></div>
}




