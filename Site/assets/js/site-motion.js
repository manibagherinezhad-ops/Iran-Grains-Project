/* Shared, interruptible reveal motion for page content and result changes. */
(() => {
    'use strict';
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const running = new WeakMap();
    function reveal(elements) {
        Array.from(elements).forEach((element, index) => {
            running.get(element)?.cancel();
            if (reduced.matches || !element.animate) return;
            running.set(element, element.animate([
                {opacity: 0, translate: '0 14px'},
                {opacity: 1, translate: '0 0'}
            ], {duration: 400, delay: Math.min(index * 35, 210), easing: 'cubic-bezier(.2,.7,.3,1)', fill: 'backwards'}));
        });
    }
    window.SiteMotion = {reveal};
    if (reduced.matches || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            reveal([entry.target]);
            observer.unobserve(entry.target);
        });
    }, {threshold: .08});
    document.querySelectorAll('.site-hero-content, .circulars-hero-content, .geometry-section > .container').forEach((element) => {
        if (element.matches('[data-animate]') || element.querySelector('[data-animate]')) return;
        observer.observe(element);
    });
})();
