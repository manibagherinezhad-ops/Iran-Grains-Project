<?php
defined('ABSPATH') || exit;
function ig_public_posts(string $type): array {
    return get_posts(['post_type' => $type, 'post_status' => 'publish', 'has_password' => false, 'numberposts' => -1,
        'orderby' => in_array($type, ['ig_board', 'ig_company'], true) ? ['menu_order' => 'ASC', 'ID' => 'ASC'] : ['date' => 'DESC', 'ID' => 'DESC'],
        'date_query' => [['before' => current_time('mysql'), 'inclusive' => true]], 'suppress_filters' => false]);
}
function ig_topic_labels(string $type): array {
    $terms = get_terms(['taxonomy' => $type . '_topic', 'hide_empty' => false]);
    if (is_wp_error($terms)) return [];
    $labels = [];
    foreach ($terms as $term) $labels[$term->slug] = $term->name;
    return $labels;
}
function ig_topic_slugs(WP_Post $post): array {
    $terms = get_the_terms($post, $post->post_type . '_topic');
    return $terms && !is_wp_error($terms) ? array_values(wp_list_pluck($terms, 'slug')) : [];
}
function ig_record(WP_Post $post): array {
    $meta = fn($key) => (string) get_post_meta($post->ID, '_ig_' . $key, true);
    $topics = ig_topic_slugs($post);
    $record = ['id' => $post->ID, 'title' => wp_strip_all_tags($post->post_title),
        'description' => wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 40, '…')),
        'image' => ig_image($post), 'date' => substr($post->post_date, 0, 10),
        'publishedAt' => $post->post_date_gmt, 'category' => $topics[0] ?? '', 'url' => get_permalink($post),
        'source' => $meta('source'), 'attachment' => wp_get_attachment_url((int) $meta('attachment_id')) ?: '',
    ];
    if ($post->post_type === 'ig_company') {
        $record += ['name' => $record['title'], 'activity' => $meta('activity'), 'categories' => $topics,
            'logo' => $record['image'], 'fullActivity' => wp_strip_all_tags($post->post_content),
            'manager' => $meta('manager'), 'phone' => $meta('phone'), 'fax' => $meta('fax'),
            'email' => $meta('email'), 'website' => $meta('website'), 'address' => $meta('address')];
    }
    return $record;
}
function ig_feed(string $type): array { return array_map('ig_record', ig_public_posts($type)); }


function ig_resolve_view(): array {
    $route = (string) get_query_var('ig_view'); $post = null;
    if (is_singular() && in_array(get_post_type(), ['ig_news', 'ig_company', 'ig_circular', 'ig_price', 'ig_event', 'ig_board'], true)) {
        $post = get_post();
        $route = $post->post_type === 'ig_company' ? 'company' : 'news-article';
    } elseif (in_array($route, ['company', 'news-article'], true)) {
        $post = get_post(absint($_GET['id'] ?? 0));
        $expected = $route === 'company' ? 'ig_company' : 'ig_news';
        if (!$post || $post->post_type !== $expected) $post = null;
    }
    $authorized_preview = $post && is_preview() && is_user_logged_in() && current_user_can('edit_post', $post->ID);
    if ($post && !$authorized_preview && ($post->post_status !== 'publish' || $post->post_date > current_time('mysql'))) $post = null;
    if ($post && !$authorized_preview && post_password_required($post)) $post = null;
    if ($route === '' && is_front_page()) $route = 'index';
    if (!in_array($route, ig_routes(), true) || (in_array($route, ['company', 'news-article'], true) && !$post)) return ['404', null];
    return [$route, $post];
}
