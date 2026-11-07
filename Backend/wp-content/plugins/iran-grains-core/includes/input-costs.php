<?php
defined('ABSPATH') || exit;
function ig_input_models(): array {
    static $models; return $models ??= json_decode(file_get_contents(IG_CORE_DIR.'/data/input-costs.json'),true)['models'];
}
function ig_input_costs(float $euro): array {
    if ($euro <= 0) return [];
    return array_map(fn($m)=>['name'=>$m['name'],'icon'=>$m['icon'],'price'=>(int) round($m['slope']*$euro+$m['intercept'])],ig_input_models());
}
function ig_input_price_data(): array {
    $settings=get_option('ig_settings',[]);$quote=get_option('ig_euro_quote',[]);
    $euro=(int)($settings['rate_eur']??0);
    return ['euro'=>$euro,'date'=>($quote['value']??0)===$euro?($quote['date']??''):($settings['rates_updated']??''),'items'=>ig_input_costs($euro),'automatic'=>get_option('ig_euro_auto',true),'status'=>get_option('ig_euro_status','هنوز دریافت خودکار انجام نشده است.')];
}
add_action('init',function(){if(!wp_next_scheduled('ig_refresh_euro'))wp_schedule_event(time()+60,'hourly','ig_refresh_euro');});
add_action('ig_refresh_euro','ig_refresh_euro');
// Only the explicitly identified EUR exchange-centre instrument is accepted.
function ig_refresh_euro(bool $force=false) {
    if(!get_option('ig_euro_auto',true))return false;
    $today=wp_date('Y-m-d',null,new DateTimeZone('Asia/Tehran'));
    if(!$force && (int)wp_date('G',null,new DateTimeZone('Asia/Tehran'))<10)return false;
    if(!$force && get_option('ig_euro_checked')===$today)return true;
    $response=wp_safe_remote_get(ig_euro_source(),['timeout'=>18,'redirection'=>2,'limit_response_size'=>1500000]);
    if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){update_option('ig_euro_status','دریافت نرخ مرکز مبادله ناموفق بود؛ آخرین نرخ معتبر حفظ شده است.',false);return false;}
    $quote=ig_parse_center_euro(wp_remote_retrieve_body($response));
    if(!$quote){update_option('ig_euro_status','نرخ قابل تأیید مرکز مبادله در منبع پیدا نشد؛ آخرین نرخ معتبر حفظ شده است.',false);return false;}
    $settings=get_option('ig_settings',[]);$settings['rate_eur']=(string)$quote['value'];update_option('ig_settings',$settings,false);
    update_option('ig_euro_quote',$quote+['checked_at'=>current_time('mysql',true),'source'=>ig_euro_source()],false);
    update_option('ig_euro_checked',$today,false);update_option('ig_euro_status','آخرین دریافت موفق: '.$today.'؛ تاریخ نرخ: '.$quote['date'],false);return true;
}
function ig_euro_source(): string {return 'https://www.tgju.org/'.rawurlencode('مرکز-مبادله-ارز-و-طلای-ایران');}
add_action('rest_api_init',function(){register_rest_route('iran-grains/v1','/input-prices',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>fn()=>new WP_REST_Response(ig_input_price_data())]);});
add_action('admin_menu',fn()=>add_submenu_page('iran-grains','قیمت نهاده‌ها','قیمت نهاده‌ها','manage_options','ig-input-costs','ig_input_admin'));
function ig_input_admin(): void {
    if(!current_user_can('manage_options'))wp_die('دسترسی مجاز نیست.');
    if($_SERVER['REQUEST_METHOD']==='POST'){check_admin_referer('ig_input_settings');update_option('ig_euro_auto',isset($_POST['automatic']),false);if(isset($_POST['refresh']))ig_refresh_euro(true);}
    $data=ig_input_price_data();echo '<div class="wrap"><h1>قیمت تمام شده نهاده‌ها بر مبنای نرخ ارز</h1><p>خروجی نهایی فایل اکسل، شامل ۱۲٪ سود، به ریال برای هر کیلوگرم. سایر مفروضات فایل از جمله نرخ ETS ثابت مانده‌اند.</p><form method="post">';wp_nonce_field('ig_input_settings');
    echo '<label><input type="checkbox" name="automatic" '.checked(get_option('ig_euro_auto',true),true,false).'> دریافت روزانهٔ نرخ یورو مرکز مبادله (منبع ثانویه: شبکه اطلاع‌رسانی طلا و ارز)</label><p>'.esc_html($data['status']).'</p><p>یوروی مبنا: '.esc_html(number_format($data['euro'])).' ریال. نرخ دستی از اطلاعات انجمن ← نرخ‌های ارز قابل تغییر است.</p><button class="button button-primary">ذخیره</button> <button class="button" name="refresh" value="1">دریافت نرخ اکنون</button></form><table class="widefat striped"><tr><th>کالا</th><th>قیمت با ۱۲٪ سود (ریال/کیلوگرم)</th></tr>';
    foreach($data['items']as$item)echo '<tr><td>'.esc_html($item['name']).'</td><td>'.esc_html(number_format($item['price'])).'</td></tr>';echo '</table><p>زمان‌بندی وردپرس با بازدید سایت اجرا می‌شود. برای اجرای دقیق روزانه روی هاست، زمان‌بند سرور باید wp-cron.php را فراخوانی کند. در قطعی منبع، نرخ قبلی با تاریخ خودش حفظ می‌شود.</p></div>';
}
function ig_parse_center_euro(string $html): ?array {
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
    $xp=new DOMXPath($dom);$row=$xp->query('//tr[@data-market-row="ice_transfer_eur_sell"]')->item(0);if(!$row)return null;
    $price=(int)str_replace([',','٬'],'',$row->getAttribute('data-price'));
    if($price<10000||$price>100000000)return null;
    $cells=$xp->query('./td',$row);$time=trim($cells->item(4)?->textContent??'');if($time==='')return null;
    $latin=strtr($time,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
    return ['value'=>$price,'date'=>preg_match('/^\d{2}:\d{2}:\d{2}$/',$latin)?wp_date('Y-m-d',null,new DateTimeZone('Asia/Tehran')):$time,'source_time'=>$time];
}
