<?php
// Read-only regression: backend rates must follow currency identities, not card order.
if (wp_get_environment_type() !== 'local' || !current_user_can('manage_options')) throw new RuntimeException('Local manager required.');
$rates = ['usd'=>1879158, 'eur'=>2097050, 'aed'=>511684, 'cny'=>282405, 'rub'=>23327];
$labels = ['usd'=>'دلار آمریکا', 'eur'=>'یورو', 'aed'=>'درهم امارات', 'cny'=>'یوآن چین', 'rub'=>'روبل روسیه'];
$settings = [];
foreach ($rates as $currency=>$value) $settings['rate_'.$currency] = $value;
foreach ([false,true] as $reorder) {
    $doc = ig_copy_source('index');
    if ($reorder) {
        $cards = ig_nodes($doc, '//*[@data-currency]');
        foreach (array_reverse($cards) as $card) $card->parentNode->appendChild($card);
    }
    ig_site_settings($doc, $settings);
    foreach ($rates as $currency=>$value) {
        $card = ig_nodes($doc, '//*[@data-currency="'.$currency.'"]')[0] ?? null;
        $xp = new DOMXPath($doc);
        if (!$card || $xp->query('.//*['.ig_class('ex-name').']', $card)->item(0)->textContent !== $labels[$currency]) throw new RuntimeException('Currency card identity mismatch: '.$currency);
        $actual = $xp->query('.//*['.ig_class('ex-value').']', $card)->item(0)->textContent;
        if ($actual !== ig_fa(number_format($value)).' ریال') throw new RuntimeException('Wrong displayed rate: '.$currency);
    }
}
echo "PASS: all five backend rates match their labeled cards, including after reordering; Persian digits preserved.\n";
