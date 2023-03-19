<?php

declare(strict_types=1);

namespace BeadTests\Session\Handlers;

use Bead\Application;
use Bead\Contracts\Session\Handler;
use Bead\Session\Handlers\Factory;
use Bead\Session\Handlers\File as FileHandler;
use Bead\Session\Handlers\Php as PhpHandler;
use Bead\Testing\XRay;
use BeadTests\Framework\TestCase;
use FilesystemIterator;
use RuntimeException;
use SplFileInfo;

final class FactoryTest extends TestCase
{
	public const TestAppRootDirectory = "/tmp/bead-framework-tests";

	public const TestSessionDirectory = "sessions";

	private Factory $factory;

	public function setUp(): void
	{
		mkdir(self::TestAppRootDirectory . DIRECTORY_SEPARATOR . self::TestSessionDirectory, recursive: true);
		$this->factory = new Factory();
	}

	private static function recursivelyRemove(string | SplFileInfo $path): void
	{
		if (is_string($path)) {
			$path = new SplFileInfo($path);
		}

		if (!file_exists((string) $path)) {
			return;
		}

		if ($path->isDir()) {
			foreach (new FilesystemIterator((string) $path) as $entry) {
				self::recursivelyRemove($entry);
			}

			rmdir((string) $path);
		} else {
			unlink((string) $path);
		}
	}

	public function tearDown(): void
	{
		self::recursivelyRemove(self::TestAppRootDirectory);
		parent::tearDown();
	}

	/**
	 * Generate a valid but useless handler of an anonymous class.
	 *
	 * @return Handler The useless handler.
	 */
	private static function createTestHandler(): Handler
	{
		return new class implements Handler
		{
			public function __construct(?string $id = null)
			{}

			public function id(): string
			{
				return "";
			}

			public function get(string $key): mixed
			{
				return null;
			}

			public function all(): array
			{
				return [];
			}

			public function set(string $key, $data): void
			{}

			public function remove(string $key): void
			{}

			public function clear(): void
			{}

			public function regenerateId(): string
			{
				return "";
			}

			public function createdAt(): int
			{
				return 0;
			}

			public function lastUsedAt(): int
			{
				return 0;
			}

			public function idGeneratedAt(): int
			{
				return 0;
			}

			public function idExpiredAt(): ?int
			{
				return PHP_INT_MAX;
			}

			public function touch(?int $time = null): void
			{}

			public function idHasExpired(): bool
			{
				return false;
			}

			public function replacementId(): ?string
			{
				return null;
			}

			public function commit(): void
			{}

			public function load(string $id): void
			{}

			public function reload(): void
			{}

			public function destroy(): void
			{}

			public static function prune(): void
			{}
		};
	}

	private static function createApplication(): Application
	{
		return new class extends Application
		{
			public function __construct()
			{
				self::$s_instance = $this;
			}

			public function rootDir(): string
			{
				return FactoryTest::TestAppRootDirectory;
			}

			public function config(string $key = null, $default = null)
			{
				if ("session.handlers.file.directory" === $key) {
					return FactoryTest::TestSessionDirectory;
				}

				return null;
			}

			public function exec(): int
			{
				return 0;
			}
		};
	}

	/** Ensure we can get a built-in handler. */
	public function testHandlerWithBuiltIn(): void
	{
		self::createApplication();
		$handler = $this->factory->handler("file");
		self::assertInstanceOf(FileHandler::class, $handler);
		$handler = $this->factory->handler("php");
		self::assertInstanceOf(PhpHandler::class, $handler);
	}

	/** Ensure we can get a registered handler. */
	public function testHandlerWithRegistered(): void
	{
		$handler = self::createTestHandler();
		$this->factory->registerHandler("test-handler", get_class($handler));
		$newHandler = $this->factory->handler("test-handler");
		self::assertInstanceOf(get_class($handler), $newHandler);
		self::assertNotSame($handler, $newHandler);
	}

	/** Ensure handler() throws when a unknown handler name is provided. */
	public function testHandlerThrows(): void
	{
		self::expectException(RuntimeException::class);
		self::expectExceptionMessage("No session handler named 'test-handler' has been registered.");
		$this->factory->handler("test-handler");
	}

	/** Ensure we can regsiter a handler. */
	public function testRegisterHandler(): void
	{
		$handlerClass = get_class(self::createTestHandler());
		$this->factory->registerHandler("test-handler", $handlerClass);
		$factory = new XRay($this->factory);
		self::assertArrayHasKey("test-handler", $factory->registeredHandlers);
	}

	/** Ensure registerHandler rejects names of built-in handlers. */
	public function testRegisterHandlerThrowsWithBuiltIn(): void
	{
		self::expectException(RuntimeException::class);
		self::expectExceptionMessage("The handler name 'file' is already taken by a built-in session handler.");
		$this->factory->registerHandler("file", FileHandler::class);
	}

	/** Ensure registerHandler() throws with a name that's already taken. */
	public function testRegisterHandlerThrowsWithAlreadyRegistered(): void
	{
		$firstHandlerClass = get_class(self::createTestHandler());
		$secondHandlerClass = get_class(self::createTestHandler());
		$this->factory->registerHandler("test-handler", $firstHandlerClass);
		self::expectException(RuntimeException::class);
		self::expectExceptionMessage("The handler name 'test-handler' is already taken by a previously-registered session handler.");
		$this->factory->registerHandler("test-handler", $secondHandlerClass);
	}

	/**
	 * Test data for testRegisterHandlerThrowsWithInvalidClass()
	 *
	 * @return iterable<string> The test data.
	 */
	public function dataForTestRegisterHandlerThrowsWithInvalidClass(): iterable
	{
		yield "Non existent class" => ["BeadTests\Session\TestHandler"];
		yield "Class that does not implement Handler" => [self::class];
	}

	/**
	 * Ensure registerHandler does not accept classes that don't exist or don't implement Handler.
	 *
	 * @dataProvider dataForTestRegisterHandlerThrowsWithInvalidClass
	 *
	 * @param string $className The class name to test with.
	 */
	public function testRegisterHandlerThrowsWithInvalidClass(string $className): void
	{
		self::expectException(RuntimeException::class);
		self::expectExceptionMessage("The class '{$className}' does not exist or does not implement the Handler interface.");
		$this->factory->registerHandler("test-handler", $className);
	}
}
