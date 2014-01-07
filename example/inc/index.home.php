<?php

/**
* Home page
*
* @author Gabriele Tozzi <gabriele@tozzi.eu>
*/
class doHome extends dophp\Pagesmarty {

    protected function _build() {
		$this->_smarty->assign('title', 'Welcome to DoPhp');
        $this->_smarty->assign('hello', 'Hello, world!');
    }
}
