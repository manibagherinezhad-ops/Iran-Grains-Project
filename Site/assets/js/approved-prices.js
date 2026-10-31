/* Pricing notices from the association archive; dates are publication dates. */
(() => {
    'use strict';
    const categories = {ceilings: 'سقف ارزی', oils: 'روغن و دانه‌های روغنی', customs: 'نرخ مبنای گمرک', consumer: 'قیمت مصرف‌کننده'};
    const documents = [
    {
        "id": 1,
        "date": "2026-10-05",
        "category": "ceilings",
        "title": "سقف ارزی واردات غلات",
        "description": "ابلاغیهٔ سقف ارزی غلات به تاریخ ۱۲ مهر ۱۴۰۵؛ جزئیات اقلام و جدول قیمت در سند اصلی منتشر شده است.",
        "source": "https://iran-grains.com/elam-saghf-arzi-anvae-ghallat/"
    },
    {
        "id": 2,
        "date": "2026-09-15",
        "category": "ceilings",
        "title": "سقف ارزی غلات و کالاهای اساسی",
        "description": "ابلاغیهٔ ۲۱ شهریور ۱۴۰۵ دربارهٔ غلات، گوشت، روغن و شکر خام. جدول اقلام مشمول در منبع اصلی در دسترس است.",
        "source": "https://iran-grains.com/saghf-arzi-ghallat-goosht-roghan-shakar/"
    },
    {
        "id": 3,
        "date": "2026-08-24",
        "category": "ceilings",
        "title": "سقف ارزی گندم و نهاده‌های دامی",
        "description": "اطلاعیهٔ مربوط به اول شهریور ۱۴۰۵ شامل نهاده‌های دامی، چای، گوشت و گندم خوراکی و دامی است.",
        "source": "https://iran-grains.com/saqf-arzy-nahade-dami-chay-goosht-gandom/"
    },
    {
        "id": 4,
        "date": "2026-08-03",
        "category": "oils",
        "title": "سقف ارزی روغن خام و اصلاحیهٔ آن",
        "description": "سند سقف ارزی روغن خام به تاریخ ۱۲ مرداد ۱۴۰۵ و اصلاحیهٔ ۱۴ مرداد در صفحهٔ اصلی این ابلاغیه قرار دارند.",
        "source": "https://iran-grains.com/sagh-arzi-nahade-roghan-kham/"
    },
    {
        "id": 5,
        "date": "2026-06-13",
        "category": "ceilings",
        "title": "سقف ارزی برخی نهاده‌های دامی",
        "description": "این اطلاعیه به سقف ارزی برخی نهاده‌های دامی در ۱۷ خرداد ۱۴۰۵ مربوط است؛ فهرست و قیمت‌ها را در سند ببینید.",
        "source": "https://iran-grains.com/elam-saghf-arzi-anvae-nahade-haye-dami-17-khordad/"
    },
    {
        "id": 6,
        "date": "2026-05-25",
        "category": "ceilings",
        "title": "سقف ارزی حبوبات و سایر اقلام اساسی",
        "description": "ابلاغیهٔ ۳۰ اردیبهشت ۱۴۰۵، حبوبات، روغن خام و گوشت قرمز را پوشش می‌دهد. اطلاعات تفصیلی در منبع آمده است.",
        "source": "https://iran-grains.com/saghf-arzi-hobobat-roghan-goosht-1405/"
    },
    {
        "id": 7,
        "date": "2026-05-16",
        "category": "oils",
        "title": "قیمت ارزی دانه و کنجالهٔ سویا",
        "description": "اطلاعیهٔ قیمت ارزی سویا و کنجاله، مربوط به ۲۳ اردیبهشت ۱۴۰۵. جدول اعلام‌شده در صفحهٔ مرجع قابل مشاهده است.",
        "source": "https://iran-grains.com/gheymat-arzi-konjale-soya/"
    },
    {
        "id": 8,
        "date": "2026-05-13",
        "category": "oils",
        "title": "سقف ارزی برنج و محصولات روغنی",
        "description": "ابلاغیهٔ ۲۱ اردیبهشت ۱۴۰۵ دربارهٔ برنج، کنجاله‌ها و دانه‌های روغنی؛ اقلام و نرخ‌ها در سند اصلی مشخص شده‌اند.",
        "source": "https://iran-grains.com/saghf-arzi-berenj-konjale-daneh-roghani/"
    },
    {
        "id": 9,
        "date": "2026-05-08",
        "category": "customs",
        "title": "نرخ ارز مبنای گمرک در سال ۱۴۰۵",
        "description": "جدول محاسبات گمرکی سال ۱۴۰۵ با مبنای نرخ ارز بهمن ۱۴۰۴. برای جزئیات، ابلاغیهٔ پیوست منبع را ببینید.",
        "source": "https://iran-grains.com/nerkh-arz-gomrok-1405-mabna-bahman-1404/"
    },
    {
        "id": 10,
        "date": "2026-05-04",
        "category": "consumer",
        "title": "قیمت مصرف‌کنندهٔ روغن نباتی",
        "description": "اطلاعیهٔ قیمت روغن نباتی به تاریخ ۱۵ اردیبهشت ۱۴۰۵. جزئیات محصولات و نرخ‌های اعلامی در سند مرجع درج شده‌اند.",
        "source": "https://iran-grains.com/gheymat-roghan-nabati-1405-02-15/"
    }
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
    // Keep native selects as form controls; enhance them with custom RTL menus.
    const dropdowns = [category, sort].map((select) => {
        const label = select.closest('label');
        const wrapper = document.createElement('div');
        wrapper.className = 'circulars-select';
        label.replaceWith(wrapper);
        wrapper.append(label);
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'circulars-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const list = document.createElement('div');
        list.id = select.id + 'List';
        list.className = 'circulars-select-menu';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', select === category ? 'موضوع ابلاغیه' : 'ترتیب نمایش');
        list.hidden = true;
        trigger.setAttribute('aria-controls', list.id);
        const text = document.createElement('span');
        trigger.append(text);
        trigger.insertAdjacentHTML('beforeend', '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>');
        const options = Array.from(select.options, (option, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'circulars-select-option';
            button.setAttribute('role', 'option');
            button.tabIndex = -1;
            button.textContent = option.text;
            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', {bubbles: true}));
                close();
                trigger.focus({preventScroll: true});
            });
            button.addEventListener('keydown', (event) => {
                if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                    event.preventDefault();
                    const next = event.key === 'Home' ? 0 : event.key === 'End' ? select.options.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + select.options.length) % select.options.length;
                    options[next].focus();
                }
            });
            list.append(button);
            return button;
        });
        function close() { list.hidden = true; trigger.setAttribute('aria-expanded', 'false'); }
        function open(last = false) {
            dropdowns.forEach((item) => item.close());
            list.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            options[last ? options.length - 1 : select.selectedIndex].focus({preventScroll: true});
        }
        trigger.addEventListener('click', () => list.hidden ? open() : close());
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {event.preventDefault(); open(event.key === 'ArrowUp');}
        });
        wrapper.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {event.preventDefault(); close(); trigger.focus({preventScroll: true});}
        });
        wrapper.addEventListener('focusout', () => {
            requestAnimationFrame(() => {if (!wrapper.contains(document.activeElement)) close();});
        });
        wrapper.append(trigger, list);
        label.hidden = true;
        return {close, wrapper, sync() {
            text.textContent = select.selectedOptions[0].text;
            trigger.setAttribute('aria-label', (select === category ? 'موضوع: ' : 'ترتیب نمایش: ') + text.textContent);
            options.forEach((option, index) => option.setAttribute('aria-selected', String(select.selectedIndex === index)));
        }};
    });
    document.addEventListener('click', (event) => dropdowns.forEach((item) => {if (!item.wrapper.contains(event.target)) item.close();}));

    $('#archiveTotal').textContent = fa(documents.length);
    function render() {
        dropdowns.forEach((item) => item.sync());
        const query = normalize(search.value);
        const results = documents.filter((item) => (category.value === 'all' || item.category === category.value) && normalize(item.title + ' ' + item.description).includes(query))
            .sort((a, b) => sort.value === 'oldest' ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date));
        const pages = Math.ceil(results.length / pageSize);
        currentPage = Math.min(currentPage, pages || 1);
        grid.innerHTML = results.slice((currentPage - 1) * pageSize, currentPage * pageSize).map((item) => `<article class="circulars-card"><div class="circulars-card-top"><span class="circulars-tag">${categories[item.category]}</span><time datetime="${item.date}">${date(item.date)}</time></div><div class="circulars-card-title"><span class="circulars-doc-icon">${docIcon}</span><h3>${item.title}</h3></div><p>${item.description}</p><div class="circulars-card-bottom"><button type="button" data-open="${item.id}" aria-label="مشاهده ${item.title}">مشاهده ابلاغیه ${arrow}</button></div></article>`).join('');
        $('#circularCount').textContent = results.length ? `نمایش ${fa((currentPage - 1) * pageSize + 1)} تا ${fa(Math.min(currentPage * pageSize, results.length))} از ${fa(results.length)} ابلاغیه` : '۰ ابلاغیه';
        $('#circularEmpty').hidden = results.length > 0;
        $('#circularFeature').hidden = !!query || category.value !== 'all' || currentPage !== 1 || sort.value !== 'newest';
        // جهت فلش‌ها را مطابق چیدمان تأییدشدهٔ راست‌به‌چپ پروژه حفظ کنید.
        pagination.innerHTML = pages > 1 ? `<button type="button" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''} aria-label="صفحه قبل">❮</button>` + Array.from({length: pages}, (_, i) => `<button type="button" data-page="${i + 1}" ${currentPage === i + 1 ? 'aria-current="page"' : ''} aria-label="صفحه ${fa(i + 1)}">${fa(i + 1)}</button>`).join('') + `<button type="button" data-page="${currentPage + 1}" ${currentPage === pages ? 'disabled' : ''} aria-label="صفحه بعد">❯</button>` : '';
    }
    [search, category, sort].forEach((input) => input.addEventListener(input === search ? 'input' : 'change', () => { currentPage = 1; render(); window.SiteMotion?.reveal(grid.children); }));
    form.addEventListener('submit', (event) => event.preventDefault());
    form.addEventListener('reset', () => { dropdowns.forEach((item) => item.close()); requestAnimationFrame(() => {currentPage = 1; render(); window.SiteMotion?.reveal(grid.children);}); });
    pagination.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        currentPage = Number(button.dataset.page);
        render();
        window.SiteMotion?.reveal(grid.children);
        $('#archiveTitle').scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'});
        pagination.querySelector('[aria-current="page"]')?.focus({preventScroll: true});
    });
    document.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => {
        grid.classList.toggle('is-list', button.dataset.view === 'list');
        document.querySelectorAll('[data-view]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        window.SiteMotion?.reveal(grid.children);
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
        $('#priceSource').href = item.source;
        dialog.showModal();
        window.SiteMotion?.reveal([dialog]);
    });
    $('.circulars-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    render();
})();
