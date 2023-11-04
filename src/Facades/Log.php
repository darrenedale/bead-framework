<?php

namespace Bead\Facades;

use Bead\Contracts\Logger as LoggerContract;
use Stringable;

/**
 * Facade for easy access to the Application container's logger.
 *
 * @method static int level()
 * @method static void setLevel(int $level)
 * @method static void emergency(string|Stringable $message, array $context = [])
 * @method static void alert(string|Stringable $message, array $context = [])
 * @method static void critical(string|Stringable $message, array $context = [])
 * @method static void error(string|Stringable $message, array $context = [])
 * @method static void warning(string|Stringable $message, array $context = [])
 * @method static void notice(string|Stringable $message, array $context = [])
 * @method static void info(string|Stringable $message, array $context = [])
 * @method static void debug(string|Stringable $message, array $context = [])
 * @method static void log(int|string|Stringable $level, string|Stringable $message, array $context = [])
 */
class Log extends ApplicationServiceFacade
{
    protected static string $serviceInterface = LoggerContract::class;
}
