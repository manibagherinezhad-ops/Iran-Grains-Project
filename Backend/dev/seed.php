<?php
// Explicit local-only demo import. This file is never included in release zips.
if (wp_get_environment_type() !== 'local') throw new RuntimeException('Demo import requires a local environment.');
$data = json_decode(file_get_contents('/opt/ig/seed.json'), true, 512, JSON_THROW_ON_ERROR);
$labels = [
    'ig_news' => ['association'=>'اخبار انجمن','domestic'=>'تولید داخلی','world'=>'بازار جهانی','market'=>'قیمت‌ها و بازار','official-domestic'=>'اخبار داخلی','official-world'=>'اخبار جهان'],
    'ig_circular' => ['agriculture'=>'گندم و کشاورزی','customs'=>'گمرک و تجارت','market'=>'تنظیم بازار','systems'=>'سامانه‌ها'],
    'ig_price' => ['ceilings'=>'سقف ارزی','oils'=>'روغن و دانه‌های روغنی','customs'=>'نرخ مبنای گمرک','consumer'=>'قیمت مصرف‌کننده'],
    'ig_company' => ['import'=>'واردات','production'=>'تولید','grains'=>'غلات','feed'=>'نهاده‌های دامی','oils'=>'دانه‌های روغنی','other'=>'سایر'],
    'ig_event' => ['meetings'=>'جلسات','conference'=>'همایش‌ها'],
];
foreach ($labels as $type => $terms) foreach ($terms as $slug => $label) {
    if (!term_exists($slug, ig_taxonomy($type))) wp_insert_term($label, ig_taxonomy($type), ['slug' => $slug]);
}
foreach (['news'=>'ig_news','circulars'=>'ig_circular','prices'=>'ig_price','companies'=>'ig_company','events'=>'ig_event','board'=>'ig_board'] as $key=>$type) {
    $records = $type === 'ig_news' ? array_reverse($data[$key]) : $data[$key];
    foreach ($records as $i=>$record) {
        $seed_key = $key . '-' . ($record['id'] ?? $i);
        if (get_posts(['post_type'=>$type,'post_status'=>'any','meta_key'=>'_ig_seed_key','meta_value'=>$seed_key,'fields'=>'ids'])) continue;
        $content = $record['content'] ?? $record['fullActivity'] ?? $record['description'] ?? '';
        if (is_array($content)) $content = implode("\n\n",$content);
        $date = ($record['date'] ?? '2026-08-01') . ' 12:00:00';
        $id = wp_insert_post(['post_type'=>$type,'post_title'=>$record['title'] ?? $record['name'],
            'post_content'=>$content,'post_excerpt'=>$record['description'] ?? '', 'post_status'=>'publish',
            'post_author'=>get_current_user_id(),'post_date'=>$date,'post_date_gmt'=>get_gmt_from_date($date),
            'menu_order'=>$i], true);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        update_post_meta($id,'_ig_seed_key',$seed_key);
        update_post_meta($id,'_ig_seed_image',$record['logo'] ?? $record['image'] ?? 'assets/img/hero-wheat-bg.png');
        foreach (ig_fields($type) as $field=>[, $kind]) {
            $value = match($field) {'event_date'=>$record['date'] ?? '', 'event_time'=>$record['time'] ?? '', 'event_status'=>$record['status'] ?? 'upcoming', default=>$record[$field] ?? ''};
            update_post_meta($id,'_ig_'.$field,ig_sanitize_field($value,$kind));
        }
        if ($type !== 'ig_board') {
            $slugs = $type === 'ig_company' ? $record['categories'] : [$record['category'] ?? 'meetings'];
            wp_set_object_terms($id,$slugs,ig_taxonomy($type));
        }
    }
}
if (!get_option('ig_settings')) update_option('ig_settings', [
    'contact_address'=>'تهران، خیابان سنایی، کوچه ۲۱، پلاک ۴، طبقه ۵، واحد ۱۹',
    'contact_phone'=>'۰۲۱ - ۸۶۰۷۴۵۸۷ / ۰۲۱ - ۸۸۳۲۶۷۹۵','contact_fax'=>'۰۲۱ - ۸۶۰۷۴۲۶۹',
    'contact_email'=>'info@iran-grains.com','rate_usd'=>'1879158','rate_aed'=>'511684',
    'rate_eur'=>'2097050','rate_rub'=>'23327','rate_cny'=>'282405','rates_updated'=>'2026-11-02',
    'rates_source'=>'https://www.ice.ir/', 'association_about'=>'انجمن غلات ایران، مرجع تخصصی غلات و دانه‌های روغنی کشور با هدف توسعهٔ پایدار صنعت و تأمین امنیت غذایی.',
]);
echo "Development demo content imported.\n";
