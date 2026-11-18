import type { Metadata } from 'next';
import Application from '../components/Application';
import '@fontsource/kanit/300.css';
import '@fontsource/kanit/400.css';
import '@fontsource/kanit/500.css';
import '@fontsource/kanit/600.css';
import '@fontsource/kanit/700.css';
import '@fontsource/kanit/800.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import './globals.css';
import '../components/profile.css';
import '../components/edit-profile.css';
import './theme.css';
export const metadata: Metadata={title:'E-LEARNING',description:'แพลตฟอร์มการเรียนรู้ออนไลน์'};
// Apply the saved palette before the first paint, including on direct page visits.
const themeScript=`(()=>{let theme;try{theme=localStorage.getItem('elearning-theme')}catch{}const dark=theme==='dark'||(theme!=='light'&&window.matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',dark);document.documentElement.dataset.theme=dark?'dark':'light'})()`;
export default function Layout({children}:{children:React.ReactNode}){return <html lang="th" suppressHydrationWarning><head><script dangerouslySetInnerHTML={{__html:themeScript}}/></head><body><Application>{children}</Application></body></html>}


