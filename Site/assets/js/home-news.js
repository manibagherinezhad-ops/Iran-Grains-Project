/* Three published news items per batch; previews keep the reader on the home page. */
(() => {
    'use strict';
    const dialog = document.getElementById('homeNewsDialog');
    const grid = document.querySelector('#news .news-grid');
    if (!dialog || !grid) return;
    const field = (id) => document.getElementById(id);
    const categories = window.IranGrains?.categories?.news || window.NewsCategories || {};
    // WordPress supplies this array exclusively from the published ig_news records.
    const items = [...(window.NewsItems || [])].sort((a, b) =>
        (b.publishedAt || b.date).localeCompare(a.publishedAt || a.date) || b.id - a.id);
    const template = grid.querySelector('.news-card')?.cloneNode(true);
    const progress = field('homeNewsProgress');
    let start = 0;
    function render() {
        if (!template || !items.length) return;
        const cards = [];
        for (let i = 0; i < Math.min(3, items.length); i++) {
            const item = items[(start + i) % items.length];
            const card = template.cloneNode(true);
            card.removeAttribute('data-animate');
            card.classList.add('in-view');
            card.hidden = false;
            card.dataset.newsId = item.id;
            card.querySelector('h3').textContent = item.title;
            card.querySelector('.news-body p').textContent = item.description;
            card.querySelector('.news-cat').textContent = categories[item.category] || '';
            card.querySelector('.news-date').textContent = new Intl.DateTimeFormat('fa-IR', {
                timeZone: 'Asia/Tehran', year: 'numeric', month: 'long', day: 'numeric'
            }).format(new Date(item.date + 'T12:00:00Z'));
            const image = card.querySelector('.news-img img');
            image.src = item.image;
            image.alt = item.title;
            card.querySelector('.news-link').href = item.url || ('news-article.html?id=' + item.id);
            cards.push(card);
        }
        grid.replaceChildren(...cards);
        window.SiteMotion?.reveal(cards);
    }
    render();
    // Delegation also handles links in every newly displayed batch.
    grid.addEventListener('click', (event) => {
        const link = event.target.closest('.news-link');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const card = link.closest('.news-card');
        const title = card.querySelector('h3').textContent;
        field('homeNewsTitle').textContent = title;
        field('homeNewsSummary').textContent = card.querySelector('.news-body p').textContent;
        field('homeNewsCategory').textContent = card.querySelector('.news-cat').textContent;
        field('homeNewsDate').textContent = card.querySelector('.news-date').textContent;
        field('homeNewsImage').src = card.querySelector('.news-img img').src;
        field('homeNewsImage').alt = title;
        field('homeNewsArticle').href = link.href;
        dialog.showModal();
        window.SiteMotion?.reveal([dialog]);
    });
    dialog.querySelector('.home-news-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    if (items.length <= 3 || !progress) return;
    progress.hidden = false;
    const bar = progress.querySelector('span');
    const duration = 5000;
    let elapsed = 0, last = performance.now();
    document.addEventListener('visibilitychange', () => {last = performance.now();});
    function tick(now) {
        const paused = document.hidden || dialog.open || grid.contains(document.activeElement);
        if (!paused) elapsed += now - last;
        last = now;
        if (elapsed >= duration) {
            start = (start + 3) % items.length;
            render();
            elapsed = 0;
        }
        bar.style.transform = `scaleX(${elapsed / duration})`;
        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
})();
