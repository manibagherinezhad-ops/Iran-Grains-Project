<?php
// Explicit import into the LOCAL news section, never an import on a public GET.
if (wp_get_environment_type() !== 'local' || !current_user_can('manage_options')) {
    throw new RuntimeException('A local manager account is required.');
}
$path = get_template_directory() . '/prototype/assets/data/news.json';
$records = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$taxonomy = ig_taxonomy('ig_news');
foreach (['official-domestic' => 'اخبار داخلی', 'official-world' => 'اخبار جهان'] as $slug => $label) {
    if (!term_exists($slug, $taxonomy)) {
        $term = wp_insert_term($label, $taxonomy, ['slug' => $slug]);
        if (is_wp_error($term)) throw new RuntimeException($term->get_error_message());
    }
}
$imported = 0; $skipped = 0;
// Same-day stories retain the source archive's order through the ID tie-break.
foreach (array_reverse($records) as $item) {
    if (empty($item['source']) || !str_starts_with($item['source'], 'https://iran-grains.com/')) continue;
    $base = ['post_type' => 'ig_news', 'post_status' => array_keys(get_post_stati()), 'fields' => 'ids', 'numberposts' => 1];
    $existing = get_posts($base + ['meta_key' => '_ig_source', 'meta_value' => $item['source']]);
    if (!$existing) $existing = get_posts($base + ['meta_key' => '_ig_seed_key', 'meta_value' => 'news-' . $item['id']]);
    if (!$existing) $existing = get_posts($base + ['title' => $item['title']]);
    if ($existing) {$skipped++; continue;}
    $date = $item['date'] . ' 00:00:00';
    $id = wp_insert_post([
        'post_type' => 'ig_news', 'post_status' => 'publish', 'post_author' => get_current_user_id(),
        'post_title' => sanitize_text_field($item['title']), 'post_excerpt' => sanitize_textarea_field($item['description']),
        'post_content' => implode("\n\n", array_map('sanitize_textarea_field', $item['content'])),
        'post_date' => $date, 'post_date_gmt' => get_gmt_from_date($date),
    ], true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    update_post_meta($id, '_ig_source', esc_url_raw($item['source']));
    update_post_meta($id, '_ig_seed_key', 'news-' . absint($item['id']));
    update_post_meta($id, '_ig_seed_image', esc_url_raw($item['image']));
    $terms = wp_set_object_terms($id, $item['category'], $taxonomy);
    if (is_wp_error($terms)) throw new RuntimeException($terms->get_error_message());
    $imported++;
}
echo "News import complete: $imported added, $skipped existing stories preserved.\n";
