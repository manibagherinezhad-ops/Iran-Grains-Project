<?php
defined('ABSPATH') || exit;

function ig_setting_fields(): array {
    return ['contact_address' => 'آدرس', 'contact_phone' => 'تلفن', 'contact_fax' => 'فکس',
        'contact_email' => 'ایمیل', 'association_about' => 'معرفی کوتاه انجمن',
        'rate_usd' => 'دلار آمریکا (ریال)', 'rate_aed' => 'درهم امارات (ریال)',
        'rate_eur' => 'یورو (ریال)', 'rate_rub' => 'روبل روسیه (ریال)', 'rate_cny' => 'یوان چین (ریال)',
        'rates_updated' => 'تاریخ نرخ‌ها (میلادی)', 'rates_source' => 'منبع نرخ ارز',
        'home_intro_prefix' => 'عنوان معرفی خانه: بخش اول', 'home_intro_highlight' => 'عنوان معرفی خانه: واژهٔ طلایی',
        'home_intro_suffix' => 'عنوان معرفی خانه: ادامهٔ خط اول', 'home_intro_line2' => 'عنوان معرفی خانه: خط دوم',
        'home_intro_subtitle' => 'توضیح معرفی خانه', 'home_intro_badge' => 'برچسب معرفی خانه'];
}
add_action('admin_menu', function () {
    add_submenu_page('iran-grains', 'تنظیمات انجمن', 'تنظیمات انجمن', 'manage_options', 'ig-settings', 'ig_settings_page');
    add_submenu_page('iran-grains', 'گزارش فعالیت', 'گزارش فعالیت', 'manage_options', 'ig-audit', 'ig_audit_page');
});
add_action('admin_init', function () {
    register_setting('ig_settings', 'ig_settings', ['type' => 'array', 'sanitize_callback' => function ($input) {
        if (!current_user_can('manage_options')) return get_option('ig_settings', []);
        $clean = [];
        foreach (ig_setting_fields() as $key => $label) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : '';
            if (str_starts_with($key, 'rate_')) $clean[$key] = (string) max(0, (int) str_replace([',', '٬'], '', strtr($value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9'])));
            elseif ($key === 'contact_email') $clean[$key] = sanitize_email($value);
            elseif ($key === 'rates_source') $clean[$key] = esc_url_raw($value, ['https', 'http']);
            elseif ($key === 'rates_updated') $clean[$key] = ig_sanitize_field($value, 'date');
            else $clean[$key] = sanitize_textarea_field($value);
        }
        ig_audit('settings_updated', 'settings'); return $clean;
    }]);
});
function ig_settings_page(): void {
    if (!current_user_can('manage_options')) wp_die('دسترسی مجاز نیست.');
    $values = get_option('ig_settings', []);
    echo '<div class="wrap ig-admin"><h1>تنظیمات انجمن</h1><form method="post" action="options.php">'; settings_fields('ig_settings');
    echo '<table class="form-table">';
    foreach (ig_setting_fields() as $key => $label) {
        echo '<tr><th><label for="ig_' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        $type = str_starts_with($key, 'rate_') ? 'number' : match ($key) {'rates_updated'=>'date','rates_source'=>'url','contact_email'=>'email',default=>''};
        if ($type) echo '<input class="regular-text" dir="ltr" type="' . $type . '" id="ig_' . esc_attr($key) . '" name="ig_settings[' . esc_attr($key) . ']" value="' . esc_attr($values[$key] ?? '') . '"' . ($type==='number'?' min="0" step="1"':'') . '>';
        else echo '<textarea class="large-text" rows="2" id="ig_' . esc_attr($key) . '" name="ig_settings[' . esc_attr($key) . ']">' . esc_textarea($values[$key] ?? '') . '</textarea>';
        echo '</td></tr>';
    }
    echo '</table>'; submit_button('ذخیرهٔ تنظیمات'); echo '</form></div>';
}
function ig_audit_page(): void {
    if (!current_user_can('manage_options')) wp_die('دسترسی مجاز نیست.');
    global $wpdb;
    $page = max(1, absint($_GET['paged'] ?? 1));
    $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'ig_audit ORDER BY id DESC LIMIT 50 OFFSET %d', ($page - 1) * 50));
    echo '<div class="wrap ig-admin"><h1>گزارش فعالیت مدیران</h1><table class="widefat striped"><thead><tr><th>کاربر</th><th>عملیات</th><th>بخش / شناسه</th><th>زمان UTC</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $user = get_userdata((int) $row->actor_id);
        $actions=['login'=>'ورود به پنل','updated'=>'ویرایش محتوا','deleted'=>'حذف محتوا','status_publish'=>'انتشار محتوا','status_draft'=>'ذخیرهٔ پیش‌نویس','status_future'=>'زمان‌بندی انتشار','status_private'=>'انتشار خصوصی','status_trash'=>'انتقال به زباله‌دان','user_created'=>'ایجاد کاربر','permissions_updated'=>'تغییر دسترسی','settings_updated'=>'تغییر تنظیمات','page_copy_updated'=>'ویرایش متن صفحه','message_deleted'=>'حذف پیام','message_read'=>'خواندن پیام','password_reset'=>'بازیابی رمز عبور'];
        $type=ig_content_types()[$row->object_type][0] ?? ['user'=>'کاربر','settings'=>'تنظیمات','message'=>'پیام'][$row->object_type] ?? 'صفحات سایت';
        echo '<tr><td>' . esc_html($user ? $user->display_name : 'سیستم') . '</td><td>' . esc_html($actions[$row->action] ?? 'تغییر محتوا') . '</td><td>' . esc_html($type . ' / ' . $row->object_id) . '</td><td>' . esc_html($row->created_at) . '</td></tr>';
    }
    echo '</tbody></table>'; ig_admin_pagination('ig-audit', $page, count($rows)); echo '</div>';
}
function ig_admin_pagination(string $slug, int $page, int $count): void {
    echo '<p>';
    if ($page > 1) echo '<a class="button" href="' . esc_url(add_query_arg(['page' => $slug, 'paged' => $page - 1], admin_url('admin.php'))) . '">صفحهٔ قبل</a> ';
    if ($count === 50) echo '<a class="button" href="' . esc_url(add_query_arg(['page' => $slug, 'paged' => $page + 1], admin_url('admin.php'))) . '">صفحهٔ بعد</a>';
    echo '</p>';
}
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if (!str_contains($hook, 'ig-') && !str_contains($hook, 'iran-grains') && !isset(ig_content_types()[$screen->post_type ?? ''])) return;
    wp_enqueue_style('ig-admin', plugins_url('assets/admin.css', dirname(__DIR__) . '/iran-grains-core.php'), [], '0.1.0');
    wp_add_inline_style('ig-admin', '@font-face{font-family:IGAdmin;src:url("' . esc_url(get_template_directory_uri() . '/prototype/assets/fonts/YekanBakh-Regular.ttf') . '") format("truetype");font-display:swap}.ig-admin,.ig-admin input,.ig-admin textarea,.ig-admin button,.ig-admin select{font-family:IGAdmin,Tahoma,sans-serif}');
    if (isset(ig_content_types()[$screen->post_type ?? ''])) {
        wp_enqueue_media();
        wp_enqueue_script('ig-admin', plugins_url('assets/admin.js', dirname(__DIR__) . '/iran-grains-core.php'), ['media-views'], '0.1.0', true);
    }
});
