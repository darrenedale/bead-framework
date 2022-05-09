<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 23/03/19
 * Time: 14:53
 */

namespace Equit\Html;

class OrderedList extends PageElement {
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