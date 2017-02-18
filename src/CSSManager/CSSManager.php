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
	private $cssContent = null;

	/**
	 * @var string
	 */
	private $jsContent = null;

	/**
	 * @var int
	 */
	private $current_cache_timestamp = 0;

	/**
	 * @var array
	 */
	private $array_css_files = [];

	/**
	 * @var array
	 */
	private $array_js_files = [];

	/**
	 * @var string
	 */
	public static $CSS_DESTINATION_PATH = 'public/main.all.css';
	public static $JS_DESTINATION_PATH = 'public/main.all.js';

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
	 * Put CSS content (param) in one CSS file
	 * @param $css
	 */
	private function addCssContent($css) {
		$this->cssContent .= $css;
	}

	/**
	 * Put JS content (param) in one JS file
	 * @param $js
	 */
	private function addJsContent($js) {
		$this->jsContent .= $js;
	}

	private function clearJsContent() {
		$this->jsContent = '';
	}

	private function clearCssContent() {
		$this->cssContent = '';
	}

	/**
	 * Add CSS file to array
	 * @param string $css_file
	 */
	private function addCssFile($css_file) {
		array_push($this->array_css_files, $css_file);
		$this->deduplicate();
	}

	/**
	 * Add JS file to array
	 * @param string $js_file
	 */
	private function addJsFile($js_file) {
		array_push($this->array_js_files, $js_file);
		$this->deduplicate();
	}

	/**
	 * List array of file and deduplicate
	 */
	private function deduplicate() {
		$array_temp_css = [];
		foreach ($this->array_css_files as $file) {
			if(!in_array($file, $array_temp_css))
				$array_temp_css[] = $file;
		}
		$this->array_css_files = $array_temp_css;
		unset($array_temp_css);

		$array_temp_js = [];
		foreach ($this->array_js_files as $file) {
			if(!in_array($file, $array_temp_js)) {
				$array_temp_js[] = $file;
			}
		}
		$this->array_js_files = $array_temp_js;
		unset($array_temp_js);
	}

	/**
	 * Add Kant interface to array
	 * @param IKant $kant
	 * @throws \Exception
	 */
	private function addKant(IKant $kant) {
		$array_css_kant = $kant->css();
		$array_js_kant = $kant->js();

		if(!is_array($array_css_kant)) {
			throw new \Exception('IKant->css() must return an array');
		}

		if(!is_array($array_js_kant)) {
			throw new \Exception('IKant->js() must return an array');
		}

		foreach ($array_css_kant as $file) {
			$this->addCssFile($file);
		}

		foreach ($array_js_kant as $file) {
			$this->addJsFile($file);
		}
	}

	/**
	 * Load JS and CSS files
	 * @throws \Exception
	 */
	private function load() {
		$this->loadCSS();
		$this->loadJS();
	}

	/**
	 * Load file and write css in new file
	 */
	private function loadCSS() {

		if(!$this->isCacheActive()) {
			$this->clearCssContent();
			foreach ($this->array_css_files as $file) {

				if(strpos($file, $this->root_path) !== false)
					$current_file = $file;
				else
					$current_file = $this->root_path.$file;

				if(!file_exists($current_file))
					throw new \Exception('This file does not exists ('.$current_file.')');

				$this->addCssContent(file_get_contents($current_file));
			}

			file_put_contents($this->root_path.self::$CSS_DESTINATION_PATH, $this->cssContent);

			$this->saveCache();
		}
	}

	/**
	 * Load file and write js in new file
	 */
	private function loadJS() {

		if(!$this->isCacheActive()) {
			$this->clearJsContent();
			foreach ($this->array_js_files as $file) {

				if(strpos($file, $this->root_path) !== false)
					$current_file = $file;
				else
					$current_file = $this->root_path.$file;

				if(!file_exists($current_file))
					throw new \Exception('This file does not exists ('.$current_file.')');

				$this->addJsContent(file_get_contents($current_file));
			}

			file_put_contents($this->root_path.self::$JS_DESTINATION_PATH, $this->jsContent);

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
				'css' => [
					"url" => $this->root_path.self::$CSS_DESTINATION_PATH,
					"timestamp" => time()+self::$CACHE_TIMESTAMP
				],
				'js' => [
					"url" => $this->root_path.self::$JS_DESTINATION_PATH,
					"timestamp" => time()+self::$CACHE_TIMESTAMP
				]
			]));
		}
	}

	/**
	 * Return url of main CSS files
	 * @return string
	 */
	public function getURLs() {
		return [
			'css' => $this->root_url.self::$CSS_DESTINATION_PATH,
			'js' => $this->root_url.self::$JS_DESTINATION_PATH
		];
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
		$instance->addKant($kant);
		$instance->load();
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
	public static function addCss($array_css_files) {
		$instance = self::getInstance();

		foreach ($array_css_files as $file)
			$instance->addCssFile($file);

		$instance->loadCSS();
	}

	/**
	 * Add array of JS file to Manager
	 * @param $array_js_files
	 */
	public static function addJs($array_js_files) {
		$instance = self::getInstance();

		foreach ($array_js_files as $file)
			$instance->addJsFile($file);

		$instance->loadJS();
	}

	/**
	 * Include links CSS and JS
	 */
	public static function link() {
		$urls = self::$instance->getURLs();
		echo '<link rel="stylesheet" href="'.$urls['css'].'?'.self::$instance->getCurrentCacheTimestamp().'" />';
		echo '<script type="text/javascript" src="'.$urls['js'].'?'.self::$instance->getCurrentCacheTimestamp().'"></script>';
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
