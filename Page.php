<?php

/**
* @file Page.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @brief Base classes for handling pages.
*        This file has now been split for readability. See "pages" subfolder for
*        more classes belonging here
*/

namespace dophp;

require_once(__DIR__ . '/DoPhp.php');

require_once(__DIR__ . '/pages/base.php');
require_once(__DIR__ . '/pages/exceptions.php');

require_once(__DIR__ . '/pages/smarty.php');
require_once(__DIR__ . '/pages/crud.php');
require_once(__DIR__ . '/pages/methods.php');
require_once(__DIR__ . '/pages/datatable.php');
require_once(__DIR__ . '/pages/backend.php');

require_once(__DIR__ . '/pages/debug.php');
