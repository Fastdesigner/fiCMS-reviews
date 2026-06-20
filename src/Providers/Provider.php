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
}
