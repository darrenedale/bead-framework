<?php
/**
 * Defines the HtmlPlaceholder trait.
 *
 * ### Dependencies
 * None.
 *
 * ### Changes
 * - (2018-09) First version of this file.
 *
 * @file HtmlPlaceholder.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Sep 2018
 */

namespace Equit\Html;

/**
 * Trait HtmlPlaceholder.
 *
 * Trait to enable HTML elements to support placeholders without having to reimplement the functionality over and over.
 */
trait Placeholder {
	/**
	 * Set the placeholder for the element.
	 *
	 * If this is set to _null_, the _placeholder_ attribute will usually be omitted from the rendered element
	 * altogether.
	 *
	 * @param null|string $placeholder The placeholder to set.
	 */
	public function setPlaceholder(?string $placeholder): void {
		$this->setAttribute("placeholder", $placeholder);
	}

	/**
	 * Fetch the element's placeholder.
	 *
	 * The placeholder returned will be _null_ if no placeholder is set.
	 *
	 * @return null|string The placeholder.
	 */
	public function placeholder(): ?string {
		return $this->attribute("placeholder");
	}
}
