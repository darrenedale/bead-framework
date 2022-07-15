<?php

namespace Equit\Html;

/**
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class UnorderedList extends Element {
	use HasListItems;
	use HasTooltip;

	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	protected function emitContainerStart() {
		return "<ul{$this->emitAttributes()}>";
	}

	protected function emitContainerEnd() {
		return "</ul>";
	}

	public function html(): string {
		return $this->emitContainerStart() . $this->emitItems() . $this->emitContainerEnd();
	}
}