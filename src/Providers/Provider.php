<?php

abstract class FiCMSReviewsProvider {
	protected $reviews;

	public function __construct(FiCMSReviews $reviews) {
		$this->reviews = $reviews;
	}

	public static function key() {
		return '';
	}

	public static function definition() {
		$key = static::key();
		return ['name'=>$key != '' ? ucfirst($key) : 'Provider','oauth'=>0,'sync'=>0];
	}

	public function requirements($integration = []) {
		return ['oauth'=>0,'sync'=>0,'config_error'=>'','connect'=>0,'location_choices'=>0,'form_fields'=>[],'form_values'=>[]];
	}

	public function normalizeIntegration($integration) {
		return $integration;
	}

	public function saveIntegration($integration, $post, $existing) {
		return $integration;
	}

	public function connect($integration) {
		return ['result'=>false];
	}

	public function status($integration) {
		$integration['connected'] = 0;
		$integration['provider_available'] = 1;
		$integration['oauth_available'] = 1;
		$integration['ready'] = 0;
		return $integration;
	}

	public function sync($integration = [], $force = false) {
		return ['result'=>false,'skipped'=>1,'count'=>0,'imported'=>0,'updated'=>0,'error'=>'provider_unavailable'];
	}

	public function displayText($text, $language) {
		return $text;
	}

	protected function reviewUrl($review, $fields = []) {
		if (!is_array($review)) return '';
		foreach (array_merge($fields,['reviewUrl','review_url','webUrl','web_url','url','link']) as $field) {
			if (!isset($review[$field]) || is_array($review[$field]) || is_object($review[$field])) continue;
			if (filter_var(trim((string) $review[$field]),FILTER_VALIDATE_URL)) return trim((string) $review[$field]);
		}
		return '';
	}

	protected function providerText($text) {
		$text = trim((string) $text);
		if ($text == '' || $this->mojibakeScore($text) == 0) return $text;
		$fixed = iconv('UTF-8','Windows-1252//IGNORE',$text);
		return is_string($fixed) && trim($fixed) != '' && $this->mojibakeScore($fixed) < $this->mojibakeScore($text) ? trim($fixed) : $text;
	}

	private function mojibakeScore($text) {
		preg_match_all('/(?:Ã[\x{0080}-\x{00BF}]|Â[\x{0080}-\x{00BF}]?|â[\x{0080}-\x{00BF}]{1,2})/u',(string) $text,$matches);
		return count($matches[0]);
	}
}
