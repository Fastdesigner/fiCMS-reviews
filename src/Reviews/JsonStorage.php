<?php

class FiCMSReviewsJsonStorage {
	public static function write($file, $data) {
		if (!is_dir(dirname($file))) mkdir(dirname($file),0775,true);
		return file_put_contents($file,json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),LOCK_EX) !== false;
	}
}
