BMin
====

Super simple JS/CSS/LESS compiler for PHP5

## Methods

- `$a->styles($group, $fileset, $options)`
- `$a->scripts($group, $fileset, $options)`
- `$a->delete($group, $type)`
- `$a->set($key, $value)`
- `$a->debug()`

## Options

Name | Type | Default | Description
---- | ---- | ------- | -----------
`live` | bool | false | On live mode bmin skips all file methods and returns only file name.
`debug` | bool | false | Creates debug data for debug() method.
`cache` | bool | true | Cache enabled. Set false to force file re-creation.
`compress` | bool | true | Compression enabled. Set false to compile files without compression (as is).
`removenewlines` | bool | true | Set true to make string one line. May break js code without line ending semicolon!
`expires` | int | 2592000 | File expirement time (ms).
`dateform` | string | d.m.Y H:i:s | Date format for debug data.
`version` | mixed |  | Version name (string) or set true for auto generation (timestamp).
`group` | string | main | Default group name.
`prefix` | string | bmin | Prefix for compiled files.
`styles` | string | css/ | Root folder for compiled css files (relative to app root).
`scripts` | string | js/ | Root folder for compiled js files (relative to app root).
`files` | string |  | Root for fileset files include path (relative to app root).
`root` | string |  | Application root.

## Dependencies

- [Minify_CSS_Compressor](https://github.com/mrclay/minify/blob/master/min/lib/Minify/CSS/Compressor.php)
- [JSMin](https://github.com/mrclay/minify/blob/master/min/lib/JSMin.php)
- [lessc](https://github.com/leafo/lessphp/blob/master/lessc.inc.php)

## Usage

**fileset.php** - Define default `$fileset`. It's basic multidimensional array so you can store it in a variable or use include file like we do in this example.

```php
return array(

	'styles' => array(
		array(
			'files' => array(
				'css/normalize.css', 
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
			'files' => array(
				'js/plugins.js', 
				'js/main.js'
			),
		),
	),
	
);
```

**index.php** - Set up BMin in your template file.

```php
<?php 
require_once 'bmin/bmin.class.php';
$bmin = new bmin(include 'fileset.php');
?>
<html>
	<head>
		<meta charset="utf-8">
		<title>BMin.. wtf!</title>
		<link rel="stylesheet" href="<?php echo $bmin->styles(); ?>">
		<script src="<?php echo $bmin->scripts('head'); ?>"></script>
	</head>
	<body>
		<p>Hello world! This is BMin Boilerplate.</p>
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script src="<?php echo $bmin->scripts(); ?>"></script>
	</body>
</html>
```

Main processors can take custom `$fileset` and/or `$options` as argument. Set `$group` name to match your fileset group namespace or set true/leave empty to use default name from options.

```php
<?php 
$myFiles = array(
	'files' => array(
		'js/lightbox.js', 
		'js/modular.js'
	)
);
$myOpts = array(
	'group' => 'lightbox', 
	'removenewlines' => false, 
	'cache' => false
);
?>
<html>
	...
	<script src="<?php echo $bmin->scripts(true, $myFiles, $myOpts); ?>"></script>
</html>
```

You can also set some options inside every fileset array.

```php
array(
	// set group name for files (not required for default group)
	'group' => 'lightbox', 
	// compress files in this set
	'compress' => false, 
	// remove all new lines in this set
	'removenewlines' => false, 
	// wrap output inside media query (only for styles)
	'media' => 'screen, projection, tv', 
	
	// files array is required for every fileset
	'files' => array(
		'css/lightbox.css', 
		'css/modular.less'
	),
),
```

## License

Licensed under MIT http://opensource.org/licenses/MIT
