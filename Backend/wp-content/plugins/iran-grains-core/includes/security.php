<?php
defined('ABSPATH') || exit;
function ig_login_rate_key(string $username): string {
    return 'ig_login_' . hash_hmac('sha256', strtolower($username) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('auth'));
}
add_filter('authenticate', function ($user, $username) {
    if ($username && (int) get_transient(ig_login_rate_key($username)) >= 6) return new WP_Error('ig_login_limit', 'تلاش‌های ورود زیاد است. ۱۵ دقیقه بعد دوباره تلاش کنید.');
    return $user;
}, 99, 2);
add_action('wp_login_failed', function ($username) {
    $key = ig_login_rate_key($username);
    set_transient($key, min(6, 1 + (int) get_transient($key)), 15 * MINUTE_IN_SECONDS);
});
add_action('wp_login', function ($username, $user) {
    delete_transient(ig_login_rate_key($username)); ig_audit('login', 'user', $user->ID);
}, 10, 2);
add_action('login_enqueue_scripts', function () {
    $logo = get_template_directory_uri() . '/prototype/assets/img/logo.png';
    wp_add_inline_style('login', 'body.login{background:#f8f5ec;direction:rtl}body.login #loginform{border:1px solid #dbe3da;border-radius:16px;box-shadow:0 12px 35px #17443412}body.login .wp-core-ui .button-primary{background:#174434;border-color:#174434}body.login .button-primary:hover{background:#efbd59!important;border-color:#efbd59!important;color:#102820!important}body.login h1 a{background-image:url("' . esc_url($logo) . '");background-size:contain;width:110px;height:120px}');
});
add_filter('login_headerurl', fn() => home_url('/'));
add_filter('login_headertext', fn() => 'انجمن غلات ایران');
