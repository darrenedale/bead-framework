<?php

/**
 * Defines the TaggedTextFileReader class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 *
 * ### Changes
 * - (2017-04) First version of this file.
 *
 * @file TaggedTextFileReader.php
 * @author Darren Edale
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018April 2017
 */

namespace Equit;

use StdClass;

require_once("classes/equit/AppLog.php");

/**
 * A sequential reader of tagged text format files.
 *
 * This class reads the content of a tagged text format files. The tagged text format is a plain-text format where
 * individual records are represented by consecutive non-empty lines of text. Each line is _tagged_ with a field
 * identifier, followed by whitespace, followed by the field content. Records are separated by one or more empty lines.
 * The _Refer_ format, used by _EndNote_, is an example of a tagged text file format.
 *
 * Tags generally take the form of a _%_ character followed by one or more alphanumeric characters. Field content
 * usually starts from the first non- whitespace character after the tag.
 *
 * In some cases, untagged lines that contain field content are permitted. These are known as _continuation lines_, and
 * they act as a continuation of the content of the field from the previous line. If supported, continuation lines may
 * not be first in a record (since there is no field to continue).
 *
 * Some tags are permitted to be present more than once per record, in which case the associated field contains either
 * an array composed of the field content from each line tagged with the field's tag, or a concatenation of that
 * content. Which is used is determined by the specific tagged format being processed.
 *
 * In ABNF, the format can be expressed as:
 *
 *     tagged-text-file: newline | newline tagged-text-file | record tagged-text-file
 *
 *     record := record-content newline
 *
 *     record-content: tag-line | record-content record-line
 *
 *     record-line := tag-line | continuation-line
 *
 *     tag-line := tag whitespace field-content newline
 *
 *     continuation-line := field-content newline
 *
 *     tag := "%" alphanumeric-char | tag alphanumeric-char
 *
 *     whitespace := " " | tab | whitespace whitespace
 *
 *     field-content := char | char field-content
 *
 *     newline := the newline character (the byte 0x0a in ASCII)
 *
 *     alphanumeric-char := "A" to "Z", "a" to "z", "1" to "9"
 *
 *     tab := the tab character (the byte 0x09 in ASCII)
 *
 *     char := any character in the file's encoding's valid range, except newline
 *
 * In most cases, a subclass implementing a parser for a specific type of tagged text file format need only reimplement
 * three methods:
 * - _applyTag()_
 * - _emptyRecord()_
 * - _validateRecord()_
 *
 * Strictly speaking, _emptyRecord()_ is not required, but it's usually a good idea to provide a record with a full
 * complement of properties in a default (empty) state to support cases where there are optional tags for records. (In
 * such cases, without a default state, returned record objects might be missing properties.)
 *
 * ## Creating readers for specific formats
 * _applyTag()_ should be reimplemented to ensure that each record parsed has its properties set correctly according to
 * the tag content read from the file. The method is called for each tag encountered for each record present in the
 * file. Thus, the calls to _applyTag()_ iteratively build toward the complete content of the record. The _applyTag()_
 * method should examine the provided tag and apply its content to the provided record in the appropriate fashion. If
 * the tag is not recognised, has invalid content, or would put the record into a state which it knows cannot possibly
 * result in a valid record, the _applyTag()_ method should set the error code and message and return _false_ to
 * indicate that the tag could not be applied. If the tag was applied, it should return _true_.
 *
 * A set of convenience helper methods is provided to aid _applyTag()_ reimplementations:
 * - _parseArrayTagContent()_ will extract an array of items from a tag's content, given the appropriate delimiter. This
 *   works much like the PHP _explode()_ built-in, except that it ensures that each item in the array is trimmed of
 *   leading and trailing whitespace.
 * - _applyRecordProperty()_ will set a record's given property to the value provided. If the record already has that
 *   property set to something non-empty, it generates an error unless the _SilentlySkipDuplicateTags_ flag is set.
 * - _appendRecordProperty()_ will update a record's given property by concatenating the value provided to the end of
 *   the property's existing value. An optional concatenating string can be provided, which will be inserted between the
 *   existing value and the provided value.
 * - _mergeRecordProperty()_ can be used like _appendRecordProperty()_ but for cases where array content needs to be
 *   merged. It expects the existing property of the record being updated to be either empty or an array, and the value
 *   to merge to be either a string that can be converted to an array using _parseArrayTagContent()_ or an array. The
 *   _KeepDuplicateListContent_ flag is honoured.
 *
 * Both _appendRecordProperty()_ and _mergeRecordProperty()_ are safe to use when the property being updated is empty.
 * They will both act like you wold expect a notional _setRecordProperty()_ to behave if the record's existing property
 * is empty.
 *
 * Finally, _validateRecord()_ can be reimplemented to validate the content of a record once it has been read in full
 * from the file, but before it is returned by _nextRecord()_. This provides subclasses with an opportunity to identify
 * errors that are not necessarily apparent while the record is being constructed (e.g. during construction a record
 * might be in a state that is invalid for a finished record but is valid for a record that has more fields to read).
 * _validateRecord()_ must return _true_ if the record is valid, _false_ if not. If it returns _false_, it _must_ set
 * the last error code, and preferably the last error message as well. The default implementation simply returns _true_,
 * effectively accepting any record as valid.
 *
 * Subclasses may define their own error codes. The class constant _ErrUser_ is provided as a starting point for
 * subclass-defined error codes. Subclasses may not use any error code that is smaller than _ErrUser_ and are encouraged
 * to define their custom error codes by referencing _ErrUser_ (e.g. `const MyCustomErrorCode = ErrUser + 1;`, etc.).
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
 * @class TaggedTextFileReader
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
class TaggedTextFileReader {
	/** Return code indicating success. */
	const ErrOk = 0;

	/** Return code indicating no error. */
	const ErrNone = self::ErrOk;

	/** Return code indicating the reader has no file path set. */
	const ErrNoPath = 1;

	/** Return code indicating the reader could not open the file. */
	const ErrFailedOpeningFile = 2;

	/** Return code indicating the reader is not open. */
	const ErrNotOpen = 3;

	/**
	 * Return code indicating the reader encountered a tag it does
	 * not recognise.
	 */
	const ErrUnrecognisedTag = 4;

	/**
	 * Return code indicating the reader encountered a duplicate tag.
	 *
	 * In cases where a tag is intended to appear only one per record, this
	 * error code should be used when more than one instanceo of the tag
	 * was found in a record.
	 */
	const ErrUnexpectedMultipleTag = 5;

	/** Return code indicating the reader encountered an invalid line . */
	const ErrInvalidLineFound = 7;

	/** Base error code for subclass-specific errors. */
	const ErrUser = 1000;

	/** Returned by nextRecord() when there are no more records to read. */
	const NoMoreRecords = -1;

	/** Returned by nextRecord() when a malformed record was encountered. */
	const MalformedRecord = -2;

	/** A line type that could not be determined. */
	const InvalidLineType = 0;

	/** A line that contains a new tag. */
	const NewTagLineType = 1;

	/** A line that is a continuation of the previous line. */
	const ContinuationLineType = 2;

	/** A line that is empty. */
	const EmptyLineType = 3;

	/**
	 * Flag to ignore the content of any tags that are not recognised.
	 *
	 * Ordinarily reader subclasses should work in a relatively strict mode and abort if they come across tag they don't
	 * recognise. Setting this flag should alter that behaviour so that subclasses silently ignore any tags they don't
	 * recognise, along with their content.
	 *
	 * @flag
	 */
	const SilentlyIgnoreUnrecognisedTags = 0x01;

	/**
	 * Flag to ignore the content of duplicated tags that should only appear once.
	 *
	 * Some tags are allowed to appear multiple times in a record, whereas some may only appear once per record. In the
	 * latter case, where a tag is found more than once, reader subclasses should ordinarily abort. With this flag set,
	 * the subclass should silently skip over and ignore the content of the second (and subsequent) instances of any tag
	 * that is only supposed to appear once per record.
	 *
	 * @flag
	 */
	const SilentlySkipDuplicateTags = 0x02;

	/**
	 * Flag to allow records to contain continuation lines.
	 *
	 * In strict format files, each line in a record is required to be tagged with the field to which its content
	 * belongs. In looser format files, continuation lines cab be present which are not tagged themselves but rather
	 * continue the content for the tag on the previous line.
	 *
	 * Use this flag to enable continuation lines to be parsed.
	 *
	 * @flag
	 */
	const PermitContinuationLines = 0x04;

	/**
	 * Flag to parse lines in strict mode.
	 *
	 * In strict format files, each line in a record is required to have a space after its tag, even if the content part
	 * of the line is intentionally empty. That is, if the _%K_ tag is intended to have no content, the line must be
	 * "_%K _" not "_%K_".
	 *
	 * By default readers will tolerate the absence of the space after the tag, and interpret such a line as a tag with
	 * no content rather than an error. (This is because text editors can be configured to automatically trim trailing
	 * whitespace at the end of lines, and it is more often the case that a tag on its own on a line represents an
	 * intentionally empty tag rather than a tag whose content has been omitted in error.)
	 *
	 * If you wish to process contentless lines in strict mode, set this flag.
	 *
	 * @flag
	 */
	const StrictLineFormat = 0x08;

	/**
	 * Flag to keep duplicate items in list-type tags.
	 *
	 * When reading a record, some properties are composed of a list of items. In most cases, the list of items is
	 * intended to be a mathematical set in which no item appears more than once. Ordinarily, the reader will ensure
	 * this is the case by silently discarding duplicate items. With this flag set, any duplicate items in a list-type
	 * tag will be kept and a warning will be generated.
	 *
	 * @flag
	 */
	const KeepDuplicateListContent = 0x10;

	/**
	 * Flag to discard empty items when parsing list-type tags.
	 *
	 * When reading list-type items, consecutive delimiters are usually interpreted as empty items. For example, in
	 * the list _one,two,,four_ there are four items (_one_, _two_, an empty item and _four_). With this flag set,
	 * any list items that are found to be empty will be discarded and the list will end up containing only the
	 * non-empty items (e.g. _one_, _two_ and _four_ in the above example).
	 *
	 * @flag
	 */
	const DiscardEmptyListItems = 0x20;

	/** @var int The flags controlling the reader's mode of operation. */
	private $m_flags = 0x00;

	/** @var string|null The path to the file to read. */
	private $m_path = null;

	/** @var resource|null The file handle of the file, once opened. */
	private $m_fh = false;

	/** @var int The index of the next line the reader will read. */
	private $m_nextLineIndex = -1;

	/** @var int The code for the last error that occurred. */
	private $m_lastError = self::ErrNone;

	/** @var string The message for the last error that occurred. */
	private $m_lastErrorMessage = "";

	/**
	 * Create a new reader.
	 *
	 * By default a reader with no file and no flags is created.
	 *
	 * @param $path string|null _optional_ The path to the file to read.
	 * @param $flags int _optional_ Bitmask of flags controlling some features of how the reader operates.
	 */
	public function __construct($path = null, int $flags = 0x00) {
		$this->setPath($path);
		$this->setFlags($flags);
	}

	/** Destructor. */
	public function __destruct() {
		$this->close();
	}

	/**
	 * Detect and skip the UTF-8 byte order mark.
	 *
	 * Some files may have the UTF-8 BOM set. This method detects and skips the BOM when the file is first opened. If
	 * there is no BOM, no bytes in the file are skipped.
	 */
	private function skipByteOrderMark() {
		/* assumes UTF-8 encoded file */
		$detect = fread($this->m_fh, 3);

		if("\xef\xbb\xbf" !== $detect) {
			/* no BOM, seek back to start of detection read */
			fseek($this->m_fh, 0 - strlen($detect), SEEK_CUR);
		}
	}

	/**
	 * Set the path to the file to read.
	 *
	 * The path can be null to unset the current path.
	 *
	 * If the reader is currently open it will be closed before the path is set. This is true even if the path being set
	 * is the same as the path currently open in the reader. If the path is set, the reader will need to be opened
	 * before it can be read.
	 *
	 * @param $path string|null The path to the file.
	 *
	 * @return bool _true_ if the path was set, _false_ otherwise.
	 */
	public function setPath($path) {
		if(is_null($path) || is_string($path)) {
			$this->close();
			$this->m_path = $path;
			return true;
		}

		AppLog::error('invalid path', __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the path to the file being read.
	 *
	 * @return string The path, or _null_ if no path is set.
	 */
	public function path() {
		return $this->m_path;
	}

	/**
	 * Set the flags that control how the reader operates.
	 *
	 * The flags will be operational immediately, even if the reader is already
	 * open. Setting the flags does not close the reader, nor does it move its
	 * internal cursor.
	 *
	 * @param $flags int A bitmask of flags.
	 *
	 * @return bool _true_ if the flags were set, _false_ otherwise.
	 */
	public function setFlags(int $flags) {
		$this->m_flags = $flags;
		return true;
	}

	/**
	 * Fetch the flags controlling how the reader operates..
	 *
	 * @return int The flags.
	 */
	public function flags() {
		return $this->m_flags;
	}

	/**
	 * Check whether a set of flags are set.
	 *
	 * @param $flags int A bitmask of flags to check for.
	 *
	 * @return bool _true_ if all of the provided flags are set, _false_ if one or more of the provided flags is not
	 * set.
	 */
	public function flagsAreSet(int $flags) {
		return $flags == ($this->m_flags & $flags);
	}

	/**
	 * Add some flags that control how the reader operates to those
	 * already in operation.
	 *
	 * The flags will be operational immediately, even if the reader is already open. Adding flags does not close the
	 * reader, nor does it move its internal cursor.
	 *
	 * This method will never turn off any flags. If any flag in the provided flags is already set, it will remain set.
	 * If any flag in the provided flags is not already set it will be switched on.
	 *
	 * @param $flags int A bitmask of flags to add.
	 *
	 * @return bool _true_ if the flags were added, _false_ otherwise.
	 */
	public function addFlags(int $flags) {
		$this->m_flags |= $flags;
		return true;
	}

	/**
	 * Remove some flags that control how the reader operates from those currently in operation.
	 *
	 * The provided flags will be switched off immediately, even if the reader is already open. Removing flags does not
	 * close the reader, nor does it move its internal cursor.
	 *
	 * This method will never turn on any flags. If any flag in the provided flags is already switched off, it will
	 * remain switched off. If any flag in the provided flags is switched on it will be switched of.
	 *
	 * @param $flags int A bitmask of flags to remove.
	 *
	 * @return bool _true_ if the flags were removed, _false_ otherwise.
	 */
	public function removeFlags(int $flags) {
		$this->m_flags &= ~$flags;
		return true;
	}

	/**
	 * Fetch the index of the last line the reader read from the file.
	 *
	 * This can be useful for diagnostic information when files contain errors.
	 *
	 * @return int The line index.
	 */
	public function lastLineIndex() {
		return $this->m_nextLineIndex - 1;
	}

	/**
	 * Fetch the index of the next line the reader will read from the file.
	 *
	 * This can be useful for diagnostic information when files contain errors.
	 *
	 * @return int The line index.
	 */
	public function nextLineIndex() {
		return $this->m_nextLineIndex;
	}

	/**
	 * Fetch the index of the next byte the reader will read from the file.
	 *
	 * This can be useful for diagnostic information when files contain errors.
	 *
	 * @return int The byte index or -1 if the reader is not open.
	 */
	public function nextByteIndex() {
		return (is_null($this->m_fh) ? -1 : ftell($this->m_fh));
	}

	/**
	 * Set the index of the next byte the reader will read from the file.
	 *
	 * @param $i int The position in the file at which to place the read cursor.
	 *
	 * If the reader is not currently open, this has no effect, and if the reader is subsequently opened the read cursor
	 * will be at the fist byte.
	 *
	 * ### Warning
	 * Use this method with extreme caution. Moving the internal cursor can cause invalid files to be parsed as if they
	 * were valid or, more likely, cause valid files to be reported as invalid.
	 *
	 * @return bool _true_ if the read cursor was set, _false_ otherwise.
	 */
	public function setNextByteIndex(int $i) {
		if(is_null($this->m_fh)) {
			AppLog::error('reader is not open', __FILE__, __LINE__, __FUNCTION__);
			return false;
		}

		return 0 === fseek($this->m_fh, $i, SEEK_SET);
	}

	/**
	 * Fetch the error code for the last error that occurred.
	 *
	 * The error code will be _ErrNone_ if no errors have occurred.
	 *
	 * @return int One of the class error code constants.
	 */
	public function lastError() {
		return $this->m_lastError;
	}


	/**
	 * Fetch the error message for the last error that occurred.
	 *
	 * The error message will be empty if no errors have occurred.
	 *
	 * @return string The error message.
	 */
	public function lastErrorMessage() {
		return $this->m_lastErrorMessage;
	}

	/**
	 * Set the code of the last error.
	 *
	 * Provide _ErrNone_ to indicate everything is OK.
	 *
	 * The message is optional but recommended to provide more useful error information, and as a convenience to avoid
	 * having to call both this and _setLastErrorMessage()_. If it is not provided, the message will be set to _null_.
	 *
	 * @param $code int The code to set.
	 * @param $msg string|null _optional_ The message associated with the code.
	 *
	 * @return bool _true_ if the error was set, _false_ otherwise.
	 */
	protected function setLastError(int $code, $msg = null) {
		$this->m_lastError = $code;
		return $this->setLastErrorMessage($msg);
	}

	/**
	 * Set the message for the last error.
	 *
	 * Provide _null_ to unset the message.
	 *
	 * @param $msg string|null The message to set.
	 *
	 * @return bool _true_ if the error message was set, _false_ otherwise.
	 */
	protected function setLastErrorMessage($msg) {
		if(!is_string($msg) && !is_null($msg)) {
			return false;
		}

		$this->m_lastErrorMessage = $msg;
		return true;
	}

	/**
	 * Check whether the reader is open.
	 *
	 * @return bool _true_ if the reader is open for reading entries, _false_
	 * otherwise.
	 */
	public function isOpen() {
		return (bool)$this->m_fh;
	}

	/**
	 * Attempt to open the reader.
	 *
	 * In order to be used, a reader must first be opened. This method attempts to open the file at the provided path to
	 * read entries from it.
	 *
	 * If the reader is already open, it takes no action and the internal cursor is unmodified.
	 *
	 * If opening fails, see lastError() and lastErrorMessage() for details of what went wrong.
	 *
	 * @return bool _true_ if the reader is open, _false_ if it could not be opened.
	 */
	public function open() {
		if($this->isOpen()) {
			return true;
		}

		if(empty($this->m_path)) {
			$this->m_lastError        = self::ErrNoPath;
			$this->m_lastErrorMessage = 'the path of the file to open is missing';
			return false;
		}

		$this->m_fh = @ fopen($this->m_path, 'rb');

		if($this->isOpen()) {
			/* detect BOM and skip */
			$this->skipByteOrderMark();
			$this->m_nextLineIndex = 0;
			return true;
		}

		$this->m_lastError = self::ErrFailedOpeningFile;
		$this->m_lastError = 'the file "' . $this->m_path . '" could not be opened for reading';
		return false;
	}

	/**
	 * Close the reader.
	 *
	 * Once closed, no more entries can be read from the reader until it is reopened. Closing a reader also means that,
	 * if subsequently reopened, the internal cursor will be reset to the start of the file.
	 *
	 * If the reader is not open, this method will not perform any action and will return success.
	 *
	 * @return bool _true_ if the reader is closed, _false_ if it could not be closed.
	 */
	public function close() {
		if($this->isOpen()) {
			fclose($this->m_fh);
			$this->m_fh = false;
		}

		$this->m_nextLineIndex = -1;
		return !$this->isOpen();
	}

	/**
	 * Check whether the reader is at the end of the file.
	 *
	 * If the reader is at the end of the file, no more entries can be read. If the reader is not open, the result of
	 * this method is not defined.
	 *
	 * @return bool _true_ if the reader is open and has reached the end of the file, _false_ if it is open and has not
	 * reached the end of the file, undefined otherwise.
	 */
	public function atEnd() {
		return feof($this->m_fh);
	}

	/**
	 * Read a line from the file.
	 *
	 * This is an internal helper function to read a line from the reader. It is only used internally. The line is
	 * trimmed of all leading and trailing whitespace.
	 *
	 * @return string The line read from the file.
	 */
	protected function readLine() {
		++$this->m_nextLineIndex;

		/* trim all leading whitespace and the trailing linefeed, if present */
		return rtrim(ltrim(fgets($this->m_fh)), chr(10));
	}

	/**
	 * Parse a line read from the file.
	 *
	 * This is an internal helper function to parse a line that has been read from the file. It is only used internally.
	 * It parses the line into an anonymous object with properties that the internal calling code knows how to
	 * interpret.
	 *
	 * The returned object may have the following properties:
	 * - **type** _int_ The type of line that was parsed. This is one of the class line type constants.
	 * - **tag** _string|null_ The string representation of the tag, without the leading _%_, or null if the line is not
	 *   a line with a new tag.
	 * - **content** _string_ The line's content. The interpretation of this content is dependent upon the line type and
	 *   the tag.
	 * - **line** _string_ The full line that was parsed into the object.
	 *
	 * @param $line string The line to parse.
	 *
	 * @return StdClass An object with properties detailing the data parsed from the line.
	 */
	protected function parseLine($line) {
		$ret       = new stdClass();
		$ret->line = $line;

		if(empty($line)) {
			$ret->type    = self::EmptyLineType;
			$ret->tag     = null;
			$ret->content = '';
		}
		else if('%' == $line[0]) {
			$line = explode(' ', $line, 2);

			if($this->m_flags & self::StrictLineFormat && 2 != count($line)) {
				$ret->type    = self::InvalidLineType;
				$ret->tag     = null;
				$ret->content = null;
			}
			else {
				$ret->type    = self::NewTagLineType;
				$ret->tag     = substr($line[0], 1);
				$ret->content = (isset($line[1]) ? $line[1] : '');
			}
		}
		else {
			$ret->type    = self::ContinuationLineType;
			$ret->tag     = null;
			$ret->content = $line;
		}

		return $ret;
	}

	/**
	 * Parse the content for an array-type tag into an array.
	 *
	 * Items are trimmed of leading and trailing whitespace. If the _DiscardEmptyListItems_ flag is set, items found to
	 * be empty after trimming of whitespace are discarded and will not be found in the returned array.
	 *
	 * @param $content string The content of the tag.
	 * @param $delim string _optional_ string containing all the characters that should be considered item delimiters in
	 * the content.
	 *
	 * @return array[string] The items parsed from the provided content.
	 */
	protected function parseArrayTagContent(string $content, string $delim = "\n") {
		$content = explode($delim, $content);
		$ret     = [];

		foreach($content as $item) {
			$item = trim($item);

			if($this->m_flags & self::DiscardEmptyListItems && empty($item)) {
				continue;
			}

			$ret[] = $item;
		}

		return $ret;
	}


	/**
	 * Attempt to apply a property to a record.
	 *
	 * If the record already has the property set, this method logs an error, sets the error code and message
	 * accordingly and returns failure, unless the _SilentlySkipDuplicateTags_ flag is set, in which case a warning is
	 * logged and the method returns success.
	 *
	 * @param $propertyName string The name of the property to set.
	 * @param $value mixed The value for the property.
	 * @param $record StdClass The record to which to apply the property.
	 *
	 * @return bool _true_ if the property was applied (or ignored according to the flags), _false_ if the property
	 * could not be applied.
	 */
	protected function applyRecordProperty(string $propertyName, $value, StdClass & $record) {
		if(!empty($record->$propertyName) && 0 !== $record->$propertyName) {
			if($this->flagsAreSet(self::SilentlySkipDuplicateTags)) {
				AppLog::warning('unexpected second ' . $propertyName . ' tag ignored (content = "' . $value . '"; record ' . $propertyName . ' is already set to "' . $record->$propertyName . '")', __FILE__, __LINE__, __FUNCTION__);
				return true;
			}

			AppLog::error('unexpected second ' . $propertyName . ' tag ignored (content = "' . $value . '"; record ' . $propertyName . ' is already set to "' . $record->$propertyName . '")', __FILE__, __LINE__, __FUNCTION__);
			$this->m_lastError        = self::ErrUnexpectedMultipleTag;
			$this->m_lastErrorMessage = 'the ' . $propertyName . ' tag may only appear once for each record';
			return false;
		}

		$record->$propertyName = $value;
		return true;
	}

	/**
	 * Append a value to the existing value for a record property.
	 *
	 * If the property is currently empty, the provided value will be set as the property value in the record. If the
	 * property is currently not empty, first the concatenator then the new value will be appended to the current value
	 * for the property.
	 *
	 * The concatenator is optional. If not provided, the new value will be appended directly to the end of the existing
	 * property value.
	 *
	 * @param $propertyName string The name of the property to modify.
	 * @param $value string The value to append to the property.
	 * @param $record StdClass A reference to the record whose property should be modified.
	 * @param $concatenator string _optional_ The content to use between the record's
	 * current property value and the value provided.
	 *
	 * @return bool _true_ if the value was appended to the property, _false_ otherwise.
	 */
	protected function appendRecordProperty(string $propertyName, $value, StdClass & $record, string $concatenator = "") {
		if(empty($record->$propertyName)) {
			$record->$propertyName = $value;
		}
		else {
			$record->$propertyName .= "$concatenator$value";
		}

		return true;
	}

	/**
	 * Merge a value to the existing value for a record property.
	 *
	 * If the property is currently empty, the provided value will be set as the property value in the record and is
	 * guaranteed to be an array. (This is true even if the provided value is empty. In this case, the property will be
	 * set to an array containing one (empty) item.) If the property is currently not empty, the new value will be
	 * merged into its existing value, as long as that existing value is an array.
	 *
	 * The delimiter is optional. If not provided, the default delimiter of a single newline character will be used if
	 * required.
	 *
	 * If the provided value is not, or cannot be converted to, an array, or if the existing property for the record is
	 * neither empty nor an array, an _ErrInvalidLineFound_ error is generated. If the _KeepDuplicateListContent_ flag
	 * is set the record's property retains any duplicates introduced by the merge; otherwise, any duplicates are
	 * removed and only one instance of each identical item in the property is retained.
	 *
	 * @param $propertyName string The name of the property to which to merge.
	 * @param $value string|array[string] The value to merge into the property.
	 * @param $record StdClass A reference to the record whose property should be modified.
	 * @param $delimiter string _optional_ The delimiter to use if the value is a string that needs to be parsed into an
	 * array.
	 *
	 * @return bool _true_ if the value was merged into the property, _false_ otherwise.
	 */
	protected function mergeRecordProperty(string $propertyName, $value, StdClass & $record, string $delimiter = "\n") {
		if(is_string($value)) {
			$value = $this->parseArrayTagContent($value, $delimiter);
		}

		if(!is_array($value)) {
			AppLog::error("invalid $propertyName tag (content = \"$value\"; expecting array to merge)", __FILE__, __LINE__, __FUNCTION__);
			$this->m_lastError        = self::ErrInvalidLineFound;
			$this->m_lastErrorMessage = "the $propertyName tag must be list content";
			return false;
		}

		if(empty($record->$propertyName)) {
			$record->$propertyName = $value;
		}
		else if(is_array($record->$propertyName)) {
			$record->$propertyName = array_merge($record->$propertyName, $value);
		}
		else {
			AppLog::error("invalid $propertyName tag (expecting array to merge into but found non-array content already in record)", __FILE__, __LINE__, __FUNCTION__);
			$this->m_lastError        = self::ErrInvalidLineFound;
			$this->m_lastErrorMessage = 'the ' . $propertyName . ' tag must be list content';
			return false;
		}

		if(!$this->flagsAreSet(self::KeepDuplicateListContent)) {
			$record->$propertyName = array_unique($record->$propertyName);
		}

		return true;
	}

	/**
	 * Attempt to apply a tag to a record.
	 *
	 * The tag is an object returned from the _parseLine()_ method.
	 *
	 * Some tags can occur multiple times, others only once. Those properties of records that represent tags that can
	 * occur multiple times are marked as arrays (see _emptyRecord()_). Applied tags that can occur multiple times are
	 * appended to the corresponding property array; those that can occur only once are set using the
	 * _applyRecordProperty()_ method. That method may report failure, in which case that failure will be passed on in
	 * the return value from this method and the error code and message set by _applyRecordProperty()_ will remain in
	 * place.
	 *
	 * The default implementation attempts to set a property on the record with the name of the tag (i.e.
	 * _$tag->tagString_) to the content of the tag (i.e. _$tag->content_).
	 *
	 * @param $tag StdClass The tag to apply.
	 * @param $record StdClass The record to which to apply the tag.
	 *
	 * @return bool _true_ if the tag was applied (or ignored according to the flags), _false_ if the tag could not be
	 * applied.
	 */
	protected function applyTag(StdClass $tag, StdClass & $record) {
		return $this->applyRecordProperty($tag->tagString, $tag->content, $record);
	}

	/**
	 * Create a new, empty, record.
	 *
	 * This method is used internally to create a new, empty record whenever one is needed. It should be reimplemented
	 * in subclasses to provide an object with a fully-defined set of empty fields. What empty means is up to the
	 * subclass to define, but will often mean properties with either _null_, an empty string or an empty array.
	 *
	 * The default implementation provides an anonymous object with no properties.
	 *
	 * @return StdClass An empty record.
	 */
	protected function emptyRecord() {
		return new StdClass();
	}

	/**
	 * Validate a parsed record.
	 *
	 * Subclasses should reimplement this method to ensure that records returned by _nextRecord()_ are valid. If they
	 * don't the default implementation will be used, which indicates that any record provided is valid.
	 *
	 * The default implementation of _nextRecord()_ calls this method immediately before returning after reading a full
	 * record from the file.
	 *
	 * @param $record StdClass The record to validate.
	 *
	 * @return bool _true_ if the record is valid, _false_ if not.
	 */
	protected function validateRecord(StdClass $record) {
		return true;
	}

	/**
	 * Skip forward in the file to find the start of the next record.
	 *
	 * ### Note
	 * If there happens to be more than one empty line between entries, this method may not actually skp a record, it
	 * may just skip from the first empty line to the second.
	 */
	public function skipContent() {
		/* skip to the next empty line */
		/** @noinspection PhpStatementHasEmptyBodyInspection */
		while(!$this->atEnd() && "" != $this->readLine()) {
			;
		}
	}

	/**
	 * Attempt to fetch the next record from the file.
	 *
	 * The reader will read lines from the file until it finds the end of a record, parsing the lines into the content
	 * of the record. If successful, the record is returned. If not, the class constant _MalformedRecord_ is returned if
	 * a badly formed record is found, or _NoMoreRecords_ if the end of the file was reached before the start of a
	 * record was found.
	 *
	 * See the _emptyRecord()_ method documentation for details of the properties of returned entries.
	 *
	 * @return StdClass|int The next record from the file, or _MalformedRecord_ if a bad record is encountered.
	 */
	public function nextRecord() {
		if(!$this->isOpen()) {
			AppLog::error("file not open", __FILE__, __LINE__, __FUNCTION__);
			$this->m_lastError        = self::ErrNotOpen;
			$this->m_lastErrorMessage = "the file is not open. did you call open() on the reader?";
			return self::MalformedRecord;
		}

		/** @var StdClass $currentTag */
		$currentTag    = null;
		$ret           = $this->emptyRecord();
		$recordStarted = false;
		$recordEnded   = false;

		while(!$recordEnded && !$this->atEnd()) {
			$line       = $this->readLine();
			$parsedLine = $this->parseLine($line);

			switch($parsedLine->type) {
				case self::EmptyLineType:
					if(!$recordStarted) {
						continue;
					}
					else {
						$recordEnded = true;
					}
					break;

				case self::ContinuationLineType:
					if(empty($currentTag)) {
						AppLog::error("found continuation, expecting new tag or empty line at line #" . $this->lastLineIndex() . " in \"" . $this->path() . "\"", __FILE__, __LINE__, __FUNCTION__);
						$this->m_lastError        = self::ErrInvalidLineFound;
						$this->m_lastErrorMessage = "found continuation, expecting new tag or empty line";
						return self::MalformedRecord;
					}
					else if(!($this->m_flags & self::PermitContinuationLines)) {
						AppLog::error("continuation lines are not permitted but found continuation at line #" . $this->lastLineIndex() . " in \"" . $this->path() . "\"", __FILE__, __LINE__, __FUNCTION__);
						$this->m_lastError        = self::ErrInvalidLineFound;
						$this->m_lastErrorMessage = "continuation lines are not permitted";
						return self::MalformedRecord;
					}
					else {
						$currentTag->content .= "\n{$parsedLine->content}";
					}

					break;

				case self::NewTagLineType:
					if(!empty($currentTag)) {
						if(!$this->applyTag($currentTag, $ret)) {
							/* applyTag() sets the error code and message if necessary */
							return self::MalformedRecord;
						}
					}
					else {
						$recordStarted = true;
					}

					$currentTag          = new stdClass();
					$currentTag->tag     = $parsedLine->tag;
					$currentTag->content = $parsedLine->content;
					break;

				case self::InvalidLineType:
					AppLog::error("invalid content at line #" . $this->lastLineIndex() . " in \"" . $this->path() . "\": \"$line\"", __FILE__, __LINE__, __FUNCTION__);
					break;
			}
		}

		if(!$recordStarted) {
			/* we've just read empty lines to the end of the file */
			return self::NoMoreRecords;
		}

		if(!empty($currentTag)) {
			$this->applyTag($currentTag, $ret);
		}

		if(!$this->validateRecord($ret)) {
			/* validateRecord() is required to set the error code and message */
			return self::MalformedRecord;
		}

		return $ret;
	}
}
