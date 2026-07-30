<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Creates a local customer review as admin. Visitor chat submissions use lead("reviews", data).',
	'args'=>['data'=>'rating:1-5 and text are required. author, source, provider, external_url, lid, published and featured are optional.'],
	'scope'=>['admin']
];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviewsMcp = [
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
	'data'=>is_array($context->args['data'] ?? null) ? $context->args['data'] : [],
	'language'=>\mcp\Util::language($context->task)
];
if ($reviewsMcp['language'] === '') $reviewsMcp['language'] = $_SESSION['language'] ?? ($site['default_language'] ?? 'de');
if (!isset($reviewsMcp['data']['rating']) || intval($reviewsMcp['data']['rating']) < 1 || intval($reviewsMcp['data']['rating']) > 5) return ['error'=>'rating must be an integer from 1 to 5.'];
if (!isset($reviewsMcp['data']['text'])) return ['error'=>'text is required.'];
$reviewsMcp['hasText'] = false;
foreach (is_array($reviewsMcp['data']['text']) ? $reviewsMcp['data']['text'] : [$reviewsMcp['data']['text']] as $reviewsMcp['textValue']) if (!is_array($reviewsMcp['textValue']) && !is_object($reviewsMcp['textValue']) && trim((string) $reviewsMcp['textValue']) !== '') $reviewsMcp['hasText'] = true;
if (!$reviewsMcp['hasText']) return ['error'=>'text is required.'];

$reviewsMcp['post'] = [
	'author'=>trim((string) ($reviewsMcp['data']['author'] ?? '')),
	'source'=>trim((string) ($reviewsMcp['data']['source'] ?? '')),
	'provider'=>trim((string) ($reviewsMcp['data']['provider'] ?? 'local')),
	'external_url'=>trim((string) ($reviewsMcp['data']['external_url'] ?? '')),
	'rating'=>intval($reviewsMcp['data']['rating']),
	'text'=>is_array($reviewsMcp['data']['text']) ? $reviewsMcp['data']['text'] : [$reviewsMcp['language']=>trim((string) $reviewsMcp['data']['text'])],
	'lid'=>isset($reviewsMcp['data']['lid']) ? $reviewsMcp['data']['lid'] : [$reviewsMcp['language']],
	'date'=>isset($reviewsMcp['data']['date']) && is_numeric($reviewsMcp['data']['date']) ? intval($reviewsMcp['data']['date']) : intval($_SERVER['now'] ?? time()),
	'published'=>isset($reviewsMcp['data']['published']) ? intval($reviewsMcp['data']['published']) : 1,
	'featured'=>isset($reviewsMcp['data']['featured']) ? intval($reviewsMcp['data']['featured']) : 0
];
$reviewsMcp['result'] = $reviewsMcp['instance']->saveFromPost('new',$reviewsMcp['post']);
if (empty($reviewsMcp['result']['result'])) return ['error'=>'Review could not be saved.'];
return ['type'=>'reviews','id'=>$reviewsMcp['result']['id'],'created'=>true,'published'=>intval($reviewsMcp['post']['published'])];
