<?php
// Explicit local import. Existing records and manager edits are preserved.
if (wp_get_environment_type() !== 'local' || !current_user_can('manage_options')) {
    throw new RuntimeException('A local manager account is required.');
}
$records = json_decode(file_get_contents(get_template_directory() . '/prototype/assets/data/members.json'), true, 512, JSON_THROW_ON_ERROR);
$taxonomy = ig_taxonomy('ig_company');
foreach (['import'=>'واردات','production'=>'تولید','grains'=>'غلات','feed'=>'نهاده‌های دامی','oils'=>'دانه‌های روغنی','other'=>'سایر'] as $slug=>$label) {
    if (!term_exists($slug, $taxonomy)) {
        $term = wp_insert_term($label, $taxonomy, ['slug'=>$slug]);
        if (is_wp_error($term)) throw new RuntimeException($term->get_error_message());
    }
}
$last = get_posts(['post_type'=>'ig_company','post_status'=>array_keys(get_post_stati()),'numberposts'=>1,'orderby'=>'menu_order','order'=>'DESC']);
$order = $last ? $last[0]->menu_order + 1 : 0;
$added = 0; $skipped = 0;
foreach ($records as $item) {
    if (empty($item['name']) || empty($item['source']) || !str_starts_with($item['source'], 'https://iran-grains.com/')) continue;
    $base = ['post_type'=>'ig_company','post_status'=>array_keys(get_post_stati()),'fields'=>'ids','numberposts'=>1];
    $existing = get_posts($base + ['meta_key'=>'_ig_source','meta_value'=>$item['source']]);
    if (!$existing) $existing = get_posts($base + ['meta_key'=>'_ig_seed_key','meta_value'=>'companies-'.$item['id']]);
    if (!$existing) $existing = get_posts($base + ['title'=>$item['name']]);
    if ($existing) {$skipped++; continue;}
    $id = wp_insert_post(wp_slash([
        'post_type'=>'ig_company','post_status'=>'publish','post_author'=>get_current_user_id(),
        'post_title'=>sanitize_text_field($item['name']), 'post_excerpt'=>sanitize_textarea_field($item['description']),
        'post_content'=>sanitize_textarea_field(($item['fullActivity'] ?? '') ?: $item['description']), 'menu_order'=>$order++,
    ]), true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    update_post_meta($id, '_ig_seed_key', 'companies-'.absint($item['id']));
    update_post_meta($id, '_ig_seed_image', esc_url_raw($item['logo']));
    foreach (ig_fields('ig_company') as $key=>[, $kind]) update_post_meta($id, '_ig_'.$key, ig_sanitize_field($item[$key] ?? '', $kind));
    $terms = wp_set_object_terms($id, $item['categories'], $taxonomy);
    if (is_wp_error($terms)) throw new RuntimeException($terms->get_error_message());
    $added++;
}
// Register the new archive URL for existing local installations.
flush_rewrite_rules(false);
echo "Company import complete: $added added, $skipped existing records preserved.\n";
