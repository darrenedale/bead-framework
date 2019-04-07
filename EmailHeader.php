<?php

/**
* Defines the EmailHeader class.
*
* ### Dependencies
* - classes/equit/AppLog.php
*
* ### Changes
* - (201-05) Updated documentation. Migrated to `[]` syntax from array().
* - (2014-04-29) Class ported from bpLibrary.
*
* @file EmailHeader.php
* @author Darren Edale
* @version 1.1.2
* @package libequit
* @date Jan 2018
*/


namespace Equit;

require_once("classes/equit/AppLog.php");

/**
 * A class encapsulating a header for an email message.
 *
 * An email header is composed of a header name, a header value and zero or more header value parameters. It is
 * presented in an email message as:
 *
 *     {name}: {value}[;{param-name}={param-value}[;{param-name}={param-value}]...]
 *
 * Use _name()_ and _setName()_ to fetch and set the header name. Use _value()_ and _setValue()_ to fetch and set the
 * value.
 *
 * The list of parameters can be fetched with _parameters()_. Values for individual named parameters be fetched and set
 * using _parameter()_ and _setParameter()_. A parameter can be removed by passing its name to _clearParameter()_. The
 * presence of certain named parameters can be tested with _hasParameter()_ and the number of parameters present in the
 * header can be checked with _parameterCount()_.
 *
 * The header name is validated when _setName()_ is called, and if it doesn't pass validation the name is not set.
 *
 * The full string representation of the header, suitable for inclusion in an email message header section, can be
 * fetched by calling _generate()_.
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
 * @class EmailHeader
 * @version 1.1.2
 * @date Jan 2018
 * @see Email EmailPart
 * @package libequit
 */
class EmailHeader {
	/** @var string|null The name of the header. */
	private $m_name = null;

	/** @var string|null The header value. */
	private $m_value = null;

	/** @var array The header value parameters. */
	private $m_params = [];

	/**
	 * Create a new email message header.
	 *
	 * @param $name string _optional_ The name for the header.
	 * @param $value string _optional_ The value for the header.
	 */
	public function __construct(?string $name = null, ?string $value = null) {
		$this->setName($name);
		$this->setValue($value);
	}

	/**
	 * Get the value of the header.
	 *
	 * @return string|null The header name, or _null_ on error.
	 */
	public function name(): ?string {
		return $this->m_name;
	}

	/**
	 * Get the value of the header.
	 *
	 * @return string|null The header value, or _null_ on error.
	 */
	public function value(): ?string {
		return $this->m_value;
	}

	/**
	 * Set the name of the header.
	 *
	 * The name must be a UTF-8 encoded string. It may not be empty. It may be _null_ to indicate that the header name
	 * is not set.
	 *
	 * @param $name string|null The name for the header.
	 *
	 * @return bool _true_ if the name was set, _false_ otherwise.
	 */
	public function setName(?string $name): bool {
		if(is_string($name)) {
			$name = trim($name);

			if(!preg_match("/^[\\!#\\\$%&'\\*\\+\\-0-9A-Z\\^_`a-z\\|~]+\$/", $name)) {
				AppLog::error("invalid name \"$name\"");
				return false;
			}
		}

		$this->m_name = $name;
		return true;
	}

	/**
	 * Set the value of the header.
	 *
	 * The value may be an empty string. It may also be _null_ to indicate that the value is not set.
	 *
	 * @param $value string|null The value for the header.
	 */
	public function setValue(?string $value): void {
		$this->m_value = $value;
	}

	/**
	 * Check whether a parameter is set for the header.
	 *
	 * @param $name string the name of the parameter to check.
	 *
	 * @return bool _true_ if the parameter is set, _false_ otehrwise.
	 */
	public function hasParameter(string $name): bool {
		return array_key_exists($name, $this->m_params);
	}

	/**
	 * Set the value of a parameter for the header.
	 *
	 * @param $name string The name of the parameter to set.
	 * @param $value string The value for the parameter.
	 */
	public function setParameter(string $name, string $value): void {
		$this->m_params[$name] = $value;
	}

	/**
	 * Get the value of a parameter for the header.
	 *
	 * @param $name string The name of the parameter to get.
	 *
	 * @return string|null The value for the parameter, or _null_ if the parameter is not set or an error occurred.
	 */
	public function parameter(string $name): ?string {
		if($this->hasParameter($name)) {
			return $this->m_params[$name];
		}

		return null;
	}

	/**
	 * Remove a parameter from the list of parameters for the header.
	 *
	 * @param $name string The name of the parameter to remove.
	 *
	 * @return bool _true_ if the parameter was found and removed, _false_ otherwise.
	 */
	public function clearParameter(string $name): bool {
		if($this->hasParameter($name)) {
			unset($this->m_params[$name]);
			return true;
		}

		return false;
	}

	/**
	 * Count the number of parameters set for the header.
	 *
	 * @return int The number of parameters.
	 */
	public function parameterCount(): int {
		return count($this->parameters());
	}

	/**
	 * Get all parameters for the header.
	 *
	 * The parameters are returned as an array, keyed by the parameter key. Both the keys and values are always UTF-8
	 * encoded strings.
	 *
	 * @return array[string=>string] The parameters.
	 */
	public function parameters(): array {
		return $this->m_params;
	}

	/**
	 * Generate the header line.
	 *
	 * The header line is generated without any trailing delimiter. For SMTP and POP3 the delimiter is the sequence
	 * <cr><lf> but other protocols, including protocols that are yet to be created, may use other delimiters. For this
	 * reason, it is up to the protocol handler to add the appropriate delimiter.
	 *
	 * @return string|null The header line, or _null_ if it is not valid.
	 */
	public function generate(): ?string {
		$ret   = null;
		$name  = $this->name();
		$value = $this->value();

		if(is_string($name) && is_string($value)) {
			$ret = "$name: $value";

			foreach($this->parameters() as $key => $value) {
				$ret .= ("; $key=$value");
			}
		}

		return $ret;
	}
}
