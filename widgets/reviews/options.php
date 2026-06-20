<?php

if (!isset($block)) return [];

require_once dirname(__DIR__,2).'/src/Reviews.php';

$reviews_options = [
	'options'=>[],
	'values'=>[],
	'datalists'=>[],
	'dependencies'=>['widget'=>['enable'=>['reviews','review'],'disable'=>[]]],
	'ratings'=>[],
	'layouts'=>['renew'=>true],
	'summary_modes'=>[],
	'sorts'=>['featured','date','rating'],
	'fields'=>[
		'reviews_summary_mode'=>['type'=>'select','default'=>'none','dynamic_name'=>'_reviews_widget_summary_mode','options'=>'summary_modes'],
		'reviews_show_items'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_items'],
		'reviews_language'=>['type'=>'multipicker','default'=>['all'],'dynamic_name'=>'_reviews_widget_language','attributes'=>['data-list'=>'installed-languages','data-all'=>'true','data-exact'=>'true']],
		'reviews_provider'=>['type'=>'multipicker','default'=>['all'],'dynamic_name'=>'_reviews_widget_provider','attributes'=>['data-list'=>'reviews-providers','data-all'=>'true','data-exact'=>'true']],
		'reviews_layout'=>['type'=>'datalist','default'=>'list','dynamic_name'=>'_reviews_widget_layout','attributes'=>['data-list'=>'reviews-layouts','data-exact'=>'true'],'dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'widgetnum'=>['type'=>'number','default'=>6,'dynamic_name'=>'_reviews_widget_limit','attributes'=>['min'=>1,'max'=>24],'dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'widgetvalue'=>['type'=>'select','default'=>1,'dynamic_name'=>'_reviews_widget_min_rating','options'=>'ratings','dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'reviews_sort'=>['type'=>'toggle','default'=>'featured','dynamic_name'=>'_reviews_widget_sort','direction'=>'reviews_sort_dir','direction_default'=>'DESC','options'=>'sorts','dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'reviews_featured'=>['type'=>'checkbox','default'=>0,'dynamic_name'=>'_reviews_widget_featured','dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'show_rating'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_rating','dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'show_source'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_source','dependencies'=>['reviews_show_items'=>['enable'=>['1']]]],
		'show_date'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_date','dependencies'=>['reviews_show_items'=>['enable'=>['1']]]]
	]
];

$reviews_options['language'] = $GLOBALS['user']['language'];
$reviews_options['instance'] = new FiCMSReviews(dirname(__DIR__,2),$GLOBALS['site']['default_language'],$GLOBALS['site']['installed_languages']);
foreach ([1,2,3,4,5] as $reviews_options['rating']) $reviews_options['ratings'][$reviews_options['rating']] = ['value'=>$reviews_options['rating'],'name'=>language__get_parsed($reviews_options['language'],'_reviews_rating_option',['rating'=>$reviews_options['rating']])];
foreach (['none','global','provider'] as $reviews_options['summary_mode']) $reviews_options['summary_modes'][$reviews_options['summary_mode']] = ['value'=>$reviews_options['summary_mode'],'name'=>language__get($reviews_options['language'],'_reviews_widget_summary_'.$reviews_options['summary_mode'])];
foreach ($reviews_options['sorts'] as $reviews_options['sort']) $reviews_options['sort_options'][$reviews_options['sort']] = ['value'=>$reviews_options['sort'],'name'=>language__get($reviews_options['language'],'_reviews_widget_sort_'.$reviews_options['sort'])];
$reviews_options['datalists']['reviews-providers'] = ['all'=>['name'=>language__get($reviews_options['language'],'_sort_all'),'value'=>'all']];
foreach ($reviews_options['instance']->providers() as $reviews_options['provider']) $reviews_options['datalists']['reviews-providers'][$reviews_options['provider']['value']] = $reviews_options['provider'];

$reviews_options['layout_dirs'] = [
	__DIR__.'/blocks',
	DESIGNPATH.'/widgets/review/blocks',
	DESIGNPATH.'/widgets/reviews/blocks'
];
foreach ($reviews_options['layout_dirs'] as $reviews_options['layout_dir']) {
	foreach (glob($reviews_options['layout_dir'].'/*.html', GLOB_NOSORT) ?: [] as $reviews_options['layout_file']) {
		$reviews_options['layout_key'] = basename($reviews_options['layout_file'],'.html');
		if ($reviews_options['layout_key'] == '') continue;
		if ($reviews_options['layout_key'] == 'summary') continue;
		$reviews_options['layouts'][$reviews_options['layout_key']] = ['name'=>language__get($reviews_options['language'],'_reviews_widget_layout_'.$reviews_options['layout_key']),'value'=>$reviews_options['layout_key']];
	}
}
if (!isset($reviews_options['layouts']['list'])) $reviews_options['layouts']['list'] = ['name'=>language__get($reviews_options['language'],'_reviews_widget_layout_list'),'value'=>'list'];
$reviews_options['datalists']['reviews-layouts'] = $reviews_options['layouts'];

foreach ($reviews_options['fields'] as $reviews_options['key'] => $reviews_options['field']) {
	$reviews_options['options'][$reviews_options['key']] = [
		'type'=>$reviews_options['field']['type'],
		'default'=>$reviews_options['field']['default'],
		'dynamic_name'=>$reviews_options['field']['dynamic_name'],
		'name'=>language__get($reviews_options['language'],$reviews_options['field']['dynamic_name']),
		'option'=>$reviews_options['key'],
		'include'=>true,
		'dependencies'=>$reviews_options['dependencies']
	];
	foreach (['attributes','direction','direction_default'] as $reviews_options['property'])
		if (isset($reviews_options['field'][$reviews_options['property']])) $reviews_options['options'][$reviews_options['key']][$reviews_options['property']] = $reviews_options['field'][$reviews_options['property']];
	if (($reviews_options['field']['options'] ?? '') == 'ratings') $reviews_options['options'][$reviews_options['key']]['options'] = $reviews_options['ratings'];
	if (($reviews_options['field']['options'] ?? '') == 'summary_modes') $reviews_options['options'][$reviews_options['key']]['options'] = $reviews_options['summary_modes'];
	if (($reviews_options['field']['options'] ?? '') == 'sorts') $reviews_options['options'][$reviews_options['key']]['options'] = $reviews_options['sort_options'];
}

return ['options'=>$reviews_options['options'],'values'=>$reviews_options['values'],'datalists'=>$reviews_options['datalists']];
