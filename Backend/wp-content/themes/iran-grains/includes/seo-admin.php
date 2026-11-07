<?php
defined('ABSPATH') || exit;
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','سئو و نمایش در گوگل','سئو و نمایش در گوگل','manage_options','ig-seo','ig_seo_admin'));
function ig_seo_admin(): void {
    if(!current_user_can('manage_options'))wp_die('دسترسی مجاز نیست.','',['response'=>403]);
    $values=get_option('ig_seo',[]);$routes=array_diff_key(ig_copy_route_labels(),array_flip(['company','news-article']));
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_admin_referer('ig_seo_settings');$input=wp_unslash((array)($_POST['seo']??[]));$clean=['pages'=>[]];
        $token=$input['google_verification']??'';$clean['google_verification']=is_scalar($token)?preg_replace('/[^A-Za-z0-9_-]/','',substr((string)$token,0,200)):'';
        foreach($routes as$route=>$label)foreach(['title','description']as$key){$v=$input['pages'][$route][$key]??'';$clean['pages'][$route][$key]=is_scalar($v)?mb_substr(sanitize_text_field((string)$v),0,$key==='title'?140:300):'';}
        update_option('ig_seo',$clean,false);$values=$clean;ig_audit('settings_updated','settings');echo '<div class="notice notice-success"><p>تنظیمات سئو ذخیره شد.</p></div>';
    }
    echo '<div class="wrap ig-admin"><h1>سئو و نمایش در گوگل</h1><p>عنوان و توضیح هر صفحه خودکار ساخته می‌شود. فقط برای تغییر نتیجهٔ پیش‌فرض، این فیلدها را پر کنید. گوگل ممکن است عنوان یا توضیح دیگری از متن صفحه انتخاب کند.</p>';
    echo '<div class="notice '.(ig_seo_indexable()?'notice-success':'notice-warning').' inline"><p>'.(ig_seo_indexable()?'ایندکس نسخهٔ اصلی فعال است.':'ایندکس غیرفعال است: محیط محلی/آزمایشی، پیش‌نمایش یا تنظیم «نمایش به موتورهای جستجو».').'</p></div>';
    echo '<p><a class="button" href="'.esc_url(home_url('/wp-sitemap.xml')).'" target="_blank" rel="noopener">مشاهدهٔ نقشهٔ سایت</a> <a class="button" href="'.esc_url(admin_url('options-reading.php')).'">تنظیم نمایش به موتورهای جستجو</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=ig-page-copy&view=content&content_type=ig_company')).'">تکمیل اطلاعات و سئوی شرکت‌ها</a></p>';
    echo '<p>برای هر شرکت، خبر یا سند، عنوان و توضیح اختصاصی سئو در «اطلاعات تکمیلی» همان محتوا و در «مرکز مدیریت متن‌ها ← محتوای بخش‌ها» قرار دارد. نام رسمی، فعالیت دقیق، معرفی اختصاصی، لوگو و اطلاعات تماس معتبر را کامل کنید؛ اطلاعات ساختگی و تکرار بی‌رویهٔ نام شرکت به رتبه کمک نمی‌کند.</p><form method="post">';wp_nonce_field('ig_seo_settings');
    echo '<h2>اتصال به Google Search Console</h2><p>بعد از ساخت مالکیت از نوع URL prefix، فقط مقدار <code>content</code> کد تأیید HTML را وارد کنید.</p><input class="large-text" dir="ltr" aria-label="کد تأیید گوگل" name="seo[google_verification]" value="'.esc_attr($values['google_verification']??'').'">';
    foreach($routes as$route=>$label){$default=ig_seo_metadata($route);echo '<details style="margin:16px 0;padding:16px;background:#fff;border:1px solid #dbe3da;border-radius:12px"><summary><strong>'.esc_html($label).'</strong></summary><p><label>عنوان در گوگل<br><input class="large-text" name="seo[pages]['.esc_attr($route).'][title]" value="'.esc_attr($values['pages'][$route]['title']??'').'" placeholder="'.esc_attr($default['title']).'"></label></p><p><label>توضیح نتیجهٔ جستجو<br><textarea class="large-text" rows="3" name="seo[pages]['.esc_attr($route).'][description]" placeholder="'.esc_attr($default['description']).'">'.esc_textarea($values['pages'][$route]['description']??'').'</textarea></label></p></details>';}
    submit_button('ذخیرهٔ تنظیمات سئو');echo '</form>';ig_seo_quality_panel();echo '</div>';
}

function ig_seo_company_gaps(): array {
    $rows=[];
    foreach(ig_public_posts('ig_company')as$post){
        $missing=[];$body=ig_copy_normalize(wp_strip_all_tags($post->post_content));$activity=ig_copy_normalize((string)get_post_meta($post->ID,'_ig_activity',true));
        if($body===''||$body===$activity)$missing[]='معرفی اختصاصی شرکت';
        if(!has_post_thumbnail($post)&&!get_post_meta($post->ID,'_ig_seed_image',true))$missing[]='لوگوی شرکت';
        $contact=false;foreach(['phone','email','website','address']as$key)if(get_post_meta($post->ID,'_ig_'.$key,true)!=='')$contact=true;
        if(!$contact)$missing[]='راه ارتباطی معتبر';
        if($missing)$rows[]=['id'=>$post->ID,'name'=>$post->post_title,'missing'=>$missing];
    }return$rows;
}
function ig_seo_quality_panel(): void {
    $rows=ig_seo_company_gaps();
    echo '<h2>تکمیل اطلاعات واقعی شرکت‌ها</h2><p>'.esc_html(ig_fa(count($rows))).' شرکت برای معرفی اختصاصی، لوگو یا راه ارتباطی به تکمیل اطلاعات نیاز دارند. این فهرست، کمبود داده را نشان می‌دهد و امتیاز یا پیش‌بینی رتبهٔ گوگل نیست.</p>';
    if(!$rows)return;
    echo '<table class="widefat striped"><thead><tr><th>شرکت</th><th>اطلاعات قابل تکمیل</th><th>ویرایش</th></tr></thead><tbody>';
    foreach(array_slice($rows,0,12)as$row){$url=add_query_arg(['page'=>'ig-page-copy','view'=>'content','content_type'=>'ig_company','content_id'=>$row['id']],admin_url('admin.php'));echo '<tr><td>'.esc_html($row['name']).'</td><td>'.esc_html(implode('، ',$row['missing'])).'</td><td><a href="'.esc_url($url).'">تکمیل اطلاعات</a></td></tr>';}
    echo '</tbody></table><p><a href="'.esc_url(admin_url('admin.php?page=ig-page-copy&view=content&content_type=ig_company')).'">مشاهده و ویرایش همهٔ شرکت‌ها</a></p>';
}
