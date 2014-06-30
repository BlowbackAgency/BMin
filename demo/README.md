# BMin Demo

Here are some basic how to use demonstrations for BMin compiler to get you started with your own web projects.

## Examples

- [__HTML5 Boilerplate__](./html5-boilerplate/)

## Basic Usage
```php
<?php

require_once '../path/to/bmin.class.php';
$bmin = new BMin();

$bmin->styles(array(
	'/path/to/css/fonts.css',
	'/path/to/css/main.less'
));

$bmin->scripts(array(
	'/path/to/js/plugins.js',
	'/path/to/js/main.js'
));

?>
```