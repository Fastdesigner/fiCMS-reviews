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
	'select'=>'',
	'inputs'=>[
		'author'=>['required'=>true],
		'source'=>[],
		'rating'=>['type'=>'select','required'=>true,'options'=>[]],
		'text'=>['required'=>true,'input'=>'textarea','attributes'=>['rows'=>5]],
		'lid'=>['type'=>'multipicker','required'=>true,'attributes'=>[]],
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
		$reviews['entry']['lid'] = helper__json_convert($_POST['lid'] ?? ['all']);
		if (empty($reviews['entry']['lid'])) $reviews['entry']['lid'] = ['all'];
		if (in_array('all',$reviews['entry']['lid'],true)) $reviews['entry']['lid'] = ['all'];
		else $reviews['entry']['lid'] = array_values(array_intersect($reviews['entry']['lid'],$site['installed_languages']));
		if (empty($reviews['entry']['lid'])) $reviews['entry']['lid'] = ['all'];
		foreach (['author','source','text'] as $reviews['field']) {
			$reviews['entry'][$reviews['field']] = helper__json_convert($_POST[$reviews['field']] ?? []);
			foreach ($reviews['entry'][$reviews['field']] as $reviews['field_lid'] => $reviews['field_value']) $reviews['entry'][$reviews['field']][$reviews['field_lid']] = trim((string) $reviews['field_value']);
		}
		$reviews['entry']['rating'] = max(1,min(5,intval($_POST['rating'] ?? 5)));
		$reviews['entry']['date'] = intval($_POST['date'] ?? $_SERVER['now']);
		$reviews['entry']['published'] = !empty($_POST['published']) ? 1 : 0;
		$reviews['entry']['featured'] = !empty($_POST['featured']) ? 1 : 0;
		$reviews['entry']['updated'] = $_SERVER['now'];
		$reviews['data']['reviews'][$reviews['id']] = $reviews['entry'];
		$reviews['data']['updated'] = $_SERVER['now'];
		$reviews['output']['result'] = ['result'=>helper__files_write($reviews['data_file'],$reviews['data'],true,true),'id'=>$reviews['id']];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'load') {
		$reviews['entry'] = $reviews['id'] == 'new' ? ['id'=>'new','author'=>[],'source'=>[],'rating'=>5,'text'=>[],'lid'=>['all'],'date'=>$_SERVER['now'],'published'=>1,'featured'=>0] : ($reviews['data']['reviews'][$reviews['id']] ?? ['id'=>$reviews['id'],'author'=>[],'source'=>[],'rating'=>5,'text'=>[],'lid'=>['all'],'date'=>$_SERVER['now'],'published'=>0,'featured'=>0]);
		foreach (['author','source','text'] as $reviews['field']) {
			if (!is_array($reviews['entry'][$reviews['field']] ?? [])) $reviews['entry'][$reviews['field']] = [$site['default_language']=>$reviews['entry'][$reviews['field']]];
		}
		$reviews['entry']['lid'] = helper__json_convert($reviews['entry']['lid'] ?? ['all']);
		if (empty($reviews['entry']['lid'])) $reviews['entry']['lid'] = ['all'];
		$reviews['select'] = $settings['key'].'-form-lingual';
		$reviews['inputs']['lid']['attributes'] = ['data-list'=>'installed-languages','data-selectcontrol'=>$reviews['select'],'data-all'=>'true'];
		$reviews['formitems'] = create__form_items($reviews['inputs'],$reviews['entry'],'reviews',$user['language']);
		$reviews['formitems']['published']['checked'] = intval($reviews['entry']['published'] ?? 0) == 1;
		$reviews['formitems']['featured']['checked'] = intval($reviews['entry']['featured'] ?? 0) == 1;
		$reviews['form'] = [
			['id'=>$settings['key'].'-form-lid','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['lid']],
			['id'=>$reviews['select'],'realid'=>$reviews['select'],'classes'=>['forms__wrapper'],'attributes'=>['data-select'=>$user['language'],'data-selecttype'=>'language','data-selectall'=>json_encode($site['installed_languages']),'data-selectoptions'=>json_encode($site['installed_languages'])],'items'=>[
				['id'=>$settings['key'].'-form-author','realid'=>'reviewsauthor','classes'=>['forms__select'],'type'=>'form','form'=>$reviews['formitems']['author']],
				['id'=>$settings['key'].'-form-source','realid'=>'reviewssource','classes'=>['forms__select'],'type'=>'form','form'=>$reviews['formitems']['source']],
				['id'=>$settings['key'].'-form-text','realid'=>'reviewstext','classes'=>['forms__select'],'type'=>'form','form'=>$reviews['formitems']['text']]
			]],
			['id'=>$settings['key'].'-form-rating','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['rating']],
			['id'=>$settings['key'].'-form-date','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['date']],
			['id'=>$settings['key'].'-form-published','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['published']],
			['id'=>$settings['key'].'-form-featured','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['featured']]
		];
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
	if (!is_array($reviews['entry']['author'] ?? [])) $reviews['entry']['author'] = [$site['default_language']=>$reviews['entry']['author']];
	if (!is_array($reviews['entry']['text'] ?? [])) $reviews['entry']['text'] = [$site['default_language']=>$reviews['entry']['text']];
	$reviews['entry']['lid'] = helper__json_convert($reviews['entry']['lid'] ?? ['all']);
	$reviews['rating_text'] = str_repeat('★',max(1,min(5,intval($reviews['entry']['rating'] ?? 0))));
	$reviews['state'] = !empty($reviews['entry']['published']) ? language__get($user['language'],'_reviews_published') : language__get($user['language'],'_reviews_draft');
	$reviews['subtitle'] = $reviews['rating_text'].' · '.$reviews['state'].' · '.format__date_relative(intval($reviews['entry']['date'] ?? $_SERVER['now']),'date',$user['language']);
	$reviews['title'] = trim((string) language__from_array($reviews['entry']['author'],$user['language']));
	if ($reviews['title'] == '') $reviews['title'] = language__get($user['language'],'_reviews_no_author');
	$reviews['preview'] = trim((string) language__from_array($reviews['entry']['text'],$user['language']));
	$reviews['row_items'] = [
		['id'=>$settings['key'].'-'.$reviews['id'].'-text','tag'=>'font','classes'=>['forms__item'],'description'=>htmlspecialchars(mb_substr($reviews['preview'],0,220),ENT_QUOTES,'UTF-8')],
		['id'=>$settings['key'].'-'.$reviews['id'].'-edit','description'=>language__get($user['language'],'_reviews_edit'),'actions'=>['load'=>['id'=>$reviews['id'],'form'=>true]]],
		['id'=>$settings['key'].'-'.$reviews['id'].'-delete','tag'=>'li','items'=>[
			['id'=>$settings['key'].'-'.$reviews['id'].'-delete-button','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button','data-confirmation'=>language__get($user['language'],'_ui_confirm_delete')],'description'=>language__get($user['language'],'_reviews_delete'),'actions'=>['load'=>['action'=>'delete','id'=>$reviews['id']]]]
		]]
	];
	$reviews['items'][] = ['id'=>$settings['key'].'-'.$reviews['id'].'-row','tag'=>'li','items'=>[
		create__dropdown($settings['key'].'-'.$reviews['id'].'-dropdown',$reviews['title'],create__list($settings['key'].'-'.$reviews['id'].'-list',$reviews['row_items'],['clear'=>true]),['subtitle'=>$reviews['subtitle'],'image'=>PAGEPATH.'/media/language/'.(in_array('all',$reviews['entry']['lid'],true) ? 'all' : $reviews['entry']['lid'][0]).'.png'])
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
