<?php
declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder;
use Bead\Contracts\ServiceContainer;
use Bead\Core\Application;
use Bead\Database\Connection;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;

use function array_key_exists;
use function is_array;

/**
 * Bind database services into the application service container.
 */
class Database implements Binder
{
    /**
     * @param string $driver The driver whos config is sought.
     * @param array $config The database config.
     * @return array The database config for the driver.
     * @throws InvalidConfigurationException if the driver config is not found or is not valid
     */
    private static function driverConfig(string $driver, array $config): array
    {
        if (!array_key_exists($driver, $config)) {
            throw new InvalidConfigurationException("db.{$driver}", "Expected database driver configuration for {$driver}. no configuration found");
        }

        $config = $config[$driver];

        if (!is_array($config)) {
            throw new InvalidConfigurationException("db.{$driver}", "Expected database driver configuration for {$driver}. found " . gettype($config));
        }

        return $config;
    }

    /** Generate a MySQL PDO DSN from the configuration. */
    private static function mysqlDsn(array $config): string
    {
        if (array_key_exists("socket", $config)) {
            ["socket" => $socket, "name" => $name,] = $config;
            return "mysql:dbname={$name};unix_socket={$host}";
        }

        ["host" => $host, "name" => $name,] = $config;

        if (array_key_exists("port", $config)) {
            return "mysql:dbname={$name};host={$host};port={$config["port"]}";
        }

        return "mysql:dbname={$name};host={$host}";
    }

    /** Generate a Postgres PDO DSN from the configuration. */
    private static function pgsqlDsn(array $config): string
    {
        ["host" => $host, "name" => $name,] = $config;

        if (array_key_exists("port", $config)) {
            return "pgsql:dbname={$name};host={$host};port={$config["port"]}";
        }

        return "pgsql:dbname={$name};host={$host}";
    }

    /** Generate a MS-SQL PDO DSN from the configuration. */
    private static function mssqlDsn(array $config): string
    {
        ["host" => $host, "name" => $name,] = $config;

        $dsn = "mssql:Database={$name};Server={$host}";

        if (array_key_exists("encrypt", $config)) {
            $dsn = "{$dsn};Encrypt={$config["encrypt"]}";
        }

        if (array_key_exists("trust_cert", $config)) {
            $dsn = $dsn . ";TrustServerCertificate=" . ($config["trust_cert"] ? "1" : "0");
        }

        if (array_key_exists("timeout", $config)) {
            $dsn = $dsn . ";LoginTimeout=" . ($config["timeout"] ? "1" : "0");
        }

        if (array_key_exists("pool", $config)) {
            $dsn = $dsn . ";ConnectionPooling=" . ($config["pool"] ? "1" : "0");
        }

        if (array_key_exists("trace", $config)) {
            $dsn = $dsn . ";TraceOn=" . ($config["trace"] ? "1" : "0");
        }

        if (array_key_exists("app", $config)) {
            $dsn = "{$dsn};APP={$config["app"]}";
        }

        if (array_key_exists("wsid", $config)) {
            $dsn = "{$dsn};WSID={$config["wsid"]}";
        }

        if (array_key_exists("tracefile", $config)) {
            $dsn = "{$dsn};TraceFile={$config["tracefile"]}";
        }

        return $dsn;
    }

    /** Generate the required DSN for the configuration. */
    private static function dsn(array $config): string
    {
        /** @var string|null $driver */
        $driver = $config["driver"] ?? null;

        if (null === $driver) {
            throw new InvalidConfigurationException("db.driver", "Expecting valid driver, found \"{$driver}\"");
        }

        $driverConfig = self::driverConfig($driver, $config);

        return match ($driver) {
            "mysql" => self::mysqlDsn($driverConfig),
            "pgsql" => self::pgsqlDsn($driverConfig),
            "mssql" => self::mssqlDsn($driverConfig),
            default => throw new InvalidConfigurationException("db.driver", "Expecting supported database driver, found \"{$driver}\""),
        };
    }

    /**
     * Bind services into the container.
     *
     * @param Application $app The application service container into which to bind services.
     * @throws InvalidConfigurationException if the database configuration is not valid.
     * @throws ServiceAlreadyBoundException if a database connection has already been bound into the application service
     * container.
     */
    public function bindServices(Application $app): void
    {
        $config = $app->config("db");
        $dsn = self::dsn($config);
        ["user" => $user, "password" => $password,] = $config;
        $db = new Connection($dsn, $user, $password);
        $app->bindService(Connection::class, $db);
    }
}
