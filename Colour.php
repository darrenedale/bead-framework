<?php

/**
 * Defines the Colour class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 *
 * @file Colour.php
 * @author Darren Edale
 * @date Jan 2018
 * @version 1.1.2
 * @package libequit
 */

namespace Equit;

/**
 * A very simple 24-bit RGB-colourspace class.
 *
 * Objects of this class represent a 24-bit colour in the RGB colourspace. Each channel (R, G, B) is represented by
 * an 8-bit integer, or 0-255 inclusive. Colours do not have an alpha component - transparency is not supported by
 * this class directly.
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
 * @class Colour
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
class Colour {
	/** @var int The red component. */
	private $m_r = 0;

	/** @var int The green component. */
	private $m_g = 0;

	/** @var int The blue component. */
	private $m_b = 0;

	/**
	 * Create a new colour.
	 *
	 * All of the colour components are optional. By default, a black Colour
	 * is created. Colour components range from 0 to 255 inclusive.
	 *
	 * @param $r int _optional_ The red component.
	 * @param $g int _optional_ The green component.
	 * @param $b int _optional_ The blue component.
	 */
	public function __construct($r = 0, $g = 0, $b = 0) {
		$this->setRed($r);
		$this->setGreen($g);
		$this->setBlue($b);
	}

	/**
	 * Set the colour's red component.
	 *
	 * The red component must be in the range 0 to 255 inclusive. It can be
	 * provided as a double or numeric string, but will be converted internally
	 * to an integer.
	 *
	 * @param $r int The red component.
	 *
	 * @return bool `true` if the red component was set, `false` otherwise.
	 */
	public function setRed($r) {
		if(is_numeric($r) || $r >= 0 && $r <= 255) {
			$this->m_r = intval($r);
			return true;
		}

		AppLog::error("invalid red component", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set the colour's green component.
	 *
	 * The green component must be in the range 0 to 255 inclusive. It can be provided as a double or numeric string,
	 * but will be converted internally to an integer.
	 *
	 * @param $g int The green component.
	 *
	 * @return bool `true` if the green component was set, `false` otherwise.
	 */
	public function setGreen($g) {
		if(is_numeric($g) || $g >= 0 && $g <= 255) {
			$this->m_g = intval($g);
			return true;
		}

		AppLog::error("invalid green component", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Set the colour's blue component.
	 *
	 * The blue component must be in the range 0 to 255 inclusive. It can be provided as a double or numeric string, but
	 * will be converted internally to an integer.
	 *
	 * @param $b int The blue component.
	 *
	 * @return bool `true` if the blue component was set, `false` otherwise.
	 */
	public function setBlue($b) {
		if(is_numeric($b) || $b >= 0 && $b <= 255) {
			$this->m_b = intval($b);
			return true;
		}

		AppLog::error("invalid blue component", __FILE__, __LINE__, __FUNCTION__);
		return false;
	}

	/**
	 * Fetch the colour's red component.
	 *
	 * The red component is guaranteed to be between 0 and 255 inclusive.
	 *
	 * @return int The red component.
	 */
	public function red() {
		return $this->m_r;
	}

	/**
	 * Fetch the colour's green component.
	 *
	 * The green component is guaranteed to be between 0 and 255 inclusive.
	 *
	 * @return int The green component.
	 */
	public function green() {
		return $this->m_g;
	}

	/**
	 * Fetch the colour's blue component.
	 *
	 * The blue component is guaranteed to be between 0 and 255 inclusive.
	 *
	 * @return int The blue component.
	 */
	public function blue() {
		return $this->m_b;
	}
}
