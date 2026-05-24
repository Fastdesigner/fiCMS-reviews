<?php

class FiCMSReviews {
	private $basePath = '';
	private $dataFile = '';
	private $defaultLanguage = 'de';
	private $installedLanguages = [];
	private $data = [];

	public function __construct($basePath = '', $defaultLanguage = '', $installedLanguages = []) {
		$this->basePath = rtrim((string) $basePath,'/');
		if ($this->basePath == '') $this->basePath = dirname(__DIR__);
		$this->dataFile = $this->basePath.'/data/reviews.json';
		$this->defaultLanguage = trim((string) $defaultLanguage) !== '' ? trim((string) $defaultLanguage) : (string) ($GLOBALS['site']['default_language'] ?? ($_SESSION['language'] ?? 'de'));
		$this->installedLanguages = is_array($installedLanguages) ? array_values($installedLanguages) : [];
		if (empty($this->installedLanguages) && isset($GLOBALS['site']['installed_languages']) && is_array($GLOBALS['site']['installed_languages'])) $this->installedLanguages = array_values($GLOBALS['site']['installed_languages']);
		if (empty($this->installedLanguages)) $this->installedLanguages = [$this->defaultLanguage];
		$this->data = $this->load();
	}

	public function dataFile() {
		return $this->dataFile;
	}

	public function all() {
		return $this->data['reviews'];
	}

	public function find($id) {
		$id = trim((string) $id);
		return $this->data['reviews'][$id] ?? $this->blank($id);
	}

	public function blank($id = 'new') {
		return ['id'=>$id,'author'=>[],'source'=>[],'rating'=>5,'text'=>[],'lid'=>['all'],'date'=>intval($_SERVER['now'] ?? time()),'published'=>($id == 'new' ? 1 : 0),'featured'=>0];
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
		foreach (['author','source','text'] as $field) $entry[$field] = $this->normalizePostedText($post[$field] ?? []);
		$entry['rating'] = max(1,min(5,intval($post['rating'] ?? 5)));
		$entry['date'] = intval($post['date'] ?? ($_SERVER['now'] ?? time()));
		$entry['published'] = !empty($post['published']) ? 1 : 0;
		$entry['featured'] = !empty($post['featured']) ? 1 : 0;
		$entry['updated'] = intval($_SERVER['now'] ?? time());
		$this->data['reviews'][$id] = $this->normalizeEntry($id,$entry);
		$this->data['updated'] = intval($_SERVER['now'] ?? time());
		return ['result'=>$this->write(),'id'=>$id];
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

	private function write() {
		if (function_exists('helper__files_write')) return helper__files_write($this->dataFile,$this->data,true,true);
		if (!is_dir(dirname($this->dataFile))) mkdir(dirname($this->dataFile),0775,true);
		return file_put_contents($this->dataFile,json_encode($this->data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
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
		return $entry;
	}

	private function row($id, $entry, $language) {
		$entry = $this->normalizeEntry($id,$entry);
		$entry['author'] = $this->resolveText($entry['author'],$language);
		$entry['source'] = $this->resolveText($entry['source'],$language);
		$entry['text'] = $this->resolveText($entry['text'],$language);
		$entry['sort_id'] = $id;
		$entry['search'] = trim($id.' '.$entry['author'].' '.$entry['source'].' '.$entry['text']);
		return $entry;
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
		$default = ['page'=>1,'count'=>20,'sort'=>'date','direction'=>'DESC','search'=>[],'attributes'=>['published'=>'','featured'=>'','rating'=>'','lid'=>'']];
		$filter = is_array($filter) ? array_replace_recursive($default,$filter) : $default;
		$filter['search'] = is_array($filter['search']) ? array_values(array_filter(array_map('trim',$filter['search']))) : [trim((string) $filter['search'])];
		$filter['page'] = max(1,intval($filter['page']));
		$filter['count'] = max(1,intval($filter['count']));
		$filter['direction'] = strtoupper((string) $filter['direction']) == 'ASC' ? 'ASC' : 'DESC';
		if (!in_array($filter['sort'],['date','rating','featured'],true)) $filter['sort'] = 'date';
		return $filter;
	}

	private function normalizeWidgetFilter($filter) {
		$default = ['limit'=>6,'min_rating'=>1,'featured'=>0,'language'=>['all'],'sort'=>'featured','direction'=>'DESC'];
		$filter = is_array($filter) ? array_merge($default,$filter) : $default;
		$filter['limit'] = max(0,intval($filter['limit']));
		$filter['min_rating'] = max(1,min(5,intval($filter['min_rating'])));
		$filter['featured'] = intval($filter['featured']) == 1 ? 1 : 0;
		$filter['language'] = $this->normalizeWidgetLanguages($filter['language']);
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

	private function matchesAdmin($row, $filter) {
		foreach ($filter['search'] as $search) if ($search !== '' && stripos($row['search'],$search) === false) return false;
		if ($filter['attributes']['published'] !== '' && intval($row['published']) != intval($filter['attributes']['published'])) return false;
		if ($filter['attributes']['featured'] !== '' && intval($row['featured']) != intval($filter['attributes']['featured'])) return false;
		if ($filter['attributes']['rating'] !== '' && intval($row['rating']) != intval($filter['attributes']['rating'])) return false;
		if ($filter['attributes']['lid'] !== '') {
			if ($filter['attributes']['lid'] == 'all' && !in_array('all',$row['lid'],true)) return false;
			if ($filter['attributes']['lid'] != 'all' && !in_array($filter['attributes']['lid'],$row['lid'],true)) return false;
		}
		return true;
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
