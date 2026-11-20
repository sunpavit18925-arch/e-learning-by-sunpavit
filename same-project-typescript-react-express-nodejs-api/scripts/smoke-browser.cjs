// Headless Chrome smoke test against the running local app. No dependencies.
const {spawn}=require('node:child_process');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const assert=require('node:assert/strict');
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const site=process.env.SMOKE_URL||'http://localhost:3000';

(async()=>{
 const executable=process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe';
 const profile=fs.mkdtempSync(path.join(os.tmpdir(),'elearning-browser-smoke-'));
 const browser=spawn(executable,['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
 let ws;
 try{
  let port;
  for(let i=0;i<100;i++){
   const file=path.join(profile,'DevToolsActivePort');
   if(fs.existsSync(file)){port=fs.readFileSync(file,'utf8').split('\n')[0];break}
   await sleep(100);
  }
  assert.ok(port,'Chrome DevTools must start');
  const targets=await (await fetch('http://127.0.0.1:'+port+'/json/list')).json();
  const target=targets.find(value=>value.type==='page');
  ws=new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true})});
  let sequence=0;const pending=new Map(),errors=[],requests=[];
  function send(method,params={}){
   return new Promise((resolve,reject)=>{
    const id=++sequence;
    const timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout: '+method))},15000);
    pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value)},reject:error=>{clearTimeout(timer);reject(error)}});
    ws.send(JSON.stringify({id,method,params}));
   });
  }
  ws.addEventListener('message',event=>{
   const message=JSON.parse(event.data);
   if(message.id){const task=pending.get(message.id);if(task){pending.delete(message.id);message.error?task.reject(Error(message.error.message)):task.resolve(message.result)}}
   if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.text+': '+(message.params.exceptionDetails.exception?.description||''));
   if(message.method==='Network.requestWillBeSent')requests.push(message.params.request.url);
   if(message.method==='Fetch.requestPaused'){
    const request=message.params;
    const body=Buffer.from(JSON.stringify({is_completed:true,progress_percentage:50})).toString('base64');
    // Exercise the progress UI without writing an enrollment to application data.
    send('Fetch.fulfillRequest',{requestId:request.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body}).catch(error=>errors.push(error.message));
   }
  });
  await send('Page.enable');await send('Runtime.enable');await send('Network.enable');
  await send('Fetch.enable',{patterns:[{urlPattern:'*/api/lessons/*/progress',requestStage:'Request'}]});
  const evaluate=async expression=>{
   const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
   if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description||result.exceptionDetails.text);
   return result.result.value;
  };
  const wait=async(expression,label)=>{
   for(let i=0;i<240;i++){if(await evaluate(expression))return;await sleep(100)}
   throw Error('Browser timeout: '+label);
  };
  const users=await (await fetch(site+'/api/demo/users')).json();
  const user=users.find(value=>value.role==='student');assert.ok(user,'Demo student exists');
  const session=await (await fetch(site+'/api/demo/session',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:user.user_id})})).json();
  assert.ok(session.token,'Demo session exists');
  await send('Page.addScriptToEvaluateOnNewDocument',{source:'localStorage.setItem("token",'+JSON.stringify(session.token)+')'});
  await send('Page.navigate',{url:site+'/'});
  await wait(`document.querySelectorAll('main a[href^="/courses/"]').length>=6`,'home courses');
  assert.ok(requests.some(url=>url.includes('/api/courses?limit=6')),'Home requests only six courses');
  await send('Page.navigate',{url:site+'/courses'});
  await wait(`document.querySelectorAll('main img[loading="lazy"]').length===9`,'catalog page one');
  const first=await evaluate(`[...document.querySelectorAll('main a[href^="/courses/"]')].map(a=>a.getAttribute('href'))`);
  await evaluate('[...document.querySelectorAll("nav[aria-label] button")].find(b=>b.textContent==="2").click()');
  await wait(`document.querySelector('button[aria-current="page"]')?.textContent==='2'`,'catalog page two');
  const second=await evaluate(`[...document.querySelectorAll('main a[href^="/courses/"]')].map(a=>a.getAttribute('href'))`);
  assert.equal(second.some(href=>first.includes(href)),false,'Catalog pages do not repeat course links');
  await evaluate('(()=>{const input=document.querySelector("main input");Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,"value").set.call(input,"performance-no-matches-9281");input.dispatchEvent(new Event("input",{bubbles:true}))})()');
  await wait(`document.querySelectorAll('main img[loading="lazy"]').length===0`,'catalog search');
  await send('Page.navigate',{url:site+'/courses/16'});
  await wait(`document.querySelectorAll('main iframe[loading="lazy"]').length>0`,'course lessons');
  const before=requests.filter(url=>/\/api\/courses\/16$/.test(url)).length;
  await evaluate('[...document.querySelectorAll("main button")].find(b=>b.textContent.includes("เรียนจบ")).click()');
  await wait('[...document.querySelectorAll("main button")].some(b=>b.textContent.includes("✓"))','progress UI updates');
  assert.equal(requests.filter(url=>/\/api\/courses\/16$/.test(url)).length,before,'Progress does not reload the entire course');
  await send('Page.navigate',{url:site+'/courses/16/quiz'});
  await wait(`document.querySelectorAll('main input[type="radio"]').length>0`,'quiz options');
  await send('Page.navigate',{url:site+'/quiz-history'});
  await wait('document.querySelector("main table")!==null','history page');
  await sleep(250);
  assert.deepEqual(errors,[],'No browser JavaScript errors');
  console.log('Browser smoke passed: home limit, catalog pagination/search, lazy video, progress UI, quiz, history, JavaScript errors');
  await send('Browser.close');
 }finally{
  if(ws)ws.close();
  if(browser.exitCode===null){browser.kill();await sleep(500)}
  const resolved=path.resolve(profile),root=path.resolve(os.tmpdir())+path.sep;
  assert.ok(resolved.startsWith(root)&&path.basename(resolved).startsWith('elearning-browser-smoke-'));
  fs.rmSync(resolved,{recursive:true,force:true,maxRetries:10,retryDelay:200});
 }
})().catch(error=>{console.error(error.message);process.exitCode=1});
