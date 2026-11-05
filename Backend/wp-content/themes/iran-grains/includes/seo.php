<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/seo-archive.php';
require_once __DIR__.'/seo-admin.php';
require_once __DIR__.'/seo-sitemap.php';
require_once __DIR__.'/seo-content.php';

function ig_seo_indexable(): bool {
    return wp_get_environment_type()==='production' && (bool)get_option('blog_public') && !is_preview() && !ig_copy_preview_data();
}
function ig_seo_snippet(string $text, int $limit=170): string {
    $text=trim(preg_replace('/[\p{Z}\s]+/u',' ',html_entity_decode(wp_strip_all_tags(strip_shortcodes($text)),ENT_QUOTES,'UTF-8')));
    return mb_strlen($text)>$limit ? rtrim(mb_substr($text,0,$limit-1)).'…' : $text;
}
function ig_seo_phone(string $value): string {
    return preg_replace('/[^+0-9]/','',strtr(trim(explode('/',$value)[0]),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']));
}
function ig_seo_metadata(string $route, ?WP_Post $post=null, ?DOMDocument $doc=null): array {
    $settings=get_option('ig_seo',[]);$entry=$settings['pages'][$route]??[];
    if(!$doc){$doc=ig_copy_source($route);if($doc)ig_copy_apply_targets(ig_copy_prepare($doc,$route));}
    $title=$doc?->getElementsByTagName('title')->item(0)?->textContent ?: (ig_copy_route_labels()[$route]??'انجمن غلات ایران');
    $description=$doc ? ((ig_nodes($doc,'//meta[@name="description"]')[0]??null)?->getAttribute('content')??'') : '';
    $image=ig_asset('assets/img/hero-wheat-bg.png');$url=ig_page_url($route);$page=ig_archive_type($route)?max(1,ig_archive_page()):1;
    if($route==='news'&&($latest=ig_public_posts('ig_news')))$image=ig_image($latest[0]);
    if($post){
        $url=get_permalink($post);$image=ig_image($post);
        $title=$post->post_title.($post->post_type==='ig_company'?' | شرکت عضو انجمن غلات ایران':' | انجمن غلات ایران');
        if($post->post_type==='ig_company'){
            $activity=(string)get_post_meta($post->ID,'_ig_activity',true);
            $description='معرفی '.$post->post_title.'، عضو انجمن غلات ایران'.($activity?'؛ '.$activity:'').'. اطلاعات شرکت و راه‌های ارتباطی.';
        }else $description=$post->post_excerpt?:$post->post_content;
        $entry=['title'=>get_post_meta($post->ID,'_ig_seo_title',true),'description'=>get_post_meta($post->ID,'_ig_seo_description',true)];
    }
    if(!empty($entry['title']))$title=$entry['title'];
    if(!empty($entry['description']))$description=$entry['description'];
    if($page>1){$title.=' — صفحهٔ '.ig_fa($page);$description.=' صفحهٔ '.ig_fa($page).'.';$url=ig_archive_url($route,$page);}
    return ['title'=>sanitize_text_field($title),'description'=>ig_seo_snippet($description),'url'=>$url,'image'=>$image,'page'=>$page,'route'=>$route];
}
function ig_seo_breadcrumbs(string $route, ?WP_Post $post, string $url): array {
    $items=[['name'=>'خانه','item'=>home_url('/')]];
    if($post){$parent=match($post->post_type){'ig_company'=>'members','ig_circular'=>'circulars','ig_price'=>'approved-prices','ig_event'=>'meetings','ig_board'=>'board',default=>'news'};$items[]=['name'=>ig_copy_route_labels()[$parent],'item'=>ig_page_url($parent)];$items[]=['name'=>$post->post_title,'item'=>$url];}
    elseif($route!=='index'){
        if($route==='members-all')$items[]=['name'=>'شرکت‌های عضو','item'=>ig_page_url('members')];
        $items[]=['name'=>ig_copy_route_labels()[$route]??'انجمن غلات ایران','item'=>$url];
    }
    return array_map(fn($item,$i)=>['@type'=>'ListItem','position'=>$i+1]+$item,$items,array_keys($items));
}
function ig_seo_schema(array $meta, ?WP_Post $post): array {
    $home=home_url('/');$settings=get_option('ig_settings',[]);$route=$meta['route'];$url=$meta['url'];
    $org=['@type'=>'Organization','@id'=>$home.'#organization','name'=>'انجمن غلات ایران','url'=>$home,'logo'=>['@type'=>'ImageObject','url'=>ig_asset('assets/img/logo.png')]];
    foreach(['contact_email'=>'email','contact_phone'=>'telephone']as$key=>$property)if(!empty($settings[$key]))$org[$property]=$property==='telephone'?ig_seo_phone($settings[$key]):$settings[$key];
    if(!empty($settings['contact_address']))$org['address']=['@type'=>'PostalAddress','streetAddress'=>$settings['contact_address'],'addressCountry'=>'IR'];
    $web=['@type'=>'WebPage','@id'=>$url.'#webpage','url'=>$url,'name'=>$meta['title'],'description'=>$meta['description'],'inLanguage'=>'fa-IR','isPartOf'=>['@id'=>$home.'#website'],'publisher'=>['@id'=>$home.'#organization']];
    if(in_array($route,['members','board','meetings'],true)||ig_archive_type($route))$web['@type']='CollectionPage';
    if($route==='contact')$web['@type']='ContactPage';
    if($route==='about')$web['@type']='AboutPage';
    $graph=[$org,['@type'=>'WebSite','@id'=>$home.'#website','url'=>$home,'name'=>'انجمن غلات ایران','inLanguage'=>'fa-IR','publisher'=>['@id'=>$home.'#organization']]];
    if($route!=='index'){$web['breadcrumb']=['@id'=>$url.'#breadcrumb'];$graph[]=['@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumb','itemListElement'=>ig_seo_breadcrumbs($route,$post,$url)];}
    if($post&&$post->post_type==='ig_company'){
        $company=['@type'=>'Organization','@id'=>$url.'#company','name'=>$post->post_title,'mainEntityOfPage'=>['@id'=>$url.'#webpage'],'memberOf'=>['@id'=>$home.'#organization'],'description'=>ig_seo_snippet($post->post_content?:$post->post_excerpt,400)];
        foreach(['phone'=>'telephone','fax'=>'faxNumber','email'=>'email','website'=>'url']as$key=>$property){$v=(string)get_post_meta($post->ID,'_ig_'.$key,true);if($v!=='')$company[$property]=in_array($key,['phone','fax'],true)?ig_seo_phone($v):$v;}
        if($v=(string)get_post_meta($post->ID,'_ig_address',true))$company['address']=['@type'=>'PostalAddress','streetAddress'=>$v];
        if(has_post_thumbnail($post)||get_post_meta($post->ID,'_ig_seed_image',true))$company['logo']=$meta['image'];
        $web['mainEntity']=['@id'=>$url.'#company'];$graph[]=$company;
    }elseif($post&&$post->post_type==='ig_news'){
        $graph[]=['@type'=>'NewsArticle','@id'=>$url.'#article','headline'=>$post->post_title,'description'=>$meta['description'],'image'=>[$meta['image']],'datePublished'=>get_post_time('c',true,$post),'dateModified'=>get_post_modified_time('c',true,$post),'inLanguage'=>'fa-IR','publisher'=>['@id'=>$home.'#organization'],'mainEntityOfPage'=>['@id'=>$url.'#webpage']];
        $web['mainEntity']=['@id'=>$url.'#article'];
    }elseif($post&&$post->post_type==='ig_board'){
        $person=['@type'=>'Person','@id'=>$url.'#person','name'=>$post->post_title,'url'=>$url,'memberOf'=>['@id'=>$home.'#organization'],'description'=>ig_seo_snippet($post->post_content?:$post->post_excerpt,400)];
        if($role=(string)get_post_meta($post->ID,'_ig_position',true))$person['jobTitle']=$role;
        if(has_post_thumbnail($post)||get_post_meta($post->ID,'_ig_seed_image',true))$person['image']=$meta['image'];
        $graph[]=$person;$web['mainEntity']=['@id'=>$url.'#person'];
    }elseif($post&&in_array($post->post_type,['ig_circular','ig_price'],true)){
        $document=['@type'=>'DigitalDocument','@id'=>$url.'#document','name'=>$post->post_title,'url'=>$url,'description'=>$meta['description'],'inLanguage'=>'fa-IR','mainEntityOfPage'=>['@id'=>$url.'#webpage']];
        if($number=(string)get_post_meta($post->ID,'_ig_document_number',true))$document['identifier']=$number;
        $attachment=absint(get_post_meta($post->ID,'_ig_attachment_id',true));
        if($attachment&&get_post_mime_type($attachment)==='application/pdf'&&($file=wp_get_attachment_url($attachment)))$document['encoding']=['@type'=>'MediaObject','contentUrl'=>$file,'encodingFormat'=>'application/pdf'];
        $graph[]=$document;$web['mainEntity']=['@id'=>$url.'#document'];
    }
    if(in_array($route,['members','board','meetings'],true)||ig_archive_type($route)){
        $items=match($route){'members'=>array_slice(ig_feed('ig_company'),0,8),'board'=>ig_feed('ig_board'),'meetings'=>ig_feed('ig_event'),default=>array_slice(ig_archive_items($route),($meta['page']-1)*($route==='members-all'?16:6),$route==='members-all'?16:6)};
        $graph[]=['@type'=>'ItemList','@id'=>$url.'#list','itemListElement'=>array_map(fn($item,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$item['title'],'url'=>$item['url']],$items,array_keys($items))];
        $web['mainEntity']=['@id'=>$url.'#list'];
    }
    $graph[]=$web;
    return ['@context'=>'https://schema.org','@graph'=>$graph];
}
function ig_seo_apply(DOMDocument $doc,string $route,?WP_Post $post): void {
    $meta=ig_seo_metadata($route,$post,$doc);$head=$doc->getElementsByTagName('head')->item(0);
    foreach(ig_nodes($doc,'//head/title | //head/meta[@name="description" or @name="robots"] | //head/link[@rel="canonical"] | //head/meta[starts-with(@property,"og:") or starts-with(@name,"twitter:")]')as$n)$n->parentNode->removeChild($n);
    $title=$doc->createElement('title');$title->appendChild($doc->createTextNode($meta['title']));$head->appendChild($title);
    $indexable=ig_seo_indexable()&&(!$post||($post->post_status==='publish'&&$post->post_password===''));
    $tags=['description'=>$meta['description'],'robots'=>$indexable?'index,follow,max-image-preview:large':'noindex,nofollow','og:locale'=>'fa_IR','og:site_name'=>'انجمن غلات ایران','og:type'=>$post?->post_type==='ig_news'?'article':'website','og:title'=>$meta['title'],'og:description'=>$meta['description'],'og:url'=>$meta['url'],'og:image'=>$meta['image'],'og:image:alt'=>$post?->post_title??'انجمن غلات ایران','twitter:card'=>'summary_large_image','twitter:title'=>$meta['title'],'twitter:description'=>$meta['description'],'twitter:image'=>$meta['image']];
    if($post?->post_type==='ig_news'){$tags['article:published_time']=get_post_time('c',true,$post);$tags['article:modified_time']=get_post_modified_time('c',true,$post);}
    if($token=get_option('ig_seo',[])['google_verification']??'')$tags['google-site-verification']=$token;
    foreach($tags as$key=>$value){$n=$doc->createElement('meta');$n->setAttribute(str_starts_with($key,'og:')||str_starts_with($key,'article:')?'property':'name',$key);$n->setAttribute('content',$value);$head->appendChild($n);}
    $canonical=$doc->createElement('link');$canonical->setAttribute('rel','canonical');$canonical->setAttribute('href',$meta['url']);$head->appendChild($canonical);
    if(ig_archive_type($route))foreach(['prev'=>$meta['page']-1,'next'=>$meta['page']+1]as$rel=>$page)if($page>=1&&$page<=ig_archive_pages($route)){$n=$doc->createElement('link');$n->setAttribute('rel',$rel);$n->setAttribute('href',ig_archive_url($route,$page));$head->appendChild($n);}
    $schema=$doc->createElement('script');$schema->setAttribute('type','application/ld+json');$schema->appendChild($doc->createCDATASection(wp_json_encode(ig_seo_schema($meta,$post),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)));$head->appendChild($schema);
    $font=$doc->createElement('link');$font->setAttribute('rel','preload');$font->setAttribute('as','font');$font->setAttribute('type','font/woff2');$font->setAttribute('crossorigin','anonymous');$font->setAttribute('href',ig_asset('assets/fonts/YekanBakh-Regular.woff2'));$head->appendChild($font);
    if(in_array($route,['index','news'],true)){$image=$doc->createElement('link');$image->setAttribute('rel','preload');$image->setAttribute('as','image');$image->setAttribute('fetchpriority','high');$image->setAttribute('href',$route==='index'?ig_asset('assets/img/photo-1437252611977-07f74518abd7.avif'):$meta['image']);$head->appendChild($image);}
    $fallback=$doc->createElement('noscript');$style=$doc->createElement('style');$style->appendChild($doc->createTextNode('[data-animate]{opacity:1!important;transform:none!important}.reveal-word{color:var(--ink)}'));$fallback->appendChild($style);$doc->getElementsByTagName('body')->item(0)->appendChild($fallback);
    foreach(ig_nodes($doc,'//img')as$img){
        if(!$img->hasAttribute('decoding'))$img->setAttribute('decoding','async');
        $hero=$img->getAttribute('id')==='articleHeroImage'||$img->getAttribute('fetchpriority')==='high'||$img->parentNode instanceof DOMElement&&$img->parentNode->getAttribute('id')==='companyBrand';
        if($hero){$img->setAttribute('loading','eager');$img->setAttribute('fetchpriority','high');}
        elseif(str_contains($img->getAttribute('class'),'intro-brand'))$img->setAttribute('loading','eager');
        elseif(!$img->hasAttribute('loading')&&!str_contains($img->getAttribute('class'),'logo'))$img->setAttribute('loading','lazy');
        $src=$img->getAttribute('src');$prefix=get_template_directory_uri().'/prototype/';
        if(str_starts_with($src,$prefix)){$local=get_template_directory().'/prototype/'.substr((string)parse_url($src,PHP_URL_PATH),strlen((string)parse_url($prefix,PHP_URL_PATH)));if(is_file($local)&&($size=@getimagesize($local))){if(!$img->hasAttribute('width'))$img->setAttribute('width',(string)$size[0]);if(!$img->hasAttribute('height'))$img->setAttribute('height',(string)$size[1]);}}
    }
}
remove_action('wp_head','wp_robots',1);
add_action('template_redirect',function(){
    if(is_admin()||wp_doing_ajax()||is_feed())return;
    [$route,$post]=ig_resolve_view();
    if(!ig_seo_indexable()||($post&&$post->post_password!==''))header('X-Robots-Tag: noindex, nofollow',true);
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)||is_preview()||ig_copy_preview_data())return;
    $path=rawurldecode((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH));
    if($route!=='404'&&preg_match('/\.html$/',$path)){$target=$post?get_permalink($post):ig_archive_url($route,max(1,ig_archive_page()));wp_safe_redirect($target,301);exit;}
    if($route!=='404')return;
    $base=rawurldecode((string)parse_url(home_url('/'),PHP_URL_PATH));$relative='/'.ltrim(str_starts_with($path,$base)?substr($path,strlen($base)):$path,'/');
    $aliases=['/sherkat-haye-ozv'=>ig_page_url('members'),'/category/akhbar-rooydad'=>ig_page_url('news'),'/category/gheymat-mosavab'=>ig_page_url('approved-prices'),'/sitemap.xml'=>home_url('/wp-sitemap.xml')];
    if(preg_match('#^/sherkat-haye-ozv-(?:[2-9]|1[0-2])/?$#',$relative))$target=ig_page_url('members-all');
    else $target=$aliases[rtrim($relative,'/')]??'';
    if(!$target)foreach(array_keys(ig_content_types())as$type)foreach(ig_public_posts($type)as$item){$source=(string)get_post_meta($item->ID,'_ig_source',true);if($source&&rtrim(rawurldecode((string)parse_url($source,PHP_URL_PATH)),'/')===rtrim($relative,'/')){$target=get_permalink($item);break 2;}}
    if($target){wp_safe_redirect($target,301);exit;}
},5);
add_filter('robots_txt',function($output){
    if(!ig_seo_indexable())return "User-agent: *\nDisallow: /\n";
    return rtrim($output)."\nSitemap: ".home_url('/wp-sitemap.xml')."\n";
},20);
