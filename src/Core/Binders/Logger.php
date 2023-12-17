<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder as BinderContract;
use Bead\Contracts\Logger as LoggerContract;
use Bead\Core\Application;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\Logging\FileLoggerException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use Bead\Logging\CompositeLogger;
use Bead\Logging\FileLogger;
use Bead\Logging\NullLogger;
use Bead\Logging\StandardErrorLogger;
use Bead\Logging\StandardOutputLogger;

use function array_key_exists;
use function Bead\Helpers\Iterable\all;
use function str_starts_with;
use function str_contains;

/** Bind logging services into the application service container. */
class Logger implements BinderContract
{
    /**
     * The default logging configuration to use when there's no config file.
     *
     * The implementation here is to have a single log file data/logs/bead-app.log.
     *
     * @return array The configuration.
     */
    protected function defaultConfiguration(): array
    {
        return [
            "loggers" => "file",
            "logs" => [
                "file" => [
                    "driver" => "file",
                    "path" => "data/logs/bead-app.log",
                    "flags" => FileLogger::FlagAppend,
                ],
            ],
        ];
    }

    /**
     * Create a FileLogger from provided config.
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
    protected function createFileLogger(array $config): FileLogger
    {
        if (!array_key_exists("path", $config)) {
            throw new InvalidConfigurationException("log.logs.*.path", "Expected log file path, none found");
        }

        $path = $config["path"];

        if (!str_starts_with($path, "/")) {
            $path = Application::instance()->rootDir() . "/{$path}";
        }

        if (".." === $path || str_contains($path, "/../") || str_starts_with($path, "../") || str_ends_with($path, "/..")) {
            throw new InvalidConfigurationException("log.logs.*.path", "Expected log file path without directory traversal components, found \"{$path}\"");
        }

        return new FileLogger($path, $config["flags"] ?? FileLogger::FlagAppend);
    }

    /**
     * Create the Logger instance to bind to the contract in the service container.
     *
     * @param array $config
     * @return LoggerContract
     */
    protected function createLogger(string $loggerName, array $config): LoggerContract
    {
        if (!is_array($config[$loggerName] ?? null)) {
            throw new InvalidConfigurationException("log.logs.{$loggerName}", "Expected configuration array for \"{$loggerName}\", found none");
        }

        $config = $config[$loggerName];

        return match ($config["driver"] ?? null) {
            "file" => $this->createFileLogger($config),
            "stdout" => new StandardOutputLogger(),
            "stderr" => new StandardErrorLogger(),
            "null" => new NullLogger(),
            default => throw new InvalidConfigurationException("log.logs.{$loggerName}.driver", "Expected recognised log driver, found \"{$config["driver"]}\""),
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
        $loggers = $app->config("log.loggers");

        if (null === $loggers) {
            ["loggers" => $loggers, "logs" => $definitions,] = $this->defaultConfiguration();
        } elseif (is_string($loggers) || (is_array($loggers) && 0 < count($loggers) && all($loggers, "is_string"))) {
            $definitions = $app->config("log.logs");
        } else {
            throw new InvalidConfigurationException("log.loggers", "Expected defined log name or array of such");
        }

        if (is_string($loggers)) {
            $app->bindService(LoggerContract::class, static::createLogger($loggers, $definitions));
            return;
        }

        $logger = new CompositeLogger();

        foreach ($loggers as $loggerName) {
            $logger->addLogger($this->createLogger($loggerName, $definitions));
        }

        $app->bindService(LoggerContract::class, $logger);
    }
}
