<?php

/**
* @file index.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @brief Main entry point
*/

require_once 'lib/dophp/DoPhp.php';
require_once 'lib/Db.php';
require_once 'lib/Auth.php';
require_once 'lib/Menu.php';
require_once 'lib/Page.php';

require 'config.php';

new DoPhp([
	'conf' => $config,
	'db' => 'Db',
	'auth' => 'Auth',
]);
