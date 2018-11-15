<?php

/**
* @file index.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @brief Main entry point
*/

require 'lib/dophp/DoPhp.php';
require 'lib/Db.php';
require 'lib/Auth.php';
require 'lib/Menu.php';
require 'lib/Page.php';

require 'config.php';

new DoPhp($config, 'Db', 'Auth');
