<?php

/**
 * Defines the LibEquit\GenericPlugin class.
 *
 * ### Dependencies
 * - classes/equit/AppLog.php
 * - classes/equit/LibEquit\Application.php
 *
 * ### Changes
 * - (2017-05) Updated documentation.
 * - (2013-12-10) First version of this file.
 *
 * @file LibEquit\GenericPlugin.php
 * @author Darren Edale
 * @version 0.9.2
 * @package libequit
 * @version 0.9.2 */

namespace Equit;

use Equit\Contracts\Response;

/**
 * Base class for application plugins.
 *
 * All application logic should be implemented in subclasses of this class.
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
 * @class Equit\GenericPlugin
 * @author Darren Edale
 * @package bead-framework
 */
abstract class GenericPlugin
{
	/**
	 * Fetch an instance of the plugin.
	 *
	 * It is up to the plugin to decide whether multiple instances can be created or whether just a single instance is
	 * allowed.
	 *
	 * This method is guaranteed to be called at least once as it is used by `Application::loadPlugins()`_` to fetch an
	 * instance of each plugin (which is used by `Application::pluginByName()` to provide plugin instances). It cannot
	 * be assumed here, therefore, or in the constructor, that all plugins have been loaded. If there is anything your
	 * plugin needs to do that depends on another plugin having been loaded already, you should do it in a separate
	 * method and connect it to the `application.pluginsloaded` event. If you are allowing multiple instances you can
	 * connect to this event and set an internal flag to record whether or not the plugins have been loaded and then act
	 * according to that flag.
	 *
	 * @return GenericPlugin|null an instance of the plugin, or `null` on error.
	 */
	public static function instance(): ?GenericPlugin
	{
		return null;
	}
}
