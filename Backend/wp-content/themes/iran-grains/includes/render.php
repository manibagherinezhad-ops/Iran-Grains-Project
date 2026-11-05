<?php
defined('ABSPATH') || exit;
function ig_nodes(DOMDocument $doc, string $path): array {
    return iterator_to_array((new DOMXPath($doc))->query($path));
}
function ig_class(string $name): string { return 'contains(concat(" ",normalize-space(@class)," ")," ' . $name . ' ")'; }
function ig_node(DOMDocument $doc, string $id): ?DOMElement { return ig_nodes($doc, '//*[@id="' . $id . '"]')[0] ?? null; }
function ig_text(?DOMNode $node, string $text): void { if ($node) $node->textContent = $text; }
function ig_html(DOMNode $node, string $html): void {
    $fragment = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $fragment->loadHTML('<?xml encoding="UTF-8"><div id="ig-fragment">' . $html . '</div>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $parent = $fragment->getElementById('ig-fragment');
    while ($node->firstChild) $node->removeChild($node->firstChild);
    foreach (iterator_to_array($parent->childNodes) as $child) $node->appendChild($node->ownerDocument->importNode($child, true));
}
function ig_preview_button(array $record, string $label = 'مشاهده خبر'): string {
    return '<button type="button" data-open="' . absint($record['id']) . '">' . esc_html($label) . ' <span aria-hidden="true">←</span></button>';
}
function ig_render(string $route, ?WP_Post $post): void {
    $file = get_template_directory() . '/prototype/' . $route . '.html';
    if (!is_file($file)) wp_die('قالب صفحه در بستهٔ انتشار موجود نیست.', '', ['response' => 503]);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . file_get_contents($file), LIBXML_NONET);
    $doc->encoding = 'UTF-8';
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    foreach (iterator_to_array($doc->childNodes) as $node) if ($node->nodeType === XML_PI_NODE) $doc->removeChild($node);
    $copyTargets = ig_copy_prepare($doc, $route);
    $config = ['assetBase' => get_template_directory_uri() . '/prototype/', 'membersUrl' => ig_page_url('members'), 'membersArchiveUrl' => ig_page_url('members-all'), 'contact' => ['url' => rest_url('iran-grains/v1/contact'), 'nonce' => wp_create_nonce('ig_contact'), 'restNonce' => wp_create_nonce('wp_rest')], 'categories' => []];
    $settings = get_option('ig_settings', []);
    $config['intro'] = array_intersect_key($settings, array_flip(['home_intro_prefix','home_intro_highlight','home_intro_suffix','home_intro_line2','home_intro_subtitle','home_intro_badge']));
    if (!empty($settings['rates_updated'])) $config['ratesUpdated'] = $settings['rates_updated'];
    if (in_array($route, ['index', 'news'], true)) {
        $news = ig_feed('ig_news'); $config['news'] = $news;
        $config['categories']['news'] = ig_topic_labels('ig_news');
        if ($route === 'index') ig_home_news($doc, $news, $config['categories']['news']);
        else ig_news_archive($doc, $news, $config['categories']['news']);
    }
    if (in_array($route, ['members', 'members-all', 'company'], true)) $config['companies'] = ig_feed('ig_company');
    if (in_array($route, ['members', 'members-all'], true)) {
        $config['categories']['companies'] = ig_topic_labels('ig_company');
        $filters = ig_nodes($doc, '//*[' . ig_class('members-filters') . ']')[0] ?? null;
        if ($filters) {
            $existing=[];
            foreach (ig_nodes($doc,'//*[@data-category]') as $button) {
                $slug=$button->getAttribute('data-category'); $existing[]=$slug;
                if ($slug!=='all' && !isset($config['categories']['companies'][$slug])) $button->parentNode->removeChild($button);
                elseif ($slug!=='all') {
                    $found=false;
                    foreach($button->childNodes as$node)if($node->nodeType===XML_TEXT_NODE&&trim($node->textContent)!==''){$node->textContent=$config['categories']['companies'][$slug];$found=true;break;}
                    if(!$found)$button->appendChild($doc->createTextNode($config['categories']['companies'][$slug]));
                }
            }
            foreach ($config['categories']['companies'] as $slug=>$label) if (!in_array($slug,$existing,true)) {
                $button=$doc->createElement('button');$button->setAttribute('type','button');$button->setAttribute('class','members-filter');$button->setAttribute('data-category',$slug);$button->setAttribute('aria-pressed','false');$button->textContent=$label;$filters->appendChild($button);
            }
        }
        foreach (ig_nodes($doc, '//*[contains(@class,"members-stat")]//strong | //*[@id="membersTotal"]') as $node) ig_text($node, ig_fa(count($config['companies'])));
    }
    if (in_array($route, ['circulars', 'approved-prices'], true)) {
        $type = $route === 'circulars' ? 'ig_circular' : 'ig_price';
        $config['documents'] = ig_feed($type); $config['categories']['documents'] = ig_topic_labels($type);
        ig_documents($doc, $config['documents'], $config['categories']['documents']);
    }
    if ($route === 'news-article') ig_article($doc, $post);
    if ($route === 'company') ig_company($doc, $post);
    if ($route === 'meetings') ig_meetings($doc);
    if ($route === 'board') ig_board($doc);
    if ($route === 'circulars') {
        $dialog = ig_node($doc, 'circularDialog');
        if ($dialog) {
            $actions = $doc->createElement('div'); $actions->setAttribute('class','circulars-dialog-actions');
            $link = $doc->createElement('a'); $link->setAttribute('id','igDocumentLink'); $link->setAttribute('class','btn btn-green-outline'); $link->textContent = 'مشاهده سند';
            $actions->appendChild($link); $dialog->appendChild($actions);
        }
    }
    ig_site_settings($doc, $settings);
    ig_menu($doc, $route, $post?->post_type ?? '');
    if ($route === 'news') ig_select_options($doc, 'circularCategory', $config['categories']['news']);
    if (in_array($route, ['circulars', 'approved-prices'], true)) ig_select_options($doc, 'circularCategory', $config['categories']['documents']);
    ig_copy_apply_targets($copyTargets);
    $config['copy'] = ig_copy_public_dictionary();

    // All URLs remain valid when WordPress is installed in a subdirectory.
    foreach (ig_nodes($doc, '//*[@href or @src or @action]') as $node) {
        foreach (['href', 'src', 'action'] as $attr) {
            if (!$node->hasAttribute($attr)) continue;
            $value = $node->getAttribute($attr);
            if (str_starts_with($value, 'assets/')) $node->setAttribute($attr, ig_asset($value));
            elseif (preg_match('/^([a-z-]+)\.html(.*)$/', $value, $match) && in_array($match[1], ig_routes(), true)) {
                $base = in_array($match[1], ['news-article', 'company'], true) ? home_url('/' . $match[1] . '.html') : ig_page_url($match[1]);
                $node->setAttribute($attr, $base . $match[2]);
            }
        }
    }
    foreach (ig_nodes($doc, '//script[@src]') as $script) {
        $name = basename(parse_url($script->getAttribute('src'), PHP_URL_PATH));
        if (in_array($name, ['news-data.js', 'member-data.js'], true) || ($route === 'news-article' && $name === 'news-article.js') || ($route === 'company' && $name === 'company.js')) $script->parentNode->removeChild($script);
    }
    // ASCII JSON escapes preserve Persian text through libxml's HTML serializer.
    $json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $script = $doc->createElement('script');
    $script->appendChild($doc->createCDATASection('window.IranGrains=' . $json . ';window.NewsItems=window.IranGrains.news||[];window.MemberDirectory=window.IranGrains.companies||[];'));
    $body = $doc->getElementsByTagName('body')->item(0);
    $firstScript = ig_nodes($doc, '//body//script')[0] ?? null;
    if ($firstScript) $firstScript->parentNode->insertBefore($script, $firstScript); else $body->appendChild($script);
    $copyRuntime = $doc->createElement('script'); $copyRuntime->setAttribute('src', get_template_directory_uri() . '/assets/copy.js');
    if ($firstScript) $firstScript->parentNode->insertBefore($copyRuntime, $firstScript); else $body->appendChild($copyRuntime);
    $bridge = $doc->createElement('script'); $bridge->setAttribute('src', get_template_directory_uri() . '/assets/site.js'); $body->appendChild($bridge);
    $html = $doc->saveHTML();
    ob_start(); wp_head(); $head = ob_get_clean();
    ob_start(); wp_footer(); $footer = ob_get_clean();
    ob_start(); wp_body_open(); $open = ob_get_clean();
    $html = str_replace('</head>', $head . '</head>', $html);
    $html = preg_replace('/(<body[^>]*>)/', '$1' . str_replace('$', '\\$', $open), $html, 1);
    echo str_replace('</body>', $footer . '</body>', $html); // Escaping happens at each field insertion above.
}

function ig_home_news(DOMDocument $doc, array $news, array $labels): void {
    $grid = ig_nodes($doc, '//*[' . ig_class('news-grid') . ']')[0] ?? null;
    if (!$grid) return;
    $html = '';
    foreach (array_slice($news, 0, 3) as $n) {
        $html .= '<article class="news-card" data-animate><div class="news-img"><img src="' . esc_url($n['image']) . '" alt="' . esc_attr($n['title']) . '" loading="lazy"><span class="news-cat">' . esc_html($labels[$n['category']] ?? '') . '</span></div><div class="news-body"><span class="news-date">' . esc_html(ig_date($n['date'])) . '</span><h3>' . esc_html($n['title']) . '</h3><p>' . esc_html($n['description']) . '</p><a class="news-link" href="' . esc_url($n['url']) . '" aria-haspopup="dialog" aria-controls="homeNewsDialog">ادامه مطلب <span aria-hidden="true">←</span></a></div></article>';
    }
    ig_html($grid, $html ?: '<p>هنوز خبری منتشر نشده است.</p>');
}
function ig_news_archive(DOMDocument $doc, array $news, array $labels): void {
    foreach ([0 => 'Hero', 1 => 'Feature'] as $index => $prefix) {
        if (!isset($news[$index])) continue;
        $n = $news[$index];
        foreach (['Title' => 'title', 'Description' => 'description'] as $suffix => $key) ig_text(ig_node($doc, 'news' . $prefix . $suffix), $n[$key]);
        ig_text(ig_node($doc, 'news' . $prefix . 'Date'), ig_date($n['date']));
        $button = ig_node($doc, 'news' . $prefix . 'Link'); $button?->setAttribute('data-open', (string) $n['id']);
        if ($prefix === 'Hero') {
            ig_text(ig_node($doc, 'newsHeroCategory'), $labels[$n['category']] ?? '');
            $photo = ig_nodes($doc, '//*[' . ig_class('circulars-hero-photo') . ']')[0];
            $photo->setAttribute('style', 'background-image:linear-gradient(90deg,#10282010,#10282026),url("' . esc_url($n['image']) . '")');
        } else ig_node($doc, 'newsFeatureImage')?->setAttribute('src', $n['image']);
    }
    $grid = ig_node($doc, 'circularGrid'); $html = '';
    foreach (array_slice($news, 2, 6) as $n) $html .= '<article class="circulars-card"><img class="archive-news-image" src="' . esc_url($n['image']) . '" alt=""><div class="archive-news-content"><div class="circulars-card-top"><span class="circulars-tag">' . esc_html($labels[$n['category']] ?? '') . '</span><time>' . esc_html(ig_date($n['date'])) . '</time></div><div class="circulars-card-title"><h3><a href="' . esc_url($n['url']) . '">' . esc_html($n['title']) . '</a></h3></div><p>' . esc_html($n['description']) . '</p></div><div class="circulars-card-bottom">' . ig_preview_button($n) . '</div></article>';
    if ($grid) ig_html($grid, $html);
}
function ig_select_options(DOMDocument $doc, string $id, array $labels): void {
    $select = ig_node($doc, $id);
    if (!$select || $select->tagName !== 'select') return;
    $html = '<option value="all">همه موضوعات</option>';
    foreach ($labels as $slug => $label) $html .= '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
    ig_html($select, $html);
}
function ig_documents(DOMDocument $doc, array $documents, array $labels): void {
    ig_text(ig_node($doc, 'archiveTotal'), ig_fa(count($documents)));
    $feature = ig_node($doc, 'circularFeature');
    if (!$documents) { $feature?->setAttribute('hidden', ''); return; }
    $first = $documents[0];
    if ($feature) {
        foreach (['.//h3' => $first['title'], './/p' => $first['description'], './/time' => ig_date($first['date'])] as $path => $value) {
            $node = (new DOMXPath($doc))->query($path, $feature)->item(0); ig_text($node, $value);
        }
        $button = (new DOMXPath($doc))->query('.//*[@data-open]', $feature)->item(0);
        $button?->setAttribute('data-open', (string) $first['id']);
    }
}
function ig_article(DOMDocument $doc, WP_Post $post): void {
    $n = ig_record($post); $labels = ig_topic_labels($post->post_type);
    ig_text($doc->getElementsByTagName('title')->item(0), $n['title'] . ' | انجمن غلات ایران');
    foreach (ig_nodes($doc, '//meta[@name="description"]') as $meta) $meta->setAttribute('content', $n['description']);
    foreach (['articleTitle', 'articleCrumb', 'articleBodyTitle'] as $id) ig_text(ig_node($doc, $id), $n['title']);
    ig_text(ig_node($doc, 'articleCategory'), $labels[$n['category']] ?? '');
    ig_text(ig_node($doc, 'articleDate'), ig_date($n['date']));
    ig_node($doc, 'articleDate')?->setAttribute('datetime', $n['date']);
    ig_node($doc, 'articleHeroImage')?->setAttribute('src', $n['image']);
    ig_node($doc, 'articleHeroImage')?->setAttribute('alt', $n['title']);
    $body = ig_node($doc, 'articleBody');
    if ($body) ig_html($body, wp_kses_post(wpautop($post->post_content)) . ($n['attachment'] ? '<p><a class="btn btn-green-outline" href="' . esc_url($n['attachment']) . '" target="_blank" rel="noopener">دریافت فایل PDF</a></p>' : '') . ($post->post_type === 'ig_news' && $n['source'] ? '<p><a href="' . esc_url($n['source']) . '" target="_blank" rel="noopener">' . esc_html(ig_ui('منبع خبر: انجمن غلات ایران')) . '</a></p>' : ''));
    if ($post->post_type !== 'ig_news') {
        $routes = ['ig_circular' => ['circulars','بخشنامه‌ها'], 'ig_price' => ['approved-prices','قیمت‌های مصوب'], 'ig_event' => ['meetings','جلسات'], 'ig_board' => ['board','هیأت‌مدیره']];
        [$route, $label] = $routes[$post->post_type];
        foreach (ig_nodes($doc, '//a[@href="news.html"]') as $a) if (!$a->hasAttribute('class') || $a->getAttribute('class') === 'article-back') {$a->setAttribute('href', ig_page_url($route)); ig_text($a, 'بازگشت به ' . $label . ' ←');}
        foreach (ig_nodes($doc, '//*[' . ig_class('article-content') . ']/*[' . ig_class('section-tag') . ']') as $tag) ig_text($tag, 'متن کامل');
    }
}
function ig_company(DOMDocument $doc, WP_Post $post): void {
    $c = ig_record($post); $labels = ig_topic_labels('ig_company');
    ig_text($doc->getElementsByTagName('title')->item(0), $c['name'] . ' | انجمن غلات ایران');
    foreach (['companyTitle' => 'name','companyCrumb' => 'name','companyActivity' => 'activity','companyManager' => 'manager'] as $id => $key) ig_text(ig_node($doc, $id), $c[$key]);
    $description = ig_node($doc, 'companyDescription');
    if ($description && $description->tagName === 'p') {
        $replacement = $doc->createElement('div'); $replacement->setAttribute('id', 'companyDescription');
        $description->parentNode->replaceChild($replacement, $description); $description = $replacement;
    }
    if ($description) ig_html($description, wp_kses_post(wpautop($post->post_content)));
    ig_html(ig_node($doc, 'companyBrand'), '<img src="' . esc_url($c['logo']) . '" alt="' . esc_attr($c['name']) . '">');
    $tags = ''; foreach ($c['categories'] as $slug) $tags .= '<span>' . esc_html($labels[$slug] ?? $slug) . '</span>';
    ig_html(ig_node($doc, 'companyTags'), $tags);
    $html = '';
    foreach (['phone'=>'تلفن','fax'=>'فکس','email'=>'ایمیل','website'=>'وب‌سایت','address'=>'آدرس'] as $key => $label) {
        if (!$c[$key]) continue;
        $value = esc_html($c[$key]);
        if (in_array($key, ['phone','email','website'], true)) {
            $href = $key === 'phone' ? 'tel:' . preg_replace('/[^+0-9]/', '', strtr($c[$key], array_combine(preg_split('//u','۰۱۲۳۴۵۶۷۸۹',-1,PREG_SPLIT_NO_EMPTY), range(0,9)))) : ($key === 'email' ? 'mailto:' . $c[$key] : $c[$key]);
            $value = '<a href="' . esc_url($href) . '">' . $value . '</a>';
        }
        $html .= '<div><dt>' . esc_html($label) . '</dt><dd>' . $value . '</dd></div>';
    }
    ig_html(ig_node($doc, 'companyContacts'), $html);
    $source = ig_node($doc, 'companySource');
    if ($c['source']) $source?->setAttribute('href', $c['source']); else $source?->setAttribute('hidden', '');
}
function ig_meetings(DOMDocument $doc): void {
    $html = '';
    foreach (ig_public_posts('ig_event') as $post) {
        $date = get_post_meta($post->ID, '_ig_event_date', true) ?: substr($post->post_date,0,10);
        [$year,$month,$day] = ig_date_parts($date);
        $status = get_post_meta($post->ID, '_ig_event_status', true) === 'held' ? 'held' : 'upcoming';
        $html .= '<article class="meet-card" data-status="' . $status . '" data-animate><div class="meet-img"><img src="' . esc_url(ig_image($post)) . '" alt="' . esc_attr($post->post_title) . '" loading="lazy"></div><div class="meet-row"><div class="meet-date"><b>' . ig_fa($day) . '</b><span>' . esc_html($month) . '</span><i>' . ig_fa($year) . '</i></div><div class="meet-body"><span class="meet-tag ' . $status . '">' . ($status==='held'?'برگزارشده':'در پیش رو') . '</span><h3>' . esc_html($post->post_title) . '</h3><p>' . esc_html($post->post_excerpt) . '</p><div class="meet-meta"><span>📍 ' . esc_html(get_post_meta($post->ID,'_ig_location',true)) . '</span><span>🕑 ' . esc_html(get_post_meta($post->ID,'_ig_event_time',true)) . '</span></div></div></div><template class="meet-summary">' . esc_html(wp_strip_all_tags($post->post_content)) . '</template></article>';
    }
    ig_html(ig_node($doc, 'meetGrid'), $html ?: '<p>هنوز جلسه‌ای ثبت نشده است.</p>');
}
function ig_board(DOMDocument $doc): void {
    $cards = ''; $profiles = '';
    foreach (ig_public_posts('ig_board') as $i => $post) {
        $role = get_post_meta($post->ID, '_ig_position', true); $image = ig_image($post);
        $cards .= '<article class="bmember"><div class="bphoto"><img src="' . esc_url($image) . '" alt="' . esc_attr($post->post_title) . '"></div><h3 class="bname">' . esc_html($post->post_title) . '</h3><span class="brole">' . esc_html($role) . '</span><p class="bbio">' . esc_html($post->post_excerpt) . '</p></article>';
        $profiles .= '<article class="profile" id="profile-' . ($i+1) . '" data-animate><div class="profile-photo"><img src="' . esc_url($image) . '" alt="' . esc_attr($post->post_title) . '"></div><div class="profile-info"><span class="profile-role">' . esc_html($role) . '</span><h3 class="section-title">' . esc_html($post->post_title) . '</h3><div class="profile-bio">' . wp_kses_post(wpautop($post->post_content)) . '</div></div></article>';
    }
    ig_html(ig_node($doc, 'bTrack'), $cards);
    $section = ig_nodes($doc, '//*[' . ig_class('profiles-section') . ']/div')[0] ?? null;
    if ($section) {
        foreach (ig_nodes($doc, '//*[' . ig_class('profile') . ']') as $p) $p->parentNode->removeChild($p);
        $holder = $doc->createElement('div'); ig_html($holder,$profiles);
        while ($holder->firstChild) $section->appendChild($holder->firstChild);
    }
}
function ig_site_settings(DOMDocument $doc, array $settings): void {
    $keys = ['آدرس'=>'contact_address','تلفن'=>'contact_phone','فکس'=>'contact_fax','ایمیل'=>'contact_email'];
    foreach (ig_nodes($doc, '//*[' . ig_class('contact-list') . ']/li | //*[' . ig_class('contact-detail') . ']') as $li) {
        $xp = new DOMXPath($doc); $heading = $xp->query('.//h4 | .//h3', $li)->item(0); $p = $xp->query('.//p',$li)->item(0);
        $key = $keys[trim($heading?->textContent ?? '')] ?? '';
        if ($key && array_key_exists($key, $settings)) {
            if ($key === 'contact_email') ig_html($p, '<a href="' . esc_url('mailto:' . $settings[$key]) . '">' . esc_html($settings[$key]) . '</a>');
            elseif ($key === 'contact_phone') {
                $html=''; foreach (explode('/', $settings[$key]) as $phone) {
                    $digits=strtr($phone,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
                    $html.=($html?' / ':'').'<a dir="ltr" href="'.esc_url('tel:'.preg_replace('/[^+0-9]/','',$digits)).'">'.esc_html(trim($phone)).'</a>';
                } ig_html($p,$html);
            } else ig_text($p, $settings[$key]);
        }
    }
    if (!empty($settings['contact_phone'])) foreach (ig_nodes($doc,'//body['.ig_class('contact-page').']//*['.ig_class('site-hero-facts').']/div[2]/b') as $node) ig_text($node, trim(explode('/',$settings['contact_phone'])[0]));
    foreach (['usd','aed','eur','rub','cny'] as $i => $currency) {
        $nodes = ig_nodes($doc, '//*[' . ig_class('ex-value') . ']');
        if (isset($nodes[$i]) && isset($settings['rate_'.$currency])) ig_text($nodes[$i], number_format((int)$settings['rate_'.$currency]) . ' ریال');
    }
    if (!empty($settings['rates_updated'])) ig_text(ig_node($doc,'exchangeDate'), 'مورخ ' . ig_date($settings['rates_updated']));
    if (!empty($settings['association_about'])) foreach (ig_nodes($doc, '//*[' . ig_class('footer-brand') . ']/p') as $p) ig_text($p, $settings['association_about']);
}
function ig_menu(DOMDocument $doc, string $route, string $type): void {
    $current = match ($type) {'ig_company' => 'members', 'ig_circular' => 'circulars', 'ig_price' => 'approved-prices', 'ig_event' => 'meetings', 'ig_board' => 'board', default => match($route) {'news-article'=>'news', 'members-all'=>'members', default=>$route}};
    foreach (ig_nodes($doc, '//*[@id="navMenu"]//a') as $link) {
        $class = trim(preg_replace('/\bactive\b/', '', $link->getAttribute('class'))); $link->setAttribute('class', $class); $link->removeAttribute('aria-current');
        if ($link->getAttribute('href') === $current . '.html') {
            $link->setAttribute('class', trim($class . ' active')); $link->setAttribute('aria-current', 'page');
            if ($link->parentNode->nodeName === 'li') {
                $parent = $link->parentNode->parentNode->parentNode;
                foreach ($parent->childNodes as $node) if ($node instanceof DOMElement && $node->tagName === 'a') {$node->setAttribute('class','nav-link active'); $node->setAttribute('aria-current','true');}
            }
        }
    }
}
