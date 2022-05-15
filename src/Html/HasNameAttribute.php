<?php
/**
 * Defines the HasNameAttribute trait.
 *
 * @file HtmlName.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 */

namespace Equit\Html;

/**
 * Trait Name.
 *
 * Trait to enable HTML elements to support names without having to reimplement the functionality over and over.
 */
trait HasNameAttribute {
	/**
	 * Set the name for the element.
	 *
	 * If this is set to _null_, the _name_ attribute will usually be omitted from the rendered element altogether.
	 *
	 * @param null|string $name The name to set.
	 */
	public function setName(?string $name): void {
		$this->setAttribute("name", $name);
	}

	/**
	 * Fetch the element's name.
	 *
	 * The name returned will be _null_ if no name is set.
	 *
	 * @return null|string The name.
	 */
	public function name(): ?string {
		return $this->attribute("name");
	}
}
