<?php

declare(strict_types=1);

namespace Bead\Session\Handlers;

use Bead\Contracts\Session\Handler;
use Bead\Contracts\Session\HandlerFactory;

class Factory implements HandlerFactory
{
	/** @var array<string,class-string> The built-in handlers that the factory provides. */
	private const BuiltInHandlers = [
		"file" => File::class,
		"php" => Php::class,
	];

	/** @var array<string,class-string The handlers that have been registered with the factory. */
	private array $registeredHandlers = [];

	public function handler(string $name, ?string $id): Handler
	{
		$class = self::BuiltInHandlers[$name] ?? $this->registeredHandlers[$name] ?? null;

		if (!isset($class)) {
			throw new RuntimeException("No session handler named {$name} has been registered.");
		}

		return new $class($id);
	}

	/**
	 * @param string $name The name to use to identify the handler.
	 * @param string $class The FQN of the handler class.
	 */
	public function registerHandler(string $name, string $class): void
	{
		if (isset(self::BuiltInHandlers[$name])) {
			throw new RuntimeException("The handler name {$name} is already taken by a built-in session handler.");
		}

		if (isset($this->registeredHandlers[$name])) {
			throw new RuntimeException("The handler name {$name} is already taken by a previously-registered session handler.");
		}

		if (!is_subclass_of($class, Handler::class, true)) {
			throw new RuntimeException("The class {$class} does not exist or does not implement the Handler interface.");
		}

		$this->registeredHandlers[$name] = $class;
	}
}
