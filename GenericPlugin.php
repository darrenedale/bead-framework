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
 * @version 1.1.2
 * @package libequit
 * @date Jan 2018
 */

namespace Equit;

/**
 * Base class for application plugins.
 *
 * All application logic should be implemented in subclasses of this class.
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
 * @class LibEquit\GenericPlugin
 * @author Darren Edale
 * @package libequit
 */
abstract class GenericPlugin {
	/**
	 * Indicate the actions that the plugin provides.
	 *
	 * Actions are not case sensitive - _get_  is the same as _GET_ and _Get_. If a plugin does not provide any actions
	 * (but does other useful things) it _must_ return an empty array otherwise it will not be loaded.
	 *
	 * @return array[string] the list of actions the plugin provides.
	 */
	public static function supportedActions(): array {
		return [];
	}

	/**
	 * Fetch an instance of the plugin.
	 *
	 * It is up to the plugin to decide whether multiple instances can be created or whether just a single instance is
	 * allowed.
	 *
	 * This method is guaranteed to be called at least once as it is used by _Application::loadPlugins()_ to fetch an
	 * instance of each plugin (which is used by _Application::pluginByName()_ and _Application::pluginForAction()_ to
	 * provide plugin instances). It cannot be assumed here, therefore, or in the constructor, that all plugins have
	 * been loaded. If there is anything your plugin needs to do that depends on another plugin having been loaded
	 * already, you should do it in a separate method and connect it to the _application.pluginsloaded_ event. If you
	 * are allowing multiple instances you can connect to this event and set an internal flag to record whether or not
	 * the plugins have been loaded and then act according to that flag.
	 *
	 * @return GenericPlugin|null an instance of the plugin, or _null_ on error.
	 */
	public static function instance(): ?GenericPlugin {
		return null;
	}

	/**
	 * Handle a request for the application.
	 *
	 * If a plugin does not handle any requests for the application, this empty implementation will suffice.
	 *
	 * @param \Equit\Request $request The request to handle.
	 *
	 * @return bool _true_ if the request was handled, _false_ if it is not a request that the plugin is supposed to
	 * handle.
	 */
	public function handleRequest(/** @noinspection PhpUnusedParameterInspection */Request $request) {
		return false;
	}
}
