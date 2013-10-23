<?php
/**
 * BMin fileset
 */
return array(

	'styles' => array(
		array(
			'group' => 'main',
			'files' => array(
				'css/normalize.css', 
				'css/main.css', 
				'css/main.less'
			),
		),
	),

	'scripts' => array(
		array(
			'group' => 'head',
			'files' => array(
				'js/vendor/modernizr-2.6.2.min.js'
			),
		),
		array(
			'group' => 'main',
			'files' => array(
				'js/plugins.js', 
				'js/main.js'
			),
		),
	),

);