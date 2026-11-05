<?php
defined('ABSPATH') || exit;
function ig_copy_content_fields(WP_Post $post): array {
    $fields=['post_title'=>['label'=>'عنوان','default'=>$post->post_title,'type'=>'text'],
        'post_excerpt'=>['label'=>'خلاصه','default'=>$post->post_excerpt,'type'=>'textarea'],
        'post_content'=>['label'=>'متن کامل','default'=>$post->post_content,'type'=>'rich']];
    foreach(ig_fields($post->post_type)as$key=>[$label,$kind])if($kind!=='pdf')$fields['meta_'.$key]=['label'=>$label,'default'=>(string)get_post_meta($post->ID,'_ig_'.$key,true),'type'=>$kind];
    return $fields;
}
function ig_copy_content_snapshot(WP_Post $post): array {return array_map(fn($f)=>$f['default'],ig_copy_content_fields($post));}
function ig_copy_content_clean(array $input,WP_Post $post): array {
    $clean=[];
    foreach(ig_copy_content_fields($post)as$key=>$field){if(!isset($input[$key])||!is_scalar($input[$key]))continue;$raw=(string)$input[$key];
        if($key==='post_content')$clean[$key]=wp_kses_post(mb_substr($raw,0,200000));
        elseif(str_starts_with($key,'meta_'))$clean[$key]=ig_sanitize_field($raw,$field['type']);
        else $clean[$key]=$key==='post_excerpt'?sanitize_textarea_field($raw):sanitize_text_field($raw);
    }if(isset($clean['post_title'])&&$clean['post_title']==='')throw new RuntimeException('عنوان نمی‌تواند خالی باشد.');return ig_company_sync_summary($clean,$post);
}
function ig_copy_content_write(array $clean,WP_Post $post): void {
    if(!current_user_can('edit_post',$post->ID))throw new RuntimeException('اجازهٔ ویرایش این محتوا را ندارید.');
    $values=['ID'=>$post->ID];foreach(['post_title','post_excerpt','post_content']as$key)if(isset($clean[$key]))$values[$key]=$clean[$key];
    $result=wp_update_post(wp_slash($values),true);if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
    foreach(ig_fields($post->post_type)as$key=>[, $kind])if($kind!=='pdf'&&array_key_exists('meta_'.$key,$clean))update_post_meta($post->ID,'_ig_'.$key,$clean['meta_'.$key]);
}
function ig_copy_content_panel(): void {
    $types=ig_content_types();$type=sanitize_key($_GET['content_type']??$_POST['content_type']??'ig_news');if(!isset($types[$type]))$type='ig_news';
    $id=absint($_GET['content_id']??$_POST['content_id']??0);$post=$id?get_post($id):null;
    if($post&&($post->post_type!==$type||!current_user_can('edit_post',$id)))$post=null;
    $search=sanitize_text_field(wp_unslash($_GET['content_search']??''));$page=max(1,absint($_GET['content_page']??1));
    $query=new WP_Query(['post_type'=>$type,'post_status'=>['publish','draft','pending','private','future'],'posts_per_page'=>30,'paged'=>$page,'s'=>$search,'orderby'=>'date','order'=>'DESC']);
    echo '<div class="ig-copy-content-nav">';foreach($types as$key=>[$label])echo '<a class="button '.($key===$type?'button-primary':'').'" href="'.esc_url(add_query_arg(['page'=>'ig-page-copy','view'=>'content','content_type'=>$key],admin_url('admin.php'))).'">'.esc_html($label).'</a> ';echo '</div>';
    echo '<div class="ig-copy-columns"><aside class="ig-copy-records"><form method="get"><input type="hidden" name="page" value="ig-page-copy"><input type="hidden" name="view" value="content"><input type="hidden" name="content_type" value="'.esc_attr($type).'"><label for="ig-record-search">جستجوی محتوا</label><input class="widefat" id="ig-record-search" name="content_search" value="'.esc_attr($search).'"><button class="button">جستجو</button></form>';
    foreach($query->posts as$item)echo '<a class="ig-copy-record '.($item->ID===$id?'is-active':'').'" href="'.esc_url(add_query_arg(['page'=>'ig-page-copy','view'=>'content','content_type'=>$type,'content_id'=>$item->ID],admin_url('admin.php'))).'">'.esc_html($item->post_title).'<small>'.esc_html(['publish'=>'منتشرشده','draft'=>'پیش‌نویس','pending'=>'در انتظار بررسی','private'=>'خصوصی','future'=>'زمان‌بندی‌شده'][$item->post_status]??'').'</small></a>';
    if(!$query->posts)echo '<p>محتوایی پیدا نشد.</p>';
    if($page>1)echo '<a class="button" href="'.esc_url(add_query_arg('content_page',$page-1)).'">صفحهٔ قبل</a> ';
    if($page<$query->max_num_pages)echo '<a class="button" href="'.esc_url(add_query_arg('content_page',$page+1)).'">صفحهٔ بعد</a>';
    echo '<p><a class="button" href="'.esc_url(admin_url('post-new.php?post_type='.$type)).'">افزودن '.$types[$type][1].'</a></p>';
    if($type!=='ig_board')echo '<a href="'.esc_url(add_query_arg(['page'=>'ig-page-copy','view'=>'topics','content_type'=>$type],admin_url('admin.php'))).'">ویرایش نام موضوعات</a>';
    echo '</aside><div class="ig-copy-record-editor">';
    if(!$post){echo '<div class="ig-copy-empty">یک محتوا را برای ویرایش متن انتخاب کنید.</div></div></div>';return;}
    echo '<h2>'.esc_html($post->post_title).'</h2><p>عنوان، خلاصه و متن را از همین‌جا ویرایش کنید. وضعیت انتشار با ذخیرهٔ متن تغییر نمی‌کند.</p><form method="post" class="ig-copy-form" data-copy-preview="1">';
    if($type==='ig_company')echo '<p class="description">«فعالیت کوتاه» و «خلاصه» در کارت‌های شرکت در معرفی اعضا و آرشیو نمایش داده می‌شوند. با ویرایش متن کامل، خلاصهٔ کارت هم تازه می‌شود؛ اگر هم‌زمان خلاصه را تغییر دهید، متن دلخواه شما نمایش داده می‌شود.</p>';
    wp_nonce_field('ig_text_center');echo '<input type="hidden" name="ig_action" value="save_content"><input type="hidden" name="view" value="content"><input type="hidden" name="content_type" value="'.esc_attr($type).'"><input type="hidden" name="content_id" value="'.$post->ID.'"><input type="hidden" name="revision" value="'.esc_attr(hash('sha256',wp_json_encode(ig_copy_content_snapshot($post)))).'">';
    foreach(ig_copy_content_fields($post)as$key=>$field){echo '<div class="ig-copy-field"><label for="ig-content-'.esc_attr($key).'">'.esc_html($field['label']).'</label>';
        if($field['type']==='rich')wp_editor($field['default'],'ig_copy_content',['textarea_name'=>'content[post_content]','textarea_rows'=>12,'media_buttons'=>false,'teeny'=>true]);
        elseif($field['type']==='status'){echo '<select id="ig-content-'.esc_attr($key).'" name="content['.esc_attr($key).']">';foreach(['upcoming'=>'در پیش رو','held'=>'برگزارشده']as$v=>$label)echo '<option value="'.$v.'" '.selected($v,$field['default'],false).'>'.$label.'</option>';echo '</select>';}
        else echo '<textarea class="large-text" rows="'.($field['type']==='textarea'?4:2).'" id="ig-content-'.esc_attr($key).'" name="content['.esc_attr($key).']">'.esc_textarea($field['default']).'</textarea>';
        echo '</div>';
    }
    ig_copy_save_bar(get_permalink($post),'ذخیرهٔ متن محتوا');echo '</form><p><a href="'.esc_url(get_edit_post_link($post->ID,'raw')).'">مدیریت تصویر، دسته‌بندی و انتشار این محتوا</a></p></div></div>';
}
function ig_copy_topics_panel(): void {
    $type=sanitize_key($_GET['content_type']??$_POST['content_type']??'ig_news');if(!isset(ig_content_types()[$type])||$type==='ig_board')$type='ig_news';
    $terms=get_terms(['taxonomy'=>ig_taxonomy($type),'hide_empty'=>false]);
    echo '<h2>نام موضوعات · '.esc_html(ig_content_types()[$type][0]).'</h2><form method="post">';wp_nonce_field('ig_text_center');echo '<input type="hidden" name="ig_action" value="save_topics"><input type="hidden" name="view" value="topics"><input type="hidden" name="content_type" value="'.esc_attr($type).'">';
    foreach(is_wp_error($terms)?[]:$terms as$term)echo '<div class="ig-copy-field"><label for="ig-term-'.$term->term_id.'">'.esc_html($term->name).'</label><input class="regular-text" id="ig-term-'.$term->term_id.'" name="terms['.$term->term_id.']" value="'.esc_attr($term->name).'"></div>';
    submit_button('ذخیرهٔ نام موضوعات');echo '</form>';
}
