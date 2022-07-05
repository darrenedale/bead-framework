<?php

namespace Equit\Html;

/**
 * A push button using an image for inclusion in forms.
 *
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class ImageButton extends Element {
	use HasNameAttribute;
	use HasTooltip;

	/** The HTML attributes supported by ImageButton objects. */
	private static $s_imageButtonAttributeNames = ['value', 'name', 'title', 'src', 'alt'];

	/**
	 * Create a new ImageButton.
	 *
	 * If no image URL or alt text are provided, these attributes default to empty. The id is optional, and if not set
	 * the image button will be generated without an _id_ attribute.
	 *
	 * @param $imageUrl string The URL for the image to display.
	 * @param $alt string The text for the image's _alt_ attribute.
	 * @param $id string The ID for the image button.
	 */
	public function __construct(string $imageUrl = "", string $alt = "", ?string $id = null) {
		parent::__construct($id);

		foreach(self::$s_imageButtonAttributeNames as $name) {
			$this->setAttribute($name, null);
		}

		$this->setImageUrl($imageUrl);
		$this->setAlternateText($alt);
	}

	/**
	 * Fetch the URL of the image to display.
	 *
	 * @return string The URL, or _null_ if no URL has been set.
	 */
	public function imageUrl(): ?string {
		return $this->attribute("src");
	}

	/**
	 * Set the URL of the image to display.
	 *
	 * The URL can be set to _null_ to unset the current URL.
	 *
	 * @param $imageUrl string The URL.
	 *
	 * @return void.
	 */
	public function setImageUrl(?string $imageUrl): void {
		$this->setAttribute("src", $imageUrl);
	}

	/**
	 * Fetch the alternate text for the image.
	 *
	 * The alternate text will be used as the value for the _alt_ attribute of the element.
	 *
	 * @return string The URL, or _null_ if no URL has been set.
	 */
	public function alternateText(): ?string {
		return $this->attribute("alt");
	}

	/**
	 * Set the alternate text for the image.
	 *
	 * The provided alternate text will be used as the value for the _alt_ attribute of the element. The alternate
	 * text can be set to _null_, although this is strongly discouraged as it will create HTML that is not standards
	 * compliant.
	 *
	 * @param $alt string The alt text.
	 *
	 * @return void.
	 */
	public function setAlternateText(?string $alt): void {
		$this->setAttribute("alt", $alt);
	}

	/**
	 * Fetch the value of the image button.
	 *
	 * This is the value submitted by this element with the form data.
	 *
	 * @return string The value, or _null_ if no value has been set.
	 */
	public function value(): ?string {
		return $this->attribute("value");
	}

	/**
	 * Set the value of the image button.
	 *
	 * This is the value submitted by this element with the form data. It can be set to _null_ to unset the current
	 * value.
	 *
	 * @param $value string The value.
	 *
	 * @return void.
	 */
	public function setValue(?string $value): void {
		$this->setAttribute("value", $value);
	}

	/**
	 * Generate the HTML for the image button.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return "<input type=\"image\"" . $this->emitAttributes() . " />";
	}
}
