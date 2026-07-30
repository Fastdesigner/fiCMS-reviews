<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Loads one customer review. Visitors reach published reviews only; an admin edit view adds the exact stored source data.',
	'args'=>[
		'id'=>'Review id returned by search.',
		'data'=>'An admin may pass {"view":"edit"} to receive the writable source data.'
	],
	'scope'=>['user','admin'],
	'anonymous'=>true
];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviewsMcp = [
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
	'id'=>trim((string) ($context->args['id'] ?? '')),
	'language'=>\mcp\Util::language($context->task)
];
if ($reviewsMcp['language'] === '') $reviewsMcp['language'] = $_SESSION['language'] ?? ($site['default_language'] ?? 'de');
if ($reviewsMcp['id'] === '') return ['error'=>'review id required - use search([terms], "reviews") to find ids'];

$reviewsMcp['all'] = $reviewsMcp['instance']->all();
if (!isset($reviewsMcp['all'][$reviewsMcp['id']])) return ['error'=>'Review not found.'];

$reviewsMcp['row'] = $reviewsMcp['instance']->display($reviewsMcp['id'],$reviewsMcp['language']);
if ($context->scope !== 'admin' && empty($reviewsMcp['row']['published'])) return ['error'=>'Review not found.'];

$reviewsMcp['result'] = [
	'type'=>'reviews',
	'id'=>$reviewsMcp['id'],
	'author'=>trim((string) ($reviewsMcp['row']['author'] ?? '')),
	'source'=>trim((string) ($reviewsMcp['row']['source'] ?? '')),
	'rating'=>intval($reviewsMcp['row']['rating'] ?? 0),
	'text'=>trim((string) ($reviewsMcp['row']['text'] ?? '')),
	'date'=>intval($reviewsMcp['row']['date'] ?? 0),
	'provider'=>trim((string) ($reviewsMcp['row']['provider'] ?? 'local')),
	'external_url'=>trim((string) ($reviewsMcp['row']['external_url'] ?? ''))
];
if ($context->scope === 'admin') {
	$reviewsMcp['result']['published'] = intval($reviewsMcp['row']['published'] ?? 0);
	$reviewsMcp['result']['featured'] = intval($reviewsMcp['row']['featured'] ?? 0);
	$reviewsMcp['result']['source_type'] = trim((string) ($reviewsMcp['row']['source_type'] ?? 'local'));
}
if ($context->scope === 'admin' && $context->edits()) $reviewsMcp['result']['edit'] = $reviewsMcp['all'][$reviewsMcp['id']];
return $reviewsMcp['result'];
