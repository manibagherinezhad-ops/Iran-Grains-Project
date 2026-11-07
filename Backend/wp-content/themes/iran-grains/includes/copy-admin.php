<?php
defined('ABSPATH') || exit;
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','مرکز مدیریت متن‌ها','مرکز مدیریت متن‌ها','manage_options','ig-page-copy','ig_copy_admin'));
function ig_copy_request_context(): array {
    $view=sanitize_key($_POST['view']??$_GET['view']??'pages');if(!in_array($view,['pages','shared','messages','settings','content','topics','history'],true))$view='pages';
    $route=sanitize_key($_POST['route']??$_GET['route']??'index');if(!isset(ig_copy_route_labels()[$route]))$route='index';return[$view,$route];
}
function ig_copy_admin_action(): string {
    if($_SERVER['REQUEST_METHOD']!=='POST')return '';
    check_admin_referer('ig_text_center');[$view,$route]=ig_copy_request_context();$action=sanitize_key($_POST['ig_action']??'save');
    if($action==='restore'){
        $id=sanitize_text_field($_POST['history_id']??'');foreach(get_option('ig_text_history',[])as$entry)if($entry['id']===$id){
            $before=$entry['before'];$extra=$entry['extra']??[];
            if(($extra['kind']??'')==='content'){$post=get_post((int)$extra['id']);if(!$post||!isset(ig_content_types()[$post->post_type])||!current_user_can('edit_post',$post->ID))throw new RuntimeException('این محتوا قابل بازگردانی نیست.');$now=ig_copy_content_snapshot($post);ig_copy_content_write(ig_copy_content_clean($before,$post),$post);ig_copy_save_history($entry['scope'],$now,$before,$extra);}
            elseif(($extra['kind']??'')==='topics'){
                $taxonomy=ig_taxonomy($extra['type']);if(!taxonomy_exists($taxonomy))throw new RuntimeException('موضوع پیدا نشد.');$now=[];foreach($before as$termId=>$name){$term=get_term((int)$termId,$taxonomy);if(!$term||is_wp_error($term))continue;$now[$termId]=$term->name;$r=wp_update_term((int)$termId,$taxonomy,['name'=>sanitize_text_field($name)]);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());}ig_copy_save_history($entry['scope'],$now,$before,$extra);
            }else{[$v,$r]=explode(':',$entry['scope'],2);$fields=ig_copy_catalog($v,$r);$now=ig_copy_snapshot($fields);ig_copy_write(ig_copy_clean($before,$fields),$fields);ig_copy_save_history($entry['scope'],$now,ig_copy_snapshot($fields),$extra);}
            return 'نسخهٔ قبلی با موفقیت بازگردانی شد.';
        }throw new RuntimeException('نسخهٔ انتخاب‌شده پیدا نشد.');
    }
    if($action==='save_content'){
        $post=get_post(absint($_POST['content_id']??0));if(!$post||!isset(ig_content_types()[$post->post_type])||!current_user_can('edit_post',$post->ID))throw new RuntimeException('اجازهٔ ویرایش این محتوا را ندارید.');
        $before=ig_copy_content_snapshot($post);if(!hash_equals(hash('sha256',wp_json_encode($before)),sanitize_text_field($_POST['revision']??'')))throw new RuntimeException('این محتوا در پنجرهٔ دیگری تغییر کرده است؛ ابتدا صفحه را تازه‌سازی کنید.');
        $clean=ig_copy_content_clean(wp_unslash((array)($_POST['content']??[])),$post);ig_copy_content_write($clean,$post);ig_copy_save_history('content:'.$post->ID,$before,ig_copy_content_snapshot(get_post($post->ID)),['kind'=>'content','id'=>$post->ID]);return 'متن محتوا ذخیره شد.';
    }
    if($action==='save_topics'){
        $type=sanitize_key($_POST['content_type']??'');if(!isset(ig_content_types()[$type])||$type==='ig_board')throw new RuntimeException('بخش معتبر نیست.');$taxonomy=ig_taxonomy($type);$before=[];$after=[];
        foreach(wp_unslash((array)($_POST['terms']??[]))as$id=>$name){$term=get_term(absint($id),$taxonomy);if(!$term||is_wp_error($term)||!is_scalar($name))continue;$value=sanitize_text_field((string)$name);if($value==='')throw new RuntimeException('نام موضوع نمی‌تواند خالی باشد.');$before[$id]=$term->name;$result=wp_update_term((int)$id,$taxonomy,['name'=>$value]);if(is_wp_error($result))throw new RuntimeException($result->get_error_message());$after[$id]=$value;}
        ig_copy_save_history('topics:'.$type,$before,$after,['kind'=>'topics','type'=>$type]);return 'نام موضوعات ذخیره شد.';
    }
    $fields=ig_copy_catalog($view,$route);if(!$fields)throw new RuntimeException('بخش قابل ویرایش پیدا نشد.');
    if(!hash_equals(ig_copy_revision($fields),sanitize_text_field($_POST['revision']??'')))throw new RuntimeException('متن‌ها در پنجرهٔ دیگری تغییر کرده‌اند؛ ابتدا صفحه را تازه‌سازی کنید.');
    $clean=ig_copy_clean(wp_unslash((array)($_POST['copy']??[])),$fields);$before=ig_copy_snapshot($fields);ig_copy_write($clean,$fields);ig_copy_save_history($view.':'.$route,$before,ig_copy_snapshot($fields));return 'متن‌ها ذخیره شدند.';
}
function ig_copy_save_bar(string $url,string $label='ذخیرهٔ تغییرات'): void {
    echo '<div class="ig-copy-savebar"><button type="submit" id="submit" class="button button-primary button-large">'.esc_html($label).'</button><button type="button" class="button button-large ig-copy-preview-button" data-preview-url="'.esc_url($url).'">پیش‌نمایش تغییرات</button><span class="ig-copy-dirty" role="status">تغییری ثبت نشده است.</span></div>';
}
function ig_copy_history_panel(): void {
    echo '<h2>تاریخچهٔ تغییرات</h2><p>با بازگردانی، فقط متن‌های همان بخش به نسخهٔ قبلی برمی‌گردند.</p>';
    $names=['pages'=>'صفحه','shared'=>'متن‌های مشترک','messages'=>'دکمه‌ها و پیام‌ها','settings'=>'اطلاعات انجمن','content'=>'محتوای بخش‌ها','topics'=>'نام موضوعات'];
    $history=get_option('ig_text_history',[]);if(!$history){echo '<div class="ig-copy-empty">هنوز تغییری ذخیره نشده است.</div>';return;}
    echo '<div class="ig-copy-history">';foreach($history as$entry){[$kind,$id]=array_pad(explode(':',$entry['scope'],2),2,'');$label=$names[$kind]??'متن‌های سایت';if($kind==='pages')$label.=' · '.(ig_copy_route_labels()[$id]??$id);if($kind==='content')$label.=' · '.get_the_title((int)$id);$user=get_userdata((int)$entry['user']);
        echo '<article><div><h3>'.esc_html($label).'</h3><p>'.esc_html($entry['time'].' · '.($user?$user->display_name:'مدیر')).'</p><span>'.count($entry['after']).' متن در این نسخه</span></div><form method="post">';wp_nonce_field('ig_text_center');echo '<input type="hidden" name="view" value="history"><input type="hidden" name="ig_action" value="restore"><input type="hidden" name="history_id" value="'.esc_attr($entry['id']).'"><button class="button" data-copy-confirm="متن‌های این بخش به نسخهٔ قبلی بازگردند؟">بازگردانی متن قبلی</button></form></article>';
    }echo '</div>';
}
function ig_copy_admin(): void {
    if(!current_user_can('manage_options'))wp_die('دسترسی مجاز نیست.','',['response'=>403]);
    [$view,$route]=ig_copy_request_context();$notice='';$error='';try{$notice=ig_copy_admin_action();}catch(Throwable$e){$error=$e->getMessage();}
    echo '<div class="wrap ig-admin ig-copy-center"><div class="ig-copy-heading"><div><span class="ig-copy-eyebrow">انجمن غلات ایران</span><h1>مرکز مدیریت متن‌های سایت</h1><p>صفحه یا بخش موردنظر را انتخاب کنید، متن را تغییر دهید و نتیجه را پیش‌نمایش کنید.</p></div><a class="button" target="_blank" rel="noopener" href="'.esc_url(home_url('/')).'">مشاهدهٔ سایت ←</a></div>';
    if($notice)echo '<div class="notice notice-success"><p>'.esc_html($notice).'</p></div>';if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
    $tabs=['pages'=>'صفحات سایت','shared'=>'هدر و فوتر','messages'=>'دکمه‌ها و پیام‌ها','settings'=>'اطلاعات انجمن','content'=>'محتوای بخش‌ها','history'=>'تاریخچه'];
    echo '<nav class="ig-copy-tabs" aria-label="بخش‌های مدیریت متن">';foreach($tabs as$key=>$label)echo '<a class="'.(($view===$key||$view==='topics'&&$key==='content')?'is-active':'').'" href="'.esc_url(add_query_arg(['page'=>'ig-page-copy','view'=>$key],admin_url('admin.php'))).'">'.esc_html($label).'</a>';echo '</nav>';
    if($view==='content'){ig_copy_content_panel();ig_copy_preview_dialog();echo '</div>';return;}if($view==='topics'){ig_copy_topics_panel();echo '</div>';return;}if($view==='history'){ig_copy_history_panel();echo '</div>';return;}
    echo '<div class="ig-copy-workspace">';
    if($view==='pages'){echo '<aside class="ig-copy-pages" aria-label="انتخاب صفحه">';foreach(ig_copy_route_labels()as$key=>$label)echo '<a class="'.($route===$key?'is-active':'').'" href="'.esc_url(add_query_arg(['page'=>'ig-page-copy','view'=>'pages','route'=>$key],admin_url('admin.php'))).'">'.esc_html($label).'</a>';echo '</aside>';}
    $fields=ig_copy_catalog($view,$route);$groups=[];foreach($fields as$field)$groups[$field['section']][]=$field;
    echo '<main class="ig-copy-editor"><div class="ig-copy-toolbar"><div><h2>'.esc_html($view==='pages'?ig_copy_route_labels()[$route]:$tabs[$view]).'</h2><span>'.count($fields).' متن قابل ویرایش</span></div><label class="ig-copy-search"><span>جستجو در متن‌ها</span><input id="ig-copy-search" type="search" placeholder="عنوان، متن یا عبارت موردنظر…"></label><button class="button" type="button" id="ig-copy-expand">بازکردن همهٔ بخش‌ها</button></div>';
    if($view!=='pages'){echo '<p><label for="ig-copy-preview-route">صفحهٔ پیش‌نمایش </label><select id="ig-copy-preview-route">';foreach(ig_copy_route_labels()+['not-found'=>'صفحهٔ پیدا نشد']as$key=>$label)echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';echo '</select></p>';}
    if($view==='messages')echo '<p class="ig-copy-tip">نام‌ها و عددهای خودکار داخل { } قرار دارند. این عبارت‌ها را نگه دارید تا عنوان خبر، تعداد نتایج و شمارهٔ صفحه درست نمایش داده شوند.</p>';
    if($view==='pages'&&in_array($route,['news','news-article','members','members-all','company','meetings','board','circulars','approved-prices'],true))echo '<p class="ig-copy-tip">عنوان و متن هر خبر، شرکت یا سند از زبانهٔ «محتوای بخش‌ها» ویرایش می‌شود. اینجا تیترها، برچسب‌ها و توضیحات ثابت صفحه را تغییر دهید.</p>';
    echo '<form method="post" class="ig-copy-form" data-copy-preview="1">';wp_nonce_field('ig_text_center');echo '<input type="hidden" name="ig_action" value="save"><input type="hidden" name="view" value="'.esc_attr($view).'"><input type="hidden" name="route" value="'.esc_attr($route).'"><input type="hidden" name="revision" value="'.esc_attr(ig_copy_revision($fields)).'">';
    $n=0;foreach($groups as$section=>$rows){echo '<details class="ig-copy-section"'.($n++===0?' open':'').'><summary><span>'.esc_html($section).'</span><small>'.count($rows).' متن</small></summary><div class="ig-copy-section-body">';foreach($rows as$field){$key=$field['key'];$value=(string)ig_copy_value($field);$type=$field['type']??'text';echo '<div class="ig-copy-field" data-copy-default="'.esc_attr($field['default']).'"><div class="ig-copy-field-heading"><label for="ig-copy-'.$key.'">'.esc_html($field['label']).'</label>'.(!empty($field['shared'])?'<span class="ig-copy-shared">مشترک در چند بخش</span>':'').'<button type="button" class="button-link ig-copy-default">متن اولیه</button></div>';
        if(in_array($type,['number','date','email','url'],true))echo '<input class="large-text" type="'.esc_attr($type).'" dir="ltr"'.($type==='number'?' min="0" step="1"':'').' id="ig-copy-'.$key.'" name="copy['.$key.']" value="'.esc_attr($value).'">';
        else echo '<textarea class="large-text" rows="'.(mb_strlen($value)>110?4:2).'" id="ig-copy-'.$key.'" name="copy['.$key.']">'.esc_textarea($value).'</textarea>';
        echo '<small class="ig-copy-original">متن اولیه: '.esc_html(mb_substr($field['default'],0,160)).'</small>';if(!empty($field['tokens']))echo '<small class="ig-copy-tokens">عبارت‌های خودکار: '.esc_html(implode('، ',array_map(fn($t)=>'{'.$t.'}',$field['tokens']))).'</small>';echo '</div>';
    }echo '</div></details>';}
    echo '<p id="ig-copy-no-results" hidden>متنی با این عبارت پیدا نشد.</p>';ig_copy_save_bar(ig_page_url($view==='pages'&&!in_array($route,['company','news-article'],true)?$route:'index'));echo '</form></main></div>';ig_copy_preview_dialog();echo '</div>';
}
function ig_copy_preview_dialog(): void {echo '<dialog id="ig-copy-preview"><div class="ig-copy-preview-head"><strong>پیش‌نمایش تغییرات ذخیره‌نشده</strong><div><button type="button" class="button" data-preview-width="100%">دسکتاپ</button> <button type="button" class="button" data-preview-width="390px">موبایل</button> <button type="button" class="button" id="ig-copy-preview-close">بستن</button></div></div><p id="ig-copy-preview-status" role="status"></p><iframe title="پیش‌نمایش صفحه" id="ig-copy-preview-frame"></iframe></dialog>';}
add_action('admin_enqueue_scripts',function($hook){if(!str_contains($hook,'ig-page-copy'))return;wp_enqueue_script('ig-copy-admin',get_template_directory_uri().'/assets/copy-admin.js',[],filemtime(__DIR__.'/../assets/copy-admin.js'),true);wp_localize_script('ig-copy-admin','igCopyAdmin',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('ig_copy_preview')]);});
