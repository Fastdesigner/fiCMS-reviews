<?php

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviews = [
	'filter'=>['page'=>1,'count'=>20,'sort'=>'date','direction'=>'DESC','search'=>[],'attributes'=>['published'=>'','featured'=>'','rating'=>'','lid'=>'','provider'=>'']],
	'filter_options'=>[],
	'output'=>['lists'=>[],'datalists'=>[],'result'=>[]],
	'entries'=>['reviews'=>[],'google'=>[]],
	'tablist'=>[],
	'attributes'=>[],
	'ratings'=>[],
	'filter_ratings'=>[],
	'languages'=>[],
	'providers'=>[],
	'google_accounts'=>[],
	'google_locations'=>[],
	'items'=>[],
	'select'=>'',
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
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

foreach ([1,2,3,4,5] as $reviews['rating']) $reviews['ratings'][$reviews['rating']] = ['value'=>$reviews['rating'],'name'=>language__get_parsed($user['language'],'_reviews_rating_option',['rating'=>$reviews['rating']])];
$reviews['inputs']['rating']['options'] = $reviews['ratings'];
$reviews['filter_ratings'] = array_merge([['name'=>language__get($user['language'],'_sort_all'),'value'=>'']],array_values($reviews['ratings']));
$reviews['languages'] = [
	['name'=>language__get($user['language'],'_sort_all'),'value'=>''],
	['name'=>language__get($user['language'],'_reviews_language_all'),'value'=>'all']
];
foreach ($site['installed_languages'] as $reviews['language']) $reviews['languages'][] = ['name'=>strtoupper($reviews['language']),'value'=>$reviews['language']];
$reviews['provider_options'] = [['name'=>language__get($user['language'],'_sort_all'),'value'=>'']];
foreach ($reviews['instance']->providers() as $reviews['provider']) $reviews['provider_options'][] = ['name'=>$reviews['provider']['name'],'value'=>$reviews['provider']['value']];

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$reviews['action'] = (string) ($_POST['action'] ?? '');
	$reviews['id'] = trim((string) ($_POST['id'] ?? ''));

	if ($reviews['action'] == 'delete') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->delete($reviews['id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save') {
		$reviews['output']['result'] = $reviews['instance']->saveFromPost($reviews['id'],$_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'google_save') {
		$reviews['output']['result'] = $reviews['instance']->saveGoogleFromPost($_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'google_sync') {
		$reviews['instance']->saveGoogleFromPost($_POST);
		$reviews['output']['result'] = $reviews['instance']->forceGoogleSync();
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'google_authorize') {
		$reviews['account_ref'] = trim((string) ($_POST['google_account_ref'] ?? 'default'));
		if ($reviews['account_ref'] == '') $reviews['account_ref'] = 'default';
		$reviews['output']['result'] = ['result'=>true,'redirect'=>PAGEPATH.'/oauth.php?action=authorize&provider=google&account='.rawurlencode($reviews['account_ref'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'load') {
		$reviews['entry'] = $reviews['id'] == 'new' ? $reviews['instance']->blank('new') : $reviews['instance']->find($reviews['id']);
		$reviews['select'] = $settings['key'].'-form-lingual';
		$reviews['inputs']['lid']['attributes'] = ['data-list'=>'installed-languages','data-selectcontrol'=>$reviews['select'],'data-all'=>'true'];
		$reviews['formitems'] = create__form_items($reviews['inputs'],$reviews['entry'],'reviews',$user['language']);
		$reviews['formitems']['published']['checked'] = intval($reviews['entry']['published'] ?? 0) == 1;
		$reviews['formitems']['featured']['checked'] = intval($reviews['entry']['featured'] ?? 0) == 1;
		$reviews['form'] = [];
		if (intval($reviews['entry']['read_only'] ?? 0) == 1 && ($reviews['entry']['provider'] ?? 'local') != 'local') {
			$reviews['form'][] = ['id'=>$settings['key'].'-form-provider','tag'=>'font','classes'=>['forms__item'],'description'=>htmlspecialchars(ucfirst($reviews['entry']['provider']).' · '.$reviews['entry']['author'].' · '.$reviews['entry']['text'],ENT_QUOTES,'UTF-8')];
			$reviews['form'][] = ['id'=>$settings['key'].'-form-lid','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['lid']];
			$reviews['form'][] = ['id'=>$settings['key'].'-form-published','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['published']];
			$reviews['form'][] = ['id'=>$settings['key'].'-form-featured','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['featured']];
		} else {
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
		}
		$reviews['headline'] = $reviews['id'] == 'new' ? language__get($user['language'],'_reviews_new') : language__get($user['language'],'_reviews_edit');
		$reviews['output']['lists'] = create__form($settings['form'],$reviews['form'],$reviews['headline'],language__get($user['language'],'_reviews_save'),['load'=>['action'=>'save','id'=>$reviews['entry']['id']]]);
		$reviews['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled'])) $_POST['handled'] = true;
}

if (isset($_SESSION['filter'][$settings['key']]) && is_array($_SESSION['filter'][$settings['key']])) $reviews['filter'] = array_replace_recursive($reviews['filter'],$_SESSION['filter'][$settings['key']]);
$reviews['admin'] = $reviews['instance']->admin($reviews['filter'],$user['language']);
$reviews['filter'] = $reviews['admin']['filter'];
$reviews['filter_options'] = [
	'page'=>['name'=>language__get($user['language'],'_sort_page'),'min'=>1,'max'=>$reviews['admin']['pages']],
	'search'=>['name'=>language__get($user['language'],'_sort_search')],
	'sort'=>['name'=>language__get($user['language'],'_sort_by'),'options'=>[
		['name'=>language__get($user['language'],'_sort_created'),'value'=>'date'],
		['name'=>language__get($user['language'],'_reviews_filter_sort_featured'),'value'=>'featured'],
		['name'=>language__get($user['language'],'_reviews_filter_sort_rating'),'value'=>'rating']
	]],
	'attributes'=>[
		'published'=>['name'=>language__get($user['language'],'_reviews_published'),'options'=>[
			['name'=>language__get($user['language'],'_sort_all'),'value'=>''],
			['name'=>language__get($user['language'],'_option_yes'),'value'=>'1'],
			['name'=>language__get($user['language'],'_option_no'),'value'=>'0']
		]],
		'featured'=>['name'=>language__get($user['language'],'_reviews_featured'),'options'=>[
			['name'=>language__get($user['language'],'_sort_all'),'value'=>''],
			['name'=>language__get($user['language'],'_option_yes'),'value'=>'1'],
			['name'=>language__get($user['language'],'_option_no'),'value'=>'0']
		]],
		'rating'=>['name'=>language__get($user['language'],'_reviews_rating'),'options'=>$reviews['filter_ratings']],
		'lid'=>['name'=>language__get($user['language'],'_reviews_lid'),'options'=>$reviews['languages']],
		'provider'=>['name'=>language__get($user['language'],'_reviews_provider'),'options'=>$reviews['provider_options']]
	]
];

foreach ($reviews['admin']['rows'] as $reviews['entry']) {
	$reviews['rating_text'] = str_repeat('★',max(1,min(5,intval($reviews['entry']['rating'] ?? 0))));
	$reviews['state'] = !empty($reviews['entry']['published']) ? language__get($user['language'],'_reviews_published') : language__get($user['language'],'_reviews_draft');
	$reviews['provider_text'] = ucfirst((string) ($reviews['entry']['provider'] ?? 'local'));
	$reviews['subtitle'] = $reviews['provider_text'].' · '.$reviews['rating_text'].' · '.$reviews['state'].' · '.format__date_relative(intval($reviews['entry']['date'] ?? $_SERVER['now']),'date',$user['language']);
	$reviews['title'] = trim((string) $reviews['entry']['author']);
	if ($reviews['title'] == '') $reviews['title'] = language__get($user['language'],'_reviews_no_author');
	$reviews['preview'] = trim((string) $reviews['entry']['text']);
	$reviews['row_items'] = [
		['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-text','tag'=>'font','classes'=>['forms__item'],'description'=>htmlspecialchars(mb_substr($reviews['preview'],0,220),ENT_QUOTES,'UTF-8')],
		['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-edit','description'=>language__get($user['language'],'_reviews_edit'),'actions'=>['load'=>['id'=>$reviews['entry']['id'],'form'=>true]]]
	];
	if (intval($reviews['entry']['read_only'] ?? 0) != 1) $reviews['row_items'][] = ['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-delete','tag'=>'li','items'=>[
		['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-delete-button','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button','data-confirmation'=>language__get($user['language'],'_ui_confirm_delete')],'description'=>language__get($user['language'],'_reviews_delete'),'actions'=>['load'=>['action'=>'delete','id'=>$reviews['entry']['id']]]]
	]];
	$reviews['items'][] = ['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-row','tag'=>'li','items'=>[
		create__dropdown($settings['key'].'-'.$reviews['entry']['id'].'-dropdown',$reviews['title'],create__list($settings['key'].'-'.$reviews['entry']['id'].'-list',$reviews['row_items'],['clear'=>true]),['subtitle'=>$reviews['subtitle'],'image'=>PAGEPATH.'/media/language/'.(in_array('all',$reviews['entry']['lid'],true) ? 'all' : $reviews['entry']['lid'][0]).'.png'])
	]];
}

if (empty($reviews['items'])) $reviews['items'][] = ['id'=>$settings['key'].'-empty','tag'=>'li','description'=>language__get($user['language'],'_sort_no_result'),'attributes'=>['data-noresult'=>'true']];
$reviews['items'][] = ['id'=>$settings['key'].'-new','tag'=>'li','description'=>language__get($user['language'],'_reviews_new'),'classes'=>['system-next'],'actions'=>['load'=>['id'=>'new','form'=>true]]];

$reviews['entries']['reviews'][] = create__filterlist($settings['key'],$reviews['filter_options'],$reviews['filter']);
$reviews['entries']['reviews'][] = create__list($settings['key'].'-list',$reviews['items'],['clear'=>true,'sort'=>true]);

$reviews['google'] = $reviews['instance']->googleStatus();
if ($reviews['google']['connected'] == 1) {
	$reviews['google_accounts'] = $reviews['instance']->googleAccounts();
	if (!empty($reviews['google_accounts']['items'])) $reviews['output']['datalists']['reviews-google-accounts'] = $reviews['google_accounts']['items'];
	if ($reviews['google']['account_name'] != '') {
		$reviews['google_locations'] = $reviews['instance']->googleLocations($reviews['google']['account_name']);
		if (!empty($reviews['google_locations']['items'])) $reviews['output']['datalists']['reviews-google-locations'] = $reviews['google_locations']['items'];
	}
}
$reviews['google_status'] = [
	['id'=>$settings['key'].'-google-status-oauth','description'=>language__get($user['language'],'_reviews_google_status_oauth'),'subtitle'=>$reviews['google']['oauth_available'] == 1 ? language__get($user['language'],'_option_yes') : language__get($user['language'],'_option_no')],
	['id'=>$settings['key'].'-google-status-connected','description'=>language__get($user['language'],'_reviews_google_status_connected'),'subtitle'=>$reviews['google']['connected'] == 1 ? language__get($user['language'],'_option_yes') : language__get($user['language'],'_option_no')],
	['id'=>$settings['key'].'-google-status-location','description'=>language__get($user['language'],'_reviews_google_status_location'),'subtitle'=>$reviews['google']['location_title'] != '' ? $reviews['google']['location_title'] : ($reviews['google']['location_name'] != '' ? $reviews['google']['location_name'] : language__get($user['language'],'_reviews_google_location_missing'))],
	['id'=>$settings['key'].'-google-status-sync','description'=>language__get($user['language'],'_reviews_google_status_last_sync'),'subtitle'=>$reviews['google']['last_sync'] > 0 ? format__date_relative($reviews['google']['last_sync'],'date',$user['language']) : language__get($user['language'],'_never')],
	['id'=>$settings['key'].'-google-status-result','description'=>language__get($user['language'],'_reviews_google_status_last_result'),'subtitle'=>language__get_parsed($user['language'],'_reviews_google_sync_result',['count'=>$reviews['google']['last_count'],'imported'=>$reviews['google']['last_imported'],'updated'=>$reviews['google']['last_updated']])]
];
if ($reviews['google']['last_error'] != '') $reviews['google_status'][] = ['id'=>$settings['key'].'-google-status-error','description'=>language__get($user['language'],'_reviews_google_status_error'),'subtitle'=>htmlspecialchars($reviews['google']['last_error'],ENT_QUOTES,'UTF-8')];
if ($reviews['google']['timer'] > $_SERVER['now']) $reviews['google_status'][] = ['id'=>$settings['key'].'-google-status-next','description'=>language__get($user['language'],'_reviews_google_status_next_sync'),'subtitle'=>format__date_relative($reviews['google']['timer'],'date',$user['language'])];

$reviews['google_form'] = [
	['id'=>$settings['key'].'-google-active','classes'=>['forms__item'],'type'=>'form','form'=>['type'=>'checkbox','option'=>'google_active','name'=>language__get($user['language'],'_reviews_google_active'),'checked'=>$reviews['google']['active'] == 1,'value'=>$reviews['google']['active']]],
	['id'=>$settings['key'].'-google-account-ref','classes'=>['forms__item'],'type'=>'form','form'=>['type'=>'input','option'=>'google_account_ref','name'=>language__get($user['language'],'_reviews_google_account_ref'),'value'=>$reviews['google']['account_ref']]],
	['id'=>$settings['key'].'-google-account-name','classes'=>['forms__item'],'type'=>'form','form'=>['type'=>'datalist','option'=>'google_account_name','name'=>language__get($user['language'],'_reviews_google_account_name'),'value'=>$reviews['google']['account_name'],'attributes'=>['data-list'=>'reviews-google-accounts','data-custom'=>'true']]],
	['id'=>$settings['key'].'-google-location-name','classes'=>['forms__item'],'type'=>'form','form'=>['type'=>'datalist','option'=>'google_location_name','name'=>language__get($user['language'],'_reviews_google_location_name'),'value'=>$reviews['google']['location_name'],'attributes'=>['data-list'=>'reviews-google-locations','data-custom'=>'true']]],
	['id'=>$settings['key'].'-google-location-title','classes'=>['forms__item'],'type'=>'form','form'=>['type'=>'input','option'=>'google_location_title','name'=>language__get($user['language'],'_reviews_google_location_title'),'value'=>$reviews['google']['location_title']]]
];
$reviews['google_actions'] = [
	['id'=>$settings['key'].'-google-authorize','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button'],'description'=>language__get($user['language'],'_reviews_google_connect'),'actions'=>['load'=>['action'=>'google_authorize']]],
	['id'=>$settings['key'].'-google-save','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button'],'description'=>language__get($user['language'],'_reviews_google_save'),'actions'=>['load'=>['action'=>'google_save']]],
	['id'=>$settings['key'].'-google-sync','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button'],'description'=>language__get($user['language'],'_reviews_google_sync'),'actions'=>['load'=>['action'=>'google_sync']]]
];
$reviews['entries']['google'][] = create__list($settings['key'].'-google-status',$reviews['google_status'],['clear'=>true]);
$reviews['entries']['google'][] = ['id'=>$settings['key'].'-google-form','tag'=>'form','classes'=>['forms__wrapper'],'items'=>array_merge($reviews['google_form'],[create__list($settings['key'].'-google-actions',$reviews['google_actions'],['clear'=>true])])];

$reviews['tablist'] = ['reviews'=>language__get($user['language'],'_reviews_tab_reviews'),'google'=>language__get($user['language'],'_reviews_tab_google')];
$reviews['attributes'] = ['reviews'=>['classes'=>['forms__wrapper']],'google'=>['classes'=>['forms__wrapper']]];
$reviews['tabs'] = create__tablist($settings['key'],$reviews['tablist'],$reviews['entries'],$reviews['attributes']);
$reviews['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','refresh'=>($_SERVER['now'] + 60),'items'=>[$reviews['tabs']['tabs'],$reviews['tabs']['panels']]];

foreach ($reviews['output'] as $key => $value) {
	if (empty($value)) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($reviews);
