/* Meeting summaries use the existing card content. */
(() => {
    'use strict';
    const dialog = document.getElementById('meetingDialog');
    const $ = (id) => document.getElementById(id);
    let closing = false;
    function open(card) {
        const image = card.querySelector('.meet-img img');
        const status = card.querySelector('.meet-tag');
        $('meetingDialogTitle').textContent = card.querySelector('h3').textContent;
        $('meetingDialogSummary').textContent = card.querySelector('.meet-summary')?.content.textContent.trim() || card.querySelector('.meet-body p').textContent;
        $('meetingDialogDate').textContent = Array.from(card.querySelector('.meet-date').children, (part) => part.textContent.trim()).join(' ');
        const meta = card.querySelectorAll('.meet-meta span');
        $('meetingDialogLocation').textContent = meta[0].textContent.replace('📍', '').trim();
        $('meetingDialogTime').textContent = meta[1].textContent.replace('🕑', '').trim();
        $('meetingDialogStatus').textContent = status.textContent;
        $('meetingDialogStatus').className = status.className;
        $('meetingDialogImage').src = image.src;
        $('meetingDialogImage').alt = card.querySelector('h3').textContent;
        card.focus({preventScroll: true});
        dialog.showModal();
        window.SiteMotion?.reveal([dialog]);
    }
    document.querySelectorAll('.meet-card').forEach((card) => {
        card.tabIndex = 0;
        card.setAttribute('role', 'button');
        card.setAttribute('aria-haspopup', 'dialog');
        card.setAttribute('aria-controls', 'meetingDialog');
        card.setAttribute('aria-label', 'خلاصهٔ ' + card.querySelector('h3').textContent.trim());
        card.addEventListener('click', () => open(card));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {event.preventDefault(); open(card);}
        });
    });
    async function close() {
        if (closing || !dialog.open) return;
        closing = true;
        dialog.getAnimations().forEach((animation) => animation.cancel());
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
            await dialog.animate([{opacity: 1, translate: '0 0'}, {opacity: 0, translate: '0 10px'}], {duration: 160, easing: 'ease-in', fill: 'forwards'}).finished;
        }
        dialog.close();
        dialog.getAnimations().forEach((animation) => animation.cancel());
        closing = false;
    }
    dialog.querySelector('.meeting-dialog-close').addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {event.preventDefault(); close();});
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) close();
    });
})();
