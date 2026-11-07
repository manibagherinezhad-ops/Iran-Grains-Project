<?php
// Scoped SEO regression tests; run only in the local development installation.
if(wp_get_environment_type()!=='local'||!current_user_can('manage_options'))throw new RuntimeException('Local manager required.');
function ig_seo_assert($condition,string $label): void {if(!$condition)throw new RuntimeException('FAIL: '.$label);}
$ids=[];$previous=get_option('ig_seo',null);$tag='ig-seo-test-'.wp_rand(100000,999999);
try{
    foreach(['publish','draft','private','future']as$status){
        $args=['post_type'=>'ig_company','post_title'=>$tag.' '.$status,'post_excerpt'=>'فعالیت تخصصی آزمایش','post_content'=>'معرفی اختصاصی شرکت آزمایشی','post_status'=>$status];
        if($status==='future')$args['post_date']=wp_date('Y-m-d H:i:s',time()+DAY_IN_SECONDS);
        $id=wp_insert_post($args,true);if(is_wp_error($id))throw new RuntimeException($id->get_error_message());$ids[$status]=$id;
    }
    $ids['protected']=wp_insert_post(['post_type'=>'ig_company','post_title'=>$tag.' protected','post_status'=>'publish','post_password'=>'private-test-content']);
    update_post_meta($ids['publish'],'_ig_activity','بازرگانی غلات');
    update_post_meta($ids['publish'],'_ig_website','https://company.example.invalid/');
    $post=get_post($ids['publish']);$meta=ig_seo_metadata('company',$post);
    ig_seo_assert(str_contains($meta['title'],$post->post_title),'company title contains actual company name');
    ig_seo_assert(str_contains($meta['description'],'بازرگانی غلات'),'company description uses actual activity');
    ig_seo_assert($meta['url']===get_permalink($post),'company canonical is permalink');
    $graph=ig_seo_schema($meta,$post)['@graph'];$company=array_values(array_filter($graph,fn($node)=>($node['@id']??'')===$meta['url'].'#company'))[0];
    ig_seo_assert($company['url']==='https://company.example.invalid/'&&isset($company['memberOf']),'company entity and membership are accurate');
    ig_seo_assert(!isset($company['telephone'],$company['aggregateRating']),'no fabricated phone or ratings');
    $listing=array_values(array_filter(ig_company_listing_feed(),fn($item)=>$item['id']===$post->ID))[0];
    ig_seo_assert(isset($listing['name'],$listing['url'])&&!isset($listing['fullActivity'],$listing['phone'],$listing['address']),'listing JSON only includes fields needed by cards and search');
    $ids['board']=wp_insert_post(['post_type'=>'ig_board','post_title'=>$tag.' member','post_content'=>'معرفی عضو هیأت مدیره','post_status'=>'publish']);
    update_post_meta($ids['board'],'_ig_position','عضو هیأت مدیره');$board=get_post($ids['board']);
    $people=array_values(array_filter(ig_seo_schema(ig_seo_metadata('news-article',$board),$board)['@graph'],fn($item)=>$item['@type']==='Person'));
    ig_seo_assert(count($people)===1&&$people[0]['jobTitle']==='عضو هیأت مدیره','board person markup matches actual role');
    $ids['document']=wp_insert_post(['post_type'=>'ig_circular','post_title'=>$tag.' document','post_content'=>'متن بخشنامهٔ آزمایشی','post_status'=>'publish']);
    update_post_meta($ids['document'],'_ig_document_number','TEST-64');$document=get_post($ids['document']);
    $documents=array_values(array_filter(ig_seo_schema(ig_seo_metadata('news-article',$document),$document)['@graph'],fn($item)=>$item['@type']==='DigitalDocument'));
    ig_seo_assert(count($documents)===1&&$documents[0]['identifier']==='TEST-64'&&!isset($documents[0]['encoding']),'document schema contains actual identifier and does not invent an attachment');
    $ids['event']=wp_insert_post(['post_type'=>'ig_event','post_title'=>$tag.' meeting','post_content'=>'گزارش جلسهٔ آزمایشی','post_status'=>'publish']);
    update_post_meta($ids['event'],'_ig_event_date','2026-09-01');update_post_meta($ids['event'],'_ig_location','تهران');
    $dom=ig_copy_source('news-article');ig_article($dom,get_post($ids['event']));
    ig_seo_assert(ig_node($dom,'articleDate')->getAttribute('datetime')==='2026-09-01','meeting page displays actual meeting date');
    ig_seo_assert(str_contains(ig_node($dom,'articleBody')->textContent,'تهران'),'meeting details remain visible in initial HTML');
    update_post_meta($post->ID,'_ig_seo_title','عنوان اختصاصی مدیر');update_post_meta($post->ID,'_ig_seo_description','توضیح اختصاصی مدیر');
    $custom=ig_seo_metadata('company',$post);ig_seo_assert($custom['title']==='عنوان اختصاصی مدیر'&&$custom['description']==='توضیح اختصاصی مدیر','editor metadata overrides defaults');
    $public=wp_list_pluck(ig_public_posts('ig_company'),'ID');ig_seo_assert(in_array($ids['publish'],$public),'published company is discoverable');
    foreach(['draft','private','future','protected']as$status)ig_seo_assert(!in_array($ids[$status],$public),'nonpublic company excluded: '.$status);
    $providers=wp_get_sitemap_providers();ig_seo_assert(isset($providers['igpages'])&&!isset($providers['users'],$providers['taxonomies']),'only useful sitemap providers exposed');
    $entries=$providers['posts']->get_url_list(1,'ig_company');$urls=wp_list_pluck($entries,'loc');
    ig_seo_assert(in_array(get_permalink($post),$urls),'published company in sitemap');
    foreach(['draft','private','future','protected']as$status)ig_seo_assert(!in_array(get_permalink($ids[$status]),$urls),'nonpublic company absent from sitemap: '.$status);
    $pages=wp_list_pluck($providers['igpages']->get_url_list(1),'loc');
    ig_seo_assert(in_array(ig_archive_url('members-all',2),$pages),'paginated company archive in sitemap');
    ig_seo_assert(!ig_seo_indexable(),'local environment remains noindex');
    update_option('ig_seo',['pages'=>['members'=>['title'=>'عنوان آرشیو آزمایشی','description'=>'توضیح آرشیو آزمایشی']]],false);
    ig_seo_assert(ig_seo_metadata('members')['title']==='عنوان آرشیو آزمایشی','static page metadata is editable');
    echo "PASS: unique metadata, manager overrides, truthful company schema, public visibility, protected/draft/future exclusions, complete sitemaps and local noindex.\n";
}finally{
    foreach($ids as$id)wp_delete_post($id,true);
    if($previous===null)delete_option('ig_seo');else update_option('ig_seo',$previous,false);
}
