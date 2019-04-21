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

		if($item instanceof ListItem) {
			$this->m_items[] = $item;
			return;
		}

		if(is_string($item)) {
			$item = new HtmlLiteral(html($item));
		}

		$listItem = new ListItem();
		$listItem->addChild($item);
		$this->m_items[] = $listItem;
	}

	/**
	 * Fetch an item from the list.
	 *
	 * It is undefined behaviour to request the item at an invalid index.
	 *
	 * @param int $idx The 0-based index of the item.
	 *
	 * @return ListItem The item at the specified index.
	 */
	public function item(int $idx): ListItem {
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
			$html .= $item->html();
		}

		return $html;
	}

	/**
	 * @var array[ListItem] The items in the list.
	 */
	private $m_items = [];
}