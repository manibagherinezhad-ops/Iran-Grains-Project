<?php
defined('ABSPATH') || exit;
function ig_copy_route_labels(): array {
    return ['index'=>'خانه','about'=>'آشنایی با انجمن','membership'=>'شرایط عضویت',
        'iran-green'=>'ایران گرین','contact'=>'تماس با ما','statute'=>'اساسنامه',
        'news'=>'صفحهٔ اخبار','circulars'=>'صفحهٔ بخشنامه‌ها','approved-prices'=>'صفحهٔ قیمت‌ها',
        'members'=>'صفحهٔ شرکت‌ها','board'=>'صفحهٔ هیأت‌مدیره','meetings'=>'صفحهٔ جلسات'];
}
function ig_copy_nodes(DOMDocument $doc): array {
    $result=[];
    foreach (ig_nodes($doc, '//body//text()[normalize-space(.) != ""]') as $node) {
        $parent=$node->parentNode; $eligible=false; $excluded=false;
        for ($a=$parent; $a instanceof DOMElement; $a=$a->parentNode) {
            if (in_array($a->tagName,['h1','h2','h3','p','a','button'],true)) $eligible=true;
            if (in_array($a->tagName,['header','footer','nav','dialog','script','style','select','template'],true)
                || in_array($a->getAttribute('id'),['news','circularGrid','circularFeature','membersGrid','meetGrid','bTrack'],true)
                || preg_match('/\b(profile|contact-list|contact-detail-list)\b/',$a->getAttribute('class'))) $excluded=true;
        }
        if ($eligible && !$excluded && mb_strlen(trim($node->textContent))>=4) $result[$node->getNodePath()]=$node;
    }
    return $result;
}
function ig_copy_source(string $route): ?DOMDocument {
    if (!isset(ig_copy_route_labels()[$route])) return null;
    $file=get_template_directory().'/prototype/'.$route.'.html';
    if (!is_file($file)) return null;
    $doc=new DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($file),LIBXML_NONET);$doc->encoding='UTF-8';
    libxml_clear_errors();libxml_use_internal_errors($old);return $doc;
}
function ig_apply_page_copy(DOMDocument $doc,string $route): void {
    $values=get_option('ig_page_copy',[])[$route]??[];
    foreach (ig_copy_nodes($doc) as $path=>$node) {
        $key=hash('sha256',$path);
        if (isset($values[$key])) $node->textContent=$values[$key];
    }
}
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','متن صفحات','متن صفحات','manage_options','ig-page-copy','ig_copy_admin'));
function ig_copy_admin(): void {
    if (!current_user_can('manage_options')) wp_die('دسترسی مجاز نیست.','',['response'=>403]);
    $route=sanitize_key($_POST['route']??$_GET['route']??'about');
    if (!isset(ig_copy_route_labels()[$route])) $route='about';
    $doc=ig_copy_source($route);if (!$doc) wp_die('فایل قالب موجود نیست.');
    $nodes=ig_copy_nodes($doc);$all=get_option('ig_page_copy',[]);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('ig_copy_'.$route);$clean=[];
        $input=isset($_POST['copy'])&&is_array($_POST['copy'])?wp_unslash($_POST['copy']):[];
        foreach ($nodes as $path=>$node) {
            $key=hash('sha256',$path);
            if (isset($input[$key])&&is_string($input[$key])) $clean[$key]=mb_substr(sanitize_textarea_field($input[$key]),0,10000);
        }
        $all[$route]=$clean;update_option('ig_page_copy',$all,false);ig_audit('page_copy_updated',$route);
        echo '<div class="notice notice-success"><p>متن صفحه ذخیره شد.</p></div>';
    }
    echo '<div class="wrap ig-admin"><h1>متن صفحات</h1><p>متن بخش‌های ثابت را بدون تغییر چیدمان و رنگ‌بندی ویرایش کنید. خبرها و اطلاعات شرکت‌ها از بخش اختصاصی خودشان مدیریت می‌شوند.</p><form method="get"><input type="hidden" name="page" value="ig-page-copy"><select name="route">';
    foreach (ig_copy_route_labels() as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($key,$route,false).'>'.esc_html($label).'</option>';
    echo '</select> <button class="button">نمایش صفحه</button></form><form method="post">';wp_nonce_field('ig_copy_'.$route);
    echo '<input type="hidden" name="route" value="'.esc_attr($route).'"><table class="form-table">';
    foreach ($nodes as $path=>$node) {
        $key=hash('sha256',$path);$original=trim($node->textContent);
        echo '<tr><th><label for="ig-copy-'.$key.'">'.esc_html(mb_substr($original,0,65)).'</label></th><td><textarea class="large-text" rows="3" id="ig-copy-'.$key.'" name="copy['.$key.']">'.esc_textarea($all[$route][$key]??$node->textContent).'</textarea></td></tr>';
    }
    echo '</table>';submit_button('ذخیرهٔ متن صفحه');echo '</form></div>';
}
