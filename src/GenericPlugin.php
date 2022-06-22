<?php

namespace Equit;

/**
 * Base class for application plugins.
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
	 * This method is guaranteed to be called at least once as it is used by `Application::loadPlugins()` to fetch an
	 * instance of each plugin (which is used by `WebApplication::pluginByName()` to provide plugin instances). It
	 * cannot be assumed here, therefore, or in the constructor, that all plugins have been loaded. If there is anything
	 * your plugin needs to do that depends on another plugin having been loaded already, you should do it in a separate
	 * method and connect it to the `application.pluginsloaded` event.
	 *
	 * @return GenericPlugin|null an instance of the plugin, or `null` on error.
	 */
	public static function instance(): ?GenericPlugin
	{
		return null;
	}
}
