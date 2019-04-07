<?php
/**
 * Defines the HtmlFormElementDisabled trait.
 *
 * Classes that utilise this trait can have a **disabled** attribute set.
 * ### Dependencies
 * None.
 *
 * ### Changes
 * - (2018-09) First version of this file.
 *
 * @file HtmlFormElementDisabled.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2019
 */
namespace Equit\Html;

trait FormElementDisabled {
	/**
	 * Set the disabled attribute for the element.
	 *
	 * Setting `true` will give the element the attribute `disabled="disabled"`; setting `false` will remove the attribute altogether.
	 *
	 * @param bool $disabled Whether or not the element should be disabled.
	 */
	public function setDisabled(?bool $disabled): void {
		$this->setAttribute("disabled", ($disabled ? "disabled" :  null));
	}

	/**
	 * Fetch the element's disabled attribute.
	 *
	 * @return bool `true` if the element is disabled, `false` if not.
	 */
	public function disabled(): bool {
		return null !== $this->attribute("disabled");
	}

}