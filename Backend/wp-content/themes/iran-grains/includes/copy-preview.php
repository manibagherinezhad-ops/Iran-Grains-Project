<?php
defined('ABSPATH') || exit;
function ig_copy_preview_url(string $route): string {
    if($route==='not-found')return home_url('/ig-text-preview-not-found/');
    if(in_array($route,['company','news-article'],true)){$posts=ig_public_posts($route==='company'?'ig_company':'ig_news');return $posts?get_permalink($posts[0]):home_url('/');}
    return ig_page_url(isset(ig_copy_route_labels()[$route])?$route:'index');
}
add_action('wp_ajax_ig_copy_preview',function(){
    if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'اجازهٔ پیش‌نمایش ندارید.'],403);
    check_ajax_referer('ig_copy_preview','preview_nonce');
    try{
        [$view,$route]=ig_copy_request_context();$kind=$view==='content'?'content':'copy';
        if($kind==='content'){$post=get_post(absint($_POST['content_id']??0));if(!$post||!isset(ig_content_types()[$post->post_type])||!current_user_can('edit_post',$post->ID))throw new RuntimeException('محتوا قابل پیش‌نمایش نیست.');$clean=ig_copy_content_clean(wp_unslash((array)($_POST['content']??[])),$post);$data=['kind'=>'content','id'=>$post->ID,'values'=>$clean];$url=add_query_arg('preview','true',get_preview_post_link($post));}
        else{$fields=ig_copy_catalog($view,$route);if(!$fields)throw new RuntimeException('بخش معتبر نیست.');$clean=ig_copy_clean(wp_unslash((array)($_POST['copy']??[])),$fields);$data=['kind'=>'copy','view'=>$view,'route'=>$route,'values'=>$clean];$target=$view==='pages'?$route:sanitize_key($_POST['preview_route']??'index');$url=ig_copy_preview_url($target);}
        $token=wp_generate_password(32,false,false);$data['user']=get_current_user_id();set_transient('ig_text_preview_'.$token,$data,15*MINUTE_IN_SECONDS);
        $url=add_query_arg(['ig_copy_preview'=>$token,'ig_copy_nonce'=>wp_create_nonce('ig_copy_preview_'.$token)],$url);
        wp_send_json_success(['url'=>$url]);
    }catch(Throwable$e){wp_send_json_error(['message'=>$e->getMessage()],400);}
});
function ig_copy_preview_data(): ?array {
    static $loaded=false,$data=null;if($loaded)return $data;$loaded=true;
    $token=sanitize_text_field($_GET['ig_copy_preview']??'');if(!preg_match('/^[A-Za-z0-9]{32}$/',$token)||!current_user_can('manage_options'))return null;
    if(!wp_verify_nonce(sanitize_text_field($_GET['ig_copy_nonce']??''),'ig_copy_preview_'.$token))return null;
    $candidate=get_transient('ig_text_preview_'.$token);if(!is_array($candidate)||(int)$candidate['user']!==get_current_user_id())return null;
    $data=$candidate;return $data;
}
add_action('init',function(){
    $preview=ig_copy_preview_data();if(!$preview)return;
    nocache_headers();add_filter('wp_robots',fn($robots)=>array_merge($robots,['noindex'=>true,'nofollow'=>true]));
    if($preview['kind']==='copy'){
        $fields=ig_copy_catalog($preview['view'],$preview['route']);$values=get_option('ig_text_values',[]);$options=[];
        foreach($preview['values']as$key=>$value){if(!isset($fields[$key]))continue;$values[$key]=$value;$binding=$fields[$key]['binding']??null;if($binding){$option=$binding['option'];$options[$option]??=get_option($option,[]);$options[$option][$binding['key']]=$value;}}
        add_filter('pre_option_ig_text_values',fn()=>$values);foreach($options as$name=>$data)add_filter('pre_option_'.$name,fn()=>$data);
    }else{
        add_filter('get_post_metadata',function($value,$id,$key,$single)use($preview){if((int)$id===(int)$preview['id']&&str_starts_with($key,'_ig_')&&array_key_exists('meta_'.substr($key,4),$preview['values'])){$v=$preview['values']['meta_'.substr($key,4)];return $single?$v:[$v];}return$value;},10,4);
    }
},30);
function ig_copy_preview_post(WP_Post $post): WP_Post {
    $preview=ig_copy_preview_data();if(!$preview||$preview['kind']!=='content'||(int)$preview['id']!==$post->ID)return$post;
    $copy=clone$post;foreach(['post_title','post_excerpt','post_content']as$key)if(isset($preview['values'][$key]))$copy->$key=$preview['values'][$key];return$copy;
}
