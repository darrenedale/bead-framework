<?php

/**
 * Defines the Validatable interface.
 *
 * ### Dependencies
 * - classes/equit/DataValidationReport.php
 *
 * ### Changes
 * - (2018-10) from the ashes of DAO comes the Validatable interface.
 *
 * @file DAO.php
 * @author Darren Edale
 * @version 1.1.2
 * @date Jan 2018
 * @package libequit
 */

namespace Equit;

/**
 * Interface for validatable objects.
 *
 * For performance reasons, it is recommended that validate() caches its validation report and only re-generates it when
 * required (i.e. a data member has changed).
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
 * @class Validatable
 * @author Darren Edale
 * @package libequit
 */
interface Validatable {
	/**
	 * Validate the content of the object.
	 *
	 * The data should be validated and a DataValidationReport object generated.
	 *
	 * @return DataValidationReport A report on the validity of the object.
	 */
	public function validate(): DataValidationReport;

	/**
	 * Determine whether an object is valid or not.
	 *
	 * @return bool `true` if the object is valid, `false` if not.
	 */
	public function isValid(): bool;
}
