/* Latest and second-latest news are selected from the dated project feed. */
(() => {
    'use strict';
    const categories = {association:'اخبار انجمن', domestic:'تولید داخلی', world:'بازار جهانی', market:'قیمت‌ها و بازار'};
    const documents = [...(window.NewsItems || [])].sort((a,b) => b.date.localeCompare(a.date) || b.id-a.id);
    const latest = documents[0], second = documents[1];
    const escape = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
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
        list.setAttribute('aria-label', select === category ? 'موضوع خبر' : 'ترتیب نمایش');
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

    $('#archiveTotalLabel').textContent = fa(documents.length) + ' خبر در آرشیو';
    if (latest) {
        $('#newsHeroTitle').textContent = latest.title;
        $('#newsHeroDescription').textContent = latest.description;
        $('#newsHeroDate').textContent = date(latest.date);
        $('#newsHeroDate').dateTime = latest.date;
        $('#newsHeroCategory').textContent = categories[latest.category];
        $('#newsHeroLink').dataset.open = latest.id;
        document.querySelector('.circulars-hero-photo').style.backgroundImage = `linear-gradient(90deg, #10282010, #10282026), url("${latest.image}")`;
    } else {
        $('#newsHeroTitle').textContent = 'اخبار';
        $('#newsHeroDescription').textContent = 'هنوز خبری ثبت نشده است.';
        $('#newsHeroLink').hidden = true;
    }
    if (second) {
        $('#newsFeatureTitle').textContent = second.title;
        $('#newsFeatureDescription').textContent = second.description;
        $('#newsFeatureDate').textContent = date(second.date);
        $('#newsFeatureDate').dateTime = second.date;
        $('#newsFeatureLink').dataset.open = second.id;
        $('#newsFeatureImage').src = second.image;
    }

    function render() {
        dropdowns.forEach((item) => item.sync());
        const query = normalize(search.value);
        const archiveItems = !query && category.value === 'all' && sort.value === 'newest' ? documents.slice(2) : documents;
        const results = archiveItems.filter((item) => (category.value === 'all' || item.category === category.value) && normalize(item.title + ' ' + item.description).includes(query))
            .sort((a, b) => sort.value === 'oldest' ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date));
        const pages = Math.ceil(results.length / pageSize);
        currentPage = Math.min(currentPage, pages || 1);
        grid.innerHTML = results.slice((currentPage-1)*pageSize,currentPage*pageSize).map(item => `<article class="circulars-card"><img class="archive-news-image" src="${escape(item.image)}" alt="" loading="lazy"><div class="archive-news-content"><div class="circulars-card-top"><span class="circulars-tag">${categories[item.category]}</span><time datetime="${item.date}">${date(item.date)}</time></div><div class="circulars-card-title"><h3>${escape(item.title)}</h3></div><p>${escape(item.description)}</p></div><div class="circulars-card-bottom"><button type="button" data-open="${item.id}" aria-label="مشاهده ${escape(item.title)}">مشاهده خبر ${arrow}</button></div></article>`).join('');
        $('#circularCount').textContent = results.length ? `نمایش ${fa((currentPage - 1) * pageSize + 1)} تا ${fa(Math.min(currentPage * pageSize, results.length))} از ${fa(results.length)} خبر` : '۰ خبر';
        $('#circularEmpty').hidden = results.length > 0;
        $('#circularFeature').hidden = !second || !!query || category.value !== 'all' || currentPage !== 1 || sort.value !== 'newest';
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
        dialog.showModal();
        window.SiteMotion?.reveal([dialog]);
    });
    $('.circulars-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    render();
    const article = Number(new URLSearchParams(location.search).get('article'));
    if (article && documents.some(item => item.id === article)) {
        const trigger = document.createElement('button');
        trigger.type = 'button'; trigger.dataset.open = article; trigger.hidden = true;
        document.body.append(trigger); trigger.click(); trigger.remove();
    }
})();
