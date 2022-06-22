<?php

/**
 * Defines the ListEdit class.
 *
 * ### Dependencies
 * - classes/equit/LibEquit\PageElement.php
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\HtmlLiteral.php
 * - classes/equit/GridLayout.php
 * - classes/equit/PushButton.php
 * - classes/equit/AutocompleteTextEdit.php
 * - classes/equit/LibEquit\Application.php
 * - classes/equit/HtmlName.php
 * - classes/equit/HtmlTooltip.php
 *
 * ### Changes
 * - (2018-01) No exposes component add, remove and edit widgets for customisation.
 * - (2017-04) First version of this file.
 *
 * @file ListEdit.php
 * @author Darren Edale
 * @version 0.9.2
 * @package bead-framework
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\AppLog;

/**
 * An editable list for inclusion in forms.
 *
 * This class represents a widget on a form that enables the user to create a list of items.
 *
 * Upon form submission, all of the items in the list are submitted. If the list is empty, the data is still submitted,
 * it will just be empty. This means that in processing scripts it may be difficult to distinguish between an empty list
 * and a list containing one empty item.
 *
 * The HTML element behind the list that stores its submitted data is a hidden `<input>` element. The hidden element
 * stores the items in the list separated by newlines, which means that individual elements in the list can't contain
 * any newline characters. This constraint should be fine because, by design, the widget is intended to capture lists of
 * small snippets of text, usually just a few words each. The display part of the ListEdit is a `<ul>` element, which is
 * kept synchronised with the list of items stored in the form element by runtime javascript. Its items can be clicked
 * to select them. Only one item can be selected at a time.
 *
 * The ListEdit also contains a single-line text input widget and buttons to add and remove items. When the add button
 * is clicked, whatever is in the text input widget is added to the list. (If the text input widget is empty, an empty
 * item is added to the list.) When the remove button is clicked, the selected item in the display list is removed. (If
 * no item is selected, no action is taken.) After removal, the item following the one removed becomes the selected
 * item, unless the selected item was the last in the list, in which case the item preceding it becomes the selected
 * item. If there are no items left in the list, the list will have no selected item.
 *
 * The hidden `<input>` element is the only form element in the ListEdit layout that has a `name` attribute and as such
 * is the only one that will result in data being submitted with the parent form. This means that the data submitted
 * for the list is a newline-separated list of the items in the list. Alternatively, the "main" ListEdit page element
 * (which is not a form element as such) is blessed with some methods and properties to manipulate the list. This
 * element has a `value` attribute that provides the items in the list in an array. You can fetch a reference to this
 * element either by using `document.getElementById()` with the ID you gave the ListEdit object, or by querying the
 * form's `elements` collection for the element with the ListEdit's `name` attribute and reading the `listEdit`
 * property.
 *
 * \par Element layout
 * Each ListEdit widget is laid out on the page like this:
 * \verbatim
 * +-----------------------------------------------------------------------------------------+
 * | GridLayout #{id} .listedit .listedit-layout                                             |
 * |                                                                                         |
 * | This is the main element, blessed with all the ListEdit methods and properties          |
 * |                                                                                         |
 * | +-------------------------------------------------------+-----------------------------+ |
 * | | AutocompleteTextEdit #{id}-itemedit                   | PushButton #{id}-add        | |
 * | | JS: {listedit}.textEdit                               | JS: {listedit}.add          | |
 * | |                                                       |                             | |
 * | | This is the single-line text entry widget where you   | Click to add the item       | |
 * | | type new items for the list.                          | to the list.                | |
 * | |                                                       |                             | |
 * | |                                           Cell (0, 0) |                 Cell (0, 1) | |
 * | +-------------------------------------------------------+-----------------------------+ |
 * | | <ul> #{id}-display .listedit-display                  | Pushbutton #{id}-remove     | |
 * | | JS: {listedit}.displayWidget                          | JS: {listedit}.removeButton | |
 * | |                                                       |                             | |
 * | | This is the display list that shows all the items in  | Click to remove the         | |
 * | | the list and in which you can click on individual     | selected item from the      | |
 * | | items to select them.                                 | list.                       | |
 * | |                                                       |                             | |
 * | |                                           Cell (1, 0) |                 Cell (1, 1) | |
 * | +-------------------------------------------------------+-----------------------------+ |
 * | | <input type="hidden"> #{id}-data .listedit-data                                     | |
 * | | JS: {listedit}.dataWidget                                                           | |
 * | |                                                                                     | |
 * | | This is the hidden form element that contains the definitive value for the          | |
 * | | ListEdit widget. Its name attribute is set to the name given to the ListEdit        | |
 * | | object.                                                                             | |
 * | |                                                                                     | |
 * | |                                                                         Cell (2, 0) | |
 * | +-------------------------------------------------------------------------------------+ |
 * +-----------------------------------------------------------------------------------------+
 * \endverbatim
 *
 * \par
 * Each of the HTML elements in the ListEdit object has a read-only `parentListEdit` property added that provides a
 * reference to the parent ListEdit in which it is embedded.
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
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 *
 * @class ListEdit
 * @author Darren Edale
 * @package bead-framework
 */
class ListEdit extends Element {
	use HasNameAttribute;
	use HasTooltip;

	private static $s_listEditAttributeNames = ["name", "title"];

	/** @var GridLayout|null The widget's layout. */
	private $m_layout = null;

	/** @var AutocompleteTextEdit|null The item editor for the widget. */
	private $m_item = null;

	/** @var null|\Equit\Html\PushButton The _add item_ push button. */
	private $m_add = null;

	/** @var \Equit\Html\HiddenValueWidget|null The widget containing the list of items for form submission. */
	private $m_list = null;

	/** @var \Equit\Html\UnorderedList|null The display for the list. */
	private $m_display = null;

	/** @var null|\Equit\Html\PushButton The _remove item_ push button. */
	private $m_remove = null;

	/** @var array[string] The list of items initially displayed in the widget. */
	private $m_items = [];

	/**
	 * Create a new ListEdit object.
	 *
	 * @param $id string _optional_ The ID for the new list edit.
	 *
	 * If no `$id` is provided, one will be generated since ListEdit objects
	 * are less effective without an ID. The auto-generated ID can be overridden
	 * by calling setId() before html() is called.
	 */
	public function __construct(?string $id = null) {
		if(empty($id)) {
			$id = "listedit-" . self::generateUid();
		}

		parent::__construct($id);

		foreach(self::$s_listEditAttributeNames as $name) {
			$this->setAttribute($name, null);
		}

		$this->m_layout = new GridLayout();
		$this->m_layout->addClassName("eq-listedit");
		$this->m_layout->addClassName("eq-listedit-layout");

		$this->m_item = new AutocompleteTextEdit();
		$this->m_item->addClassName("eq-listedit-itemedit");

		$this->m_add = new PushButton("+");
		$this->m_add->addClassName("eq-listedit-add");
		$this->m_add->addClassName("eq-listedit-button");
		$this->m_add->setTooltip(tr("Add this item to the list."));

		$this->m_list = new HiddenValueWidget();
		$this->m_list->addClassName("eq-listedit-data");

		$this->m_display = new UnorderedList();
		$this->m_display->addClassName("eq-listedit-display");

		$this->m_remove = new PushButton("-");
		$this->m_remove->addClassName("eq-listedit-remove");
		$this->m_remove->addClassName("eq-listedit-button");
		$this->m_remove->setTooltip(tr("Remove the selected item from the list."));

		$row = 0;
		$this->m_layout->addElement($this->m_item, $row, 0);
		$this->m_layout->addElement($this->m_add, $row, 1);

		++$row;
		$this->m_layout->addElement($this->m_display, $row, 0);
		$this->m_layout->addElement($this->m_remove, $row, 1);

		++$row;
		$this->m_layout->addElement($this->m_list, $row, 0, 1, 2);
	}

	/**
	 * Fetch the _add item_ button.
	 *
	 * You can use this method to fetch the button to enable you to customise it (e.g. add a tooltip). Upon construction
	 * of the _ListEdit_, the button will have a default state with a generic tooltip and `+` as the button's text.
	 * There is no point in setting the ID of the returned button, its ID will be reset to that required by the
	 * _ListEdit_ upon output.
	 *
	 * @return PushButton The button used to add an item.
	 */
	public function addButton(): PushButton {
		return $this->m_add;
	}

	/**
	 * Fetch the *remove item* button.
	 *
	 * You can use this method to fetch the button to enable you to customise it (e.g. add a
	 * tooltip). Upon construction of the ListEdit, the button will have a defauilt state with a
	 * generic tooltip and `-` as the button's text. There is no point in setting the ID of the
	 * returned button, its ID will be reset to that required by the ListEdit upon output.
	 *
	 * @return PushButton The button.
	 */
	public function removeButton(): PushButton {
		return $this->m_remove;
	}

	/**
	 * Fetch the item text editor.
	 *
	 * You can use this method to fetch the editor to enable you to customise it (e.g. add a
	 * tooltip). Upon construction of the ListEdit, the text edit will have a defauilt state with a
	 * generic tooltip and placeholder text. There is no point in setting the ID of the text edit,
	 * its ID will be reset to that required by the ListEdit upon output.
	 *
	 * \warning If you change the autocomplete API function on the returned object, any autocomplete
	 * API function set using the ListEdit::setAutocompleteApiFunction() will be discarded;
	 * similarly, if you use ListEdit::setAutocompleteApiFunction(), any function set directly on
	 * the TextEdit will be discarded.
	 *
	 * \warning his method is marked as returning a TextEdit object. Currently, this is an instance
	 * of the AutocompleteTextEdit subclass, but this is not guaranteed to remain so. The only
	 * guarantee is that the returned object is a TextEdit instance.
	 *
	 * @return TextEdit The text edit.
	 */
	public function itemTextEdit(): TextEdit {
		return $this->m_item;
	}

	/**
	 * Fetch the ListEdit's layout.
	 *
	 * The layout can be used to customise or extend the ListEdit. It is recommended that you
	 * restrict yourself to adding widgets to the layout. To change properties of the existing
	 * widgets in the layout, use the individual component widget getters (e.g. addButton()).
	 * Fetching widgets via the layout will break if the layout of the ListEdit's component widgets
	 * changes, whereas the individual getters will always return the expected widget.
	 *
	 * @return GridLayout The layout.
	 */
	public function layout(): GridLayout {
		return $this->m_layout;
	}

	/**
	 * Set the initial list of items.
	 *
	 * @param $items [string] The items to set.
	 *
	 * The provided set of items must be an array composed entirely of strings. Any invalid item in the array results
	 * in the entire array being discarded and the _ListEdit_ remaining unmodified.
	 *
	 * @return bool _true_ if the items were set, _false_ otherwise.
	 */
	public function setItems(array $items): bool {
		foreach($items as $item) {
			if(!is_string($item)) {
				AppLog::error("non-string item in array of items", __FILE__, __LINE__, __FUNCTION__);
				return false;
			}
		}

		$this->m_items = $items;
		return true;
	}

	/**
	 * Clear the list of items.
	 *
	 * After a call to this method, the _ListEdit_ widget will contain no items.
	 */
	public function clear(): void {
		$this->m_items = [];
	}

	/**
	 * Add an item to the initial list of items.
	 *
	 * @param $item string The item to add.
	 *
	 * @return void.
	 */
	public function addItem(string $item): void {
		$this->m_items[] = $item;
	}

	/**
	 * Fetch the initial list of items.
	 *
	 * This method guarantees that the returned array contains only strings.
	 *
	 * @return array[string] The list of items.
	 */
	public function items(): array {
		return $this->m_items;
	}

	/**
	 * Fetch the widget's placeholder text.
	 *
	 * This is the placeholder that the item editor widget will have.
	 *
	 * @return string The placeholder for the widget, or _null_ if no placeholder is set.
	 */
	public function placeholder(): ?string {
		return $this->m_item->placeholder();
	}

	/**
	 * Set the widget's placeholder text.
	 *
	 * @param $placeholder string The placeholder text for the widget.
	 *
	 * This is the placeholder that the item editor widget will have.
	 *
	 * The placeholder text can be _null_ to unset the existing placeholder
	 * text.
	 *
	 * @return void.
	 */
	public function setPlaceholder(?string $placeholder): void {
		$this->m_item->setPlaceholder($placeholder);
	}

	/**
	 * Set the widget's item editor type.
	 *
	 * This can be any of the _TextEdit_ types. In theory it can even be the _MultiLine_ type, but this is rarely likely
	 * to make any sense.
	 *
	 * @param $type int The text editor type.
	 *
	 * @return bool _true_ if the type was set, _false_ otherwise.
	 */
	public function setItemEditorType(int $type): bool {
		return $this->m_item->setType($type);
	}

	/**
	 * Fetch the widget's item editor type.
	 *
	 * This will be one of the _TextEdit_ types.
	 *
	 * @return int The type of text widget.
	 *
	 */
	public function itemEditorType(): int {
		return $this->m_item->type();
	}

	/**
	 * Set the API function that the item text edit will use.
	 *
	 * @param $fn string The name of the API function.
	 * @param $contentParameterName string _optional_ The name of the URL parameter to use to provide the user's
	 * current input to the API function.
	 * @param $otherArgs array _optional_ An associative array (_string_ => _string_) of other parameters for the
	 * API function call. Keys must start with an alpha char and be composed entirely of alphanumeric chars and
	 * underscores.
	 *
	 * @see \Equit\Html\AutocompleteTextEdit::setAutocompleteApiCall().
	 *
	 * @return bool _true_ if the autocomplete API call was set, _false_ otherwise.
	 * @deprecated Use set AutocompleteEndpoint() instead.
	 */
	public function setAutocompleteApiCall(string $fn, ?string $parameterName = null, array $otherArgs = []): bool {
		return $this->m_item->setAutocompleteApiCall($fn, $parameterName, $otherArgs);
	}

	/**
	 * Set the endpoint that the item text edit will use for suggestions.
	 *
	 * @param $endpoint string The endpoint.
	 * @param $contentParameterName string _optional_ The name of the URL parameter to use to provide the user's
	 * current input to the API function.
	 * @param $otherArgs array _optional_ An associative array (_string_ => _string_) of other parameters for the
	 * API function call. Keys must start with an alpha char and be composed entirely of alphanumeric chars and
	 * underscores.
	 *
	 * @see \Equit\Html\AutocompleteTextEdit::setAutocompleteApiCall().
	 *
	 * @return bool _true_ if the autocomplete API call was set, _false_ otherwise.
	 */
	public function setAutocompleteEndpoint(string $endpoint, ?string $parameterName = null, array $otherArgs = []): bool {
		return $this->m_item->setAutocompleteEndpoint($endpoint, $parameterName, $otherArgs);
	}

	/**
	 * Set the runtime function that will process the result of the API call for the item edit.
	 *
	 * @param string|null $fn The runtime callable.
	 *
	 * @see \Equit\Html\AutocompleteTextEdit::setAutocompleteApiResultProcessor().
	 *
	 * @return bool `true` if the processor was set, `false`  if not.
	 */
	public function setAutocompleteApiResultProcessor(?string $fn): bool {
		return $this->m_item->setAutocompleteApiResultProcessor($fn);
	}

	/**
	 * Fetch the HTML for the ListEdit.
	 *
	 * See the class-level documentation for details of the HTML's structure.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		$items   = $this->items();
		$id      = $this->id();
		$name    = $this->name();

		$this->m_list->setValue(implode(chr(10), $items));
		$this->m_display->setTooltip($this->tooltip());

		foreach($items as $itemText) {
			$item = new ListItem();
			$item->addClassName("eq-listedit-item");
			$item->addChildElement(new HtmlLiteral(html($itemText)));
			$this->m_display->addItem($item);
		}

		$this->m_layout->setId($id);
		$this->m_item->setId("$id-itemedit");
		$this->m_add->setId("$id-add");
		$this->m_list->setId("$id-data");
		$this->m_remove->setId("$id-remove");
		$this->m_display->setId("$id-display");

		if(!empty($name)) {
			if("[]" != substr($name, -2)) {
				$name .= "[]";
			}

			$this->m_list->setName($name);
		}

		$this->m_layout->setClassName($this->m_layout->classNamesString() . " " . $this->classNamesString());
		return $this->m_layout->html();
	}
}
