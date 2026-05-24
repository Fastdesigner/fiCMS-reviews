<?php

if (!$site['onsite']) return;

$reviews = [
	'data_file'=>dirname(__DIR__,2).'/data/reviews.json',
	'data'=>['reviews'=>[]],
	'structure_file'=>'',
	'structure'=>[],
	'limit'=>6,
	'min_rating'=>1,
	'replace'=>[
		'count'=>0,
		'list'=>''
	],
	'options'=>[
		'show_rating'=>1,
		'show_source'=>1,
		'show_date'=>1
	],
	'items'=>[],
	'content'=>''
];

$reviews['structure_file'] = widgets__layout_file('review');
if ($reviews['structure_file'] == '') $reviews['structure_file'] = widgets__layout_file($service['temp']['data']['wert']);
if ($reviews['structure_file'] == '') {
	$service['content'] = '';
	unset($reviews);
	return;
}

if (isset($service['temp']['data']['add']) && intval($service['temp']['data']['add']) > 0) $reviews['limit'] = intval($service['temp']['data']['add']);
if (isset($service['temp']['data']['block']['option_widgetnum']) && intval($service['temp']['data']['block']['option_widgetnum']) > 0) $reviews['limit'] = intval($service['temp']['data']['block']['option_widgetnum']);
if (isset($service['temp']['data']['block']['option_widgetvalue']) && intval($service['temp']['data']['block']['option_widgetvalue']) > 0) $reviews['min_rating'] = max(1,min(5,intval($service['temp']['data']['block']['option_widgetvalue'])));
foreach ($reviews['options'] as $reviews['option'] => $reviews['default']) $reviews['options'][$reviews['option']] = intval($service['temp']['data']['block']['option_'.$reviews['option']] ?? $reviews['default']) == 1 ? 1 : 0;
if ($reviews['limit'] <= 0) $reviews['limit'] = 6;

if (is_file($reviews['data_file'])) {
	$reviews['loaded'] = helper__json_convert(file_get_contents($reviews['data_file']));
	if (is_array($reviews['loaded'])) $reviews['data'] = array_merge($reviews['data'],$reviews['loaded']);
}
if (!isset($reviews['data']['reviews']) || !is_array($reviews['data']['reviews'])) $reviews['data']['reviews'] = [];
$reviews['structure'] = parser__file($reviews['structure_file']);
if (!isset($reviews['structure']['list'])) $reviews['structure']['list'] = '';

uasort($reviews['data']['reviews'],function($a,$b) {
	$af = intval($a['featured'] ?? 0);
	$bf = intval($b['featured'] ?? 0);
	if ($af != $bf) return ($af < $bf) ? 1 : -1;
	$ad = intval($a['date'] ?? 0);
	$bd = intval($b['date'] ?? 0);
	if ($ad == $bd) return 0;
	return ($ad < $bd) ? 1 : -1;
});

foreach ($reviews['data']['reviews'] as $reviews['entry']) {
	if (count($reviews['items']) >= $reviews['limit']) break;
	if (empty($reviews['entry']['published'])) continue;
	if (intval($reviews['entry']['rating'] ?? 0) < $reviews['min_rating']) continue;
	$reviews['entry']['lid'] = helper__json_convert($reviews['entry']['lid'] ?? ['all']);
	if (!in_array('all',$reviews['entry']['lid'],true) && !in_array($_SESSION['language'],$reviews['entry']['lid'],true)) continue;
	foreach (['author','source','text'] as $reviews['field']) {
		if (!is_array($reviews['entry'][$reviews['field']] ?? [])) $reviews['entry'][$reviews['field']] = [$_SESSION['language']=>$reviews['entry'][$reviews['field']]];
		$reviews['entry'][$reviews['field']] = trim((string) language__from_array($reviews['entry'][$reviews['field']],$_SESSION['language']));
	}
	if ($reviews['entry']['text'] == '') continue;
	$reviews['rating'] = max(1,min(5,intval($reviews['entry']['rating'] ?? 0)));
	$reviews['item'] = [
		'id'=>htmlspecialchars(trim((string) ($reviews['entry']['id'] ?? '')),ENT_QUOTES,'UTF-8'),
		'author'=>htmlspecialchars(trim((string) ($reviews['entry']['author'] ?? '')),ENT_QUOTES,'UTF-8'),
		'source'=>htmlspecialchars(trim((string) ($reviews['entry']['source'] ?? '')),ENT_QUOTES,'UTF-8'),
		'text'=>nl2br(htmlspecialchars(trim((string) ($reviews['entry']['text'] ?? '')),ENT_QUOTES,'UTF-8')),
		'rating'=>$reviews['rating'],
		'rating_value'=>$reviews['rating'],
		'rating_label'=>htmlspecialchars(language__get_parsed($_SESSION['language'],'_reviews_rating_label',['rating'=>$reviews['rating']]),ENT_QUOTES,'UTF-8'),
		'rating_stars'=>str_repeat('★',$reviews['rating']),
		'date'=>format__date_relative(intval($reviews['entry']['date'] ?? $_SERVER['now']),'date',$_SESSION['language']),
		'datetime'=>date('Y-m-d',intval($reviews['entry']['date'] ?? $_SERVER['now'])),
		'featured'=>intval($reviews['entry']['featured'] ?? 0),
		'has_author'=>trim((string) ($reviews['entry']['author'] ?? '')) !== '' ? 1 : 0,
		'has_source'=>trim((string) ($reviews['entry']['source'] ?? '')) !== '' ? 1 : 0,
		'render_rating'=>$reviews['options']['show_rating'],
		'render_source'=>($reviews['options']['show_source'] == 1 && trim((string) ($reviews['entry']['source'] ?? '')) !== '') ? 1 : 0,
		'render_date'=>$reviews['options']['show_date']
	];
	if ($reviews['structure']['list'] !== '') {
		$reviews['line'] = $reviews['structure']['list'];
		$reviews['items'][] = parser__replace($reviews['line'],$reviews['item']);
	}
}

if (count($reviews['items']) > 0) {
	$reviews['replace']['count'] = count($reviews['items']);
	$reviews['replace']['list'] = implode($reviews['items']);
	$reviews['content'] = parser__replace($reviews['structure']['frame'],$reviews['replace']);
}
$service['content'] = $reviews['content'];

unset($reviews);
