/* داده‌های نمایشی آرشیو؛ قابل جایگزینی با اسناد انجمن. */
(() => {
    'use strict';
    const categories = {agriculture: 'غلات و کشاورزی', customs: 'گمرک و تجارت', market: 'تنظیم بازار', systems: 'سامانه‌ها'};
    const documents = [
        {id: 1, date: '2026-10-25', category: 'agriculture', title: 'دستورالعمل گندم دامی و انسانی', description: 'مروری بر دستورالعمل اجرایی خرید و عرضهٔ گندم و ضوابط مرتبط با زنجیرهٔ تأمین آن.'},
        {id: 2, date: '2026-10-24', category: 'agriculture', title: 'آموزش ثبت نام خریدار گندم', description: 'آشنایی با مراحل ثبت اطلاعات خریداران گندم و مدارک مورد نیاز برای تکمیل درخواست.'},
        {id: 3, date: '2026-10-23', category: 'agriculture', title: 'آموزش فرایند زنجیره گندم', description: 'مروری بر مراحل و فرایندهای زنجیرهٔ تأمین گندم، از تولید و نگهداری تا مصرف.'},
        {id: 4, date: '2026-10-21', category: 'customs', title: 'اطلاعیه تغییرات تعرفه‌های گمرکی', description: 'مرور تغییرات تعرفه‌ها و نکات قابل توجه در تهیهٔ اسناد و اظهار محموله‌های غلات.'},
        {id: 5, date: '2026-10-20', category: 'market', title: 'ضوابط عرضه و توزیع نهاده‌های دامی', description: 'آشنایی با روند عرضهٔ نهاده‌ها و هماهنگی میان تأمین‌کنندگان و فعالان زنجیرهٔ توزیع.'},
        {id: 6, date: '2026-10-19', category: 'systems', title: 'راهنمای اتصال سامانه‌های تجاری', description: 'مرور مراحل تبادل اطلاعات و ثبت مشخصات محموله‌ها در سامانه‌های مرتبط با تجارت غلات.'},
        {id: 7, date: '2026-10-18', category: 'agriculture', title: 'ضوابط نگهداری و انبارش غلات', description: 'مروری بر شرایط نگهداری، کنترل کیفیت و پایش موجودی محصولات در انبارهای غلات.'},
        {id: 8, date: '2026-10-17', category: 'customs', title: 'راهنمای مدارک ترخیص محموله‌های غلات', description: 'آشنایی با اسناد مورد نیاز و مراحل بررسی مدارک تجاری و گمرکی محموله‌ها.'},
        {id: 9, date: '2026-10-16', category: 'market', title: 'اطلاعیه پایش موجودی و عرضه محصولات', description: 'مرور شیوهٔ ثبت موجودی و گزارش وضعیت عرضه برای هماهنگی بهتر فعالان بازار.'}
    ];
    const $ = (selector) => document.querySelector(selector);
    const form = $('#circularFilters'), search = $('#circularSearch'), category = $('#circularCategory'), sort = $('#circularSort');
    const grid = $('#circularGrid'), pagination = $('#circularPagination'), dialog = $('#circularDialog');
    const fa = (n) => new Intl.NumberFormat('fa-IR').format(n);
    const date = (value) => new Intl.DateTimeFormat('fa-IR', {timeZone: 'Asia/Tehran', year: 'numeric', month: 'long', day: 'numeric'}).format(new Date(value + 'T12:00:00Z'));
    const normalize = (value) => value.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u200c\u200f]/g, ' ').replace(/\s+/g, ' ').trim();
    const docIcon = '<svg width="24" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H5v18h14V8Z"/><path d="M14 3v5h5M8 12h8M8 16h6"/></svg>';
    const arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6"/></svg>';
    let currentPage = 1;
    const pageSize = 6;
    $('#archiveTotal').textContent = fa(documents.length);
    function render() {
        const query = normalize(search.value);
        const results = documents.filter((item) => (category.value === 'all' || item.category === category.value) && normalize(item.title + ' ' + item.description).includes(query))
            .sort((a, b) => sort.value === 'oldest' ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date));
        const pages = Math.ceil(results.length / pageSize);
        currentPage = Math.min(currentPage, pages || 1);
        grid.innerHTML = results.slice((currentPage - 1) * pageSize, currentPage * pageSize).map((item) => `<article class="circulars-card"><div class="circulars-card-top"><span class="circulars-tag">${categories[item.category]}</span><time datetime="${item.date}">${date(item.date)}</time></div><div class="circulars-card-title"><span class="circulars-doc-icon">${docIcon}</span><h3>${item.title}</h3></div><p>${item.description}</p><div class="circulars-card-bottom"><span>بخشنامه نمونه</span><button type="button" data-open="${item.id}" aria-label="مشاهده ${item.title}">مشاهده بخشنامه ${arrow}</button></div></article>`).join('');
        $('#circularCount').textContent = results.length ? `نمایش ${fa((currentPage - 1) * pageSize + 1)} تا ${fa(Math.min(currentPage * pageSize, results.length))} از ${fa(results.length)} بخشنامه` : '۰ بخشنامه';
        $('#circularEmpty').hidden = results.length > 0;
        $('#circularFeature').hidden = !!query || category.value !== 'all' || currentPage !== 1 || sort.value !== 'newest';
        pagination.innerHTML = pages > 1 ? `<button type="button" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''} aria-label="صفحه قبل">❯</button>` + Array.from({length: pages}, (_, i) => `<button type="button" data-page="${i + 1}" ${currentPage === i + 1 ? 'aria-current="page"' : ''} aria-label="صفحه ${fa(i + 1)}">${fa(i + 1)}</button>`).join('') + `<button type="button" data-page="${currentPage + 1}" ${currentPage === pages ? 'disabled' : ''} aria-label="صفحه بعد">❮</button>` : '';
    }
    [search, category, sort].forEach((input) => input.addEventListener(input === search ? 'input' : 'change', () => { currentPage = 1; render(); }));
    form.addEventListener('submit', (event) => event.preventDefault());
    form.addEventListener('reset', () => { requestAnimationFrame(() => {currentPage = 1; render();}); });
    pagination.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        currentPage = Number(button.dataset.page);
        render();
        $('#archiveTitle').scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'});
        pagination.querySelector('[aria-current="page"]')?.focus({preventScroll: true});
    });
    document.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => {
        grid.classList.toggle('is-list', button.dataset.view === 'list');
        document.querySelectorAll('[data-view]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    }));
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-open]');
        if (!button) return;
        const item = documents.find((doc) => doc.id === Number(button.dataset.open));
        if (!item) return;
        $('#circularDialogTitle').textContent = item.title;
        $('#circularDialogCategory').textContent = categories[item.category];
        $('#circularDialogDate').textContent = date(item.date);
        $('#circularDialogDate').dateTime = item.date;
        $('#circularDialogBody').textContent = item.description;
        dialog.showModal();
    });
    $('.circulars-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    render();
})();
