<?php

namespace Equit\Html;

/**
 * @deprecated The HTML library of the framework has been replaced by the `View` and `Layout` classes.
 */
class OrderedList extends Element {
	use HasListItems;

	public function __construct(?string $id = null) {
		parent::__construct($id);
	}

	protected function emitContainerStart() {
		return "<ol{$this->emitAttributes()}>";
	}

	protected function emitContainerEnd() {
		return "</ol>";
	}

	public function html(): string {
		return $this->emitContainerStart() . $this->emitItems() . $this->emitContainerEnd();
	}
}