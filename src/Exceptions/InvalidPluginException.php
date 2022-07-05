<?php

namespace Equit\Exceptions;

use Equit\Plugin;
use Exception;
use Throwable;

/**
 * Exception thrown when a plugin can't be loaded.
 */
class InvalidPluginException extends Exception
{
	/** @var string The plugin file. */
	private string $m_path;

	/** @var \Equit\Plugin|null The plugin instance, if available. */
	private ?Plugin $m_plugin;

	/**
	 * @param string $path The path from which the plugin was attempted to be loaded.
	 * @param \Equit\Plugin|null $plugin The plugin instance, if available.
	 * @param string $message The optional error message. Defaults to an empty string.
	 * @param int $code The optional error code. Defaults to 0.
	 * @param \Throwable|null $previous The optional previous Throwable. Defaults to null.
	 */
	public function __construct(string $path, ?Plugin $plugin = null, string $message = "", int $code = 0, Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
		$this->m_path = $path;
		$this->m_plugin = $plugin;
	}

	/**
	 * Fetch the file path from which the plugin load was attempted.
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->m_path;
	}

	/**
	 * Check whether the exception has an instance of the invalid plugin.
	 *
	 * @return bool true if it does, false if not.
	 */
	public function hasPlugin(): bool
	{
		return isset($this->m_plugin);
	}

	/**
	 * Fetch the invalid plugin, if available.
	 *
	 * @return \Equit\Plugin|null The plugin.
	 */
	public function getPlugin(): ?Plugin
	{
		return $this->m_plugin;
	}
}