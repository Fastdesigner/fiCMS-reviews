<?php

class FiCMSReviews {
	private $basePath = '';
	private $dataFile = '';
	private $integrationsFile = '';
	private $defaultLanguage = 'de';
	private $installedLanguages = [];
	private $data = [];
	private $integrations = [];

	public function __construct($basePath = '', $defaultLanguage = '', $installedLanguages = []) {
		$this->basePath = rtrim((string) $basePath,'/');
		if ($this->basePath == '') $this->basePath = dirname(__DIR__);
		if (defined('PLUGINPATH') && is_dir(PLUGINPATH.'/'.basename($this->basePath))) $this->basePath = PLUGINPATH.'/'.basename($this->basePath);
		$this->dataFile = $this->basePath.'/data/reviews.json';
		$this->integrationsFile = $this->basePath.'/data/integrations.json';
		$this->defaultLanguage = trim((string) $defaultLanguage) !== '' ? trim((string) $defaultLanguage) : (string) ($GLOBALS['site']['default_language'] ?? ($_SESSION['language'] ?? 'de'));
		$this->installedLanguages = is_array($installedLanguages) ? array_values($installedLanguages) : [];
		if (empty($this->installedLanguages) && isset($GLOBALS['site']['installed_languages']) && is_array($GLOBALS['site']['installed_languages'])) $this->installedLanguages = array_values($GLOBALS['site']['installed_languages']);
		if (empty($this->installedLanguages)) $this->installedLanguages = [$this->defaultLanguage];
		$this->data = $this->load();
		$this->integrations = $this->loadIntegrations();
	}

	public function dataFile() {
		return $this->dataFile;
	}

	public function integrationsFile() {
		return $this->integrationsFile;
	}

	public function all() {
		return $this->data['reviews'];
	}

	public function find($id) {
		$id = trim((string) $id);
		return $this->data['reviews'][$id] ?? $this->blank($id);
	}

	public function blank($id = 'new') {
		return ['id'=>$id,'author'=>[],'source'=>[],'rating'=>5,'text'=>[],'lid'=>['all'],'date'=>intval($_SERVER['now'] ?? time()),'published'=>($id == 'new' ? 1 : 0),'featured'=>0,'provider'=>'local','source_type'=>'local','external_id'=>'','external_updated'=>0,'imported'=>0,'read_only'=>0];
	}

	public function delete($id) {
		$id = trim((string) $id);
		if ($id == '' || !isset($this->data['reviews'][$id])) return false;
		unset($this->data['reviews'][$id]);
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
		return $this->write();
	}

	public function saveFromPost($id, $post) {
		$id = $this->validId($id) ? trim((string) $id) : $this->createId();
		if ($id == 'new') $id = $this->createId();
		$entry = $this->data['reviews'][$id] ?? ['id'=>$id,'created'=>intval($_SERVER['now'] ?? time())];
		$entry['id'] = $id;
		$entry['lid'] = $this->normalizeLanguages($post['lid'] ?? ['all'],true);
		if (intval($entry['read_only'] ?? 0) != 1 || ($entry['provider'] ?? 'local') == 'local') {
			foreach (['author','source','text'] as $field) $entry[$field] = $this->normalizePostedText($post[$field] ?? []);
			$entry['rating'] = max(1,min(5,intval($post['rating'] ?? 5)));
			$entry['date'] = intval($post['date'] ?? ($_SERVER['now'] ?? time()));
			$entry['provider'] = 'local';
			$entry['source_type'] = 'local';
			$entry['external_id'] = '';
			$entry['external_updated'] = 0;
			$entry['imported'] = 0;
			$entry['read_only'] = 0;
		}
		$entry['published'] = !empty($post['published']) ? 1 : 0;
		$entry['featured'] = !empty($post['featured']) ? 1 : 0;
		$entry['updated'] = intval($_SERVER['now'] ?? time());
		$this->data['reviews'][$id] = $this->normalizeEntry($id,$entry);
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
		return ['result'=>$this->write(),'id'=>$id];
	}

	public function providers() {
		$providers = ['local'=>['name'=>'Local','value'=>'local']];
		foreach ($this->data['reviews'] as $entry) {
			$entry = $this->normalizeEntry($entry['id'] ?? '',$entry);
			if ($entry['provider'] == '') continue;
			$providers[$entry['provider']] = ['name'=>ucfirst($entry['provider']),'value'=>$entry['provider']];
		}
		foreach ($this->providerDefinitions() as $key => $definition) $providers[$key] = ['name'=>$definition['name'],'value'=>$key];
		return $providers;
	}

	public function providerDefinitions() {
		return [
			'google'=>['name'=>'Google','oauth'=>1,'sync'=>1]
		];
	}

	public function getProviderLogo($provider = '') {
		$provider = preg_replace('/[^a-z0-9_]/i','',trim((string) $provider));
		if ($provider == '') return '';
		foreach (['svg','png','webp'] as $extension) {
			$file = $this->basePath.'/assets/img/providers/'.$provider.'.'.$extension;
			$path = (defined('PLUGINPATH') ? PLUGINPATH.'/'.basename($this->basePath) : trim($this->basePath,'/')).'/assets/img/providers/'.$provider.'.'.$extension;
			if (is_file($file)) return rtrim(PAGEPATH,'/').'/'.$path.'?v='.filemtime($file);
		}
		return '';
	}

	public function integrations() {
		return $this->integrations['integrations'];
	}

	public function integration($id = '') {
		$id = $this->validIntegrationId($id) ? trim((string) $id) : '';
		foreach ($this->integrations['integrations'] as $integration) if ($integration['id'] == $id) return $integration;
		return $this->blankIntegration($id == '' ? 'new' : $id);
	}

	public function blankIntegration($id = 'new') {
		return ['id'=>$id,'label'=>'','provider'=>'google','active'=>1,'account_ref'=>'default','target'=>[],'last_sync'=>0,'last_error'=>'','last_count'=>0,'last_imported'=>0,'last_updated'=>0];
	}

	public function saveIntegrationFromPost($id, $post) {
		$original = $this->validIntegrationId($id) ? trim((string) $id) : '';
		$provider = $this->validProvider($post['integration_provider'] ?? '') ? trim((string) $post['integration_provider']) : 'google';
		$saveId = $this->validIntegrationId($post['integration_id'] ?? '') ? trim((string) $post['integration_id']) : $original;
		if ($saveId == '' || $saveId == 'new') $saveId = $this->createIntegrationId($provider);
		$existing = $original != '' && $original != 'new' ? $this->integration($original) : $this->blankIntegration($saveId);
		$integration = array_merge($existing,[
			'id'=>$saveId,
			'label'=>trim((string) ($post['integration_label'] ?? '')) != '' ? trim((string) ($post['integration_label'] ?? '')) : ucfirst($provider),
			'provider'=>$provider,
			'active'=>($original == '' || $original == 'new') && !isset($post['integration_active']) ? 1 : (!empty($post['integration_active']) ? 1 : 0),
			'account_ref'=>trim((string) ($post['integration_account_ref'] ?? ($existing['account_ref'] ?? 'default'))) ?: 'default'
		]);
		if ($provider == 'google' && isset($post['integration_google_location']) && is_string($post['integration_google_location'])) {
			$location = $this->decode($post['integration_google_location']);
			if (is_array($location)) $integration['target'] = [
				'account_name'=>trim((string) ($location['account_name'] ?? '')),
				'location_name'=>trim((string) ($location['location_name'] ?? '')),
				'location_title'=>trim((string) ($location['location_title'] ?? ''))
			];
			if (is_array($location) && $integration['last_error'] == 'google_location_missing') $integration['last_error'] = '';
		}
		foreach ($this->integrations['integrations'] as $key => $entry) {
			if ($entry['id'] != $original && $entry['id'] != $saveId) continue;
			array_splice($this->integrations['integrations'],$key,1);
			break;
		}
		$this->integrations['integrations'][] = $this->normalizeIntegration($integration);
		$this->integrations['updated'] = intval($_SERVER['now'] ?? time());
		return ['result'=>$this->writeIntegrations(),'id'=>$saveId];
	}

	public function deleteIntegration($id) {
		$id = trim((string) $id);
		foreach ($this->integrations['integrations'] as $key => $integration) {
			if ($integration['id'] != $id) continue;
			array_splice($this->integrations['integrations'],$key,1);
			$this->integrations['updated'] = intval($_SERVER['now'] ?? time());
			return $this->writeIntegrations();
		}
		return false;
	}

	public function connectIntegration($id) {
		$integration = $this->integration($id);
		if ($integration['provider'] != 'google') return ['result'=>false];
		if (!class_exists('\oauth\OAuth')) {
			$integration['last_error'] = 'OAuth plugin unavailable';
			$this->storeIntegration($integration);
			return ['result'=>false,'error'=>$integration['last_error']];
		}
		if (!\oauth\OAuth::provider('google',false)) {
			$integration['last_error'] = 'Google OAuth provider unavailable';
			$this->storeIntegration($integration);
			return ['result'=>false,'error'=>$integration['last_error']];
		}
		if ($integration['last_error'] != '') {
			$integration['last_error'] = '';
			$this->storeIntegration($integration);
		}
		return ['result'=>true,'redirect'=>PAGEPATH.'/oauth.php?action=authorize&provider=google&account='.rawurlencode($integration['account_ref']),'redirect_target'=>'_blank'];
	}

	public function integrationStatus($id) {
		$integration = $this->integration($id);
		if ($integration['provider'] == 'google' && class_exists('\oauth\OAuth') && \oauth\OAuth::account_load('google',$integration['account_ref']) && trim((string) ($integration['target']['location_name'] ?? '')) == '') $integration = $this->resolveGoogleTarget($integration);
		$integration['connected'] = $integration['provider'] == 'google' && class_exists('\oauth\OAuth') && \oauth\OAuth::account_load('google',$integration['account_ref']) ? 1 : 0;
		$integration['provider_available'] = $integration['provider'] != 'google' || class_exists('\google\BusinessProfile') ? 1 : 0;
		$integration['oauth_available'] = $integration['provider'] != 'google' || (class_exists('\oauth\OAuth') && \oauth\OAuth::provider('google',false)) ? 1 : 0;
		$integration['ready'] = $integration['provider'] == 'google' && $integration['connected'] == 1 && trim((string) ($integration['target']['account_name'] ?? '')) != '' && trim((string) ($integration['target']['location_name'] ?? '')) != '' ? 1 : 0;
		$integration['timer'] = $this->timer('reviews_sync_'.$integration['id']);
		return $integration;
	}

	public function googleAccounts($integration = []) {
		$integration = $this->normalizeIntegration($integration);
		$result = ['result'=>false,'items'=>[],'error'=>''];
		if (!class_exists('\google\BusinessProfile')) {
			$result['error'] = 'google_unavailable';
			return $result;
		}
		$google = new \google\BusinessProfile($integration['account_ref']);
		$accounts = $google->accounts();
		if (!is_array($accounts)) {
			$result['error'] = $this->googleLastError($google);
			return $result;
		}
		foreach ($accounts['accounts'] ?? [] as $account) {
			$name = trim((string) ($account['name'] ?? ''));
			if ($name == '') continue;
			$result['items'][$name] = ['name'=>trim((string) ($account['accountName'] ?? $name)),'value'=>$name];
		}
		$result['result'] = true;
		return $result;
	}

	public function googleLocations($integration = [], $accountName = '') {
		$integration = $this->normalizeIntegration($integration);
		$accountName = trim((string) $accountName) !== '' ? trim((string) $accountName) : trim((string) ($integration['target']['account_name'] ?? ''));
		$result = ['result'=>false,'items'=>[],'error'=>''];
		if (!class_exists('\google\BusinessProfile') || $accountName == '') {
			$result['error'] = $accountName == '' ? 'google_account_missing' : 'google_unavailable';
			return $result;
		}
		$google = new \google\BusinessProfile($integration['account_ref']);
		$locations = $google->locations($accountName);
		if (!is_array($locations)) {
			$result['error'] = $this->googleLastError($google);
			return $result;
		}
		foreach ($locations['locations'] ?? [] as $location) {
			$name = trim((string) ($location['name'] ?? ''));
			if ($name == '') continue;
			$result['items'][$name] = ['name'=>trim((string) ($location['title'] ?? $name)),'value'=>$name];
		}
		$result['result'] = true;
		return $result;
	}

	public function googleLocationChoices($integration = []) {
		$choices = [];
		$accounts = $this->googleAccounts($integration);
		if (!$accounts['result']) return ['result'=>false,'items'=>[],'error'=>$accounts['error']];
		foreach ($accounts['items'] as $account) {
			$locations = $this->googleLocations($integration,$account['value']);
			if (!$locations['result']) return ['result'=>false,'items'=>[],'error'=>$locations['error']];
			foreach ($locations['items'] as $location) $choices[] = [
				'name'=>$account['name'].' · '.$location['name'],
				'value'=>json_encode(['account_name'=>$account['value'],'location_name'=>$location['value'],'location_title'=>$location['name']],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			];
		}
		return ['result'=>true,'items'=>$choices,'error'=>''];
	}

	public function forceSyncIntegration($id) {
		$integration = $this->integration($id);
		$this->deleteTimer('reviews_sync_'.$integration['id']);
		return $this->syncIntegration($integration,true);
	}

	public function cron() {
		$result = ['result'=>true,'items'=>[]];
		foreach ($this->integrations['integrations'] as $integration) {
			if (intval($integration['active'] ?? 0) != 1) continue;
			$result['items'][$integration['id']] = $this->syncIntegration($integration,false);
		}
		return $result;
	}

	public function syncIntegration($integration = [], $force = false) {
		$integration = $this->normalizeIntegration($integration);
		if ($integration['provider'] == 'google') return $this->syncGoogle($integration,$force);
		return ['result'=>false,'skipped'=>1,'count'=>0,'imported'=>0,'updated'=>0,'error'=>'provider_unavailable'];
	}

	public function syncGoogle($integration = [], $force = false) {
		$integration = $this->normalizeIntegration($integration);
		$result = ['result'=>false,'skipped'=>0,'count'=>0,'imported'=>0,'updated'=>0,'error'=>''];
		if ($integration['active'] != 1) {
			$result['skipped'] = 1;
			$result['result'] = true;
			return $result;
		}
		if (!$force && function_exists('helper__system_runtime') && !helper__system_runtime('reviews_sync_'.$integration['id'],24,false,'hours')) {
			$result['skipped'] = 1;
			$result['result'] = true;
			return $result;
		}
		$integration = $this->resolveGoogleTarget($integration);
		if (trim((string) ($integration['target']['account_name'] ?? '')) == '' || trim((string) ($integration['target']['location_name'] ?? '')) == '') {
			$result['skipped'] = 1;
			$result['error'] = 'google_location_missing';
			return $result;
		}
		if (!class_exists('\google\BusinessProfile')) {
			$result['error'] = 'google_unavailable';
			return $this->finishIntegrationSync($integration,$result);
		}

		$google = new \google\BusinessProfile($integration['account_ref']);
		$pageToken = '';
		$page = 0;
		do {
			$response = $google->reviews($integration['target']['account_name'],$integration['target']['location_name'],50,$pageToken,'updateTime desc');
			if (!is_array($response)) {
				$result['error'] = $this->googleLastError($google);
				return $this->finishIntegrationSync($integration,$result);
			}
			foreach ($response['reviews'] ?? [] as $review) {
				$state = $this->importGoogleReview($review,$integration);
				$result['count']++;
				if ($state == 'imported') $result['imported']++;
				if ($state == 'updated') $result['updated']++;
			}
			$pageToken = trim((string) ($response['nextPageToken'] ?? ''));
			$page++;
		} while ($pageToken != '' && $page < 10);

		$result['result'] = true;
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
		$this->write();
		return $this->finishIntegrationSync($integration,$result);
	}

	public function admin($filter, $language) {
		$filter = $this->normalizeFilter($filter);
		$rows = [];
		foreach ($this->data['reviews'] as $id => $entry) {
			$row = $this->row($id,$entry,$language);
			if (!$this->matchesAdmin($row,$filter)) continue;
			$rows[] = $row;
		}
		$rows = $this->sortRows($rows,$filter['sort'],$filter['direction']);
		$total = count($rows);
		$pages = max(1,intval(ceil($total / max(1,intval($filter['count'])))));
		$filter['page'] = min($pages,max(1,intval($filter['page'])));
		$rows = array_slice($rows,($filter['page'] - 1) * intval($filter['count']),intval($filter['count']));
		return ['rows'=>$rows,'total'=>$total,'pages'=>$pages,'filter'=>$filter];
	}

	public function widget($filter, $language) {
		$filter = $this->normalizeWidgetFilter($filter);
		$rows = [];
		foreach ($this->data['reviews'] as $id => $entry) {
			$row = $this->row($id,$entry,$language);
			if (empty($row['published'])) continue;
			if (!$this->matchesWidgetProvider($row['provider'],$filter['provider'])) continue;
			if (intval($row['rating']) < intval($filter['min_rating'])) continue;
			if (intval($filter['featured']) == 1 && empty($row['featured'])) continue;
			if (!$this->matchesWidgetLanguage($row['lid'],$filter['language'],$language)) continue;
			if ($row['text'] == '') continue;
			$rows[] = $row;
		}
		$rows = $this->sortRows($rows,$filter['sort'],$filter['direction']);
		if (intval($filter['limit']) > 0) $rows = array_slice($rows,0,intval($filter['limit']));
		return $rows;
	}

	public function summary($rows, $language) {
		$summary = ['count'=>count($rows),'rating_sum'=>0,'rating_average'=>0,'rating_average_label'=>'','rating_stars'=>'','rating_1_count'=>0,'rating_2_count'=>0,'rating_3_count'=>0,'rating_4_count'=>0,'rating_5_count'=>0];
		foreach ($rows as $row) {
			$rating = max(1,min(5,intval($row['rating'] ?? 0)));
			$summary['rating_sum'] += $rating;
			$summary['rating_'.$rating.'_count']++;
		}
		if ($summary['count'] > 0) $summary['rating_average'] = round($summary['rating_sum'] / $summary['count'],1);
		$summary['rating_average_label'] = $this->formatNumber($summary['rating_average'],$language);
		$summary['rating_stars'] = str_repeat('★',max(1,min(5,intval(round($summary['rating_average'])))));
		if ($summary['count'] == 0) $summary['rating_stars'] = '';
		$summary['rating_label'] = function_exists('language__get_parsed') ? language__get_parsed($language,'_reviews_rating_average_label',['rating'=>$summary['rating_average_label']]) : $summary['rating_average_label'].' / 5';
		return $summary;
	}

	private function load() {
		$data = ['reviews'=>[],'updated'=>0];
		if (is_file($this->dataFile)) {
			$loaded = $this->decode(file_get_contents($this->dataFile));
			if (is_array($loaded)) $data = array_merge($data,$loaded);
		}
		if (!isset($data['reviews']) || !is_array($data['reviews'])) $data['reviews'] = [];
		foreach ($data['reviews'] as $id => $entry) $data['reviews'][$id] = $this->normalizeEntry($id,$entry);
		return $data;
	}

	private function loadIntegrations() {
		$data = ['integrations'=>[],'updated'=>0];
		if (is_file($this->integrationsFile)) {
			$loaded = $this->decode(file_get_contents($this->integrationsFile));
			if (is_array($loaded)) $data = array_replace_recursive($data,$loaded);
		}
		if (isset($data['providers']['google']) && is_array($data['providers']['google'])) $data['integrations'][] = $this->legacyGoogleIntegration($data['providers']['google']);
		unset($data['providers']);
		if (!is_array($data['integrations'] ?? null)) $data['integrations'] = [];
		foreach ($data['integrations'] as $key => $integration) $data['integrations'][$key] = $this->normalizeIntegration($integration);
		return $data;
	}

	private function write() {
		if (function_exists('helper__files_write')) return helper__files_write($this->dataFile,$this->data,true,true);
		if (!is_dir(dirname($this->dataFile))) mkdir(dirname($this->dataFile),0775,true);
		return file_put_contents($this->dataFile,json_encode($this->data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
	}

	private function writeIntegrations() {
		if (function_exists('helper__files_write')) return helper__files_write($this->integrationsFile,$this->integrations,true,true);
		if (!is_dir(dirname($this->integrationsFile))) mkdir(dirname($this->integrationsFile),0775,true);
		return file_put_contents($this->integrationsFile,json_encode($this->integrations,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
	}

	private function normalizeEntry($id, $entry) {
		if (!is_array($entry)) $entry = [];
		$entry['id'] = trim((string) ($entry['id'] ?? $id));
		$entry['lid'] = $this->normalizeLanguages($entry['lid'] ?? ['all'],false);
		foreach (['author','source','text'] as $field) $entry[$field] = $this->normalizeText($entry[$field] ?? []);
		$entry['rating'] = max(1,min(5,intval($entry['rating'] ?? 5)));
		$entry['date'] = intval($entry['date'] ?? 0);
		$entry['published'] = !empty($entry['published']) ? 1 : 0;
		$entry['featured'] = !empty($entry['featured']) ? 1 : 0;
		$entry['provider'] = $this->validProvider($entry['provider'] ?? 'local') ? trim((string) $entry['provider']) : 'local';
		$entry['source_type'] = trim((string) ($entry['source_type'] ?? ($entry['provider'] == 'local' ? 'local' : 'provider')));
		$entry['external_id'] = trim((string) ($entry['external_id'] ?? ''));
		$entry['external_updated'] = intval($entry['external_updated'] ?? 0);
		$entry['imported'] = !empty($entry['imported']) ? 1 : 0;
		$entry['read_only'] = !empty($entry['read_only']) ? 1 : 0;
		return $entry;
	}

	private function row($id, $entry, $language) {
		$entry = $this->normalizeEntry($id,$entry);
		$entry['author'] = $this->resolveText($entry['author'],$language);
		$entry['author_initials'] = $this->initials($entry['author']);
		$entry['source'] = $this->resolveText($entry['source'],$language);
		$entry['text'] = $this->resolveText($entry['text'],$language);
		$entry['sort_id'] = $id;
		$entry['search'] = trim($id.' '.$entry['author'].' '.$entry['source'].' '.$entry['text'].' '.$entry['provider']);
		return $entry;
	}

	private function initials($name) {
		$name = preg_replace('/[^\p{L}\p{N}\s\-]+/u',' ',trim((string) $name));
		$name = preg_replace('/\s+/u',' ',trim((string) $name));
		if ($name == '') return '';
		$parts = preg_split('/[\s\-]+/u',$name);
		$parts = array_values(array_filter($parts,function($part) { return trim((string) $part) !== ''; }));
		if (empty($parts)) return '';
		$initials = mb_substr($parts[0],0,1,'UTF-8');
		$initials .= count($parts) > 1 ? mb_substr($parts[count($parts) - 1],0,1,'UTF-8') : mb_substr($parts[0],1,1,'UTF-8');
		return mb_strtoupper($initials,'UTF-8');
	}

	private function normalizeText($value) {
		$value = $this->decodeText($value);
		if (!is_array($value)) return [$this->defaultLanguage=>trim((string) $value)];
		if (count($value) == 1 && array_keys($value) === [0]) return [$this->defaultLanguage=>trim((string) reset($value))];
		foreach ($value as $language => $text) $value[$language] = trim((string) $text);
		return $value;
	}

	private function normalizePostedText($value) {
		$value = $this->decodeText($value);
		if (!is_array($value)) $value = [$this->defaultLanguage=>$value];
		if (count($value) == 1 && array_keys($value) === [0]) return [$this->defaultLanguage=>trim((string) reset($value))];
		foreach ($value as $language => $text) $value[$language] = trim((string) $text);
		return $value;
	}

	private function resolveText($value, $language) {
		$value = $this->normalizeText($value);
		if (function_exists('language__from_array')) return trim((string) language__from_array($value,$language));
		if (isset($value[$language]) && trim((string) $value[$language]) !== '') return trim((string) $value[$language]);
		if (isset($value[$this->defaultLanguage]) && trim((string) $value[$this->defaultLanguage]) !== '') return trim((string) $value[$this->defaultLanguage]);
		foreach ($value as $text) if (trim((string) $text) !== '') return trim((string) $text);
		return '';
	}

	private function normalizeLanguages($value, $strict) {
		$value = $this->decode($value);
		if (!is_array($value)) $value = [trim((string) $value)];
		$value = array_values(array_filter(array_map('strval',$value)));
		if (empty($value) || in_array('all',$value,true)) return ['all'];
		if ($strict) $value = array_values(array_intersect($value,$this->installedLanguages));
		return empty($value) ? ['all'] : $value;
	}

	private function matchesLanguage($languages, $language) {
		$languages = $this->normalizeLanguages($languages,false);
		return in_array('all',$languages,true) || in_array($language,$languages,true);
	}

	private function matchesWidgetLanguage($languages, $filter, $currentLanguage) {
		$filter = $this->normalizeWidgetLanguages($filter);
		if (in_array('all',$filter,true)) return true;
		if (in_array('current',$filter,true)) return $this->matchesLanguage($languages,$currentLanguage);
		$languages = $this->normalizeLanguages($languages,false);
		if (in_array('all',$languages,true)) return true;
		return count(array_intersect($filter,$languages)) > 0;
	}

	private function normalizeFilter($filter) {
		$default = ['page'=>1,'count'=>20,'sort'=>'date','direction'=>'DESC','search'=>[],'attributes'=>['published'=>'','featured'=>'','rating'=>'','lid'=>'','provider'=>'']];
		$filter = is_array($filter) ? array_replace_recursive($default,$filter) : $default;
		$filter['search'] = is_array($filter['search']) ? array_values(array_filter(array_map('trim',$filter['search']))) : [trim((string) $filter['search'])];
		$filter['page'] = max(1,intval($filter['page']));
		$filter['count'] = max(1,intval($filter['count']));
		$filter['direction'] = strtoupper((string) $filter['direction']) == 'ASC' ? 'ASC' : 'DESC';
		if (!in_array($filter['sort'],['date','rating','featured'],true)) $filter['sort'] = 'date';
		return $filter;
	}

	private function normalizeWidgetFilter($filter) {
		$default = ['limit'=>6,'min_rating'=>1,'featured'=>0,'language'=>['all'],'provider'=>['all'],'sort'=>'featured','direction'=>'DESC'];
		$filter = is_array($filter) ? array_merge($default,$filter) : $default;
		$filter['limit'] = max(0,intval($filter['limit']));
		$filter['min_rating'] = max(1,min(5,intval($filter['min_rating'])));
		$filter['featured'] = intval($filter['featured']) == 1 ? 1 : 0;
		$filter['language'] = $this->normalizeWidgetLanguages($filter['language']);
		$filter['provider'] = $this->normalizeWidgetProviders($filter['provider']);
		if (!in_array($filter['sort'],['featured','date','rating'],true)) $filter['sort'] = 'featured';
		$filter['direction'] = strtoupper((string) $filter['direction']) == 'ASC' ? 'ASC' : 'DESC';
		return $filter;
	}

	private function normalizeWidgetLanguages($value) {
		$value = $this->decode($value);
		if (!is_array($value)) $value = [trim((string) $value)];
		$value = array_values(array_filter(array_map('strval',$value)));
		if (empty($value)) return ['all'];
		if (in_array('all',$value,true)) return ['all'];
		return array_values(array_intersect($value,array_merge(['current'],$this->installedLanguages))) ?: ['all'];
	}

	private function normalizeWidgetProviders($value) {
		$value = $this->decode($value);
		if (!is_array($value)) $value = [trim((string) $value)];
		$value = array_values(array_filter(array_map('strval',$value)));
		if (empty($value) || in_array('all',$value,true)) return ['all'];
		return array_values(array_intersect($value,array_keys($this->providers()))) ?: ['all'];
	}

	private function matchesAdmin($row, $filter) {
		foreach ($filter['search'] as $search) if ($search !== '' && stripos($row['search'],$search) === false) return false;
		if ($filter['attributes']['published'] !== '' && intval($row['published']) != intval($filter['attributes']['published'])) return false;
		if ($filter['attributes']['featured'] !== '' && intval($row['featured']) != intval($filter['attributes']['featured'])) return false;
		if ($filter['attributes']['rating'] !== '' && intval($row['rating']) != intval($filter['attributes']['rating'])) return false;
		if ($filter['attributes']['provider'] !== '' && $row['provider'] != $filter['attributes']['provider']) return false;
		if ($filter['attributes']['lid'] !== '') {
			if ($filter['attributes']['lid'] == 'all' && !in_array('all',$row['lid'],true)) return false;
			if ($filter['attributes']['lid'] != 'all' && !in_array($filter['attributes']['lid'],$row['lid'],true)) return false;
		}
		return true;
	}

	private function matchesWidgetProvider($provider, $filter) {
		$filter = $this->normalizeWidgetProviders($filter);
		return in_array('all',$filter,true) || in_array($provider,$filter,true);
	}

	private function sortRows($rows, $sort, $direction) {
		usort($rows,function($a,$b) use ($sort,$direction) {
			if ($sort == 'featured') {
				$left = intval($a['featured'] ?? 0);
				$right = intval($b['featured'] ?? 0);
				if ($left != $right) return $right <=> $left;
			}
			$left = ($sort == 'rating') ? intval($a['rating'] ?? 0) : intval($a['date'] ?? 0);
			$right = ($sort == 'rating') ? intval($b['rating'] ?? 0) : intval($b['date'] ?? 0);
			if ($left == $right) return strcmp((string) ($b['sort_id'] ?? ''),(string) ($a['sort_id'] ?? ''));
			return $direction == 'ASC' ? ($left <=> $right) : ($right <=> $left);
		});
		return $rows;
	}

	private function legacyGoogleIntegration($config) {
		$config = is_array($config) ? $config : [];
		return [
			'id'=>'google',
			'label'=>'Google',
			'provider'=>'google',
			'active'=>!empty($config['active']) ? 1 : 0,
			'account_ref'=>trim((string) ($config['account_ref'] ?? 'default')) !== '' ? trim((string) ($config['account_ref'] ?? 'default')) : 'default',
			'target'=>[
				'account_name'=>trim((string) ($config['account_name'] ?? '')),
				'location_name'=>trim((string) ($config['location_name'] ?? '')),
				'location_title'=>trim((string) ($config['location_title'] ?? ''))
			],
			'last_sync'=>intval($config['last_sync'] ?? 0),
			'last_error'=>trim((string) ($config['last_error'] ?? '')),
			'last_count'=>intval($config['last_count'] ?? 0),
			'last_imported'=>intval($config['last_imported'] ?? 0),
			'last_updated'=>intval($config['last_updated'] ?? 0)
		];
	}

	private function normalizeIntegration($integration) {
		if (!is_array($integration)) $integration = [];
		$provider = $this->validProvider($integration['provider'] ?? '') ? trim((string) $integration['provider']) : 'google';
		$id = $this->validIntegrationId($integration['id'] ?? '') ? trim((string) $integration['id']) : $provider;
		$target = is_array($integration['target'] ?? null) ? $integration['target'] : [];
		return [
			'id'=>$id,
			'label'=>trim((string) ($integration['label'] ?? '')) != '' ? trim((string) ($integration['label'] ?? '')) : ucfirst($provider),
			'provider'=>$provider,
			'active'=>!empty($integration['active']) ? 1 : 0,
			'account_ref'=>trim((string) ($integration['account_ref'] ?? 'default')) !== '' ? trim((string) ($integration['account_ref'] ?? 'default')) : 'default',
			'target'=>[
				'account_name'=>trim((string) ($target['account_name'] ?? '')),
				'location_name'=>trim((string) ($target['location_name'] ?? '')),
				'location_title'=>trim((string) ($target['location_title'] ?? ''))
			],
			'last_sync'=>intval($integration['last_sync'] ?? 0),
			'last_error'=>trim((string) ($integration['last_error'] ?? '')),
			'last_count'=>intval($integration['last_count'] ?? 0),
			'last_imported'=>intval($integration['last_imported'] ?? 0),
			'last_updated'=>intval($integration['last_updated'] ?? 0)
		];
	}

	private function resolveGoogleTarget($integration) {
		$integration = $this->normalizeIntegration($integration);
		if (trim((string) ($integration['target']['account_name'] ?? '')) != '' && trim((string) ($integration['target']['location_name'] ?? '')) != '') return $integration;
		$choices = $this->googleLocationChoices($integration);
		if (!$choices['result'] || count($choices['items']) != 1) return $integration;
		$location = $this->decode($choices['items'][0]['value']);
		if (!is_array($location)) return $integration;
		$integration['target'] = [
			'account_name'=>trim((string) ($location['account_name'] ?? '')),
			'location_name'=>trim((string) ($location['location_name'] ?? '')),
			'location_title'=>trim((string) ($location['location_title'] ?? ''))
		];
		if ($integration['last_error'] == 'google_location_missing') $integration['last_error'] = '';
		$this->storeIntegration($integration);
		return $integration;
	}

	private function storeIntegration($integration) {
		$integration = $this->normalizeIntegration($integration);
		foreach ($this->integrations['integrations'] as $key => $entry) {
			if ($entry['id'] != $integration['id']) continue;
			$this->integrations['integrations'][$key] = $integration;
			$this->integrations['updated'] = intval($_SERVER['now'] ?? time());
			return $this->writeIntegrations();
		}
		$this->integrations['integrations'][] = $integration;
		$this->integrations['updated'] = intval($_SERVER['now'] ?? time());
		return $this->writeIntegrations();
	}

	private function importGoogleReview($review, $integration) {
		$integration = $this->normalizeIntegration($integration);
		$externalId = trim((string) ($review['reviewId'] ?? ($review['name'] ?? '')));
		$id = $this->findProviderReview('google',$externalId);
		if ($id == '') $id = $this->findGoogleDuplicate($review);
		$state = $id == '' ? 'imported' : 'updated';
		if ($id == '') $id = $this->createProviderId('google',$externalId);

		$existing = $this->data['reviews'][$id] ?? ['id'=>$id,'created'=>intval($_SERVER['now'] ?? time()),'published'=>1,'featured'=>0,'lid'=>['all']];
		$entry = [
			'id'=>$id,
			'created'=>$existing['created'] ?? intval($_SERVER['now'] ?? time()),
			'author'=>[$this->defaultLanguage=>trim((string) ($review['reviewer']['displayName'] ?? ''))],
			'source'=>[$this->defaultLanguage=>$integration['target']['location_title'] != '' ? $integration['target']['location_title'] : $integration['label']],
			'rating'=>$this->googleRating($review['starRating'] ?? 5),
			'text'=>[$this->defaultLanguage=>trim((string) ($review['comment'] ?? ''))],
			'lid'=>$existing['lid'] ?? ['all'],
			'date'=>$this->googleTime($review['createTime'] ?? ''),
			'published'=>intval($existing['published'] ?? 1),
			'featured'=>intval($existing['featured'] ?? 0),
			'provider'=>'google',
			'source_type'=>'provider',
			'external_id'=>$externalId,
			'external_updated'=>$this->googleTime($review['updateTime'] ?? ''),
			'imported'=>1,
			'read_only'=>1,
			'updated'=>intval($_SERVER['now'] ?? time())
		];
		$this->data['reviews'][$id] = $this->normalizeEntry($id,$entry);
		return $state;
	}

	private function finishIntegrationSync($integration, $result) {
		$integration = $this->normalizeIntegration($integration);
		$integration['last_sync'] = intval($_SERVER['now'] ?? time());
		$integration['last_error'] = trim((string) ($result['error'] ?? ''));
		$integration['last_count'] = intval($result['count'] ?? 0);
		$integration['last_imported'] = intval($result['imported'] ?? 0);
		$integration['last_updated'] = intval($result['updated'] ?? 0);
		$this->storeIntegration($integration);
		return $result;
	}

	private function findProviderReview($provider, $externalId) {
		if (trim((string) $externalId) == '') return '';
		foreach ($this->data['reviews'] as $id => $entry) {
			$entry = $this->normalizeEntry($id,$entry);
			if ($entry['provider'] == $provider && $entry['external_id'] == $externalId) return $id;
		}
		return '';
	}

	private function findGoogleDuplicate($review) {
		$author = trim((string) ($review['reviewer']['displayName'] ?? ''));
		$text = trim((string) ($review['comment'] ?? ''));
		$rating = $this->googleRating($review['starRating'] ?? 5);
		$date = $this->googleTime($review['createTime'] ?? '');
		foreach ($this->data['reviews'] as $id => $entry) {
			$row = $this->row($id,$entry,$this->defaultLanguage);
			if ($row['provider'] != 'google') continue;
			if ($row['external_id'] != '') continue;
			if (intval($row['rating']) != $rating || intval($row['date']) != $date) continue;
			if (trim((string) $row['author']) != $author || trim((string) $row['text']) != $text) continue;
			return $id;
		}
		return '';
	}

	private function createProviderId($provider, $externalId) {
		$id = $provider.'_'.substr(sha1($externalId != '' ? $externalId : random_bytes(16)),0,16);
		return isset($this->data['reviews'][$id]) ? $id.'_'.bin2hex(random_bytes(2)) : $id;
	}

	private function googleRating($value) {
		$ratings = ['ONE'=>1,'TWO'=>2,'THREE'=>3,'FOUR'=>4,'FIVE'=>5];
		if (is_numeric($value)) return max(1,min(5,intval($value)));
		return $ratings[strtoupper(trim((string) $value))] ?? 5;
	}

	private function googleTime($value) {
		$time = strtotime(trim((string) $value));
		return $time === false ? intval($_SERVER['now'] ?? time()) : intval($time);
	}

	private function googleLastError($google) {
		$last = method_exists($google,'last') ? $google->last() : [];
		if (isset($last['body']['error']['message'])) return trim((string) $last['body']['error']['message']);
		if (isset($last['error']) && trim((string) $last['error']) !== '') return trim((string) $last['error']);
		return 'google_request_failed';
	}

	private function timer($name) {
		$file = defined('CACHEPATH') ? CACHEPATH.'/timers/.'.$name : '';
		return $file != '' && is_file($file) ? intval(file_get_contents($file)) : 0;
	}

	private function deleteTimer($name) {
		$file = defined('CACHEPATH') ? CACHEPATH.'/timers/.'.$name : '';
		if ($file == '' || !is_file($file)) return false;
		if (function_exists('helper__files_delete')) return helper__files_delete($file,true);
		return unlink($file);
	}

	private function validProvider($provider) {
		return preg_match('/^[a-z0-9_-]+$/',trim((string) $provider));
	}

	private function validIntegrationId($id) {
		return preg_match('/^[A-Za-z0-9_-]+$/',trim((string) $id));
	}

	private function createIntegrationId($provider) {
		$id = $this->validProvider($provider) ? trim((string) $provider) : 'integration';
		foreach ($this->integrations['integrations'] as $integration) if ($integration['id'] == $id) $id = '';
		if ($id != '') return $id;
		return 'integration_'.intval($_SERVER['now'] ?? time()).'_'.bin2hex(random_bytes(2));
	}

	private function createId() {
		return 'review_'.intval($_SERVER['now'] ?? time()).'_'.bin2hex(random_bytes(4));
	}

	private function validId($id) {
		$id = trim((string) $id);
		return $id !== '' && preg_match('/^[A-Za-z0-9_.-]+$/',$id);
	}

	private function formatNumber($value, $language) {
		if (class_exists('NumberFormatter')) {
			$formatter = new NumberFormatter(str_replace('_','-',(string) $language),NumberFormatter::DECIMAL);
			$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS,1);
			$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS,1);
			return $formatter->format($value);
		}
		return number_format($value,1,'.','');
	}

	private function decodeText($value) {
		if (!is_string($value)) return $value;
		$decoded = json_decode($value,true);
		return json_last_error() == JSON_ERROR_NONE ? $decoded : $value;
	}

	private function decode($value) {
		if (function_exists('helper__json_convert')) return helper__json_convert($value);
		if (!is_string($value)) return $value;
		$decoded = json_decode($value,true);
		return json_last_error() == JSON_ERROR_NONE ? $decoded : $value;
	}
}
