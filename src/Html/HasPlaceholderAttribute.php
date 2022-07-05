<?php

namespace Equit\Html;

/**
 * Trait HtmlPlaceholder.
 *
 * Trait to enable HTML elements to support placeholders without having to reimplement the functionality over and over.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
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
