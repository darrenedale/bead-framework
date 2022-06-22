<?php

/**
 * Defines the _FileSelect_ class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Page.php
 * - classes/equit/LibEquit\PageElement.php
 *
 * ### Changes
 * - (2018-09) Uses traits for common HTML attributes.
 * - (2018-09) Uses string type hints.
 * - (2017-05) Updated documentation. Migrated to `[]` syntax from `array()`.
 * - (2013-12-10) First version of this file.
 *
 * @file FileSelect.php
 * @author Darren Edale
 * @version 0.9.2
 * @package bead-framework
 * @version 0.9.2 */

namespace Equit\Html;

use Equit\AppLog;
use Equit\Html\HasNameAttribute;
use Equit\Html\HasTooltip;
use Equit\Html\Element;

/**
 * A file selector for inclusion in forms.
 *
 * This is a lightweight, general-purpose file selector that allows the user to choose a file to upload. Objects can
 * have a number of MIME types added to hint to the user agent what MIME types the user should be allowed to choose.
 * Support for this is dependent on the user agent as it is only a hint.
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
 * @class FileSelect
 * @author Darren Edale
 * @package bead-framework
 */
class FileSelect extends Element {
	use HasTooltip;
	use HasNameAttribute;

	/** @var array The HTML element attributes for the FileSelect element. */
	private static $s_fileSelectAttributeNames = ["name", "title", "accept"];

	/**
	 * Create a new file select widget.
	 *
	 * By default, a widget with no ID is created.
	 *
	 * @param $id string _optional_ The ID of the file select widget.
	 */
	public function __construct(?string $id = null) {
		parent::__construct($id);

		foreach(self::$s_fileSelectAttributeNames as $name) {
			$this->setAttribute($name, null);
		}
	}

	/**
	 * Fetch the mime types acceptable to the file select widget.
	 *
	 * @return array[string] The MIME types, or _null_ if none have been set.
	 */
	public function mimeTypes(): ?array {
		$ret = $this->attribute("accept");

		if(is_string($ret)) {
			$ret = explode(",", $ret);
		}

		return $ret;
	}

	/**
	 * Set the MIME types acceptable to the file select widget.
	 *
	 * The types can be provided either as a comma-separated string containing all the MIME types, or as an array of
	 * strings, each representing a single MIME type. The content of the array is not validated, it is up to your code
	 * to ensure it provides valid content. Invalid content is likely to give rise to a runtime error.
	 *
	 * You can set the types to _null_ to remove all current acceptable MIME types and reset the file select widget to
	 * accept any MIME type.
	 *
	 * @param $types array[string]|string The acceptable MIME types.
	 *
	 * @return bool _true_ if the MIME types were set, _false_ otherwise.
	 */
	public function setMimeTypes($types): bool {
		if(is_array($types)) {
			$types = implode(",", $types);
		}

		if(is_string($types) || is_null($types)) {
			$this->setAttribute("accept", $types);
			return true;
		}

		AppLog::error("invalid MIME types", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Add a MIME type to the set of types acceptable to the file select widget.
	 *
	 * @param $type string The MIME type to add.
	 *
	 * It is possible to add the same MIME type more than once, though rarely advised. You should try to avoid this in
	 * your code. If the provided type is an empty string, it is not added.
	 *
	 * @return bool _true_ if the MIME type was added, _false_ if not.
	 */
	public function addMimeType(string $type): bool {
		if(empty($type)) {
			AppLog::error("invalid MIME type", __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		$accept = $this->attribute("accept");

		if(empty($accept)) {
			$this->setAttribute("accept", $type);
		}
		else {
			$this->setAttribute("accept", "$accept,$type");
		}

		return true;
	}

	/**
	 * Generate the HTML for the widget.
	 *
	 * This method generates UTF-8 encoded XHTML5.
	 *
	 * @return string The HTML.
	 */
	public function html(): string {
		return "<input type=\"file\"" . $this->emitAttributes() . " />";
	}
}
