<?php

if (!$site['onsite']) return;

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviews = [
	'structure_file'=>'',
	'structure'=>[],
	'layout_paths'=>[
		DESIGNPATH.'/widgets/review',
		DESIGNPATH.'/widgets/reviews',
		__DIR__
	],
	'block_files'=>[],
	'sections'=>[],
	'block'=>isset($service['temp']['data']['block']) && is_array($service['temp']['data']['block']) ? $service['temp']['data']['block'] : [],
	'limit'=>6,
	'layout'=>'list',
	'selected'=>'list',
	'min_rating'=>1,
	'summary_mode'=>'none',
	'show_items'=>1,
	'rows'=>[],
	'summary_rows'=>[],
	'summary_items'=>[],
	'summary'=>[],
	'uses_rating_stars'=>false,
	'replace'=>[
		'count'=>0,
		'layout'=>'list',
		'items'=>'',
		'list'=>'',
		'slider'=>'',
		'summary'=>'',
		'render_items'=>0,
		'render_list'=>0,
		'render_slider'=>0,
		'render_summary'=>0
	],
	'options'=>[
		'show_rating'=>1,
		'show_source'=>1,
		'show_date'=>1
	],
	'items'=>[],
	'content'=>'',
	'instance'=>new FiCMSReviews(dirname(__DIR__,2),$site['default_language'],$site['installed_languages'])
];

foreach ($reviews['layout_paths'] as $reviews['layout_path']) if (is_file($reviews['layout_path'].'/frame.html')) { $reviews['structure_file'] = $reviews['layout_path'].'/frame.html'; break; }
if ($reviews['structure_file'] == '') {
	$service['content'] = '';
	unset($reviews);
	return;
}

$_SERVER['load_services']['reviews'] = true;

if (isset($service['temp']['data']['add']) && intval($service['temp']['data']['add']) > 0) $reviews['limit'] = intval($service['temp']['data']['add']);
if (isset($reviews['block']['option_widgetnum']) && intval($reviews['block']['option_widgetnum']) > 0) $reviews['limit'] = intval($reviews['block']['option_widgetnum']);
if (isset($reviews['block']['option_reviews_min_rating']) && intval($reviews['block']['option_reviews_min_rating']) > 0) $reviews['min_rating'] = max(1,min(5,intval($reviews['block']['option_reviews_min_rating'])));
else if (isset($reviews['block']['option_widgetvalue']) && intval($reviews['block']['option_widgetvalue']) > 0) $reviews['min_rating'] = max(1,min(5,intval($reviews['block']['option_widgetvalue'])));
foreach ($reviews['options'] as $reviews['option'] => $reviews['default']) $reviews['options'][$reviews['option']] = intval($reviews['block']['option_'.$reviews['option']] ?? $reviews['default']) == 1 ? 1 : 0;
if ($reviews['limit'] <= 0) $reviews['limit'] = 6;

$reviews['summary_mode'] = trim((string) ($reviews['block']['option_reviews_summary_mode'] ?? 'none'));
if (!in_array($reviews['summary_mode'],['none','global','provider'],true)) $reviews['summary_mode'] = 'none';
$reviews['show_items'] = intval($reviews['block']['option_reviews_show_items'] ?? 1) == 1 ? 1 : 0;
$reviews['layout'] = trim((string) ($reviews['block']['option_reviews_layout'] ?? 'list'));
if ($reviews['layout'] == 'summary') {
	if (!isset($reviews['block']['option_reviews_summary_mode'])) $reviews['summary_mode'] = 'global';
	if (!isset($reviews['block']['option_reviews_show_items'])) $reviews['show_items'] = 0;
	$reviews['layout'] = 'list';
}
if ($reviews['layout'] == '') $reviews['layout'] = 'list';
$reviews['structure'] = parser__file($reviews['structure_file']);
foreach ($reviews['layout_paths'] as $reviews['layout_path']) {
	foreach (glob($reviews['layout_path'].'/blocks/*.html', GLOB_NOSORT) ?: [] as $reviews['section_file']) {
		$reviews['section'] = basename($reviews['section_file'],'.html');
		if ($reviews['section'] != '') $reviews['sections'][$reviews['section']] = $reviews['section'];
	}
}
foreach ($reviews['sections'] as $reviews['section']) {
	$reviews['replace'][$reviews['section']] = '';
	$reviews['replace']['render_'.$reviews['section']] = 0;
	$reviews['structure'][$reviews['section']] = '';
	$reviews['block_files'][$reviews['section']] = '';
	foreach ($reviews['layout_paths'] as $reviews['layout_path']) if (is_file($reviews['layout_path'].'/blocks/'.$reviews['section'].'.html')) { $reviews['block_files'][$reviews['section']] = $reviews['layout_path'].'/blocks/'.$reviews['section'].'.html'; break; }
	if ($reviews['block_files'][$reviews['section']] == '') continue;
	$reviews['block_structure'] = parser__file($reviews['block_files'][$reviews['section']]);
	$reviews['structure'][$reviews['section']] = $reviews['block_structure']['frame'] ?? '';
}
if (!isset($reviews['structure'][$reviews['layout']]) || $reviews['structure'][$reviews['layout']] == '') $reviews['layout'] = 'list';
$reviews['selected'] = $reviews['layout'];
$reviews['uses_rating_stars'] = strpos($reviews['structure'][$reviews['selected']],'###rating_stars###') !== false;

// Fragment-Cache: Output hängt an Block-Optionen (block:<id>), Sprache (Scope) und den
// Review-Quelldateien (data/reviews.json + integrations.json, vom Sync neu geschrieben → mtime).
// Cache-Hit überspringt den Daten-Load + Render komplett.
$reviews['block_id'] = (int) ($service['cache']['context']['block_id'] ?? ($reviews['block']['id'] ?? 0));
$reviews['definition'] = [
	'type'=>'fragment',
	'scope'=>[
		'widget'=>$service['cache']['context']['widget'] ?? ($service['temp']['data']['wert'] ?? 'reviews'),
		'block_id'=>$reviews['block_id'],
		'lid'=>$_SESSION['language']
	],
	'watch'=>[
		'versions'=>($reviews['block_id'] > 0 ? ['block:'.$reviews['block_id']] : []),
		'values'=>[],
		'files'=>array_values(array_filter(array_merge([__FILE__,$reviews['structure_file'],$reviews['instance']->dataFile(),$reviews['instance']->integrationsFile(),$reviews['instance']->providersFile()],$reviews['block_files'])))
	],
	'policy'=>['cacheable'=>($reviews['block_id'] > 0 && (!isset($service['cache']['policy']['cacheable']) || (int) $service['cache']['policy']['cacheable'] === 1)) ? 1 : 0],
	'meta'=>[]
];
$reviews['cache_entry'] = false;
if ($reviews['definition']['policy']['cacheable'] == 1) {
	$reviews['cache_entry'] = $_SERVER['CacheDirector']->entry($reviews['definition']);
	$reviews['cache_entry']->setDefinition($reviews['definition']);
	$reviews['cached'] = $reviews['cache_entry']->get();
	if ($reviews['cached'] !== false) {
		$service['content'] = $reviews['cached'];
		unset($reviews);
		return;
	}
}

$reviews['filter'] = [
	'limit'=>$reviews['limit'],
	'min_rating'=>$reviews['min_rating'],
	'featured'=>intval($reviews['block']['option_reviews_featured'] ?? 0),
	'language'=>$reviews['block']['option_reviews_language'] ?? ['all'],
	'provider'=>$reviews['block']['option_reviews_provider'] ?? ['all'],
	'sort'=>trim((string) ($reviews['block']['option_reviews_sort'] ?? 'featured')),
	'direction'=>trim((string) ($reviews['block']['option_reviews_sort_dir'] ?? 'DESC'))
];
$reviews['rows'] = $reviews['show_items'] == 1 ? $reviews['instance']->widget($reviews['filter'],$_SESSION['language']) : [];
$reviews['summary_rows'] = $reviews['summary_mode'] != 'none' ? $reviews['instance']->summaryRows($reviews['filter'],$_SESSION['language']) : [];

foreach ($reviews['rows'] as $reviews['row']) {
	$reviews['rating'] = max(1,min(5,intval($reviews['row']['rating'] ?? 0)));
	$reviews['provider_logo'] = $reviews['instance']->getProviderLogo($reviews['row']['provider'] ?? '');
	$reviews['item'] = [
		'id'=>htmlspecialchars(trim((string) ($reviews['row']['id'] ?? '')),ENT_QUOTES,'UTF-8'),
		'author'=>htmlspecialchars(trim((string) ($reviews['row']['author'] ?? '')),ENT_QUOTES,'UTF-8'),
		'author_initials'=>htmlspecialchars(trim((string) ($reviews['row']['author_initials'] ?? '')),ENT_QUOTES,'UTF-8'),
		'source'=>htmlspecialchars(trim((string) ($reviews['row']['source'] ?? '')),ENT_QUOTES,'UTF-8'),
		'text'=>nl2br(htmlspecialchars(trim((string) ($reviews['row']['text'] ?? '')),ENT_QUOTES,'UTF-8')),
		'rating'=>$reviews['rating'],
		'rating_value'=>$reviews['rating'],
		'rating_label'=>htmlspecialchars(language__get_parsed($_SESSION['language'],'_reviews_rating_label',['rating'=>$reviews['rating']]),ENT_QUOTES,'UTF-8'),
		'rating_stars'=>$reviews['uses_rating_stars'] ? str_repeat('★',$reviews['rating']) : '',
		'date'=>format__date_relative(intval($reviews['row']['date'] ?? $_SERVER['now']),'date',$_SESSION['language']),
		'datetime'=>date('Y-m-d',intval($reviews['row']['date'] ?? $_SERVER['now'])),
		'featured'=>intval($reviews['row']['featured'] ?? 0),
		'provider'=>htmlspecialchars(trim((string) ($reviews['row']['provider'] ?? 'local')),ENT_QUOTES,'UTF-8'),
		'provider_logo'=>htmlspecialchars($reviews['provider_logo'],ENT_QUOTES,'UTF-8'),
		'external_url'=>htmlspecialchars(trim((string) ($reviews['row']['external_url'] ?? '')),ENT_QUOTES,'UTF-8'),
		'has_author'=>trim((string) ($reviews['row']['author'] ?? '')) !== '' ? 1 : 0,
		'has_source'=>trim((string) ($reviews['row']['source'] ?? '')) !== '' ? 1 : 0,
		'has_provider_logo'=>$reviews['provider_logo'] != '' ? 1 : 0,
		'has_external_url'=>trim((string) ($reviews['row']['external_url'] ?? '')) !== '' ? 1 : 0,
		'render_rating'=>$reviews['options']['show_rating'],
		'render_source'=>($reviews['options']['show_source'] == 1 && $reviews['provider_logo'] == '' && trim((string) ($reviews['row']['source'] ?? '')) !== '') ? 1 : 0,
		'render_provider_logo'=>$reviews['provider_logo'] != '' ? 1 : 0,
		'render_date'=>$reviews['options']['show_date']
	];
	if ($reviews['selected'] != 'summary' && $reviews['structure'][$reviews['selected']] !== '') {
		$reviews['line'] = $reviews['structure'][$reviews['selected']];
		$reviews['items'][] = parser__replace($reviews['line'],$reviews['item']);
	}
}

if (count($reviews['rows']) > 0 || count($reviews['summary_rows']) > 0) {
	if ($reviews['summary_mode'] == 'global' && $reviews['structure']['summary'] !== '') {
		$reviews['summary'] = $reviews['instance']->summary($reviews['summary_rows'],$_SESSION['language']);
		$reviews['summary']['rating_value'] = max(0,min(5,intval(round($reviews['summary']['rating_average']))));
		$reviews['provider_logo'] = '';
		$reviews['line'] = $reviews['structure']['summary'];
		$reviews['summary_items'][] = parser__replace($reviews['line'],[
			'count'=>$reviews['summary']['count'],
			'average'=>htmlspecialchars($reviews['summary']['rating_average_label'],ENT_QUOTES,'UTF-8'),
			'average_label'=>htmlspecialchars($reviews['summary']['rating_label'],ENT_QUOTES,'UTF-8'),
			'summary_label'=>htmlspecialchars(language__get($_SESSION['language'],'_reviews_widget_summary_global_label'),ENT_QUOTES,'UTF-8'),
			'summary_rating_value'=>$reviews['summary']['rating_value'],
			'summary_rating_stars'=>htmlspecialchars($reviews['summary']['rating_stars'],ENT_QUOTES,'UTF-8'),
			'rating_1_count'=>$reviews['summary']['rating_1_count'],
			'rating_2_count'=>$reviews['summary']['rating_2_count'],
			'rating_3_count'=>$reviews['summary']['rating_3_count'],
			'rating_4_count'=>$reviews['summary']['rating_4_count'],
			'rating_5_count'=>$reviews['summary']['rating_5_count'],
			'provider'=>'all',
			'provider_logo'=>'',
			'has_provider_logo'=>0
		]);
	} else if ($reviews['summary_mode'] == 'provider' && $reviews['structure']['summary'] !== '') {
		$reviews['summary_groups'] = [];
		$reviews['providers'] = $reviews['instance']->providers();
		foreach ($reviews['summary_rows'] as $reviews['summary_row']) $reviews['summary_groups'][$reviews['summary_row']['provider']][] = $reviews['summary_row'];
		foreach ($reviews['summary_groups'] as $reviews['provider'] => $reviews['summary_group']) {
			$reviews['summary'] = $reviews['instance']->summary($reviews['summary_group'],$_SESSION['language']);
			$reviews['summary']['rating_value'] = max(0,min(5,intval(round($reviews['summary']['rating_average']))));
			$reviews['provider_logo'] = $reviews['instance']->getProviderLogo($reviews['provider']);
			$reviews['provider_name'] = $reviews['providers'][$reviews['provider']]['name'] ?? ucfirst($reviews['provider']);
			$reviews['line'] = $reviews['structure']['summary'];
			$reviews['summary_items'][] = parser__replace($reviews['line'],[
				'count'=>$reviews['summary']['count'],
				'average'=>htmlspecialchars($reviews['summary']['rating_average_label'],ENT_QUOTES,'UTF-8'),
				'average_label'=>htmlspecialchars($reviews['summary']['rating_label'],ENT_QUOTES,'UTF-8'),
				'summary_label'=>htmlspecialchars($reviews['provider_name'],ENT_QUOTES,'UTF-8'),
				'summary_rating_value'=>$reviews['summary']['rating_value'],
				'summary_rating_stars'=>htmlspecialchars($reviews['summary']['rating_stars'],ENT_QUOTES,'UTF-8'),
				'rating_1_count'=>$reviews['summary']['rating_1_count'],
				'rating_2_count'=>$reviews['summary']['rating_2_count'],
				'rating_3_count'=>$reviews['summary']['rating_3_count'],
				'rating_4_count'=>$reviews['summary']['rating_4_count'],
				'rating_5_count'=>$reviews['summary']['rating_5_count'],
				'provider'=>htmlspecialchars($reviews['provider'],ENT_QUOTES,'UTF-8'),
				'provider_logo'=>htmlspecialchars($reviews['provider_logo'],ENT_QUOTES,'UTF-8'),
				'has_provider_logo'=>$reviews['provider_logo'] != '' ? 1 : 0
			]);
		}
	}
	$reviews['summary_base'] = count($reviews['summary_rows']) > 0 ? $reviews['summary_rows'] : $reviews['rows'];
	$reviews['summary'] = $reviews['instance']->summary($reviews['summary_base'],$_SESSION['language']);
	$reviews['summary']['rating_value'] = max(0,min(5,intval(round($reviews['summary']['rating_average']))));
	$reviews['replace'] = array_merge($reviews['replace'],[
		'count'=>$reviews['show_items'] == 1 ? count($reviews['rows']) : count($reviews['summary_rows']),
		'layout'=>$reviews['selected'],
		'average'=>htmlspecialchars($reviews['summary']['rating_average_label'],ENT_QUOTES,'UTF-8'),
		'average_label'=>htmlspecialchars($reviews['summary']['rating_label'],ENT_QUOTES,'UTF-8'),
		'summary_rating_value'=>$reviews['summary']['rating_value'],
		'summary_rating_stars'=>htmlspecialchars($reviews['summary']['rating_stars'],ENT_QUOTES,'UTF-8'),
		'rating_1_count'=>$reviews['summary']['rating_1_count'],
		'rating_2_count'=>$reviews['summary']['rating_2_count'],
		'rating_3_count'=>$reviews['summary']['rating_3_count'],
		'rating_4_count'=>$reviews['summary']['rating_4_count'],
		'rating_5_count'=>$reviews['summary']['rating_5_count'],
		'summary'=>implode('',$reviews['summary_items']),
		'render_summary'=>count($reviews['summary_items']) > 0 ? 1 : 0,
		'render_items'=>$reviews['show_items'] == 1 && count($reviews['items']) > 0 ? 1 : 0,
		'render_'.$reviews['selected']=>$reviews['show_items'] == 1 && count($reviews['items']) > 0 ? 1 : 0
	]);
	$reviews['replace']['items'] = implode('',$reviews['items']);
	$reviews['replace'][$reviews['selected']] = $reviews['replace']['items'];
	$reviews['frame'] = $reviews['structure']['frame'];
	$reviews['content'] = parser__replace($reviews['frame'],$reviews['replace']);
}
$service['content'] = $reviews['content'];
if ($reviews['cache_entry']) $reviews['cache_entry']->set($service['content'],$reviews['definition']['meta']);

unset($reviews);
