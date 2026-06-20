<?php

require_once __DIR__.'/Providers/Provider.php';
foreach (glob(__DIR__.'/Providers/*Provider.php') ?: [] as $file) if (basename($file) != 'Provider.php') require_once $file;

class FiCMSReviews {
	const PROVIDER_REFRESH_VERSION = 'provider_language_sync_202606201000';
	const PROVIDER_REFRESH_CUTOFF = '2026-06-20 10:00:00 Europe/Berlin';

	private $basePath = '';
	private $dataFile = '';
	private $integrationsFile = '';
	private $defaultLanguage = 'de';
	private $installedLanguages = [];
	private $data = [];
	private $integrations = [];
	private $providerClasses = [];
	private $providerInstances = [];

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
		$this->providerClasses = $this->loadProviderClasses();
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

	public function display($id, $language) {
		return $this->row($id,$this->find($id),$language);
	}

	public function blank($id = 'new') {
		return ['id'=>$id,'author'=>'','source'=>'','rating'=>5,'text'=>[],'lid'=>['all'],'date'=>intval($_SERVER['now'] ?? time()),'published'=>($id == 'new' ? 1 : 0),'featured'=>0,'provider'=>'local','source_type'=>'local','external_id'=>'','external_updated'=>0,'imported'=>0,'read_only'=>0];
	}

	public function delete($id) {
		$id = trim((string) $id);
		if ($id == '' || !isset($this->data['reviews'][$id])) return false;
		unset($this->data['reviews'][$id]);
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
		return $this->write();
	}

	public function setPublished($id, $published) {
		$id = trim((string) $id);
		if ($id == '' || !isset($this->data['reviews'][$id])) return false;
		$this->data['reviews'][$id] = $this->normalizeEntry($id,$this->data['reviews'][$id]);
		$this->data['reviews'][$id]['published'] = intval($published) == 1 ? 1 : 0;
		$this->data['reviews'][$id]['updated'] = intval($_SERVER['now'] ?? time());
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
			foreach (['author','source'] as $field) $entry[$field] = $this->normalizePlainText($post[$field] ?? '');
			$entry['text'] = $this->normalizePostedText($post['text'] ?? []);
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
		$definitions = [];
		foreach ($this->providerClasses as $key => $class) $definitions[$key] = $class::definition();
		return $definitions;
	}

	public function defaultLanguage() {
		return $this->defaultLanguage;
	}

	public function providerRequirements($provider, $integration = []) {
		$instance = $this->provider($provider);
		return $instance ? $instance->requirements($integration) : ['oauth'=>0,'sync'=>0,'config_error'=>'','connect'=>0,'location_choices'=>0,'form_fields'=>[],'form_values'=>[]];
	}

	private function provider($provider) {
		$provider = $this->validProvider($provider) ? trim((string) $provider) : '';
		if ($provider == '' || !isset($this->providerClasses[$provider])) return false;
		if (!isset($this->providerInstances[$provider])) {
			$class = $this->providerClasses[$provider];
			$this->providerInstances[$provider] = new $class($this);
		}
		return $this->providerInstances[$provider];
	}

	private function defaultProvider() {
		$providers = array_keys($this->providerClasses);
		return $providers[0] ?? 'provider';
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
		return ['id'=>$id,'label'=>'','provider'=>$this->defaultProvider(),'active'=>1,'account_ref'=>'default','target'=>[],'config'=>[],'last_sync'=>0,'last_error'=>'','last_count'=>0,'last_imported'=>0,'last_updated'=>0];
	}

	public function saveIntegrationFromPost($id, $post) {
		$original = $this->validIntegrationId($id) ? trim((string) $id) : '';
		$provider = $this->provider($post['integration_provider'] ?? '') ? trim((string) $post['integration_provider']) : $this->defaultProvider();
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
		$integration = $this->provider($provider)->saveIntegration($integration,$post,$existing);
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
		$provider = $this->provider($integration['provider']);
		return $provider ? $provider->connect($integration) : ['result'=>false];
	}

	public function integrationStatus($id) {
		$integration = $this->integration($id);
		$provider = $this->provider($integration['provider']);
		if ($provider) $integration = $provider->status($integration);
		else {
			$integration['connected'] = 0;
			$integration['provider_available'] = 0;
			$integration['oauth_available'] = 0;
			$integration['ready'] = 0;
		}
		$integration['timer'] = $this->timer('reviews_sync_'.$integration['id']);
		return $integration;
	}

	public function providerLocationChoices($integration = []) {
		$integration = $this->normalizeIntegration($integration);
		$provider = $this->provider($integration['provider']);
		return $provider && method_exists($provider,'locationChoices') ? $provider->locationChoices($integration) : ['result'=>false,'items'=>[],'error'=>'provider_locations_unavailable'];
	}

	public function providerOAuthError($provider, $error) {
		$provider = $this->provider($provider);
		return $provider && method_exists($provider,'oauthError') ? $provider->oauthError($error) : false;
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
		$provider = $this->provider($integration['provider']);
		return $provider ? $provider->sync($integration,$force) : ['result'=>false,'skipped'=>1,'count'=>0,'imported'=>0,'updated'=>0,'error'=>'provider_unavailable'];
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
			if (empty($row['has_text'])) continue;
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
		$data = ['reviews'=>[],'updated'=>0,'ratings'=>$this->emptyRatings(),'provider_refresh'=>[]];
		if (is_file($this->dataFile)) {
			$loaded = $this->decode(file_get_contents($this->dataFile));
			if (is_array($loaded)) $data = array_merge($data,$loaded);
		}
		if (!isset($data['reviews']) || !is_array($data['reviews'])) $data['reviews'] = [];
		foreach ($data['reviews'] as $id => $entry) $data['reviews'][$id] = $this->normalizeEntry($id,$entry);
		if (!is_array($data['provider_refresh'] ?? null)) $data['provider_refresh'] = [];
		$data['ratings'] = $this->aggregateRatings($data['reviews']);
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

	private function loadProviderClasses() {
		$providers = [];
		foreach (get_declared_classes() as $class) {
			if (!is_subclass_of($class,'FiCMSReviewsProvider')) continue;
			$key = trim((string) $class::key());
			if (!$this->validProvider($key)) continue;
			$providers[$key] = $class;
		}
		ksort($providers);
		return $providers;
	}

	public function write() {
		$this->data['ratings'] = $this->aggregateRatings($this->data['reviews']);
		if (function_exists('helper__files_write')) return helper__files_write($this->dataFile,$this->data,true,true);
		if (!is_dir(dirname($this->dataFile))) mkdir(dirname($this->dataFile),0775,true);
		return file_put_contents($this->dataFile,json_encode($this->data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
	}

	public function touchData() {
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
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
		foreach (['author','source'] as $field) $entry[$field] = $this->normalizePlainText($entry[$field] ?? '');
		$entry['text'] = $this->normalizeText($entry['text'] ?? []);
		$entry['rating'] = max(1,min(5,intval($entry['rating'] ?? 5)));
		$entry['date'] = intval($entry['date'] ?? 0);
		$entry['published'] = !empty($entry['published']) ? 1 : 0;
		$entry['featured'] = !empty($entry['featured']) ? 1 : 0;
		$entry['provider'] = trim((string) ($entry['provider'] ?? 'local'));
		if (!$this->validProvider($entry['provider'])) $entry['provider'] = 'local';
		$entry['source_type'] = trim((string) ($entry['source_type'] ?? ($entry['provider'] == 'local' ? 'local' : 'provider')));
		$entry['external_id'] = trim((string) ($entry['external_id'] ?? ''));
		$entry['external_updated'] = intval($entry['external_updated'] ?? 0);
		$entry['imported'] = !empty($entry['imported']) ? 1 : 0;
		$entry['read_only'] = !empty($entry['read_only']) ? 1 : 0;
		if ($entry['imported'] == 1 && in_array('all',$entry['lid'],true) && count($this->installedLanguages) == 1) $entry['lid'] = [$this->installedLanguages[0]];
		return $entry;
	}

	private function row($id, $entry, $language) {
		$entry = $this->normalizeEntry($id,$entry);
		$entry['author'] = trim((string) $entry['author']);
		$entry['author_initials'] = $this->initials($entry['author']);
		$entry['source'] = trim((string) $entry['source']);
		$entry['text'] = $this->resolveText($entry['text'],$language);
		$provider = $this->provider($entry['provider']);
		if ($provider) $entry['text'] = $provider->displayText($entry['text'],$language);
		$entry['has_text'] = trim((string) $entry['text']) !== '' ? 1 : 0;
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

	private function normalizePlainText($value) {
		$value = $this->decodeText($value);
		if (!is_array($value)) return trim((string) $value);
		if (isset($value[$this->defaultLanguage]) && trim((string) $value[$this->defaultLanguage]) !== '') return trim((string) $value[$this->defaultLanguage]);
		foreach ($value as $text) if (trim((string) $text) !== '') return trim((string) $text);
		return '';
	}

	private function resolveText($value, $language) {
		$value = $this->normalizeText($value);
		if (function_exists('language__from_array')) return trim((string) language__from_array($value,$language));
		if (isset($value[$language]) && trim((string) $value[$language]) !== '') return trim((string) $value[$language]);
		if (isset($value[$this->defaultLanguage]) && trim((string) $value[$this->defaultLanguage]) !== '') return trim((string) $value[$this->defaultLanguage]);
		foreach ($value as $text) if (trim((string) $text) !== '') return trim((string) $text);
		return '';
	}

	private function textLanguages($text) {
		$languages = [];
		foreach ($this->normalizeText($text) as $language => $value) {
			if (trim((string) $value) == '') continue;
			if (in_array($language,$this->installedLanguages,true)) $languages[] = $language;
		}
		return empty($languages) ? ['all'] : array_values(array_unique($languages));
	}

	private function reviewLanguages($review, $text) {
		$languages = $this->normalizeLanguages($review['languages'] ?? ($review['lid'] ?? []),true);
		if (!in_array('all',$languages,true)) return $languages;
		$languages = $this->textLanguages($text);
		if (!in_array('all',$languages,true)) return $languages;
		return count($this->installedLanguages) == 1 ? [$this->installedLanguages[0]] : ['all'];
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

	public function normalizeIntegration($integration) {
		if (!is_array($integration)) $integration = [];
		$provider = $this->validProvider($integration['provider'] ?? '') ? trim((string) $integration['provider']) : $this->defaultProvider();
		$id = $this->validIntegrationId($integration['id'] ?? '') ? trim((string) $integration['id']) : $provider;
		$target = is_array($integration['target'] ?? null) ? $integration['target'] : [];
		$config = is_array($integration['config'] ?? null) ? $integration['config'] : [];
		$normalizedConfig = [];
		foreach ($config as $key => $value) {
			$key = preg_replace('/[^a-z0-9_-]+/i','',trim((string) $key));
			if ($key == '' || is_array($value) || is_object($value)) continue;
			$normalizedConfig[$key] = trim((string) $value);
		}
		$normalized = [
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
			'config'=>$normalizedConfig,
			'last_sync'=>intval($integration['last_sync'] ?? 0),
			'last_error'=>trim((string) ($integration['last_error'] ?? '')),
			'last_count'=>intval($integration['last_count'] ?? 0),
			'last_imported'=>intval($integration['last_imported'] ?? 0),
			'last_updated'=>intval($integration['last_updated'] ?? 0)
		];
		$providerInstance = $this->provider($provider);
		return $providerInstance ? $providerInstance->normalizeIntegration($normalized) : $normalized;
	}

	public function storeIntegration($integration) {
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

	public function importProviderReview($provider, $review, $id = '') {
		$provider = $this->validProvider($provider) ? trim((string) $provider) : 'provider';
		$externalId = trim((string) ($review['external_id'] ?? ''));
		$id = $this->validId($id) ? trim((string) $id) : $this->findProviderReview($provider,$externalId);
		$state = $id == '' ? 'imported' : 'updated';
		if ($id == '') $id = $this->createProviderId($provider,$externalId);

		$existing = $this->normalizeEntry($id,$this->data['reviews'][$id] ?? ['id'=>$id,'created'=>intval($_SERVER['now'] ?? time()),'published'=>1,'featured'=>0,'lid'=>[]]);
		$text = $existing['text'];
		foreach ($this->normalizeText($review['text'] ?? []) as $language => $value) {
			if (trim((string) $value) == '') continue;
			$text[$language] = trim((string) $value);
		}
		$entry = [
			'id'=>$id,
			'created'=>$existing['created'] ?? intval($_SERVER['now'] ?? time()),
			'author'=>$this->normalizePlainText($review['author'] ?? $existing['author']),
			'source'=>$this->normalizePlainText($review['source'] ?? $existing['source']),
			'rating'=>max(1,min(5,intval($review['rating'] ?? $existing['rating']))),
			'text'=>$text,
			'lid'=>$this->reviewLanguages($review,$text),
			'date'=>intval($review['date'] ?? $existing['date']),
			'published'=>intval($existing['published'] ?? 1),
			'featured'=>intval($existing['featured'] ?? 0),
			'provider'=>$provider,
			'source_type'=>'provider',
			'external_id'=>$externalId,
			'external_updated'=>intval($review['external_updated'] ?? $existing['external_updated']),
			'imported'=>1,
			'read_only'=>1,
			'updated'=>intval($_SERVER['now'] ?? time())
		];
		$this->data['reviews'][$id] = $this->normalizeEntry($id,$entry);
		return $state;
	}

	public function finishIntegrationSync($integration, $result) {
		$integration = $this->normalizeIntegration($integration);
		$integration['last_sync'] = intval($_SERVER['now'] ?? time());
		$integration['last_error'] = trim((string) ($result['error'] ?? ''));
		$integration['last_count'] = intval($result['count'] ?? 0);
		$integration['last_imported'] = intval($result['imported'] ?? 0);
		$integration['last_updated'] = intval($result['updated'] ?? 0);
		$this->storeIntegration($integration);
		return $result;
	}

	public function findProviderReview($provider, $externalId) {
		if (trim((string) $externalId) == '') return '';
		foreach ($this->data['reviews'] as $id => $entry) {
			$entry = $this->normalizeEntry($id,$entry);
			if ($entry['provider'] == $provider && $entry['external_id'] == $externalId) return $id;
		}
		return '';
	}

	private function createProviderId($provider, $externalId) {
		$id = $provider.'_'.substr(sha1($externalId != '' ? $externalId : random_bytes(16)),0,16);
		return isset($this->data['reviews'][$id]) ? $id.'_'.bin2hex(random_bytes(2)) : $id;
	}

	public function syncLanguages() {
		$languages = array_values(array_unique(array_filter(array_map([$this,'normalizeSyncLanguage'],$this->installedLanguages))));
		return empty($languages) ? [$this->defaultLanguage] : $languages;
	}

	public function normalizeSyncLanguage($language) {
		$language = strtolower(trim((string) $language));
		$language = preg_replace('/[^a-z0-9_-]+/','',$language);
		return $language != '' ? $language : $this->defaultLanguage;
	}

	public function acceptLanguage($language) {
		$language = $this->normalizeSyncLanguage($language);
		return $language == $this->defaultLanguage ? $language : $language.','.$this->defaultLanguage.';q=0.7';
	}

	public function providerRefreshRequired($provider) {
		$provider = $this->validProvider($provider) ? trim((string) $provider) : '';
		return $provider != '' && trim((string) ($this->data['provider_refresh'][$provider] ?? '')) != self::PROVIDER_REFRESH_VERSION;
	}

	public function markProviderRefresh($provider) {
		$provider = $this->validProvider($provider) ? trim((string) $provider) : '';
		if ($provider == '') return;
		if (!is_array($this->data['provider_refresh'] ?? null)) $this->data['provider_refresh'] = [];
		$this->data['provider_refresh'][$provider] = self::PROVIDER_REFRESH_VERSION;
	}

	public function providerRefreshCutoff() {
		$time = strtotime(self::PROVIDER_REFRESH_CUTOFF);
		return $time === false ? 0 : intval($time);
	}

	public function removeImportedProviderReviews($provider, $cutoff) {
		$removed = 0;
		foreach ($this->data['reviews'] as $id => $entry) {
			$entry = $this->normalizeEntry($id,$entry);
			if ($entry['provider'] != $provider || intval($entry['imported']) != 1) continue;
			if ($this->entryImportTime($entry) >= intval($cutoff)) continue;
			unset($this->data['reviews'][$id]);
			$removed++;
		}
		if ($removed > 0) $this->data['updated'] = intval($_SERVER['now'] ?? time());
		return $removed;
	}

	private function entryImportTime($entry) {
		foreach (['updated','created','external_updated'] as $field) if (intval($entry[$field] ?? 0) > 0) return intval($entry[$field]);
		return 0;
	}

	private function emptyRatings() {
		return ['total'=>['count'=>0,'sum'=>0,'average'=>0],'providers'=>[]];
	}

	private function aggregateRatings($reviews) {
		$ratings = $this->emptyRatings();
		if (!is_array($reviews)) return $ratings;
		foreach ($reviews as $id => $entry) {
			$entry = $this->normalizeEntry($id,$entry);
			if (intval($entry['published']) != 1) continue;
			$rating = max(1,min(5,intval($entry['rating'])));
			$provider = $entry['provider'] != '' ? $entry['provider'] : 'local';
			if (!isset($ratings['providers'][$provider])) $ratings['providers'][$provider] = ['count'=>0,'sum'=>0,'average'=>0];
			$ratings['total']['count']++;
			$ratings['total']['sum'] += $rating;
			$ratings['providers'][$provider]['count']++;
			$ratings['providers'][$provider]['sum'] += $rating;
		}
		if ($ratings['total']['count'] > 0) $ratings['total']['average'] = round($ratings['total']['sum'] / $ratings['total']['count'],1);
		foreach ($ratings['providers'] as $provider => $rating) if ($rating['count'] > 0) $ratings['providers'][$provider]['average'] = round($rating['sum'] / $rating['count'],1);
		ksort($ratings['providers']);
		return $ratings;
	}

	private function timer($name) {
		$file = defined('CACHEPATH') ? CACHEPATH.'/timers/.'.$name : '';
		return $file != '' && is_file($file) ? intval(file_get_contents($file)) : 0;
	}

	public function deleteTimer($name) {
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

	public function decode($value) {
		if (function_exists('helper__json_convert')) return helper__json_convert($value);
		if (!is_string($value)) return $value;
		$decoded = json_decode($value,true);
		return json_last_error() == JSON_ERROR_NONE ? $decoded : $value;
	}
}
