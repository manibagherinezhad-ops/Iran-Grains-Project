(() => {
    'use strict';
    const center=document.querySelector('.ig-copy-center');if(!center)return;
    let dirty=false,submitting=false;
    function changed(){dirty=true;document.querySelectorAll('.ig-copy-dirty').forEach(el=>el.textContent='تغییرات ذخیره‌نشده دارید.');}
    center.querySelectorAll('.ig-copy-form').forEach(form=>{
        form.addEventListener('input',changed);form.addEventListener('change',changed);
        form.addEventListener('submit',()=>{window.tinymce?.triggerSave();submitting=true;});
    });
    center.querySelectorAll('.ig-copy-default').forEach(button=>button.addEventListener('click',()=>{const field=button.closest('.ig-copy-field'),input=field.querySelector('input,textarea');input.value=field.dataset.copyDefault;input.dispatchEvent(new Event('input',{bubbles:true}));}));
    const search=document.getElementById('ig-copy-search');
    search?.addEventListener('input',()=>{const q=search.value.trim().toLocaleLowerCase('fa');let found=0;center.querySelectorAll('.ig-copy-section').forEach(section=>{let matches=0;section.querySelectorAll('.ig-copy-field').forEach(field=>{const text=(field.textContent+' '+(field.querySelector('input,textarea')?.value||'')).toLocaleLowerCase('fa');const show=!q||text.includes(q);field.hidden=!show;if(show)matches++;});section.hidden=!matches;if(q&&matches)section.open=true;found+=matches;});document.getElementById('ig-copy-no-results').hidden=found>0;});
    document.getElementById('ig-copy-expand')?.addEventListener('click',event=>{const sections=[...center.querySelectorAll('.ig-copy-section:not([hidden])')],open=sections.some(s=>!s.open);sections.forEach(s=>s.open=open);event.target.textContent=open?'بستن همهٔ بخش‌ها':'بازکردن همهٔ بخش‌ها';});
    center.querySelectorAll('[data-copy-confirm]').forEach(button=>button.addEventListener('click',event=>{if(!confirm(button.dataset.copyConfirm))event.preventDefault();else submitting=true;}));
    const dialog=document.getElementById('ig-copy-preview'),frame=document.getElementById('ig-copy-preview-frame'),status=document.getElementById('ig-copy-preview-status');
    center.querySelectorAll('.ig-copy-preview-button').forEach(button=>button.addEventListener('click',async()=>{
        const form=button.closest('form');window.tinymce?.triggerSave();const body=new FormData(form);body.set('action','ig_copy_preview');body.set('preview_nonce',window.igCopyAdmin.nonce);body.set('preview_route',document.getElementById('ig-copy-preview-route')?.value||'index');
        button.disabled=true;status.textContent='در حال آماده‌سازی پیش‌نمایش…';frame.removeAttribute('src');dialog.showModal();
        try{const response=await fetch(window.igCopyAdmin.ajax,{method:'POST',body,credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.success)throw new Error(result.data?.message||'پیش‌نمایش آماده نشد.');frame.src=result.data.url;status.textContent='این تغییرات هنوز ذخیره نشده‌اند.';}
        catch(error){status.textContent=error.message||'ارتباط برقرار نشد. دوباره تلاش کنید.';}
        finally{button.disabled=false;}
    }));
    document.getElementById('ig-copy-preview-close')?.addEventListener('click',()=>dialog.close());
    center.querySelectorAll('[data-preview-width]').forEach(button=>button.addEventListener('click',()=>{frame.style.width=button.dataset.previewWidth;}));
    window.addEventListener('beforeunload',event=>{if(!dirty||submitting)return;event.preventDefault();event.returnValue='';});
    if(window.tinymce)window.tinymce.on('AddEditor',event=>event.editor.on('change input undo redo',changed));
})();
