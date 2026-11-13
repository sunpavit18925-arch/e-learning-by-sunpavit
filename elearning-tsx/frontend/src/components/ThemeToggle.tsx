'use client';
import {useEffect,useState} from 'react';

const storageKey='elearning-theme';

export default function ThemeToggle(){
 const [dark,setDark]=useState(false);

 useEffect(()=>{
  const system=window.matchMedia('(prefers-color-scheme: dark)');
  function sync(){
   let preference:string|null=null;
   try{preference=localStorage.getItem(storageKey)}catch{}
   const enabled=preference==='dark'||(preference!=='light'&&system.matches);
   document.documentElement.classList.toggle('dark',enabled);
   document.documentElement.dataset.theme=enabled?'dark':'light';
   setDark(enabled);
  }
  sync();
  system.addEventListener('change',sync);
  window.addEventListener('storage',sync);
  return()=>{system.removeEventListener('change',sync);window.removeEventListener('storage',sync)};
 },[]);

 function toggle(){
  const next=!dark;
  document.documentElement.classList.toggle('dark',next);
  document.documentElement.dataset.theme=next?'dark':'light';
  try{localStorage.setItem(storageKey,next?'dark':'light')}catch{}
  setDark(next);
 }

 const label=dark?'เปลี่ยนเป็นโหมดสว่าง':'เปลี่ยนเป็นโหมดมืด';
 return <button type="button" onClick={toggle} aria-label={label} aria-pressed={dark} title={label} className="theme-toggle grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-700 bg-slate-800/60 text-indigo-200 hover:border-indigo-400 hover:bg-slate-700 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 transition-colors"><i className={`fa-solid ${dark?'fa-sun':'fa-moon'}`} aria-hidden="true"/></button>;
}
