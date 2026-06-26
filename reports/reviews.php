<?php

if (!$site['onsite']) return;

if ($reports['mode'] == 'meta') {
	$reports['meta'] = ['active'=>1,'opt_label'=>'reviews_report','selfsend'=>1,'schedule'=>['every'=>'month','offset'=>3],'frequency'=>['default'=>'month','options'=>['month']]];
	return;
}
if ($reports['mode'] != 'send') return;

require_once dirname(__DIR__).'/src/Reviews.php';

$reviewsReport = [
	'instance'=>new FiCMSReviews(dirname(__DIR__),$site['default_language'],$site['installed_languages']),
	'published'=>[],
	'unpublished'=>0,
	'featured'=>0,
	'current'=>[],
	'previous'=>[],
	'ratings'=>[1=>0,2=>0,3=>0,4=>0,5=>0],
	'providers'=>[],
	'list'=>[],
	'format'=>function($value, $language) {
		if (class_exists('NumberFormatter')) {
			$formatter = new NumberFormatter(str_replace('_','-',(string) $language),NumberFormatter::DECIMAL);
			$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS,1);
			$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS,1);
			return $formatter->format($value);
		}
		return number_format($value,1,'.','');
	}
];

foreach ($reviewsReport['instance']->all() as $reviewsReport['id'] => $reviewsReport['entry']) {
	$reviewsReport['row'] = $reviewsReport['instance']->display($reviewsReport['id'],$site['default_language']);
	$reviewsReport['rating'] = max(1,min(5,intval($reviewsReport['row']['rating'] ?? 0)));
	$reviewsReport['date'] = intval($reviewsReport['row']['date'] ?? 0);
	if (empty($reviewsReport['row']['published'])) {
		$reviewsReport['unpublished']++;
		continue;
	}
	$reviewsReport['published'][] = $reviewsReport['row'];
	$reviewsReport['ratings'][$reviewsReport['rating']]++;
	if (!empty($reviewsReport['row']['featured'])) $reviewsReport['featured']++;
	if ($reviewsReport['date'] >= $reports['range']['start'] && $reviewsReport['date'] <= $reports['range']['end']) $reviewsReport['current'][] = $reviewsReport['row'];
	else if ($reviewsReport['date'] >= $reports['range']['prev_start'] && $reviewsReport['date'] <= $reports['range']['prev_end']) $reviewsReport['previous'][] = $reviewsReport['row'];
	$reviewsReport['provider'] = trim((string) ($reviewsReport['row']['provider'] ?? 'local')) ?: 'local';
	if (!isset($reviewsReport['providers'][$reviewsReport['provider']])) $reviewsReport['providers'][$reviewsReport['provider']] = ['count'=>0,'sum'=>0];
	$reviewsReport['providers'][$reviewsReport['provider']]['count']++;
	$reviewsReport['providers'][$reviewsReport['provider']]['sum'] += $reviewsReport['rating'];
}

if (!count($reviewsReport['published'])) {
	unset($reviewsReport);
	return;
}

$reports['has_values'] = 1;
$reviewsReport['published_count'] = count($reviewsReport['published']);
$reviewsReport['current_count'] = count($reviewsReport['current']);
$reviewsReport['previous_count'] = count($reviewsReport['previous']);
$reviewsReport['sum'] = 0;
foreach ($reviewsReport['published'] as $reviewsReport['row']) $reviewsReport['sum'] += max(1,min(5,intval($reviewsReport['row']['rating'] ?? 0)));
$reviewsReport['average'] = $reviewsReport['published_count'] > 0 ? round($reviewsReport['sum'] / $reviewsReport['published_count'],1) : 0;
$reviewsReport['score'] = (int) round($reviewsReport['average'] / 5 * 100);
$reviewsReport['average_label'] = $reviewsReport['format']($reviewsReport['average'],$site['default_language']);

$reviewsReport['list'][] = ['feature'=>'lead','data'=>[
	'titlekey'=>'_reports_reviews','title'=>'','desckey'=>'_reports_reviews_description','text'=>'','hasassess'=>'1',
	'assess'=>[
		['color'=>reports__score_color($reviewsReport['score']),'labelkey'=>'_reports_reviews_average','label'=>'','level'=>$reviewsReport['average_label'].' / 5'],
		['color'=>$reviewsReport['current_count'] > 0 ? '#2e7d32' : '#9e9e9e','labelkey'=>'_reports_reviews_new','label'=>'','level'=>(string) $reviewsReport['current_count']]
	]
]];
$reviewsReport['list'][] = ['feature'=>'score','data'=>[
	'titlekey'=>'_reports_reviews_quality','title'=>'','desckey'=>'',
	'value'=>$reviewsReport['average_label'],'color'=>reports__score_color($reviewsReport['score']),'labelkey'=>'_reports_reviews_average',
	'delta'=>'',
	'metric'=>reports__metrics([
		['key'=>'published','label'=>'_reports_reviews_published','value'=>$reviewsReport['published_count']],
		['key'=>'current','label'=>'_reports_reviews_new','value'=>$reviewsReport['current_count']],
		['key'=>'unpublished','label'=>'_reports_reviews_unpublished','value'=>$reviewsReport['unpublished']],
		['key'=>'featured','label'=>'_reports_reviews_featured','value'=>$reviewsReport['featured']]
	],[
		['key'=>'current','value'=>$reviewsReport['previous_count']]
	])
]];

$reviewsReport['segments'] = [];
$reviewsReport['legend'] = [];
foreach ([5=>['#2e7d32','_reports_reviews_5_star'],4=>['#6aa84f','_reports_reviews_4_star'],3=>['#d99e3a','_reports_reviews_3_star'],2=>['#c62828','_reports_reviews_1_2_star']] as $reviewsReport['star'] => $reviewsReport['meta']) {
	$reviewsReport['count'] = $reviewsReport['star'] == 2 ? $reviewsReport['ratings'][1] + $reviewsReport['ratings'][2] : $reviewsReport['ratings'][$reviewsReport['star']];
	if ($reviewsReport['count'] > 0) {
		$reviewsReport['width'] = round($reviewsReport['count'] / $reviewsReport['published_count'] * 100,1);
		$reviewsReport['segments'][] = ['width'=>$reviewsReport['width'],'color'=>$reviewsReport['meta'][0],'inlabel'=>($reviewsReport['width'] >= 12) ? (string) $reviewsReport['count'] : ''];
	}
	$reviewsReport['legend'][] = ['color'=>$reviewsReport['meta'][0],'labelkey'=>$reviewsReport['meta'][1],'label'=>'','count'=>(string) $reviewsReport['count']];
}
if ($reviewsReport['segments']) $reviewsReport['list'][] = ['feature'=>'split','data'=>['titlekey'=>'_reports_reviews_distribution','title'=>'','value'=>(string) $reviewsReport['published_count'],'valuecolor'=>'','delta'=>'','seg'=>$reviewsReport['segments'],'haslegend'=>'1','legend'=>$reviewsReport['legend']]];

if ($reviewsReport['providers']) {
	$reviewsReport['providerNames'] = $reviewsReport['instance']->providers();
	$reviewsReport['providerMax'] = 1;
	foreach ($reviewsReport['providers'] as $reviewsReport['providerData']) $reviewsReport['providerMax'] = max($reviewsReport['providerMax'],$reviewsReport['providerData']['count']);
	$reviewsReport['providerRows'] = [];
	uasort($reviewsReport['providers'],function($a,$b) { return intval($b['count'] ?? 0) <=> intval($a['count'] ?? 0); });
	foreach ($reviewsReport['providers'] as $reviewsReport['provider'] => $reviewsReport['providerData']) {
		$reviewsReport['providerAverage'] = $reviewsReport['providerData']['count'] > 0 ? round($reviewsReport['providerData']['sum'] / $reviewsReport['providerData']['count'],1) : 0;
		$reviewsReport['providerWidth'] = (int) round($reviewsReport['providerData']['count'] / $reviewsReport['providerMax'] * 100);
		$reviewsReport['providerRows'][] = ['name'=>htmlspecialchars($reviewsReport['providerNames'][$reviewsReport['provider']]['name'] ?? ucfirst($reviewsReport['provider']),ENT_QUOTES,'UTF-8'),'width'=>$reviewsReport['providerWidth'],'rest'=>100 - $reviewsReport['providerWidth'],'color'=>'#4a90d9','num'=>(string) $reviewsReport['providerData']['count'],'sub'=>$reviewsReport['format']($reviewsReport['providerAverage'],$site['default_language']).' / 5'];
	}
	$reviewsReport['list'][] = ['feature'=>'bars','data'=>['titlekey'=>'_reports_reviews_sources','title'=>'','value'=>'','valuecolor'=>'','delta'=>'','row'=>$reviewsReport['providerRows']]];
}

if ($reviewsReport['current']) {
	usort($reviewsReport['current'],function($a,$b) {
		$rating = intval($a['rating'] ?? 0) <=> intval($b['rating'] ?? 0);
		return $rating != 0 ? $rating : intval($b['date'] ?? 0) <=> intval($a['date'] ?? 0);
	});
	$reviewsReport['items'] = [];
	foreach (array_slice($reviewsReport['current'],0,5) as $reviewsReport['row']) {
		$reviewsReport['text'] = trim(strip_tags((string) ($reviewsReport['row']['text'] ?? '')));
		if (mb_strlen($reviewsReport['text'],'UTF-8') > 180) $reviewsReport['text'] = mb_substr($reviewsReport['text'],0,177,'UTF-8').'...';
		$reviewsReport['items'][] = [
			'flag'=>'',
			'text'=>htmlspecialchars(trim((string) ($reviewsReport['row']['author'] ?? '')) ?: language__get($site['default_language'],'_reviews_no_author'),ENT_QUOTES,'UTF-8').' · '.str_repeat('★',max(1,min(5,intval($reviewsReport['row']['rating'] ?? 0)))).'<br><span style="color:#8c887e;font-size:11px;">'.htmlspecialchars($reviewsReport['text'],ENT_QUOTES,'UTF-8').'</span>'
		];
	}
	if ($reviewsReport['items']) $reviewsReport['list'][] = ['feature'=>'issue','data'=>['border'=>'#d99e3a','titlekey'=>'_reports_reviews_recent','desckey'=>'','item'=>$reviewsReport['items']]];
}

foreach ($reports['recipients'] as $reviewsReport['email'] => $reviewsReport['value']) $reports['items'][$reviewsReport['email']] = ['list'=>$reviewsReport['list']];

unset($reviewsReport);
