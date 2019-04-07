<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 10/03/19
 * Time: 18:03
 */

namespace Equit\Html;

trait ListItems {

	/**
	 * Add an item to the list.
	 *
	 * It is undefined behaviour to add an item that is neither a string nor a PageElement.
	 *
	 * @param $item string|PageElement The item to add.
	 */
	public function addItem($item): void {
		assert(is_string($item) || $item instanceof PageElement);
		$this->m_items[] = $item;
	}

	/**
	 * Fetch an item from the list.
	 *
	 * It is undefined behaviour to request the item at an invalid index.
	 *
	 * @param int $idx The 0-based index of the item.
	 *
	 * @return string|PageElement The item at the specified index.
	 */
	public function item(int $idx) {
		assert(0 <= $idx && $this->itemCount() > $idx);
		return $this->m_items[$idx];
	}

	/**
	 * Fetch the items in the list.
	 *
	 * @return array[string|PageElement] The list items.
	 */
	public function items(): array {
		return $this->m_items;
	}

	/**
	 * Fetch the number of items in the list.
	 *
	 * @return int The number of items in the list.
	 */
	public function itemCount(): int {
		return count($this->m_items);
	}

	protected final function emitItems(): string {
		$html = "";

		foreach($this->items() as $item) {
			if(is_string($item)) {
				$html .= "<li>" . html($item) . "</li>";
			}
			else {
				$html .= "<li>{$item->html()}</li>";
			}
		}

		return $html;
	}

	/**
	 * @var array The items in the list.
	 */
	private $m_items = [];
}