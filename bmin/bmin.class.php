<?php

/**
 * BMin JS/CSS/LESS Compiler
 *
 * Copyright (C) 2013 Jukka Hankaniemi - Blowback.fi
 *
 * @version 1.0.2
 * @author Jukka Hankaniemi https://github.com/Roope
 * @copyright Blowback https://github.com/BlowbackDesign
 * @license MIT http://opensource.org/licenses/MIT
 */

class BMin {
	
	/**
	 * Configuration options
	 *
	 */
	protected $options = array(
		'live' => false, 
		'debug' => false, 
		'cache' => true, 
		'compress' => true, 
		'removenewlines' => true, 
		'expires' => 2592000, 
		'dateform' => 'd.m.Y H:i:s', 
		'version' => '',       // version name: [str] (a-z0-9) or `true` for auto timestamp
		'group' => 'main',     // default group name [str] (a-z0-9)
		'prefix' => 'bmin',    // prefix for compiled file name [str] (a-z0-9)
		'styles' => 'css/',    // root for compiled css files
		'scripts' => 'js/',    // root for compiled js files
		'files' => '',         // root for fileset files include path
		'root' => ''           // application root
	);
	
	/**
	 * Runtime properties
	 *
	 */
	protected $path;          // base path for this application
	protected $url;           // base url for generated `styles` and `scripts` files

	protected $time;          // time array for fileset modification times
	protected $time_start;    // time variable for bebug parse timing
	protected $time_mod;      // time variable to hold global modification time

	protected $fileset;       // array for default fileset
	protected $ending;        // array for filetype endings
	protected $debug;         // array for debug data
	
	
	/**
	 * Default constructor
	 *
	 */
	public function __construct($fileset=array(), $options=array()) {
		
		// set fileset
		if (count($fileset)) {
			$this->fileset['styles'] = isset($fileset['styles']) ? $fileset['styles'] : null; 
			$this->fileset['scripts'] = isset($fileset['scripts']) ? $fileset['scripts'] : null; 
		} else $this->fileset = null; 
		
		// set options
		if (count($options)) $this->set('options', $options); 
		
		// set root folder
		$root = empty($options['root']) ? dirname($_SERVER['SCRIPT_NAME']) : $options['root']; 
		
		// set paths
		$this->path = rtrim(dirname(__FILE__), '/') . '/'; 
		$this->url = '/' . ltrim('/' . trim($root, '/') . '/', '/'); 
		
		// set file endings
		$this->ending['styles'] = 'css'; 
		$this->ending['scripts'] = 'js'; 
		
		// get includes
		require_once $this->path . 'Compressor.php'; 
		require_once $this->path . 'JSMin.php'; 
		require_once $this->path . 'Less.php'; 
	}
	
	public function init() { 
	
	}
	
	/**
	 * Options setter
	 *
	 */
	public function set($key, $value) {
		if ($key == 'options' && is_array($value)) $this->options = array_merge($this->options, $value); 
		else if (array_key_exists($key, $this->options)) $this->options[$key] = $value; 
		else return parent::set($key, $value); 
		return $this; 
	}
	
	/**
	 * Get url
	 *
	 */
	protected function getUrl($type=null) {
		return $type ? $this->url . $this->options[$type] : $this->url; 
	}
	
	/**
	 * Get path
	 *
	 */
	protected function getPath($type=null) {
		return $_SERVER['DOCUMENT_ROOT'] . $this->getUrl($type); 
	}
	
	/**
	 * Get fileset path
	 *
	 */
	protected function getFilesetPath() {
		return $this->getPath() . $this->options['files']; 
	}
	
	/**
	 * Process styles
	 *
	 */
	public function styles($group=null, $fileset=array(), $options=array()) {
		try { 
			return $this->process('styles', $group, $fileset, $options); 
		} 
		catch(Exception $e) { 
			$this->debug['errors'][] = $e->getMessage(); 
		}
	}
	
	/**
	 * Process scripts
	 *
	 */
	public function scripts($group=null, $fileset=array(), $options=array()) {
		try { 
			return $this->process('scripts', $group, $fileset, $options); 
		}
		catch(Exception $e) { 
			$this->debug['errors'][] = $e->getMessage(); 
		}
	}
	
	/**
	 * Main processor
	 *
	 */
	protected function process($type, $group, $fileset, $options) {
		
		$cached = null;
						
		// use custom options if set 
		if (count($options)) {
			$default_options = $this->options; 
			$this->set('options', $options); 
		}
		
		// get file ending 
		$ending = $this->ending[$type]; 
		
		// get group name 
		$group = $this->groupName($group); 
		
		// get version name 
		$version = $this->versionName($this->options['version']); 
		
		// set file name and paths
		$name = $this->fileName($group, $ending, $version); 
		$file = $this->getPath($type) . $name; 
		$url = $this->getUrl($type) . $name; 
		
		if (!$this->options['live']) {
			
			// use custom fileset if set 
			$fileset = count($fileset) ? $fileset : $this->fileset[$type]; 

			// sanitize fileset array for this group 
			$fileset = $this->sanitizeFileset($fileset, $type, $group); 

			if (count($fileset)) {

				// set last modification time 
				$this->setTimeData($fileset, $group, $ending, $version); 

				// create compiled file 
				$cached = $this->cacheFile($fileset, $file, $group, $ending); 

			} else {

				// throw exception if fileset is empty 
				throw new Exception("Fileset not found. type:$type group:$group"); 

			}
		
		// quick exit on live mode 
		} else $cached = true; 
		
		// reset default options 
		if (count($options)) $this->options = $default_options; 
		
		// return file url 
		return ($cached) ? $url : false; 
	}
	
	/**
	 * Sanitize fileset array for single group namespace
	 *
	 */
	protected function sanitizeFileset($fileset, $type, $group) {
		$arr = null; 
		if (count($fileset)) {
			if (array_key_exists($type, $fileset)) {
				return $this->sanitizeFileset($fileset[$type], $type, $group);
			}
			if (array_key_exists('files', $fileset)) {
				if ($this->filesetHasFiles($fileset, $group)) $arr[] = $fileset;
			} else {
				foreach ($fileset as $fileset) {
					if (array_key_exists('files', $fileset)) {
						if ($this->filesetHasFiles($fileset, $group)) $arr[] = $fileset;
					}
				}
			}
		}
		return $arr;
	}
	
	/**
	 * Returns true if fileset has files for given group
	 *
	 */
	protected function filesetHasFiles($fileset, $group) {
		if (count($fileset['files'])) { 
			if (isset($fileset['group'])) { 
				return $this->groupName($fileset['group']) === $group ? true : false; 
			} else if ($this->groupName() === $group) return true; 
		}
		return false; 
	}
	
	/**
	 * Return / Sanitize group name
	 *
	 */
	protected function groupName($group=null) {
		$group = is_string($group) ? $group : $this->options['group']; 
		return $this->sanitize($group); 
	}
	
	/**
	 * Return / Sanitize version name
	 *
	 */
	protected function versionName($version=null) {
		$version = !empty($version) ? $version : $this->options['version']; 
		if ($version === true) return true; 
		else return $this->sanitize($version); 
	}
	
	/**
	 * Create file name
	 *
	 */
	protected function fileName($group, $ending, $version=null) { 
		$prefix = $this->sanitize($this->options['prefix']); 
		if ($version === true) $version = $this->time[$ending][$group]; 
		if (empty($version)) return "{$prefix}.{$group}.{$ending}"; 
		else return "{$prefix}.{$group}.{$version}.{$ending}"; 
	}
	
	/**
	 * Main sanitizer
	 *
	 */
	protected function sanitize($value, $replace='_', $keep=null) {
		if (!ctype_alnum($value)) { 
			$regex = 'a-zA-Z0-9'; 
			if (is_array($keep) && !empty($keep)) foreach ($keep as $char) $regex .= trim($char); 
			$value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH); 
			$value = preg_replace('/[^'.$regex.']/', $replace, $value); 
			$value = preg_replace('/'.$replace.'+/', $replace, $value); 
		}
		return strtolower(trim($value, $replace)); 
	}
	
	/**
	 * Get file extension
	 *
	 */
	protected function fileExtension($file) {
		return strtolower(pathinfo($file, PATHINFO_EXTENSION)); 
	}
	
	/**
	 * Set modification time data for fileset group
	 *
	 */
	protected function setTimeData($fileset, $group, $ending, $version) {
		
		// zero time data
		$this->time_mod = 0; 
		$this->time[$ending][$group] = 0; 
				
		// begin debug data
		if ($this->options['debug']) {
			$this->time_start = microtime(true); 
			$this->debug[$ending][$group] = array(); 
			$this->debug[$ending][$group]['version'] = $version; 
		}
		
		// loop each fileset
		foreach ($fileset as $set) {
			// loop each fileset files
			foreach ($set['files'] as $file) {
				// filename with path
				$file = $this->getFilesetPath() . $file; 
				// file is readable
				if (@is_file($file) && @is_readable($file)) {
					// file mod time
					$time = filemtime($file); 
					// update time data
					$this->time[$ending][$group] = $time > $this->time[$ending][$group] ? $time : $this->time[$ending][$group]; 
					$this->time_mod = $this->time[$ending][$group] > $this->time_mod ? $this->time[$ending][$group] : $this->time_mod; 
					// debug included files
					if ($this->options['debug']) {
						$this->debug[$ending][$group]['included']['dates'][] = filemtime($file); 
						$this->debug[$ending][$group]['included']['files'][] = $file; 
					}
					
				} 
				// debug failed files
				else if ($this->options['debug']) $this->debug[$ending][$group]['failed']['files'][] = $file; 
			}
		}
		
	}
	
	/**
	 * Create/Update cache file
	 *
	 */
	protected function cacheFile($fileset, $file, $group, $ending) {
		
		$cached = false; 
		$time = $this->time[$ending][$group]; 
		$isfile = is_file($file); 
		$filetime = $isfile ? filemtime($file) : 0; 
		
		// create new file if: cache disabled / file doesn't exist / is modified / is expired
		if (!$this->options['cache'] || !$isfile || $time > $filetime || time() - $filetime > $this->options['expires']) {
			$data = $this->processFileset($fileset, $ending);
			@file_put_contents($file, $data, LOCK_EX);
		} else $cached = true; 
		
		// save debug data
		if ($this->options['debug']) {
			$this->debug[$ending][$group]['cache'] = $cached;
		}
		
		// file is ok
		if ($this->time_mod > 0 && @is_readable($file)) {
			// save debug data
			if ($this->options['debug']) {
				$this->debug[$ending][$group]['time'] = microtime(true) - $this->time_start; 
				$this->debug[$ending][$group]['size'] = filesize($file); 
			}
			return true; 
		}
		
		return false;
	}
	
	/**
	 * Process fileset
	 *
	 */
	protected function processFileset($fileset, $ending) {
		
		$out = null;
		
		// loop each fileset 
		foreach ($fileset as $set) { 
			// settings for fileset 
			$compress = isset($set['compress']) ? $set['compress'] : $this->options['compress']; 
			$newlines = isset($set['removenewlines']) ? $set['removenewlines'] : $this->options['removenewlines']; 
			// compile files to string 
			$string = $this->processFilesetFiles($set['files'], $ending, $compress, $newlines); 
			// create css media queries if set 
			if (($ending === 'css') && isset($set['media']) && !empty($set['media'])) {
				// sanitize media name and update files string 
				$media = $this->sanitize($set['media'], ' ', array('(', ')', ':', ',', '-')); 
				$string = $compress || $newlines ? "@media $media { $string } " : "\n@media $media {\n$string\n} "; 
			}
			$out .= $string; 
		}
		
		return $out;
	}
	
	/**
	 * Process fileset files
	 *
	 */
	protected function processFilesetFiles($fileset, $ending, $compress, $newlines) {
		
		$out = null; 
		$compress = is_bool($compress) ? $compress : null; 
		$newlines = is_bool($newlines) ? $newlines : null; 
		
		// set lessc with css files
		$less = $ending === 'css' ? new Less_Parser : null; 
		
		// loop each file
		foreach ($fileset as $file) {
			// filename with path
			$file = $this->getFilesetPath() . $file; 
			// file is readable
			if (@file_exists($file) && @is_readable($file)) {
				// get less files
				if ($less && $this->fileExtension($file) === 'less') {
					$less->parseFile($file); 
					$out .= $less->getCss(); 
				}
				// get files without preprocessing
				else $out .= @file_get_contents($file); 
			}
		}
		
		// compress output
		if ($compress && $ending === 'js') $out = JSMin::minify($out); 
		if ($compress && $ending === 'css') $out = Minify_CSS_Compressor::process($out); 
		if ($compress && $newlines) $out = str_replace(array("\r\n","\r","\n"), " ", $out); 

		return $out;
	}
	
	/**
	 * Output debug data
	 *
	 */
	public function debug() {
		
		$out = null; 
		
		// debug if enabled and there is some data
		if ($this->options['debug'] && count($this->debug)) {
			
			$out = "\n<!--\n\n BMIN DEBUG";
			$out .= "\n Last Edit: " . date($this->options['dateform'], $this->time_mod) . "\n";
			
			foreach ($this->debug as $type => $data) {
				
				// display errors
				if ($type === 'errors' && count($data)) {
					foreach ($this->debug['errors'] as $e) { $out .= "\n {$e}"; }
					$out .= "\n"; 
				}
			
				// display filearray data
				else {
					$out .= "\n // {$type} files and sources\n"; 
					
					foreach ($data as $key => $value) {
						
						$cache = $value['cache'] ? 'Cached' : 'Processed'; 
						$file_name = $this->fileName($key, $type, $value['version']); 
						$size = isset($value['size']) ? $value['size'] / 1000 : 0; 
						$time = isset($value['time']) ? number_format($value['time'] * 1000, 12) : 0; 
						
						$out .= "\n {$cache}: {$file_name} | {$size} KB | {$time} ms"; 
						
						if (isset($value['included']) && count($value['included']['files'])) {
							foreach ($value['included']['files'] as $row => $file) {
								$out .= "\n ".date($this->options['dateform'], $value['included']['dates'][$row])." - {$file}";
							}
						}
						
						if (isset($value['failed']) && count($value['failed']['files'])) {
							foreach ($value['failed']['files'] as $file) {
								$out .= "\n File Not Found - {$file}";
							}
						}
						
						$out .= "\n";
					}
				}
				
			}
			
			$out .= "\n -->\n";
		}
		
		return $out;
	}
	
	/**
	 * Delete compiled files
	 *
	 */
	public function delete($group=null, $type=null) {
				
		// set file endings
		if ($type) $ending[$type] = $this->ending[$type]; 
		else $ending = $this->ending; 
		
		// loop each ending
		foreach ($ending as $key => $data) {
			$prefix = $this->sanitize($this->options['prefix']); 
			$folder = $this->getPath($key); 
			$dir = new DirectoryIterator($folder); 
			foreach($dir as $file) {
				$regex = "{$prefix}\."; 
				if ($group) $regex .= $this->sanitize($group) . '\.'; 
				if ($file->isDir() || $file->isDot()) continue; 
				if ($file->isFile() && in_array($this->fileExtension($file), $ending) && preg_match('/^'.$regex.'/', $file->getFilename())) {
					unlink($file->getPathname()); 
				}
			}
		}
	}
	
}
