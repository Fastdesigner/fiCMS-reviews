<?php

class FiCMSReviewsRows {
	private $reviews;
	private $normalizer;
	private $installedLanguages = [];

	public function __construct($reviews, $normalizer, $installedLanguages) {
		$this->reviews = $reviews;
		$this->normalizer = $normalizer;
		$this->installedLanguages = is_array($installedLanguages) ? array_values($installedLanguages) : [];
	}

	public function row($id, $entry, $language) {
		$entry = $this->normalizer->entry($id,$entry);
		$entry['author'] = trim((string) $entry['author']);
		$entry['author_initials'] = $this->initials($entry['author']);
		$entry['source'] = trim((string) $entry['source']);
		$entry['text'] = $this->normalizer->resolveText($entry['text'],$language);
		$entry['text'] = $this->reviews->providerDisplayText($entry['provider'],$entry['text'],$language);
		$entry['has_text'] = trim((string) $entry['text']) !== '' ? 1 : 0;
		$entry['sort_id'] = $id;
		$entry['search'] = trim($id.' '.$entry['author'].' '.$entry['source'].' '.$entry['text'].' '.$entry['provider']);
		return $entry;
	}

	public function admin($reviews, $filter, $language) {
		$filter = $this->normalizeFilter($filter);
		$rows = [];
		foreach ($reviews as $id => $entry) {
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

	public function widget($reviews, $filter, $language) {
		$filter = $this->normalizeWidgetFilter($filter);
		$rows = [];
		foreach ($reviews as $id => $entry) {
			$row = $this->row($id,$entry,$language);
			if (empty($row['published'])) continue;
			if (!$this->matchesWidgetProvider($row['provider'],$filter)) continue;
			if (intval($row['rating']) < intval($filter['min_rating'])) continue;
			if (intval($filter['featured']) == 1 && empty($row['featured'])) continue;
			if (!$this->matchesWidgetLanguage($row['lid'],$filter,$language)) continue;
			if (empty($row['has_text'])) continue;
			$rows[] = $row;
		}
		$rows = $this->sortRows($rows,$filter['sort'],$filter['direction']);
		if (intval($filter['limit']) > 0) $rows = array_slice($rows,0,intval($filter['limit']));
		return $rows;
	}

	public function summaryRows($reviews, $filter, $language) {
		$filter = $this->normalizeWidgetFilter($filter);
		$rows = [];
		foreach ($reviews as $id => $entry) {
			$row = $this->row($id,$entry,$language);
			if (empty($row['published'])) continue;
			if (!$this->matchesWidgetProvider($row['provider'],$filter)) continue;
			if (!$this->matchesWidgetLanguage($row['lid'],$filter,$language)) continue;
			$rows[] = $row;
		}
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
		return $this->normalizeSelection($value,array_merge(['current'],$this->installedLanguages));
	}

	private function normalizeWidgetProviders($value) {
		return $this->normalizeSelection($value,array_keys($this->reviews->providers()));
	}

	private function normalizeSelection($value, $allowed) {
		$value = $this->normalizer->decode($value);
		if (!is_array($value)) $value = [trim((string) $value)];
		$value = array_values(array_filter(array_map('strval',$value)));
		if (empty($value) || in_array('all',$value,true)) return ['all'];
		return array_values(array_intersect($value,$allowed)) ?: ['all'];
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

	private function matchesWidgetLanguage($languages, $filter, $currentLanguage) {
		if (in_array('all',$filter['language'],true)) return true;
		if (in_array('current',$filter['language'],true)) return $this->matchesLanguage($languages,$currentLanguage);
		$languages = $this->normalizer->languages($languages,false);
		if (in_array('all',$languages,true)) return true;
		return count(array_intersect($filter['language'],$languages)) > 0;
	}

	private function matchesLanguage($languages, $language) {
		$languages = $this->normalizer->languages($languages,false);
		return in_array('all',$languages,true) || in_array($language,$languages,true);
	}

	private function matchesWidgetProvider($provider, $filter) {
		return in_array('all',$filter['provider'],true) || in_array($provider,$filter['provider'],true);
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

	private function formatNumber($value, $language) {
		if (class_exists('NumberFormatter')) {
			$formatter = new NumberFormatter(str_replace('_','-',(string) $language),NumberFormatter::DECIMAL);
			$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS,1);
			$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS,1);
			return $formatter->format($value);
		}
		return number_format($value,1,'.','');
	}
}
