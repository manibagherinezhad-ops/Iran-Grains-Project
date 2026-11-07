<?php
defined('ABSPATH') || exit;
function ig_ad_samples():array {
    $file=get_template_directory().'/prototype/assets/data/advertising-samples.json';
    return is_readable($file)?json_decode(file_get_contents($file),true):[];
}
function ig_ad_sample_image(array $ad):string {
    $allowed=array_column(ig_ad_samples(),'sample_image');$file=$ad['sample_image']??'';
    return in_array($file,$allowed,true)?get_template_directory_uri().'/prototype/assets/img/'.$file:'';
}
function ig_ad_image(array $ad,string $size):string {
    if(!empty($ad['image']))return wp_get_attachment_image($ad['image'],$size,false,['loading'=>'lazy']);
    $url=ig_ad_sample_image($ad);
    return $url?'<img src="'.esc_url($url).'" alt="تصویر تبلیغ نمونه" loading="lazy"'.($size==='thumbnail'?' width="150"':'').'>':'';
}
// The owner requested visible sample advertisements. Seed once; later removals stay removed.
add_action('init',function(){
    if(get_option('ig_ad_samples_installed'))return;
    if(!ig_ads())update_option('ig_advertising',array_merge(get_option('ig_advertising',[]),ig_ad_samples()),false);
    update_option('ig_ad_samples_installed',true,false);
},22);
// Add only the two newly requested examples once; retain existing edits.
add_action('init',function(){
    if(get_option('ig_ad_four_samples_installed'))return;
    $ads=get_option('ig_advertising',[]);$samples=ig_ad_samples();
    $candidates=ig_ads()?array_slice($samples,2):$samples;
    foreach($candidates as$sample)if(!in_array($sample['text'],array_column($ads,'text'),true))$ads[]=$sample;
    update_option('ig_advertising',$ads,false);update_option('ig_ad_four_samples_installed',true,false);
},23);
function ig_ad_text(string $text):string {
    $lines=array_values(array_filter(array_map('trim',preg_split('/\R/u',$text)),fn($line)=>$line!==''));
    if(!$lines)return '';
    $title=array_shift($lines);$caption=count($lines)>1?array_pop($lines):'';
    return '<span class="ig-ad-title">'.esc_html($title).'</span>'.($lines?'<span class="ig-ad-description">'.esc_html(implode("\n",$lines)).'</span>':'').($caption!==''?'<span class="ig-ad-caption">'.esc_html($caption).'</span>':'');
}
function ig_ads():array {return array_values(array_filter(get_option('ig_advertising',[]),fn($a)=>!empty($a['enabled'])&&(!empty($a['text'])||!empty($a['image'])||ig_ad_sample_image($a))));}
function ig_ads_visible(?string $route=null):bool {
    if($route===null)$route=function_exists('ig_resolve_view')?ig_resolve_view()[0]:(string)get_query_var('ig_view');
    return !in_array($route,['about','board','contact','404'],true)&&!is_404()&&(bool)ig_ads();
}
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','تبلیغات سایت','تبلیغات سایت','manage_options','ig-advertising','ig_ads_admin'));
function ig_ads_admin():void {
    if(!current_user_can('manage_options'))wp_die('دسترسی مجاز نیست.');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_admin_referer('ig_advertising');$clean=[];
        foreach(array_slice((array)wp_unslash($_POST['ads']??[]),0,20)as$a){if(!is_array($a))continue;$image=absint($a['image']??0);if($image&&(!wp_attachment_is_image($image)||!current_user_can('edit_post',$image)))$image=0;
            $sample=!$image&&in_array($a['sample_image']??'',array_column(ig_ad_samples(),'sample_image'),true)?$a['sample_image']:'';
            $text=mb_substr(sanitize_textarea_field($a['text']??''),0,1000);if($text===''&&!$image&&!$sample)continue;
            $clean[]=['text'=>$text,'image'=>$image,'sample_image'=>$sample,'url'=>esc_url_raw($a['url']??'', ['https','http']),'enabled'=>isset($a['enabled'])];}
        update_option('ig_advertising',$clean,false);ig_audit('advertising_updated','advertising');echo '<div class="notice notice-success"><p>تبلیغات ذخیره شد.</p></div>';
    }
    wp_enqueue_media();wp_enqueue_script('ig-ad-admin',plugins_url('../assets/advertising-admin.js',__FILE__),[],filemtime(IG_CORE_DIR.'/assets/advertising-admin.js'),true);
    $ads=get_option('ig_advertising',[]);$ads[]=['text'=>'','image'=>0,'url'=>'','enabled'=>true];
    echo '<div class="wrap"><h1>تبلیغات سایت</h1><p>ستون سمت چپ تا نیمهٔ مسیر پیمایش صفحه نمایش داده می‌شود. هر تبلیغ شامل متن و تصویر است. لینک‌ها sponsored/nofollow هستند. در متن، خط اول عنوان است، خط‌های میانی توضیحات و خط آخر (در متن سه‌خطی یا بیشتر) یادداشت پایانی است. ردیف خالی ذخیره نمی‌شود؛ برای افزودن تبلیغ تازه پس از ذخیره، ردیف خالی جدید می‌بینید.</p><form method="post">';wp_nonce_field('ig_advertising');
    foreach($ads as$i=>$ad){echo '<fieldset class="ig-ad-editor" style="padding:20px;margin:15px 0;border:1px solid #ccc"><legend>تبلیغ '.($i+1).'</legend><label><input type="checkbox" name="ads['.$i.'][enabled]" '.checked(!empty($ad['enabled']),true,false).'> نمایش فعال</label><p><label>متن تبلیغ<br><textarea class="large-text" rows="3" name="ads['.$i.'][text]">'.esc_textarea($ad['text']).'</textarea></label></p><p><label>لینک مقصد<br><input class="regular-text" type="url" dir="ltr" name="ads['.$i.'][url]" value="'.esc_attr($ad['url']).'"></label></p><input class="ig-ad-image" type="hidden" name="ads['.$i.'][image]" value="'.absint($ad['image']).'"><input class="ig-ad-sample-image" type="hidden" name="ads['.$i.'][sample_image]" value="'.esc_attr($ad['sample_image']??'').'"><div class="ig-ad-preview">'.ig_ad_image($ad,'thumbnail').'</div><button type="button" class="button ig-ad-pick">انتخاب تصویر</button> <button type="button" class="button ig-ad-remove">حذف تصویر</button></fieldset>';}
    submit_button('ذخیرهٔ تبلیغات');echo '</form></div>';
}
add_filter('body_class',function($classes){if(ig_ads_visible())$classes[]='ig-has-ads';return $classes;});
add_action('wp_footer',function(){if(!ig_ads_visible())return;echo '<span class="ig-ad-rail" role="complementary" aria-label="تبلیغات" data-nosnippet><span class="ig-ad-label">تبلیغات</span>';
    foreach(ig_ads()as$ad){echo '<span class="ig-ad-card">';if($ad['url'])echo '<a href="'.esc_url($ad['url']).'" rel="sponsored nofollow noopener noreferrer" target="_blank">';
        $picture=ig_ad_image($ad,'medium');if($picture)echo '<span class="ig-ad-picture">'.$picture.'</span>';
        echo '<span class="ig-ad-text">'.ig_ad_text($ad['text']).'</span>';if($ad['url'])echo '</a>';echo '</span>';}
    echo '</span>';
});
