<?php

if (!isset($block)) return [];

$reviews_options = [
	'options'=>[],
	'values'=>[],
	'dependencies'=>['widget'=>['enable'=>['reviews'],'disable'=>[]]],
	'ratings'=>[]
];

foreach ([1,2,3,4,5] as $reviews_options['rating']) $reviews_options['ratings'][$reviews_options['rating']] = ['value'=>$reviews_options['rating'],'name'=>language__get_parsed($GLOBALS['user']['language'] ?? $_SESSION['language'],'_reviews_rating_option',['rating'=>$reviews_options['rating']])];

$reviews_options['options']['widgetnum'] = [
	'type'=>'number',
	'default'=>6,
	'name'=>language__get($GLOBALS['user']['language'] ?? $_SESSION['language'],'_reviews_widget_limit'),
	'option'=>'widgetnum',
	'include'=>true,
	'dependencies'=>$reviews_options['dependencies'],
	'attributes'=>['min'=>1,'max'=>24]
];
$reviews_options['options']['widgetvalue'] = [
	'type'=>'select',
	'default'=>1,
	'name'=>language__get($GLOBALS['user']['language'] ?? $_SESSION['language'],'_reviews_widget_min_rating'),
	'option'=>'widgetvalue',
	'include'=>true,
	'dependencies'=>$reviews_options['dependencies'],
	'options'=>$reviews_options['ratings']
];

return ['options'=>$reviews_options['options'],'values'=>$reviews_options['values']];
