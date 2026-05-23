<?php

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

$reviews = [
	'output'=>['lists'=>[],'datalists'=>[],'result'=>[]],
	'entries'=>['reviews'=>[]],
	'tablist'=>[],
	'attributes'=>[],
	'data_file'=>dirname(__DIR__,2).'/data/reviews.json',
	'data'=>['reviews'=>[],'updated'=>0],
	'ratings'=>[],
	'languages'=>['all'=>['name'=>language__get($user['language'],'_reviews_language_all')]],
	'inputs'=>[
		'author'=>['required'=>true],
		'source'=>[],
		'rating'=>['type'=>'select','required'=>true,'options'=>[]],
		'text'=>['required'=>true,'input'=>'textarea','attributes'=>['rows'=>5]],
		'lid'=>['type'=>'datalist','required'=>true,'attributes'=>['data-list'=>'reviews-languages']],
		'date'=>['type'=>'date','attributes'=>['data-zero'=>'true']],
		'published'=>['type'=>'checkbox'],
		'featured'=>['type'=>'checkbox']
	]
];

if (is_file($reviews['data_file'])) {
	$reviews['loaded'] = helper__json_convert(file_get_contents($reviews['data_file']));
	if (is_array($reviews['loaded'])) $reviews['data'] = array_merge($reviews['data'],$reviews['loaded']);
}
if (!isset($reviews['data']['reviews']) || !is_array($reviews['data']['reviews'])) $reviews['data']['reviews'] = [];

foreach ([1,2,3,4,5] as $reviews['rating']) $reviews['ratings'][$reviews['rating']] = ['value'=>$reviews['rating'],'name'=>language__get_parsed($user['language'],'_reviews_rating_option',['rating'=>$reviews['rating']])];
$reviews['inputs']['rating']['options'] = $reviews['ratings'];
foreach ($site['installed_languages'] as $reviews['lid']) $reviews['languages'][$reviews['lid']] = ['name'=>(function_exists('locale_get_display_language') ? locale_get_display_language($reviews['lid'],$user['language']).' ('.$reviews['lid'].')' : $reviews['lid'])];
$reviews['output']['datalists']['reviews-languages'] = $reviews['languages'];

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$reviews['action'] = (string) ($_POST['action'] ?? '');
	$reviews['id'] = trim((string) ($_POST['id'] ?? ''));

	if ($reviews['action'] == 'delete' && $reviews['id'] !== '' && isset($reviews['data']['reviews'][$reviews['id']])) {
		unset($reviews['data']['reviews'][$reviews['id']]);
		$reviews['data']['updated'] = $_SERVER['now'];
		$reviews['output']['result'] = ['result'=>helper__files_write($reviews['data_file'],$reviews['data'],true,true)];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save') {
		if ($reviews['id'] == 'new' || $reviews['id'] == '') $reviews['id'] = 'review_'.$_SERVER['now'].'_'.bin2hex(random_bytes(4));
		if (!preg_match('/^[A-Za-z0-9_.-]+$/',$reviews['id'])) $reviews['id'] = 'review_'.$_SERVER['now'].'_'.bin2hex(random_bytes(4));
		$reviews['entry'] = $reviews['data']['reviews'][$reviews['id']] ?? ['id'=>$reviews['id'],'created'=>$_SERVER['now']];
		$reviews['entry']['author'] = trim((string) ($_POST['author'] ?? ''));
		$reviews['entry']['source'] = trim((string) ($_POST['source'] ?? ''));
		$reviews['entry']['rating'] = max(1,min(5,intval($_POST['rating'] ?? 5)));
		$reviews['entry']['text'] = trim((string) ($_POST['text'] ?? ''));
		$reviews['entry']['lid'] = trim((string) ($_POST['lid'] ?? 'all'));
		$reviews['entry']['date'] = intval($_POST['date'] ?? $_SERVER['now']);
		$reviews['entry']['published'] = !empty($_POST['published']) ? 1 : 0;
		$reviews['entry']['featured'] = !empty($_POST['featured']) ? 1 : 0;
		$reviews['entry']['updated'] = $_SERVER['now'];
		if ($reviews['entry']['lid'] == '') $reviews['entry']['lid'] = 'all';
		$reviews['data']['reviews'][$reviews['id']] = $reviews['entry'];
		$reviews['data']['updated'] = $_SERVER['now'];
		$reviews['output']['result'] = ['result'=>helper__files_write($reviews['data_file'],$reviews['data'],true,true),'id'=>$reviews['id']];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'load') {
		$reviews['entry'] = $reviews['id'] == 'new' ? ['id'=>'new','author'=>'','source'=>'','rating'=>5,'text'=>'','lid'=>'all','date'=>$_SERVER['now'],'published'=>1,'featured'=>0] : ($reviews['data']['reviews'][$reviews['id']] ?? ['id'=>$reviews['id'],'author'=>'','source'=>'','rating'=>5,'text'=>'','lid'=>'all','date'=>$_SERVER['now'],'published'=>0,'featured'=>0]);
		$reviews['formitems'] = create__form_items($reviews['inputs'],$reviews['entry'],'reviews',$user['language']);
		$reviews['formitems']['published']['checked'] = intval($reviews['entry']['published'] ?? 0) == 1;
		$reviews['formitems']['featured']['checked'] = intval($reviews['entry']['featured'] ?? 0) == 1;
		$reviews['form'] = [];
		foreach (['author','source','rating','text','lid','date','published','featured'] as $reviews['field']) $reviews['form'][] = ['id'=>$settings['key'].'-form-'.$reviews['field'],'classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems'][$reviews['field']]];
		$reviews['headline'] = $reviews['id'] == 'new' ? language__get($user['language'],'_reviews_new') : language__get($user['language'],'_reviews_edit');
		$reviews['output']['lists'] = create__form($settings['form'],$reviews['form'],$reviews['headline'],language__get($user['language'],'_reviews_save'),['load'=>['action'=>'save','id'=>$reviews['entry']['id']]]);
		$reviews['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled'])) $_POST['handled'] = true;
}

$reviews['items'] = [];
uasort($reviews['data']['reviews'],function($a,$b) {
	$ad = intval($a['date'] ?? 0);
	$bd = intval($b['date'] ?? 0);
	if ($ad == $bd) return 0;
	return ($ad < $bd) ? 1 : -1;
});

foreach ($reviews['data']['reviews'] as $reviews['id'] => $reviews['entry']) {
	$reviews['rating_text'] = str_repeat('★',max(1,min(5,intval($reviews['entry']['rating'] ?? 0))));
	$reviews['state'] = !empty($reviews['entry']['published']) ? language__get($user['language'],'_reviews_published') : language__get($user['language'],'_reviews_draft');
	$reviews['subtitle'] = $reviews['rating_text'].' · '.$reviews['state'].' · '.format__date_relative(intval($reviews['entry']['date'] ?? $_SERVER['now']),'date',$user['language']);
	$reviews['row_items'] = [
		['id'=>$settings['key'].'-'.$reviews['id'].'-text','tag'=>'font','classes'=>['forms__item'],'description'=>htmlspecialchars(mb_substr(trim((string) ($reviews['entry']['text'] ?? '')),0,220),ENT_QUOTES,'UTF-8')],
		['id'=>$settings['key'].'-'.$reviews['id'].'-edit','description'=>language__get($user['language'],'_reviews_edit'),'actions'=>['load'=>['id'=>$reviews['id'],'form'=>true]]],
		['id'=>$settings['key'].'-'.$reviews['id'].'-delete','tag'=>'li','items'=>[
			['id'=>$settings['key'].'-'.$reviews['id'].'-delete-button','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button','data-confirmation'=>language__get($user['language'],'_ui_confirm_delete')],'description'=>language__get($user['language'],'_reviews_delete'),'actions'=>['load'=>['action'=>'delete','id'=>$reviews['id']]]]
		]]
	];
	$reviews['items'][] = ['id'=>$settings['key'].'-'.$reviews['id'].'-row','tag'=>'li','items'=>[
		create__dropdown($settings['key'].'-'.$reviews['id'].'-dropdown',trim((string) ($reviews['entry']['author'] ?? '')),create__list($settings['key'].'-'.$reviews['id'].'-list',$reviews['row_items'],['clear'=>true]),['subtitle'=>$reviews['subtitle']])
	]];
}

if (empty($reviews['items'])) $reviews['items'][] = ['id'=>$settings['key'].'-empty','tag'=>'font','classes'=>['forms__item'],'description'=>language__get($user['language'],'_reviews_empty')];
$reviews['items'][] = ['id'=>$settings['key'].'-new','tag'=>'li','description'=>language__get($user['language'],'_reviews_new'),'classes'=>['system-next'],'actions'=>['load'=>['id'=>'new','form'=>true]]];

$reviews['entries']['reviews'][] = create__list($settings['key'].'-list',$reviews['items'],['clear'=>true,'sort'=>true]);
$reviews['tablist'] = ['reviews'=>language__get($user['language'],'_reviews_tab_reviews')];
$reviews['attributes'] = ['reviews'=>['classes'=>['forms__wrapper']]];
$reviews['tabs'] = create__tablist($settings['key'],$reviews['tablist'],$reviews['entries'],$reviews['attributes']);
$reviews['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','refresh'=>($_SERVER['now'] + 60),'items'=>[$reviews['tabs']['tabs'],$reviews['tabs']['panels']]];

foreach ($reviews['output'] as $key => $value) {
	if (empty($value)) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($reviews);
