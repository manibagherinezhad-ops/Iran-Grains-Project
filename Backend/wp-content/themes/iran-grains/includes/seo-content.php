<?php
defined('ABSPATH') || exit;
function ig_article_details(DOMDocument $doc,WP_Post $post): void {
    $body=ig_node($doc,'articleBody');if(!$body)return;
    $facts=[];
    if($post->post_type==='ig_event'){
        $date=(string)get_post_meta($post->ID,'_ig_event_date',true);
        if($date&&ig_sanitize_field($date,'date')){$facts['تاریخ جلسه']=ig_date($date);ig_text(ig_node($doc,'articleDate'),ig_date($date));ig_node($doc,'articleDate')?->setAttribute('datetime',$date);}
        foreach(['event_time'=>'ساعت برگزاری','location'=>'محل برگزاری']as$key=>$label)if($value=(string)get_post_meta($post->ID,'_ig_'.$key,true))$facts[$label]=$value;
    }elseif($post->post_type==='ig_board'){
        if($role=(string)get_post_meta($post->ID,'_ig_position',true)){$facts['سمت در انجمن']=$role;ig_text(ig_node($doc,'articleCategory'),$role);}
        ig_node($doc,'articleDate')?->setAttribute('hidden','');
    }elseif(in_array($post->post_type,['ig_circular','ig_price'],true)){
        foreach(['document_number'=>'شمارهٔ ابلاغیه','issuer'=>'مرجع صادرکننده']as$key=>$label)if($value=(string)get_post_meta($post->ID,'_ig_'.$key,true))$facts[$label]=$value;
    }
    if($facts){$dl=$doc->createElement('dl');$dl->setAttribute('class','article-facts');$html='';foreach($facts as$label=>$value)$html.='<div><dt>'.esc_html(ig_ui($label)).'</dt><dd>'.esc_html($value).'</dd></div>';ig_html($dl,$html);$body->insertBefore($dl,$body->firstChild);}
    if($post->post_type==='ig_news'){
        $published=get_post_time('Y-m-d',false,$post);$modified=get_post_modified_time('Y-m-d',false,$post);
        if($modified&&$modified>$published){$time=$doc->createElement('time');$time->setAttribute('class','article-updated');$time->setAttribute('datetime',get_post_modified_time('c',true,$post));$time->textContent=ig_ui('آخرین به‌روزرسانی').': '.ig_date($modified);ig_node($doc,'articleDate')?->parentNode?->appendChild($time);}
    }
    if(!in_array($post->post_type,['ig_news','ig_circular','ig_price'],true))return;
    $taxonomy=ig_taxonomy($post->post_type);$terms=wp_get_post_terms($post->ID,$taxonomy,['fields'=>'ids']);
    $query=['post_type'=>$post->post_type,'post_status'=>'publish','has_password'=>false,'post__not_in'=>[$post->ID],'numberposts'=>3,'date_query'=>[['before'=>current_time('mysql'),'inclusive'=>true]],'orderby'=>['date'=>'DESC','ID'=>'DESC']];
    if($terms&&!is_wp_error($terms))$query['tax_query']=[['taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>$terms]];
    $related=get_posts($query);if(!$related)return;$nav=$doc->createElement('nav');$nav->setAttribute('class','article-related');$nav->setAttribute('aria-label',ig_ui('مطالب مرتبط'));
    $html='<h2>'.esc_html(ig_ui('مطالب مرتبط')).'</h2><ul>';foreach($related as$item)$html.='<li><a href="'.esc_url(get_permalink($item)).'">'.esc_html($item->post_title).'</a></li>';ig_html($nav,$html.'</ul>');$body->parentNode->appendChild($nav);
}
