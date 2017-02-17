<?php

namespace M2Max\CSSManager;

/**
 * Created by PhpStorm.
 * User: Maxime Maillet
 * Date: 17/02/2017
 * Time: 00:10
 */
 class CSSManager
 {
 	private static $instance = null;

 	private $root_path;
 	private $root_url;
 	private $cssContent;
 	private $current_cache_timestamp = 0;
	private $array_css_files;

 	/**
 	* @var string
 	*/
 	public static $CSS_DESTINATION_PATH = 'public/main.all.css';

 	/**
 	* 60 days
 	* @var init
 	*/
 	public static $CACHE_TIMESTAMP = 60*60*24*60;

	/**
	* @var string
	*/
	public static $ENVIRONMENT = null;

 	private function __construct($array_css_files) {
 		$this->root_path = dirname($_SERVER['SCRIPT_FILENAME']).'/';
 		$this->root_url = dirname($_SERVER['SCRIPT_NAME']).'/';
		$this->array_css_files = $array_css_files;
			if(!$this->cacheActive()) {
	 			$this->loadLocal();
				$this->activeCache();
	 		}
 	}

	private function loadLocal() {

		if(in_array(PHP_OS, ['WIN', 'WINNT'])) {
			$this->root_path = str_replace('/', '\\', $this->root_path);
		}

		foreach ($this->array_css_files as $file) {
			if(is_string($file)) {
				if(strpos($file, $this->root_path) !== false) {
					$this->addCssContent(file_get_contents($file));
				}
				else {
					$this->addCssContent(file_get_contents($this->root_path.$file));
				}
			}
		}

		if(!file_exists($this->root_path.'public/')) {
			if(!mkdir($this->root_path.'public/')) {
				throw new \Exception('Unable to create directory, check permissions');
			}
		}

		file_put_contents($this->root_path.self::$CSS_DESTINATION_PATH, $this->cssContent);
	}

 	private function activeCache() {
 		$cache_file = dirname(__DIR__).'/cache.json';
 		if(!file_exists($cache_file)) {
 			file_put_contents($cache_file, json_encode([
 				"url" => $this->root_path.self::$CSS_DESTINATION_PATH,
 				"timestamp" => time()+self::$CACHE_TIMESTAMP
 			]));
 		}
 	}

 	private function cacheActive() {
		if(self::$ENVIRONMENT !== null  && strtolower(self::$ENVIRONMENT) == 'dev') {
			return false;
		}
		elseif(self::$ENVIRONMENT === null && defined('ENVIRONMENT') && strtolower(ENVIRONMENT) == 'dev') {
			return false;
		}

 		$cache_file = dirname(__DIR__).'/cache.json';
 		if(file_exists($cache_file)) {
 			$cache = json_decode(file_get_contents($cache_file), true);
 			if(array_key_exists('timestamp', $cache))
 				$this->current_cache_timestamp = $cache['timestamp'];

 			if(array_key_exists('url', $cache) && file_exists($cache['url']))
 				return true;
 		}

 		return false;
 	}

 	private function addCssContent($css) {
 		$this->cssContent .= $css;
 	}

 	public function getURL() {
 		return $this->root_url.self::$CSS_DESTINATION_PATH;
 	}

 	public function getCurrentCacheTimestamp() {
 		return $this->current_cache_timestamp;
 	}

 	public static function init($array_css_files) {
 		if(self::$instance == null)
 			self::$instance = new CSSManager($array_css_files);
 	}

 	public static function link() {
 		echo '<link rel="stylesheet" href="'.self::$instance->getURL().'?'.self::$instance->getCurrentCacheTimestamp().'" />';
 	}

 	public static function dumpCache() {
 		$cache_file = dirname(__DIR__).'/cache.json';
 		if(file_exists($cache_file) && !unlink($cache_file)) {
 			throw new \Exception('Unable to remove cache file, check write permissions');
 		}
 	}

	public static function picture($path) {
		if(self::$instance == null)
			throw new \Exception('CSSManager are not instanciated');

			echo self::$instance->root_url.$path;
	}

	public static function importBefore(IKant $kant) {
		//var_dump($kant);
	}

	public static function importAfter(IKant $kant) {
		if(self::$instance == null)
			throw new \Exception('CSSManager are not instanciated');

		self::$instance->addKant($kant);
		self::$instance->loadLocal();
	}
 }
