<?php

namespace Equit\Html;

/**
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
trait HasDisabledAttribute {
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