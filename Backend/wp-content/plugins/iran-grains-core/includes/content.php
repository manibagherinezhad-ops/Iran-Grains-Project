<?php
defined('ABSPATH') || exit;

function ig_content_types(): array {
    return [
        'ig_news' => ['اخبار', 'خبر', 'news'],
        'ig_circular' => ['بخشنامه‌ها', 'بخشنامه', 'circular'],
        'ig_price' => ['قیمت‌های مصوب', 'ابلاغیهٔ قیمت', 'price-notice'],
        'ig_event' => ['جلسات و رویدادها', 'جلسه یا رویداد', 'event'],
        'ig_company' => ['شرکت‌های عضو', 'شرکت', 'company'],
        'ig_board' => ['هیأت‌مدیره', 'عضو هیأت‌مدیره', 'board-member'],
    ];
}

function ig_fields(string $type): array {
    $shared = ['source' => ['نشانی منبع', 'url']];
    return ['seo_title'=>['عنوان نمایش در گوگل (اختیاری)','text'], 'seo_description'=>['توضیح نمایش در گوگل (اختیاری)','textarea']] + match ($type) {
        'ig_news' => $shared,
        'ig_circular', 'ig_price' => $shared + [
            'document_number' => ['شمارهٔ ابلاغیه', 'text'],
            'issuer' => ['مرجع صادرکننده', 'text'],
            'attachment_id' => ['فایل PDF پیوست', 'pdf'],
        ],
        'ig_company' => $shared + [
            'activity' => ['فعالیت کوتاه', 'text'], 'manager' => ['مدیرعامل', 'text'],
            'phone' => ['تلفن', 'text'], 'fax' => ['فکس', 'text'],
            'email' => ['ایمیل', 'email'], 'website' => ['وب‌سایت شرکت', 'url'],
            'address' => ['آدرس', 'textarea'],
        ],
        'ig_event' => [
            'event_date' => ['تاریخ برگزاری (میلادی)', 'date'],
            'event_time' => ['ساعت برگزاری', 'time'], 'location' => ['محل برگزاری', 'text'],
            'event_status' => ['وضعیت برگزاری', 'status'],
        ],
        'ig_board' => ['position' => ['سمت', 'text']],
        default => [],
    };
}

function ig_taxonomy(string $type): string { return $type . '_topic'; }

// A full activity edit must not leave an older summary on member cards.
// An explicitly edited summary takes precedence, including clearing it.
function ig_company_sync_summary(array $values, WP_Post $before): array {
    if ($before->post_type !== 'ig_company' || !array_key_exists('post_content', $values)
        || $values['post_content'] === $before->post_content) return $values;
    if (array_key_exists('post_excerpt', $values) && $values['post_excerpt'] !== $before->post_excerpt) return $values;
    $plain = preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?\s*>/i', ' ', strip_shortcodes($values['post_content']));
    $values['post_excerpt'] = wp_trim_words(wp_strip_all_tags($plain), 40, '…');
    return $values;
}
add_filter('wp_insert_post_data', function ($data, $postarr, $unsanitized, $update) {
    if (!$update || $data['post_type'] !== 'ig_company' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return $data;
    $before = get_post((int) ($postarr['ID'] ?? 0));
    if (!$before) return $data;
    return wp_slash(ig_company_sync_summary(wp_unslash($data), $before));
}, 10, 4);

function ig_register_content(): void {
    foreach (ig_content_types() as $type => [$plural, $single, $slug]) {
        register_post_type($type, [
            'labels' => ['name' => $plural, 'singular_name' => $single,
                'add_new' => 'افزودن', 'add_new_item' => 'افزودن ' . $single,
                'edit_item' => 'ویرایش ' . $single, 'all_items' => 'همهٔ ' . $plural,
                'not_found' => 'موردی پیدا نشد', 'featured_image' => 'تصویر شاخص یا لوگو',
                'set_featured_image' => 'انتخاب تصویر', 'remove_featured_image' => 'حذف تصویر'],
            'public' => true, 'show_in_rest' => true, 'has_archive' => false,
            'rewrite' => ['slug' => $slug, 'with_front' => false],
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'page-attributes'],
            'capability_type' => [$type, $type . 's'], 'map_meta_cap' => true,
            'menu_icon' => 'dashicons-media-document',
        ]);
        if ($type !== 'ig_board') {
            register_taxonomy(ig_taxonomy($type), $type, [
                'label' => 'موضوعات ' . $plural, 'hierarchical' => true,
                'show_in_rest' => true, 'rewrite' => false,
                'capabilities' => [
                    'manage_terms' => 'manage_' . $type . '_terms',
                    'edit_terms' => 'manage_' . $type . '_terms',
                    'delete_terms' => 'manage_' . $type . '_terms',
                    'assign_terms' => 'edit_' . $type . 's',
                ],
            ]);
        }
        foreach (ig_fields($type) as $field => [, $kind]) {
            register_post_meta($type, '_ig_' . $field, [
                'single' => true, 'type' => $kind === 'pdf' ? 'integer' : 'string',
                'show_in_rest' => false,
                'auth_callback' => fn($allowed, $key, $id) => current_user_can('edit_post', $id),
                'sanitize_callback' => fn($value) => ig_sanitize_field($value, $kind),
            ]);
        }
    }
}

function ig_sanitize_field($value, string $kind) {
    if (!is_scalar($value)) return $kind === 'pdf' ? 0 : '';
    $value = (string) $value;
    return match ($kind) {
        'url' => esc_url_raw($value, ['https', 'http']),
        'email' => sanitize_email($value),
        'textarea' => sanitize_textarea_field($value),
        'pdf' => absint($value),
        'status' => in_array($value, ['upcoming', 'held'], true) ? $value : 'upcoming',
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '',
        'time' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '',
        default => sanitize_text_field($value),
    };
}

add_action('add_meta_boxes', function ($type) {
    if (!isset(ig_content_types()[$type])) return;
    add_meta_box('ig_details', 'اطلاعات تکمیلی', 'ig_details_box', $type, 'normal', 'high');
});
function ig_details_box(WP_Post $post): void {
    wp_nonce_field('ig_details_' . $post->ID, 'ig_details_nonce');
    echo '<div class="ig-fields">';
    foreach (ig_fields($post->post_type) as $key => [$label, $kind]) {
        $value = get_post_meta($post->ID, '_ig_' . $key, true);
        echo '<p><label for="ig_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
        if ($kind === 'textarea') {
            echo '<textarea class="widefat" rows="3" id="ig_' . esc_attr($key) . '" name="ig_fields[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
        } elseif ($kind === 'status') {
            echo '<select id="ig_' . esc_attr($key) . '" name="ig_fields[' . esc_attr($key) . ']">';
            foreach (['upcoming' => 'پیش رو', 'held' => 'برگزارشده'] as $v => $text) echo '<option value="' . esc_attr($v) . '" ' . selected($v, $value, false) . '>' . esc_html($text) . '</option>';
            echo '</select>';
        } elseif ($kind === 'pdf') {
            echo '<input type="hidden" id="ig_attachment_id" name="ig_fields[attachment_id]" value="' . absint($value) . '">';
            echo '<button type="button" class="button" id="ig-select-pdf">انتخاب PDF</button> <button type="button" class="button" id="ig-clear-pdf">حذف پیوست</button> <span id="ig-pdf-label">' . esc_html($value ? get_the_title((int) $value) : 'پیوستی انتخاب نشده') . '</span>';
        } else {
            echo '<input class="widefat" id="ig_' . esc_attr($key) . '" type="' . esc_attr(in_array($kind, ['url', 'email', 'date', 'time']) ? $kind : 'text') . '" name="ig_fields[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
        }
        echo '</p>';
    }
    echo '<p class="description">خلاصه را در بخش «چکیده» و متن کامل را در ویرایشگر وارد کنید. ترتیب نمایش از بخش «ویژگی‌ها» قابل تنظیم است.</p>';
    if ($post->post_type === 'ig_company') echo '<p class="description">فعالیت کوتاه و چکیده روی همهٔ کارت‌های شرکت نمایش داده می‌شوند. با تغییر متن کامل، چکیدهٔ کارت هم تازه می‌شود؛ اگر هم‌زمان چکیده را ویرایش کنید، متن دلخواه شما استفاده می‌شود.</p>';
    echo '</div>';
}
add_action('save_post', function ($id, $post) {
    if (!isset(ig_content_types()[$post->post_type]) || wp_is_post_revision($id) || wp_is_post_autosave($id)
        || !current_user_can('edit_post', $id) || !isset($_POST['ig_details_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ig_details_nonce'])), 'ig_details_' . $id)) return;
    $input = isset($_POST['ig_fields']) && is_array($_POST['ig_fields']) ? wp_unslash($_POST['ig_fields']) : [];
    foreach (ig_fields($post->post_type) as $key => [, $kind]) {
        $value = ig_sanitize_field($input[$key] ?? '', $kind);
        if ($kind === 'pdf' && $value) {
            if (get_post_mime_type($value) !== 'application/pdf' || !current_user_can('edit_post', $value)) continue;
        }
        update_post_meta($id, '_ig_' . $key, $value);
    }
}, 10, 2);

add_filter('use_block_editor_for_post_type', fn($use, $type) => isset(ig_content_types()[$type]) ? false : $use, 10, 2);
