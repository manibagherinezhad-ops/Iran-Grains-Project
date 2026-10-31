/* Names, activities and logo URLs are from the association member directory. */
(() => {
    'use strict';
    const companies = [
    {
        "id": 1,
        "name": "کشت و صنعت اپال",
        "activity": "تأمین، تولید و توزیع محصولات کشاورزی",
        "description": "فعال در زنجیرهٔ تأمین روغن، غلات، کنجاله و نهاده‌های دامی.",
        "categories": [
            "production",
            "grains",
            "feed",
            "oils"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/09/logo2-300x215-removebg-preview.png",
        "source": "https://iran-grains.com/kesht-sanat-opal/"
    },
    {
        "id": 2,
        "name": "کالادشت",
        "activity": "بازرگانی و تأمین مواد اولیه",
        "description": "تأمین مواد اولیهٔ صنعتی و همچنین نهاده‌های کشاورزی و دامی.",
        "categories": [
            "grains",
            "feed",
            "other"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/12/WhatsApp_Image_2025-12-01_at_12.35.53-removebg-preview.png",
        "source": "https://iran-grains.com/kaladasht/"
    },
    {
        "id": 3,
        "name": "پرشین پروتئین پارس",
        "activity": "واردات غلات و محصولات غذایی",
        "description": "واردکنندهٔ غلات، نهاده‌های دامی، حبوبات، گوشت و قهوه.",
        "categories": [
            "import",
            "grains",
            "feed",
            "other"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/12/pppars_engLogo-removebg-preview-removebg-preview.png",
        "source": "https://iran-grains.com/persian-protein-pars/"
    },
    {
        "id": 4,
        "name": "هلدینگ توسعه غزال",
        "activity": "بازرگانی و زنجیرهٔ تأمین",
        "description": "فعال در واردات نهاده‌ها و دانه‌های روغنی و تولید محصولات مرتبط با دام و طیور.",
        "categories": [
            "import",
            "production",
            "grains",
            "feed",
            "oils"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/dc2bc4d2-9379-490e-8ee2-edea522016b2-removebg-preview.png",
        "source": "https://iran-grains.com/holding-tosee-ghazal/"
    },
    {
        "id": 5,
        "name": "حامی کشاورز وطن",
        "activity": "واردات نهاده‌های دامی",
        "description": "تأمین ذرت، جو، سویا و کنجاله برای زنجیرهٔ نهاده‌های دامی.",
        "categories": [
            "import",
            "grains",
            "feed"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/151910fb-978f-4305-875b-76f46f6aeff1-removebg-preview.png",
        "source": "https://iran-grains.com/hami-keshavarz-vatan/"
    },
    {
        "id": 6,
        "name": "زنجیره سالم طیور",
        "activity": "فعالیت در زنجیرهٔ طیور",
        "description": "فعال در کشتارگاه طیور و واردات نهاده‌هایی مانند ذرت، جو، سویا و کنجاله.",
        "categories": [
            "import",
            "production",
            "feed"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/377d401e-85f1-4915-83b3-d30dd8bd72f1__1_-removebg-preview.png",
        "source": "https://iran-grains.com/zanjire-salem-tayour/"
    },
    {
        "id": 7,
        "name": "توسعه زنجیره سلامت",
        "activity": "تأمین نهاده و محصولات دامی",
        "description": "واردات گوشت منجمد و نهاده‌های دامی شامل ذرت، جو، سویا و کنجاله.",
        "categories": [
            "import",
            "grains",
            "feed"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/6d4168a3-58e0-4218-be2f-03f17909f25e__1_-removebg-preview.png",
        "source": "https://iran-grains.com/tosee-zanjire-salamat/"
    },
    {
        "id": 8,
        "name": "سالم خوراک افق",
        "activity": "فعالیت در حوزهٔ نهاده‌های دامی",
        "description": "واردات نهاده‌های دامی و تولید شوینده‌ها و ویتامین‌های مربوط به دام.",
        "categories": [
            "import",
            "production",
            "feed"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/2a12c55b-9265-4c55-a615-70ff94e40a43__1_-removebg-preview.png",
        "source": "https://iran-grains.com/salem-khorak-ofogh/"
    },
    {
        "id": 9,
        "name": "توسعه غلات افق",
        "activity": "واردات نهاده‌های دامی",
        "description": "فعال در تأمین ذرت، جو، سویا و کنجاله برای زنجیرهٔ خوراک دام.",
        "categories": [
            "import",
            "grains",
            "feed"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/9ebe5c55-974e-4bad-9e6b-80dc7012a7bc-removebg-preview.png",
        "source": "https://iran-grains.com/tosee-ghalat-ofogh/"
    },
    {
        "id": 10,
        "name": "غلات صنعت غزال",
        "activity": "تأمین و واردات نهاده‌های دامی",
        "description": "واردکنندهٔ نهاده‌های دامی از جمله ذرت، جو، سویا و کنجاله.",
        "categories": [
            "import",
            "grains",
            "feed"
        ],
        "logo": "https://iran-grains.com/wp-content/uploads/2025/07/097a1f9e-6c9e-40d3-a871-2971f5ce6045-removebg-preview.png",
        "source": "https://iran-grains.com/ghalat-sanat-ghazal/"
    }
];
    const categories = {import: 'واردات', production: 'تولید', grains: 'غلات', feed: 'نهاده‌های دامی', oils: 'دانه‌های روغنی', other: 'سایر'};
    const $ = (id) => document.getElementById(id);
    const grid = $('membersGrid'), search = $('membersSearch'), pagination = $('membersPagination'), dialog = $('memberDialog');
    const fa = (n) => new Intl.NumberFormat('fa-IR').format(n);
    const normalize = (s) => s.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u200c\u200f]/g, ' ').replace(/[\u064b-\u065f]/g, '').replace(/\s+/g, ' ').trim();
    const escape = (s) => s.replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6"/></svg>';
    const leaf = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M20 3C8 3 3 8 5 16c8 4 15-1 15-13ZM4 21 16 9"/></svg>';
    let category = 'all', page = 1;
    const pageSize = 8;
    function brand(company) {
        return `<div class="company-brand brand-tone-${company.id % 4}"><span class="company-brand-fallback" aria-hidden="true">${leaf}<b>${escape(company.name.split(' ')[0])}</b></span><img data-logo="${escape(company.logo)}" alt="نشان ${escape(company.name)}" loading="lazy" decoding="async" referrerpolicy="no-referrer"></div>`;
    }
    function loadBrands(root) {
        root.querySelectorAll('img[data-logo]').forEach((img) => {
            img.addEventListener('load', () => {if (img.naturalWidth) img.parentElement.classList.add('has-logo');}, {once: true});
            img.addEventListener('error', () => img.remove(), {once: true});
            img.src = img.dataset.logo;
        });
    }
    function card(company, featured = false, compact = false) {
        return `<article class="member-card${featured ? ' member-feature' : ''}${compact ? ' member-compact' : ''}">${featured ? '<span class="member-feature-badge">✧ معرفی عضو</span>' : ''}${brand(company)}<div class="member-card-copy"><h3>${escape(company.name)}</h3><span class="member-activity">${escape(company.activity)}</span><p>${escape(company.description)}</p><button type="button" class="members-more${featured ? ' members-more-primary' : ''}" data-company="${company.id}" aria-label="دربارهٔ ${escape(company.name)} بیشتر بدانید">بیشتر بدانید ${arrow}</button></div></article>`;
    }
    function ribbon() {
        return `<aside class="members-ribbon"><span aria-hidden="true">${leaf}</span><div><h3>${fa(companies.length)} شرکت، همراه صنعت غلات</h3><p>در مسیر توسعهٔ پایدار زنجیرهٔ تأمین، با هم همراه باشیم.</p><a class="btn" href="https://iran-grains.com/sherkat-haye-ozv/" target="_blank" rel="noopener">مشاهده همهٔ شرکت‌ها ${arrow}</a></div></aside>`;
    }
    function render(animate = true) {
        const query = normalize(search.value);
        const results = companies.filter((company) => (category === 'all' || company.categories.includes(category)) && normalize(company.name + ' ' + company.activity + ' ' + company.description + ' ' + company.categories.map((key) => categories[key]).join(' ')).includes(query));
        const pages = Math.ceil(results.length / pageSize);
        page = Math.min(page, Math.max(pages, 1));
        const items = results.slice((page - 1) * pageSize, page * pageSize);
        const featured = category === 'all' && !query && page === 1 && items.length === 8;
        grid.innerHTML = featured ? card(items[1]) + card(items[2]) + card(items[0], true) + items.slice(3, 7).map((company) => card(company)).join('') + ribbon() + card(items[7], false, true) : items.map((company) => card(company)).join('');
        loadBrands(grid);
        $('membersEmpty').hidden = results.length > 0;
        $('membersClear').hidden = category === 'all' && !query;
        $('membersCount').textContent = results.length ? `نمایش ${fa((page - 1) * pageSize + 1)} تا ${fa(Math.min(page * pageSize, results.length))} از ${fa(results.length)} شرکت` : '۰ شرکت';
        document.querySelectorAll('[data-category]').forEach((button) => {
            const active = button.dataset.category === category;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        // Keep arrow directions consistent with the approved RTL pagination.
        pagination.innerHTML = pages > 1 ? `<button type="button" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''} aria-label="صفحه قبل">❮</button>` + Array.from({length: pages}, (_, index) => `<button type="button" data-page="${index + 1}" ${page === index + 1 ? 'aria-current="page"' : ''} aria-label="صفحه ${fa(index + 1)}">${fa(index + 1)}</button>`).join('') + `<button type="button" data-page="${page + 1}" ${page === pages ? 'disabled' : ''} aria-label="صفحه بعد">❯</button>` : '';
        if (animate) window.SiteMotion?.reveal(grid.children);
    }
    function reset() { category = 'all'; search.value = ''; page = 1; render(); }
    function show(company, trigger) {
        $('memberDialogBrand').innerHTML = brand(company);
        loadBrands($('memberDialogBrand'));
        $('memberDialogTitle').textContent = company.name;
        $('memberDialogActivity').textContent = company.activity;
        $('memberDialogDescription').textContent = company.description;
        $('memberDialogTags').innerHTML = company.categories.map((key) => `<span>${categories[key]}</span>`).join('');
        $('memberDialogSource').href = company.source;
        trigger.focus({preventScroll: true});
        dialog.showModal();
        window.SiteMotion?.reveal([dialog]);
    }
    $('membersTotal').textContent = fa(companies.length);
    $('membersSearchForm').addEventListener('submit', (event) => event.preventDefault());
    search.addEventListener('input', () => {page = 1; render();});
    document.querySelectorAll('[data-category]').forEach((button) => button.addEventListener('click', () => {category = button.dataset.category; page = 1; render();}));
    $('membersClear').addEventListener('click', reset);
    $('membersEmptyReset').addEventListener('click', () => {reset(); search.focus({preventScroll: true});});
    pagination.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        page = Number(button.dataset.page); render();
        $('directoryTitle').scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'});
        pagination.querySelector('[aria-current="page"]')?.focus({preventScroll: true});
    });
    grid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-company]');
        if (!button) return;
        const company = companies.find((item) => item.id === Number(button.dataset.company));
        if (company) show(company, button);
    });
    dialog.querySelector('.member-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    render(false);
})();
