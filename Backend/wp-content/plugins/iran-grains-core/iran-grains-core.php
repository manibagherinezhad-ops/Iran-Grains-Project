<?php
/**
 * Plugin Name: Iran Grains Core
 * Description: مدیریت محتوای انجمن غلات ایران، دسترسی‌ها و پیام‌های کاربران.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Text Domain: iran-grains
 */
defined('ABSPATH') || exit;
define('IG_CORE_DIR', __DIR__);
require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/messages.php';
require_once __DIR__ . '/includes/security.php';

register_activation_hook(__FILE__, function () {
    ig_register_content();
    ig_install_access();
    ig_install_tables();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

add_action('init', 'ig_register_content');
add_action('init', function () {
    if (get_option('ig_core_version') !== '0.1.0') {
        ig_install_access();
        ig_install_tables();
        update_option('ig_core_version', '0.1.0', false);
    }
}, 20);

function ig_audit(string $action, string $type = '', int $object_id = 0): void {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ig_audit', [
        'actor_id' => get_current_user_id(), 'action' => sanitize_key($action),
        'object_type' => sanitize_key($type), 'object_id' => $object_id,
        'created_at' => current_time('mysql', true),
    ], ['%d', '%s', '%s', '%d', '%s']);
}
add_action('transition_post_status', function ($new, $old, $post) {
    if (isset(ig_content_types()[$post->post_type]) && $new !== 'auto-draft') {
        ig_audit('status_' . $new, $post->post_type, $post->ID);
    }
}, 10, 3);
add_action('post_updated', function ($id, $after) {
    if (isset(ig_content_types()[$after->post_type]) && !wp_is_post_revision($id)) ig_audit('updated', $after->post_type, $id);
}, 10, 2);
add_action('deleted_post', function ($id, $post) {
    if (isset(ig_content_types()[$post->post_type])) ig_audit('deleted', $post->post_type, $id);
}, 10, 2);
add_action('user_register', fn($id) => ig_audit('user_created', 'user', $id));
add_action('password_reset', fn($user) => ig_audit('password_reset', 'user', $user->ID));

// Content administrators upload only supported images and PDF documents.
add_filter('upload_mimes', function ($mimes) {
    if (current_user_can('manage_options')) return $mimes;
    return array_intersect_key($mimes, array_flip(['jpg|jpeg|jpe', 'png', 'gif', 'webp', 'avif', 'pdf']));
});
add_filter('wp_handle_upload_prefilter', function ($file) {
    if (!current_user_can('manage_options') && $file['size'] > 10 * MB_IN_BYTES) {
        $file['error'] = 'حداکثر حجم فایل برای ادمین محتوا ۱۰ مگابایت است.';
    }
    return $file;
});
