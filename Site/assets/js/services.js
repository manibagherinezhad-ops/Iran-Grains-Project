/* Read the rendered text so edits from the management panel reach the modal. */
(() => {
    'use strict';
    const dialog = document.getElementById('servicesDialog');
    if (!dialog) return;
    const panels = Array.from(dialog.querySelectorAll('.service-detail'));
    let trigger = null;
    document.querySelectorAll('#services .service-link').forEach((link, index) => {
        const panel = panels[index];
        if (!panel) return;
        link.href = '#servicesDialog';
        link.setAttribute('role', 'button');
        link.setAttribute('aria-haspopup', 'dialog');
        link.setAttribute('aria-controls', 'servicesDialog');
        const open = (event) => {
            event.preventDefault();
            trigger = link;
            panels.forEach((item) => {item.hidden = item !== panel;});
            dialog.setAttribute('aria-labelledby', panel.querySelector('h2').id);
            dialog.showModal();
            dialog.scrollTop = 0;
            window.SiteMotion?.reveal([dialog]);
        };
        link.addEventListener('click', open);
        link.addEventListener('keydown', (event) => {if (event.key === ' ') open(event);});
    });
    dialog.querySelector('.services-dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {trigger?.focus({preventScroll: true});});
})();
