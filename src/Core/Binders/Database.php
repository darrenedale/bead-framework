<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder;
use Bead\Core\Application;
use Bead\Database\Connection;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;

use function array_key_exists;
use function is_array;

/** Bind database services into the application service container. */
class Database implements Binder
{
    /**
     * @param string $driver The driver whose config is sought.
     * @param array $config The database config.
     * @return array The database config for the driver.
     * @throws InvalidConfigurationException if the driver config is not found or is not valid
     */
    final protected static function driverConfig(string $driver, array $config): array
    {
        if (!array_key_exists($driver, $config)) {
            throw new InvalidConfigurationException("db.{$driver}", "Expected database driver configuration for {$driver}, no configuration found");
        }

        $config = $config[$driver];

        if (!is_array($config)) {
            throw new InvalidConfigurationException("db.{$driver}", "Expected database driver configuration for {$driver}, found " . gettype($config));
        }

        return $config;
    }

    /** Generate a MySQL PDO DSN from the configuration. */
    final protected static function mySqlDsn(array $config): string
    {
        if (array_key_exists("socket", $config)) {
            ["socket" => $socket, "name" => $name,] = $config;
            return "mysql:dbname={$name};unix_socket={$socket}";
        }

        ["host" => $host, "name" => $name,] = $config;

        if (array_key_exists("port", $config)) {
            return "mysql:dbname={$name};host={$host};port={$config["port"]}";
        }

        return "mysql:dbname={$name};host={$host}";
    }

    /** Generate a Postgres PDO DSN from the configuration. */
    final protected static function pgSqlDsn(array $config): string
    {
        ["host" => $host, "name" => $name,] = $config;

        $dsn = "pgsql:dbname={$name};host={$host}";

        if (array_key_exists("port", $config)) {
            $dsn .= ";port={$config["port"]}";
        }

        // NOTE in sample config file, list the following supported modes:
        // disable (no ssl)
        // allow (try ssl if fails without)
        // prefer (default, try ssl, fall back to without if it fails)
        // require (only use ssl, verify the cert if CA file is available)
        // verify-ca (only use ssl, verify the cert issuer)
        // verify-full (only use ssl, verify the cert issuer and domain)
        if (array_key_exists("sslmode", $config)) {
            $dsn .= ";sslmode={$config["sslmode"]}";
        }

        return $dsn;
    }

    /** Generate a MS-SQL PDO DSN from the configuration. */
    final protected static function msSqlDsn(array $config): string
    {
        ["host" => $host, "name" => $name,] = $config;

        $dsn = "sqlsrv:Database={$name};Server={$host}";

        if (array_key_exists("encrypt", $config)) {
            $dsn .= ";Encrypt=" . ($config["encrypt"] ? "1" : "0");
        }

        if (array_key_exists("trust_cert", $config)) {
            $dsn .= ";TrustServerCertificate=" . ($config["trust_cert"] ? "1" : "0");
        }

        if (array_key_exists("timeout", $config)) {
            $dsn .= ";LoginTimeout={$config["timeout"]}";
        }

        if (array_key_exists("pool", $config)) {
            $dsn .= ";ConnectionPooling=" . ($config["pool"] ? "1" : "0");
        }

        if (array_key_exists("trace", $config)) {
            $dsn .= ";TraceOn=" . ($config["trace"] ? "1" : "0");
        }

        if (array_key_exists("app", $config)) {
            $dsn .= ";APP={$config["app"]}";
        }

        if (array_key_exists("wsid", $config)) {
            $dsn .= ";WSID={$config["wsid"]}";
        }

        if (array_key_exists("tracefile", $config)) {
            $dsn .= ";TraceFile={$config["tracefile"]}";
        }

        return $dsn;
    }

    /**
     * Generate the required DSN for the configuration.
     *
     * @throws InvalidConfigurationException if the driver is not recognised.
     */
    protected static function dsn(string $driver, array $config): string
    {
        return match ($driver) {
            "mysql" => self::mySqlDsn($config),
            "pgsql" => self::pgSqlDsn($config),
            "mssql" => self::msSqlDsn($config),
            default => throw new InvalidConfigurationException("db.driver", "Expecting supported database driver, found \"{$driver}\""),
        };
    }

    /**
     * Helper to create a connection.
     *
     * Abstracted primarily to make createDatabaseConnection() testable.
     */
    private static function connection(string $dsn, string $user, string $password): Connection
    {
        return new Connection($dsn, $user, $password);
    }

    /**
     * Create the Connection instance to bind into the service container.
     *
     * @param array $config The database configuration.
     * @return Connection
     */
    protected static function createDatabaseConnection(array $config): Connection
    {
        /** @var string|null $driver */
        $driver = $config["driver"] ?? null;

        if (null === $driver) {
            throw new InvalidConfigurationException("db.driver", "Expecting valid driver, none found");
        }

        $driverConfig = self::driverConfig($driver, $config);
        $dsn = self::dsn($driver, $driverConfig);
        ["user" => $user, "password" => $password,] = $config[$driver];
        return self::connection($dsn, $user, $password);
    }

    /**
     * @param Application $app The application service container into which to bind services.
     * @throws InvalidConfigurationException if the database configuration is not valid.
     * @throws ServiceAlreadyBoundException if a database connection has already been bound into the application service
     * container.
     */
    public function bindServices(Application $app): void
    {
        $app->bindService(Connection::class, static::createDatabaseConnection($app->config("db")));
    }
}
