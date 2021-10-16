<?php

/**
* @file config.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @brief Server configuration file
*/

require('base.php');

$config = array_merge($config, [
	'db' => array(
		'dsn' => 'mysql:host=mysql;dbname=app',
		'user' => 'root',
		'pass' => 'root',
	),
	/*
	'memcache' => [
		'host' => 'localhost',
	],
	*/
	'debug' => true,
	'strict' => true,
	'testserver' => true,
	/*
	'dophp' => [
		//'url' => 'lib/dophp',
		//'path' => ,
	],
	*/
]);
