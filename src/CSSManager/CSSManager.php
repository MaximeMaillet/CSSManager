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
	/**
	 * @var CSSManager|null
	 */
	private static $instance = null;

	/**
	 * @var string
	 */
	private $root_path;

	/**
	 * @var string
	 */
	private $root_url;

	/**
	 * @var string
	 */
	private $cssContent;

	/**
	 * @var int
	 */
	private $current_cache_timestamp = 0;

	/**
	 * @var array
	 */
	private $array_css_files = [];

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

	/**
	 * CSSManager constructor.
	 */
	private function __construct() {
		$this->root_path = dirname($_SERVER['SCRIPT_FILENAME']).'/';
		$this->root_url = dirname($_SERVER['SCRIPT_NAME']).'/';

		if(in_array(PHP_OS, ['WIN', 'WINNT'])) {
			$this->root_path = str_replace('/', '\\', $this->root_path);
		}

		if(!file_exists($this->root_path.'public/')) {
			if(!mkdir($this->root_path.'public/')) {
				throw new \Exception('Unable to create directory, check permissions');
			}
		}
	}

	/**
	 * Add CSS file to array
	 * @param string $css_file
	 */
	private function addFile($css_file) {
		array_push($this->array_css_files, $css_file);
	}

	/**
	 * Add Kant interface to array
	 * @param IKant $kant
	 */
	private function addKant(IKant $kant) {
		$array_kants = $kant->get();

		foreach ($array_kants as $file)
			$this->addCssContent(file_get_contents($file));
	}

	/**
	 * Put CSS content (param) with other CSS
	 * @param $css
	 */
	private function addCssContent($css) {
		$this->cssContent .= $css;
	}

	/**
	 * Load file and write css in new file
	 */
	private function loadCSS() {

		if($this->isCacheActive()) {
			foreach ($this->array_css_files as $file) {
				if(strpos($file, $this->root_path) !== false) {
					$this->addCssContent(file_get_contents($file));
				}
				else {
					$this->addCssContent(file_get_contents($this->root_path.$file));
				}
			}

			file_put_contents($this->root_path.self::$CSS_DESTINATION_PATH, $this->cssContent);

			$this->saveCache();
		}
	}

	/**
	 * Define if cache is available or not
	 * @return bool
	 */
	private function isCacheActive() {
		if(self::$ENVIRONMENT !== null  && strtolower(self::$ENVIRONMENT) == 'dev')
			return false;

		if(self::$ENVIRONMENT === null && defined('ENVIRONMENT') && strtolower(ENVIRONMENT) == 'dev')
			return false;

		$cache_file = dirname(__DIR__).'/cache.json';
		if(file_exists($cache_file)) {
			$cache = json_decode(file_get_contents($cache_file), true);

			if (array_key_exists('timestamp', $cache))
				$this->current_cache_timestamp = $cache['timestamp'];

			if ($this->current_cache_timestamp <= time())
				return false;

			if (array_key_exists('url', $cache) && !file_exists($cache['url']))
				return false;
		}

		return true;
	}

	/**
	 * Save file in cache
	 */
	private function saveCache() {
		$cache_file = dirname(__DIR__).'/cache.json';
		if(!file_exists($cache_file)) {
			file_put_contents($cache_file, json_encode([
				"url" => $this->root_path.self::$CSS_DESTINATION_PATH,
				"timestamp" => time()+self::$CACHE_TIMESTAMP
			]));
		}
	}

	/**
	 * Return url of main CSS files
	 * @return string
	 */
	public function getURL() {
		return $this->root_url.self::$CSS_DESTINATION_PATH;
	}

	/**
	 * Return cache's timestamp
	 * @return int
	 */
	public function getCurrentCacheTimestamp() {
		return $this->current_cache_timestamp;
	}


	/**
	 * Return instance of CSSManager
	 * @return CSSManager|null
	 */
	private static function getInstance() {
		if(self::$instance === null)
			self::$instance = new CSSManager();
		return self::$instance;
	}

	/**
	 * Import Kant Library
	 * @param IKant $kant
	 * @throws \Exception
	 */
	public static function import(IKant $kant) {
		$instance = self::getInstance();
		$array_kant = $kant->get();

		if(!is_array($array_kant)) {
			throw new \Exception('IKant->get() must return an array');
		}

		foreach ($array_kant as $file)
			$instance->addFile($file);
	}


	public static function importMultiple($array_kants) {
		$instance = self::getInstance();

		foreach ($array_kants as $kant) {
			if(!($kant instanceof IKant))
				throw new \Exception(get_class($kant).' not implemented by IKant');
			$array_kant = $kant->get();
			if(!is_array($array_kant)) {
				throw new \Exception('IKant->get() must return an array');
			}

			foreach ($array_kant as $file)
				$instance->addFile($file);
		}
	}

	/**
	 * Add array of CSS file to Manager
	 * @param $array_css_files
	 */
	public static function add($array_css_files) {
		$instance = self::getInstance();

		foreach ($array_css_files as $file)
			$instance->addFile($file);
		$instance->loadCSS();
	}

	/**
	 * Include links CSS and JS
	 */
	public static function link() {
		echo '<link rel="stylesheet" href="'.self::$instance->getURL().'?'.self::$instance->getCurrentCacheTimestamp().'" />';
	}

	/**
	 * Method for dump cache
	 * @throws \Exception
	 */
	public static function dumpCache() {
		$cache_file = dirname(__DIR__).'/cache.json';
		if(file_exists($cache_file) && !unlink($cache_file)) {
			throw new \Exception('Unable to remove cache file, check write permissions');
		}
	}
}
