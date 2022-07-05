<?php

namespace Equit\Html;

/**
 * Trait Name.
 *
 * Trait to enable HTML elements to support names without having to reimplement the functionality over and over.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
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
