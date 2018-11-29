<?php

/**
* @file debug.php
* @author Gabriele Tozzi <gabriele@tozzi.eu>
* @package DoPhp
* @see Page.php
* @brief Debug Page classes
*/

namespace dophp\debug;

require_once(__DIR__ . '/../Page.php');


/**
 * Page class for showing debug info
 */
class Viewer extends \dophp\PageBase implements \dophp\PageInterface {

	public function run() {
		// Do not log this request
		$this->debug->hide();

		$style = '
		<style>
			div {
				background-color: #ffe;
				padding: 5px;
				margin: 5px;
				border: 1px black dotted;
			}
			div.action {
				background-color: white;
			}
		</style>
		';

		$htmls = [];
		$dbg = Debug::instance();

		foreach( $dbg->getRequests() as $req )
			$htmls[] = $req->asHtml();

		$out = '<!DOCTYPE html>';
		$out .= "<html lang=\"en\">\n<head>\n";
		$out .= "<title>DoPhp Debug Info</title>\n";
		$out .= $style;
		$out .= "\n</head>\n<body>\n";
		$out .= "<p>";
		$out .= "Debug class <b>" . get_class($dbg) . "</b>";
		$out .= " stored <i>" . $dbg->countRequests() . "</i> requests.";
		$out .= "</p>";
		$out .= implode($htmls);
		$out .= "\n</body>\n</html>";

		return $out;
	}

}
