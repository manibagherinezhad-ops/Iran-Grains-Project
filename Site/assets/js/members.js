/* Names, activities and logo URLs are from the association member directory. */
(() => {
    'use strict';
    const companies = window.MemberDirectory;
    const categories = {import: 'واردات', production: 'تولید', grains: 'غلات', feed: 'نهاده‌های دامی', oils: 'دانه‌های روغنی', other: 'سایر'};
    const $ = (id) => document.getElementById(id);
    const grid = $('membersGrid'), search = $('membersSearch'), pagination = $('membersPagination');
    const fa = (n) => new Intl.NumberFormat('fa-IR').format(n);
    const normalize = (s) => s.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u200c\u200f]/g, ' ').replace(/[\u064b-\u065f]/g, '').replace(/\s+/g, ' ').trim();
    const escape = (s) => s.replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6"/></svg>';
    const leaf = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M20 3C8 3 3 8 5 16c8 4 15-1 15-13ZM4 21 16 9"/></svg>';
    let category = 'all', page = 1, featuredId = 1;
    let order = companies.map((company) => company.id);
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
    function card(company, featured = false) {
        return `<article data-company-card="${company.id}" class="member-card${featured ? ' member-feature' : ''}">${featured ? '<span class="member-feature-badge">✧ معرفی عضو</span>' : ''}<button type="button" class="member-logo-button" data-promote="${company.id}" aria-label="نمایش برجستهٔ ${escape(company.name)}" aria-pressed="${company.id === featuredId}">${brand(company)}</button><div class="member-card-copy"><h3>${escape(company.name)}</h3><span class="member-activity">${escape(company.activity)}</span><p>${escape(company.description)}</p><a href="company.html?id=${company.id}" class="members-more${featured ? ' members-more-primary' : ''}" aria-label="دربارهٔ ${escape(company.name)} بیشتر بدانید">بیشتر بدانید ${arrow}</a></div></article>`;
    }
    function ribbon() {
        return `<aside class="members-ribbon"><span aria-hidden="true">${leaf}</span><div><h3>${fa(companies.length)} شرکت، همراه صنعت غلات</h3><p>در مسیر توسعهٔ پایدار زنجیرهٔ تأمین، با هم همراه باشیم.</p><a class="btn" href="https://iran-grains.com/sherkat-haye-ozv/" target="_blank" rel="noopener">مشاهده همهٔ شرکت‌ها ${arrow}</a></div></aside>`;
    }
    function render(animate = true) {
        const query = normalize(search.value);
        const results = order.map((id) => companies.find((company) => company.id === id)).filter((company) => (category === 'all' || company.categories.includes(category)) && normalize(company.name + ' ' + company.activity + ' ' + company.description + ' ' + company.categories.map((key) => categories[key]).join(' ')).includes(query));
        const pages = Math.ceil(results.length / pageSize);
        page = Math.min(page, Math.max(pages, 1));
        const items = results.slice((page - 1) * pageSize, page * pageSize);
        const featured = page === 1 && items[0]?.id === featuredId;
        if (featured) {
            const others = items.slice(1);
            grid.innerHTML = others.slice(0, 2).map((company) => card(company)).join('') + card(items[0], true) + others.slice(2, 6).map((company) => card(company)).join('') + (category === 'all' && !query && items.length === 8 ? ribbon() : '') + others.slice(6).map((company) => card(company)).join('');
        } else grid.innerHTML = items.map((company) => card(company)).join('');
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
    $('membersTotal').textContent = fa(160);
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
        const logo = event.target.closest('[data-promote]');
        if (!logo) return;
        const id = Number(logo.dataset.promote);
        const old = new Map(Array.from(grid.querySelectorAll('[data-company-card]'), (card) => [Number(card.dataset.companyCard), card.getBoundingClientRect()]));
        featuredId = id;
        order = [id, ...order.filter((key) => key !== id)];
        page = 1;
        render(false);
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
            grid.querySelectorAll('[data-company-card]').forEach((card) => {
                const previous = old.get(Number(card.dataset.companyCard)), next = card.getBoundingClientRect();
                card.animate([{translate: previous ? `${previous.x-next.x}px ${previous.y-next.y}px` : '0 16px', opacity: previous ? .7 : 0}, {translate: '0 0', opacity: 1}], {duration: 480, easing: 'cubic-bezier(.2,.7,.3,1)'});
            });
        }
        grid.querySelector(`[data-promote="${id}"]`)?.focus({preventScroll: true});
    });
    const hero = document.querySelector('.members-hero');
    if (matchMedia('(hover: hover) and (pointer: fine)').matches && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        let frame = 0;
        hero.addEventListener('pointermove', (event) => {
            const x=event.clientX, y=event.clientY;
            cancelAnimationFrame(frame);
            frame=requestAnimationFrame(() => {
                const rect=hero.getBoundingClientRect();
                hero.style.setProperty('--members-x', ((x-rect.left)/rect.width-.5)*18+'px');
                hero.style.setProperty('--members-y', ((y-rect.top)/rect.height-.5)*14+'px');
            });
        });
        hero.addEventListener('pointerleave', () => {cancelAnimationFrame(frame);hero.style.setProperty('--members-x','0px');hero.style.setProperty('--members-y','0px');});
    }
    render(false);
})();
