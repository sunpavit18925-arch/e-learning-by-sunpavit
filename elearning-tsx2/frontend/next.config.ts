import type {NextConfig} from 'next';
const backend=process.env.BACKEND_URL || 'http://127.0.0.1:4000';
const legacyCourse=(source:string,key:string,destination:string)=>({source,has:[{type:'query' as const,key,value:'(?<courseId>[0-9]+)'}],destination,permanent:true});
const config:NextConfig={
 outputFileTracingRoot:process.cwd(),
 async rewrites(){return [{source:'/api/:path*',destination:`${backend}/api/:path*`},{source:'/uploads/:path*',destination:`${backend}/uploads/:path*`},{source:'/image/:path*',destination:`${backend}/image/:path*`}]},
 // Only redirects for old bookmarks. All rendered pages live in app/**/page.tsx.
 async redirects(){return [
  legacyCourse('/courses.php','id','/courses/:courseId'),
  legacyCourse('/edit-course.php','course_id','/courses/:courseId/edit'),
  legacyCourse('/edit-course.php','id','/courses/:courseId/edit'),
  legacyCourse('/manage-lessons.php','course_id','/courses/:courseId/lessons'),
  legacyCourse('/quiz_view.php','id','/courses/:courseId/quiz'),
  legacyCourse('/add-quiz.php','id','/courses/:courseId/quiz/edit'),
  legacyCourse('/edit-quiz.php','id','/courses/:courseId/quiz/edit'),
  legacyCourse('/quiz_score_board.php','course_id','/courses/:courseId/leaderboard'),
  legacyCourse('/quiz_score_board.php','id','/courses/:courseId/leaderboard'),
  legacyCourse('/generate_pdf.php','id','/courses/:courseId/certificate'),
  legacyCourse('/notification_detail.php','id','/notifications/:courseId'),
  ...Object.entries({'index.php':'/','courses_all.php':'/courses','courses.php':'/courses','about.php':'/about','contact.php':'/contact','profile.php':'/profile','edit_profile.php':'/profile/edit','quiz-history.php':'/quiz-history','notifications_all.php':'/notifications','add-course.php':'/courses/new','login.php':'/demo-roles','register.php':'/demo-roles','logout.php':'/logout'}).map(([source,destination])=>({source:'/'+source,destination,permanent:true})),
 ]},
};
export default config;
