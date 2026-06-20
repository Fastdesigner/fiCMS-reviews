<?php

class FiCMSReviewsNormalizer {
	private $defaultLanguage = 'de';
	private $installedLanguages = [];

	public function __construct($defaultLanguage, $installedLanguages) {
		$this->defaultLanguage = trim((string) $defaultLanguage) !== '' ? trim((string) $defaultLanguage) : 'de';
		$this->installedLanguages = is_array($installedLanguages) ? array_values($installedLanguages) : [$this->defaultLanguage];
		if (empty($this->installedLanguages)) $this->installedLanguages = [$this->defaultLanguage];
	}

	public static function validProviderKey($provider) {
		return preg_match('/^[a-z0-9_-]+$/',trim((string) $provider)) === 1;
	}

	public function entry($id, $entry) {
		if (!is_array($entry)) $entry = [];
		$entry['id'] = trim((string) ($entry['id'] ?? $id));
		$entry['lid'] = $this->languages($entry['lid'] ?? ['all'],false);
		foreach (['author','source'] as $field) $entry[$field] = $this->plainText($entry[$field] ?? '');
		$entry['text'] = $this->text($entry['text'] ?? []);
		$entry['rating'] = max(1,min(5,intval($entry['rating'] ?? 5)));
		$entry['date'] = intval($entry['date'] ?? 0);
		$entry['published'] = !empty($entry['published']) ? 1 : 0;
		$entry['featured'] = !empty($entry['featured']) ? 1 : 0;
		$entry['provider'] = trim((string) ($entry['provider'] ?? 'local'));
		if (!self::validProviderKey($entry['provider'])) $entry['provider'] = 'local';
		$entry['source_type'] = trim((string) ($entry['source_type'] ?? ($entry['provider'] == 'local' ? 'local' : 'provider')));
		$entry['external_id'] = trim((string) ($entry['external_id'] ?? ''));
		$entry['external_updated'] = intval($entry['external_updated'] ?? 0);
		$entry['imported'] = !empty($entry['imported']) ? 1 : 0;
		$entry['read_only'] = !empty($entry['read_only']) ? 1 : 0;
		if ($entry['imported'] == 1 && in_array('all',$entry['lid'],true) && count($this->installedLanguages) == 1) $entry['lid'] = [$this->installedLanguages[0]];
		return $entry;
	}

	public function text($value) {
		$value = $this->decodeText($value);
		if (!is_array($value)) return [$this->defaultLanguage=>trim((string) $value)];
		if (count($value) == 1 && array_keys($value) === [0]) return [$this->defaultLanguage=>trim((string) reset($value))];
		foreach ($value as $language => $text) $value[$language] = trim((string) $text);
		return $value;
	}

	public function plainText($value) {
		return $this->firstText($this->text($value),'');
	}

	public function resolveText($value, $language) {
		$value = $this->text($value);
		if (function_exists('language__from_array')) return trim((string) language__from_array($value,$language));
		return $this->firstText($value,$language);
	}

	public function textLanguages($text) {
		$languages = [];
		foreach ($this->text($text) as $language => $value) {
			if (trim((string) $value) == '') continue;
			if (in_array($language,$this->installedLanguages,true)) $languages[] = $language;
		}
		return empty($languages) ? ['all'] : array_values(array_unique($languages));
	}

	public function reviewLanguages($review, $text) {
		$languages = $this->languages($review['languages'] ?? ($review['lid'] ?? []),true);
		if (!in_array('all',$languages,true)) return $languages;
		$languages = $this->textLanguages($text);
		if (!in_array('all',$languages,true)) return $languages;
		return count($this->installedLanguages) == 1 ? [$this->installedLanguages[0]] : ['all'];
	}

	public function languages($value, $strict) {
		$value = $this->decode($value);
		if (!is_array($value)) $value = [trim((string) $value)];
		$value = array_values(array_filter(array_map('strval',$value)));
		if (empty($value) || in_array('all',$value,true)) return ['all'];
		if ($strict) $value = array_values(array_intersect($value,$this->installedLanguages));
		return empty($value) ? ['all'] : $value;
	}

	public function syncLanguage($language) {
		$language = strtolower(trim((string) $language));
		$language = preg_replace('/[^a-z0-9_-]+/','',$language);
		return $language != '' ? $language : $this->defaultLanguage;
	}

	public function decodeText($value) {
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

	private function firstText($texts, $language) {
		if ($language != '' && isset($texts[$language]) && trim((string) $texts[$language]) !== '') return trim((string) $texts[$language]);
		if (isset($texts[$this->defaultLanguage]) && trim((string) $texts[$this->defaultLanguage]) !== '') return trim((string) $texts[$this->defaultLanguage]);
		foreach ($texts as $text) if (trim((string) $text) !== '') return trim((string) $text);
		return '';
	}
}
