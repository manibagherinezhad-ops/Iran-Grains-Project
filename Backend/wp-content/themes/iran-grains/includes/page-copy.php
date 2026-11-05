<?php
defined('ABSPATH') || exit;
function ig_copy_route_labels(): array {
    return ['index'=>'خانه','about'=>'آشنایی با انجمن','membership'=>'شرایط عضویت','iran-green'=>'ایران گرین',
        'contact'=>'تماس با ما','statute'=>'اساسنامه','news'=>'اخبار','news-article'=>'صفحهٔ خبر کامل',
        'circulars'=>'بخشنامه‌ها','approved-prices'=>'قیمت‌های مصوب','members'=>'شرکت‌های عضو',
        'company'=>'معرفی شرکت','board'=>'هیأت‌مدیره','meetings'=>'جلسات'];
}
function ig_copy_source(string $route): ?DOMDocument {
    if (!isset(ig_copy_route_labels()[$route])) return null;
    $file=get_template_directory().'/prototype/'.$route.'.html';if(!is_file($file))return null;
    $doc=new DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($file),LIBXML_NONET);$doc->encoding='UTF-8';
    libxml_clear_errors();libxml_use_internal_errors($old);return $doc;
}
function ig_copy_is_data(DOMNode $node): bool {
    $ids=['bTrack','meetGrid','membersGrid','circularGrid','membersTotal','archiveTotal','companyTitle','companyCrumb','companyActivity','companyDescription','companyManager',
        'companyTags','companyBrand','articleTitle','articleCrumb','articleCategory','articleDate','articleBody','articleBodyTitle',
        'newsHeroTitle','newsHeroDescription','newsHeroDate','newsHeroCategory','newsFeatureTitle','newsFeatureDescription','newsFeatureDate',
        'circularDialogTitle','circularDialogBody','circularDialogCategory','circularDialogDate','homeNewsTitle','homeNewsSummary','homeNewsCategory','homeNewsDate'];
    for($a=$node instanceof DOMElement?$node:$node->parentNode;$a instanceof DOMElement;$a=$a->parentNode){
        if(in_array($a->tagName,['script','style','svg','template','noscript'],true)||in_array($a->getAttribute('id'),$ids,true))return true;
        $c=' '.$a->getAttribute('class').' ';
        if(preg_match('/\b(news-cat|bname|brole|bbio|profile|profile-bio|profile-role|member-card-copy|member-activity|member-dialog-tags|news-date|ex-value)\b/',$c))return true;
        if($a->hasAttribute('data-count'))return true;
        if($a->tagName==='time')return true;
        if(in_array($a->tagName,['h3','p'],true)){
            for($b=$a->parentNode;$b instanceof DOMElement;$b=$b->parentNode){
                if(preg_match('/\b(news-card|meet-card|profile|circulars-feature)\b/',$b->getAttribute('class')))return true;
            }
        }
        if(in_array($a->tagName,['p','dd'],true))for($b=$a->parentNode;$b instanceof DOMElement;$b=$b->parentNode){
            if(preg_match('/\b(contact-list|contact-detail-list|company-contact)\b/',$b->getAttribute('class')))return true;
        }
    }
    return false;
}
function ig_copy_section(DOMElement $element): array {
    $known=['home'=>'هیرو و معرفی','news'=>'اخبار و رویدادها','services'=>'خدمات انجمن','about'=>'دربارهٔ انجمن','contact'=>'تماس با ما','exchange'=>'نرخ‌های ارز'];
    for($a=$element;$a instanceof DOMElement;$a=$a->parentNode){
        if($a->tagName==='header')return ['header','هدر و منوی اصلی'];
        if($a->tagName==='footer'){
            $parent=$element;while($parent instanceof DOMElement&&$parent!==$a){
                if(str_contains(' '.$parent->getAttribute('class').' ',' footer-col ')){
                    $h=$parent->getElementsByTagName('h4')->item(0);if($h)return ['footer','فوتر · '.ig_copy_normalize($h->textContent)];
                }$parent=$parent->parentNode;
            }return ['footer','فوتر و معرفی انجمن'];
        }
        if($a->tagName==='dialog')return ['page','پنجره‌های توضیحات'];
        if($a->tagName==='head')return ['page','عنوان و توضیحات مرورگر'];
        if($a->tagName==='section'){
            if(isset($known[$a->getAttribute('id')]))return ['page',$known[$a->getAttribute('id')]];
            foreach(['h1','h2','h3']as$tag){$h=$a->getElementsByTagName($tag)->item(0);if($h&&trim($h->textContent)!=='')return ['page',mb_substr(ig_copy_normalize($h->textContent),0,85)];}
            return ['page','برچسب‌ها و دکمه‌های صفحه'];
        }
    }return ['page','متن‌های عمومی صفحه'];
}
function ig_copy_prepare(DOMDocument $doc,string $route): array {
    $targets=[];$ui=ig_copy_ui_catalog();
    foreach(ig_nodes($doc,'//body//text()[normalize-space(.)!=""] | //head/title/text()')as$node){
        if(!($node->parentNode instanceof DOMElement)||ig_copy_is_data($node))continue;
        $parent=$node->parentNode;$default=ig_copy_normalize($node->textContent);
        if($parent->tagName==='option'&&$parent->parentNode instanceof DOMElement&&$parent->parentNode->getAttribute('id')==='circularCategory'&&$parent->getAttribute('value')!=='all')continue;
        if($parent->tagName==='button'&&$parent->hasAttribute('data-category')&&$parent->getAttribute('data-category')!=='all')continue;
        if(!preg_match('/[\p{L}\p{N}]/u',$default))continue;
        if($parent->tagName==='title'&&in_array($route,['news-article','company'],true))continue;
        [$scope,$section]=ig_copy_section($parent);$path=$node->getNodePath();
        $key=$scope==='page'?'page_'.$route.'_'.substr(hash('sha256',$path),0,20):'shared_'.$scope.'_'.substr(hash('sha256',$default),0,20);
        $label=match($parent->tagName){'h1'=>'عنوان اصلی','h2'=>'عنوان بخش','h3'=>'تیتر','p'=>'توضیحات','a','button'=>'متن دکمه یا لینک','title'=>'عنوان مرورگر',default=>'متن و برچسب'};
        $field=['key'=>$key,'default'=>$default,'scope'=>$scope,'section'=>$section,'label'=>$label,'tokens'=>[],
            'legacy'=>['route'=>$route,'key'=>hash('sha256',$path)]];
        $uiKey=ig_copy_ui_key($default);
        if($scope==='page'&&isset($ui[$uiKey])&&in_array($parent->tagName,['a','button','option','label','dt'],true))$field=array_merge($ui[$uiKey],['scope'=>'page','section'=>$section,'label'=>$label,'shared'=>true]);
        if($scope==='footer'&&$parent->tagName==='p'&&str_contains(' '.$parent->parentNode->getAttribute('class').' ',' footer-brand ')){
            $field=['key'=>'setting_association_about','default'=>$default,'scope'=>'footer','section'=>$section,'label'=>'معرفی انجمن در فوتر','tokens'=>[],'binding'=>['option'=>'ig_settings','key'=>'association_about']];
            $targets[]=['node'=>$parent,'field'=>$field,'kind'=>'element'];continue;
        }
        $targets[]=['node'=>$node,'field'=>$field,'kind'=>'text'];
    }
    foreach(ig_nodes($doc,'//*[@placeholder or @aria-label or @title or @alt or @data-count] | //meta[@name="description"]')as$element){
        foreach(['placeholder'=>'راهنمای فیلد','aria-label'=>'عنوان دسترس‌پذیر','title'=>'راهنمای عنصر','alt'=>'توضیح تصویر','data-count'=>'عدد آمار','content'=>'توضیح مرورگر']as$attr=>$label){
            if(!$element->hasAttribute($attr)||($attr==='content'&&$element->tagName!=='meta'))continue;
            if($attr!=='data-count'&&ig_copy_is_data($element))continue;
            if($attr==='content'&&in_array($route,['company','news-article'],true))continue;
            $default=ig_copy_normalize($element->getAttribute($attr));if($default==='')continue;
            [$scope,$section]=ig_copy_section($element);$path=$element->getNodePath().'/@'.$attr;
            $key=$scope==='page'?'page_'.$route.'_'.substr(hash('sha256',$path),0,20):'shared_'.$scope.'_'.substr(hash('sha256',$attr.'|'.$default),0,20);
            $field=['key'=>$key,'default'=>$default,'scope'=>$scope,'section'=>$section,'label'=>$label,'tokens'=>[],'type'=>$attr==='data-count'?'number':'text'];
            $targets[]=['node'=>$element,'field'=>$field,'kind'=>'attribute','attr'=>$attr];
        }
    }
    return $targets;
}
function ig_copy_apply_targets(array $targets): void {
    foreach($targets as$target){$node=$target['node'];$value=(string)ig_copy_value($target['field']);
        if($target['kind']==='attribute'){$node->setAttribute($target['attr'],$value);$node->setAttribute('data-ig-static-copy','1');}
        elseif($target['kind']==='element'){$node->textContent=$value;$node->setAttribute('data-ig-static-copy','1');}
        elseif($node->parentNode){$raw=$node->textContent;preg_match('/^(\s*).*?(\s*)$/us',$raw,$padding);$node->textContent=($padding[1]??'').$value.($padding[2]??'');$node->parentNode->setAttribute('data-ig-static-copy','1');}
    }
}
function ig_copy_catalog(string $view,string $route): array {
    $fields=[];
    if($view==='messages')foreach(ig_copy_ui_catalog()as$key=>$field){if(!empty($field['setting']))continue;$field['section']=ig_copy_message_group($field);$field['label']='متن رابط کاربر';$fields[$key]=$field;}
    if(in_array($view,['pages','shared'],true)){
        $routes=$view==='shared'?array_keys(ig_copy_route_labels()):[$route];
        foreach($routes as$r){$doc=ig_copy_source($r);if(!$doc)continue;foreach(ig_copy_prepare($doc,$r)as$target){$f=$target['field'];if(($view==='pages'&&$f['scope']==='page')||($view==='shared'&&$f['scope']!=='page'))$fields[$f['key']]??=$f;}}
    }
    if($view==='settings'||($view==='pages'&&$route==='index'))foreach(ig_setting_fields()as$key=>$label){
        if($view==='pages'&&!str_starts_with($key,'home_intro_'))continue;
        if($view==='settings'&&str_starts_with($key,'home_intro_'))continue;
        $default=ig_copy_setting_defaults()[$key]??'';
        $fields['setting_'.$key]=['key'=>'setting_'.$key,'default'=>$default,'label'=>$label,'section'=>str_starts_with($key,'home_intro_')?'هیرو و معرفی':(str_starts_with($key,'rate')?'نرخ‌های ارز':'اطلاعات تماس و معرفی انجمن'),'tokens'=>[],
            'binding'=>['option'=>'ig_settings','key'=>$key],'type'=>str_starts_with($key,'rate_')?'number':match($key){'rates_updated'=>'date','rates_source'=>'url','contact_email'=>'email',default=>'text'}];
    }
    return $fields;
}
function ig_copy_setting_defaults(): array {
    return ['home_intro_prefix'=>'آینده‌ی','home_intro_highlight'=>'غلات','home_intro_suffix'=>'را','home_intro_line2'=>'با هم می‌سازیم',
        'home_intro_badge'=>'🌱 مرجع تخصصی صنعت غلات ایران','home_intro_subtitle'=>'انجمن غلات ایران با بیش از دو دهه تجربه، پل ارتباطی میان تولیدکنندگان، واردکنندگان و نهادهای دولتی برای توسعه‌ی پایدار صنعت غلات و تأمین امنیت غذایی کشور است.'];
}
function ig_copy_message_group(array $field): string {
    $groups=['main'=>'خانه و پیام‌های عمومی','news'=>'اخبار','circulars'=>'بخشنامه‌ها','approved-prices'=>'قیمت‌های مصوب','members'=>'شرکت‌های عضو','company'=>'معرفی شرکت','meetings-ui'=>'جلسات','news-article'=>'خبر کامل','site'=>'فرم تماس و پیام‌های سایت'];
    return $groups[$field['groups'][0]??'site']??'پیام‌های سایت';
}
require_once __DIR__.'/copy-admin.php';
require_once __DIR__.'/copy-content.php';
require_once __DIR__.'/copy-preview.php';
