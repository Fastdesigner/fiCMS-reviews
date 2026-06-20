<?php

class FiCMSReviewsGoogleProvider extends FiCMSReviewsProvider {
	public static function key() {
		return 'google';
	}

	public static function definition() {
		return ['name'=>'Google','oauth'=>1,'sync'=>1];
	}

	public function requirements($integration = []) {
		$integration = $this->reviews->normalizeIntegration($integration);
		return [
			'oauth'=>1,
			'sync'=>1,
			'connect'=>1,
			'location_choices'=>1,
			'config_error'=>'google_location_missing',
			'oauth_provider'=>'Google',
			'target_label'=>'_reviews_integration_target',
			'location_select'=>['option'=>'integration_google_location','label'=>'_reviews_google_location_name','empty'=>'_reviews_google_location_select'],
			'form_fields'=>['account_ref'=>['type'=>'hidden']],
			'form_values'=>['account_ref'=>$integration['account_ref']]
		];
	}

	public function saveIntegration($integration, $post, $existing) {
		if (!isset($post['integration_google_location']) || !is_string($post['integration_google_location'])) return $integration;
		$location = $this->reviews->decode($post['integration_google_location']);
		if (is_array($location)) $integration['target'] = [
			'account_name'=>trim((string) ($location['account_name'] ?? '')),
			'location_name'=>trim((string) ($location['location_name'] ?? '')),
			'location_title'=>trim((string) ($location['location_title'] ?? ''))
		];
		if (is_array($location) && $integration['last_error'] == 'google_location_missing') $integration['last_error'] = '';
		return $integration;
	}

	public function connect($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		if (!class_exists('\oauth\OAuth')) {
			$integration['last_error'] = 'OAuth plugin unavailable';
			$this->reviews->storeIntegration($integration);
			return ['result'=>false,'error'=>$integration['last_error']];
		}
		if (!\oauth\OAuth::provider('google',false)) {
			$integration['last_error'] = 'Google OAuth provider unavailable';
			$this->reviews->storeIntegration($integration);
			return ['result'=>false,'error'=>$integration['last_error']];
		}
		if ($integration['last_error'] != '') {
			$integration['last_error'] = '';
			$this->reviews->storeIntegration($integration);
		}
		return ['result'=>true,'redirect'=>PAGEPATH.'/oauth.php?action=authorize&provider=google&account='.rawurlencode($integration['account_ref']),'redirect_target'=>'_blank'];
	}

	public function status($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		if (class_exists('\oauth\OAuth') && \oauth\OAuth::account_load('google',$integration['account_ref']) && trim((string) ($integration['target']['location_name'] ?? '')) == '') $integration = $this->resolveTarget($integration);
		$integration['connected'] = class_exists('\oauth\OAuth') && \oauth\OAuth::account_load('google',$integration['account_ref']) ? 1 : 0;
		$integration['provider_available'] = class_exists('\google\BusinessProfile') ? 1 : 0;
		$integration['oauth_available'] = class_exists('\oauth\OAuth') && \oauth\OAuth::provider('google',false) ? 1 : 0;
		$integration['ready'] = $integration['connected'] == 1 && trim((string) ($integration['target']['account_name'] ?? '')) != '' && trim((string) ($integration['target']['location_name'] ?? '')) != '' ? 1 : 0;
		return $integration;
	}

	public function accounts($integration = []) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$result = ['result'=>false,'items'=>[],'error'=>''];
		if (!class_exists('\google\BusinessProfile')) {
			$result['error'] = 'google_unavailable';
			return $result;
		}
		$google = new \google\BusinessProfile($integration['account_ref']);
		$accounts = $google->accounts();
		if (!is_array($accounts)) {
			$result['error'] = $this->lastError($google);
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

	public function locations($integration = [], $accountName = '') {
		$integration = $this->reviews->normalizeIntegration($integration);
		$accountName = trim((string) $accountName) !== '' ? trim((string) $accountName) : trim((string) ($integration['target']['account_name'] ?? ''));
		$result = ['result'=>false,'items'=>[],'error'=>''];
		if (!class_exists('\google\BusinessProfile') || $accountName == '') {
			$result['error'] = $accountName == '' ? 'google_account_missing' : 'google_unavailable';
			return $result;
		}
		$google = new \google\BusinessProfile($integration['account_ref']);
		$locations = $google->locations($accountName);
		if (!is_array($locations)) {
			$result['error'] = $this->lastError($google);
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

	public function locationChoices($integration = []) {
		$choices = [];
		$accounts = $this->accounts($integration);
		if (!$accounts['result']) return ['result'=>false,'items'=>[],'error'=>$accounts['error']];
		foreach ($accounts['items'] as $account) {
			$locations = $this->locations($integration,$account['value']);
			if (!$locations['result']) return ['result'=>false,'items'=>[],'error'=>$locations['error']];
			foreach ($locations['items'] as $location) $choices[] = [
				'name'=>$account['name'].' · '.$location['name'],
				'value'=>json_encode(['account_name'=>$account['value'],'location_name'=>$location['value'],'location_title'=>$location['name']],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			];
		}
		return ['result'=>true,'items'=>$choices,'error'=>''];
	}

	public function oauthError($error) {
		$error = strtolower(trim((string) $error));
		if ($error == '') return false;
		foreach (['oauth_unavailable','refresh_token_missing','access_token_missing','account_unavailable','provider_unavailable','provider_or_client_unavailable','invalid_grant','invalid_client','unauthorized_client','access_denied'] as $needle) {
			if ($error == $needle || strpos($error,$needle.':') === 0) return true;
		}
		foreach (['bridge_refresh','refresh_http','invalid authentication credentials'] as $needle) {
			if (strpos($error,$needle) !== false) return true;
		}
		return false;
	}

	public function sync($integration = [], $force = false) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$result = ['result'=>false,'skipped'=>0,'count'=>0,'imported'=>0,'updated'=>0,'removed'=>0,'error'=>''];
		if ($integration['active'] != 1) {
			$result['skipped'] = 1;
			$result['result'] = true;
			return $result;
		}
		$refresh = $this->reviews->providerRefreshRequired('google');
		if ($refresh) {
			$this->reviews->deleteTimer('reviews_sync_'.$integration['id']);
			$force = true;
		}
		if (!$force && function_exists('helper__system_runtime') && !helper__system_runtime('reviews_sync_'.$integration['id'],24,false,'hours')) {
			$result['skipped'] = 1;
			$result['result'] = true;
			return $result;
		}
		$integration = $this->resolveTarget($integration);
		if (trim((string) ($integration['target']['account_name'] ?? '')) == '' || trim((string) ($integration['target']['location_name'] ?? '')) == '') {
			$result['skipped'] = 1;
			$result['error'] = 'google_location_missing';
			return $result;
		}
		if (!class_exists('\google\BusinessProfile')) {
			$result['error'] = 'google_unavailable';
			return $this->reviews->finishIntegrationSync($integration,$result);
		}

		$google = new \google\BusinessProfile($integration['account_ref']);
		if ($refresh) $result['removed'] = $this->reviews->removeImportedProviderReviews('google',$this->reviews->providerRefreshCutoff());
		$states = [];
		foreach ($this->reviews->syncLanguages() as $language) {
			$pageToken = '';
			$page = 0;
			do {
				$response = $google->reviews($integration['target']['account_name'],$integration['target']['location_name'],50,$pageToken,'updateTime desc',$this->reviews->acceptLanguage($language));
				if (!is_array($response)) {
					$result['error'] = $this->lastError($google);
					return $this->reviews->finishIntegrationSync($integration,$result);
				}
				foreach ($response['reviews'] ?? [] as $review) {
					$externalId = $this->reviewExternalId($review);
					$state = $this->importReview($review,$integration,$language);
					if (isset($states[$externalId])) continue;
					$states[$externalId] = $state;
					$result['count']++;
					if ($state == 'imported') $result['imported']++;
					if ($state == 'updated') $result['updated']++;
				}
				$pageToken = trim((string) ($response['nextPageToken'] ?? ''));
				$page++;
			} while ($pageToken != '' && $page < 10);
		}

		$result['result'] = true;
		$this->reviews->markProviderRefresh('google');
		$this->reviews->touchData();
		$this->reviews->write();
		return $this->reviews->finishIntegrationSync($integration,$result);
	}

	public function resolveTarget($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		if (trim((string) ($integration['target']['account_name'] ?? '')) != '' && trim((string) ($integration['target']['location_name'] ?? '')) != '') return $integration;
		$choices = $this->locationChoices($integration);
		if (!$choices['result'] || count($choices['items']) != 1) return $integration;
		$location = $this->reviews->decode($choices['items'][0]['value']);
		if (!is_array($location)) return $integration;
		$integration['target'] = [
			'account_name'=>trim((string) ($location['account_name'] ?? '')),
			'location_name'=>trim((string) ($location['location_name'] ?? '')),
			'location_title'=>trim((string) ($location['location_title'] ?? ''))
		];
		if ($integration['last_error'] == 'google_location_missing') $integration['last_error'] = '';
		$this->reviews->storeIntegration($integration);
		return $integration;
	}

	public static function commentForLanguage($text, $language, $defaultLanguage = '') {
		$language = strtolower(trim((string) $language));
		$defaultLanguage = strtolower(trim((string) $defaultLanguage));
		$text = trim(str_replace(["\r\n","\r"],"\n",(string) $text));
		if ($text == '') return '';
		if (preg_match('/^(.*?)\n*\s*\((?:Original|Originaltext|Originale|Texto original)\)\s*\n*(.*)$/isu',$text,$match)) return trim($match[1]) != '' ? trim($match[1]) : trim($match[2]);
		if (preg_match('/^(.*?)\n*\s*\((?:Translated by Google|Übersetzt von Google|Traducido por Google|Tradotto da Google)\)\s*\n*(.*)$/isu',$text,$match)) {
			if ($language != '' && $language != $defaultLanguage && trim($match[2]) != '') return trim($match[2]);
			return trim($match[1]);
		}
		return $text;
	}

	public function displayText($text, $language) {
		return self::commentForLanguage($text,$language,$this->reviews->defaultLanguage());
	}

	private function importReview($review, $integration, $language) {
		return $this->reviews->importProviderReview('google',$this->normalizeReview($review,$integration,$language),$this->findDuplicate($review));
	}

	private function normalizeReview($review, $integration, $language) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$language = $this->reviews->normalizeSyncLanguage($language);
		return [
			'external_id'=>$this->reviewExternalId($review),
			'author'=>trim((string) ($review['reviewer']['displayName'] ?? '')),
			'source'=>$integration['target']['location_title'] != '' ? $integration['target']['location_title'] : $integration['label'],
			'rating'=>$this->rating($review['starRating'] ?? 5),
			'text'=>[$language=>self::commentForLanguage($review['comment'] ?? '',$language,$this->reviews->defaultLanguage())],
			'languages'=>[$language],
			'date'=>$this->time($review['createTime'] ?? ''),
			'external_updated'=>$this->time($review['updateTime'] ?? '')
		];
	}

	private function findDuplicate($review) {
		$externalId = $this->reviewExternalId($review);
		$id = $this->reviews->findProviderReview('google',$externalId);
		if ($id != '') return $id;
		$author = trim((string) ($review['reviewer']['displayName'] ?? ''));
		$text = self::commentForLanguage($review['comment'] ?? '',$this->reviews->defaultLanguage(),$this->reviews->defaultLanguage());
		$rating = $this->rating($review['starRating'] ?? 5);
		$date = $this->time($review['createTime'] ?? '');
		foreach ($this->reviews->all() as $id => $entry) {
			$row = $this->reviews->display($id,$this->reviews->defaultLanguage());
			if ($row['provider'] != 'google') continue;
			if ($row['external_id'] != '') continue;
			if (intval($row['rating']) != $rating || intval($row['date']) != $date) continue;
			if (trim((string) $row['author']) != $author || trim((string) $row['text']) != $text) continue;
			return $id;
		}
		return '';
	}

	private function reviewExternalId($review) {
		return trim((string) ($review['reviewId'] ?? ($review['name'] ?? '')));
	}

	private function rating($value) {
		$ratings = ['ONE'=>1,'TWO'=>2,'THREE'=>3,'FOUR'=>4,'FIVE'=>5];
		if (is_numeric($value)) return max(1,min(5,intval($value)));
		return $ratings[strtoupper(trim((string) $value))] ?? 5;
	}

	private function time($value) {
		$time = strtotime(trim((string) $value));
		return $time === false ? intval($_SERVER['now'] ?? time()) : intval($time);
	}

	private function lastError($google) {
		$last = method_exists($google,'last') ? $google->last() : [];
		if (isset($last['body']['error']['message'])) return trim((string) $last['body']['error']['message']);
		if (isset($last['error']) && trim((string) $last['error']) !== '') return trim((string) $last['error']);
		return 'google_request_failed';
	}
}
