<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Finds customer reviews. Visitors reach published reviews only; admins can also filter the complete collection.',
	'args'=>[
		'terms'=>'The words to find, or ["*"] to list reviews.',
		'data'=>'Optional admin filters: published:0|1, rating:1-5, provider:"google"|...'
	],
	'scope'=>['user','admin'],
	'anonymous'=>true
];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviewsMcp = [
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages']),
	'language'=>\mcp\Util::language($context->task),
	'filter'=>(isset($context->args['data']) && is_array($context->args['data'])) ? $context->args['data'] : [],
	'results'=>[]
];
$reviewsMcp['search'] = \mcp\Search::query($context);
if ($reviewsMcp['language'] === '') $reviewsMcp['language'] = $_SESSION['language'] ?? ($site['default_language'] ?? 'de');

foreach ($reviewsMcp['instance']->all() as $reviewsMcp['id'] => $reviewsMcp['entry']) {
	$reviewsMcp['row'] = $reviewsMcp['instance']->display($reviewsMcp['id'],$reviewsMcp['language']);
	if ($context->scope !== 'admin' && empty($reviewsMcp['row']['published'])) continue;
	if (isset($reviewsMcp['filter']['published']) && is_numeric($reviewsMcp['filter']['published']) && intval($reviewsMcp['row']['published'] ?? 0) !== intval($reviewsMcp['filter']['published'])) continue;
	if (isset($reviewsMcp['filter']['rating']) && is_numeric($reviewsMcp['filter']['rating']) && intval($reviewsMcp['row']['rating'] ?? 0) !== intval($reviewsMcp['filter']['rating'])) continue;
	if (isset($reviewsMcp['filter']['provider']) && is_string($reviewsMcp['filter']['provider']) && trim($reviewsMcp['filter']['provider']) !== '' && trim((string) ($reviewsMcp['row']['provider'] ?? '')) !== trim($reviewsMcp['filter']['provider'])) continue;

	$reviewsMcp['texts'] = [];
	foreach (($reviewsMcp['entry']['text'] ?? []) as $reviewsMcp['text']) if (!is_array($reviewsMcp['text']) && !is_object($reviewsMcp['text'])) $reviewsMcp['texts'][] = trim(strip_tags((string) $reviewsMcp['text']));
	$reviewsMcp['match'] = trim($reviewsMcp['id'].' '.($reviewsMcp['row']['author'] ?? '').' '.($reviewsMcp['row']['source'] ?? '').' '.($reviewsMcp['row']['provider'] ?? '').' '.implode(' ',$reviewsMcp['texts']));
	if ($reviewsMcp['search']['terms'] !== ['*'] && !\mcp\Search::matches($reviewsMcp['match'],$reviewsMcp['search']['terms'],$reviewsMcp['search']['mode'])) continue;

	$reviewsMcp['results'][] = \mcp\Search::result('reviews',$reviewsMcp['id'],\mcp\Search::snippet(trim((string) ($reviewsMcp['row']['text'] ?? '')),$reviewsMcp['search']['terms'],180),trim((string) ($reviewsMcp['row']['author'] ?? '')));
}

unset($reviewsMcp['instance']);
return $reviewsMcp['results'];
