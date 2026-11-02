/* Backend-only bindings. The static preview remains usable on its own. */
(() => {
    const form = document.getElementById('contactForm');
    if (form && window.IranGrains?.contact) {
        const trap = document.createElement('input');
        trap.name = 'website'; trap.tabIndex = -1; trap.autocomplete = 'off'; trap.hidden = true; form.append(trap);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const status = document.getElementById('formStatus');
            if (!form.reportValidity()) return;
            const button = form.querySelector('[type="submit"]'), label = button.textContent;
            button.disabled = true; button.textContent = 'در حال ارسال…'; status.textContent = '';
            try {
                const values = Object.fromEntries(['name','phone','email','subject','message'].map(id => [id, form.querySelector('#'+id).value]));
                values.website = trap.value; values._ig_nonce = window.IranGrains.contact.nonce;
                const response = await fetch(window.IranGrains.contact.url, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':window.IranGrains.contact.restNonce}, credentials:'same-origin', body:JSON.stringify(values)});
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'ارسال پیام انجام نشد.');
                status.textContent = result.message; form.reset();
            } catch (error) { status.textContent = error.message || 'ارتباط برقرار نشد. دوباره تلاش کنید.'; }
            finally { button.disabled = false; button.textContent = label; }
        });
    }
})();
