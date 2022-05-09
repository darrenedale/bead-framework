<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 08/03/19
 * Time: 18:45
 */

namespace Equit\Html;

use Equit\Request;

class AlphabeticalIndexView extends IndexView {
	const DefaultUrlParameterName = "letter";

	/**
	 * Initialise a new alphabetical index.
	 *
	 * The index will include all letters between A and Z inclusive.
	 *
	 * @param \Equit\Request|null $req
	 * @param string $parameterName
	 */
	public function __construct(?Request $req = null, string $parameterName = self::DefaultUrlParameterName) {
		parent::__construct($req, $parameterName);
		$this->setRangeStart("A");
		$this->setRangeEnd("Z");
	}
}
