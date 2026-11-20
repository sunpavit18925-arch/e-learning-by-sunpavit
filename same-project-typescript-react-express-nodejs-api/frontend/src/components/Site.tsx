'use client';
import {useSession} from './Application';
import {NoticeBox} from './Shared';
import dynamic from 'next/dynamic';
function Loading(){return <main className="p-8 flex-1 text-slate-500">กำลังโหลด...</main>}
const Home=dynamic(()=>import('./HomeAndCatalog').then(module=>module.Home),{loading:Loading});
const Catalog=dynamic(()=>import('./HomeAndCatalog').then(module=>module.Catalog),{loading:Loading});
const CoursePage=dynamic(()=>import('./Learning').then(module=>module.CoursePage),{loading:Loading});
const LessonsPage=dynamic(()=>import('./Learning').then(module=>module.LessonsPage),{loading:Loading});
const QuizPage=dynamic(()=>import('./Quiz').then(module=>module.QuizPage),{loading:Loading});
const QuizEditor=dynamic(()=>import('./Quiz').then(module=>module.QuizEditor),{loading:Loading});
const Leaderboard=dynamic(()=>import('./Quiz').then(module=>module.Leaderboard),{loading:Loading});
const CourseEditor=dynamic(()=>import('./CourseEditor').then(module=>module.CourseEditor),{loading:Loading});
const History=dynamic(()=>import('./Account').then(module=>module.History),{loading:Loading});
const Notifications=dynamic(()=>import('./Account').then(module=>module.Notifications),{loading:Loading});
const About=dynamic(()=>import('./About'),{loading:Loading});
const DemoRoles=dynamic(()=>import('./DemoRoles'),{loading:Loading});
const Contact=dynamic(()=>import('./Contact'),{loading:Loading});
const AdminMessages=dynamic(()=>import('./AdminMessages'),{loading:Loading});
const EditProfile=dynamic(()=>import('./EditProfile'),{loading:Loading});
const Certificate=dynamic(()=>import('./Certificate'),{loading:Loading});
const Profile=dynamic(()=>import('./Profile'),{loading:Loading});

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
