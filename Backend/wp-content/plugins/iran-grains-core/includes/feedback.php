<?php
defined('ABSPATH') || exit;
add_action('init',function(){
    if(get_option('ig_feedback_schema')==='1')return;
    global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$charset=$wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$wpdb->prefix}ig_feedback (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      rating tinyint unsigned NOT NULL,
      comment text NOT NULL,
      status varchar(20) NOT NULL DEFAULT 'new',
      created_at datetime NOT NULL,
      PRIMARY KEY  (id)
    ) $charset;");
    if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.'ig_feedback'))))update_option('ig_feedback_schema','1',false);
},21);
add_action('rest_api_init',function(){register_rest_route('iran-grains/v1','/feedback',['methods'=>'POST','permission_callback'=>fn($r)=>wp_verify_nonce((string)$r->get_param('_ig_nonce'),'ig_feedback')?true:new WP_Error('nonce','صفحه را تازه‌سازی کنید.',['status'=>403]),'callback'=>'ig_receive_feedback']);});
function ig_receive_feedback(WP_REST_Request $r) {
    $rating=filter_var($r->get_param('rating'),FILTER_VALIDATE_INT);$raw=$r->get_param('comment');if(!is_scalar($raw))return new WP_Error('fields','نظر معتبر وارد کنید.',['status'=>400]);$comment=sanitize_textarea_field((string)$raw);
    if(strlen((string)$r->get_body())>16000||$rating<1||$rating>5||!$comment||mb_strlen($comment)>2000||$r->get_param('website'))return new WP_Error('fields','رأی و نظر معتبر (حداکثر ۲۰۰۰ نویسه) وارد کنید.',['status'=>400]);
    $key='ig_feedback_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',wp_salt('nonce'));$count=(int)get_transient($key);
    if($count>=3)return new WP_Error('rate','نظر شما پیش‌تر ثبت شده است؛ لطفاً بعداً تلاش کنید.',['status'=>429]);
    global $wpdb;$ok=$wpdb->insert($wpdb->prefix.'ig_feedback',['rating'=>$rating,'comment'=>$comment,'created_at'=>current_time('mysql',true)],['%d','%s','%s']);
    if(!$ok)return new WP_Error('storage','ثبت نظر انجام نشد؛ دوباره تلاش کنید.',['status'=>500]);
    set_transient($key,$count+1,DAY_IN_SECONDS);return new WP_REST_Response(['message'=>'سپاس؛ رأی و نظر شما ثبت شد.'],201);
}
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','ارزیابی عملکرد انجمن','ارزیابی عملکرد انجمن','manage_options','ig-feedback','ig_feedback_admin'));
function ig_feedback_admin():void {
    if(!current_user_can('manage_options'))wp_die('دسترسی مجاز نیست.');global $wpdb;
    if($_SERVER['REQUEST_METHOD']==='POST'){check_admin_referer('ig_feedback_action');$id=absint($_POST['id']??0);if(isset($_POST['delete']))$wpdb->delete($wpdb->prefix.'ig_feedback',['id'=>$id],['%d']);else$wpdb->update($wpdb->prefix.'ig_feedback',['status'=>'read'],['id'=>$id],['%s'],['%d']);ig_audit('feedback_reviewed','feedback',$id);}
    $page=max(1,absint($_GET['paged']??1));$stats=$wpdb->get_row("SELECT COUNT(*) total, AVG(rating) average FROM {$wpdb->prefix}ig_feedback");
    echo '<div class="wrap ig-admin"><h1>ارزیابی عملکرد انجمن</h1><p>تعداد رأی‌ها: '.esc_html($stats->total).' | میانگین: '.esc_html(number_format((float)$stats->average,2)).' از ۵</p>';
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ig_feedback ORDER BY id DESC LIMIT 50 OFFSET %d",($page-1)*50));
    foreach($rows as$row){echo '<article class="ig-admin-message"><h2>امتیاز '.esc_html($row->rating).' از ۵</h2><p>'.nl2br(esc_html($row->comment)).'</p><small>'.esc_html($row->created_at.' UTC — '.$row->status).'</small><form method="post">';wp_nonce_field('ig_feedback_action');echo '<input type="hidden" name="id" value="'.absint($row->id).'"><button class="button">خوانده‌شده</button> <button class="button" name="delete" value="1">حذف</button></form></article>';}
    ig_admin_pagination('ig-feedback',$page,count($rows));echo '</div>';
}
