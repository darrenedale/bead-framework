<?php

/**
 * Defines the Layout interface.
 *
 * ### Dependencies
 * No dependencies.
 *
 * ### Changes
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
 * - (2013-12-22) class created.
 *
 * @file Layout.php
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @package libequit
 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\PageElement;

/** An interface for page element layouts.
 *
 * This class defines the interface that must be implemented to provide a layout for a web page.
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
 * @class Layout
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @package libequit
 * @ingroup libequit
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
abstract class Layout {
	private $m_classNames = [];
	private $m_id = null;

	/**
	 * Create a new layout.
	 *
	 * By default, a layout with no ID is created.
	 *
	 * @param $id string _optional_ The ID for the layout.
	 */
	public function __construct(?string $id = null) {
		$this->setId($id);
	}

	/**
	 * Fetch the layout's ID.
	 *
	 * @return string The ID, or `null` if none has been set.
	 */
	public function id() {
		return $this->m_id;
	}

	/**
	 * Set the layout's ID.
	 *
	 * The ID can be set to `null` to unset the current ID. If the layout has
	 * no ID when the HTML is generated, its \b id attribute will be omitted.
	 *
	 * @param $id `string` The ID.
	 *
	 * @return bool `true` if the ID was set, `false` otherwise.
	 */
	public function setId($id) {
		if(is_string($id) || is_null($id)) {
			$this->m_id = $id;
			return true;
		}

		AppLog::error('invalid id', __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set the layout's class.
	 *
	 * @param $class `string` The class.
	 *
	 * Layouts can have multiple classes. This method will discard all of the
	 * layout's existing classes and replace them with the single class
	 * provided.
	 *
	 * @note This method is called setClassName because the equivalent getter is named classNames() owing to the fact
	 * that \b class is a PHP keyword and therefore can't be used as a method name.
	 *
	 * \see setClassNames(), addClassName()
	 *
	 * @return bool `true` if the class was set, `false` otherwise.
	 */
	public function setClassName($class) {
		if(is_string($class)) {
			$class = [$class];
		}

		return $this->setClassNames($class);
	}

	/**
	 * Set the layout's classes.
	 *
	 * @param $class `[string]` The classes.
	 *
	 * Layouts can have multiple classes. This method will discard all of the
	 * layout's existing classes and replace them with the classes provided in
	 * the array.
	 *
	 * @note This method is called setClassNames because the equivalent getter
	 * is named classNames() owing to the fact that \b class is a PHP keyword
	 * and therefore can't be used as a method name.
	 *
	 * \sa setClassName(), addClassName()
	 *
	 * @return bool `true` if the classes were set, `false` otherwise.
	 */
	public function setClassNames($class) {
		if(is_array($class)) {
			$this->m_classNames = $class;
			return true;
		}

		AppLog::error('invalid class names', __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Add a class to the layout.
	 *
	 * Layouts can have multiple classes. This method will add a class to its
	 * existing list of classes.
	 *
	 * @note Even though it acts on the **class** HTML attribute, this method is called `addClassName` because the
	 * equivalent getter is named `classNames` owing to the fact that `class` is a PHP keyword and therefore can't be
	 * used as a method name.
	 *
	 * @param string $class The class to add.
	 *
	 * @see setClassNames(), setClassName()
	 *
	 * @return bool `true` if the class was added, `false` otherwise.
	 */
	public function addClassName($class) {
		if(!is_string($class)) {
			AppLog::error('invalid class name', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$this->m_classNames[] = $class;
		return true;
	}

	/**
	 * Remove a class from the layout.
	 *
	 * @param $class `string` The class to remove.
	 *
	 * Layouts can have multiple classes. This method will remove a class from
	 * its existing list of classes.
	 *
	 * @note This method is called removeClassName because the equivalent getter
	 * is named classNames() owing to the fact that \b class is a PHP keyword
	 * and therefore can't be used as a method name.
	 *
	 * @return bool `true` if the class was removeed, `false` otherwise.
	 */
	public function removeClassName($class) {
		if(!is_string($class)) {
			AppLog::error('invalid class name', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		while(false !== ($i = array_search($class, $this->m_classNames))) {
			array_splice($this->m_classNames, $i, 1);
		}

		return true;
	}

	/**
	 * Fetch the layout's list of classes.
	 *
	 * An empty array will be returned if the layout has no classes.
	 *
	 * @return array[string] The classes.
	 */
	public function classNames() {
		return $this->m_classNames;
	}

	/**
	 * Fetch the layout's list of classes.
	 *
	 * The layout's classes are provided as a single whitespace-separated
	 * string. This is how they are formatted in the HTML \b class attribute.
	 *
	 * The string will be empty if the layout has no classes.
	 *
	 * @return string The class names.
	 */
	public function classNamesString() {
		return implode(' ', $this->m_classNames);
	}

	/**
	 * Add an element to the layout.
	 *
	 * @param $element PageElement is the element to add.
	 *
	 * The element is added to the layout. Further parameters can be defined that allow for customisation of exactly how
	 * the element is added (for example, an order number or coordinate).
	 *
	 * @return bool `true` if the element was added, `false` otherwise.
	 */
	public abstract function addElement(PageElement $element);


	/**
	 * Add a sub-layout to the layout.
	 *
	 * @param $layout Layout The layout to add.
	 *
	 * The sub-layout should be added to the layout. Further parameters can be defined that allow for customisation of
	 * exactly how the layout is added (for example, an order number or coordinate). Implementing classes should not
	 * alter the layout that is provided - it should be added as-is.
	 *
	 * @return bool `true` if the layout was added, `false` otherwise.
	 */
	public abstract function addLayout(Layout $layout);


	/**
	 * Fetch the children in the layout.
	 *
	 * This method should return all the items (elements and layouts) that are
	 * included in the layout. They must be provided as an array. The order of
	 * the children is not dictated by the definition of this method, but it
	 * should be an order that is logical and consistent with the general
	 * layout paradigm implemented by the class. The array must not be multi-
	 * dimensional.
	 *
	 * @return array[LibEquit\PageElement, Layout] the children.
	 */
	public abstract function children();


	/**
	 * Fetch the child elements in the layout.
	 *
	 * This method should return all the elements (not layouts) that are
	 * included in the layout. They must be provided as an array. The order of
	 * the elements is not dictated by the definition of this method, but it
	 * should be an order that is logical and consistent with the general
	 * layout paradigm implemented by the class. The array must not be multi-
	 * dimensional.
	 *
	 * @return array[LibEquit\PageElement] the children.
	 */
	public abstract function elements();


	/**
	 * Fetch the child layouts in the layout.
	 *
	 * This method should return all the layouts (not elements) that are
	 * included in the layout. They must be provided as an array. The order of
	 * the layouts is not dictated by the definition of this method, but it
	 * should be an order that is logical and consistent with the general
	 * layout paradigm implemented by the class. The array must not be multi-
	 * dimensional.
	 *
	 * @return array[Layout] the children.
	 */
	public abstract function layouts();


	/**
	 * Fetch the number of children the layout has.
	 *
	 * The number of children is the sum of the number of sub-layouts and the
	 * number of form elements included in the layout. Implementing classes must
	 * not query the sub-layouts for their child counts and add that; each
	 * layout counts only as one child.
	 *
	 * @return int The number of children.
	 */
	public abstract function childCount();


	/**
	 * Fetch the number of form elements the layout has.
	 *
	 * The number of children is the number of form elements included in the
	 * layout.
	 *
	 * @return int The number of children.
	 */
	public abstract function elementCount();


	/**
	 * Fetch the number of sub-layouts the layout has.
	 *
	 * The number of children is the number of sub-layouts included in the
	 * layout.
	 *
	 * @return int The number of children.
	 */
	public abstract function layoutCount();


	/**
	 * Generates the layout markup appropriate for the document type.
	 *
	 * Each child element and layout must be asked to generate its own markup
	 * within the structure defined by the layout class.
	 *
	 * @return string The HTML for the layout, or `null` on error.
	 */
	public abstract function html();
}

;
