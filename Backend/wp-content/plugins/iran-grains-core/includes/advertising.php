<?php
defined('ABSPATH') || exit;
function ig_ads():array {return array_values(array_filter(get_option('ig_advertising',[]),fn($a)=>!empty($a['enabled'])&&(!empty($a['text'])||!empty($a['image']))));}
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','تبلیغات سایت','تبلیغات سایت','manage_options','ig-advertising','ig_ads_admin'));
function ig_ads_admin():void {
    if(!current_user_can('manage_options'))wp_die('دسترسی مجاز نیست.');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_admin_referer('ig_advertising');$clean=[];
        foreach(array_slice((array)wp_unslash($_POST['ads']??[]),0,20)as$a){if(!is_array($a))continue;$image=absint($a['image']??0);if($image&&(!wp_attachment_is_image($image)||!current_user_can('edit_post',$image)))$image=0;
            $text=mb_substr(sanitize_textarea_field($a['text']??''),0,1000);if($text===''&&!$image)continue;
            $clean[]=['text'=>$text,'image'=>$image,'url'=>esc_url_raw($a['url']??'', ['https','http']),'enabled'=>isset($a['enabled'])];}
        update_option('ig_advertising',$clean,false);ig_audit('advertising_updated','advertising');echo '<div class="notice notice-success"><p>تبلیغات ذخیره شد.</p></div>';
    }
    wp_enqueue_media();wp_enqueue_script('ig-ad-admin',plugins_url('../assets/advertising-admin.js',__FILE__),[],filemtime(IG_CORE_DIR.'/assets/advertising-admin.js'),true);
    $ads=get_option('ig_advertising',[]);$ads[]=['text'=>'','image'=>0,'url'=>'','enabled'=>true];
    echo '<div class="wrap"><h1>تبلیغات سایت</h1><p>ستون سمت چپ تا نیمهٔ مسیر پیمایش صفحه نمایش داده می‌شود. هر تبلیغ شامل متن و تصویر است. لینک‌ها sponsored/nofollow هستند. ردیف خالی ذخیره نمی‌شود؛ برای افزودن تبلیغ تازه پس از ذخیره، ردیف خالی جدید می‌بینید.</p><form method="post">';wp_nonce_field('ig_advertising');
    foreach($ads as$i=>$ad){echo '<fieldset class="ig-ad-editor" style="padding:20px;margin:15px 0;border:1px solid #ccc"><legend>تبلیغ '.($i+1).'</legend><label><input type="checkbox" name="ads['.$i.'][enabled]" '.checked(!empty($ad['enabled']),true,false).'> نمایش فعال</label><p><label>متن تبلیغ<br><textarea class="large-text" rows="3" name="ads['.$i.'][text]">'.esc_textarea($ad['text']).'</textarea></label></p><p><label>لینک مقصد<br><input class="regular-text" type="url" dir="ltr" name="ads['.$i.'][url]" value="'.esc_attr($ad['url']).'"></label></p><input class="ig-ad-image" type="hidden" name="ads['.$i.'][image]" value="'.absint($ad['image']).'"><div class="ig-ad-preview">'.($ad['image']?wp_get_attachment_image($ad['image'],'thumbnail'):'').'</div><button type="button" class="button ig-ad-pick">انتخاب تصویر</button> <button type="button" class="button ig-ad-remove">حذف تصویر</button></fieldset>';}
    submit_button('ذخیرهٔ تبلیغات');echo '</form></div>';
}
add_filter('body_class',function($classes){if(ig_ads())$classes[]='ig-has-ads';return $classes;});
add_action('wp_footer',function(){if(is_404()||!ig_ads())return;echo '<span class="ig-ad-rail" role="complementary" aria-label="تبلیغات" data-nosnippet><span class="ig-ad-label">تبلیغات</span>';
    foreach(ig_ads()as$ad){echo '<span class="ig-ad-card">';if($ad['url'])echo '<a href="'.esc_url($ad['url']).'" rel="sponsored nofollow noopener noreferrer" target="_blank">';
        if($ad['image'])echo '<span class="ig-ad-picture">'.wp_get_attachment_image($ad['image'],'medium',false,['loading'=>'lazy']).'</span>';
        echo '<span class="ig-ad-text">'.esc_html($ad['text']).'</span>';if($ad['url'])echo '</a>';echo '</span>';}
    echo '</span>';
});
