<?php

class FiCMSReviewsProviderRegistry {
	private $reviews;
	private $basePath = '';
	private $classes = [];
	private $instances = [];

	public function __construct($reviews, $basePath) {
		$this->reviews = $reviews;
		$this->basePath = rtrim((string) $basePath,'/');
		$this->classes = $this->loadClasses();
	}

	public function instance($provider) {
		$provider = FiCMSReviewsNormalizer::validProviderKey($provider) ? trim((string) $provider) : '';
		if ($provider == '' || !isset($this->classes[$provider])) return false;
		if (!isset($this->instances[$provider])) {
			$class = $this->classes[$provider];
			$this->instances[$provider] = new $class($this->reviews);
		}
		return $this->instances[$provider];
	}

	public function definitions() {
		$definitions = [];
		foreach ($this->classes as $key => $class) $definitions[$key] = $class::definition();
		return $definitions;
	}

	public function defaultProvider() {
		$providers = array_keys($this->classes);
		return $providers[0] ?? 'provider';
	}

	public function logo($provider = '') {
		$provider = preg_replace('/[^a-z0-9_]/i','',trim((string) $provider));
		if ($provider == '') return '';
		foreach (['svg','png','webp'] as $extension) {
			$file = $this->basePath.'/assets/img/providers/'.$provider.'.'.$extension;
			$path = (defined('PLUGINPATH') ? PLUGINPATH.'/'.basename($this->basePath) : trim($this->basePath,'/')).'/assets/img/providers/'.$provider.'.'.$extension;
			if (is_file($file)) return rtrim(PAGEPATH,'/').'/'.$path.'?v='.filemtime($file);
		}
		return '';
	}

	private function loadClasses() {
		$providers = [];
		foreach (get_declared_classes() as $class) {
			if (!is_subclass_of($class,'FiCMSReviewsProvider')) continue;
			$key = trim((string) $class::key());
			if (!FiCMSReviewsNormalizer::validProviderKey($key)) continue;
			$providers[$key] = $class;
		}
		ksort($providers);
		return $providers;
	}
}
