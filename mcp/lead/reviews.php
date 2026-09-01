<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Receives a customer review from the visitor and stores it unpublished for moderation.',
	'args'=>['data'=>'rating:1-5 and text are required. author and source are optional.'],
	'scope'=>['user','admin'],
	'anonymous'=>true,
	'covers_forms'=>['review','bewertung'],
	'lead'=>\mcp\Descriptors::lead('reviews','','reviews',63,'review')
];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviewsMcp = [
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
	'data'=>is_array($context->args['data'] ?? null) ? $context->args['data'] : [],
	'language'=>\mcp\Util::language($context->task)
];
if ($reviewsMcp['language'] === '') $reviewsMcp['language'] = $_SESSION['language'] ?? ($site['default_language'] ?? 'de');
if (!isset($reviewsMcp['data']['rating']) || intval($reviewsMcp['data']['rating']) < 1 || intval($reviewsMcp['data']['rating']) > 5) return ['error'=>'rating must be an integer from 1 to 5.'];
if (!isset($reviewsMcp['data']['text']) || is_array($reviewsMcp['data']['text']) || is_object($reviewsMcp['data']['text']) || trim((string) $reviewsMcp['data']['text']) === '') return ['error'=>'text is required.'];

$reviewsMcp['post'] = [
	'author'=>trim((string) ($reviewsMcp['data']['author'] ?? '')),
	'source'=>trim((string) ($reviewsMcp['data']['source'] ?? '')),
	'rating'=>intval($reviewsMcp['data']['rating']),
	'text'=>[$reviewsMcp['language']=>trim((string) $reviewsMcp['data']['text'])],
	'lid'=>[$reviewsMcp['language']],
	'date'=>intval($_SERVER['now'] ?? time()),
	'published'=>$context->scope === 'admin' && isset($reviewsMcp['data']['published']) ? intval($reviewsMcp['data']['published']) : 0,
	'featured'=>0
];
$reviewsMcp['result'] = $reviewsMcp['instance']->saveFromPost('new',$reviewsMcp['post']);
if (empty($reviewsMcp['result']['result'])) return ['error'=>'Review could not be saved.'];
return [
	'type'=>'reviews',
	'id'=>$reviewsMcp['result']['id'],
	'status'=>'received',
	'published'=>intval($reviewsMcp['post']['published']),
	'next_hint'=>'Reply with a short acknowledgement. Tell the visitor the review has been received and will be checked before publication.'
];
