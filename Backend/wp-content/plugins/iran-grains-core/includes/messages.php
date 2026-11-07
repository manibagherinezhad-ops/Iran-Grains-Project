<?php
defined('ABSPATH') || exit;
function ig_install_tables(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$wpdb->prefix}ig_messages (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        sender_name varchar(190) NOT NULL,
        sender_email varchar(190) NOT NULL,
        sender_phone varchar(80) NOT NULL DEFAULT '',
        subject varchar(190) NOT NULL,
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status_date (status, created_at)
    ) $charset;");
    dbDelta("CREATE TABLE {$wpdb->prefix}ig_audit (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
        action varchar(80) NOT NULL,
        object_type varchar(40) NOT NULL,
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY actor_date (actor_id, created_at)
    ) $charset;");
}
add_action('rest_api_init', function () {
    register_rest_route('iran-grains/v1', '/contact', [
        'methods' => 'POST', 'permission_callback' => function (WP_REST_Request $r) {
            return wp_verify_nonce((string) $r->get_param('_ig_nonce'), 'ig_contact') ? true : new WP_Error('ig_nonce', 'صفحه را تازه‌سازی و دوباره تلاش کنید.', ['status' => 403]);
        },
        'callback' => 'ig_receive_contact',
    ]);
});
function ig_receive_contact(WP_REST_Request $request) {
    if (strlen((string) $request->get_body()) > 20000) return new WP_Error('ig_size', 'پیام بیش از حد طولانی است.', ['status' => 413]);
    if ($request->get_param('website')) return new WP_Error('ig_spam', 'ارسال پیام انجام نشد.', ['status' => 400]);
    $name = sanitize_text_field((string) $request->get_param('name'));
    $email = sanitize_email((string) $request->get_param('email'));
    $subject = sanitize_text_field((string) $request->get_param('subject'));
    $phone = mb_substr(sanitize_text_field((string) $request->get_param('phone')), 0, 80);
    $message = sanitize_textarea_field((string) $request->get_param('message'));
    if (!$name || !is_email($email) || !$subject || !$message || strlen($message) > 12000 || strlen($name) > 190 || strlen($subject) > 190) return new WP_Error('ig_fields', 'نام، ایمیل معتبر، موضوع و متن پیام را وارد کنید.', ['status' => 400]);
    // Use the direct peer address; client-supplied forwarded headers are untrusted.
    $key = 'ig_contact_' . hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', wp_salt('nonce'));
    $count = (int) get_transient($key);
    if ($count >= 5) return new WP_Error('ig_rate', 'تعداد پیام‌ها زیاد است. چند دقیقه بعد تلاش کنید.', ['status' => 429]);
    set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
    global $wpdb;
    $saved = $wpdb->insert($wpdb->prefix . 'ig_messages', ['sender_name' => $name, 'sender_email' => $email, 'sender_phone' => $phone, 'subject' => $subject, 'message' => $message, 'created_at' => current_time('mysql', true)], ['%s','%s','%s','%s','%s','%s']);
    if (!$saved) return new WP_Error('ig_storage', 'ذخیرهٔ پیام انجام نشد. دوباره تلاش کنید.', ['status' => 500]);
    return new WP_REST_Response(['message' => 'پیام شما ثبت شد و از پنل انجمن قابل پیگیری است.'], 201);
}
add_action('admin_menu', fn() => add_submenu_page('iran-grains', 'پیام‌های کاربران', 'پیام‌های کاربران', 'ig_manage_messages', 'ig-messages', 'ig_messages_page'));
function ig_messages_page(): void {
    if (!current_user_can('ig_manage_messages')) wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    global $wpdb;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('ig_message_action');
        $id = absint($_POST['message_id'] ?? 0);
        if (($_POST['ig_action'] ?? '') === 'delete') $wpdb->delete($wpdb->prefix . 'ig_messages', ['id' => $id], ['%d']);
        else $wpdb->update($wpdb->prefix . 'ig_messages', ['status' => 'read'], ['id' => $id], ['%s'], ['%d']);
        ig_audit('message_' . (($_POST['ig_action'] ?? '') === 'delete' ? 'deleted' : 'read'), 'message', $id);
    }
    $page = max(1, absint($_GET['paged'] ?? 1));
    $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'ig_messages ORDER BY id DESC LIMIT 50 OFFSET %d', ($page - 1) * 50));
    echo '<div class="wrap ig-admin"><h1>پیام‌های کاربران</h1>';
    foreach ($rows as $row) {
        echo '<article class="ig-admin-message"><h2>' . esc_html($row->subject) . '</h2><p>' . esc_html($row->sender_name . ' — ' . $row->sender_email . ' — ' . $row->sender_phone . ' — ' . $row->created_at . ' UTC') . '</p><p>' . nl2br(esc_html($row->message)) . '</p><form method="post">';
        wp_nonce_field('ig_message_action'); echo '<input type="hidden" name="message_id" value="' . absint($row->id) . '"><button class="button" name="ig_action" value="read">علامت‌گذاری به‌عنوان خوانده‌شده</button> <button class="button" name="ig_action" value="delete">حذف پیام</button><span> ' . esc_html($row->status === 'new' ? 'جدید' : 'خوانده‌شده') . '</span></form></article>';
    }
    if (!$rows) echo '<p>هنوز پیامی ثبت نشده است.</p>';
    ig_admin_pagination('ig-messages', $page, count($rows)); echo '</div>';
}
