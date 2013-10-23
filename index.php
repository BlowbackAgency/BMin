<?php 
/**
 * BMin JS/CSS/LESS Compiler HTML5 Boilerplate Demo Page
 *
 * Copyright (C) 2013 Jukka Hankaniemi - Blowback.fi
 * Licensed under MIT http://opensource.org/licenses/MIT
 */

	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	
	require_once 'bmin/bmin.class.php';
	
	$bmin = new bmin(include 'fileset.php');
	$bmin->set('cache', false);
	$bmin->set('debug', true);

?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>BMin.. wtf!</title>
		<link rel="stylesheet" href="<?php echo $bmin->styles(); ?>">
		<script src="<?php echo $bmin->scripts('head'); ?>"></script>
	</head>
	<body>
		
		<div id="main">
			<p>Hello world! This is HTML5 Boilerplate.</p>
		</div>
		
		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script>window.jQuery || document.write('<script src="js/vendor/jquery-1.10.2.min.js"><\/script>')</script>
		<script src="<?php echo $bmin->scripts(); ?>"></script>
		
		<?php 
			
			//$bmin->delete(); // delete all compiled files
			echo $bmin->debug(); // display debug data (always last)
			
		?>

	</body>
</html>
