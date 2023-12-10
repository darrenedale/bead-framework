<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder as BinderContract;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Core\Application;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\Logging\FileLoggerException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Logging\FileLogger;
use Bead\Logging\NullLogger;
use Bead\Logging\StandardErrorLogger;
use Bead\Logging\StandardOutputLogger;

use function array_key_exists;
use function str_starts_with;
use function str_contains;

/** Bind logging services into the application service container. */
class Logger implements BinderContract
{
    private const DefaultConfig = [
        "driver" => "file",
        "file" => [
            "path" => "logs/bead-app.log",
            "flags" => FileLogger::FlagAppend,
        ],
    ];

    /**
     * Generate a FileLogger from provided config.
     *
     * If the config contains an absolute path, the path is used untouched. If the path is relative, it's relative to
     * the application root directory provided.
     *
     * In either case, the resulting path must not have any `..` path components in it.
     *
     * @param array $config The log configuration.
     * @return FileLogger The generated logger.
     * @throws InvalidConfigurationException if the file logger configuration is not present, doesn't contain a path or
     * contains an invalid path.
     * @throws FileLoggerException if creating the logger fails.
     */
    protected static function createFileLogger(array $config): FileLogger
    {
        if (!array_key_exists("file", $config)) {
            throw new InvalidConfigurationException("log.file", "Expected file configuration, none found");
        }

        if (!array_key_exists("path", $config["file"])) {
            throw new InvalidConfigurationException("log.file.path", "Expected log file path, none found");
        }

        $path = $config["file"]["path"];

        if (!str_starts_with($path, "/")) {
            $path = Application::instance()->rootDir() . "/{$path}";
        }

        if (".." === $path || str_contains($path, "/../") || str_starts_with($path, "../") || str_ends_with($path, "/..")) {
            throw new InvalidConfigurationException("log.file.path", "Expected log file path without directory traversal components, found \"{$path}\"");
        }

        return new FileLogger($path, $config["file"]["flags"] ?? FileLogger::FlagAppend);
    }

    /**
     * Create the Logger instance to bind to the contract in the service container.
     *
     * @param array $config
     * @return LoggerContract
     */
    protected static function createLogger(array $config): LoggerContract
    {
        return match ($config["driver"]) {
            "file" => self::createFileLogger($config),
            "stdout" => new StandardOutputLogger(),
            "stderr" => new StandardErrorLogger(),
            "null" => new NullLogger(),
            default => throw new InvalidConfigurationException("log.driver", "Expected recognised log driver, found \"{$config["driver"]}\""),
        };
    }

    /**
     *  Bind services into the container.
     *
     * @param Application $app The application service container into which to bind services.
     * @throws InvalidConfigurationException if no log driver or an unrecognised driver is found in the configuration.
     * @throws ServiceAlreadyBoundException if a logger is already bound.
     * @throws FileLoggerException if creating the logger fails.
     */
    public function bindServices(Application $app): void
    {
        $config = $app->config("log", self::DefaultConfig);
        $driver = $config["driver"] ?? null;

        if (null === $driver) {
            throw new InvalidConfigurationException("log.driver", "Expected log driver, none found");
        }

        $app->bindService(LoggerContract::class, static::createLogger($config));
    }
}
