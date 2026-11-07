(() => {
 'use strict';
 const initialize=()=>{
 const config=window.IranGrains||{}, grid=document.getElementById('inputCostsGrid');
 const fa=(n)=>new Intl.NumberFormat('fa-IR').format(n);
 function prices(data){
  const rateDate=/^\d{4}-\d{2}-\d{2}$/.test(data.date||'')?new Intl.DateTimeFormat('fa-IR-u-ca-persian',{timeZone:'Asia/Tehran'}).format(new Date(data.date+'T12:00:00+03:30')):data.date;
  if(!grid)return;grid.replaceChildren();
  const euro=document.querySelector('[data-currency="eur"] .ex-value');if(euro&&data.euro)euro.textContent=fa(data.euro)+' ریال';
  for(const item of data.items||[]){const card=document.createElement('div');card.className='ex-card';const icon=document.createElement('span');icon.className='ex-icon';icon.setAttribute('aria-hidden','true');const image=document.createElement('img');const kind=['corn','barley','soymeal','soybean','wheat'].includes(item.icon)?item.icon:'wheat';image.src=(config.assetBase||'')+'assets/img/inputs/'+kind+'.svg';image.alt='';image.width=36;image.height=36;icon.append(image);const meta=document.createElement('div');meta.className='ex-meta';const name=document.createElement('span');name.className='ex-name';name.textContent=item.name;const value=document.createElement('b');value.className='ex-value';value.textContent=fa(item.price)+' ریال';meta.append(name,value);card.append(icon,meta);grid.append(card);}
  const note=document.getElementById('inputCostsNote');if(note&&data.euro)note.textContent='قیمت فروش با ۱۲٪ سود، ریال برای هر کیلوگرم | یوروی مبنا: '+fa(data.euro)+' ریال'+(rateDate?' | تاریخ نرخ: '+rateDate:' | تاریخ نرخ ثبت نشده است');
 }
 if(grid){if(config.inputPrices)prices(config.inputPrices);else fetch('assets/data/input-cost-models.json').then(r=>r.json()).then(models=>prices({euro:2109417,date:'نمونهٔ فایل اکسل',items:models.map(m=>({name:m.name,icon:m.icon,price:Math.round(m.slope*2109417+m.intercept)}))})).catch(()=>{});
  if(config.inputPricesUrl)setInterval(()=>{if(!document.hidden)fetch(config.inputPricesUrl).then(r=>r.json()).then(prices).catch(()=>{});},3600000);
 }
 const form=document.getElementById('associationFeedbackForm');
 if(form){const panel=document.getElementById('feedbackCommentPanel'),status=document.getElementById('feedbackStatus'),prompt=document.getElementById('feedbackPrompt');
 const prompts=Array.from(document.querySelectorAll('#feedbackPrompts > span'),node=>node.textContent);
 form.addEventListener('change',e=>{if(e.target.name!=='rating')return;prompt.textContent=prompts[Number(e.target.value)-1];const wasHidden=panel.hidden;panel.hidden=false;if(wasHidden)window.SiteMotion?.reveal([panel]);});
 form.addEventListener('submit',async e=>{e.preventDefault();if(!form.reportValidity())return;if(!config.feedback){status.textContent='ثبت رأی در نسخهٔ متصل به پنل فعال است.';return;}const button=form.querySelector('[type="submit"]');button.disabled=true;status.textContent='در حال ثبت نظر…';try{const response=await fetch(config.feedback.url,{method:'POST',headers:{'Content-Type':'application/json',...(config.feedback.restNonce?{'X-WP-Nonce':config.feedback.restNonce}:{})},body:JSON.stringify({...Object.fromEntries(new FormData(form)),_ig_nonce:config.feedback.nonce})});const data=await response.json();if(!response.ok)throw Error(data.message||'ثبت نظر انجام نشد.');status.textContent=data.message;form.reset();panel.hidden=true;}catch(error){status.textContent=error.message;}finally{button.disabled=false;}});
 }
 if(!window.IranGrains&&!document.body.matches('.theme-about,.theme-board,.theme-contact')&&!document.querySelector('.ig-ad-rail')){
  fetch('assets/data/advertising-samples.json').then(r=>r.json()).then(ads=>{
   const rail=document.createElement('span');rail.className='ig-ad-rail';rail.setAttribute('role','complementary');rail.setAttribute('aria-label','تبلیغات');rail.setAttribute('data-nosnippet','');
   const label=document.createElement('span');label.className='ig-ad-label';label.textContent='تبلیغات';rail.append(label);
   for(const ad of ads){const card=document.createElement('span');card.className='ig-ad-card';const picture=document.createElement('span');picture.className='ig-ad-picture';const img=new Image();img.src='assets/img/'+ad.sample_image;img.alt='تصویر تبلیغ نمونه';img.loading='lazy';picture.append(img);const text=document.createElement('span');text.className='ig-ad-text';const lines=ad.text.split(/\r?\n/).map(line=>line.trim()).filter(Boolean);const title=document.createElement('span');title.className='ig-ad-title';title.textContent=lines.shift()||'';text.append(title);const caption=lines.length>1?lines.pop():'';if(lines.length){const description=document.createElement('span');description.className='ig-ad-description';description.textContent=lines.join('\n');text.append(description);}if(caption){const note=document.createElement('span');note.className='ig-ad-caption';note.textContent=caption;text.append(note);}card.append(picture,text);rail.append(card);}
   document.body.append(rail);document.body.classList.add('ig-has-ads');watchRail(rail);
  }).catch(()=>{});
 }
 function watchRail(rail){
  const compact=matchMedia('(max-width: 1100px)');
  let frame=0, resizeFrame=0;
  const containers=Array.from(document.querySelectorAll('.container')).filter(node=>!node.closest('header,footer,dialog')&&!node.parentElement.closest('.container'));
  const update=()=>{
   frame=0;
   const range=document.documentElement.scrollHeight-innerHeight;
   const hidden=!compact.matches&&range>0&&scrollY>range/2;
   rail.classList.toggle('is-past-half',hidden);
   rail.inert=hidden;
  };
  const layout=()=>{
   resizeFrame=0;
   containers.forEach(node=>node.classList.remove('ig-ad-reserved'));
   if(compact.matches){
    const hero=document.querySelector('#home, body > section, body > main > section');
    const main=document.querySelector('body > main');
    if(hero)hero.after(rail);else if(main)main.before(rail);
   }else{
    document.body.append(rail);
    // Reserve space only for content in the first half. Section backgrounds
    // stay full width, and later sections retain their normal centred layout.
    const halfway=document.documentElement.scrollHeight/2;
    containers.filter(node=>node.getBoundingClientRect().top+scrollY<halfway).forEach(node=>node.classList.add('ig-ad-reserved'));
   }
   update();
  };
  addEventListener('scroll',()=>{if(!frame)frame=requestAnimationFrame(update);},{passive:true});
  addEventListener('resize',()=>{cancelAnimationFrame(resizeFrame);resizeFrame=requestAnimationFrame(layout);});
  addEventListener('load',layout,{once:true});
  layout();
 }
 const rail=document.querySelector('.ig-ad-rail');if(rail)watchRail(rail);
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize,{once:true});else initialize();
})();
