(() => {
    'use strict';
    const $ = (id) => document.getElementById(id);
    const id = Number(new URLSearchParams(location.search).get('id'));
    const company = window.MemberDirectory.find((item) => item.id === id);
    if (!company) {
        document.title = 'شرکت یافت نشد | انجمن غلات ایران';
        $('companyMain').innerHTML = '<section class="container company-not-found"><h1>شرکت موردنظر یافت نشد</h1><a class="btn btn-green-outline" href="members.html">بازگشت به شرکت‌های عضو</a></section>';
        return;
    }
    const categories = {import:'واردات', production:'تولید', grains:'غلات', feed:'نهاده‌های دامی', oils:'دانه‌های روغنی', other:'سایر'};
    document.title = company.name + ' | انجمن غلات ایران';
    $('companyTitle').textContent = company.name;
    $('companyCrumb').textContent = company.name;
    $('companyActivity').textContent = company.activity;
    $('companyDescription').textContent = company.fullActivity || company.description;
    $('companyManager').textContent = company.manager || 'درج نشده';
    $('companySource').href = company.source;
    company.categories.forEach((key) => {const span=document.createElement('span');span.textContent=categories[key];$('companyTags').append(span);});
    const brand=document.createElement('div');brand.className='company-brand';
    const fallback=document.createElement('span');fallback.className='company-brand-fallback';fallback.textContent=company.name.split(' ')[0];brand.append(fallback);
    const logo=new Image();logo.alt='نشان '+company.name;logo.referrerPolicy='no-referrer';logo.addEventListener('load',()=>brand.classList.add('has-logo'));logo.addEventListener('error',()=>logo.remove());logo.src=company.logo;brand.append(logo);$('companyBrand').append(brand);
    const latin=(s)=>s.replace(/[۰-۹]/g,c=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(c)).replace(/[٠-٩]/g,c=>'٠١٢٣٤٥٦٧٨٩'.indexOf(c));
    [['تلفن',company.phone,'tel'],['فکس',company.fax,'text'],['ایمیل',company.email,'email'],['وب‌سایت',company.website,'web'],['آدرس دفتر',company.address,'address']].forEach(([label,value,type])=>{
        const row=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;
        if(!value) dd.textContent='درج نشده';
        else if(type==='tel') value.split(' / ').forEach((number,index)=>{if(index)dd.append(' / ');const a=document.createElement('a');a.textContent=number;a.dir='ltr';a.href='tel:'+latin(number).replace(/[^+\d]/g,'');dd.append(a);});
        else if(type==='email'||type==='web'){const a=document.createElement('a');a.textContent=type==='web'?value.replace(/^https?:\/\//,'').replace(/\/$/,''):value;a.href=type==='email'?'mailto:'+value:value;a.dir='ltr';if(type==='web'){a.target='_blank';a.rel='noopener';}dd.append(a);}
        else {dd.textContent=value;if(type!=='address')dd.dir='ltr';}
        row.append(dt,dd);$('companyContacts').append(row);
    });
})();
