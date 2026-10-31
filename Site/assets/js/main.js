/* ============================================
   انجمن غلات ایران — اسکریپت اصلی
   ============================================ */
(function () {
    'use strict';

    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

    // ---------- تبدیل عدد لاتین به فارسی ----------
    const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const toFa = (num) => String(num).replace(/\d/g, (d) => FA_DIGITS[d]);

    /* ============================================
       ۱) نوار ناوبری: افکت اسکرول + لینک فعال
       ============================================ */
    const navbar = $('#navbar');
    const navLinks = $$('.nav-link');
    const sections = navLinks
        .map((link) => {
            const id = link.getAttribute('href');
            return id && id.startsWith('#') ? document.querySelector(id) : null;
        })
        .filter(Boolean);

    let ticking = false;
    function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const y = window.scrollY;

            // هدر فقط بعد از رد شدن از هیرو ظاهر و چسبان میشه
            // (تو صفحات داخلی با کلاس subpage، هدر همیشه دیده میشه)
            const heroEl = document.getElementById('home');
            const heroBottom = heroEl ? (heroEl.offsetTop + heroEl.offsetHeight) : window.innerHeight;
            navbar.classList.toggle('visible', document.body.classList.contains('subpage') || y > heroBottom - 100);

            // افکت سایه روی نوار
            navbar.classList.toggle('scrolled', y > 30);

            // دکمه‌ی بازگشت به بالا
            const backTop = $('#backToTop');
            if (backTop) backTop.classList.toggle('show', y > 500);

            // لینک فعال بر اساس بخشی که در دید است
            const mid = y + window.innerHeight * 0.35;
            let activeIndex = -1;
            for (let i = 0; i < sections.length; i++) {
                const sec = sections[i];
                if (sec && sec.offsetTop <= mid) activeIndex = i;
            }
            navLinks.forEach((link, i) => link.classList.toggle('active', i === activeIndex));

            ticking = false;
        });
    }
    window.addEventListener('scroll', onScroll, { passive: true });

    /* ============================================
       ۲) منوی موبایل
       ============================================ */
    const navToggle = $('#navToggle');
    const navMenu = $('#navMenu');

    function closeMenu() {
        navMenu.classList.remove('open');
        navToggle.classList.remove('active');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    navToggle.addEventListener('click', () => {
        const isOpen = navMenu.classList.toggle('open');
        navToggle.classList.toggle('active', isOpen);
        navToggle.setAttribute('aria-expanded', String(isOpen));
        document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    // بستن منو با کلیک روی هر لینک (به جز آیتم‌های دارای زیرمنو)
    navLinks.forEach((link) => {
        link.addEventListener('click', (e) => {
            // اگه داخل زیرمنوی موبایل هستیم، فقط زیرمنو رو باز/بسته کن
            const parentItem = link.closest('.nav-item-has-children');
            if (parentItem && window.innerWidth <= 768) {
                e.preventDefault();
                parentItem.classList.toggle('open-mobile');
                return;
            }
            // اگه روی لینک داخل dropdown کلیک شد، منو رو ببند
            if (link.closest('.dropdown')) {
                closeMenu();
            }
        });
    });

    // باز/بسته کردن زیرمنو در موبایل با کلیک روی caret
    const hasChildren = $$('.nav-item-has-children > .nav-link .caret');
    hasChildren.forEach((caret) => {
        caret.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                e.stopPropagation();
                caret.closest('.nav-item-has-children').classList.toggle('open-mobile');
            }
        });
    });

    // بستن منو با کلیک بیرون از آن
    document.addEventListener('click', (e) => {
        if (
            navMenu.classList.contains('open') &&
            !navMenu.contains(e.target) &&
            !navToggle.contains(e.target)
        ) {
            closeMenu();
        }
    });

    // بستن منو با تغییر سایز صفحه
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeMenu();
    });

    /* ============================================
       ۳) انیمیشن ورود المان‌ها (scroll reveal)
       ============================================ */
    const animatedEls = $$('[data-animate]');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries, obs) => {
                entries.forEach((entry, i) => {
                    if (entry.isIntersecting) {
                        // تاخیر کوتاه برای ایجاد حس زنجیره‌ای
                        setTimeout(() => entry.target.classList.add('in-view'), i * 80);
                        obs.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -50px 0px' }
        );
        animatedEls.forEach((el) => observer.observe(el));
    } else {
        animatedEls.forEach((el) => el.classList.add('in-view'));
    }

    /* ============================================
       ۴) شمارش آمار (count up)
       ============================================ */
    const counters = $$('[data-count]');
    const animateCounter = (el) => {
        const target = parseInt(el.dataset.count, 10);
        const duration = 1800;
        const startTime = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startTime) / duration, 1);
            // easeOutCubic برای حرکت نرم در پایان
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = Math.floor(eased * target);
            el.textContent = toFa(value);
            if (progress < 1) requestAnimationFrame(tick);
            else el.textContent = toFa(target);
        };
        requestAnimationFrame(tick);
    };

    if ('IntersectionObserver' in window) {
        const counterObserver = new IntersectionObserver(
            (entries, obs) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.5 }
        );
        counters.forEach((c) => counterObserver.observe(c));
    } else {
        counters.forEach((c) => (c.textContent = toFa(c.dataset.count)));
    }

    /* ============================================
       ۵) بازگشت به بالا
       ============================================ */
    const backToTop = $('#backToTop');
    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ============================================
       ۶) فرم تماس (شبیه‌سازی ارسال)
       ============================================ */
    const form = $('#contactForm');
    const formStatus = $('#formStatus');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            // اعتبارسنجی ساده
            const fields = form.querySelectorAll('input[required], textarea[required]');
            let valid = true;
            fields.forEach((f) => {
                if (!f.value.trim()) {
                    valid = false;
                    f.style.borderColor = '#e53e3e';
                } else {
                    f.style.borderColor = '';
                }
            });

            if (!valid) {
                formStatus.textContent = 'لطفاً همه‌ی فیلدها را تکمیل کنید.';
                formStatus.style.color = '#e53e3e';
                return;
            }

            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.textContent = 'در حال ارسال...';
            btn.disabled = true;

            // شبیه‌سازی ارسال (چون بک‌اند نداریم)
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
                form.reset();
                formStatus.textContent = '✓ پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس می‌گیریم.';
                formStatus.style.color = '';
            }, 1200);
        });
    }

    // اجرای اولیه برای حالت لود در قسمت‌های پایین
    onScroll();

    /* ============================================
       ۸) افکت کلمه‌به‌کلمه پررنگ شدن متن درباره انجمن
       (دوطرفه: اسکرول پایین = پررنگ، اسکرول بالا = کمرنگ)
       ============================================ */
    const revealText = $('#aboutRevealText');
    if (revealText) {
        const original = revealText.textContent.trim();
        const words = original.split(/\s+/);
        revealText.innerHTML = words
            .map((w) => `<span class="reveal-word">${w} </span>`)
            .join('');

        const wordEls = $$('.reveal-word', revealText);
        const updateReveal = () => {
            const rect = revealText.getBoundingClientRect();
            const vh = window.innerHeight;
            // منطقه‌ی فعال: از ۸۵٪ پایین viewport تا ۱۵٪ بالای viewport
            const start = vh * 0.85;
            const end = vh * 0.15;
            const elMid = rect.top + rect.height / 2;
            let progress = (start - elMid) / (start - end);
            progress = Math.max(0, Math.min(1, progress));
            const visibleCount = Math.floor(progress * wordEls.length);
            wordEls.forEach((el, i) => {
                el.classList.toggle('revealed', i < visibleCount);
            });
        };
        window.addEventListener('scroll', updateReveal, { passive: true });
        window.addEventListener('resize', updateReveal);
        updateReveal();
    }

    /* ============================================
       ۹) اسلایدر خبری هیرو — داده‌محور
       برای اتصال آینده به پیج اخبار، فقط همین آرایه رو
       از منبع اخبار (۵ خبر آخر) پر کنید.
       ============================================ */
    const heroNewsData = [
        { img: 'assets/img/photo-1464226184884-fa280b87c399.avif', kicker: 'بازار جهانی', title: 'افزایش تولید جهانی گندم در سال جاری بر اساس گزارش فائو', date: '۱۲ مرداد ۱۴۰۴' },
        { img: 'assets/img/photo-1574323347407-f5e1ad6d020b.avif', kicker: 'گزارش روز', title: 'چشم‌انداز مثبت بازار جهانی غلات در نیمه‌ی دوم سال', date: '۱۰ مرداد ۱۴۰۴' },
        { img: 'assets/img/photo-1625246333195-78d9c38ad449.avif', kicker: 'تولید داخلی', title: 'رکورد جدید برداشت گندم در استان‌های اصلی کشور', date: '۸ مرداد ۱۴۰۴' },
        { img: 'assets/img/photo-1500382017468-9049fed747ef.avif', kicker: 'قیمت‌ها', title: 'تثبیت قیمت نهاده‌های دامی در بازار داخلی', date: '۵ مرداد ۱۴۰۴' },
        { img: 'assets/img/photo-1437252611977-07f74518abd7.avif', kicker: 'زراعت مدرن', title: 'توسعه‌ی کشاورزی مکانیزه در اراضی گندم‌کاری کشور', date: '۲ مرداد ۱۴۰۴' },
    ];

    const heroSlider = $('#heroSlider');
    if (heroSlider && heroNewsData.length) {
        // اسلاید ۰ = معرفی انجمن (فقط بار اول)، بعدش اخبار
        const introSlide = document.createElement('div');
        introSlide.className = 'hero-slide active';
        introSlide.innerHTML =
            '<div class="slide-bg" style="background-image: url(\'assets/img/photo-1437252611977-07f74518abd7.avif\')"></div>' +
            '<div class="slide-shade intro-shade"></div>' +
            '<div class="slide-intro"><div class="container">' +
                '<span class="hero-badge">🌱 مرجع تخصصی صنعت غلات ایران</span>' +
                '<h1 class="hero-title">آینده‌ی <span class="text-gold">غلات</span> را<br>با هم می‌سازیم</h1>' +
                '<p class="hero-subtitle">انجمن غلات ایران با بیش از دو دهه تجربه، پل ارتباطی میان تولیدکنندگان، واردکنندگان و نهادهای دولتی برای توسعه‌ی پایدار صنعت غلات و تأمین امنیت غذایی کشور است.</p>' +
                '<div class="hero-actions">' +
                    '<a href="#about" class="btn btn-gold">درباره انجمن <span aria-hidden="true">←</span></a>' +
                    '<a href="#contact" class="btn btn-outline">درخواست عضویت</a>' +
                '</div>' +
            '</div></div>';
        heroSlider.appendChild(introSlide);

        // اسلایدهای خبری
        heroNewsData.forEach((n) => {
            const slide = document.createElement('div');
            slide.className = 'hero-slide';
            slide.innerHTML =
                '<div class="slide-bg" style="background-image: url(\'' + n.img + '\')"></div>' +
                '<div class="slide-shade"></div>' +
                '<div class="slide-info">' +
                    '<div class="slide-title-wrap">' +
                        '<span class="slide-title-kicker">' + n.kicker + '</span>' +
                        '<h2 class="slide-title">' + n.title + '</h2>' +
                    '</div>' +
                    '<span class="slide-date">' + n.date + '</span>' +
                '</div>';
            heroSlider.appendChild(slide);
        });

        /* چرخش هر ۵ ثانیه: معرفی (اسلاید ۰) فقط بار اول؛
           بعد از آخرین خبر، لوپ فقط بین اخبار (۱ تا آخر) می‌مونه */
        const slides = $$('.hero-slide', heroSlider);
        let heroIdx = 0;
        const SLIDE_MS = 5000;
        const ANIM_MS = 900;

        function heroNext() {
            const current = slides[heroIdx];
            let nextIdx = heroIdx + 1;
            if (nextIdx >= slides.length) nextIdx = 1; /* برگشت به اولین خبر، نه معرفی */
            const next = slides[nextIdx];

            current.classList.add('leaving');
            next.classList.add('active');

            setTimeout(() => {
                current.classList.remove('active', 'leaving');
                current.classList.add('reset');
                void current.offsetWidth; /* force reflow تا بدون انیمیشن برگرده */
                current.classList.remove('reset');
            }, ANIM_MS + 50);

            heroIdx = nextIdx;
        }

        if (slides.length > 1) setInterval(heroNext, SLIDE_MS);
    }

    /* ============================================
       ۱۰) تاریخ روز در باکس نرخ ارز
       ============================================ */
    const exDateEl = $('#exchangeDate');
    if (exDateEl) {
        const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
            timeZone: 'Asia/Tehran', year: 'numeric', month: '2-digit', day: '2-digit'
        });
        const updateExchangeDate = () => { exDateEl.textContent = 'مورخ ' + formatter.format(new Date()); };
        updateExchangeDate();
        setInterval(updateExchangeDate, 60 * 1000);
    }

    /* ============================================
       ۱۱) آکاردئون اهداف — باز/بسته با کلیک (برای لمسی)
       (hover هم از طریق CSS فعاله)
       ============================================ */
    const accItems = $$('.acc-item');
    accItems.forEach((item) => {
        item.addEventListener('click', () => {
            const wasOpen = item.classList.contains('open');
            accItems.forEach((i) => i.classList.remove('open'));
            if (!wasOpen) item.classList.add('open');
        });
    });

    /* ============================================
       ۱۲) فیلتر و صفحه‌بندی جلسات (meetings.html)
       ============================================ */
    const mFilters = $$('.mfilter');
    if (mFilters.length) {
        const mCards = $$('.meet-card');
        const pagination = $('#meetPagination');
        const pageNumbers = $('#meetPageNumbers');
        const pageSize = 5;
        let currentFilter = 'all';
        let currentPage = 1;

        function renderMeetings() {
            const filtered = mCards.filter((card) => currentFilter === 'all' || card.dataset.status === currentFilter);
            const pageCount = Math.ceil(filtered.length / pageSize);
            currentPage = Math.min(currentPage, Math.max(pageCount, 1));
            mCards.forEach((card) => {
                const index = filtered.indexOf(card);
                card.classList.toggle('hidden', index < 0 || Math.floor(index / pageSize) + 1 !== currentPage);
            });

            pageNumbers.replaceChildren();
            for (let page = 1; page <= pageCount; page++) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = toFa(page);
                button.setAttribute('aria-label', `صفحهٔ ${toFa(page)}`);
                if (page === currentPage) button.setAttribute('aria-current', 'page');
                button.addEventListener('click', () => changeMeetingPage(page));
                pageNumbers.appendChild(button);
            }
            pagination.querySelector('[data-page-direction="prev"]').disabled = currentPage === 1;
            pagination.querySelector('[data-page-direction="next"]').disabled = currentPage === pageCount;
        }

        function changeMeetingPage(page) {
            currentPage = page;
            renderMeetings();
            window.SiteMotion?.reveal(mCards.filter((card) => !card.classList.contains('hidden')));
            $('#meetGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        mFilters.forEach((btn) => {
            btn.addEventListener('click', () => {
                mFilters.forEach((b) => b.classList.remove('on'));
                btn.classList.add('on');
                currentFilter = btn.dataset.filter;
                currentPage = 1;
                renderMeetings();
                window.SiteMotion?.reveal(mCards.filter((card) => !card.classList.contains('hidden')));
            });
        });
        pagination.querySelector('[data-page-direction="prev"]').addEventListener('click', () => changeMeetingPage(currentPage - 1));
        pagination.querySelector('[data-page-direction="next"]').addEventListener('click', () => changeMeetingPage(currentPage + 1));
        renderMeetings();
    }

    /* ============================================
       ۱۳) کاروسیل اعضای هیئت مدیره (board.html)
       وسط بزرگ — ۳ ثانیه حرکت خودکار — درگ + دکمه
       ============================================ */
    const bTrack = $('#bTrack');
    if (bTrack) {
        const cards = $$('.bmember', bTrack);
        const n = cards.length;
        let bActive = 0;
        let spacing = window.innerWidth < 540 ? 235 : 330;

        /* نقاط ناوبری */
        const bDots = $('#bDots');
        if (bDots) {
            cards.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'bdot';
                dot.setAttribute('aria-label', 'عضو ' + (i + 1));
                dot.addEventListener('click', () => { bActive = i; layout(); syncDots(); restart(); });
                bDots.appendChild(dot);
            });
        }
        const dots = $$('.bdot', bDots);

        /* چیدمان: عضو فعال وسط (بزرگ)، همسایه‌ها کوچیک‌تر، بقیه مخفی */
        function layout(dragX) {
            dragX = dragX || 0;
            cards.forEach((card, i) => {
                let d = i - bActive;
                if (d > n / 2) d -= n;
                if (d < -n / 2) d += n;
                const abs = Math.abs(d);
                const x = -d * spacing + dragX; /* عکس بعدی سمت چپ → حرکت چپ به راست */
                const s = abs === 0 ? 1 : (abs === 1 ? 0.72 : 0.55);
                card.style.transform = 'translateX(calc(-50% + ' + x + 'px)) scale(' + s + ')';
                card.style.opacity = abs <= 1 ? '1' : '0';
                card.style.zIndex = String(10 - abs);
                card.classList.toggle('is-active', d === 0);
            });
        }
        function syncDots() { dots.forEach((dt, i) => {
            dt.classList.toggle('on', i === bActive);
            if (i === bActive) dt.setAttribute('aria-current', 'page');
            else dt.removeAttribute('aria-current');
        }); }
        function next() { bActive = (bActive + 1) % n; layout(); syncDots(); }
        function prev() { bActive = (bActive - 1 + n) % n; layout(); syncDots(); }

        /* دکمه‌ها */
        const nextBtn = $('#bNext'), prevBtn = $('#bPrev');
        if (nextBtn) nextBtn.addEventListener('click', () => { next(); restart(); });
        if (prevBtn) prevBtn.addEventListener('click', () => { prev(); restart(); });

        /* درگ با نگه‌داشتن کلیک — جهت هماهنگ با فلش‌ها
           کلیک ساده روی کارت → اسکرول نرم به پروفایل همون عضو */
        const viewport = $('#bViewport');
        const profileEls = $$('.profile');
        let dragging = false, startX = 0, curX = 0, downCard = null;

        viewport.addEventListener('pointerdown', (e) => {
            dragging = true;
            startX = curX = e.clientX;
            downCard = e.target.closest('.bmember');
            viewport.classList.add('dragging');
            stop();
        });
        window.addEventListener('pointermove', (e) => {
            if (!dragging) return;
            curX = e.clientX;
            layout(curX - startX);
        });
        window.addEventListener('pointerup', () => {
            if (!dragging) return;
            dragging = false;
            viewport.classList.remove('dragging');
            const dx = curX - startX;

            /* کلیک (نه درگ) → رفتن به پروفایل همون عضو */
            if (Math.abs(dx) < 8 && downCard) {
                const idx = cards.indexOf(downCard);
                const target = profileEls[idx];
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.classList.remove('flash');
                    void target.offsetWidth;
                    target.classList.add('flash');
                    setTimeout(() => target.classList.remove('flash'), 1600);
                }
                layout();
                restart();
                return;
            }

            if (dx > 60) prev();
            else if (dx < -60) next();
            else layout();
            restart();
        });

        /* پخش خودکار هر ۳ ثانیه — با هاور یا درگ متوقف میشه */
        let timer = null;
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function restart() { stop(); timer = setInterval(next, 3000); }
        viewport.addEventListener('mouseenter', stop);
        viewport.addEventListener('mouseleave', () => { if (!dragging) restart(); });

        window.addEventListener('resize', () => {
            spacing = window.innerWidth < 540 ? 235 : 330;
            if (!dragging) layout();
        });

        layout();
        syncDots();
        restart();
    }
})();
