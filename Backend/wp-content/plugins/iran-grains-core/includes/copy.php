<?php
defined('ABSPATH') || exit;
function ig_copy_normalize(string $text): string { return trim(preg_replace('/[\p{Z}\s]+/u',' ',$text)); }
function ig_copy_ui_key(string $text): string { return 'ui_'.substr(hash('sha256',ig_copy_normalize($text)),0,16); }
function ig_copy_ui_catalog(): array {
    static $catalog;
    if (isset($catalog)) return $catalog;
    $path=get_template_directory().'/prototype/assets/data/ui-copy.json';
    $rows=is_readable($path)?json_decode(file_get_contents($path),true):[];$catalog=[];
    foreach (is_array($rows)?$rows:[] as $row) $catalog[$row['key']]=$row;
    return $catalog;
}
function ig_copy_value(array $field) {
    $values=get_option('ig_text_values',[]);
    $binding=$field['binding']??(!empty($field['setting'])?['option'=>'ig_settings','key'=>$field['setting']]:null);
    if ($binding) {
        $value=get_option($binding['option'],[]);
        if (array_key_exists($binding['key'],$value)&&$value[$binding['key']]!=='') return (string)$value[$binding['key']];
        if (array_key_exists('setting_'.$binding['key'],$values)) return $values['setting_'.$binding['key']];
    }
    if (array_key_exists($field['key'],$values)) return $values[$field['key']];
    if (!empty($field['legacy'])) {
        $old=get_option('ig_page_copy',[]);
        if (isset($old[$field['legacy']['route']][$field['legacy']['key']])) return $old[$field['legacy']['route']][$field['legacy']['key']];
    }
    return $field['default'];
}
function ig_ui(string $text): string {
    $key=ig_copy_ui_key($text);$catalog=ig_copy_ui_catalog();
    return isset($catalog[$key])?(string)ig_copy_value($catalog[$key]):$text;
}
function ig_copy_public_dictionary(): array {
    $rows=[];
    foreach (ig_copy_ui_catalog() as $entry) {
        $value=(string)ig_copy_value($entry);
        if ($value!==$entry['default']) $rows[]=['key'=>$entry['key'],'default'=>$entry['default'],'value'=>$value,'tokens'=>$entry['tokens']];
    }
    return $rows;
}
function ig_copy_save_history(string $scope,array $before,array $after,array $extra=[]): void {
    if ($before===$after) return;
    $history=get_option('ig_text_history',[]);
    array_unshift($history,['id'=>wp_generate_uuid4(),'scope'=>$scope,'before'=>$before,'after'=>$after,'extra'=>$extra,'user'=>get_current_user_id(),'time'=>current_time('mysql')]);
    update_option('ig_text_history',array_slice($history,0,60),false);
    ig_audit('page_copy_updated','text_center');
}
function ig_copy_snapshot(array $fields): array { $result=[];foreach($fields as $key=>$field)$result[$key]=ig_copy_value($field);return $result; }
function ig_copy_revision(array $fields): string { return hash('sha256',wp_json_encode(ig_copy_snapshot($fields))); }
function ig_copy_clean(array $input,array $fields): array {
    $clean=[];
    foreach ($fields as $key=>$field) {
        if (!array_key_exists($key,$input)) continue;
        if (!is_scalar($input[$key])) throw new RuntimeException('مقدار متن معتبر نیست.');
        $value=mb_substr(sanitize_textarea_field((string)$input[$key]),0,10000);
        foreach ($field['tokens']??[] as $token) if (!str_contains($value,'{'.$token.'}')) throw new RuntimeException('عبارت خودکار «{'.$token.'}» باید در متن باقی بماند.');
        if (($field['type']??'')==='number') $value=(string)max(0,(int)$value);
        if (!empty($field['binding'])) {
            $k=$field['binding']['key'];
            if ($k==='contact_email')$value=sanitize_email($value);
            elseif($k==='rates_source')$value=esc_url_raw($value,['https','http']);
            elseif($k==='rates_updated')$value=ig_sanitize_field($value,'date');
        }
        $clean[$key]=$value;
    }
    return $clean;
}
function ig_copy_write(array $clean,array $fields): void {
    $values=get_option('ig_text_values',[]);$options=[];
    foreach ($clean as $key=>$value) {
        if (!isset($fields[$key])) continue;
        $binding=$fields[$key]['binding']??null;
        if ($binding) {
            $option=$binding['option'];if(!isset($options[$option]))$options[$option]=get_option($option,[]);
            $options[$option][$binding['key']]=$value;
        }
        // Also retain intentional empty values, including an empty legacy setting.
        $values[$key]=$value;
    }
    update_option('ig_text_values',$values,false);
    foreach($options as $option=>$value)update_option($option,$value,false);
}
