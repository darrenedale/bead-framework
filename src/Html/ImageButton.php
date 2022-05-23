<?php

/**
 * Defines the ImageButton class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\PageElement.php
 * - classes/equit/HtmlName.php
 * - classes/equit/HtmlTooltip.php
 *
 * ### Changes
 * - (2018-09) Uses traits for common HTML attributes.
 * - (2018-09) Uses string type hints.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
 * - (2013-12-10) First version of this file.
 *
 * ### Todo
 * Tooltip as a trait.
 *
 * @file ImageButton.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\Html\HasNameAttribute;
use Equit\Html\HasTooltip;
use Equit\Html\PageElement;

/**
 * A push button using an image for inclusion in forms.
 *
 * ### Actions
 * This module does not support any actions.
 *
 * ### API Functions
 * This module does not provide an API.
 *
 * ### Events
 * This module does not emit any events.
 *
 * ### Connections
 * This module does not connect to any events.
 *
 * ### Settings
 * This module does not read any settings.
 *
 * ### Session Data
 * This module does not create a session context.
 *
 * @class ImageButton
 * @author Darren Edale
 * @package libequit
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class ImageButton extends PageElement {
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
