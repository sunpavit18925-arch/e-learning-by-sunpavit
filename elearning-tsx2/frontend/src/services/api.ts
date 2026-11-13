export async function apiRequest<T>(url:string, options:RequestInit = {}):Promise<T>{
 const token=typeof window==='undefined'?'':localStorage.getItem('token')||'';
 const headers=new Headers(options.headers);if(!(options.body instanceof FormData)) headers.set('Content-Type','application/json');if(token)headers.set('Authorization',`Bearer ${token}`);
 const response=await fetch('/api'+url,{...options,headers,cache:'no-store'});
 if(!response.ok){let message='เกิดข้อผิดพลาดในการทำรายการ';try{const data=await response.json();message=data.message||message}catch{}throw new Error(message)}
 return response.json() as Promise<T>;
}
export function imageUrl(file:string|null|undefined){return file?`/uploads/${encodeURIComponent(file.split(/[\\/]/).pop()||'')}`:'/image/pic_default.jpg'}
export function formatDate(value:string){return new Date(value).toLocaleDateString('th-TH',{day:'numeric',month:'short',year:'numeric'})}
