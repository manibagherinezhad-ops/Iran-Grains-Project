<?php
defined('ABSPATH') || exit;

function ig_archive_type(string $route): string {
    return ['members-all'=>'ig_company','news'=>'ig_news','circulars'=>'ig_circular','approved-prices'=>'ig_price'][$route] ?? '';
}
function ig_archive_page(): int {
    $value = $_GET['ig_page'] ?? '1';
    return is_scalar($value) && preg_match('/^[1-9][0-9]{0,5}$/D', (string)$value) ? (int)$value : 0;
}
function ig_archive_items(string $route): array {
    $type = ig_archive_type($route);
    $items = $type ? ig_feed($type) : [];
    return $route === 'news' ? array_slice($items, 2) : $items;
}
function ig_archive_pages(string $route): int {
    return max(1, (int)ceil(count(ig_archive_items($route)) / ($route === 'members-all' ? 16 : 6)));
}
function ig_archive_url(string $route, int $page = 1): string {
    return $page > 1 ? add_query_arg('ig_page', $page, ig_page_url($route)) : ig_page_url($route);
}
function ig_archive_pagination(string $route, int $page, int $pages): string {
    if ($pages < 2) return '';
    $link = function(int $target, string $label, string $text, bool $disabled = false) use($route, $page): string {
        if ($disabled) return '<button type="button" disabled aria-label="'.esc_attr($label).'">'.$text.'</button>';
        return '<a href="'.esc_url(ig_archive_url($route,$target)).'" data-page="'.$target.'" aria-label="'.esc_attr($label).'"'.($target===$page?' aria-current="page"':'').'>'.$text.'</a>';
    };
    $html = $link($page-1, 'صفحه قبل', '❮', $page===1);
    for ($i=1; $i<=$pages; $i++) $html .= $link($i, 'صفحه '.ig_fa($i), ig_fa($i));
    return $html.$link($page+1, 'صفحه بعد', '❯', $page===$pages);
}
function ig_company_card_html(array $c, bool $featured = false, bool $promote = false): string {
    $arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6"/></svg>';
    $brand = '<div class="company-brand has-logo"><img src="'.esc_url($c['logo']).'" alt="نشان '.esc_attr($c['name']).'" loading="lazy" decoding="async"></div>';
    $logo = $promote ? '<button type="button" class="member-logo-button" data-promote="'.$c['id'].'" aria-label="نمایش برجستهٔ '.esc_attr($c['name']).'">'.$brand.'</button>' : '<a class="member-logo-button" href="'.esc_url($c['url']).'">'.$brand.'</a>';
    return '<article data-company-card="'.$c['id'].'" class="member-card'.($featured?' member-feature':'').'">'.($featured?'<span class="member-feature-badge">✧ معرفی عضو</span>':'').$logo.'<div class="member-card-copy"><h3><a href="'.esc_url($c['url']).'">'.esc_html($c['name']).'</a></h3><span class="member-activity">'.esc_html($c['activity']).'</span><p>'.esc_html($c['description']).'</p><a href="'.esc_url($c['url']).'" class="members-more'.($featured?' members-more-primary':'').'">'.esc_html(ig_ui('بیشتر بدانید')).' '.$arrow.'</a></div></article>';
}
function ig_render_archive(DOMDocument $doc, string $route): void {
    if ($route === 'members') {
        $items = array_slice(ig_feed('ig_company'),0,8); $html='';
        foreach ([1,2,0,3,4,5,6] as $i) if(isset($items[$i])) $html .= ig_company_card_html($items[$i], $i===0, true);
        if (count($items)===8) {
            $html .= '<aside class="members-ribbon"><div><h3>'.ig_fa(count(ig_public_posts('ig_company'))).' شرکت، همراه صنعت غلات</h3><p>در مسیر توسعهٔ پایدار زنجیرهٔ تأمین، با هم همراه باشیم.</p><a class="btn" href="'.esc_url(ig_page_url('members-all')).'">مشاهده همهٔ شرکت‌ها ←</a></div></aside>'.ig_company_card_html($items[7],false,true);
        }
        if ($grid=ig_node($doc,'membersGrid')) ig_html($grid,$html);
        return;
    }
    if (!ig_archive_type($route)) return;
    $all=ig_archive_items($route); $page=max(1,ig_archive_page()); $size=$route==='members-all'?16:6;
    $items=array_slice($all,($page-1)*$size,$size); $html='';
    $labels=ig_topic_labels(ig_archive_type($route));
    foreach ($items as $item) {
        if ($route==='members-all') {$html.=ig_company_card_html($item); continue;}
        $news=$route==='news'; $label=$news?'مشاهده خبر':($route==='circulars'?'مشاهده بخشنامه':'مشاهده ابلاغیه');
        $html.='<article class="circulars-card">'.($news?'<img class="archive-news-image" src="'.esc_url($item['image']).'" alt="'.esc_attr($item['title']).'" loading="lazy" decoding="async"><div class="archive-news-content">':'').'<div class="circulars-card-top"><span class="circulars-tag">'.esc_html($labels[$item['category']]??'').'</span><time datetime="'.esc_attr($item['date']).'">'.esc_html(ig_date($item['date'])).'</time></div><div class="circulars-card-title">'.(!$news?'<span class="circulars-doc-icon" aria-hidden="true"><svg width="24" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M14 3H5v18h14V8ZM14 3v5h5M8 12h8M8 16h6"/></svg></span>':'').'<h3><a href="'.esc_url($item['url']).'">'.esc_html($item['title']).'</a></h3></div><p>'.esc_html($item['description']).'</p>'.($news?'</div>':'').'<div class="circulars-card-bottom"><a class="ig-document-link" href="'.esc_url($item['url']).'">'.esc_html($label).' ←</a></div></article>';
    }
    if($grid=ig_node($doc,$route==='members-all'?'membersGrid':'circularGrid'))ig_html($grid,$html);
    if($pagination=ig_node($doc,$route==='members-all'?'membersPagination':'circularPagination'))ig_html($pagination,ig_archive_pagination($route,$page,ig_archive_pages($route)));
    $count=count($all);$noun=$route==='members-all'?'شرکت':($route==='news'?'خبر':($route==='circulars'?'بخشنامه':'ابلاغیه'));
    ig_text(ig_node($doc,$route==='members-all'?'membersCount':'circularCount'),$count?'نمایش '.ig_fa(($page-1)*$size+1).' تا '.ig_fa(min($page*$size,$count)).' از '.ig_fa($count).' '.$noun:'۰ '.$noun);
    if($feature=ig_node($doc,'circularFeature'))if($page>1)$feature->setAttribute('hidden','');
}
function ig_render_home_intro(DOMDocument $doc): void {
    $slider=ig_node($doc,'heroSlider');$file=get_template_directory().'/prototype/assets/templates/home-intro.html';
    if(!$slider||!is_readable($file))return;
    ig_html($slider,'<div class="hero-slide hero-intro-stage active">'.file_get_contents($file).'</div>');
    $intro=[];foreach(ig_copy_setting_defaults()as$key=>$default)$intro[$key]=ig_copy_value(['key'=>'setting_'.$key,'default'=>$default,'binding'=>['option'=>'ig_settings','key'=>$key]]);
    $lines=ig_nodes($doc,'//*[@id="heroSlider"]//*['.ig_class('intro-title-line').']');
    if(isset($lines[0]))ig_html($lines[0],esc_html($intro['home_intro_prefix']).' <span class="text-gold">'.esc_html($intro['home_intro_highlight']).'</span> '.esc_html($intro['home_intro_suffix']));
    ig_text($lines[1]??null,$intro['home_intro_line2']);
    ig_text(ig_nodes($doc,'//*[@id="heroSlider"]//*['.ig_class('hero-subtitle').']')[0]??null,$intro['home_intro_subtitle']);
    ig_text(ig_nodes($doc,'//*[@id="heroSlider"]//*['.ig_class('hero-badge').']')[0]??null,$intro['home_intro_badge']);
}
function ig_company_related(DOMDocument $doc,WP_Post $post): void {
    $terms=wp_get_post_terms($post->ID,ig_taxonomy('ig_company'),['fields'=>'ids']);if(!$terms||is_wp_error($terms))return;
    $items=get_posts(['post_type'=>'ig_company','post_status'=>'publish','has_password'=>false,'date_query'=>[['before'=>current_time('mysql'),'inclusive'=>true]],'numberposts'=>4,'post__not_in'=>[$post->ID],'orderby'=>['menu_order'=>'ASC','ID'=>'ASC'],'tax_query'=>[['taxonomy'=>ig_taxonomy('ig_company'),'field'=>'term_id','terms'=>$terms]]]);
    if(!$items)return;$main=ig_node($doc,'companyMain');if(!$main)return;
    $section=$doc->createElement('section');$section->setAttribute('class','geometry-section section ig-company-related');
    $cards='';foreach($items as$item)$cards.=ig_company_card_html(ig_record($item));
    ig_html($section,'<span class="section-geometry" aria-hidden="true"></span><div class="container"><h2 class="section-title">'.esc_html(ig_ui('شرکت‌های هم‌زمینه')).'</h2><div class="members-grid">'.$cards.'</div></div>');$main->appendChild($section);
}
