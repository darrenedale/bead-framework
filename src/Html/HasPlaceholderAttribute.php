<?php
/**
 * Defines the HasPlaceholderAttribute trait.
 *
 * @file HasPlaceholderAttribute.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 */

namespace Equit\Html;

/**
 * Trait HtmlPlaceholder.
 *
 * Trait to enable HTML elements to support placeholders without having to reimplement the functionality over and over.
 */
trait HasPlaceholderAttribute
{
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
