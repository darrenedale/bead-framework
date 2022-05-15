<?php

/**
* Defines the Email class.
*
* ### Dependencies
* - classes/equit/AppLog.php
* - classes/equit/EmailPart.php
* - classes/equit/EmailHeader.php
*
* ### Todo
* - refactor the email classes so that Email objects can be other than multipart/mixed and EmailPart objects can have
*   child parts so that proper MIME trees can be produced.
* - refactor code common to Email and EmailPart into trait.
*
* ### Changes
* - (2017-05) Updated documentation. Migrated to `[]` syntax from array().
* - (2014-04-29) Class ported from bpLibrary.
*
* @file Email.php
* @author Darren Edale
* @version 0.9.1
* @package libequit
* @version 0.9.1*/

namespace Equit;

/**
 * Class encapsulating an email message.
 *
 * This class can be used to create and send email messages. It provides a comfortable object-oriented interface for
 * setting custom headers and additional _Cc_ and _Bcc_ recipients that can be challenging for new users of the
 * _mail()_ function and makes it easy to create multipart messages. It guarantees that extra headers, for example,
 * will be set correctly.
 *
 * Messages are always of type *multipart/mixed* for now, but may be expanded in future to support other subtypes of
 * multipart. It will never directly support non-MIME messages; simple *text/plain* messages are always encapsulated
 * as multipart MIME messages with a single body part.
 *
 * That said, the class provides an interface to treat a message _as if_ it were a simple single-part message,
 * namely the _setBody()_ family of methods. Use of any of these methods will immediately wipe all multipart content
 * in the message in favour of the supplied body content.
 *
 * To add more parts to a multipart message, use the _addBodyPart()_, _addBodyPartContent()_ or _addAttachment()_
 * methods. The first two are generally more appropriate for content that is to be displayed inline; the latter is
 * for adding traditional file attachments to the message.
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
 * @actions _None_
 * @aio-api _None_
 * @events _None_
 * @connections _None_
 * @settings _None_
 * @session _None_
 *
 * @class Email
 * @version 0.9.1
 * @version 0.9.1 * @see EmailHeader EmailPart
 * @package libequit
 */
class Email {
//		private const CRLF = "\r\n";
	/** @var string A single linefeed character. */
	private const LF   = "\n";

	/** @var string The default delimiter to use between parts in the message body. */
	const DefaultDelimiter = "--email-delimiter-16fbcac50765f150dc35716069dba9c9--";
	/* some old (< 2.9 AFAIK) versions of postfix need the line end to be this on
	 * *nix */
	/** @var string The line ending to use in the message body during transmission. */
	const LineEnd = self::LF;
//		const LineEnd = self::CRLF;

	/**
	 * @var array The immutable headers for emails.
	 *
	 * This still being _null_ acts as the trigger to initialise the class
	 */
	private static $s_immutableHeaders = null;

	/** @var array[EmailHeader] The headers for the email. */
	protected $m_headers = [];

	/** @var array The parts of the email body. */
	protected $m_body = [];

	/** @var string The delimiter for parts of the email body. */
	protected $m_bodyPartDelimiter = Email::DefaultDelimiter;

	/**
	 * @var array[string] The set of headers that have special treatment internally.
	 *
	 * Headers in this array are handled using special methods; the addHeader() methods handle redirection when used
	 * to set them.
	 */
	protected static $s_specialHeaders = ["Content-Type", "To", "Cc", "Bcc", "From", "Subject", "Content-Transfer-Encoding"];

	/**
	 * Constructor.
	 *
	 * The recipient and sender of the message may each be _null_ to indicate that the recipient or sender is not
	 * set. The recipient may not be an array of destination addresses: to add more addresses, use the _addTo()_,
	 * _addCc()_ or _addBcc()_ methods.
	 *
	 * The subject is a special header which is handled independently of the other headers. It must be a string, or
	 * _null_ to indicate that the subject is not set.
	 *
	 * If the message body is _null_ (default), an empty email will be created.
	 *
	 * The headers may be either an array of properly formatted mail header strings without the trailing _CRLF_, or
	 * an array of _EmailHeader_ objects. If it is an array of strings, they will be encapsulated within
	 * _EmailHeader_ objects internally. If _null_ (default), no headers will be set. Note that should you
	 * successfully set the message sender or subject in this way, they will be over-written with the sender and/or
	 * subject set with the specific parameters for those headers, even if they are _null_.
	 *
	 * @param $to string|null _optional_ The destination address for the message.
	 * @param $subject string|null _optional_ The subject for the message.
	 * @param $msg string|null _optional_ The initial body content for the message.
	 * @param $from string|null _optional_ The sender of the message.
	 * @param $headers array[string|EmailHeader] _optional_ The initial set of headers for the message.
	 */
	public function __construct(?string $to = null, ?string $subject = "", ?string $msg = null, string $from = "", ?array $headers = null) {
		Email::initialiseClass();

		$this->addTo($to);
		$this->setBody($msg);

		if(is_array($headers)) {
			foreach($headers as $header) {
				if($header instanceof EmailHeader) {
					$this->addHeader($header);
				} else if(is_string($header)) {
					$this->addHeaderLine($header);
				}
			}
		}

		// do these after headers so that they take precedence over sender and subject set using direct headers
		$this->setFrom($from);
		$this->setSubject($subject);
	}

	/**
	 * Initialise the class for its first use.
	 *
	 * This method initialises some internal static attributes ready for the first use of objects of the class.
	 */
	private static function initialiseClass(): void {
		if(!isset(Email::$s_immutableHeaders)) {
			Email::$s_immutableHeaders = [new EmailHeader("Content-Transfer-Encoding", "7bit")];
		}
	}

	/**
	 * Gets the headers for the message.
	 *
	 * The headers for the message are always returned as an array of _EmailHeader_ objects. If there are none set, an
	 * empty array will be returned.
	 *
	 * @return array[EmailHeader] The headers for the message.
	 */
	public function headers(): array {
		$ret         = Email::$s_immutableHeaders;
		$contentType = new EmailHeader('Content-Type', 'multipart/mixed');
		$contentType->setParameter('boundary', '"' . $this->m_bodyPartDelimiter . '"');
		$ret[] = $contentType;
		return array_merge($ret, $this->m_headers);
	}

	/**
	 * Gets the value(s) associated with a header key.
	 *
	 * There may be multiple instances of the same header in an email message (e.g. the _CC_ header), hence an array is
	 * returned rather than just a string. If the header requested contains just one value, an array with a single
	 * element is returned.
	 *
	 * @param $headerName string is the name of the header whose value/s is/are sought.
	 *
	 * @return array[string] All the values assigned to the specified header, or _null_ if an error occurred. The array
	 * will be empty if the header is not specified.
	 */
	public function headerValues(string $headerName): array {
		$ret = [];

		foreach($this->m_headers as $header) {
			if(0 === strcasecmp($header->name(), $headerName)) {
				$ret[] = $header->value();
			}
		}

		return $ret;
	}

	/**
	 * Adds a header line to the email message part.
	 *
	 * This is a convenience function to allow addition of pre-formatted headers to an email message. Headers are
	 * formatted as:
	 *
	 *     <key>:<value><cr><lf>
	 *
	 * This function will allow headers to be added either with or without the trailing _<cr><lf>_; in either case, the
	 * resulting headers retrieved using _headers()_ will be correctly formatted.
	 *
	 * Headers that do not contain the **:** delimiter will be rejected. Only the first instance of **:** is considered
	 * a delimiter; anything after is treated as the value of the header. Multiple headers may not be added using a
	 * single call to this method. Such attempts will be rejected.
	 *
	 * ### Note
	 * This method does not yet ensure the validity of the name and value provided for the header. This behaviour must
	 * not be relied upon - it should be assumed that this method will reject invalid header names or values in future.
	 *
	 * @param $header string The header line to add.
	 *
	 * @return bool _true_ if the header was successfully added to the message, _false_ otherwise.
	 */
	public function addHeaderLine(string $header): bool {
		$header = trim($header);

		if(empty($header)) {
			AppLog::error("empty header line provided");
			return false;
		}

		/* check for attempt to add multiple header lines  */
		if(preg_match("/\\r\\n[^\\t]/", $header)) {
			AppLog::error("header provided contains more than one header");
			return false;
		}

		// FIXME this currently leaves any parameters with the value ( which should still work OK for now)
		// Note Don't use structured decomposition because explode() might return an array of length 1, which would
		// trigger an E_NOTICE
		$header = explode(":", $header, 2);

		if(2 != count($header)) {
			AppLog::error("invalid header line provided (\"$header\")");
			return false;
		}

		/* EmailHeader constructor handles validation */
		return $this->addHeader(new EmailHeader(trim($header[0]), trim($header[1])));
	}

	/**
	 * Add a header from an _EmailHeader_ object.
	 *
	 * @param $header EmailHeader The header object.
	 *
	 * @return bool _true_ if the header was added successfully, _false_ otherwise.
	 */
	private function _addHeaderObject(EmailHeader $header): bool {
		/* check for CRLF in either header or value  */
		$headerName  = $header->name();
		$headerValue = $header->value();

		if(!(is_string($headerName)) || !(is_string($headerValue))) {
			AppLog::error("invalid header - missing header name or value (or both)");
			return false;
		}

		if(false !== strpos(self::LineEnd, $headerName)) {
			AppLog::error("invalid header - might contain more than one header line");
			return false;
		}

		switch(mb_convert_case($headerName, MB_CASE_LOWER, "UTF-8")) {
			case "to":
				return $this->addTo($headerValue);

			case "from":
				return $this->setFrom($headerValue);

			case "subject":
				$this->setSubject($headerValue);
				return true;

// 			case "content-type":
// 				return $this->setContentType($value);
// 				
// 			case "content-transfer-encoding":
// 				return $this->setContentEncoding($value);

			default:
				$this->m_headers[] = $header;
		}

		return true;
	}

	/**
	 * Add a header from a pair of strings.
	 *
	 * @param $header string The name of the header.
	 * @param $value string The value for the header.
	 *
	 * @return bool _true_ if the header was added successfully, _false_ otherwise.
	 */
	private function _addHeaderStrings(string $header, string $value): bool {
		/* EmailHeader constructor handles validation */
		return $this->_addHeaderObject(new EmailHeader($header, $value));
	}

	/**
	 * Adds a header to the email message.
	 *
	 * ### Note
	 * This method cannot be used to set the subject, content-type, content-encoding or sender of the message. See the
	 * _setSubject()_ and _setFrom()_ methods. The _content-type_ and _content-encoding_ headers are fixed.
	 *
	 * @param $header string|EmailHeader The header to add.
	 * @param $value string _optional_ is the value for the header.
	 *
	 * @return bool _true_ if the header was added, _false_ otherwise.
	 */
	public function addHeader($header, ?string $value = null): bool {
		if($header instanceof EmailHeader) {
			return $this->_addHeaderObject($header);
		} else {
			if(is_string($header)) {
				return $this->_addHeaderStrings($header, $value);
			}
		}

		AppLog::error("received invalid argument for \$header: " . stringify($header), __FILE__,__LINE__, __FUNCTION__);
		trigger_error(tr("Internal error creating email message headers (%1).", __FILE__, __LINE__, "ERR_EMAIL_ADDHEADER_INVALID_HEADER"), E_USER_ERROR);
	}

	/**
	 * Remove a named header.
	 *
	 * All headers found with a matching name will be removed.
	 *
	 * @param $headerName string is the name of the header to remove.
	 */
	private function _removeHeaderByName(string $headerName): void {
		for($i = 0; $i < count($this->m_headers); ++$i) {
			if(0 === strcasecmp($this->m_headers[$i]->name(), $headerName)) {
				array_splice($this->m_headers, $i, 1);
				--$i;
			}
		}
	}

	/**
	 * Remove a header.
	 *
	 * All headers found to match the specified header in name, value and all parameters will be removed.
	 *
	 * @param $header EmailHeader The header to remove.
	 */
	private function _removeHeaderObject(EmailHeader $header): void {
		$headerName  = $header->name();
		$headerValue = $header->value();

		if(is_null($headerName) || is_null($headerValue)) {
			return;
		}

		$n = count($this->m_headers);

		for($i = 0; $i < $n; ++$i) {
			$retain = true;

			if(0 === strcasecmp($this->m_headers[$i]->name(), $headerName) && 0 === strcmp($this->m_headers[$i]->value(), $headerValue)) {
				$retain = false;

				foreach($this->m_headers[$i]->parameters() as $key => $value) {
					if(!$header->hasParameter($key) || $header->parameter($key) != $value) {
						$retain = true;
						break;
					}
				}
			}

			if(!$retain) {
				array_splice($this->m_headers, $i, 1);
				--$i;
			}
		}
	}

	/**
	 * Remove a header.
	 *
	 * Supplying a string will remove all headers with that name; providing an _EmailHeader_ object will attempt to
	 * remove a header that matches it precisely - including the header value and any parameters. If the header does not
	 * match precisely any header in the message, no headers will be removed.
	 *
	 * @param $header string|EmailHeader The header to remove.
	 *
	 * @return bool _true_ if the header has been removed or did not exist, _false_ if an error occurred.
	 */
	public function removeHeader($header): bool {
		if($header instanceof EmailHeader) {
			$this->_removeHeaderObject($header);
			return true;
		} else {
			if(is_string($header)) {
				$this->_removeHeaderByName($header);
				return true;
			}
		}

		AppLog::error("received invalid argument for \$header: " . stringify($header), __FILE__,__LINE__, __FUNCTION__);
		trigger_error(tr("Internal error creating email message headers (%1).", __FILE__, __LINE__, "ERR_EMAIL_REMOVEHEADER_INVALID_HEADER"), E_USER_ERROR);
	}

	/**
	 * Find a header by its name.
	 *
	 * This method will check through the set of headers in the message part and return the first one whose name matches
	 * that provided.
	 *
	 * @param $name string The name of the header to find.
	 *
	 * @return EmailHeader|null The header if found, or _null_ if not or on error.
	 */
	private function findHeaderByName(string $name): ?EmailHeader {
		foreach($this->headers() as $header) {
			if(0 === strcasecmp($header->name(), $name)) {
				return $header;
			}
		}

		return null;
	}

	/**
	 * Find all headers by name.
	 *
	 * This method will check through the set of headers in the message part and return all whose name matches that
	 * provided.
	 *
	 * @param $name string is the name of the headers to find.
	 *
	 * @return array[EmailHeader] The headers if found, or an empty array if not.
	 */
	private function _findAllHeadersByName(string $name): array {
		$ret = [];

		foreach($this->headers() as $header) {
			if(strcasecmp($header->name(), $name)) {
				$ret[] = $header;
			}
		}

		return $ret;
	}

	/**
	 * Clears all headers from the email message.
	 *
	 * The required headers _Content-Type_, _To_, _Cc_, _Bcc_, _From_, _Subject_ and _Content-Transfer-Encoding_ will be
	 * retained - these headers cannot be cleared. If you want to reset the content type and content encoding to their
	 * default values you must make the following calls, respectively:
	 * - $part->setContentType(EmailPart::DEFAULT_CONTENT_TYPE);
	 * - $part->setContentEncoding(EmailPart::DEFAULT_CONTENT_ENCODING);
	 */
	public function clearHeaders(): void {
		$retainedHeaders = [];

		foreach(Email::$s_specialHeaders as $headerName) {
			$headers = $this->_findAllHeadersByName($headerName);

			foreach($headers as $h) {
				$retainedHeaders[] = $h;
			}
		}

		// $s_immutableHeaders handles content-transfer-encoding retention
		$this->m_headers = $retainedHeaders;
	}

	/**
	 * Gets the body of the message.
	 *
	 * The body of the message is formatted in a way that complies with RFC2045. Briefly, this means that the content of
	 * message parts is split using _LineEnd_ into lines of no more than 76 characters. An exception to this is any
	 * message part that has a content type of *text/plain*, which is inserted into the message's main body as is
	 * without any modification.
	 *
	 * @return string The full body of the email message.
	 */
	public function body(): string {
		$ret = "";

		foreach($this->parts() as $part) {
			$ret .= self::LineEnd . "--{$this->m_bodyPartDelimiter}" . self::LineEnd;

			/* output the part headers  */
			$headers = $part->headers();

			/** @var EmailHeader $header */
			foreach($headers as $header) {
				$myHeader = $header->generate();

				if(empty($myHeader)) {
					AppLog::error("invalid header: \"" . $header->name() . ": " . $header->value() . "\"");
				} else {
					$ret .= $myHeader . self::LineEnd;
				}
			}

			$ret .= self::LineEnd . $part->content() . self::LineEnd;
		}

		return "$ret--{$this->m_bodyPartDelimiter}--";
	}

	/**
	 * Get the parts for the message body.
	 *
	 * @return array[EmailPart] The parts, or _null_ on error.
	 */
	public function parts(): array {
		return $this->m_body;
	}

	/**
	 * Get the number of parts for the message body.
	 *
	 * @return int The number of parts.
	 */
	public function partCount(): int {
		return count($this->m_body);
	}

	/**
	 * Sets the body of the message from a string.
	 *
	 * ### Note Using this function replaces all existing message body parts with a single plain text body part.
	 *
	 * @param $body string is the string to use as the body of the message.
	 */
	public function setBody(?string $body): void {
		if(is_null($body)) {
			$this->m_body = [];
			return;
		}

		// by default parts have content-type text/plain, content-transfer-encoding: quoted-printable
		$this->m_body = [new EmailPart($body)];
	}

	/**
	 * Gets the subject of the email message.
	 *
	 * @return string The message subject.
	 */
	public function subject(): string {
		$header = $this->findHeaderByName("subject");

		if(isset($header)) {
			return $header->value();
		}

		return "";
	}

	/**
	 * Sets the subject of the email message.
	 *
	 * @param $subject string the new subject of the email message.
	 */
	public function setSubject(string $subject): void {
		$header = $this->findHeaderByName("Subject");

		if(!isset($header)) {
			$this->m_headers[] = new EmailHeader("Subject", $subject);
		} else {
			$header->setValue($subject);
		}
	}

	/**
	 * Gets the recipients of the message.
	 *
	 * @return array[string] The primary recipients of the message.
	 */
	public function to(): array {
		return $this->headerValues("To");
	}

	/**
	 * Add a recipient of the message.
	 *
	 * The recipient should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
	 *
	 * @param $address string the new recipient address.
	 *
	 * @return bool _true_ if the address was valid and was added to the recipient list, _false_ otherwise.
	 */
	public function addTo(string $address): bool {
		$this->m_headers[] = new EmailHeader("To", $address);
		return true;
	}

	/**
	 * Add several recipients of the message.
	 *
	 * The recipients should be provided in RFCxxxx format, although this rule is not strictly enforced (yet). If
	 * any address in the provided array is found to be invalid for any reason, none of the addresses in the array
	 * will be added.
	 *
	 * Any non-string addresses trigger a fatal error.
	 *
	 * @param $addresses array[string] the new recipient address.
	 *
	 * @return bool _true_ if all addresses were added successfully, _false_ if any address was found to be invalid.
	 */
	public function addToAddresses(array $addresses): bool {
		foreach($addresses as $addr) {
			if(!is_string($addr)) {
				AppLog::error("invalid address found in provided array (expected string, found " . stringify($addr) . ")", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("Internal error adding email recipients (%1).", __FILE__, __LINE__, "ERR_EMAIL_ADDTOADDRESSES_INVALID_ADDRESS"), E_USER_ERROR);
			}
		}

		foreach($addresses as $addr) {
			$this->m_headers[] = new EmailHeader("To", $addr);
		}

		return true;
	}

	/**
	 * Add a recipient of the message.
	 *
	 * The recipient's address is added to the Cc list. The address should be provided in RFCxxxx format, although this
	 * rule is not strictly enforced (yet).
	 *
	 * @param $address string the new recipient.
	 *
	 * @return bool _true_ if the address was valid and was added to the recipient list, _false_ otherwise.
	 */
	public function addCc(string $address): bool {
		$this->m_headers[] = new EmailHeader("Cc", $address);
		return true;
	}

	/**
	 * Add several recipients of the message.
	 *
	 * The recipients should be provided in RFCxxxx format, although this rule is not strictly enforced (yet). If
	 * any address in the provided array is found to be invalid for any reason, none of the addresses in the array
	 * will be added.
	 *
	 * Any non-string addresses trigger a fatal error.
	 *
	 * @param $addresses array[string] the new recipient addresses.
	 *
	 * @return bool _true_ if all addresses were added successfully, _false_ if any address was found to be invalid.
	 */
	public function addCcAddresses(array $addresses): bool {
		foreach($addresses as $addr) {
			if(!is_string($addr)) {
				AppLog::error("invalid address found in provided array (expected string, found " . stringify($addr) . ")", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("Internal error adding email recipients (%1).", __FILE__, __LINE__, "ERR_EMAIL_ADDCCADDRESSES_INVALID_ADDRESS"), E_USER_ERROR);
			}
		}

		foreach($addresses as $addr) {
			$this->m_headers[] = new EmailHeader("Cc", $addr);
		}

		return true;
	}

	/**
	 * Add a recipient of the message.
	 *
	 * The recipient's address is added to the Bcc list. The address should be provided in RFCxxxx format, although this
	 * rule is not strictly enforced (yet).
	 *
	 * @param $address string the new recipient.
	 *
	 * @return bool _true_ if the address was valid and was added to the recipient list, _false_ otherwise.
	 */
	public function addBcc(string $address): bool {
		$this->m_headers[] = new EmailHeader("Bcc", $address);
		return true;
	}

	/**
	 * Add several recipients of the message.
	 *
	 * The recipients should be provided in RFCxxxx format, although this rule is not strictly enforced (yet). If any
	 * address in the provided array is found to be invalid for any reason, none of the addresses in the array will
	 * be added.
	 *
	 * @param $addresses array[string] the new recipient addresses.
	 *
	 * @return bool _true_ if all addresses were added successfully, _false_ if any address was found to be invalid.
	 */
	public function addBccAddresses(array $addresses): bool {
		foreach($addresses as $addr) {
			if(!is_string($addr)) {
				AppLog::error("invalid address found in provided array (expected string, found " . stringify($addr) . ")", __FILE__, __LINE__, __FUNCTION__);
				trigger_error(tr("Internal error adding email recipients (%1).", __FILE__, __LINE__, "ERR_EMAIL_ADDBCCADDRESSES_INVALID_ADDRESS"), E_USER_ERROR);
			}
		}

		foreach($addresses as $addr) {
			$this->m_headers[] = new EmailHeader("Bcc", $addr);
		}

		return true;
	}

	/**
	 * Gets the sender of the message.
	 *
	 * @return string The message sender.
	 */
	public function from(): string {
		$from = $this->findHeaderByName("From");

		if(isset($from)) {
			return $from->value();
		}

		return "";
	}

	/**
	 * Sets the sender of the message.
	 *
	 * The sender should be provided in RFCxxxx format, although this rule is not strictly enforced (yet).
	 *
	 * @param $sender string the new sender of the message.
	 *
	 * @return bool _true_ if the sender was set, _false_ otherwise.
	 */
	public function setFrom(string $sender): bool {
		$header = $this->findHeaderByName("From");

		if(isset($header)) {
			$header->setValue($sender);
		} else {
			$this->m_headers[] = new EmailHeader("From", $sender);
		}

		return true;
	}

	/**
	 * Gets the carbon-copy recipients of the message.
	 *
	 * The cc recipients are returned as an array of addresses. If there are none, this will be an empty array.
	 *
	 * @return array[string] The CC recipients.
	 */
	public function cc(): array {
		return $this->headerValues("Cc");
	}

	/**
	 * Gets the blind-carbon-copy recipients of the message.
	 *
	 * The BCC recipients are returned as an array of addresses. If there are none, this will be an empty array.
	 *
	 * @return array[string] The BCC recipients.
	 */
	public function bcc() {
		return $this->headerValues("Bcc");
	}

	/**
	 * Add a body part to the email message.
	 *
	 * @param $part EmailPart The part to add.
	 *
	 * Parts are always added to the end of the message.
	 */
	public function addBodyPart(EmailPart $part): void {
		$this->m_body[] = $part;
	}

	/**
	 * Add a body part to the email message.
	 *
	 * If no type or encoding is provided, the defaults of *text/plain* (in UTF-8 character encoding) and
	 * *quoted-printable* will be used respectively.
	 *
	 * It is the client code's responsibility to ensure that the data in the content string provided matches the type
	 * and transfer encoding specified. No checks, translations or conversions will be carried out.
	 *
	 * @param $content string the content part to add.
	 * @param $contentType string _optional_ is the MIME type of the content part to add.
	 * @param $contentEncoding string _optional_ is the transfer encoding of the part to add.
	 */
	public function addBodyPartContent(string $content, string $contentType = "text/plain; charset=\"utf-8\"", string $contentEncoding = "quoted-printable"): void {
		$part = new EmailPart($content);
		$part->setContentType($contentType);
		$part->setContentEncoding($contentEncoding);
	}

	/**
	 * Add an attachment to the email message.
	 *
	 * If no type or encoding is provided, the defaults of *text/plain* (in UTF-8 character encoding) and
	 * *quoted-printable* will be used respectively.
	 *
	 * It is the client code's responsibility to ensure that the data in the content string provided matches the type
	 * and transfer encoding specified. No checks, translations or conversions will be carried out.
	 *
	 * ### Note
	 * The filename parameter is not the name of the local file to attach to the email message. It is the default name
	 * to give to the attachment when it is received and the recipient saves it.
	 *
	 * @param $content string The content of the attachment to add.
	 * @param $contentType string The MIME type of the attachment to add.
	 * @param $contentEncoding string The transfer encoding of the attachment to add.
	 * @param $filename string The name of the file to assign to the attachment when it is attached to the message.
	 */
	public function addAttachment(string $content, string $contentType, string $contentEncoding, string $filename): void {
		$newPart = new EmailPart($content);
		$newPart->setContentType("$contentType; name=\"$filename\"");
		$newPart->setContentEncoding($contentEncoding);
		$newPart->addHeader("Content-Disposition", "attachment");

		$this->addBodyPart($newPart);
	}

	/**
	 * Send the message.
	 *
	 * Send the message using the internal PHP function _mail()_.
	 *
	 * @return bool _true_ if the message was submitted for delivery, false otherwise.
	 */
	public function send(): bool {
		/* get all headers except the subject, which is supplied separately in the mail() function  */
		$headers      = $this->headers();
		$headerString = "MIME-Version: 1.0" . self::LineEnd;

		foreach($headers as $header) {
			if(0 === strcasecmp("subject", $header->name())) {
				continue;
			}

			$myHeader = $header->generate();

			if(empty($myHeader)) {
				AppLog::error("invalid header: \"" . $header->name() . ": " . $header->value() . "\"");
			} else {
				$headerString .= $myHeader . self::LineEnd;
			}
		}

		return mail(implode(",", array_unique(array_merge($this->to(), $this->cc(), $this->bcc()))), $this->subject(), $this->body(), $headerString);
	}
}
