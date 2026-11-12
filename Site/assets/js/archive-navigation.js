/* Real archive URLs remain usable with JavaScript disabled. */
(() => {
    'use strict';
    const initialTitle=document.title.replace(/ — صفحهٔ [۰-۹0-9]+$/u,'');
    const base=new URL(document.querySelector('link[rel="canonical"]')?.href||location.href);
    base.searchParams.delete('ig_page');base.hash='';
    const fa=n=>new Intl.NumberFormat('fa-IR').format(n);
    const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const page=()=>Math.max(1,Number.parseInt(new URL(location.href).searchParams.get('ig_page'),10)||1);
    function url(n){const u=new URL(base);if(n>1)u.searchParams.set('ig_page',n);return u.href;}
    function sync(n,filtered=false,push=false){
        const target=url(filtered?1:n),visible=new URL(location.href);
        if(n>1&&!filtered)visible.searchParams.set('ig_page',n);else visible.searchParams.delete('ig_page');
        if(visible.href!==location.href)history[push?'pushState':'replaceState'](null,'',visible.href);
        document.title=initialTitle+(n>1&&!filtered?' — صفحهٔ '+fa(n):'');
        const canonical=document.querySelector('link[rel="canonical"]');if(canonical)canonical.href=target;
        for(const selector of ['meta[property="og:url"]'])document.querySelector(selector)?.setAttribute('content',target);
        for(const selector of ['meta[property="og:title"]','meta[name="twitter:title"]'])document.querySelector(selector)?.setAttribute('content',document.title);
    }
    function render(pages,current,filtered=false){
        sync(current,filtered);if(pages<2)return '';
        function control(n,label,text,disabled=false){
            if(disabled)return `<button type="button" disabled aria-label="${escape(label)}">${text}</button>`;
            const attrs=`data-page="${n}" aria-label="${escape(label)}"${n===current?' aria-current="page"':''}`;
            return filtered?`<button type="button" ${attrs}>${text}</button>`:`<a href="${escape(url(n))}" ${attrs}>${text}</a>`;
        }
        return control(current-1,'صفحه قبل','❮',current===1)+Array.from({length:pages},(_,i)=>control(i+1,'صفحه '+fa(i+1),fa(i+1))).join('')+control(current+1,'صفحه بعد','❯',current===pages);
    }
    window.ArchiveNavigation={page,url,render,commit:(n,filtered=false)=>sync(n,filtered,true)};
})();
