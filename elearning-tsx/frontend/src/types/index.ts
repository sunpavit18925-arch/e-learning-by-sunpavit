export interface User {user_id:number;username:string;first_name:string;last_name:string;email:string;image:string|null;role:'admin'|'instructor'|'student';created_at:string}
export interface Category {category_id:number;name:string}
export interface Course {course_id:number;title:string;description:string;thumbnail_url:string|null;category_id:number;category_name:string|null;instructor_id:number;first_name:string;last_name:string;progress_percentage?:number;enrollment_date?:string}
export interface Lesson {lesson_id:number;course_id:number;title:string;content_type:string;content_url:string;order_index:number}
export interface Option {option_id?:number;option_text:string;is_correct?:number|boolean}
export interface Question {question_id?:number;question_text:string;points:number;options:Option[]}
export interface Quiz {quiz_id:number;course_id:number;title:string;time_limit:number;passing_score:number;questions:Question[]}
export interface Attempt {attempt_id:number;quiz_id:number;title:string;course_title:string;course_id:number;score_obtained:number;is_passed:number;attempt_date:string;time_spent_seconds:number;passing_score:number;time_limit:number}
export interface CourseDetail extends Course {lessons:Lesson[];quiz:Quiz|null;completed:number[];enrolled:boolean;hasPassed:boolean;canManage:boolean;latestAttempt:Attempt|null}
export interface Notice {noti_id:number;title:string;message:string;link:string|null;is_read:number;created_at:string}
export interface Stats {enrolled:number;passed:number;latest:Attempt|null}
