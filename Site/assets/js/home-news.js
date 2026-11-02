/* Open the landing-page summary without navigating to the news archive. */
(() => {
    'use strict';
    const dialog = document.getElementById('homeNewsDialog');
    if (!dialog) return;
    const field = (id) => document.getElementById(id);
    document.querySelectorAll('#news .news-card .news-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
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
    });
    dialog.querySelector('.home-news-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
})();
