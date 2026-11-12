<?php
defined('ABSPATH') || exit;
if (!function_exists('ig_content_types')) {
    status_header(503);
    wp_die('افزونهٔ Iran Grains Core باید فعال شود.', 'آماده‌سازی سایت', ['response' => 503]);
}
[$route, $post] = ig_resolve_view();
if ($route === '404') {
    status_header(404); nocache_headers();header('X-Robots-Tag: noindex, nofollow',true);
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>' . esc_html(ig_ui('صفحه یافت نشد')) . '</title><h1>' . esc_html(ig_ui('صفحهٔ موردنظر پیدا نشد.')) . '</h1><a href="' . esc_url(home_url('/')) . '">' . esc_html(ig_ui('بازگشت به خانه')) . '</a></html>';
    return;
}
status_header(200);
global $wp_query;
$wp_query->is_404 = false;
if (is_preview()) nocache_headers();
ig_render($route, $post);
