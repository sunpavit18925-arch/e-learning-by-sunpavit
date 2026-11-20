'use client';

export default function Pagination({page,totalPages,onChange,disabled=false}:{page:number;totalPages:number;onChange:(page:number)=>void;disabled?:boolean}) {
 if(totalPages<=1)return null;
 const first=Math.max(1,Math.min(page-2,totalPages-4));
 const pages=Array.from({length:Math.min(5,totalPages)},(_,i)=>first+i);
 const style='px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed';
 return <nav aria-label="แบ่งหน้า" className="flex flex-wrap items-center justify-center gap-2 mt-8">
  <button type="button" disabled={disabled||page<=1} onClick={()=>onChange(page-1)} className={style}>ก่อนหน้า</button>
  {pages.map(value=><button type="button" key={value} disabled={disabled} aria-current={value===page?'page':undefined} onClick={()=>onChange(value)} className={`${style} ${value===page?'bg-indigo-600 text-white':'bg-white dark:bg-slate-900'}`}>{value}</button>)}
  <button type="button" disabled={disabled||page>=totalPages} onClick={()=>onChange(page+1)} className={style}>ถัดไป</button>
  <span className="text-xs text-slate-500 dark:text-slate-400">หน้า {page} / {totalPages}</span>
 </nav>;
}
