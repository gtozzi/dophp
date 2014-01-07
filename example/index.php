<?php

/**
* @file index.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @brief Serves all server pages
*/

require 'lib/dophp/DoPhp.php';
require 'config.php';

$config = array();
new DoPhp($config, 'it_IT.UTF-8');
