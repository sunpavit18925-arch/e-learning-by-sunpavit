'use client';
import {useSession} from './Application';
import {NoticeBox} from './Shared';
import {Home,Catalog} from './HomeAndCatalog';
import About from './About';
import DemoRoles from './DemoRoles';
import Contact from './Contact';
import AdminMessages from './AdminMessages';
import {CoursePage,LessonsPage} from './Learning';
import {QuizPage,QuizEditor,Leaderboard} from './Quiz';
import {CourseEditor} from './CourseEditor';
import {History,Notifications} from './Account';
import EditProfile from './EditProfile';
import Certificate from './Certificate';
import Profile from './Profile';

export type PageName='home'|'courses'|'course'|'new-course'|'edit-course'|'lessons'|'quiz'|'edit-quiz'|'leaderboard'|'certificate'|'about'|'contact'|'demo-roles'|'profile'|'edit-profile'|'history'|'notifications'|'notification'|'messages';
const privatePages=new Set<PageName>(['courses','course','new-course','edit-course','lessons','quiz','edit-quiz','leaderboard','certificate','profile','edit-profile','history','notifications','notification','messages']);
export default function Site({page,id=''}:{page:PageName;id?:string}){
 const {user,loading,setUser,selectDemo}=useSession();
 if(loading&&privatePages.has(page))return <main className="p-8 text-slate-500 dark:text-slate-400 flex-1">กำลังโหลด...</main>;
 if(privatePages.has(page)&&!user)return <DemoRoles currentUser={null} onSelect={selectDemo}/>;
 switch(page){
  case 'home':return <Home user={user}/>;
  case 'about':return <About/>;
  case 'contact':return user?.role==='admin'?<AdminMessages/>:<Contact user={user}/>;
  case 'demo-roles':return <DemoRoles currentUser={user} onSelect={selectDemo}/>;
  case 'messages':return user?.role==='admin'?<AdminMessages/>:<main className="p-10 flex-1"><NoticeBox text="เฉพาะผู้ดูแลระบบเท่านั้น"/></main>;
  case 'courses':return <Catalog user={user!}/>;
  case 'course':return <CoursePage key={id} id={id} user={user!}/>;
  case 'new-course':return <CourseEditor id="" user={user!}/>;
  case 'edit-course':return <CourseEditor key={id} id={id} user={user!}/>;
  case 'lessons':return <LessonsPage key={id} id={id}/>;
  case 'quiz':return <QuizPage key={id} id={id}/>;
  case 'edit-quiz':return <QuizEditor key={id} id={id}/>;
  case 'leaderboard':return <Leaderboard key={id} id={id}/>;
  case 'certificate':return <Certificate key={id} id={id}/>;
  case 'profile':return <Profile user={user!}/>;
  case 'edit-profile':return <EditProfile user={user!} onChange={setUser}/>;
  case 'history':return <History/>;
  case 'notifications':return <Notifications id=""/>;
  case 'notification':return <Notifications key={id} id={id}/>;
 }
}
