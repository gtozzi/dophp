<?php declare(strict_types=1);


/**
* Custom Auth class
*/
class Auth extends dophp\AuthPlain {

	/** This is a stub

	protected $_username = null;

	protected function _login($user, $pwd, $source) {
		$q = '
			SELECT
				u.id,
				c.password
			FROM users AS u
			WHERE u.username = ?
		';
		$p = [
			$user,
		];
		$r = $this->_db->xrun($q,$p)->fetch();

		if( ! $r || ! $r['id'] )
			return null;

		if( $source == self::SOURCE_SESSION ) {
			if( $pwd !== $r['password'] )
				return null;
		} elseif( ! hash_equals(sha1($pwd), $r['password']) ) {
			return null;
		}

		$this->_username = $user;

		return [ $r['id'], $r['password'] ];
	}
	*/

}
