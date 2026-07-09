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
		'provider'=>['type'=>'select','required'=>true,'options'=>[]],
		'external_url'=>['format'=>'url'],
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
$reviews['review_provider_options'] = array_values($reviews['instance']->providers());
$reviews['inputs']['provider']['options'] = $reviews['review_provider_options'];

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$reviews['action'] = (string) ($_POST['action'] ?? '');
	$reviews['id'] = trim((string) ($_POST['id'] ?? ''));
	$reviews['integration_load'] = $reviews['action'] == 'load' && substr($reviews['id'],0,12) == 'integration-';
	$reviews['provider_load'] = $reviews['action'] == 'load' && substr($reviews['id'],0,9) == 'provider-';
	$reviews['integration_id'] = $reviews['integration_load'] ? substr($reviews['id'],12) : $reviews['id'];
	$reviews['provider_id'] = $reviews['provider_load'] ? substr($reviews['id'],9) : $reviews['id'];

	if ($reviews['action'] == 'delete') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->delete($reviews['id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save') {
		$reviews['output']['result'] = $reviews['instance']->saveFromPost($reviews['id'],$_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'ac') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->setPublished($reviews['id'],$_POST['value'] ?? 0)];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'delete_integration') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->deleteIntegration($reviews['integration_id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'delete_provider') {
		$reviews['output']['result'] = ['result'=>$reviews['instance']->deleteProvider($reviews['provider_id'])];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save_provider') {
		$reviews['output']['result'] = $reviews['instance']->saveProviderFromPost($reviews['provider_id'],$_POST);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'save_integration') {
		$reviews['saved'] = $reviews['instance']->saveIntegrationFromPost($reviews['integration_id'],$_POST);
		if (empty($reviews['saved']['result'])) {
			$reviews['output']['result'] = $reviews['saved'];
			$_POST['handled'] = true;
		} else {
			$reviews['integration_id'] = $reviews['saved']['id'];
			$reviews['id'] = 'integration-'.$reviews['integration_id'];
			$reviews['integration_load'] = true;
			$reviews['action'] = 'load';
		}
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
		$reviews['requirements'] = $reviews['instance']->providerRequirements($reviews['status']['provider'],$reviews['status']);
		$reviews['output']['result'] = ['result'=>true,'id'=>$reviews['integration_id'],'connected'=>$reviews['status']['connected'],'provider'=>$reviews['status']['provider'],'ready'=>$reviews['status']['ready']];
		if ($reviews['status']['connected'] == 1 && !empty($reviews['requirements']['location_choices'])) {
			$reviews['status_locations'] = $reviews['instance']->providerLocationChoices($reviews['status']);
			$reviews['output']['result']['locations'] = $reviews['status_locations'];
		}
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['provider_load']) {
		$reviews['provider'] = $reviews['provider_id'] == 'new' ? $reviews['instance']->blankProvider('new') : $reviews['instance']->providerSetting($reviews['provider_id']);
		$reviews['provider_inputs'] = [
			'id'=>['required'=>true,'option'=>'provider_id','attributes'=>['pattern'=>'[a-z0-9_-]+']],
			'label'=>['required'=>true,'option'=>'provider_label'],
			'active'=>['type'=>'checkbox','option'=>'provider_active']
		];
		if ($reviews['provider']['id'] != 'new') $reviews['provider_inputs']['id']['attributes']['readonly'] = 'readonly';
		$reviews['provider_formitems'] = create__form_items($reviews['provider_inputs'],$reviews['provider'],'reviews_provider',$user['language']);
		$reviews['provider_formitems']['active']['checked'] = intval($reviews['provider']['active'] ?? 0) == 1;
		$reviews['provider_icon'] = $reviews['provider']['id'] != 'new' ? $reviews['instance']->providerIconJson($reviews['provider']['id']) : false;
		$reviews['provider_current_icon'] = $reviews['provider']['id'] != 'new' ? $reviews['instance']->getProviderLogo($reviews['provider']['id']) : '';
		$reviews['provider_data_form'] = [
			['id'=>$settings['key'].'-provider-form-id','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['provider_formitems']['id']],
			['id'=>$settings['key'].'-provider-form-label','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['provider_formitems']['label']],
			['id'=>$settings['key'].'-provider-form-active','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['provider_formitems']['active']]
		];
		$reviews['provider_icon_form'] = [];
		if ($reviews['provider_current_icon'] != '') $reviews['provider_icon_form'][] = ['id'=>$settings['key'].'-provider-current-icon','tag'=>'li','description'=>language__get($user['language'],'_reviews_provider_current_icon'),'image'=>$reviews['provider_current_icon']];
		$reviews['provider_icon_form'][] = ['id'=>$settings['key'].'-provider-form-icon','type'=>'form','classes'=>['forms__item','img--obj-contain'],'form'=>['type'=>'media','name'=>language__get($user['language'],'_reviews_provider_icon'),'option'=>'provider_icon','value'=>$reviews['provider_icon']]];
		$reviews['provider_form_tablist'] = [
			'data'=>language__get($user['language'],'_reviews_provider_tab_data'),
			'icon'=>language__get($user['language'],'_reviews_provider_tab_icon')
		];
		$reviews['provider_form_entries'] = ['data'=>$reviews['provider_data_form'],'icon'=>$reviews['provider_icon_form']];
		$reviews['provider_form_attrs'] = ['data'=>['classes'=>['forms__wrapper']],'icon'=>['classes'=>['forms__wrapper']]];
		$reviews['provider_tabs'] = create__tablist($settings['key'].'-provider-form-tabs',$reviews['provider_form_tablist'],$reviews['provider_form_entries'],$reviews['provider_form_attrs']);
		$reviews['provider_form'] = [
			['id'=>$settings['key'].'-provider-form-tabs','classes'=>['forms__wrapper'],'items'=>[$reviews['provider_tabs']['tabs'],$reviews['provider_tabs']['panels']]]
		];
		$reviews['headline'] = $reviews['provider']['id'] == 'new' ? language__get($user['language'],'_reviews_provider_new') : language__get_parsed($user['language'],'_reviews_provider_edit',['label'=>$reviews['provider']['label']]);
		$reviews['output']['lists'] = create__form($settings['form'],$reviews['provider_form'],$reviews['headline'],language__get($user['language'],'_settings_form_save'),['load'=>['action'=>'save_provider','id'=>$reviews['provider']['id']]]);
		$reviews['output']['result'] = ['result'=>true];
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['integration_load']) {
		$reviews['integration'] = $reviews['integration_id'] == 'new' ? $reviews['instance']->blankIntegration('new') : $reviews['instance']->integration($reviews['integration_id']);
		if (isset($_POST['integration_label'])) $reviews['integration']['label'] = trim((string) $_POST['integration_label']);
		if (isset($_POST['integration_active'])) $reviews['integration']['active'] = !empty($_POST['integration_active']) && !in_array(strtolower(trim((string) $_POST['integration_active'])),['0','false','off','no'],true) ? 1 : 0;
		$reviews['definitions'] = $reviews['instance']->providerDefinitions();
		$reviews['external_provider'] = $reviews['integration_id'] == 'new' || isset($reviews['definitions'][$reviews['integration']['provider']]) ? 1 : 0;
		if (isset($_POST['integration_external_provider'])) $reviews['external_provider'] = in_array(strtolower(trim((string) $_POST['integration_external_provider'])),['','0','false','off','no'],true) ? 0 : 1;
		$reviews['oauth_selection'] = $reviews['instance']->oauthIntegrationFromValue($_POST['integration_oauth_account'] ?? '');
		$reviews['provider_preview'] = !empty($reviews['oauth_selection']) && $reviews['oauth_selection']['provider'] != $reviews['integration']['provider'];
		$reviews['account_preview'] = (!empty($reviews['oauth_selection']) && $reviews['oauth_selection']['account_ref'] != $reviews['integration']['account_ref']) || (isset($_POST['integration_account_ref']) && (trim((string) $_POST['integration_account_ref']) ?: 'default') != $reviews['integration']['account_ref']);
		if ($reviews['external_provider'] == 1 && !empty($reviews['oauth_selection'])) {
			$reviews['integration']['provider'] = $reviews['oauth_selection']['provider'];
			$reviews['integration']['account_ref'] = $reviews['oauth_selection']['account_ref'];
		}
		if ($reviews['external_provider'] == 1 && isset($_POST['integration_account_ref'])) $reviews['integration']['account_ref'] = trim((string) $_POST['integration_account_ref']) ?: 'default';
		$reviews['status'] = $reviews['integration_id'] == 'new' || $reviews['provider_preview'] || $reviews['account_preview'] ? $reviews['instance']->previewIntegrationStatus($reviews['integration']) : $reviews['instance']->integrationStatus($reviews['integration_id']);
		$reviews['requirements'] = $reviews['instance']->providerRequirements($reviews['integration']['provider'],$reviews['integration']);
		$reviews['locations'] = ['result'=>false,'items'=>[],'error'=>''];
		$reviews['oauth_value'] = $reviews['external_provider'] == 1 && !empty($reviews['requirements']['oauth']) && ($reviews['integration_id'] != 'new' || !empty($reviews['oauth_selection'])) ? $reviews['integration']['provider'].'|'.$reviews['integration']['account_ref'] : '';
		if (!empty($reviews['requirements']['location_choices']) && $reviews['status']['connected'] == 1 && $reviews['oauth_value'] != '') $reviews['locations'] = $reviews['instance']->providerLocationChoices($reviews['integration']);
		$reviews['location_error'] = strtolower(trim((string) ($reviews['locations']['error'] ?? '')));
		$reviews['location_oauth_error'] = $reviews['instance']->providerOAuthError($reviews['integration']['provider'],$reviews['location_error']) ? 1 : 0;
		$reviews['oauth_options'] = $reviews['external_provider'] == 1 ? $reviews['instance']->oauthIntegrationOptions($reviews['integration_id']) : [];
		if ($reviews['oauth_value'] != '' && !in_array($reviews['oauth_value'],array_column($reviews['oauth_options'],'value'),true)) array_unshift($reviews['oauth_options'],['name'=>$reviews['requirements']['oauth_provider'].' / '.$reviews['integration']['account_ref'],'value'=>$reviews['oauth_value']]);
		$reviews['integration_inputs'] = [
			'active'=>['type'=>'checkbox'],
			'label'=>[],
			'external_provider'=>['type'=>'checkbox','change'=>['function'=>'reviews__provider_change']]
		];
		if ($reviews['external_provider'] == 1 && !empty($reviews['oauth_options'])) $reviews['integration_inputs']['oauth_account'] = ['type'=>'select','required'=>true,'options'=>$reviews['oauth_options'],'change'=>['function'=>'reviews__provider_change']];
		$reviews['integration_inputs']['provider'] = ['type'=>'hidden'];
		$reviews['integration_inputs']['account_ref'] = ['type'=>'hidden'];
		if ($reviews['external_provider'] == 1) foreach (($reviews['requirements']['form_fields'] ?? []) as $reviews['field'] => $reviews['definition']) $reviews['integration_inputs'][$reviews['field']] = $reviews['definition'];
		$reviews['integration_values'] = [
			'active'=>$reviews['integration']['active'],
			'label'=>$reviews['integration']['label'],
			'external_provider'=>$reviews['external_provider'],
			'oauth_account'=>$reviews['oauth_value'],
			'provider'=>$reviews['integration']['provider'],
			'account_ref'=>$reviews['integration']['account_ref']
		];
		foreach (($reviews['requirements']['form_values'] ?? []) as $reviews['field'] => $reviews['value']) $reviews['integration_values'][$reviews['field']] = $reviews['value'];
		foreach (array_keys($reviews['integration_inputs']) as $reviews['field']) $reviews['integration_inputs'][$reviews['field']]['option'] = 'integration_'.$reviews['field'];
		$reviews['formitems'] = create__form_items($reviews['integration_inputs'],$reviews['integration_values'],'reviews_integration',$user['language']);
		$reviews['formitems']['active']['checked'] = intval($reviews['integration']['active'] ?? 0) == 1;
		$reviews['formitems']['external_provider']['checked'] = $reviews['external_provider'] == 1;
		$reviews['external_notify'] = false;
		if ($reviews['external_provider'] == 1 && $reviews['oauth_value'] == '') $reviews['external_notify'] = 'warning';
		if ($reviews['external_provider'] == 1 && !empty($reviews['requirements']['connect']) && intval($reviews['status']['connected'] ?? 0) != 1) $reviews['external_notify'] = 'warning';
		if ($reviews['location_oauth_error'] == 1 || trim((string) ($reviews['status']['last_error'] ?? '')) != '') $reviews['external_notify'] = 'error';
		$reviews['integration_form'] = [
			['id'=>$settings['key'].'-integration-id','type'=>'form','classes'=>['forms__hidden'],'form'=>['type'=>'hidden','option'=>'integration_id','value'=>$reviews['integration']['id']]],
			['id'=>$settings['key'].'-integration-state','tag'=>'span','classes'=>['forms__hidden'],'attributes'=>['data-reviews-integration'=>$reviews['integration']['id'],'data-reviews-provider'=>$reviews['integration']['provider'],'data-reviews-connected'=>intval($reviews['status']['connected'] ?? 0)]],
			['id'=>$settings['key'].'-integration-provider','type'=>'form','classes'=>['forms__hidden'],'form'=>$reviews['formitems']['provider']],
			['id'=>$settings['key'].'-integration-account-ref','type'=>'form','classes'=>['forms__hidden'],'form'=>$reviews['formitems']['account_ref']],
			['id'=>$settings['key'].'-integration-label','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems']['label']]
		];
		$reviews['connection_form'] = [
			['id'=>$settings['key'].'-integration-active','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems']['active']]
		];
		$reviews['external_provider_content'] = ['id'=>$settings['key'].'-integration-external-provider-content','tag'=>'ul','classes'=>['forms__wrapper'],'items'=>[]];
		if ($reviews['external_provider'] == 1 && (!empty($reviews['requirements']['oauth']) || $reviews['integration_id'] == 'new')) $reviews['external_provider_content']['items'][] = ['id'=>$settings['key'].'-integration-oauth-hint','tag'=>'p','classes'=>['forms__item'],'description'=>language__get($user['language'],'_reviews_integration_oauth_hint')];
		if (isset($reviews['formitems']['oauth_account'])) $reviews['external_provider_content']['items'][] = ['id'=>$settings['key'].'-integration-oauth-account','type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems']['oauth_account']];
		if ($reviews['external_provider'] == 1 && !empty($reviews['requirements']['oauth']) && !isset($reviews['formitems']['oauth_account'])) $reviews['external_provider_content']['items'][] = [
			'id'=>$settings['key'].'-integration-oauth-link',
			'tag'=>'button',
			'classes'=>['system-button','forms__item'],
			'attributes'=>['type'=>'button'],
			'description'=>language__get($user['language'],'_reviews_integration_oauth_system_create'),
			'actions'=>['load'=>['function'=>'reviews__open_integrations']]
		];
		foreach (($reviews['requirements']['form_fields'] ?? []) as $reviews['field'] => $reviews['definition']) {
			if (!isset($reviews['formitems'][$reviews['field']])) continue;
			if (($reviews['formitems'][$reviews['field']]['type'] ?? '') != 'hidden') continue;
			array_splice($reviews['integration_form'],1,0,[['id'=>$settings['key'].'-integration-'.$reviews['field'],'type'=>'form','classes'=>['forms__hidden'],'form'=>$reviews['formitems'][$reviews['field']]]]);
		}
		if ($reviews['external_provider'] == 1) foreach (($reviews['requirements']['form_fields'] ?? []) as $reviews['field'] => $reviews['definition']) if (($reviews['formitems'][$reviews['field']]['type'] ?? '') != 'hidden') $reviews['external_provider_content']['items'][] = ['id'=>$settings['key'].'-integration-'.$reviews['field'],'type'=>'form','classes'=>['forms__item'],'form'=>$reviews['formitems'][$reviews['field']]];
		if ($reviews['external_provider'] == 1 && !empty($reviews['requirements']['location_choices']) && $reviews['status']['connected'] == 1 && $reviews['oauth_value'] != '' && $reviews['locations']['result'] && count($reviews['locations']['items']) > 0) {
			$reviews['location_value'] = '';
			foreach ($reviews['locations']['items'] as $reviews['location_option']) {
				$reviews['location_decoded'] = $reviews['instance']->decode($reviews['location_option']['value'] ?? '');
				if (!is_array($reviews['location_decoded'])) continue;
				if (trim((string) ($reviews['integration']['target']['page_id'] ?? '')) != '' && trim((string) ($reviews['location_decoded']['page_id'] ?? '')) == trim((string) ($reviews['integration']['target']['page_id'] ?? ''))) $reviews['location_value'] = $reviews['location_option']['value'];
				if (trim((string) ($reviews['integration']['target']['location_name'] ?? '')) != '' && trim((string) ($reviews['location_decoded']['location_name'] ?? '')) == trim((string) ($reviews['integration']['target']['location_name'] ?? ''))) $reviews['location_value'] = $reviews['location_option']['value'];
				if (trim((string) ($reviews['integration']['target']['location_name'] ?? '')) != '' && trim((string) ($reviews['location_decoded']['page_name'] ?? '')) == trim((string) ($reviews['integration']['target']['location_name'] ?? ''))) $reviews['location_value'] = $reviews['location_option']['value'];
				if ($reviews['location_value'] != '') break;
			}
			if ($reviews['location_value'] == '' && trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '' && count($reviews['locations']['items']) == 1) $reviews['location_value'] = $reviews['locations']['items'][0]['value'];
			$reviews['location_options'] = $reviews['locations']['items'];
			if ($reviews['location_value'] == '') array_unshift($reviews['location_options'],['name'=>language__get($user['language'],$reviews['requirements']['location_select']['empty'] ?? '_reviews_integration_target_missing'),'value'=>'','disabled'=>true]);
			$reviews['external_provider_content']['items'][] = ['id'=>$settings['key'].'-integration-'.$reviews['integration']['provider'].'-location','type'=>'form','classes'=>['forms__item'],'form'=>['type'=>'select','option'=>$reviews['requirements']['location_select']['option'] ?? 'integration_location','name'=>language__get($user['language'],$reviews['requirements']['location_select']['label'] ?? '_reviews_integration_target'),'options'=>$reviews['location_options'],'value'=>$reviews['location_value']]];
		}
		$reviews['connection_form'][] = create__dropdown($settings['key'].'-integration-external-provider-dropdown',language__get($user['language'],'_reviews_integration_external_provider'),$reviews['external_provider_content'],array_merge(['attributes'=>['data-details-independent'=>'true'],'actions'=>['ac'=>['function'=>'reviews__provider_toggle','action'=>'load','name'=>'integration_external_provider','checked'=>$reviews['external_provider'] == 1]]],$reviews['external_notify'] === false ? [] : ['notify'=>$reviews['external_notify']]));
		$reviews['provider_current_icon'] = $reviews['external_provider'] == 1 ? $reviews['instance']->getProviderLogo($reviews['integration']['provider']) : '';
		$reviews['provider_icon'] = $reviews['external_provider'] == 1 ? $reviews['instance']->providerIconJson($reviews['integration']['provider']) : false;
		$reviews['logo_item'] = [
			'id'=>$settings['key'].'-integration-provider-icon',
			'type'=>'form',
			'classes'=>['forms__item','img--obj-contain'],
			'form'=>['type'=>'media','name'=>language__get($user['language'],'_reviews_provider_icon'),'option'=>'provider_icon','value'=>$reviews['provider_icon']]
		];
		if ($reviews['provider_current_icon'] != '') $reviews['logo_item']['form']['attributes'] = ['style'=>'--_image:url('.htmlspecialchars($reviews['provider_current_icon'],ENT_QUOTES,'UTF-8').') center / contain no-repeat var(--ficms-color-contrast-1)'];
		$reviews['logo_form'] = [$reviews['logo_item']];
		$reviews['stats_graph'] = ['series'=>['reviews'=>[]],'points'=>[]];
		$reviews['stats_count'] = 0;
		$reviews['stats_sum'] = 0;
		$reviews['stats_published'] = 0;
		$reviews['stats_start'] = strtotime('-29 days',strtotime('today',intval($_SERVER['now'] ?? time())));
		for ($reviews['stats_day'] = 0; $reviews['stats_day'] < 30; $reviews['stats_day']++) $reviews['stats_graph']['points'][date('Y-m-d',$reviews['stats_start'] + ($reviews['stats_day'] * 86400))] = ['label'=>date('d.m.',$reviews['stats_start'] + ($reviews['stats_day'] * 86400)),'data'=>['reviews'=>0]];
		foreach ($reviews['instance']->all() as $reviews['review_id'] => $reviews['review_entry']) {
			if (($reviews['review_entry']['provider'] ?? '') != $reviews['integration']['provider']) continue;
			$reviews['stats_count']++;
			if (intval($reviews['review_entry']['published'] ?? 0) == 1) {
				$reviews['stats_published']++;
				$reviews['stats_sum'] += max(1,min(5,intval($reviews['review_entry']['rating'] ?? 0)));
			}
			$reviews['review_day'] = date('Y-m-d',intval($reviews['review_entry']['created'] ?? ($reviews['review_entry']['date'] ?? 0)));
			if (isset($reviews['stats_graph']['points'][$reviews['review_day']])) $reviews['stats_graph']['points'][$reviews['review_day']]['data']['reviews']++;
		}
		$reviews['statistics_form'] = [
			['id'=>$settings['key'].'-integration-statistics-graph','type'=>'statistics','chart'=>'graph','attributes'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_reviews_integration_new_reviews_30_days')],'values'=>statistics__format_graph($user['language'],['series'=>$reviews['stats_graph']['series'],'points'=>array_values($reviews['stats_graph']['points'])],['reviews'=>'_reports_reviews_new'],['gridLines'=>4,'legend'=>false])],
			['id'=>$settings['key'].'-integration-statistics-count','type'=>'statistics','chart'=>'info','values'=>['value'=>$reviews['stats_count'],'label'=>language__get($user['language'],'_reviews_integration_reviews_count')]],
			['id'=>$settings['key'].'-integration-statistics-rating','type'=>'statistics','chart'=>'info','values'=>['value'=>$reviews['stats_published'] > 0 ? round($reviews['stats_sum'] / $reviews['stats_published'],1) : '0','label'=>language__get($user['language'],'_reviews_integration_provider_rating')]]
		];
		$reviews['integration_form_tabs'] = create__tablist($settings['key'].'-integration-form-tabs',[
			'connection'=>language__get($user['language'],'_reviews_integration_tab_connection'),
			'logo'=>language__get($user['language'],'_reviews_integration_tab_logo'),
			'statistics'=>language__get($user['language'],'_reviews_integration_tab_statistics')
		],[
			'connection'=>$reviews['connection_form'],
			'logo'=>$reviews['logo_form'],
			'statistics'=>[
				['id'=>$settings['key'].'-integration-statistics','classes'=>['statistics__wrapper'],'items'=>$reviews['statistics_form']]
			]
		],[
			'connection'=>['classes'=>['forms__wrapper']],
			'logo'=>['classes'=>['forms__wrapper']],
			'statistics'=>['classes'=>['forms__wrapper']]
		]);
		$reviews['integration_form'][] = ['id'=>$settings['key'].'-integration-tabs','classes'=>['forms__wrapper'],'items'=>[$reviews['integration_form_tabs']['tabs'],$reviews['integration_form_tabs']['panels']]];
		$reviews['connect_label'] = $reviews['external_provider'] == 1 && !empty($reviews['requirements']['connect']) && ($reviews['status']['connected'] != 1 || $reviews['location_oauth_error'] == 1) ? language__get($user['language'],'_reviews_integration_manage_oauth') : false;
		$reviews['connect_action'] = $reviews['connect_label'] ? ['load'=>['function'=>'reviews__open_integrations']] : [];
		$reviews['submit_label'] = $reviews['external_provider'] == 1 && !empty($reviews['requirements']['oauth']) && $reviews['oauth_value'] == '' ? false : (!empty($reviews['requirements']['location_choices']) && intval($reviews['status']['connected'] ?? 0) == 1 && trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '' && !empty($reviews['locations']['result']) ? language__get($user['language'],'_reviews_integration_save_location') : language__get($user['language'],'_settings_form_save'));
		$reviews['submit_action'] = ['load'=>['action'=>'save_integration','id'=>$reviews['integration_id']]];
		$reviews['output']['lists'] = create__form($settings['form'],$reviews['integration_form'],$reviews['integration_id'] == 'new' ? language__get($user['language'],'_reviews_integration_new') : language__get_parsed($user['language'],'_reviews_integration_edit',['label'=>$reviews['integration']['label']]),$reviews['submit_label'],$reviews['submit_action'],[],$reviews['connect_label'],$reviews['connect_action']);
		$_POST['handled'] = true;
	}

	if (!isset($_POST['handled']) && $reviews['action'] == 'load') {
		$reviews['entry'] = $reviews['id'] == 'new' ? $reviews['instance']->blank('new') : $reviews['instance']->find($reviews['id']);
		$reviews['select'] = $settings['key'].'-form-lingual';
		$reviews['inputs']['lid']['attributes'] = ['data-list'=>'installed-languages','data-selectcontrol'=>$reviews['select'],'data-all'=>'true'];
		$reviews['formitems'] = create__form_items($reviews['inputs'],$reviews['entry'],'reviews',$user['language']);
		$reviews['formitems']['published']['checked'] = intval($reviews['entry']['published'] ?? 0) == 1;
		$reviews['formitems']['featured']['checked'] = intval($reviews['entry']['featured'] ?? 0) == 1;
		if (intval($reviews['entry']['read_only'] ?? 0) == 1 && ($reviews['entry']['provider'] ?? 'local') != 'local') {
			foreach (['author','source','provider','external_url','text','rating','date'] as $reviews['disabled_field']) $reviews['formitems'][$reviews['disabled_field']]['attributes']['disabled'] = 'disabled';
			unset($reviews['disabled_field']);
		}
		$reviews['form'] = [
			['id'=>$settings['key'].'-form-author','realid'=>'reviewsauthor','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['author']],
			['id'=>$settings['key'].'-form-source','realid'=>'reviewssource','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['source']],
			['id'=>$settings['key'].'-form-provider','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['provider']],
			['id'=>$settings['key'].'-form-external-url','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['external_url']],
			['id'=>$settings['key'].'-form-lid','classes'=>['forms__item'],'type'=>'form','form'=>$reviews['formitems']['lid']],
			['id'=>$reviews['select'],'realid'=>$reviews['select'],'classes'=>['forms__wrapper'],'attributes'=>['data-select'=>$user['language'],'data-selecttype'=>'language','data-selectall'=>json_encode($site['installed_languages']),'data-selectoptions'=>json_encode($site['installed_languages'])],'items'=>[
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

$reviews['provider_definitions'] = $reviews['instance']->providerDefinitions();
foreach ($reviews['admin']['rows'] as $reviews['entry']) {
	$reviews['rating_text'] = str_repeat('★',max(1,min(5,intval($reviews['entry']['rating'] ?? 0))));
	$reviews['state'] = !empty($reviews['entry']['published']) ? language__get($user['language'],'_reviews_published') : language__get($user['language'],'_reviews_draft');
	$reviews['provider_text'] = $reviews['provider_definitions'][$reviews['entry']['provider'] ?? 'local']['name'] ?? ucfirst((string) ($reviews['entry']['provider'] ?? 'local'));
	$reviews['subtitle'] = $reviews['provider_text'].' · '.$reviews['rating_text'].' · '.$reviews['state'].' · '.format__date_relative(intval($reviews['entry']['date'] ?? $_SERVER['now']),'date',$user['language']);
	$reviews['title'] = trim((string) $reviews['entry']['author']);
	if ($reviews['title'] == '') $reviews['title'] = language__get($user['language'],'_reviews_no_author');
	$reviews['item'] = ['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-row','tag'=>'li','description'=>$reviews['title'],'subtitle'=>$reviews['subtitle'],'image'=>PAGEPATH.'/media/language/'.(in_array('all',$reviews['entry']['lid'],true) ? 'all' : $reviews['entry']['lid'][0]).'.png','actions'=>['load'=>['id'=>$reviews['entry']['id'],'form'=>true]]];
	$reviews['item']['actions']['ac'] = ['id'=>$reviews['entry']['id'],'action'=>'ac','name'=>$settings['key'].'-'.$reviews['entry']['id'],'checked'=>!empty($reviews['entry']['published']),'dropdown_sync'=>false];
	if (intval($reviews['entry']['read_only'] ?? 0) != 1) $reviews['item']['actions']['delete'] = ['id'=>$reviews['entry']['id'],'action'=>'delete'];
	$reviews['items'][] = $reviews['item'];
}

if (empty($reviews['items'])) $reviews['items'][] = ['id'=>$settings['key'].'-empty','tag'=>'li','description'=>language__get($user['language'],'_sort_no_result'),'attributes'=>['data-noresult'=>'true']];
$reviews['items'][] = ['id'=>$settings['key'].'-new','tag'=>'li','description'=>language__get($user['language'],'_reviews_new'),'classes'=>['system-next'],'actions'=>['load'=>['id'=>'new','form'=>true]]];

$reviews['entries']['reviews'][] = create__filterlist($settings['key'],$reviews['filter_options'],$reviews['filter']);
$reviews['entries']['reviews'][] = create__list($settings['key'].'-list',$reviews['items'],['clear'=>true,'sort'=>true]);

foreach ($reviews['instance']->integrations() as $reviews['integration']) {
	$reviews['status'] = $reviews['instance']->integrationStatus($reviews['integration']['id']);
	$reviews['requirements'] = $reviews['instance']->providerRequirements($reviews['integration']['provider'],$reviews['integration']);
	$reviews['provider_label'] = $reviews['instance']->providerDefinitions()[$reviews['integration']['provider']]['name'] ?? ucfirst($reviews['integration']['provider']);
	$reviews['provider_logo'] = $reviews['instance']->getProviderLogo($reviews['integration']['provider']);
	$reviews['status_oauth_error'] = $reviews['instance']->providerOAuthError($reviews['integration']['provider'],$reviews['status']['last_error'] ?? '') ? 1 : 0;
	$reviews['config_error'] = ($reviews['requirements']['config_error'] ?? '') != '' && ($reviews['status']['last_error'] ?? '') == $reviews['requirements']['config_error'];
	$reviews['status_error'] = ($reviews['status_oauth_error'] == 1 || ($reviews['status']['last_error'] != '' && !$reviews['config_error'])) ? 1 : 0;
	$reviews['location_title'] = trim((string) ($reviews['status']['target']['location_title'] ?? ''));
	$reviews['review_count'] = $reviews['instance']->providerReviewCount($reviews['integration']['provider']);
	$reviews['subtitle'] = $reviews['location_title'] != '' && $reviews['status']['ready'] == 1 ? $reviews['location_title'] : trim((string) ($reviews['status']['target']['location_name'] ?? ''));
	$reviews['notify'] = false;
	if ($reviews['status_error'] == 1) {
		$reviews['subtitle'] = language__get($user['language'],'_reviews_integration_status_error');
		$reviews['notify'] = 'error';
	} else if (!empty($reviews['requirements']['connect']) && $reviews['status']['connected'] != 1) {
		$reviews['subtitle'] = language__get($user['language'],'_reviews_integration_connect_provider');
		$reviews['notify'] = 'warning';
	} else if ($reviews['status']['ready'] != 1 || $reviews['location_title'] == '') {
		$reviews['subtitle'] = !empty($reviews['requirements']['location_choices']) ? language__get($user['language'],'_reviews_integration_select_location') : language__get($user['language'],'_reviews_integration_configure_provider');
		$reviews['notify'] = 'warning';
	}
	$reviews['last_sync'] = $reviews['status']['ready'] == 1 && $reviews['status']['last_sync'] > 0 ? format__date_relative($reviews['status']['last_sync'],'relative',$user['language'],true) : language__get($user['language'],'_never');
	$reviews['details'] = [
		['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-edit','description'=>language__get($user['language'],'_reviews_integration_edit_link'),'actions'=>['load'=>['id'=>'integration-'.$reviews['integration']['id'],'form'=>true]]],
		['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-sync-info','description'=>language__get($user['language'],'_reviews_integration_last_sync'),'subtitle'=>$reviews['last_sync']]
	];
	if ($reviews['status']['ready'] == 1) $reviews['details'][1]['actions']['icons']['sync'] = ['systemicon'=>'refresh','action'=>'sync_integration','id'=>$reviews['integration']['id'],'title'=>language__get($user['language'],'_reviews_integration_sync')];
	if (!empty($reviews['requirements']['connect']) && ($reviews['status']['connected'] != 1 || $reviews['status_oauth_error'] == 1)) array_splice($reviews['details'],1,0,[['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-connect','description'=>language__get($user['language'],'_reviews_integration_manage_oauth'),'actions'=>['load'=>['function'=>'reviews__open_integrations']]]]);
	if ($reviews['status']['last_error'] != '' && (!$reviews['config_error'] || $reviews['status']['ready'] == 1)) $reviews['details'][] = ['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-error','description'=>language__get($user['language'],'_reviews_integration_last_error'),'subtitle'=>htmlspecialchars($reviews['status']['last_error'],ENT_QUOTES,'UTF-8')];
	$reviews['details'][] = ['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-delete','tag'=>'li','items'=>[
		['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-delete-button','tag'=>'button','classes'=>['system-button'],'attributes'=>['type'=>'button','data-confirmation'=>language__get($user['language'],'_ui_confirm_delete')],'description'=>language__get($user['language'],'_reviews_integration_delete'),'actions'=>['load'=>['action'=>'delete_integration','id'=>$reviews['integration']['id']]]]
	]];
	$reviews['attributes'] = ['class'=>'system-next'];
	if ($reviews['notify'] !== false) $reviews['attributes']['data-notify'] = $reviews['notify'];
	$reviews['dropdown_options'] = [
		'subtitle'=>$reviews['subtitle'],
		'attributes'=>$reviews['attributes'],
		'icons'=>[[
			'id'=>$settings['key'].'-'.$reviews['integration']['id'].'-count',
			'value'=>(string) $reviews['review_count'],
			'attributes'=>['title'=>language__get($user['language'],'_reviews_tab_reviews').': '.$reviews['review_count']]
		]]
	];
	if ($reviews['provider_logo'] != '') $reviews['dropdown_options']['image'] = $reviews['provider_logo'];
	$reviews['integration_items'][] = ['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-row','tag'=>'li','items'=>[
		create__dropdown($settings['key'].'-'.$reviews['integration']['id'].'-dropdown',$reviews['integration']['label'],create__list($settings['key'].'-'.$reviews['integration']['id'].'-list',$reviews['details'],['clear'=>true]),$reviews['dropdown_options'])
	]];
}
foreach ($reviews['instance']->providerSettings() as $reviews['provider']) {
	if (!empty($reviews['provider']['system'])) continue;
	$reviews['provider_logo'] = $reviews['instance']->getProviderLogo($reviews['provider']['id']);
	$reviews['provider_count'] = $reviews['instance']->providerReviewCount($reviews['provider']['id']);
	$reviews['provider_state'] = !empty($reviews['provider']['active']) ? language__get($user['language'],'_option_yes') : language__get($user['language'],'_option_no');
	$reviews['provider_subtitle'] = language__get($user['language'],'_reviews_provider_custom').' · '.language__get($user['language'],'_reviews_provider_active').': '.$reviews['provider_state'].' · '.$reviews['provider_count'].' '.language__get($user['language'],'_reviews_tab_reviews');
	$reviews['provider_item'] = ['id'=>$settings['key'].'-provider-'.$reviews['provider']['id'].'-row','tag'=>'li','description'=>$reviews['provider']['label'],'subtitle'=>$reviews['provider_subtitle'],'actions'=>['load'=>['id'=>'provider-'.$reviews['provider']['id'],'form'=>true],'delete'=>['id'=>$reviews['provider']['id'],'action'=>'delete_provider']]];
	if ($reviews['provider_logo'] != '') $reviews['provider_item']['image'] = $reviews['provider_logo'];
	$reviews['integration_items'][] = $reviews['provider_item'];
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
