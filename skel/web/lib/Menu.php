<?php declare(strict_types=1);


/**
 * The base menu
 */
class Menu extends dophp\Menu {

	/**
	 * Creates the application menu
	 *
	 * @param $user Auth: The user
	 */
	public function __construct(Auth $user) {
		$items = [
		];

		parent::__construct(null, null, $items);
	}

}
