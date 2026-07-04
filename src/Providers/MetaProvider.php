<?php

class FiCMSReviewsMetaProvider extends FiCMSReviewsProvider {
	public static function key() {
		return 'meta';
	}

	public static function definition() {
		return ['name'=>'Meta','oauth'=>1,'sync'=>1];
	}

	public function requirements($integration = []) {
		$integration = $this->reviews->normalizeIntegration($integration);
		return array_merge([
			'oauth'=>1,
			'sync'=>1,
			'connect'=>1,
			'location_choices'=>1,
			'config_error'=>'meta_page_missing',
			'oauth_provider'=>'Meta',
			'target_label'=>'_reviews_meta_page',
			'location_select'=>['option'=>'integration_meta_page','label'=>'_reviews_meta_page','empty'=>'_reviews_meta_page_select'],
			'form_fields'=>[],
			'form_values'=>[]
		],$this->oauthAccountRequirements('meta'));
	}

	public function saveIntegration($integration, $post, $existing) {
		if (!isset($post['integration_meta_page']) || !is_string($post['integration_meta_page'])) return $integration;
		$page = $this->reviews->decode($post['integration_meta_page']);
		if (is_array($page)) $integration['target'] = [
			'page_id'=>trim((string) ($page['page_id'] ?? '')),
			'location_name'=>trim((string) ($page['page_name'] ?? ($page['location_name'] ?? ''))),
			'location_title'=>trim((string) ($page['page_name'] ?? ($page['location_title'] ?? '')))
		];
		if (is_array($page) && $integration['last_error'] == 'meta_page_missing') $integration['last_error'] = '';
		return $integration;
	}

	public function connect($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		if ($integration['last_error'] != '') {
			$integration['last_error'] = '';
			$this->reviews->storeIntegration($integration);
		}
		return ['result'=>false,'error'=>'oauth_managed_by_integrations'];
	}

	public function status($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		if ($integration['id'] != 'new' && $this->oauthConnected('meta',$integration['account_ref']) && trim((string) ($integration['target']['page_id'] ?? '')) == '') $integration = $this->resolveTarget($integration);
		$integration['connected'] = $this->oauthConnected('meta',$integration['account_ref']) ? 1 : 0;
		$integration['provider_available'] = class_exists('\meta\Meta') ? 1 : 0;
		$integration['oauth_available'] = class_exists('\oauth\OAuth') && \oauth\OAuth::provider('meta',false) ? 1 : 0;
		$integration['ready'] = $integration['connected'] == 1 && trim((string) ($integration['target']['page_id'] ?? '')) != '' ? 1 : 0;
		return $integration;
	}

	public function locationChoices($integration = []) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$result = ['result'=>false,'items'=>[],'error'=>''];
		if (!class_exists('\meta\Meta')) {
			$result['error'] = 'meta_unavailable';
			return $result;
		}
		$meta = new \meta\Meta($integration['account_ref']);
		$after = '';
		do {
			$response = $meta->pages('id,name,access_token,tasks,category',100,$after);
			if (!is_array($response)) {
				$result['error'] = $this->lastError($meta);
				return $result;
			}
			foreach ($response['data'] ?? [] as $page) {
				$pageId = trim((string) ($page['id'] ?? ''));
				$pageName = trim((string) ($page['name'] ?? $pageId));
				if ($pageId == '') continue;
				$result['items'][] = [
					'name'=>$pageName,
					'value'=>json_encode(['page_id'=>$pageId,'page_name'=>$pageName,'location_name'=>$pageName,'location_title'=>$pageName],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				];
			}
			$after = trim((string) ($response['paging']['cursors']['after'] ?? ''));
		} while ($after != '');
		$result['result'] = true;
		return $result;
	}

	public function oauthError($error) {
		$error = strtolower(trim((string) $error));
		if ($error == '') return false;
		foreach (['oauth_unavailable','access_token_missing','account_unavailable','provider_unavailable','provider_or_client_unavailable','invalid_grant','invalid_client','unauthorized_client','access_denied'] as $needle) {
			if ($error == $needle || strpos($error,$needle.':') === 0) return true;
		}
		foreach (['bridge_refresh','refresh_http','invalid oauth','invalid token','session has expired'] as $needle) {
			if (strpos($error,$needle) !== false) return true;
		}
		return false;
	}

	public function sync($integration = [], $force = false) {
		$integration = $this->reviews->normalizeIntegration($integration);
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
		$integration = $this->resolveTarget($integration);
		if (trim((string) ($integration['target']['page_id'] ?? '')) == '') {
			$result['skipped'] = 1;
			$result['error'] = 'meta_page_missing';
			return $result;
		}
		if (!class_exists('\meta\Meta')) {
			$result['error'] = 'meta_unavailable';
			return $this->reviews->finishIntegrationSync($integration,$result);
		}

		$meta = new \meta\Meta($integration['account_ref']);
		$pageAccessToken = $meta->pageAccessToken($integration['target']['page_id']);
		if ($pageAccessToken == '') {
			$result['error'] = $this->lastError($meta);
			return $this->reviews->finishIntegrationSync($integration,$result);
		}
		$after = '';
		$page = 0;
		do {
			$response = $meta->ratings($integration['target']['page_id'],$pageAccessToken,'created_time,recommendation_type,review_text,reviewer{id,name},from{id,name},open_graph_story{id,message,from{id,name}}',100,$after);
			if (!is_array($response)) {
				$result['error'] = $this->lastError($meta);
				return $this->reviews->finishIntegrationSync($integration,$result);
			}
			foreach ($response['data'] ?? [] as $review) {
				$state = $this->importReview($review,$integration);
				if ($this->reviewAuthor($review) == '' && count($result['FICMS_REVIEWS_META_AUTHOR_MISSING'] ?? []) < 3) $result['FICMS_REVIEWS_META_AUTHOR_MISSING'][] = [
					'keys'=>array_keys($review),
					'reviewer_keys'=>is_array($review['reviewer'] ?? null) ? array_keys($review['reviewer']) : [],
					'from_keys'=>is_array($review['from'] ?? null) ? array_keys($review['from']) : [],
					'story_keys'=>is_array($review['open_graph_story'] ?? null) ? array_keys($review['open_graph_story']) : [],
					'story_from_keys'=>is_array($review['open_graph_story']['from'] ?? null) ? array_keys($review['open_graph_story']['from']) : []
				];
				$result['count']++;
				if ($state == 'imported') $result['imported']++;
				if ($state == 'updated') $result['updated']++;
			}
			$after = trim((string) ($response['paging']['cursors']['after'] ?? ''));
			$page++;
		} while ($after != '' && $page < 10);

		$result['result'] = true;
		$this->reviews->touchData();
		$this->reviews->write();
		return $this->reviews->finishIntegrationSync($integration,$result);
	}

	public function resolveTarget($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		if (trim((string) ($integration['target']['page_id'] ?? '')) != '') return $integration;
		$choices = $this->locationChoices($integration);
		if (!$choices['result'] || count($choices['items']) != 1) return $integration;
		$page = $this->reviews->decode($choices['items'][0]['value']);
		if (!is_array($page)) return $integration;
		$integration['target'] = [
			'page_id'=>trim((string) ($page['page_id'] ?? '')),
			'location_name'=>trim((string) ($page['page_name'] ?? '')),
			'location_title'=>trim((string) ($page['page_name'] ?? ''))
		];
		if ($integration['last_error'] == 'meta_page_missing') $integration['last_error'] = '';
		$this->reviews->storeIntegration($integration);
		return $integration;
	}

	private function importReview($review, $integration) {
		return $this->reviews->importProviderReview('meta',$this->normalizeReview($review,$integration),$this->findDuplicate($review));
	}

	private function normalizeReview($review, $integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$language = $this->reviews->defaultLanguage();
		return [
			'external_id'=>$this->reviewExternalId($review),
			'external_url'=>$this->reviewExternalUrl($review),
			'author'=>$this->reviewAuthor($review),
			'source'=>$integration['target']['location_title'] != '' ? $integration['target']['location_title'] : $integration['label'],
			'rating'=>$this->rating($review['recommendation_type'] ?? ''),
			'text'=>[$language=>$this->reviewText($review)],
			'languages'=>[$language],
			'date'=>$this->time($review['created_time'] ?? ''),
			'external_updated'=>$this->time($review['created_time'] ?? '')
		];
	}

	private function findDuplicate($review) {
		$externalId = $this->reviewExternalId($review);
		$id = $this->reviews->findProviderReview('meta',$externalId);
		if ($id != '') return $id;
		$author = $this->reviewAuthor($review);
		$text = $this->reviewText($review);
		$rating = $this->rating($review['recommendation_type'] ?? '');
		$date = $this->time($review['created_time'] ?? '');
		foreach ($this->reviews->all() as $id => $entry) {
			$row = $this->reviews->display($id,$this->reviews->defaultLanguage());
			if ($row['provider'] != 'meta') continue;
			if ($row['external_id'] != '') continue;
			if (intval($row['rating']) != $rating || intval($row['date']) != $date) continue;
			if (trim((string) $row['author']) != $author || trim((string) $row['text']) != $text) continue;
			return $id;
		}
		return '';
	}

	private function reviewExternalId($review) {
		$id = trim((string) ($review['id'] ?? ($review['open_graph_story']['id'] ?? '')));
		if ($id != '') return $id;
		return substr(hash('sha256',json_encode([$review['created_time'] ?? '',$review['recommendation_type'] ?? '',$review['reviewer']['id'] ?? '',$review['review_text'] ?? ''],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),0,32);
	}

	private function reviewAuthor($review) {
		foreach ([
			$review['reviewer']['name'] ?? '',
			$review['reviewer']['displayName'] ?? '',
			$review['reviewer']['display_name'] ?? '',
			$review['from']['name'] ?? '',
			$review['open_graph_story']['from']['name'] ?? ''
		] as $author) {
			$author = $this->providerText($author);
			if ($author != '') return $author;
		}
		return '';
	}

	private function reviewText($review) {
		foreach ([
			$review['review_text'] ?? '',
			$review['text'] ?? '',
			$review['message'] ?? '',
			$review['open_graph_story']['message'] ?? '',
			$review['open_graph_story']['description'] ?? ''
		] as $text) {
			$text = $this->providerText($text);
			if ($text != '') return $text;
		}
		return '';
	}

	private function reviewExternalUrl($review) {
		if (!is_array($review)) return '';
		foreach ([
			$review['permalink_url'] ?? '',
			$review['open_graph_story']['permalink_url'] ?? '',
			$review['open_graph_story']['link'] ?? '',
			$review['link'] ?? ''
		] as $url) {
			$url = trim((string) $url);
			if (filter_var($url,FILTER_VALIDATE_URL)) return $url;
		}
		return $this->reviewUrl($review,['permalink_url']);
	}

	private function rating($value) {
		$value = strtoupper(trim((string) $value));
		if ($value == 'NEGATIVE') return 1;
		if ($value == 'POSITIVE') return 5;
		return 3;
	}

	private function time($value) {
		$time = strtotime(trim((string) $value));
		return $time === false ? intval($_SERVER['now'] ?? time()) : intval($time);
	}

	private function lastError($meta) {
		$last = method_exists($meta,'last') ? $meta->last() : [];
		if (isset($last['body']['error']['message'])) return trim((string) $last['body']['error']['message']);
		if (isset($last['error']) && trim((string) $last['error']) !== '') return trim((string) $last['error']);
		return 'meta_request_failed';
	}
}
