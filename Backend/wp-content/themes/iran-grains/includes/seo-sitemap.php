<?php
defined('ABSPATH') || exit;
// The local map can be inspected while all local pages remain noindex.
add_filter('wp_sitemaps_enabled',fn($enabled)=>wp_get_environment_type()==='local'?true:$enabled);
add_filter('wp_sitemaps_post_types',fn($types)=>array_intersect_key($types,ig_content_types()));
add_filter('wp_sitemaps_add_provider',fn($provider,$name)=>in_array($name,['users','taxonomies'],true)?false:$provider,10,2);
add_filter('wp_sitemaps_posts_query_args',function($args){
    $args['has_password']=false;$args['post_status']='publish';$args['date_query']=[['before'=>current_time('mysql'),'inclusive'=>true]];return$args;
});
add_action('init',function(){
    if(!class_exists('WP_Sitemaps_Provider'))return;
    $provider=new class extends WP_Sitemaps_Provider {
        public function __construct(){$this->name='igpages';$this->object_type='igpages';}
        public function get_url_list($page_num,$object_subtype=''){
            if((int)$page_num!==1)return[];$urls=[];
            foreach(array_diff(ig_routes(),['company','news-article'])as$route){
                $urls[]=['loc'=>ig_page_url($route)];
                if(ig_archive_type($route))for($page=2;$page<=ig_archive_pages($route);$page++)$urls[]=['loc'=>ig_archive_url($route,$page)];
            }return$urls;
        }
        public function get_max_num_pages($object_subtype=''){return 1;}
    };
    wp_register_sitemap_provider('igpages',$provider);
},20);
