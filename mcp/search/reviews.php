<?php

if (!$site['onsite']) return false;

if ($mcp['mode'] === 'capabilities') return ['tool'=>'search','type'=>'reviews','text'=>'search([terms], "reviews") searches published customer reviews. Admin may pass {data:{published:0|1, rating:1-5, provider:"google"|...}}. Pass terms:["*"] to list reviews.'];
if ($mcp['mode'] === 'schema') return [];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviewsMcp = [
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
	'language'=>function_exists('mcp__task_language') ? mcp__task_language($mcp['task'] ?? []) : '',
	'filter'=>(isset($mcp['args']['data']) && is_array($mcp['args']['data'])) ? $mcp['args']['data'] : [],
	'results'=>[]
];
if ($reviewsMcp['language'] === '') $reviewsMcp['language'] = $_SESSION['language'] ?? ($site['default_language'] ?? 'de');

foreach ($reviewsMcp['instance']->all() as $reviewsMcp['id'] => $reviewsMcp['entry']) {
	$reviewsMcp['row'] = $reviewsMcp['instance']->display($reviewsMcp['id'],$reviewsMcp['language']);
	if (($mcp['scope'] ?? 'user') !== 'admin' && empty($reviewsMcp['row']['published'])) continue;
	if (isset($reviewsMcp['filter']['published']) && is_numeric($reviewsMcp['filter']['published']) && intval($reviewsMcp['row']['published'] ?? 0) !== intval($reviewsMcp['filter']['published'])) continue;
	if (isset($reviewsMcp['filter']['rating']) && is_numeric($reviewsMcp['filter']['rating']) && intval($reviewsMcp['row']['rating'] ?? 0) !== intval($reviewsMcp['filter']['rating'])) continue;
	if (isset($reviewsMcp['filter']['provider']) && is_string($reviewsMcp['filter']['provider']) && trim($reviewsMcp['filter']['provider']) !== '' && trim((string) ($reviewsMcp['row']['provider'] ?? '')) !== trim($reviewsMcp['filter']['provider'])) continue;

	$reviewsMcp['texts'] = [];
	foreach (($reviewsMcp['entry']['text'] ?? []) as $reviewsMcp['text']) if (!is_array($reviewsMcp['text']) && !is_object($reviewsMcp['text'])) $reviewsMcp['texts'][] = trim(strip_tags((string) $reviewsMcp['text']));
	$reviewsMcp['match'] = trim($reviewsMcp['id'].' '.($reviewsMcp['row']['author'] ?? '').' '.($reviewsMcp['row']['source'] ?? '').' '.($reviewsMcp['row']['provider'] ?? '').' '.implode(' ',$reviewsMcp['texts']));
	if ($search['terms'] !== ['*'] && !mcp__text_match($reviewsMcp['match'],$search['terms'],$search['mode'])) continue;

	$reviewsMcp['results'][] = [
		'type'=>'reviews',
		'id'=>$reviewsMcp['id'],
		'rating'=>intval($reviewsMcp['row']['rating'] ?? 0),
		'provider'=>trim((string) ($reviewsMcp['row']['provider'] ?? 'local')),
		'title'=>trim((string) ($reviewsMcp['row']['author'] ?? '')),
		'text'=>mcp__text_snippet(trim((string) ($reviewsMcp['row']['text'] ?? '')),$search['terms'],180),
		'published'=>intval($reviewsMcp['row']['published'] ?? 0),
		'external_url'=>trim((string) ($reviewsMcp['row']['external_url'] ?? '')),
		'full_version'=>['tool'=>'get','type'=>'reviews','id'=>$reviewsMcp['id']]
	];
}

unset($reviewsMcp['instance']);
return $reviewsMcp['results'];
