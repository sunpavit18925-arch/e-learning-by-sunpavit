'use client';
import {useCallback,useEffect,useState} from 'react';
import type {PageResult} from '../types';
import {apiRequest} from './api';

export function usePagedApi<T>(url:string) {
 const [data,setData]=useState<PageResult<T>>({items:[],total:0,page:1,pageSize:20,totalPages:0});
 const [loading,setLoading]=useState(true),[error,setError]=useState(''),[version,setVersion]=useState(0);
 const refresh=useCallback(()=>setVersion(value=>value+1),[]);
 useEffect(()=>{
  const controller=new AbortController();
  setLoading(true);setError('');
  apiRequest<PageResult<T>>(url,{signal:controller.signal})
   .then(value=>{if(!controller.signal.aborted)setData(value)})
   .catch(e=>{if(!controller.signal.aborted)setError((e as Error).message)})
   .finally(()=>{if(!controller.signal.aborted)setLoading(false)});
  return()=>controller.abort();
 },[url,version]);
 return {...data,loading,error,refresh};
}
