<?php
/**
 * Defines the HTML Data trait.
 *
 * ### Dependencies
 * None.
 *
 * ### Changes
 * - (2018-09) First version of this file.
 *
 * @file Data.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Sep 2018
 */

namespace Equit\Html;

/**
 * Trait Data.
 *
 * Trait to enable HTML elements to support data attributes.
 */
trait Data {
	/**
	 * Set a data item on the element.
	 *
	 * Set a `null` value to unset the data item.
	 *
	 * @param string $name The name of the data item to set. You **must** ensure this is valid.
	 * @param string|null $value The value to set.
	 */
	public function setData(string $name, ?string $value): void {
		$this->setAttribute("data-$name", $value);
	}

	/**
	 * Fetch a data attribute from the element.
	 *
	 * The data returned will be _null_ if no such data item is set.
	 *
	 * @param $name string The name of the data item to fetch.
	 *
	 * @return null|string The tooltip.
	 */
	public function data($name): ?string {
		return $this->attribute("data-$name");
	}
}
