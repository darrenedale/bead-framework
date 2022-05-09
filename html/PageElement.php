<?php

/**
 * Defines the LibEquit\PageElement base class.
 *
 * ### Dependencies
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated to `[]` syntax for array literals.
 * - (2013-12-10) First version of this file.
 *
 * @file LibEquit\PageElement.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit\Html;

use Equit\AppLog;

require_once __DIR__ . "/../includes/string.php";

/**
 *
 * Base class for all elements that can be added to the page.
 *
 * ### Note
 * Subclasses **must** call _parent::__construct()_ from inside all their constructors. This ensures that the
 * internal data structures for some of the facilities provided by the protected helper methods are properly
 * initialised. Failure to do so could result in your subclass generating PHP errors in calls to this base class.
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
 * @class LibEquit\PageElement
 * @author Darren Edale
 * @ingroup libequit
 * @package libequit
 * @see Page
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
abstract class PageElement {
	/**
	 * Create a new page element.
	 *
	 * By default an element with no ID attribute is created.
	 *
	 * @param $id string _optional_ The ID for the page element.
	 */
	public function __construct(?string $id = null) {
		$this->m_attributes["class"] = [];
		$this->setId($id);
	}

	/**
	 * Generate a new unique ID for an element.
	 *
	 * This is an internal helper function that can be used by implementing
	 * classes to generate a unique ID where one is needed but has not been
	 * explicitly provided by the creator of the element object.
	 *
	 * The generated ID is "elementuid{nn}" where {nn} is a sequence number
	 * starting with 1 for the first UID generated.
	 *
	 * @return string A new unique ID.
	 */
	protected static function generateUid(): string {
		static $s_seq = 0;
		$s_seq++;
		return "elementuid{$s_seq}";
	}

	/**
	 * Fetch the URLs of the runtime support scripts for the element.
	 *
	 * Most simple elements won't need to implement this method. More complex elements might require some runtime logic,
	 * which is implemented in scripts. This method provides the URLs for the scripts required for the type of
	 * element.
	 *
	 * For elements provided by the equit library, the script URLs are relative to the installation path of the
	 * library. This usually means you need to prefix the URL with the installation path in the <script> element on
	 * the page. Alternatives include setting up path aliases in your web server configuration or using symbolic links.
	 *
	 * @return array[string] The support javascript URLs.
	 */
	public static function runtimeScriptUrls(): array {
		return [];
	}

	/**
	 * Set the value of a named attribute.
	 *
	 * This is an internal helper method that implementing classes can use to set attribute values for the attributes
	 * that belong to the main element type they are implementing. This makes it easy to add attributes without having
	 * to write specific code to implement them.
	 *
	 * The value can be a string or an array of strings.
	 *
	 * Subclasses should obviously validate the attribute name and value before calling this method.
	 *
	 * @warning **Never** set the class attribute to anything other than an array of strings (or an empty array).
	 *
	 * @param $name string The name of the attribute to set.
	 * @param $value string|array|null The value for the attribute.
	 */
	protected function setAttribute(string $name, $value): void {
		assert(is_string($value) || is_null($value) || is_array($value), "invalid attribute value");

		if(is_array($value)) {
			foreach($value as $v) {
				assert(is_string($v), "invalid (non-string) element in array type attribute value");
			}
		}

		$this->m_attributes[$name] = $value;
	}

	/**
	 * Fetch the value of an attribute.
	 *
	 * @param $name string The name of the attribute to fetch.
	 *
	 * @return string The attribute value, or _null_ if the attribute is not defined.
	 */
	public function attribute(string $name) {
		return $this->m_attributes[$name] ?? null;
	}

	/**
	 * Set the ID of the page element.
	 *
	 * @param $id string The ID.
	 */
	public function setId(?string $id): void {
		$this->setAttribute("id", $id);
	}

	/**
	 * Fetch the ID of the page element.
	 *
	 * @return string The ID, or _null_ if no ID has been set.
	 */
	public function id(): ?string {
		return $this->m_attributes["id"] ?? null;
	}

	/**
	 * Set the elements"s class.
	 *
	 * Elements can have multiple classes. This method will discard all of the
	 * element"s existing classes and replace them with the single class
	 * provided.
	 *
	 * @note This method is called setClassName because the equivalent getter
	 * is named classNames() owing to the fact that `class` is a PHP keyword
	 * and therefore can"t be used as a method name.
	 *
	 * \see setClassNames(), addClassName()
	 *
	 * @param $class string The class.
	 */
	public function setClassName(string $class): void {
		$this->setAttribute("class", [$class]);
	}

	/**
	 * Set the element"s classes.
	 *
	 * Elements can have multiple classes. This method will discard all of the
	 * element"s existing classes and replace them with the classes provided in
	 * the array.
	 *
	 * ## Note
	 * This method is called setClassNames because the equivalent getter
	 * is named classNames() owing to the fact that `class` is a PHP keyword
	 * and therefore can't be used as a method name.
	 *
	 * @see setClassName(), addClassName()
	 *
	 * @param $classes [string] The classes. */
	public function setClassNames(?array $classes): void {
		$this->setAttribute("class", $classes ?? []);
	}

	/**
	 * Add a class to the element.
	 *
	 * Elements can have multiple classes. This method will add a class to its
	 * existing list of classes.
	 *
	 * ## Note
	 * This method is called addClassName because the equivalent getter
	 * is named classNames() owing to the fact that `class` is a PHP keyword
	 * and therefore can"t be used as a method name.
	 *
	 * @see setClassNames(), setClassName()
	 *
	 * @param $class string The class to add.
	 *
	 * @return void.
	 */
	public function addClassName(string $class): void {
		$this->m_attributes["class"][] = $class;
	}

	/**
	 * Remove a class from the element.
	 *
	 * Elements can have multiple classes. This method will remove a class from
	 * its existing list of classes.
	 *
	 * ## Note
	 * This method is called removeClassName because the equivalent getter
	 * is named classNames() owing to the fact that `class` is a PHP keyword.
	 * and therefore can"t be used as a method name.
	 *
	 * @param $class string The class to remove.
	 *
	 * @return void.
	 */
	public function removeClassName(string $class): void {
		$classes =& $this->m_attributes["class"];

		while(false !== ($i = array_search($class, $classes))) {
			array_splice($classes, $i, 1);
		}
	}

	/**
	 * Fetch the element's list of classes.
	 *
	 * An empty array will be returned if the element has no classes.
	 *
	 * @return array[string] The classes.
	 */
	public function classNames(): array {
		return $this->m_attributes["class"];
	}

	/**
	 * Fetch the element's list of classes.
	 *
	 * The element's classes are provided as a single whitespace-separated
	 * string. This is how they are formatted in the HTML \b class attribute.
	 *
	 * The string will be empty if the element has no classes.
	 *
	 * @return string The class names.
	 */
	public function classNamesString(): string {
		return implode(" ", $this->m_attributes["class"]);
	}

	/**
	 * Set the style of the page element.
	 *
	 * The style is assumed to be CSS. It is used for the `style` attribute in
	 * the generated HTML element.
	 *
	 * @param $css string The style.
	 *
	 * @return void.
	 */
	public function setStyle(?string $css): void {
		$this->setAttribute("style", $css);
	}

	/**
	 * Fetch the style attribute of the page element.
	 *
	 * @return string The style, or _null_ if no style has been set.
	 */
	public function style(): ?string {
		return $this->m_attributes["style"];
	}

	/**
	 * Set a data item on the element.
	 *
	 * Set a `null` value to unset the data item.
	 *
	 * @param string $name The name of the data item to set. You **must** ensure this is valid.
	 * @param string|null $value The value to set.
	 */
	public function setData(string $name, ?string $value): void {
		$this->setAttribute("data-$name", $value);
	}

	/**
	 * Fetch a data attribute from the element.
	 *
	 * The data returned will be _null_ if no such data item is set.
	 *
	 * @param $name string The name of the data item to fetch.
	 *
	 * @return null|string The tooltip.
	 */
	public function data($name): ?string {
		return $this->attribute("data-$name");
	}

	/**
	 * Generate all the attributes for the element.
	 *
	 * All the attributes stored using setAttribute() are generated as HTML. The
	 * HTML fragment is XHTML1.0 Strict, UTF-8 encoded. This is compatible with
	 * XHTML5.
	 *
	 * @return string The HTML fragment for the element"s attributes.
	 */
	protected function emitAttributes(): string {
		$ret = "";

		foreach($this->m_attributes as $name => $attr) {
			if(!isset($attr) || (is_array($attr) && empty($attr))) {
				continue;
			}

			$ret .= $this->emitAttribute($name, $attr);
		}

		return $ret;
	}

	/**
	 * Emit a single HTML element attribute.
	 *
	 * Providing an invalid attribute value will cause your program to fail.
	 *
	 * @param string $attrName The attribute name.
	 * @param string|array $attrValue The attribute value.
	 *
	 * @return string
	 */
	protected function emitAttribute(string $attrName, $attrValue): string {
		$ret = " $attrName=\"";

		if(is_string($attrValue)) {
			$ret .= html($attrValue);
		}
		else if(is_array($attrValue)) {
			$ret .= html(implode(" ", $attrValue));
		}
		else {
			assert(false, "found invalid attribute value for attribute $attrName");
		}

		$ret .= "\"";
		return $ret;
	}
	/**
	 * Get the HTML for the element.
	 *
	 * Implementing classes must reimplement this method to produce the HTML for
	 * the element. The HTML is required to be valid XHTML5, encoded as UTF-8.
	 *
	 * @return string The element HTML.
	 */
	public abstract function html(): string;

	/**
	 * Output the element.
	 *
	 * The element is output to the current output stream. This is usually
	 * standard output, which is usually what is sent to the user agent.
	 */
	final public function output(): void {
		echo $this->html();
	}

	/** @var array The element attributes. */
	private $m_attributes = [];
}
