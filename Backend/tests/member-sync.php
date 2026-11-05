<?php
// Local regression: activity edits reach both member listings and private previews.
if (wp_get_environment_type() !== 'local' || !current_user_can('manage_options')) throw new RuntimeException('Local manager required.');
function ig_member_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException('FAIL: '.$message); }
$ids = [];
try {
    $id = wp_insert_post(['post_type'=>'ig_company','post_title'=>'آزمون همگام‌سازی شرکت '.wp_rand(), 'post_status'=>'publish',
        'post_excerpt'=>'خلاصهٔ قدیمی','post_content'=>'فعالیت قدیمی'], true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    $ids[] = $id;
    wp_update_post(['ID'=>$id,'post_content'=>'<p>فعالیت تازه در واردات غلات.</p><p>توزیع نهاده‌های دامی.</p>']);
    $post = get_post($id);
    ig_member_assert($post->post_excerpt === 'فعالیت تازه در واردات غلات. توزیع نهاده‌های دامی.', 'body edit refreshes stale card summary with paragraph spacing');
    $clean = ig_copy_content_clean(['post_content'=>'فعالیت تازه‌تر از مرکز متن‌ها','post_excerpt'=>$post->post_excerpt,'meta_activity'=>'فعالیت کوتاه تازه'], $post);
    ig_member_assert($clean['post_excerpt'] === 'فعالیت تازه‌تر از مرکز متن‌ها', 'private preview uses the same summary as saving');
    ig_copy_content_write($clean, $post);
    $record = ig_record(get_post($id));
    ig_member_assert($record['description'] === $clean['post_excerpt'] && $record['activity'] === 'فعالیت کوتاه تازه', 'center save reaches public company record');
    foreach ([false,true] as $featured) {
        $html = ig_company_card_html($record,$featured,true);
        ig_member_assert(str_contains($html,$clean['post_excerpt']) && str_contains($html,'فعالیت کوتاه تازه'), 'normal and featured server cards use updated text');
    }
    $listing = array_values(array_filter(ig_company_listing_feed(),fn($item)=>$item['id']===$id))[0];
    ig_member_assert($listing['description'] === $record['description'] && $listing['activity'] === $record['activity'], 'JavaScript card feed matches server cards');
    wp_update_post(['ID'=>$id,'post_content'=>'متن کامل ویرایش‌شده','post_excerpt'=>'خلاصهٔ دلخواه مدیر']);
    ig_member_assert(get_post($id)->post_excerpt === 'خلاصهٔ دلخواه مدیر', 'explicit summary wins when both fields change');
    wp_update_post(['ID'=>$id,'post_excerpt'=>'خلاصهٔ مستقل کارت']);
    ig_member_assert(ig_record(get_post($id))['description'] === 'خلاصهٔ مستقل کارت', 'summary-only edit updates cards');
    wp_update_post(['ID'=>$id,'post_content'=>'متن جایگزین برای خلاصهٔ خالی','post_excerpt'=>'']);
    ig_member_assert(get_post($id)->post_excerpt === '' && ig_record(get_post($id))['description'] === 'متن جایگزین برای خلاصهٔ خالی', 'clearing summary keeps body fallback');
    $news = wp_insert_post(['post_type'=>'ig_news','post_title'=>'آزمون عدم تغییر خبر','post_status'=>'draft','post_excerpt'=>'خلاصهٔ خبر','post_content'=>'متن قدیم خبر']);
    $ids[] = $news;
    wp_update_post(['ID'=>$news,'post_content'=>'متن جدید خبر']);
    ig_member_assert(get_post($news)->post_excerpt === 'خلاصهٔ خبر', 'other post types retain their summary behavior');
    $fields = ig_copy_catalog('pages','index');
    $groups = array_unique(array_column(array_filter($fields,fn($field)=>str_starts_with($field['section'],'خدمات انجمن · توضیحات ')),'section'));
    ig_member_assert(count($groups) === 6, 'six service detail groups are editable in the text center');
    echo "PASS: company body/summary/activity synchronization, explicit summary priority, preview parity, featured/normal cards, shared listing feed, and six editable services.\n";
} finally {
    foreach ($ids as $id) wp_delete_post($id,true);
}
