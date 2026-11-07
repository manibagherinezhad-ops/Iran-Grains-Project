<?php
if(wp_get_environment_type()!=='local'||!current_user_can('manage_options'))throw new RuntimeException('Local manager required.');
function ig_add_assert($ok,$name){if(!$ok)throw new RuntimeException('FAIL: '.$name);}
$models=ig_input_models();ig_add_assert(count($models)===8,'eight inputs only');
foreach($models as$m){$price=$m['slope']*$m['baseline_euro']+$m['intercept'];ig_add_assert(abs($price-$m['cached_result'])<0.00001,'Excel cached result '.$m['name']);}
ig_add_assert(ig_input_costs(0)===[],'invalid euro is not priced');$p=ig_input_costs(2200000);$q=ig_input_costs(2109417);foreach($p as$i=>$item)ig_add_assert($item['price']>$q[$i]['price'],'euro change reaches each item');
$sample='<table><tr data-market-row="price_eur" data-price="9999999"><td>bad</td></tr><tr data-market-row="ice_transfer_eur_sell" data-price="2112773"><th>یورو</th><td>2112773</td><td>0</td><td>1</td><td>2</td><td>۰۸:۱۷:۲۵</td></tr></table>';
ig_add_assert(ig_parse_center_euro($sample)['value']===2112773,'only centre selling transfer EUR accepted');ig_add_assert(ig_parse_center_euro('<html>دسترسی خارج از محدوده</html>')===null,'failed source is not treated as a rate');
$saved=[];foreach(['ig_settings','ig_euro_auto','ig_euro_quote','ig_euro_checked','ig_euro_status']as$key)$saved[$key]=get_option($key,null);
$mock=function($pre,$args,$url)use($sample){return $url===ig_euro_source()?['headers'=>[],'body'=>$sample,'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]]:$pre;};
try{
    update_option('ig_euro_auto',true,false);add_filter('pre_http_request',$mock,10,3);
    ig_add_assert(ig_refresh_euro(true)===true,'automatic refresh succeeds with valid provider response');
    $current=get_option('ig_settings');ig_add_assert((int)$current['rate_eur']===2112773,'automatic euro reaches the shared backend setting');
    foreach(['rate_usd','rate_aed','rate_rub','rate_cny']as$key)ig_add_assert(($current[$key]??null)===($saved['ig_settings'][$key]??null),'other rates unchanged');
    remove_filter('pre_http_request',$mock,10);$bad=fn()=>new WP_Error('test','source unavailable');add_filter('pre_http_request',$bad,10,3);
    ig_add_assert(ig_refresh_euro(true)===false,'source failure is reported');ig_add_assert((int)get_option('ig_settings')['rate_eur']===2112773,'failed refresh preserves last valid euro');remove_filter('pre_http_request',$bad,10);
}finally{remove_filter('pre_http_request',$mock,10);if(isset($bad))remove_filter('pre_http_request',$bad,10);foreach($saved as$key=>$value){if($value===null)delete_option($key);else update_option($key,$value,false);}}
$tag='آزمون ارزیابی '.wp_rand();$key='ig_feedback_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',wp_salt('nonce'));$before=get_transient($key);$id=0;
try{set_transient($key,0,DAY_IN_SECONDS);$r=new WP_REST_Request('POST','/iran-grains/v1/feedback');$r->set_body_params(['rating'=>4,'comment'=>$tag]);$response=rest_do_request($r);ig_add_assert($response->get_status()===403,'nonce required');$r->set_param('_ig_nonce',wp_create_nonce('ig_feedback'));$response=rest_do_request($r);ig_add_assert($response->get_status()===201,'vote and comment stored');global $wpdb;$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ig_feedback WHERE comment=%s",$tag));ig_add_assert($id>0,'feedback persisted');$r->set_param('rating',6);ig_add_assert(rest_do_request($r)->get_status()===400,'invalid score rejected');$r->set_param('rating',4);set_transient($key,3,DAY_IN_SECONDS);ig_add_assert(rest_do_request($r)->get_status()===429,'rate limited');}finally{if($id)$wpdb->delete($wpdb->prefix.'ig_feedback',['id'=>$id],['%d']);if($before===false)delete_transient($key);else set_transient($key,$before,DAY_IN_SECONDS);}
$doc=ig_copy_source('index');ig_add_assert(count(ig_nodes($doc,'//*[@id="associationFeedback"]/following-sibling::section[@id="contact"]'))===1,'feedback precedes contact');
echo "PASS: all 8 workbook outputs, euro recalculation, centre-only rate parser, source failure safety, nonce, feedback persistence, rating validation, throttling and section order.\n";
