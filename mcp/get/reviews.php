<?php

if (!$site['onsite']) return false;

if ($mcp['mode'] === 'capabilities') return [];
if ($mcp['mode'] === 'schema') return [];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviewsMcp = [
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
	'id'=>trim((string) ($get['id'] ?? '')),
	'language'=>function_exists('mcp__task_language') ? mcp__task_language($mcp['task'] ?? []) : ''
];
if ($reviewsMcp['language'] === '') $reviewsMcp['language'] = $_SESSION['language'] ?? ($site['default_language'] ?? 'de');
if ($reviewsMcp['id'] === '') return ['error'=>'review id required - use search([terms], "reviews") to find ids'];

$reviewsMcp['all'] = $reviewsMcp['instance']->all();
if (!isset($reviewsMcp['all'][$reviewsMcp['id']])) return ['error'=>'Review not found.'];

$reviewsMcp['row'] = $reviewsMcp['instance']->display($reviewsMcp['id'],$reviewsMcp['language']);
if (($mcp['scope'] ?? 'user') !== 'admin' && empty($reviewsMcp['row']['published'])) return ['error'=>'Review not found.'];

$reviewsMcp['result'] = [
	'type'=>'reviews',
	'id'=>$reviewsMcp['id'],
	'author'=>trim((string) ($reviewsMcp['row']['author'] ?? '')),
	'source'=>trim((string) ($reviewsMcp['row']['source'] ?? '')),
	'rating'=>intval($reviewsMcp['row']['rating'] ?? 0),
	'text'=>trim((string) ($reviewsMcp['row']['text'] ?? '')),
	'language'=>$reviewsMcp['language'],
	'lid'=>$reviewsMcp['row']['lid'] ?? [],
	'text_lid'=>$reviewsMcp['row']['text_lid'] ?? [],
	'date'=>intval($reviewsMcp['row']['date'] ?? 0),
	'published'=>intval($reviewsMcp['row']['published'] ?? 0),
	'featured'=>intval($reviewsMcp['row']['featured'] ?? 0),
	'provider'=>trim((string) ($reviewsMcp['row']['provider'] ?? 'local')),
	'source_type'=>trim((string) ($reviewsMcp['row']['source_type'] ?? 'local')),
	'external_url'=>trim((string) ($reviewsMcp['row']['external_url'] ?? ''))
];
if (($mcp['scope'] ?? 'user') === 'admin') $reviewsMcp['result']['data'] = $reviewsMcp['all'][$reviewsMcp['id']];
return $reviewsMcp['result'];
