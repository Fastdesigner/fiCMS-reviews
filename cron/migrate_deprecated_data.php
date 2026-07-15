<?php

if (!$site['onsite']) return;

// Einmalige Migration: der deprecated Settings-Screen schrieb bis zum PLUGINPATH-Fix nach deprecated/data.
// Das Script entfernt sich nach der Ausführung selbst; kommt es per Update erneut mit, läuft es einmal ins Leere.
$reviewsMigrate = ['legacy_dir'=>dirname(__DIR__).'/deprecated/data'];
if (is_dir($reviewsMigrate['legacy_dir'])) {
	foreach (['reviews.json','integrations.json','providers.json'] as $reviewsMigrate['file']) {
		$reviewsMigrate['source'] = $reviewsMigrate['legacy_dir'].'/'.$reviewsMigrate['file'];
		$reviewsMigrate['target'] = dirname(__DIR__).'/data/'.$reviewsMigrate['file'];
		if (!is_file($reviewsMigrate['source'])) continue;
		if (!is_file($reviewsMigrate['target']) || filesize($reviewsMigrate['target']) < 3) rename($reviewsMigrate['source'],$reviewsMigrate['target']);
		else unlink($reviewsMigrate['source']);
	}
	if (!count(glob($reviewsMigrate['legacy_dir'].'/*') ?: [])) rmdir($reviewsMigrate['legacy_dir']);
}
if (!is_dir($reviewsMigrate['legacy_dir'])) unlink(__FILE__);
unset($reviewsMigrate);
