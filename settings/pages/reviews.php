<?php

if (!file_exists(DESIGNSYSTEM.'/assets/js/admin/sys.js')) {
	require PLUGINPATH.'/fiCMS-reviews/deprecated/settings/pages/reviews.php';
	return;
}

if (!$site['onsite'] || !isset($settings['key']) || $html['is_superviser'] != 1) return;

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviews = [
	'ui'=>new \ficms\Ui($settings['key'],'reviews',$user['language']),
	'filter'=>['page'=>1,'count'=>20,'sort'=>'date','direction'=>'DESC','search'=>[],'attributes'=>['published'=>'','featured'=>'','rating'=>'','lid'=>'','provider'=>'']],
	'items'=>['reviews'=>[],'integrations'=>[]],
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages'])
];

foreach ([1,2,3,4,5] as $reviews['rating']) $reviews['ratings'][] = ['value'=>$reviews['rating'],'name'=>language__get_parsed($user['language'],'_reviews_rating_option',['rating'=>$reviews['rating']])];
$reviews['filter_ratings'] = array_merge([['name'=>language__get($user['language'],'_sort_all'),'value'=>'']],$reviews['ratings']);
$reviews['languages'] = [['name'=>language__get($user['language'],'_sort_all'),'value'=>''],['name'=>language__get($user['language'],'_reviews_language_all'),'value'=>'all']];
foreach ($site['installed_languages'] as $reviews['language']) $reviews['languages'][] = ['name'=>strtoupper($reviews['language']),'value'=>$reviews['language']];
$reviews['providers'] = $reviews['instance']->providers();
$reviews['provider_options'] = array_merge([['name'=>language__get($user['language'],'_sort_all'),'value'=>'']],$reviews['providers']);

if (isset($_POST['settings'],$_POST['type']) && $_POST['type'] == $settings['key']) {
	$reviews['action'] = (string) ($_POST['action'] ?? '');
	$reviews['id'] = trim((string) ($_POST['id'] ?? ''));
	$reviews['integration_load'] = $reviews['action'] == 'load' && str_starts_with($reviews['id'],'integration-');
	$reviews['provider_load'] = $reviews['action'] == 'load' && str_starts_with($reviews['id'],'provider-');
	$reviews['integration_id'] = $reviews['integration_load'] ? substr($reviews['id'],12) : $reviews['id'];
	$reviews['provider_id'] = $reviews['provider_load'] ? substr($reviews['id'],9) : $reviews['id'];
	if ($reviews['action'] == 'delete') $reviews['result'] = ['result'=>$reviews['instance']->delete($reviews['id'])];
	if ($reviews['action'] == 'save') $reviews['result'] = $reviews['instance']->saveFromPost($reviews['id'],$_POST);
	if ($reviews['action'] == 'ac') $reviews['result'] = ['result'=>$reviews['instance']->setPublished($reviews['id'],$_POST['value'] ?? 0)];
	if ($reviews['action'] == 'delete_integration') $reviews['result'] = ['result'=>$reviews['instance']->deleteIntegration($reviews['integration_id'])];
	if ($reviews['action'] == 'delete_provider') $reviews['result'] = ['result'=>$reviews['instance']->deleteProvider($reviews['provider_id'])];
	if ($reviews['action'] == 'save_provider') $reviews['result'] = ['result'=>$reviews['instance']->saveProviderFromPost($reviews['provider_id'],$_POST)];
	if ($reviews['action'] == 'save_integration' || $reviews['action'] == 'save_connect_integration') {
		$reviews['saved'] = $reviews['instance']->saveIntegrationFromPost($reviews['integration_id'],$_POST);
		if ($reviews['action'] == 'save_connect_integration' || empty($reviews['saved']['result'])) $reviews['result'] = $reviews['action'] == 'save_connect_integration' && !empty($reviews['saved']['result']) ? array_merge($reviews['instance']->connectIntegration($reviews['saved']['id']),['id'=>$reviews['saved']['id']]) : $reviews['saved'];
		else { $reviews['integration_id'] = $reviews['saved']['id']; $reviews['integration_load'] = true; }
	}
	if ($reviews['action'] == 'connect_integration') $reviews['result'] = $reviews['instance']->connectIntegration($reviews['integration_id']);
	if ($reviews['action'] == 'sync_integration') $reviews['result'] = $reviews['instance']->forceSyncIntegration($reviews['integration_id']);
	if ($reviews['action'] == 'integration_status') {
		$reviews['status'] = $reviews['instance']->integrationStatus($reviews['integration_id']);
		$reviews['result'] = ['result'=>true,'id'=>$reviews['integration_id'],'connected'=>$reviews['status']['connected'],'provider'=>$reviews['status']['provider'],'ready'=>$reviews['status']['ready']];
		$reviews['requirements'] = $reviews['instance']->providerRequirements($reviews['status']['provider'],$reviews['status']);
		if ($reviews['status']['connected'] == 1 && !empty($reviews['requirements']['location_choices'])) $reviews['result']['locations'] = $reviews['instance']->providerLocationChoices($reviews['status']);
	}
	if ($reviews['provider_load']) {
		$reviews['provider'] = $reviews['provider_id'] == 'new' ? $reviews['instance']->blankProvider('new') : $reviews['instance']->providerSetting($reviews['provider_id']);
		foreach (['-headline','-body','-submit-wrapper'] as $reviews['suffix']) $reviews['ui']->register($settings['form'].$reviews['suffix']);
		$reviews['ui']->slot($settings['form'].'-headline',['headline'=>$reviews['provider']['id'] == 'new' ? language__get($user['language'],'_reviews_provider_new') : language__get_parsed($user['language'],'_reviews_provider_edit',['label'=>$reviews['provider']['label']])]);
		$reviews['tabs'] = $reviews['ui']->slot($settings['form'].'-body',['clear'=>true])->tabs('provider-tabs',['id'=>$settings['key'].'-provider-form-tabsTabs']);
		$reviews['data'] = $reviews['tabs']->tab('data',['label'=>language__get($user['language'],'_reviews_provider_tab_data')]);
		$reviews['data']->field('provider_id','input',$reviews['provider']['id'],['id'=>$settings['key'].'-provider-form-id','label'=>language__get($user['language'],'_reviews_provider_id'),'attrs'=>array_merge(['required'=>'true','pattern'=>'[a-z0-9_-]+'],$reviews['provider']['id'] == 'new' ? [] : ['readonly'=>'readonly']),'call'=>false]);
		$reviews['data']->field('provider_label','input',$reviews['provider']['label'],['id'=>$settings['key'].'-provider-form-label','label'=>language__get($user['language'],'_reviews_provider_label'),'attrs'=>['required'=>'true'],'call'=>false]);
		$reviews['data']->check('provider_active',$reviews['provider']['active'],['id'=>$settings['key'].'-provider-form-active','label'=>language__get($user['language'],'_reviews_provider_active'),'call'=>false]);
		$reviews['icon'] = $reviews['tabs']->tab('icon',['label'=>language__get($user['language'],'_reviews_provider_tab_icon')]);
		if ($reviews['provider']['id'] != 'new' && $reviews['instance']->getProviderLogo($reviews['provider']['id']) != '') $reviews['icon']->item('current-icon',['id'=>$settings['key'].'-provider-current-icon','label'=>language__get($user['language'],'_reviews_provider_current_icon'),'image'=>$reviews['instance']->getProviderLogo($reviews['provider']['id'])]);
		$reviews['icon']->field('provider_icon','media',$reviews['provider']['id'] == 'new' ? false : $reviews['instance']->providerIconJson($reviews['provider']['id']),['id'=>$settings['key'].'-provider-form-icon','label'=>language__get($user['language'],'_reviews_provider_icon'),'call'=>false]);
		$reviews['ui']->slot($settings['form'].'-submit-wrapper',['clear'=>true])->button('submit',['id'=>$settings['form'].'-submit-button','label'=>language__get($user['language'],'_settings_form_save'),'action'=>'save_provider','aid'=>$reviews['provider']['id']]);
		$reviews['result'] = ['result'=>true];
	}
	if ($reviews['integration_load']) {
		$reviews['integration'] = $reviews['integration_id'] == 'new' ? $reviews['instance']->blankIntegration('new') : $reviews['instance']->integration($reviews['integration_id']);
		if (isset($_POST['integration_label'])) $reviews['integration']['label'] = trim((string) $_POST['integration_label']);
		if (isset($_POST['integration_active'])) $reviews['integration']['active'] = !in_array(strtolower(trim((string) $_POST['integration_active'])),['','0','false','off','no'],true);
		$reviews['definitions'] = $reviews['instance']->providerDefinitions();
		$reviews['external'] = $reviews['integration_id'] == 'new' || isset($reviews['definitions'][$reviews['integration']['provider']]);
		if (isset($_POST['integration_external_provider'])) $reviews['external'] = !in_array(strtolower(trim((string) $_POST['integration_external_provider'])),['','0','false','off','no'],true);
		$reviews['oauth'] = $reviews['instance']->oauthIntegrationFromValue($_POST['integration_oauth_account'] ?? '');
		if ($reviews['external'] && $reviews['oauth']) { $reviews['integration']['provider'] = $reviews['oauth']['provider']; $reviews['integration']['account_ref'] = $reviews['oauth']['account_ref']; }
		if ($reviews['external'] && isset($_POST['integration_account_ref'])) $reviews['integration']['account_ref'] = trim((string) $_POST['integration_account_ref']) ?: 'default';
		$reviews['status'] = $reviews['integration_id'] == 'new' || $reviews['oauth'] ? $reviews['instance']->previewIntegrationStatus($reviews['integration']) : $reviews['instance']->integrationStatus($reviews['integration_id']);
		$reviews['requirements'] = $reviews['instance']->providerRequirements($reviews['integration']['provider'],$reviews['integration']);
		$reviews['oauth_value'] = $reviews['external'] && !empty($reviews['requirements']['oauth']) && ($reviews['integration_id'] != 'new' || $reviews['oauth']) ? $reviews['integration']['provider'].'|'.$reviews['integration']['account_ref'] : '';
		$reviews['locations'] = !empty($reviews['requirements']['location_choices']) && $reviews['status']['connected'] == 1 && $reviews['oauth_value'] != '' ? $reviews['instance']->providerLocationChoices($reviews['integration']) : ['result'=>false,'items'=>[],'error'=>''];
		$reviews['location_error'] = strtolower(trim((string) $reviews['locations']['error']));
		$reviews['oauth_error'] = $reviews['instance']->providerOAuthError($reviews['integration']['provider'],$reviews['location_error']);
		$reviews['options'] = $reviews['external'] ? $reviews['instance']->oauthIntegrationOptions($reviews['integration_id']) : [];
		if ($reviews['oauth_value'] != '' && !in_array($reviews['oauth_value'],array_column($reviews['options'],'value'),true)) array_unshift($reviews['options'],['name'=>$reviews['requirements']['oauth_provider'].' / '.$reviews['integration']['account_ref'],'value'=>$reviews['oauth_value']]);
		foreach (['-headline','-body','-submit-wrapper'] as $reviews['suffix']) $reviews['ui']->register($settings['form'].$reviews['suffix']);
		$reviews['ui']->slot($settings['form'].'-headline',['headline'=>$reviews['integration_id'] == 'new' ? language__get($user['language'],'_reviews_integration_new') : language__get_parsed($user['language'],'_reviews_integration_edit',['label'=>$reviews['integration']['label']])]);
		$reviews['body'] = $reviews['ui']->slot($settings['form'].'-body',['clear'=>true]);
		$reviews['body']->field('integration_id','hidden',$reviews['integration']['id'],['id'=>$settings['key'].'-integration-id','label'=>false,'call'=>false]);
		$reviews['body']->item('state',['id'=>$settings['key'].'-integration-state','label'=>false,'attrs'=>['data-reviews-integration'=>$reviews['integration']['id'],'data-reviews-provider'=>$reviews['integration']['provider'],'data-reviews-connected'=>(int) $reviews['status']['connected']]]);
		$reviews['body']->field('integration_provider','hidden',$reviews['integration']['provider'],['id'=>$settings['key'].'-integration-provider','label'=>false,'call'=>false]);
		$reviews['body']->field('integration_account_ref','hidden',$reviews['integration']['account_ref'],['id'=>$settings['key'].'-integration-account-ref','label'=>false,'call'=>false]);
		$reviews['body']->field('integration_label','input',$reviews['integration']['label'],['id'=>$settings['key'].'-integration-label','label'=>language__get($user['language'],'_reviews_integration_label'),'call'=>false]);
		$reviews['tabs'] = $reviews['body']->tabs('integration-tabs',['id'=>$settings['key'].'-integration-form-tabsTabs']);
		$reviews['connection'] = $reviews['tabs']->tab('connection',['label'=>language__get($user['language'],'_reviews_integration_tab_connection')]);
		$reviews['connection']->check('integration_active',$reviews['integration']['active'],['id'=>$settings['key'].'-integration-active','label'=>language__get($user['language'],'_reviews_integration_active'),'call'=>false]);
		$reviews['external_node'] = $reviews['connection']->dropdown('external-provider',['id'=>$settings['key'].'-integration-external-provider-dropdown','label'=>language__get($user['language'],'_reviews_integration_external_provider'),'independent'=>true,'notify'=>$reviews['oauth_error'] || trim((string) $reviews['status']['last_error']) != '' ? 'error' : (($reviews['external'] && ($reviews['oauth_value'] == '' || (!empty($reviews['requirements']['connect']) && !$reviews['status']['connected']))) ? 'warning' : null),'toggle'=>['name'=>'integration_external_provider','checked'=>$reviews['external'],'action'=>'load','call'=>'reviews__provider_toggle']]);
		if ($reviews['external'] && (!empty($reviews['requirements']['oauth']) || $reviews['integration_id'] == 'new')) $reviews['external_node']->text('oauth-hint',language__get($user['language'],'_reviews_integration_oauth_hint'),['id'=>$settings['key'].'-integration-oauth-hint']);
		if ($reviews['external'] && $reviews['options']) $reviews['external_node']->field('integration_oauth_account','select',$reviews['oauth_value'],['id'=>$settings['key'].'-integration-oauth-account','label'=>language__get($user['language'],'_reviews_integration_oauth_account'),'options'=>$reviews['options'],'attrs'=>['required'=>'true'],'call'=>'reviews__provider_change']);
		if ($reviews['external'] && !empty($reviews['requirements']['oauth']) && !$reviews['options']) $reviews['external_node']->button('oauth-link',['id'=>$settings['key'].'-integration-oauth-link','label'=>language__get($user['language'],'_reviews_integration_oauth_system_create'),'call'=>'reviews__open_integrations']);
		if ($reviews['external']) foreach (($reviews['requirements']['form_fields'] ?? []) as $reviews['field'] => $reviews['definition']) $reviews['external_node']->field('integration_'.$reviews['field'],$reviews['definition']['type'] ?? 'input',$reviews['requirements']['form_values'][$reviews['field']] ?? '',['id'=>$settings['key'].'-integration-'.$reviews['field'],'label'=>language__get($user['language'],$reviews['definition']['label'] ?? '_reviews_integration_'.$reviews['field']),'options'=>$reviews['definition']['options'] ?? [],'attrs'=>$reviews['definition']['attributes'] ?? [],'call'=>false]);
		if ($reviews['external'] && !empty($reviews['requirements']['location_choices']) && $reviews['status']['connected'] == 1 && $reviews['oauth_value'] != '' && $reviews['locations']['result'] && $reviews['locations']['items']) {
			$reviews['location_value'] = '';
			foreach ($reviews['locations']['items'] as $reviews['location_option']) {
				$reviews['location_decoded'] = $reviews['instance']->decode($reviews['location_option']['value'] ?? '');
				if (!is_array($reviews['location_decoded'])) continue;
				foreach (['page_id','location_name'] as $reviews['target_key']) if (trim((string) ($reviews['integration']['target'][$reviews['target_key']] ?? '')) != '' && trim((string) ($reviews['location_decoded'][$reviews['target_key']] ?? '')) == trim((string) $reviews['integration']['target'][$reviews['target_key']])) $reviews['location_value'] = $reviews['location_option']['value'];
				if (trim((string) ($reviews['integration']['target']['location_name'] ?? '')) != '' && trim((string) ($reviews['location_decoded']['page_name'] ?? '')) == trim((string) $reviews['integration']['target']['location_name'])) $reviews['location_value'] = $reviews['location_option']['value'];
				if ($reviews['location_value'] != '') break;
			}
			if ($reviews['location_value'] == '' && trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '' && count($reviews['locations']['items']) == 1) $reviews['location_value'] = $reviews['locations']['items'][0]['value'];
			$reviews['location_options'] = $reviews['locations']['items'];
			if ($reviews['location_value'] == '') array_unshift($reviews['location_options'],['name'=>language__get($user['language'],$reviews['requirements']['location_select']['empty'] ?? '_reviews_integration_target_missing'),'value'=>'','disabled'=>true]);
			$reviews['external_node']->field($reviews['requirements']['location_select']['option'] ?? 'integration_location','select',$reviews['location_value'],['id'=>$settings['key'].'-integration-'.$reviews['integration']['provider'].'-location','label'=>language__get($user['language'],$reviews['requirements']['location_select']['label'] ?? '_reviews_integration_target'),'options'=>$reviews['location_options'],'call'=>false]);
		}
		$reviews['logo'] = $reviews['tabs']->tab('logo',['label'=>language__get($user['language'],'_reviews_integration_tab_logo')]);
		$reviews['logo']->field('provider_icon','media',$reviews['external'] ? $reviews['instance']->providerIconJson($reviews['integration']['provider']) : false,['id'=>$settings['key'].'-integration-provider-icon','label'=>language__get($user['language'],'_reviews_provider_icon'),'call'=>false]);
		$reviews['graph'] = ['series'=>['reviews'=>[]],'points'=>[]]; $reviews['count'] = 0; $reviews['sum'] = 0; $reviews['published'] = 0; $reviews['start'] = strtotime('-29 days',strtotime('today',(int) ($_SERVER['now'] ?? time())));
		for ($reviews['day'] = 0; $reviews['day'] < 30; $reviews['day']++) $reviews['graph']['points'][date('Y-m-d',$reviews['start'] + $reviews['day'] * 86400)] = ['label'=>date('d.m.',$reviews['start'] + $reviews['day'] * 86400),'data'=>['reviews'=>0]];
		foreach ($reviews['instance']->all() as $reviews['review']) if (($reviews['review']['provider'] ?? '') == $reviews['integration']['provider']) { $reviews['count']++; if (!empty($reviews['review']['published'])) { $reviews['published']++; $reviews['sum'] += max(1,min(5,(int) $reviews['review']['rating'])); } $reviews['date'] = date('Y-m-d',(int) ($reviews['review']['created'] ?? $reviews['review']['date'])); if (isset($reviews['graph']['points'][$reviews['date']])) $reviews['graph']['points'][$reviews['date']]['data']['reviews']++; }
		$reviews['stats'] = $reviews['tabs']->tab('statistics',['label'=>language__get($user['language'],'_reviews_integration_tab_statistics')])->listing('summary',['id'=>$settings['key'].'-integration-statistics','kind'=>'statistics']);
		$reviews['stats']->statistics('graph','graph',statistics__format_graph($user['language'],['series'=>$reviews['graph']['series'],'points'=>array_values($reviews['graph']['points'])],['reviews'=>'_reports_reviews_new'],['gridLines'=>4,'legend'=>false]),['id'=>$settings['key'].'-integration-statistics-graph','attrs'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_reviews_integration_new_reviews_30_days')]]);
		$reviews['stats']->statistics('count','info',['value'=>$reviews['count'],'label'=>language__get($user['language'],'_reviews_integration_reviews_count')],['id'=>$settings['key'].'-integration-statistics-count']);
		$reviews['stats']->statistics('rating','info',['value'=>$reviews['published'] ? round($reviews['sum'] / $reviews['published'],1) : '0','label'=>language__get($user['language'],'_reviews_integration_provider_rating')],['id'=>$settings['key'].'-integration-statistics-rating']);
		$reviews['submit'] = $reviews['ui']->slot($settings['form'].'-submit-wrapper',['clear'=>true]);
		$reviews['submit_label'] = $reviews['external'] && !empty($reviews['requirements']['oauth']) && $reviews['oauth_value'] == '' ? false : (!empty($reviews['requirements']['location_choices']) && intval($reviews['status']['connected'] ?? 0) == 1 && trim((string) ($reviews['integration']['target']['location_name'] ?? '')) == '' && !empty($reviews['locations']['result']) ? language__get($user['language'],'_reviews_integration_save_location') : language__get($user['language'],'_settings_form_save'));
		if ($reviews['submit_label'] !== false) $reviews['submit']->button('submit',['id'=>$settings['form'].'-submit-button','label'=>$reviews['submit_label'],'action'=>'save_integration','aid'=>$reviews['integration_id']]);
		if ($reviews['external'] && !empty($reviews['requirements']['connect']) && ($reviews['status']['connected'] != 1 || $reviews['oauth_error'])) $reviews['submit']->button('connect',['id'=>$settings['form'].'-connect-button','label'=>language__get($user['language'],'_reviews_integration_manage_oauth'),'call'=>'reviews__open_integrations']);
		$reviews['result'] = ['result'=>true];
	}
	if ($reviews['action'] == 'load' && !$reviews['integration_load'] && !$reviews['provider_load']) {
		$reviews['entry'] = $reviews['id'] == 'new' ? $reviews['instance']->blank('new') : $reviews['instance']->find($reviews['id']);
		$reviews['read_only'] = intval($reviews['entry']['read_only'] ?? 0) == 1 && ($reviews['entry']['provider'] ?? 'local') != 'local';
		foreach (['-headline','-body','-submit-wrapper'] as $reviews['suffix']) $reviews['ui']->register($settings['form'].$reviews['suffix']);
		$reviews['ui']->slot($settings['form'].'-headline',['headline'=>$reviews['id'] == 'new' ? language__get($user['language'],'_reviews_new') : language__get($user['language'],'_reviews_edit')]);
		$reviews['body'] = $reviews['ui']->slot($settings['form'].'-body',['clear'=>true]);
		foreach (['author'=>'input','source'=>'input','provider'=>'select','external_url'=>'input','lid'=>'multipicker'] as $reviews['field'] => $reviews['type']) $reviews['body']->field($reviews['field'],$reviews['type'],$reviews['entry'][$reviews['field']] ?? '',['id'=>$settings['key'].'-form-'.str_replace('_','',$reviews['field']),'label'=>language__get($user['language'],'_reviews_'.$reviews['field']),'options'=>$reviews['field'] == 'provider' ? $reviews['providers'] : [],'attrs'=>array_merge(in_array($reviews['field'],['author','provider','lid']) ? ['required'=>'true'] : [],$reviews['field'] == 'lid' ? ['data-list'=>'installed-languages','data-selectcontrol'=>$settings['key'].'-form-lingual','data-all'=>'true'] : ($reviews['field'] == 'external_url' ? ['data-format'=>'url'] : []),$reviews['read_only'] ? ['disabled'=>'disabled'] : []),'call'=>false]);
		$reviews['multi'] = $reviews['body']->multilingual('lingual',['id'=>$settings['key'].'-form-lingual','select'=>$user['language'],'all'=>$site['installed_languages']]);
		$reviews['multi']->field('text','textarea',$reviews['entry']['text'] ?? [],['id'=>$settings['key'].'-form-text','label'=>language__get($user['language'],'_reviews_text'),'rows'=>5,'attrs'=>array_merge(['required'=>'true'],$reviews['read_only'] ? ['disabled'=>'disabled'] : []),'call'=>false]);
		foreach (['rating'=>'select','date'=>'date'] as $reviews['field'] => $reviews['type']) $reviews['body']->field($reviews['field'],$reviews['type'],$reviews['entry'][$reviews['field']] ?? '',['id'=>$settings['key'].'-form-'.$reviews['field'],'label'=>language__get($user['language'],'_reviews_'.$reviews['field']),'options'=>$reviews['field'] == 'rating' ? $reviews['ratings'] : [],'attrs'=>array_merge($reviews['field'] == 'rating' ? ['required'=>'true'] : ['data-zero'=>'true'],$reviews['read_only'] ? ['disabled'=>'disabled'] : []),'call'=>false]);
		foreach (['published','featured'] as $reviews['field']) $reviews['body']->check($reviews['field'],$reviews['entry'][$reviews['field']] ?? false,['id'=>$settings['key'].'-form-'.$reviews['field'],'label'=>language__get($user['language'],'_reviews_'.$reviews['field']),'call'=>false]);
		$reviews['ui']->slot($settings['form'].'-submit-wrapper',['clear'=>true])->button('submit',['id'=>$settings['form'].'-submit-button','label'=>language__get($user['language'],'_reviews_save'),'action'=>'save','aid'=>$reviews['entry']['id']]);
		$reviews['result'] = ['result'=>true];
	}
		if (isset($reviews['result'])) {
			if (!isset($settings['output']['result'])) $settings['output']['result'] = [];
			$settings['output']['result'] = array_merge($settings['output']['result'],$reviews['result']);
		}
	$_POST['handled'] = true;
}

if (isset($_SESSION['filter'][$settings['key']]) && is_array($_SESSION['filter'][$settings['key']])) $reviews['filter'] = array_replace_recursive($reviews['filter'],$_SESSION['filter'][$settings['key']]);
$reviews['admin'] = $reviews['instance']->admin($reviews['filter'],$user['language']);
$reviews['filter'] = $reviews['admin']['filter'];
$reviews['tab'] = $reviews['ui']->tab('reviews',['label'=>language__get($user['language'],'_reviews_tab_reviews')]);
$reviews['filter_node'] = $reviews['tab']->filter($settings['key'],['id'=>$settings['key'].'-filter','callback'=>'settings__filter']);
$reviews['filter_node']->field('page','number',$reviews['filter']['page'],['id'=>$settings['key'].'Filter-page','label'=>language__get($user['language'],'_sort_page'),'attrs'=>['min'=>1,'max'=>$reviews['admin']['pages']],'call'=>'settings__filter']);
$reviews['filter_node']->field('search','multipicker',$reviews['filter']['search'],['id'=>$settings['key'].'Filter-search','label'=>language__get($user['language'],'_sort_search'),'custom'=>true,'attrs'=>['data-seperator'=>'["enter"]'],'call'=>'settings__filter']);
$reviews['filter_node']->field('sort','select',$reviews['filter']['sort'],['id'=>$settings['key'].'Filter-sort','label'=>language__get($user['language'],'_sort_by'),'options'=>[['name'=>language__get($user['language'],'_sort_created'),'value'=>'date'],['name'=>language__get($user['language'],'_reviews_filter_sort_featured'),'value'=>'featured'],['name'=>language__get($user['language'],'_reviews_filter_sort_rating'),'value'=>'rating']],'call'=>'settings__filter']);
$reviews['filter_node']->field('direction','toggle',$reviews['filter']['direction'],['id'=>$settings['key'].'Filter-direction','label'=>false,'attrs'=>['data-state'=>'ASC,DESC'],'call'=>'settings__filter']);
foreach (['published'=>[['name'=>language__get($user['language'],'_sort_all'),'value'=>''],['name'=>language__get($user['language'],'_option_yes'),'value'=>'1'],['name'=>language__get($user['language'],'_option_no'),'value'=>'0']],'featured'=>[['name'=>language__get($user['language'],'_sort_all'),'value'=>''],['name'=>language__get($user['language'],'_option_yes'),'value'=>'1'],['name'=>language__get($user['language'],'_option_no'),'value'=>'0']],'rating'=>$reviews['filter_ratings'],'lid'=>$reviews['languages'],'provider'=>$reviews['provider_options']] as $reviews['field'] => $reviews['options']) $reviews['filter_node']->field('attributes['.$reviews['field'].']','select',$reviews['filter']['attributes'][$reviews['field']],['id'=>$settings['key'].'Filter-'.$reviews['field'],'label'=>language__get($user['language'],'_reviews_'.$reviews['field']),'options'=>$reviews['options'],'call'=>'settings__filter']);
$reviews['listing'] = $reviews['tab']->listing('list',['id'=>$settings['key'].'-list','clear'=>true,'sort'=>true]);
$reviews['definitions'] = $reviews['instance']->providerDefinitions();
foreach ($reviews['admin']['rows'] as $reviews['entry']) {
	$reviews['provider'] = $reviews['definitions'][$reviews['entry']['provider'] ?? 'local']['name'] ?? ucfirst((string) ($reviews['entry']['provider'] ?? 'local'));
	$reviews['listing']->item('review-'.$reviews['entry']['id'],['id'=>$settings['key'].'-'.$reviews['entry']['id'].'-row','label'=>trim((string) $reviews['entry']['author']) ?: language__get($user['language'],'_reviews_no_author'),'subtitle'=>$reviews['provider'].' · '.language__get_parsed($user['language'],'_reviews_rating_option',['rating'=>$reviews['entry']['rating']]).' · '.(!empty($reviews['entry']['published']) ? language__get($user['language'],'_reviews_published') : language__get($user['language'],'_reviews_draft')),'image'=>PAGEPATH.'/media/language/'.(in_array('all',$reviews['entry']['lid'],true) ? 'all' : $reviews['entry']['lid'][0]).'.png','load'=>['id'=>$reviews['entry']['id'],'form'=>true],'toggle'=>['id'=>$reviews['entry']['id'],'name'=>$settings['key'].'-'.$reviews['entry']['id'],'action'=>'ac','checked'=>!empty($reviews['entry']['published'])],'delete'=>(int) ($reviews['entry']['read_only'] ?? 0) == 1 ? null : ['id'=>$reviews['entry']['id']]]);
}
if (!$reviews['admin']['rows']) $reviews['listing']->item('empty',['id'=>$settings['key'].'-empty','label'=>language__get($user['language'],'_sort_no_result'),'attrs'=>['data-noresult'=>'true']]);
$reviews['listing']->item('new',['id'=>$settings['key'].'-new','label'=>language__get($user['language'],'_reviews_new'),'attrs'=>['class'=>'system-next'],'load'=>['id'=>'new','form'=>true]]);
$reviews['integrations'] = $reviews['ui']->tab('integrations',['label'=>language__get($user['language'],'_reviews_tab_integrations')])->listing('list',['id'=>$settings['key'].'-integrations-list','clear'=>true,'sort'=>true]);
foreach ($reviews['instance']->integrations() as $reviews['integration']) {
	$reviews['status'] = $reviews['instance']->integrationStatus($reviews['integration']['id']); $reviews['requirements'] = $reviews['instance']->providerRequirements($reviews['integration']['provider'],$reviews['integration']); $reviews['error'] = $reviews['instance']->providerOAuthError($reviews['integration']['provider'],$reviews['status']['last_error'] ?? '');
	$reviews['subtitle'] = $reviews['status']['ready'] ? trim((string) ($reviews['status']['target']['location_title'] ?? $reviews['status']['target']['location_name'] ?? '')) : (!empty($reviews['requirements']['connect']) && !$reviews['status']['connected'] ? language__get($user['language'],'_reviews_integration_connect_provider') : language__get($user['language'],'_reviews_integration_configure_provider'));
	$reviews['dropdown'] = $reviews['integrations']->dropdown('integration-'.$reviews['integration']['id'],['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-dropdown','label'=>$reviews['integration']['label'],'subtitle'=>$reviews['subtitle'],'image'=>$reviews['instance']->getProviderLogo($reviews['integration']['provider']),'notify'=>$reviews['error'] ? 'error' : (!$reviews['status']['ready'] ? 'warning' : null),'attrs'=>['class'=>'system-next']]);
	$reviews['dropdown']->item('edit',['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-edit','label'=>language__get($user['language'],'_reviews_integration_edit_link'),'load'=>['id'=>'integration-'.$reviews['integration']['id'],'form'=>true]]);
	if (!empty($reviews['requirements']['connect']) && (!$reviews['status']['connected'] || $reviews['error'])) $reviews['dropdown']->button('connect',['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-connect','label'=>language__get($user['language'],'_reviews_integration_manage_oauth'),'call'=>'reviews__open_integrations']);
	$reviews['dropdown']->item('sync-info',['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-sync-info','label'=>language__get($user['language'],'_reviews_integration_last_sync'),'subtitle'=>$reviews['status']['ready'] && $reviews['status']['last_sync'] > 0 ? format__date_relative($reviews['status']['last_sync'],'relative',$user['language'],true) : language__get($user['language'],'_never'),'actions'=>['icons'=>['sync'=>['systemicon'=>'refresh','action'=>'sync_integration','id'=>$reviews['integration']['id'],'title'=>language__get($user['language'],'_reviews_integration_sync')]]]]);
	if ($reviews['status']['last_error'] != '') $reviews['dropdown']->item('error',['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-error','label'=>language__get($user['language'],'_reviews_integration_last_error'),'subtitle'=>$reviews['status']['last_error']]);
	$reviews['dropdown']->button('delete',['id'=>$settings['key'].'-'.$reviews['integration']['id'].'-delete','label'=>language__get($user['language'],'_reviews_integration_delete'),'action'=>'delete_integration','aid'=>$reviews['integration']['id'],'confirm'=>language__get($user['language'],'_ui_confirm_delete')]);
}
foreach ($reviews['instance']->providerSettings() as $reviews['provider']) if (empty($reviews['provider']['system'])) {
	$reviews['provider_node'] = $reviews['integrations']->dropdown('provider-'.$reviews['provider']['id'],['id'=>$settings['key'].'-provider-'.$reviews['provider']['id'].'-row','label'=>$reviews['provider']['label'],'subtitle'=>language__get($user['language'],'_reviews_provider_custom'),'image'=>$reviews['instance']->getProviderLogo($reviews['provider']['id'])]);
	$reviews['provider_node']->item('edit',['id'=>$settings['key'].'-provider-'.$reviews['provider']['id'].'-edit','label'=>language__get($user['language'],'_reviews_integration_edit_link'),'load'=>['id'=>'provider-'.$reviews['provider']['id'],'form'=>true]]);
	$reviews['provider_node']->button('delete',['id'=>$settings['key'].'-provider-'.$reviews['provider']['id'].'-delete','label'=>language__get($user['language'],'_reviews_provider_delete'),'action'=>'delete_provider','aid'=>$reviews['provider']['id'],'confirm'=>language__get($user['language'],'_ui_confirm_delete')]);
}
$reviews['integrations']->item('new',['id'=>$settings['key'].'-integration-new','label'=>language__get($user['language'],'_reviews_integration_new'),'attrs'=>['class'=>'system-next'],'load'=>['id'=>'integration-new','form'=>true]]);
$reviews['ui']->refresh($_SERVER['now'] + 60);
$reviews['ui']->emit($settings);

unset($reviews);
