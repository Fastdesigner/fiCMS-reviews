<?php

class FiCMSReviewsTripadvisorProvider extends FiCMSReviewsProvider {
	const BASE_URL = 'https://terra.tripadvisor.com/api';
	const SUPPORTED_LANGUAGES = ['ar','cs','da','de','el','en','es','fi','fr','hu','id','it','iw','ja','ko','nl','no','pl','pt','ru','sk','sr','sv','th','tr','vi','zh-CN','zh-TW'];

	public static function key() {
		return 'tripadvisor';
	}

	public static function definition() {
		return ['name'=>'Tripadvisor','oauth'=>0,'sync'=>1];
	}

	public function requirements($integration = []) {
		$integration = $this->reviews->normalizeIntegration($integration);
		return [
			'oauth'=>0,
			'sync'=>1,
			'connect'=>0,
			'location_choices'=>0,
			'config_error'=>'tripadvisor_config_missing',
			'target_label'=>'_reviews_tripadvisor_location_id',
			'secret_fields'=>['api_key'=>'_reviews_tripadvisor_api_key'],
			'form_fields'=>[
				'tripadvisor_api_key'=>['attributes'=>['autocomplete'=>'off']],
				'tripadvisor_location_id'=>['required'=>true]
			],
			'form_values'=>[
				'tripadvisor_api_key'=>'',
				'tripadvisor_location_id'=>$integration['target']['location_name'] ?? ''
			]
		];
	}

	public function saveIntegration($integration, $post, $existing) {
		$apiKey = trim((string) ($post['integration_tripadvisor_api_key'] ?? ''));
		$locationId = preg_replace('/[^0-9]+/','',trim((string) ($post['integration_tripadvisor_location_id'] ?? ($existing['target']['location_name'] ?? ''))));
		$integration['config']['api_key'] = $apiKey != '' ? $apiKey : trim((string) ($existing['config']['api_key'] ?? ''));
		$integration['target'] = [
			'account_name'=>'',
			'location_name'=>$locationId,
			'location_title'=>trim((string) ($integration['label'] ?? ''))
		];
		if ($integration['last_error'] == 'tripadvisor_config_missing') $integration['last_error'] = '';
		return $integration;
	}

	public function status($integration) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$integration['connected'] = trim((string) ($integration['config']['api_key'] ?? '')) != '' ? 1 : 0;
		$integration['provider_available'] = function_exists('curl__request') || function_exists('curl_init') ? 1 : 0;
		$integration['oauth_available'] = 1;
		$integration['ready'] = $integration['connected'] == 1 && trim((string) ($integration['target']['location_name'] ?? '')) != '' ? 1 : 0;
		return $integration;
	}

	public function sync($integration = [], $force = false) {
		$integration = $this->reviews->normalizeIntegration($integration);
		$result = ['result'=>false,'skipped'=>0,'count'=>0,'imported'=>0,'updated'=>0,'removed'=>0,'error'=>''];
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
		if (trim((string) ($integration['config']['api_key'] ?? '')) == '' || trim((string) ($integration['target']['location_name'] ?? '')) == '') {
			$result['skipped'] = 1;
			$result['error'] = 'tripadvisor_config_missing';
			return $result;
		}

		$states = [];
		foreach ($this->languages() as $language) {
			$response = $this->request($integration,$language);
			if (!is_array($response)) {
				$result['error'] = 'tripadvisor_request_failed';
				return $this->reviews->finishIntegrationSync($integration,$result);
			}
			if (isset($response['error']) && trim((string) $response['error']) !== '') {
				$result['error'] = trim((string) $response['error']);
				return $this->reviews->finishIntegrationSync($integration,$result);
			}
			foreach ($response['data'] ?? [] as $review) {
				$externalId = $this->reviewExternalId($review);
				if ($externalId == '') continue;
				$state = $this->importReview($review,$integration,$language);
				if ($state == '') continue;
				if (isset($states[$externalId])) continue;
				$states[$externalId] = $state;
				$result['count']++;
				if ($state == 'imported') $result['imported']++;
				if ($state == 'updated') $result['updated']++;
			}
		}

		$result['result'] = true;
		$this->reviews->touchData();
		$this->reviews->write();
		return $this->reviews->finishIntegrationSync($integration,$result);
	}

	private function request($integration, $language) {
		$query = http_build_query([
			'language'=>$language,
			'sort_by'=>'MOST_RECENT',
			'page'=>1,
			'size'=>3
		]);
		$url = self::BASE_URL.'/locations/'.rawurlencode($integration['target']['location_name']).'/reviews?'.$query;
		$headers = [
			'Accept: application/json',
			'X-API-KEY: '.trim((string) ($integration['config']['api_key'] ?? ''))
		];
		if (function_exists('curl__request')) $response = curl__request($url,$headers,[],'','',null,'GET',30);
		else $response = $this->curl($url,$headers);
		if (!is_array($response)) return ['error'=>'tripadvisor_curl_unavailable'];
		if (intval($response['code'] ?? 0) < 200 || intval($response['code'] ?? 0) >= 300) return ['error'=>$this->error($response)];
		$body = json_decode((string) ($response['body'] ?? ''),true);
		return is_array($body) ? $body : ['error'=>'tripadvisor_invalid_response'];
	}

	private function curl($url, $headers) {
		if (!function_exists('curl_init')) return false;
		$curl = curl_init($url);
		curl_setopt_array($curl,[
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_HTTPHEADER=>$headers,
			CURLOPT_TIMEOUT=>30,
			CURLOPT_CUSTOMREQUEST=>'GET'
		]);
		$body = curl_exec($curl);
		$result = ['body'=>$body !== false ? $body : '', 'code'=>curl_getinfo($curl,CURLINFO_HTTP_CODE), 'error'=>curl_error($curl)];
		curl_close($curl);
		return $result;
	}

	private function importReview($review, $integration, $language) {
		$review = $this->normalizeReview($review,$integration,$language);
		foreach ($review['text'] as $text) if (trim((string) $text) != '') return $this->reviews->importProviderReview('tripadvisor',$review,$this->reviews->findProviderReview('tripadvisor',$review['external_id']));
		return '';
	}

	private function normalizeReview($review, $integration, $language) {
		$language = $this->normalizeLanguage($language);
		$title = $this->translation($review['title'] ?? [],$language);
		$text = $this->translation($review['text'] ?? [],$language);
		if ($title != '' && $text != '' && stripos($text,$title) !== 0) $text = $title."\n\n".$text;
		if ($text == '') $text = $title;
		return [
			'external_id'=>$this->reviewExternalId($review),
			'author'=>trim((string) ($review['user']['username'] ?? '')),
			'source'=>trim((string) ($integration['target']['location_title'] ?? '')) != '' ? trim((string) $integration['target']['location_title']) : $integration['label'],
			'rating'=>$this->rating($review['rating'] ?? 5),
			'text'=>[$language=>$text],
			'languages'=>[$language],
			'date'=>$this->time($review['publish_ts'] ?? ''),
			'external_updated'=>$this->time($review['publish_ts'] ?? '')
		];
	}

	private function translation($translations, $language) {
		if (!is_array($translations)) return trim((string) $translations);
		foreach ($translations as $translation) {
			if (!is_array($translation) || $this->normalizeLanguage($translation['language'] ?? '') != $language) continue;
			return trim((string) ($translation['value'] ?? ''));
		}
		foreach ($translations as $translation) {
			if (!is_array($translation) || empty($translation['primary'])) continue;
			return trim((string) ($translation['value'] ?? ''));
		}
		foreach ($translations as $translation) {
			if (!is_array($translation) || trim((string) ($translation['value'] ?? '')) == '') continue;
			return trim((string) $translation['value']);
		}
		return '';
	}

	private function languages() {
		$languages = [];
		foreach ($this->reviews->syncLanguages() as $language) {
			$language = $this->normalizeLanguage($language);
			if (in_array($language,self::SUPPORTED_LANGUAGES,true)) $languages[] = $language;
		}
		return array_values(array_unique($languages)) ?: ['en'];
	}

	private function normalizeLanguage($language) {
		$language = trim((string) $language);
		foreach (['zh-cn'=>'zh-CN','zh_cn'=>'zh-CN','zh-tw'=>'zh-TW','zh_tw'=>'zh-TW','he'=>'iw'] as $from => $to) if (strtolower($language) == $from) return $to;
		$language = strtolower($language);
		if (strpos($language,'-') !== false) $language = substr($language,0,strpos($language,'-'));
		if (strpos($language,'_') !== false) $language = substr($language,0,strpos($language,'_'));
		return $language;
	}

	private function reviewExternalId($review) {
		return trim((string) ($review['id'] ?? ''));
	}

	private function rating($value) {
		return max(1,min(5,intval($value)));
	}

	private function time($value) {
		$time = strtotime(trim((string) $value));
		return $time === false ? intval($_SERVER['now'] ?? time()) : intval($time);
	}

	private function error($response) {
		$body = json_decode((string) ($response['body'] ?? ''),true);
		foreach (['detail','message','title'] as $field) if (is_array($body) && trim((string) ($body[$field] ?? '')) != '') return trim((string) $body[$field]);
		if (trim((string) ($response['error'] ?? '')) != '') return trim((string) $response['error']);
		return 'tripadvisor_http_'.intval($response['code'] ?? 0);
	}
}
