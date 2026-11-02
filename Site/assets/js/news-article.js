(() => {
    'use strict';
    const $ = (id) => document.getElementById(id);
    const id = Number(new URLSearchParams(location.search).get('id'));
    const item = window.NewsItems.find((news) => news.id === id);
    if (!item) {
        document.title = 'خبر یافت نشد | انجمن غلات ایران';
        $('newsArticleMain').innerHTML = '<section class="container article-not-found"><h1>خبر موردنظر یافت نشد</h1><a class="btn btn-green-outline" href="news.html">بازگشت به اخبار</a></section>';
        return;
    }
    const categories = {association:'اخبار انجمن',domestic:'تولید داخلی',world:'بازار جهانی',market:'قیمت‌ها و بازار'};
    document.title = item.title + ' | انجمن غلات ایران';
    $('articleTitle').textContent = item.title;
    $('articleCrumb').textContent = item.title;
    $('articleCategory').textContent = categories[item.category];
    $('articleHeroImage').src = item.image;
    $('articleHeroImage').alt = item.title;
    $('articleDate').dateTime = item.date;
    $('articleDate').textContent = new Intl.DateTimeFormat('fa-IR',{timeZone:'Asia/Tehran',year:'numeric',month:'long',day:'numeric'}).format(new Date(item.date+'T12:00:00Z'));
    (item.content?.length ? item.content : [item.description]).forEach((text) => {
        const paragraph=document.createElement('p');
        paragraph.textContent=text;
        $('articleBody').append(paragraph);
    });
})();
