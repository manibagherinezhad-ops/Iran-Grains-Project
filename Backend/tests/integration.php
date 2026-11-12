<?php
// Run with WP-CLI in the disposable LOCAL database only.
if (wp_get_environment_type() !== 'local') throw new RuntimeException('Local tests only.');
function ig_assert($condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
$posts = []; $users = []; $original_user = get_current_user_id();
$tag = 'igtest' . wp_rand(100000,999999);
try {
    $manager = get_user_by('login','ig_manager');
    $author = wp_insert_user(['user_login'=>$tag,'user_email'=>$tag.'@example.invalid','user_pass'=>wp_generate_password(30),'role'=>'ig_content_admin']);
    ig_assert(!is_wp_error($author),'create content administrator'); $users[]=$author;
    ig_assert(user_can($author,'publish_ig_newss'),'default news publishing');
    ig_assert(!user_can($author,'manage_options') && !user_can($author,'create_users') && !user_can($author,'promote_users'),'no settings or user administration');
    ig_assert(!user_can($author,'edit_ig_companys'),'company access denied by default');
    wp_set_current_user($manager->ID);
    $other=wp_insert_post(['post_type'=>'ig_news','post_title'=>$tag.' other','post_status'=>'publish','post_author'=>$manager->ID]); $posts[]=$other;
    $draft=wp_insert_post(['post_type'=>'ig_news','post_title'=>$tag.' draft','post_status'=>'draft','post_author'=>$manager->ID]); $posts[]=$draft;
    $future=wp_insert_post(['post_type'=>'ig_news','post_title'=>$tag.' future','post_status'=>'future','post_date'=>wp_date('Y-m-d H:i:s',time()+DAY_IN_SECONDS),'post_author'=>$manager->ID]); $posts[]=$future;
    $protected=wp_insert_post(['post_type'=>'ig_news','post_title'=>$tag.' protected','post_status'=>'publish','post_password'=>wp_generate_password(20),'post_author'=>$manager->ID]); $posts[]=$protected;
    wp_set_current_user($author);
    ig_assert(!current_user_can('edit_post',$other),'another author cannot be edited');
    $request=new WP_REST_Request('POST','/wp/v2/ig_news');
    $request->set_body_params(['title'=>$tag.' published','content'=>'متن کامل آزمایش','excerpt'=>'خلاصهٔ آزمایش','status'=>'publish']);
    $response=rest_do_request($request);
    ig_assert($response->get_status()===201,'create and publish own news through REST');
    $own=$response->get_data()['id']; $posts[]=$own;
    ig_assert(current_user_can('edit_post',$own),'edit own published news');
    $request=new WP_REST_Request('POST','/wp/v2/ig_news/'.$other); $request->set_param('title','Forbidden');
    ig_assert(rest_do_request($request)->get_status()===403,'REST rejects editing another author');
    $request=new WP_REST_Request('POST','/wp/v2/ig_company'); $request->set_body_params(['title'=>'Forbidden','status'=>'publish']);
    ig_assert(rest_do_request($request)->get_status()===403,'REST rejects another content section');
    $request=new WP_REST_Request('POST','/wp/v2/settings'); $request->set_param('title','Forbidden');
    ig_assert(rest_do_request($request)->get_status()===403,'REST rejects settings modification');
    $request=new WP_REST_Request('POST','/wp/v2/users'); $request->set_body_params(['username'=>'Forbidden','email'=>'forbidden@example.invalid','password'=>wp_generate_password(30),'roles'=>['administrator']]);
    ig_assert(rest_do_request($request)->get_status()===403,'REST rejects user creation and promotion');
    $u=get_userdata($author);$u->add_cap('publish_ig_newss',false); wp_set_current_user(0); wp_set_current_user($author);
    $request=new WP_REST_Request('POST','/wp/v2/ig_news'); $request->set_body_params(['title'=>'Forbidden','status'=>'publish']);
    ig_assert(rest_do_request($request)->get_status()===403,'revoking publish permission is enforced on server');
    $u->add_cap('edit_ig_companys',true);
    ig_assert(user_can($u,'edit_ig_companys'),'granting another section is effective');
    $mimes=get_allowed_mime_types($author);
    ig_assert(!isset($mimes['svg']) && !in_array('application/x-httpd-php',$mimes,true),'unsafe upload types excluded');
    foreach ([11*MB_IN_BYTES,64*MB_IN_BYTES] as $size) {
        $file=apply_filters('wp_handle_upload_prefilter',['name'=>'test.pdf','size'=>$size,'error'=>0]);
        ig_assert($file['error']===0,'content administrator upload allowed through 64 MiB');
    }
    $file=apply_filters('wp_handle_upload_prefilter',['name'=>'test.pdf','size'=>64*MB_IN_BYTES+1,'error'=>0]);
    ig_assert(is_string($file['error']),'content administrator upload over 64 MiB rejected');
    wp_set_current_user($manager->ID);
    $file=apply_filters('wp_handle_upload_prefilter',['name'=>'test.pdf','size'=>64*MB_IN_BYTES+1,'error'=>0]);
    ig_assert(is_string($file['error']),'manager upload over 64 MiB rejected');
    wp_set_current_user(0);
    $ids=wp_list_pluck(ig_public_posts('ig_news'),'ID');
    ig_assert(in_array($own,$ids) && !in_array($draft,$ids) && !in_array($future,$ids) && !in_array($protected,$ids),'public feed excludes drafts, future and protected news');
    $request=new WP_REST_Request('POST','/iran-grains/v1/contact'); $request->set_body_params(['name'=>'User','email'=>'user@example.com','subject'=>$tag,'message'=>'Message']);
    ig_assert(rest_do_request($request)->get_status()===403,'contact rejects missing nonce');
    $request->set_param('_ig_nonce',wp_create_nonce('ig_contact'));
    $request->set_param('email','not-an-email');
    ig_assert(rest_do_request($request)->get_status()===400,'contact validates email');
    $request->set_param('email','user@example.com');
    global $wpdb;
    $rate='ig_contact_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown',wp_salt('nonce')); delete_transient($rate);
    ig_assert(rest_do_request($request)->get_status()===201,'contact stores actual message');
    ig_assert((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ig_messages WHERE subject=%s",$tag))===1,'contact row exists');
    $request->set_param('website','spam');ig_assert(rest_do_request($request)->get_status()===400,'honeypot rejected');$request->set_param('website','');
    set_transient($rate,5,600); ig_assert(rest_do_request($request)->get_status()===429,'contact throttling enforced'); delete_transient($rate);
    $wpdb->delete($wpdb->prefix.'ig_messages',['subject'=>$tag],['%s']);
    ig_assert(ig_date('2026-11-02')==='۱۱ آبان ۱۴۰۵','Persian date conversion');
    ig_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ig_audit")>0,'audit events recorded');
    echo "PASS: publishing, ownership, section grants, revocation, settings/users isolation, uploads, public visibility, contact CSRF/validation/storage/throttling, Persian dates and audit.\n";
} finally {
    wp_set_current_user($original_user);
    foreach ($posts as $id) wp_delete_post($id,true);
    require_once ABSPATH.'wp-admin/includes/user.php';
    foreach ($users as $id) wp_delete_user($id);
}
