<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/page-copy.php';
add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);
    add_theme_support('automatic-feed-links');
});
function ig_routes(): array {
    return ['index', 'news', 'news-article', 'circulars', 'approved-prices', 'meetings',
        'iran-green', 'members', 'members-all', 'company', 'membership', 'about', 'board', 'contact', 'statute'];
}
add_action('init', function () {
    foreach (ig_routes() as $route) {
        add_rewrite_rule('^' . preg_quote($route, '/') . '\.html$', 'index.php?ig_view=' . $route, 'top');
        if (!in_array($route, ['index', 'news-article', 'company'], true)) add_rewrite_rule('^' . preg_quote($route, '/') . '/?$', 'index.php?ig_view=' . $route, 'top');
    }
});
add_filter('query_vars', fn($vars) => array_merge($vars, ['ig_view']));
add_action('after_switch_theme', 'flush_rewrite_rules');
add_filter('redirect_canonical', fn($url) => get_query_var('ig_view') ? false : $url);
remove_action('wp_head', 'rel_canonical');
add_action('wp_head', function () {
    if (is_singular() && get_post_type() !== 'page') echo '<link rel="canonical" href="' . esc_url(get_permalink()) . '">';
});

function ig_page_url(string $route): string {
    return home_url($route === 'index' ? '/' : '/' . $route . '/');
}
function ig_asset(string $path): string {
    return get_template_directory_uri() . '/prototype/' . ltrim($path, '/');
}
function ig_image(WP_Post $post): string {
    $url = get_the_post_thumbnail_url($post, 'large');
    if ($url) return $url;
    $seed = get_post_meta($post->ID, '_ig_seed_image', true);
    if ($seed && str_starts_with($seed, 'assets/')) return ig_asset($seed);
    return $seed ? esc_url_raw($seed, ['http','https']) : ig_asset('assets/img/hero-wheat-bg.png');
}
function ig_fa($value): string {
    return strtr((string) $value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}
function ig_date_parts(string $date): array {
    [$gy,$gm,$gd] = array_map('intval', explode('-', substr($date, 0, 10)));
    $gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + 365*$gy + intdiv($gy2+3,4) - intdiv($gy2+99,100) + intdiv($gy2+399,400) + $gd + $gdm[$gm-1];
    $jy = -1595 + 33*intdiv($days,12053); $days %= 12053;
    $jy += 4*intdiv($days,1461); $days %= 1461;
    if ($days > 365) {$jy += intdiv($days-1,365); $days = ($days-1)%365;}
    $jm = $days < 186 ? 1+intdiv($days,31) : 7+intdiv($days-186,30);
    $jd = 1+($days < 186 ? $days%31 : ($days-186)%30);
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    return [$jy, $months[$jm-1], $jd];
}
function ig_date(string $date): string {
    [$year, $month, $day] = ig_date_parts($date);
    return ig_fa($day) . ' ' . $month . ' ' . ig_fa($year);
}
