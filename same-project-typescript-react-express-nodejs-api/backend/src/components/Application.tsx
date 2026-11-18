'use client';
import {createContext,useContext,useEffect,useState} from 'react';
import {usePathname,useRouter} from 'next/navigation';
import type {User} from '../types';
import {apiRequest} from '../services/api';
import {Shell} from './Shared';

type Session={user:User|null;loading:boolean;setUser:(user:User|null)=>void;selectDemo:(user:User)=>void;logout:()=>void};
const SessionContext=createContext<Session|null>(null);
export function useSession(){const session=useContext(SessionContext);if(!session)throw Error('Session provider is missing');return session}

export default function Application({children}:{children:React.ReactNode}){
 const [user,setUser]=useState<User|null>(null),[loading,setLoading]=useState(true);
 const router=useRouter(),pathname=usePathname();
 useEffect(()=>{
  let active=true;
  if(!localStorage.getItem('token')){setLoading(false);return}
  apiRequest<User>('/me').then(value=>{if(active)setUser(value)}).catch(()=>{if(active){localStorage.removeItem('token');setUser(null)}}).finally(()=>{if(active)setLoading(false)});
  return()=>{active=false};
 },[]);
 function selectDemo(selected:User){setUser(selected);router.replace('/')}
 function logout(){localStorage.removeItem('token');setUser(null);router.replace('/')}
 const standalone=pathname.endsWith('/certificate');
 return <SessionContext.Provider value={{user,loading,setUser,selectDemo,logout}}>{standalone?children:<Shell user={user} onLogout={logout}>{children}</Shell>}</SessionContext.Provider>;
}

