(() => {
    document.getElementById('ig-select-pdf')?.addEventListener('click', () => {
        const picker = wp.media({title: 'انتخاب فایل PDF', library: {type: 'application/pdf'}, multiple: false});
        picker.on('select', () => {
            const file = picker.state().get('selection').first().toJSON();
            document.getElementById('ig_attachment_id').value = file.id;
            document.getElementById('ig-pdf-label').textContent = file.title;
        });
        picker.open();
    });
    document.getElementById('ig-clear-pdf')?.addEventListener('click', () => {
        document.getElementById('ig_attachment_id').value = '';
        document.getElementById('ig-pdf-label').textContent = 'پیوستی انتخاب نشده';
    });
})();
