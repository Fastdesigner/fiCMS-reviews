<?php

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviews = [
	'filter'=>['page'=>1,'count'=>20,'sort'=>'date','direction'=>'DESC','search'=>[],'attributes'=>['published'=>'','featured'=>'','rating'=>'','lid'=>'','provider'=>'']],
	'filter_options'=>[],
	'output'=>['lists'=>[],'datalists'=>[],'result'=>[]],
	'entries'=>['reviews'=>[],'integrations'=>[]],
	'tablist'=>[],
	'attributes'=>[],
	'ratings'=>[],
	'filter_ratings'=>[],
	'languages'=>[],
	'integration_items'=>[],
	'items'=>[],
	'select'=>'',
	'location_oauth_error'=>0,
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
$reviews['integration_provider_options'] = [];
foreach ($reviews['instance']->providerDefinitions() as $reviews['provider_key'] => $reviews['provider_definition']) $reviews['integration_provider_options'][] = ['name'=>$reviews['provider_definition']['name'],'value'=>$reviews['provider_key']];

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$reviews['action'] = (string) ($_POST['action'] ?? '');
	$reviews['id'] = trim((string) ($_POST['id'] ?? ''));
	$reviews['integration_load'] = $reviews['action'] == 'load' && substr($reviews['id'],0,12) == 'integration-';
	$reviews['integration_id'] = $reviews['integration_load'] ? substr($reviews['id'],12) : $reviews['id'];

	if ($reviews['action'] == 'delete') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->delete($reviews['id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save') {
		$reviews['output']['result'] = $reviews['instance']->saveFromPost($reviews['id'],$_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'delete_integration') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->deleteIntegration($reviews['integration_id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save_integration') {
		$reviews['output']['result'] = $reviews['instance']->saveIntegrationFromPost($reviews['integration_id'],$_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save_connect_integration') {
		$reviews['saved'] = $reviews['instance']->saveIntegrationFromPost($reviews['integration_id'],$_POST);
		$reviews['output']['result'] = !empty($reviews['saved']['result']) ? array_merge($reviews['instance']->connectIntegration($reviews['saved']['id']),['id'=>$reviews['saved']['id']]) : $reviews['saved'];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'connect_integration') {
		$reviews['output']['result'] = $reviews['instance']->connectIntegration($reviews['integration_id']);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'sync_integration') {
		$reviews['output']['result'] = $reviews['instance']->forceSyncIntegration($reviews['integration_id']);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'integration_status') {
		$reviews['status'] = $reviews['instance']->integrationStatus($reviews['integration_id']);
		$reviews['output']['result'] = ['result'=>true,'id'=>$reviews['integration_id'],'connected'=>$reviews['status']['connected'],'provider'=>$reviews['status']['provider'],'ready'=>$reviews['status']['ready']];
		if ($reviews['status']['connected'] == 1 && $reviews['status']['provider'] == 'google') {
			$reviews['status_locations'] = $reviews['instance']->googleLocationChoices($reviews['status']);
			$reviews['output']['result']['locations'] = $reviews['status_locations'];
		}
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['integration_load']) {
		$reviews['integration'] = $reviews['integration_id'] == 'new' ? $reviews['instance']->blankIntegration('new') : $reviews['instance']->integration($reviews['integration_id']);
		$reviews['status'] = $reviews['integration_id'] == 'new' ? $reviews['integration'] : $reviews['instance']->integrationStatus($reviews['integration_id']);
		$reviews['formitems'] = create__form_items([
			'active'=>['type'=>'checkbox'],
			'label'=>[],
			'provider'=>['required'=>true,'type'=>'select','options'=>$reviews['integration_provider_options']],
			'account_ref'=>['type'=>'hidden']
		],[
			'active'=>$reviews['integration']['active'],
			'label'=>$reviews['integration']['label'],
			'provider'=>$reviews['integration']['provider'],
			'account_ref'=>$reviews['integration']['account_ref']
		],'reviews_integration',$user['language']);
		foreach (['active','label','provider','account_ref'] as $reviews['field']) $reviews['formitems'][$reviews['field']]['option'] = 'integration_'.$reviews['field'];
		$reviews['formitems']['active']['checked'] = intval($reviews['integration']['active'] ?? 0) == 1;
		$reviews['integration_form'] = [
			['id'=>$settings['key'].'-integration-id','type'=>'form','classes'=>['forms__hidden'],'form'=>['type'=>'hidden','option'=>'integration_id','value'=>$reviews['integration']['id']]],
			['id'=>$settings['key'].'-integration-account-ref','type'=>'form','classes'=>['forms__hidden'],'form'=>$reviews['formitems']['account_ref']],
			['id'=>$settings['key'].'-integration-state','tag'=>'span','classes'=>['forms__hidden'],'attributes'=>['data-reviews-integration'=>$reviews['integration']['id'],'data-reviews-provider'=>$reviews['integration']['provider'],'data-reviews-connected'=>intval($reviews['status']['connected'] ?? 0)]],
			['id'=>$settings['key'].'-integration-label','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems']['label']],
			['id'=>$settings['key'].'-integration-provider','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems']['provider']]
		];
		if ($reviews['integration_id'] != 'new') array_splice($reviews['integration_form'],2,0,[['id'=>$settings['key'].'-integration-active','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems']['active']]]);
		if ($reviews['integration_id'] == 'new' || intval($reviews['status']['connected'] ?? 0) != 1) $reviews['integration_form'][] = ['id'=>$settings['key'].'-integration-connect-hint','tag'=>'font','classes'=>['forms__item','forms__hidden'],'attributes'=>['data-reviews-connect-hint'=>'true'],'description'=>language__get($user['language'],'_reviews_integration_popup_hint')];
		if ($reviews['integration']['provider'] == 'google' && $reviews['integration_id'] != 'new') {
			$reviews['locations'] = ['result'=>false,'items'=>[],'error'=>''];
			if ($reviews['status']['connected'] == 1) $reviews['locations'] = $reviews['instance']->googleLocationChoices($reviews['integration']);
			$reviews['location_error'] = strtolower(trim((string) ($reviews['locations']['error'] ?? '')));
			$reviews['location_oauth_error'] = $reviews['instance']->googleOAuthError($reviews['location_error']) ? 1 : 0;
			$reviews['connected_subtitle'] = $reviews['status']['connected'] == 1 ? language__get($user['language'],'_option_yes') : language__get($user['language'],'_option_no');
			if ($reviews['location_oauth_error'] == 1) $reviews['connected_subtitle'] .= ' · '.language__get($user['language'],'_reviews_integration_reconnect_required');
			$reviews['status_items'] = [
				['id'=>$settings['key'].'-integration-status-connected','description'=>language__get($user['language'],'_reviews_integration_connected'),'subtitle'=>$reviews['connected_subtitle']],
				['id'=>$settings['key'].'-integration-status-target','description'=>language__get($user['language'],'_reviews_integration_target'),'subtitle'=>trim((string) ($reviews['status']['target']['location_title'] ?? '')) != '' ? $reviews['status']['target']['location_title'] : language__get($user['language'],'_reviews_integration_target_missing')],
				['id'=>$settings['key'].'-integration-status-sync','description'=>language__get($user['language'],'_reviews_integration_last_sync'),'subtitle'=>$reviews['status']['ready'] == 1 && $reviews['status']['last_sync'] > 0 ? format__date_relative($reviews['status']['last_sync'],'date',$user['language']) : language__get($user['language'],'_never')],
				['id'=>$settings['key'].'-integration-status-result','description'=>language__get($user['language'],'_reviews_integration_last_result'),'subtitle'=>language__get_parsed($user['language'],'_reviews_integration_sync_result',['count'=>$reviews['status']['last_count'],'imported'=>$reviews['status']['last_imported'],'updated'=>$reviews['status']['last_updated']])]
			];
			if ($reviews['status']['last_error'] != '' && ($reviews['status']['last_error'] != 'google_location_missing' || $reviews['status']['ready'] == 1)) $reviews['status_items'][] = ['id'=>$settings['key'].'-integration-status-error','description'=>language__get($user['language'],'_reviews_integration_last_error'),'subtitle'=>htmlspecialchars($reviews['status']['last_error'],ENT_QUOTES,'UTF-8')];
			if ($reviews['status']['connected'] == 1 && trim((string) ($reviews['status']['target']['location_name'] ?? '')) == '' && !$reviews['locations']['result'] && $reviews['locations']['error'] != '') $reviews['status_items'][] = ['id'=>$settings['key'].'-integration-status-location-error','description'=>language__get($user['language'],'_reviews_google_location_name'),'subtitle'=>htmlspecialchars($reviews['locations']['error'],ENT_QUOTES,'UTF-8')];
			$reviews['integration_form'][] = create__dropdown($settings['key'].'-integration-status',language__get($user['language'],'_reviews_integration_status'),create__list($settings['key'].'-integration-status-list',$reviews['status_items'],['clear'=>true]),['attributes'=>['data-details-independent'=>'true']]);
			if ($reviews['status']['connected'] == 1 && $reviews['locations']['result'] && count($reviews['locations']['items']) > 0) {
				$reviews['location_value'] = json_encode($reviews['integration']['target'],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if (trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '' && count($reviews['locations']['items']) == 1) $reviews['location_value'] = $reviews['locations']['items'][0]['value'];
				$reviews['location_options'] = $reviews['locations']['items'];
				if (trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '') array_unshift($reviews['location_options'],['name'=>language__get($user['language'],'_reviews_google_location_select'),'value'=>'','disabled'=>true]);
				$reviews['integration_form'][] = ['id'=>$settings['key'].'-integration-google-location','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'select','option'=>'integration_google_location','name'=>language__get($user['language'],'_reviews_google_location_name'),'options'=>$reviews['location_options'],'value'=>$reviews['location_value']]];
			}
		}
		$reviews['connect_label'] = $reviews['integration_id'] != 'new' && $reviews['integration']['provider'] == 'google' && ($reviews['status']['connected'] != 1 || $reviews['location_oauth_error'] == 1) ? language__get($user['language'],$reviews['status']['connected'] == 1 ? '_reviews_integration_reconnect' : '_reviews_integration_connect') : false;
		$reviews['connect_action'] = $reviews['connect_label'] ? ['load'=>['action'=>'save_connect_integration','id'=>$reviews['integration']['id'],'target'=>'_blank','function'=>'reviews__connect']] : [];
		$reviews['submit_label'] = $reviews['integration_id'] == 'new' ? language__get($user['language'],'_reviews_integration_connect') : ($reviews['integration']['provider'] == 'google' && intval($reviews['status']['connected'] ?? 0) == 1 && trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '' && !empty($reviews['locations']['result']) ? language__get($user['language'],'_reviews_integration_save_location') : language__get($user['language'],'_settings_form_save'));
		$reviews['submit_action'] = ['load'=>['action'=>$reviews['integration_id'] == 'new' ? 'save_connect_integration' : 'save_integration','id'=>$reviews['integration_id']]];
		if ($reviews['integration_id'] == 'new') $reviews['submit_action']['load'] = array_merge($reviews['submit_action']['load'],['target'=>'_blank','function'=>'reviews__connect']);
		$reviews['integration_form'][] = ['id'=>$settings['key'].'-integration-oauth-overlay-data','tag'=>'span','classes'=>['forms__hidden'],'attributes'=>['data-reviews-oauth-provider'=>'Google','data-reviews-oauth-message'=>language__get($user['language'],'_reviews_integration_oauth_overlay'),'data-reviews-oauth-refresh'=>language__get($user['language'],'_reviews_integration_oauth_refresh'),'data-reviews-oauth-cancel'=>language__get($user['language'],'_reviews_integration_oauth_cancel')]];
		$reviews['output']['lists'] = create__form($settings['form'],$reviews['integration_form'],$reviews['integration_id'] == 'new' ? language__get($user['language'],'_reviews_integration_new') : language__get_parsed($user['language'],'_reviews_integration_edit',['label'=>$reviews['integration']['label']]),$reviews['submit_label'],$reviews['submit_action'],[],$reviews['connect_label'],$reviews['connect_action']);
		$reviews['output']['result'] = ['result'=>true];
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
	$reviews['item'] = ['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-row','tag'=>'li','description'=>$reviews['title'],'subtitle'=>$reviews['subtitle'],'image'=>PAGEPATH.'/media/language/'.(in_array('all',$reviews['entry']['lid'],true) ? 'all' : $reviews['entry']['lid'][0]).'.png','actions'=>['load'=>['id'=>$reviews['entry']['id'],'form'=>true]]];
	if (intval($reviews['entry']['read_only'] ?? 0) != 1) $reviews['item']['actions']['delete'] = ['id'=>$reviews['entry']['id'],'action'=>'delete'];
	$reviews['items'][] = $reviews['item'];
}

if (empty($reviews['items'])) $reviews['items'][] = ['id'=>$settings['key'].'-empty','tag'=>'li','description'=>language__get($user['language'],'_sort_no_result'),'attributes'=>['data-noresult'=>'true']];
$reviews['items'][] = ['id'=>$settings['key'].'-new','tag'=>'li','description'=>language__get($user['language'],'_reviews_new'),'classes'=>['system-next'],'actions'=>['load'=>['id'=>'new','form'=>true]]];

$reviews['entries']['reviews'][] = create__filterlist($settings['key'],$reviews['filter_options'],$reviews['filter']);
$reviews['entries']['reviews'][] = create__list($settings['key'].'-list',$reviews['items'],['clear'=>true,'sort'=>true]);

foreach ($reviews['instance']->integrations() as $reviews['integration']) {
	$reviews['status'] = $reviews['instance']->integrationStatus($reviews['integration']['id']);
	$reviews['provider_label'] = $reviews['instance']->providerDefinitions()[$reviews['integration']['provider']]['name'] ?? ucfirst($reviews['integration']['provider']);
	$reviews['provider_logo'] = $reviews['instance']->getProviderLogo($reviews['integration']['provider']);
	$reviews['status_oauth_error'] = $reviews['integration']['provider'] == 'google' && $reviews['instance']->googleOAuthError($reviews['status']['last_error'] ?? '') ? 1 : 0;
	$reviews['status_error'] = ($reviews['status_oauth_error'] == 1 || ($reviews['status']['last_error'] != '' && $reviews['status']['last_error'] != 'google_location_missing')) ? 1 : 0;
	$reviews['location_title'] = trim((string) ($reviews['status']['target']['location_title'] ?? ''));
	$reviews['subtitle'] = $reviews['location_title'] != '' && $reviews['status']['ready'] == 1 ? $reviews['location_title'] : '';
	$reviews['notify'] = false;
	if ($reviews['status_error'] == 1) {
		$reviews['subtitle'] = language__get($user['language'],'_reviews_integration_status_error');
		$reviews['notify'] = 'error';
	} else if ($reviews['status']['connected'] != 1) {
		$reviews['subtitle'] = language__get($user['language'],'_reviews_integration_connect_google');
		$reviews['notify'] = 'warning';
	} else if ($reviews['status']['ready'] != 1 || $reviews['location_title'] == '') {
		$reviews['subtitle'] = language__get($user['language'],'_reviews_integration_select_location');
		$reviews['notify'] = 'warning';
	}
	$reviews['last_sync'] = $reviews['status']['ready'] == 1 && $reviews['status']['last_sync'] > 0 ? format__date_relative($reviews['status']['last_sync'],'relative',$user['language']) : language__get($user['language'],'_never');
	$reviews['details'] = [
		['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-edit','description'=>language__get($user['language'],'_reviews_integration_edit_link'),'actions'=>['load'=>['id'=>'integration-'.$reviews['integration']['id'],'form'=>true]]],
		['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-sync-info','description'=>language__get($user['language'],'_reviews_integration_last_sync'),'subtitle'=>$reviews['last_sync']]
	];
	if ($reviews['status']['connected'] != 1 || $reviews['status_oauth_error'] == 1) array_splice($reviews['details'],1,0,[['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-connect','description'=>language__get($user['language'],$reviews['status']['connected'] == 1 ? '_reviews_integration_reconnect' : '_reviews_integration_connect'),'actions'=>['load'=>['action'=>'connect_integration','id'=>$reviews['integration']['id'],'target'=>'_blank']]]]);
	if ($reviews['status']['last_error'] != '' && ($reviews['status']['last_error'] != 'google_location_missing' || $reviews['status']['ready'] == 1)) $reviews['details'][] = ['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-error','description'=>language__get($user['language'],'_reviews_integration_last_error'),'subtitle'=>htmlspecialchars($reviews['status']['last_error'],ENT_QUOTES,'UTF-8')];
	$reviews['details'][] = ['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-delete','tag'=>'li','clear'=>true,'items'=>[
		['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-delete-button','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button','data-confirmation'=>language__get($user['language'],'_ui_confirm_delete')],'description'=>language__get($user['language'],'_reviews_integration_delete'),'actions'=>['load'=>['action'=>'delete_integration','id'=>$reviews['integration']['id']]]]
	]];
	$reviews['actions'] = [];
	if ($reviews['status']['ready'] == 1) $reviews['actions']['icons']['sync'] = ['systemicon'=>'refresh','action'=>'sync_integration','id'=>$reviews['integration']['id'],'title'=>language__get($user['language'],'_reviews_integration_sync')];
	$reviews['attributes'] = ['class'=>'system-next'];
	if ($reviews['notify'] !== false) $reviews['attributes']['data-notify'] = $reviews['notify'];
	$reviews['integration_items'][] = ['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-row','tag'=>'li','items'=>[
		create__dropdown($settings['key'].'-'.$reviews['integration']['id'].'-dropdown',$reviews['integration']['label'],create__list($settings['key'].'-'.$reviews['integration']['id'].'-list',$reviews['details'],['clear'=>true]),array_merge(['subtitle'=>$reviews['subtitle'],'attributes'=>$reviews['attributes'],'actions'=>$reviews['actions']],$reviews['provider_logo'] != '' ? ['image'=>$reviews['provider_logo']] : []))
	]];
}
$reviews['integration_items'][] = ['id'=>$settings['key'].'-integration-new','tag'=>'li','description'=>language__get($user['language'],'_reviews_integration_new'),'classes'=>['system-next'],'actions'=>['load'=>['id'=>'integration-new','form'=>true]]];
$reviews['entries']['integrations'][] = create__list($settings['key'].'-integrations-list',$reviews['integration_items'],['clear'=>true,'sort'=>true]);

$reviews['tablist'] = ['reviews'=>language__get($user['language'],'_reviews_tab_reviews'),'integrations'=>language__get($user['language'],'_reviews_tab_integrations')];
$reviews['attributes'] = ['reviews'=>['classes'=>['forms__wrapper']],'integrations'=>['classes'=>['forms__wrapper']]];
$reviews['tabs'] = create__tablist($settings['key'],$reviews['tablist'],$reviews['entries'],$reviews['attributes']);
$reviews['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','refresh'=>($_SERVER['now'] + 60),'items'=>[$reviews['tabs']['tabs'],$reviews['tabs']['panels']]];

foreach ($reviews['output'] as $key => $value) {
	if (empty($value)) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($reviews);
