<?php

namespace Bead;

/**
 * Base class for application plugins.
 */
abstract class Plugin
{
    /**
     * Fetch an instance of the plugin.
     *
     * It is up to the plugin to decide whether multiple instances can be created or whether just a single instance is
     * allowed. It is expected that in most cases Plugins will be singletons.
     *
     * This method is guaranteed to be called at least once as it is used by `Application::loadPlugins()` to fetch an
     * instance of each plugin (which is used by `WebApplication::pluginByName()` to provide plugin instances). It
     * cannot be assumed here, therefore, or in the constructor, that all plugins have been loaded. If there is anything
     * your plugin needs to do that depends on another plugin having been loaded already, you should do it in a separate
     * method and connect it to the `application.pluginsloaded` event.
     *
     * @return Plugin|null an instance of the plugin, or `null` on error.
     */
    public static function instance(): ?Plugin
    {
        return null;
    }
}
