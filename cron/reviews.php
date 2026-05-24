<?php

if (!$site['onsite']) return;

require_once dirname(__DIR__).'/src/Reviews.php';

$reviews = [
	'instance'=>new FiCMSReviews(dirname(__DIR__),$site['default_language'],$site['installed_languages']),
	'result'=>[]
];

$reviews['result'] = $reviews['instance']->cron();

unset($reviews);
