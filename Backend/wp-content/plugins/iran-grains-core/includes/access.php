<?php
defined('ABSPATH') || exit;

function ig_cap_groups(string $type): array {
    $plural = $type . 's';
    return [
        'edit' => ['edit_' . $plural, 'edit_published_' . $plural],
        'publish' => ['publish_' . $plural],
        'others' => ['edit_others_' . $plural, 'read_private_' . $plural, 'edit_private_' . $plural],
        'delete' => ['delete_' . $plural, 'delete_published_' . $plural],
        'delete_others' => ['delete_others_' . $plural, 'delete_private_' . $plural],
        'terms' => ['manage_' . $type . '_terms'],
    ];
}
function ig_all_content_caps(): array {
    $caps = [];
    foreach (ig_content_types() as $type => $labels) foreach (ig_cap_groups($type) as $group) $caps = array_merge($caps, $group);
    return array_unique($caps);
}
function ig_install_access(): void {
    $admin = get_role('administrator');
    foreach (array_merge(ig_all_content_caps(), ['ig_manage_messages']) as $cap) $admin?->add_cap($cap);
    if (!get_role('ig_content_admin')) {
        $caps = ['read' => true, 'upload_files' => true];
        foreach (['edit', 'publish'] as $group) foreach (ig_cap_groups('ig_news')[$group] as $cap) $caps[$cap] = true;
        add_role('ig_content_admin', 'ادمین محتوا', $caps);
    }
}

// A content administrator can work with their own media without gaining access
// to standard WordPress posts or another administrator's uploads.
add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
    if (!in_array($cap, ['edit_post', 'delete_post'], true) || empty($args[0])) return $caps;
    $user = get_userdata($user_id);
    $post = get_post((int) $args[0]);
    if (!$user || !$post || $post->post_type !== 'attachment' || user_can($user, 'manage_options')) return $caps;
    return (int) $post->post_author === (int) $user_id ? ['upload_files'] : ['do_not_allow'];
}, 20, 4);
add_filter('ajax_query_attachments_args', function ($args) {
    if (!current_user_can('manage_options')) $args['author'] = get_current_user_id();
    return $args;
});
add_filter('rest_attachment_query', function ($args) {
    if (!current_user_can('manage_options')) $args['author'] = get_current_user_id();
    return $args;
});

add_action('admin_menu', function () {
    add_menu_page('پنل انجمن غلات ایران', 'پنل انجمن', 'read', 'iran-grains', 'ig_dashboard', 'dashicons-chart-area', 2);
    add_submenu_page('iran-grains', 'دسترسی ادمین‌ها', 'دسترسی ادمین‌ها', 'manage_options', 'ig-access', 'ig_access_page');
});

function ig_access_page(): void {
    if (!current_user_can('manage_options')) wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    $id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    $target = $id ? get_userdata($id) : false;
    if ($target && (!in_array('ig_content_admin', $target->roles, true) || user_can($target, 'manage_options'))) $target = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('ig_access');
        $target = get_userdata(absint($_POST['user_id'] ?? 0));
        if (!$target || !in_array('ig_content_admin', $target->roles, true) || user_can($target, 'manage_options')) wp_die('حساب قابل ویرایش نیست.', '', ['response' => 403]);
        $input = isset($_POST['ig_permissions']) && is_array($_POST['ig_permissions']) ? $_POST['ig_permissions'] : [];
        foreach (ig_all_content_caps() as $cap) $target->add_cap($cap, false);
        foreach (ig_content_types() as $type => $labels) {
            foreach (ig_cap_groups($type) as $group => $caps) {
                if (empty($input[$type][$group])) continue;
                foreach ($caps as $cap) $target->add_cap($cap, true);
            }
        }
        $target->add_cap('ig_manage_messages', !empty($_POST['ig_messages']));
        ig_audit('permissions_updated', 'user', $target->ID);
        echo '<div class="notice notice-success"><p>دسترسی‌ها ذخیره شدند.</p></div>';
    }
    echo '<div class="wrap ig-admin"><h1>دسترسی ادمین‌ها</h1><p>کاربر جدید را با نقش «ادمین محتوا» بسازید، سپس مجوزهای او را از این صفحه تنظیم کنید.</p><a class="button" href="' . esc_url(admin_url('user-new.php')) . '">افزودن کاربر</a><hr><form method="get"><input type="hidden" name="page" value="ig-access"><label>انتخاب ادمین <select name="user_id"><option value="">انتخاب کنید</option>';
    foreach (get_users(['role' => 'ig_content_admin', 'number' => 1000]) as $u) echo '<option value="' . $u->ID . '" ' . selected(($target ? $target->ID : 0), $u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    echo '</select></label> <button class="button">نمایش دسترسی‌ها</button></form>';
    if ($target) {
        echo '<form method="post">'; wp_nonce_field('ig_access');
        echo '<input type="hidden" name="user_id" value="' . $target->ID . '"><table class="widefat striped"><thead><tr><th>بخش</th>';
        $labels = ['edit' => 'ایجاد و ویرایش خود', 'publish' => 'انتشار', 'others' => 'ویرایش دیگران', 'delete' => 'حذف خود', 'delete_others' => 'حذف دیگران', 'terms' => 'موضوعات'];
        foreach ($labels as $label) echo '<th>' . esc_html($label) . '</th>';
        echo '</tr></thead><tbody>';
        foreach (ig_content_types() as $type => [$plural]) {
            echo '<tr><th>' . esc_html($plural) . '</th>';
            foreach (ig_cap_groups($type) as $group => $caps) echo '<td><input type="checkbox" aria-label="' . esc_attr($plural . ' — ' . $labels[$group]) . '" name="ig_permissions[' . esc_attr($type) . '][' . esc_attr($group) . ']" value="1" ' . checked(user_can($target, $caps[0]), true, false) . '></td>';
            echo '</tr>';
        }
        echo '</tbody></table><p><label><input type="checkbox" name="ig_messages" value="1" ' . checked(user_can($target, 'ig_manage_messages'), true, false) . '> مدیریت پیام‌های تماس</label></p>';
        submit_button('ذخیرهٔ دسترسی‌ها'); echo '</form>';
    }
    echo '</div>';
}

function ig_dashboard(): void {
    echo '<div class="wrap ig-admin"><h1>پنل انجمن غلات ایران</h1><p>خوش آمدید. بخش موردنظر را برای مدیریت محتوا انتخاب کنید.</p><div class="ig-admin-grid">';
    if (current_user_can('manage_options')) echo '<a class="ig-admin-card" href="' . esc_url(admin_url('admin.php?page=ig-page-copy')) . '"><span class="dashicons dashicons-edit"></span><h2>مرکز مدیریت متن‌ها</h2><span>ویرایش متن تمام صفحات و بخش‌ها ←</span></a>';
    foreach (ig_content_types() as $type => [$plural]) {
        if (!current_user_can('edit_' . $type . 's')) continue;
        echo '<a class="ig-admin-card" href="' . esc_url(admin_url('edit.php?post_type=' . $type)) . '"><span class="dashicons dashicons-media-document"></span><h2>' . esc_html($plural) . '</h2><span>مشاهده و مدیریت ←</span></a>';
    }
    if (current_user_can('ig_manage_messages')) echo '<a class="ig-admin-card" href="' . esc_url(admin_url('admin.php?page=ig-messages')) . '"><h2>پیام‌های کاربران</h2><span>مشاهدهٔ پیام‌ها ←</span></a>';
    echo '</div></div>';
}
