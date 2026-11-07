(() => {
 'use strict';
 const initialize=()=>{
 const config=window.IranGrains||{}, grid=document.getElementById('inputCostsGrid');
 const fa=(n)=>new Intl.NumberFormat('fa-IR').format(n);
 function prices(data){
  const rateDate=/^\d{4}-\d{2}-\d{2}$/.test(data.date||'')?new Intl.DateTimeFormat('fa-IR-u-ca-persian',{timeZone:'Asia/Tehran'}).format(new Date(data.date+'T12:00:00+03:30')):data.date;
  if(!grid)return;grid.replaceChildren();
  const euro=document.querySelector('[data-currency="eur"] .ex-value');if(euro&&data.euro)euro.textContent=fa(data.euro)+' ریال';
  for(const item of data.items||[]){const card=document.createElement('div');card.className='ex-card';const icon=document.createElement('span');icon.className='ex-icon';icon.setAttribute('aria-hidden','true');icon.textContent='🌾';const meta=document.createElement('div');meta.className='ex-meta';const name=document.createElement('span');name.className='ex-name';name.textContent=item.name;const value=document.createElement('b');value.className='ex-value';value.textContent=fa(item.price)+' ریال';meta.append(name,value);card.append(icon,meta);grid.append(card);}
  const note=document.getElementById('inputCostsNote');if(note&&data.euro)note.textContent='قیمت فروش با ۱۲٪ سود، ریال برای هر کیلوگرم | یوروی مبنا: '+fa(data.euro)+' ریال'+(rateDate?' | تاریخ نرخ: '+rateDate:' | تاریخ نرخ ثبت نشده است');
 }
 if(grid){if(config.inputPrices)prices(config.inputPrices);else fetch('assets/data/input-cost-models.json').then(r=>r.json()).then(models=>prices({euro:2109417,date:'نمونهٔ فایل اکسل',items:models.map(m=>({name:m.name,price:Math.round(m.slope*2109417+m.intercept)}))})).catch(()=>{});
  if(config.inputPricesUrl)setInterval(()=>{if(!document.hidden)fetch(config.inputPricesUrl).then(r=>r.json()).then(prices).catch(()=>{});},3600000);
 }
 const form=document.getElementById('associationFeedbackForm');
 if(form){const panel=document.getElementById('feedbackCommentPanel'),status=document.getElementById('feedbackStatus'),prompt=document.getElementById('feedbackPrompt');
 const prompts=Array.from(document.querySelectorAll('#feedbackPrompts > span'),node=>node.textContent);
 form.addEventListener('change',e=>{if(e.target.name!=='rating')return;prompt.textContent=prompts[Number(e.target.value)-1];const wasHidden=panel.hidden;panel.hidden=false;if(wasHidden)window.SiteMotion?.reveal([panel]);});
 form.addEventListener('submit',async e=>{e.preventDefault();if(!form.reportValidity())return;if(!config.feedback){status.textContent='ثبت رأی در نسخهٔ متصل به پنل فعال است.';return;}const button=form.querySelector('[type="submit"]');button.disabled=true;status.textContent='در حال ثبت نظر…';try{const response=await fetch(config.feedback.url,{method:'POST',headers:{'Content-Type':'application/json',...(config.feedback.restNonce?{'X-WP-Nonce':config.feedback.restNonce}:{})},body:JSON.stringify({...Object.fromEntries(new FormData(form)),_ig_nonce:config.feedback.nonce})});const data=await response.json();if(!response.ok)throw Error(data.message||'ثبت نظر انجام نشد.');status.textContent=data.message;form.reset();panel.hidden=true;}catch(error){status.textContent=error.message;}finally{button.disabled=false;}});
 }
 const rail=document.querySelector('.ig-ad-rail');if(rail){let frame=0;const update=()=>{frame=0;const range=document.documentElement.scrollHeight-innerHeight;rail.classList.toggle('is-past-half',range>0&&scrollY>range/2);};addEventListener('scroll',()=>{if(!frame)frame=requestAnimationFrame(update);},{passive:true});addEventListener('resize',update);update();}
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize,{once:true});else initialize();
})();
