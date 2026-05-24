<?php

if (!$site['onsite']) return;

$reviews = [
	'data_file'=>dirname(__DIR__,2).'/data/reviews.json',
	'data'=>['reviews'=>[]],
	'limit'=>6,
	'min_rating'=>1,
	'items'=>[],
	'content'=>''
];

if (isset($service['temp']['data']['add']) && intval($service['temp']['data']['add']) > 0) $reviews['limit'] = intval($service['temp']['data']['add']);
if (isset($service['temp']['data']['block']['option_widgetnum']) && intval($service['temp']['data']['block']['option_widgetnum']) > 0) $reviews['limit'] = intval($service['temp']['data']['block']['option_widgetnum']);
if (isset($service['temp']['data']['block']['option_widgetvalue']) && intval($service['temp']['data']['block']['option_widgetvalue']) > 0) $reviews['min_rating'] = max(1,min(5,intval($service['temp']['data']['block']['option_widgetvalue'])));
if ($reviews['limit'] <= 0) $reviews['limit'] = 6;

if (is_file($reviews['data_file'])) {
	$reviews['loaded'] = helper__json_convert(file_get_contents($reviews['data_file']));
	if (is_array($reviews['loaded'])) $reviews['data'] = array_merge($reviews['data'],$reviews['loaded']);
}
if (!isset($reviews['data']['reviews']) || !is_array($reviews['data']['reviews'])) $reviews['data']['reviews'] = [];

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
	$reviews['items'][] = '<article class="reviews__item" data-rating="'.$reviews['rating'].'">'.
		'<div class="reviews__rating" aria-label="'.htmlspecialchars(language__get_parsed($_SESSION['language'],'_reviews_rating_label',['rating'=>$reviews['rating']]),ENT_QUOTES,'UTF-8').'">'.str_repeat('★',$reviews['rating']).'</div>'.
		'<blockquote class="reviews__text">'.nl2br(htmlspecialchars(trim((string) ($reviews['entry']['text'] ?? '')),ENT_QUOTES,'UTF-8')).'</blockquote>'.
		'<footer class="reviews__meta">'.
			'<span class="reviews__author">'.htmlspecialchars(trim((string) ($reviews['entry']['author'] ?? '')),ENT_QUOTES,'UTF-8').'</span>'.
			(trim((string) ($reviews['entry']['source'] ?? '')) !== '' ? '<span class="reviews__source">'.htmlspecialchars(trim((string) $reviews['entry']['source']),ENT_QUOTES,'UTF-8').'</span>' : '').
			'<time datetime="'.date('Y-m-d',intval($reviews['entry']['date'] ?? $_SERVER['now'])).'">'.format__date_relative(intval($reviews['entry']['date'] ?? $_SERVER['now']),'date',$_SESSION['language']).'</time>'.
		'</footer>'.
	'</article>';
}

if (count($reviews['items']) > 0) $reviews['content'] = '<section class="reviews" data-count="'.count($reviews['items']).'">'.implode('',$reviews['items']).'</section>';
$service['content'] = $reviews['content'];

unset($reviews);
