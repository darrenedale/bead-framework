<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 23/03/19
 * Time: 14:53
 */

namespace Equit\Html;

class UnorderedList extends PageElement {
	use ListItems;
	use Tooltip;

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