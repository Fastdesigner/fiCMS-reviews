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
	'sorts'=>['featured','date','rating'],
	'fields'=>[
		'widgetnum'=>['type'=>'number','default'=>6,'dynamic_name'=>'_reviews_widget_limit','attributes'=>['min'=>1,'max'=>24]],
		'widgetvalue'=>['type'=>'select','default'=>1,'dynamic_name'=>'_reviews_widget_min_rating','options'=>'ratings'],
		'reviews_layout'=>['type'=>'datalist','default'=>'list','dynamic_name'=>'_reviews_widget_layout','attributes'=>['data-list'=>'reviews-layouts','data-exact'=>'true']],
		'reviews_sort'=>['type'=>'toggle','default'=>'featured','dynamic_name'=>'_reviews_widget_sort','direction'=>'reviews_sort_dir','direction_default'=>'DESC','options'=>'sorts'],
		'reviews_featured'=>['type'=>'checkbox','default'=>0,'dynamic_name'=>'_reviews_widget_featured'],
		'reviews_language'=>['type'=>'multipicker','default'=>['all'],'dynamic_name'=>'_reviews_widget_language','attributes'=>['data-list'=>'installed-languages','data-all'=>'true','data-exact'=>'true']],
		'reviews_provider'=>['type'=>'multipicker','default'=>['all'],'dynamic_name'=>'_reviews_widget_provider','attributes'=>['data-list'=>'reviews-providers','data-all'=>'true','data-exact'=>'true']],
		'show_rating'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_rating'],
		'show_source'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_source'],
		'show_date'=>['type'=>'checkbox','default'=>1,'dynamic_name'=>'_reviews_widget_show_date']
	]
];

$reviews_options['language'] = $GLOBALS['user']['language'];
$reviews_options['instance'] = new FiCMSReviews(dirname(__DIR__,2),$GLOBALS['site']['default_language'],$GLOBALS['site']['installed_languages']);
foreach ([1,2,3,4,5] as $reviews_options['rating']) $reviews_options['ratings'][$reviews_options['rating']] = ['value'=>$reviews_options['rating'],'name'=>language__get_parsed($reviews_options['language'],'_reviews_rating_option',['rating'=>$reviews_options['rating']])];
foreach ($reviews_options['sorts'] as $reviews_options['sort']) $reviews_options['sort_options'][$reviews_options['sort']] = ['value'=>$reviews_options['sort'],'name'=>language__get($reviews_options['language'],'_reviews_widget_sort_'.$reviews_options['sort'])];
$reviews_options['datalists']['reviews-providers'] = ['all'=>['name'=>language__get($reviews_options['language'],'_sort_all'),'value'=>'all']];
foreach ($reviews_options['instance']->providers() as $reviews_options['provider']) $reviews_options['datalists']['reviews-providers'][$reviews_options['provider']['value']] = $reviews_options['provider'];

$reviews_options['structure_file'] = widgets__layout_file('review');
if ($reviews_options['structure_file'] == '') $reviews_options['structure_file'] = widgets__layout_file('reviews');
if ($reviews_options['structure_file'] != '') {
	$reviews_options['structure'] = parser__file($reviews_options['structure_file']);
	foreach ($reviews_options['structure'] as $reviews_options['key'] => $reviews_options['value']) {
		if (in_array($reviews_options['key'],['frame','origin'],true)) continue;
		$reviews_options['layouts'][$reviews_options['key']] = ['name'=>language__get($reviews_options['language'],'_reviews_widget_layout_'.$reviews_options['key']),'value'=>$reviews_options['key']];
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
	if (($reviews_options['field']['options'] ?? '') == 'sorts') $reviews_options['options'][$reviews_options['key']]['options'] = $reviews_options['sort_options'];
}

return ['options'=>$reviews_options['options'],'values'=>$reviews_options['values'],'datalists'=>$reviews_options['datalists']];
