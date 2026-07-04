<?php

require_once __DIR__.'/Reviews/Normalizer.php';
require_once __DIR__.'/Reviews/JsonStorage.php';
require_once __DIR__.'/Reviews/Rows.php';
require_once __DIR__.'/Providers/Provider.php';
foreach (glob(__DIR__.'/Providers/*Provider.php') ?: [] as $file) if (basename($file) != 'Provider.php') require_once $file;
require_once __DIR__.'/Reviews/ProviderRegistry.php';

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
	private $normalizer;
	private $providerRegistry;
	private $rows;

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
		$this->normalizer = new FiCMSReviewsNormalizer($this->defaultLanguage,$this->installedLanguages);
		$this->providerRegistry = new FiCMSReviewsProviderRegistry($this,$this->basePath);
		$this->rows = new FiCMSReviewsRows($this,$this->normalizer,$this->installedLanguages);
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
		return $this->rows->row($id,$this->find($id),$language);
	}

	public function blank($id = 'new') {
		return ['id'=>$id,'author'=>'','source'=>'','rating'=>5,'text'=>[],'lid'=>['all'],'date'=>intval($_SERVER['now'] ?? time()),'published'=>($id == 'new' ? 1 : 0),'featured'=>0,'provider'=>'local','source_type'=>'local','external_id'=>'','external_url'=>'','external_updated'=>0,'imported'=>0,'read_only'=>0];
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
		$entry['lid'] = $this->normalizer->languages($post['lid'] ?? ['all'],true);
		if (intval($entry['read_only'] ?? 0) != 1 || ($entry['provider'] ?? 'local') == 'local') {
			foreach (['author','source'] as $field) $entry[$field] = $this->normalizer->plainText($post[$field] ?? '');
			$entry['text'] = $this->normalizer->text($post['text'] ?? []);
			$entry['rating'] = max(1,min(5,intval($post['rating'] ?? 5)));
			$entry['date'] = intval($post['date'] ?? ($_SERVER['now'] ?? time()));
			$entry['provider'] = 'local';
			$entry['source_type'] = 'local';
			$entry['external_id'] = '';
			$entry['external_url'] = '';
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
		return $this->providerRegistry->definitions();
	}

	public function defaultLanguage() {
		return $this->defaultLanguage;
	}

	public function providerRequirements($provider, $integration = []) {
		$instance = $this->provider($provider);
		return $instance ? $instance->requirements($integration) : ['oauth'=>0,'sync'=>0,'config_error'=>'','connect'=>0,'location_choices'=>0,'oauth_accounts'=>0,'oauth_account_options'=>[],'form_fields'=>[],'form_values'=>[]];
	}

	private function provider($provider) {
		return $this->providerRegistry->instance($provider);
	}

	private function defaultProvider() {
		return $this->providerRegistry->defaultProvider();
	}

	public function getProviderLogo($provider = '') {
		return $this->providerRegistry->logo($provider);
	}

	public function providerDisplayText($provider, $text, $language) {
		$instance = $this->provider($provider);
		return $instance ? $instance->displayText($text,$language) : $text;
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

	public function previewIntegrationStatus($integration) {
		$integration = $this->normalizeIntegration($integration);
		$provider = $this->provider($integration['provider']);
		if ($provider) $integration = $provider->status($integration);
		else {
			$integration['connected'] = 0;
			$integration['provider_available'] = 0;
			$integration['oauth_available'] = 0;
			$integration['ready'] = 0;
		}
		$integration['timer'] = 0;
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
		return $this->rows->admin($this->data['reviews'],$filter,$language);
	}

	public function widget($filter, $language) {
		return $this->rows->widget($this->data['reviews'],$filter,$language);
	}

	public function summaryRows($filter, $language) {
		return $this->rows->summaryRows($this->data['reviews'],$filter,$language);
	}

	public function summary($rows, $language) {
		return $this->rows->summary($rows,$language);
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

	public function write() {
		$this->data['ratings'] = $this->aggregateRatings($this->data['reviews']);
		return FiCMSReviewsJsonStorage::write($this->dataFile,$this->data);
	}

	public function touchData() {
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
	}

	private function writeIntegrations() {
		return FiCMSReviewsJsonStorage::write($this->integrationsFile,$this->integrations);
	}

	private function normalizeEntry($id, $entry) {
		return $this->normalizer->entry($id,$entry);
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
				'location_title'=>trim((string) ($target['location_title'] ?? '')),
				'page_id'=>trim((string) ($target['page_id'] ?? ''))
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
		foreach ($this->normalizer->text($review['text'] ?? []) as $language => $value) {
			if (trim((string) $value) == '') continue;
			$text[$language] = trim((string) $value);
		}
		$entry = [
			'id'=>$id,
			'created'=>$existing['created'] ?? intval($_SERVER['now'] ?? time()),
			'author'=>$this->normalizer->plainText($review['author'] ?? $existing['author']),
			'source'=>$this->normalizer->plainText($review['source'] ?? $existing['source']),
			'rating'=>max(1,min(5,intval($review['rating'] ?? $existing['rating']))),
			'text'=>$text,
			'lid'=>$this->normalizer->reviewLanguages($review,$text),
			'date'=>intval($review['date'] ?? $existing['date']),
			'published'=>intval($existing['published'] ?? 1),
			'featured'=>intval($existing['featured'] ?? 0),
			'provider'=>$provider,
			'source_type'=>'provider',
			'external_id'=>$externalId,
			'external_url'=>$this->normalizer->url($review['external_url'] ?? ($existing['external_url'] ?? '')),
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
		return $this->normalizer->syncLanguage($language);
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
		return unlink($file);
	}

	private function validProvider($provider) {
		return FiCMSReviewsNormalizer::validProviderKey($provider);
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

	public function decode($value) {
		return $this->normalizer->decode($value);
	}
}
