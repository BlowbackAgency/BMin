<?php

/**
 * BMin JS/CSS/LESS Compiler
 *
 * Copyright (C) 2014 Jukka Hankaniemi - Blowback.fi
 *
 * @version 1.1.0
 * @author Jukka Hankaniemi https://github.com/Roope
 * @copyright Blowback https://github.com/BlowbackDesign
 * @license MIT http://opensource.org/licenses/MIT
 */

class BMin {

	/**
	 * Default configuration options
	 *
	 */
	protected $options = array(
		'live' => false, 
		'debug' => false, 
		'cache' => true, 
		'compress' => true, 
		'newlines' => true, 
		'removenewlines' => null,   // renamed to newlines from v1.1.0
		'expires' => 2592000, 
		'dateform' => 'd.m.Y H:i:s', 
		'version' => '',            // version name: [str] (a-z0-9)
		'group' => 'main',          // default group name [str] (a-z0-9)
		'prefix' => 'bmin',         // prefix for compiled file name [str] (a-z0-9)
		'root' => '',               // directory-path, leave empty for document root
		'path' => '',               // url-path, leave empty for script's path
		'styles' => '/css',         // path to compiled css files (relative to url-path)
		'scripts' => '/js',         // path to compiled js files (relative to url-path)
	);

	/**
	 * Root directory-path
	 *
	 */
	protected $root;

	/**
	 * Root url-path
	 *
	 */
	protected $path;

	/**
	 * Runtime configuration options
	 *
	 */
	protected $config;
	
	/**
	 * Main file data array
	 *
	 */
	protected $data = array();

	/**
	 * File data types and extensions
	 *
	 */
	private static function types($type=null) {
		$types = array(
			'styles' => array('css', 'less'),
			'scripts' => array('js')
		);
		if($type != null) {
			if($type === true) return array_keys($types);
			else if(isset($types[$type])) return $types[$type];
			else foreach($types as $k => $v) if(isset($v[$type])) return $k;
			return null;
		}
		return $types;
	}
	
	/**
	 * Constructor
	 *
	 */
	public function __construct($fileset=array(), $options=array()) { 

		// set config
		$this->set('config', $options);

		// set default root and path
		$this->root = !empty($this->config['root']) ? $this->pathName($this->config['root']) : $_SERVER['DOCUMENT_ROOT'];
		$this->path = !empty($this->config['path']) ? $this->pathName($this->config['path']) : dirname($_SERVER['SCRIPT_NAME']);

		if(!$this->config['live']) {

			// add fileset data
			$this->addFileset(self::types(true), $fileset);

			// get includes
			$include_dir = dirname(__FILE__) . '/lib';
			require_once "{$include_dir}/Compressor.php";
			require_once "{$include_dir}/JSMin.php";
			require_once "{$include_dir}/less/Less.php";
		}

	}
	
	/**
	 * Configuration options setter
	 *
	 */
	public function set($key, $value) {
		if($key == 'config' && is_array($value)) $this->config = array_merge($this->options, $value);
		else if(array_key_exists($key, $this->options)) $this->config[$key] = $value;
		if(in_array($key, array('root','path'))) $this->$key = $this->pathName($value);
		return $this;
	}
	
	/**
	 * Name sanitizer
	 *
	 */
	protected function sanitize($name) {
		if(!ctype_alnum($name)) {
			$replace = '_';
			$regex = 'a-zA-Z0-9';
			$name = filter_var($name, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
			$name = preg_replace("/[^{$regex}]/", $replace, $name);
			$name = preg_replace("/{$replace}+/", $replace, $name);
			$name = trim($name, $replace);
		}
		return strtolower(trim($name));
	}
	
	/**
	 * Styles processor
	 *
	 */
	public function styles($group=null, $fileset=null, $options=array()) {
		return $this->process('styles', $group, $fileset, $options);
	}
	
	/**
	 * Scripts processor
	 *
	 */
	public function scripts($group=null, $fileset=null, $options=array()) {
		return $this->process('scripts', $group, $fileset, $options);
	}
	
	/**
	 * Main processor
	 *
	 */
	protected function process($type, $group=null, $fileset=array(), $options=array()) {

		$group = !empty($group) ? $group : $this->config['group'];
				
		// process fileset and return url (only group name is set)
		if(is_string($group) && $fileset === null) {
			// use sanitized group name
			$_group = $this->sanitize($group);
			// fast exit on live mode
			if($this->config['live']) return $this->fileName($type, $_group);
			// process files group if there is some data set
			if($this->issetData($type, $_group)) return $this->processFileset($type, $_group);
		}
		
		// group name is array or filename string
		if(is_array($group) || (is_string($group) && strpos($group, '.') !== false)) {
			// merge options from fileset array
			if(is_array($fileset)) $options = array_merge($options, $fileset);
			// get files from group
			$fileset = is_array($group) ? $group : array($group);
			// add files and return
			$this->add($type, $fileset, $options);
			return $this;
		}
		
		// group name is set and fileset is array or filename string
		if(is_string($group)) {
			// grab single file string to files array
			if(is_string($fileset) && strpos($fileset, '.') !== false) $fileset = array($fileset);
			// proceed if fileset has data
			if(is_array($fileset) && count($fileset)) {
				// merge group name to options
				$options = array_merge($options, array('group'=>$group));
				// add files and return
				$this->add($type, $fileset, $options);
				return $this;
			}
		}
		
		return null;
	}

	/**
	 * Check if there is some valid file data set for given type/group combo
	 *
	 * @param string $type Data type name
	 * @param string $group Data group name
	 * @return bool
	 *
	 */
	protected function issetData($type, $group) {
		return isset($this->data[$type][$group]['files']);
	}

	/**
	 * Wrapper to add file(s) to main data
	 *
	 * @param string $type Files type
	 * @param array $files Files array
	 * @param array $config Fileset config data
	 *
	 */
	protected function add($type, $files, $config=array()) {
		if(is_array($files)) {
			if(isset($files[$type])) {
				$this->addFileset($type, $files[$type], $config);
			}
			else if(isset($files['files'])) {
				$this->addFileset($type, $files, $config);
			}
			else if(count($files)) {
				$files = array_merge($config, array('files'=>$files));
				$this->addFilesetFiles($type, $files);
			}
		}
	}

	/**
	 * Wrapper to add fileset to main data
	 *
	 * @param string $type Files type
	 * @param array $fileset Fileset data
	 *
	 */
	protected function addFileset($type, $fileset, $config=array()) {
		if(is_array($type)) {
			foreach($type as $t) {
				if(array_key_exists('files', $fileset)) {
					$files = array_merge($config, $fileset);
					$this->addFilesetFiles($t, $files);
				}
				else if(array_key_exists($t, $fileset)) {
					$this->addFileset($t, $fileset[$t], $config);
				}
			}
		}
		else if(is_string($type) && array_key_exists('files', $fileset)) {
			$files = array_merge($config, $fileset);
			$this->addFilesetFiles($type, $files);
		}
		else if(is_string($type)) {
			foreach($fileset as $fileset) {
				if(array_key_exists('files', $fileset)) {
					$files = array_merge($config, $fileset);
					$this->addFilesetFiles($type, $files);
				}
			}
		}
	}

	/**
	 * Add single fileset to main data
	 *
	 * @param string $type Files type
	 * @param array $config Fileset data
	 *
	 */
	protected function addFilesetFiles($type, $fileset) {

		// removenewlines is renamed to newlines from v1.1.0 - reset config if old name is found
		if(isset($this->config['removenewlines'])): $this->config['newlines'] = $this->config['removenewlines']; unset($this->config['removenewlines']); endif;
		if(isset($fileset['removenewlines'])): $fileset['newlines'] = $fileset['removenewlines']; unset($fileset['removenewlines']); endif;

		$config = array(
			'root' => isset($fileset['root']) ? $fileset['root'] : '', 
			'path' => isset($fileset['path']) ? $fileset['path'] : '', 
			'group' => isset($fileset['group']) ? $fileset['group'] : $this->config['group'], 
			'cache' => isset($fileset['cache']) ? $fileset['cache'] : $this->config['cache'], 
			'compress' => isset($fileset['compress']) ? $fileset['compress'] : $this->config['compress'], 
			'newlines' => isset($fileset['newlines']) ? $fileset['newlines'] : $this->config['newlines'], 
			'expires' => isset($fileset['expires']) ? $fileset['expires'] : $this->config['expires'], 
			'version' => isset($fileset['version']) ? $fileset['version'] : $this->config['version'], 
		);

		$groups = array();

		foreach($fileset['files'] as $key => $val) {
			$group = $config['group'];
			if(is_string($key) && is_string($val)) {
				$_config = array_merge($config, array('group'=>$val));
				$group = $_config['group'];
				$this->addFilesetFile($type, $key, $_config);
			} else if(is_string($val)) {
				$this->addFilesetFile($type, $val, $config);
			}
			if(!isset($groups[$group])) $groups[$group] = $group;
		}

		// save fileset config data for each group
		foreach($groups as $group) {
			$this->data[$type][$group]['config']['cache'] = (bool) $config['cache'];
			$this->data[$type][$group]['config']['compress'] = (bool) $config['compress'];
			$this->data[$type][$group]['config']['newlines'] = (bool) $config['newlines'];
			$this->data[$type][$group]['config']['expires'] = (int) $this->sanitize($config['expires']);
			$this->data[$type][$group]['config']['version'] = $this->sanitize($config['version']);
		}

	}

	/**
	 * Add single file to main data
	 *
	 * @param string $type File type
	 * @param string $file File name
	 * @param array $config Fileset config data
	 *
	 */
	protected function addFilesetFile($type, $file, $config) {

		// dont't add files on live mode
		if($this->config['live']) return;

		// set path and file data
		$root = !empty($config['root']) ? $this->pathName($config['root']) : $this->root;
		$path = !empty($config['path']) ? $this->pathName($config['path']) : $this->path;
		$file = $this->pathName($file);

		// clean duplicate paths from file to allow full path on filename
		$file = str_replace($root, '', $file);
		$file = str_replace($path, '', $file);

		// set file-path
		$filepath = "{$root}{$path}{$file}";

		// use group name from config data or fallback to default
		$group = isset($config['group']) ? $config['group'] : $this->config['group'];

		// set key and sanitize group and type
		$key = $this->fileKey($filepath);
		$group = $this->sanitize($group);
		$type = $this->sanitize($type);

		// set filename data (keep segments for debug)
		$data = array('filepath'=>$filepath, 'root'=>$root, 'path'=>$path, 'file'=>$file);

		if(@is_file($filepath)) {

			$data['time'] = filemtime($filepath);
			$this->data[$type][$group]['files'][$key] = $data;

		} else if($this->config['debug']) {

			$this->data[$type][$group]['failed'][$key] = $data;

		}

	}
	
	/**
	 * Look type group combo for cachefile and return filename if file is readable.
	 * If cachefile doesn't exist or it's expired, create new one.
	 *
	 * @param string $type Data type name
	 * @param string $group Data group name
	 * @return mixed
	 *
	 */
	protected function processFileset($type, $group) {
		
		$build = true;
		$cachefile = $this->fileName($type, $group, true);
		$cache = $this->data[$type][$group]['config']['cache'];

		if(@is_file($cachefile)) {

			$build = false;
			$cachetime = filemtime($cachefile);
			$expires = $this->data[$type][$group]['config']['expires'];

			if(($cachetime + $expires) < time()) $build = true;

			if(!$build) {
				foreach($this->data[$type][$group]['files'] as $file) {
					if($cachetime < $file['time']) $build = true;
				}
			}

		}

		if($build || $cache === false) {
			if($this->processFilesetFiles($type, $group)) $build = false;
		}

		if($build === false) {
			return $this->fileName($type, $group);
		}

		return false;
	}
	
	/**
	 * Generate new cachefile for type group combo
	 *
	 * @param string $type Data type name
	 * @param string $group Data group name
	 * @return bool
	 *
	 */
	protected function processFilesetFiles($type, $group) {

		$str = '';
		$files = $this->data[$type][$group]['files'];
		$cachefile = $this->fileName($type, $group, true);
		$compress = $this->data[$type][$group]['config']['compress'];
		$newlines = $this->data[$type][$group]['config']['newlines'];

		// set process start time
		$this->data[$type][$group]['process']['start'] = microtime(true);

		// get all files from group
		foreach($files as $key => $val) {
			$file = $val['filepath'];
			$extension = pathinfo($file, PATHINFO_EXTENSION);
			switch($extension) {
				case 'less':
					$less = new Less_Parser;
					$less->parseFile($file);
					$str .= $less->getCss();
					break;
				default:
					$str .= @file_get_contents($file);
			}
		}

		// compress data
		if($compress === true) {
			switch($type) {
				case 'styles':
					$str = Minify_CSS_Compressor::process($str);
					break;
				case 'scripts':
					$str = JSMin::minify($str);
					break;
			}
			if($newlines === true) {
				$str = str_replace(array("\r\n","\r","\n"), " ", $str);
			}
		}

		// create destination folder
		$dir = pathinfo($cachefile, PATHINFO_DIRNAME);
		if(!file_exists($dir)) mkdir($dir, 0777, true);

		// save string to cache file
		@file_put_contents($cachefile, $str, LOCK_EX);
		
		// set process stop time
		$this->data[$type][$group]['process']['stop'] = microtime(true);
		
		// return true if cachefile created succesfully
		return @is_file($cachefile) ? true : false;
	}
	
	/**
	 * Return normalized path name
	 *
	 * @return string
	 *
	 */
	protected function pathName($path) {
		$path = trim($path, '/');
		return !empty($path) ? "/{$path}" : '';
	}
	
	/**
	 * Return cache file name string
	 *
	 */
	protected function fileName($type, $group='', $path=null) { 

		if(empty($group)) $group = $this->config['group'];

		$name = $this->sanitize($group);
		$folder = $this->pathName($this->config[$type]);
		$prefix = $this->sanitize($this->config['prefix']);
		$version = $this->data[$type][$group]['config']['version'];
		
		$types = self::types($type);
		$extension = array_shift($types);

		$filepath = "{$this->path}{$folder}/{$prefix}.{$name}";
		if($path === true) $filepath = "{$this->root}{$filepath}";
		if(!empty($version)) $filepath .= ".{$version}";

		return "{$filepath}.{$extension}";
	}
	
	/**
	 * Return hashed array key for file
	 *
	 * @param string $name Filename with path
	 * @return string
	 *
	 */
	protected function fileKey($name) {
		$pos = strpos($name, '?');
		$key = $pos ? substr($name, 0, $pos) : $name;
		return md5($key);
	}
	
	/**
	 * Return debug data
	 *
	 * @return string
	 *
	 */
	public function debug($print_data=false) {
		$out = '';
		$processtime = 0;
		if($this->config['debug'] && count($this->data)) {

			foreach($this->data as $type => $data) {
				foreach($data as $group => $a) {
					$cachefile = $this->fileName($type, $group, true);
					$out .= "\n{$type}('{$group}') {\n";
					if(@is_file($cachefile)) {
						clearstatcache();
						$filesize = round(filesize($cachefile) / 1024, 2);
						$filetime = date($this->config['dateform'], filemtime($cachefile));
						if(isset($a['process'])) {
							$cachetime = ($a['process']['stop'] - $a['process']['start']);
							$processtime += $cachetime;
						}
						$msg = isset($cachetime) ? "NOW in {$cachetime} seconds" : $filetime;
						$out .= "\n\tcompiled: {$cachefile} - {$filesize} KB\n";
						$out .= "\tgenerated: {$msg}\n";
					}
					if(isset($a['config']) && count($a['config'])) {
						$out .= "\n\tconfig: {";
						foreach($a['config'] as $key => $val) {
							if(is_bool($val)) {
								$val = $val === true ? 'true': 'false';
							}
							if(!empty($val)) {
								$out .= "\n\t\t{$key}: {$val}";
							}
						}
						$out .= "\n\t}\n";
					}
					if(isset($a['files']) && count($a['files'])) {
						$out .= "\n\thas-files: {";
						foreach($a['files'] as $file) {
							$file = $file['filepath'];
							$out .= "\n\t\t{$file}";
						}
						$out .= "\n\t}\n";
					}
					if(isset($a['failed']) && count($a['failed'])) {
						$out .= "\n\tfailed-to-read-files: {";
						foreach($a['failed'] as $file) {
							$file = $file['filepath'];
							$out .= "\n\t\t{$file}";
						}
						$out .= "\n\t}\n";
					}
					$out .= "\n}\n";
				}
			}

			// set time data
			$t = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
			$p = $processtime > 0 ? $processtime : null;

			// switch to raw data output if set
			if($print_data) $out = "\n" . print_r($this->data, true);

			// add header and time to output
			$head = "\n\tBMIN DEBUG DATA";
			$head .= "\n\tThis page was born in {$t} seconds";
			if($p) $head .= "\n\tBMin processing took {$p} seconds";
			$out = "{$head}\n{$out}";

			// switch tabs to spaces
			$out = preg_replace('/\t/', '   ', $out);

			// comment output
			$out = "\n<!--\n{$out}\n-->\n";

		}
		return $out;
	}

	/**
	 * Delete compiled files
	 *
	 */
	public function delete($type=null, $group=null) {

		$deleted = array();
		$types = self::types();
		$regex = $this->config['prefix'] . "\.";

		if($group != null) {
			$group = is_string($group) ? $this->sanitize($group) : $this->config['group'];
			$regex .= "{$group}\.";
		}

		foreach($types as $key => $val) {
			if($type && $type != $key) continue;

			$extension = array_shift($val);
			$filename = $this->fileName($key, $group, true);
			$dirname = pathinfo($filename, PATHINFO_DIRNAME);

			if(!is_dir($dirname)) continue;
			$directory = new DirectoryIterator($dirname);

			foreach($directory as $item) {
				if($item->isDir() || $item->isDot()) continue;
				if($item->isFile()) {
					if(pathinfo($item, PATHINFO_EXTENSION) != $extension) continue;
					if(preg_match("/^{$regex}/", $item->getFilename())) {
						$file = $item->getPathname();
						if(unlink($file)) $deleted[] = $file;
					}
				}
			}

		}
		
		return($deleted);
	}

}

