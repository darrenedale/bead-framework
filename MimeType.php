<?php

/**
 *
 * Defines the MimeType class.
 *
 * ### Dependencies
 * No dependencies.
 *
 * ### Changes
 * - (2017-05) Fixed initialisation error for $m_description member. Updated
 *   documentation. Migrated to `[]` syntax from array().
 * - (2013-12-10) First version of this file.
 *
 * @file MimeType.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit;

/**
 * Abstract representation of a MIME type.
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
 * @class MimeType
 * @author Darren Edale
 * @ingroup libequit
 * @package libequit
 *
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 */
class MimeType {
	/** The type. */
	private $m_type = null;

	/** The extensions. */
	private $m_extensions = [];

	/** The description. */
	private $m_description = null;

	/** The internal db of known MIME types. */
	private static $s_knownTypeMap = null;

	/**
	 * Create a new MimeType.
	 *
	 * @param $type `string` The MIME type.
	 * @param $extensions array[string] _optional_ The filename extensions for the MIME
	 * type.
	 * @param $description string _optional_ The human-readable description.
	 *
	 * If the set of extensions provided is not empty, the first extension in
	 * the array is considered the default extension.
	 */
	public function __construct($type, $extensions = [], $description = null) {
		$this->setType($type);
		$this->setExtensions($extensions);
		$this->setDescription($description);
	}

	/**
	 * Set the MIME type.
	 *
	 * @param $type `string` The type.
	 *
	 * @return bool `true` if the type was set, `false` if not.
	 */
	public function setType($type) {
		if(is_string($type)) {
			$this->m_type = $type;
			return true;
		}

		AppLog::error('invalid type', __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the MimeType's type.
	 *
	 * @return string The type
	 */
	public function type() {
		return $this->m_type;
	}

	/**
	 * Set a list of extensions for the MimeType.
	 *
	 * @note The first extension in the provided array will be considered the
	 * default extension.
	 *
	 * @param $exts array[string] The set of filename extensions.
	 *
	 * @return bool `true` if the extensions were set, `false` otherwise.
	 */
	public function setExtensions($exts) {
		if(is_array($exts)) {
			foreach($exts as $ext) {
				if(!is_string($ext)) {
					AppLog::error('invalid extension', __FILE__, __LINE__, __FUNCTION__);
					return false;
				}
			}

			$this->m_extensions = array_unique($exts);
			return true;
		}

		AppLog::error('invalid extensions', __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set a single extension as the only one for the MimeType.
	 *
	 * @param $ext `string` The extension.
	 *
	 * This is a convenience method for `setExtensions([$ext])`.
	 *
	 * @return bool `true` if the extension was set, `false` otherwise.
	 */
	public function setExtension($ext) {
		return $this->setExtensions([$ext]);
	}

	/**
	 * Add a single extension to the MimeType.
	 *
	 * @param $ext `string` The extension to add.
	 *
	 * @return bool `true` if the extension was added, `false` if not.
	 */
	public function addExtension($ext) {
		if(!is_string($ext)) {
			AppLog::error('invalid extension', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		if(!in_array($ext, $this->m_extensions)) {
			$this->m_extensions[] = $ext;
		}

		return false;
	}

	/**
	 * Clear a single extension from the MimeType.
	 *
	 * @param $ext `string` The extension to remove.
	 *
	 * After a successful call, the set of extensions will no longer contain
	 * `$ext`.
	 *
	 * @return bool `true` if the removal was successful, `false` otherwise.
	 */
	public function removeExtension($ext) {
		if(!is_string($ext)) {
			AppLog::error('invalid extension', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		while(false !== ($i = array_search($ext, $this->m_extensions))) {
			array_splice($this->m_extensions, $i, 1);
		}

		return true;
	}

	/**
	 * Clear all the MimeType's extensions.
	 *
	 * @return bool `true`.
	 */
	public function clearExtensions() {
		$this->m_extensions = [];
		return true;
	}

	/**
	 * Fetch the MimeType's default extension.
	 *
	 * @return string The default extension, or `null` if no extensions
	 * have been set.
	 */
	public function extension() {
		if(0 < count($this->m_extensions)) {
			return $this->m_extensions[0];
		}

		return null;
	}

	/**
	 * Fetch the MimeType's extensions.
	 *
	 * @return array[string] The extensions.
	 */
	public function extensions() {
		return $this->m_extensions;
	}

	/**
	 * Set the MimeType's description.
	 *
	 * @param $desc `string` The description for the MIME type.
	 *
	 * @return bool `true` if the extension was set, `false` otherwise.
	 */
	public function setDescription($desc) {
		if(is_string($desc) || is_null($desc)) {
			$this->m_description = $desc;
			return true;
		}

		return false;
	}

	/**
	 * Fetch the MimeType's description.
	 *
	 * @return string The description for the MIME type, or `null`
	 * if it does not have one.
	 */
	public function description() {
		return $this->m_description;
	}

	/**
	 * Internal helper to read the database of known MIME types.
	 *
	 * @param $force `boolean` Whether or not to force a re-read of the database.
	 */
	private static function readKnownTypes($force = false) {
		if($force || is_null(self::$s_knownTypeMap)) {
			self::$s_knownTypeMap                                      = [];
			self::$s_knownTypeMap['text/plain']                        = new MimeType('text/plain', ['txt'], 'Plain text');
			self::$s_knownTypeMap['text/csv']                          = new MimeType('text/csv', ['csv'], 'CSV spreadsheet');
			self::$s_knownTypeMap['text/html']                         = new MimeType('text/html', ['html', 'htm', 'shtml'], 'HTML');
			self::$s_knownTypeMap['application/x-endnote-tagged-text'] = new MimeType('application/x-endnote-tagged-text', ['txt'], 'Endnote tagged format');
		}
	}

	/**
	 * Fetch an array of all known MIME types.
	 *
	 * @return array[MimeType] All known MIME types.
	 */
	public static function allKnownTypes() {
		self::readKnownTypes();
		return self::$s_knownTypeMap;
	}

	/**
	 * Fetch the details of a known MIME type.
	 *
	 * @param $type `string` The MIME type to find.
	 *
	 * @return MimeType|null The details of the requested type, or `null` if it
	 * was not found.
	 */
	public static function fetch($type) {
		self::readKnownTypes();

		if(array_key_exists($type, self::$s_knownTypeMap)) {
			return self::$s_knownTypeMap[$type];
		}

		return null;
	}
}
