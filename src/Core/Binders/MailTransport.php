<?php

declare(strict_types=1);

namespace Bead\Core\Binders;

use Bead\Contracts\Binder;
use Bead\Contracts\Email\Transport as TransportContract;
use Bead\Core\Application;
use Bead\Email\Transport\Mailgun;
use Bead\Email\Transport\Php;
use Bead\Exceptions\InvalidConfigurationException;
use Bead\Exceptions\ServiceAlreadyBoundException;
use RuntimeException;

/**
 * Binds an implementation of the \Bead\Contracts\Email\Transport interface into an Application service container.
 */
class MailTransport implements Binder
{
    /**
     * Create a Php transport instance.
     *
     * There are currently no configuration options for this transport.
     *
     * @param string $transportName The user-provided name of the transport being created
     * @param array $config The user-provided configuration for the transport.
     *
     * @return Php The transport instance.
     */
    protected function createPhpTransport(string $transportName, array $config): Php
    {
        return new Php();
    }

    /**
     * Create a Mailgun transport instance.
     *
     * The configuration must include the Mailgun API key and the sending domain. It may optionally also include the
     * Mailgun endpoint to communicate with. This defaults to https://api.mailgun.net
     *
     * @param string $transportName The user-provided name of the transport being created
     * @param array $config The user-provided configuration for the transport.
     *
     * @return Mailgun The transport instance.
     * @throws InvalidConfigurationException if the provided config has invalid values.
     * @throws RuntimeException if mailgun is not installed.
     */
    protected function createMailgunTransport(string $transportName, array $config): Mailgun
    {
        $domain = $config["domain"] ?? null;
        $key = $config["key"] ?? null;

        if (!is_string($domain) || "" === trim($domain)) {
            $domain = match (true) {
                is_null($domain) => "none",
                is_string($domain) => "\"\"",
                is_object($domain) => $domain::class,
                default => gettype($domain),
            };

            throw new InvalidConfigurationException("mail.transports.{$transportName}.domain", "Expected valid mailgun domain, found {$domain}");
        }

        if (!is_string($key) || "" === trim($key)) {
            $key = match (true) {
                is_null($key) => "none",
                is_string($key) => "\"\"",
                is_object($key) => $key::class,
                default => gettype($key),
            };

            throw new InvalidConfigurationException("mail.transports.{$transportName}.key", "Expected valid mailgun key, found {$key}");
        }

        $args = [$key, $domain,];

        if (array_key_exists("endpoint", $config)) {
            $args[] = $config["endpoint"];
        }

        return Mailgun::create(...$args);
    }

    /**
     * Create the configured transport.
     *
     * @param string $transportName The name of the transport to create.
     * @param array $config The configuration for the named transport.
     *
     * @return TransportContract
     * @throws InvalidConfigurationException if the driver is not found or not supported, or the provided config has
     * invalid values.
     */
    protected function createTransport(string $transportName, array $config): TransportContract
    {
        return match ($config["driver"] ?? null) {
            "php" => $this->createPhpTransport($transportName, $config),
            "mailgun" => $this->createMailgunTransport($transportName, $config),
            default => throw new InvalidConfigurationException("mail.transports.{$transportName}.driver", "Expecting supported transport driver, found " . (is_string($config["driver"] ?? null) ? "\"{$config["driver"]}\"" : "none")),
        };
    }

    /**
     * @param Application $app The application service container into which to bind the mail transport services.
     * @throws InvalidConfigurationException if the transport specified in the mail config has an invalid configuration.
     * @throws ServiceAlreadyBoundException if an implementation is already bound to the Transport contract.
     */
    public function bindServices(Application $app): void
    {
        $transport = $app->config("mail.transport");

        if (null === $transport) {
            return;
        }

        if (!is_string($transport)) {
            throw new InvalidConfigurationException("mail.transport", "Expected string transport name, found " . getType($transport));
        }

        $config = $app->config("mail.transports.{$transport}");

        if (!is_array($config)) {
            throw new InvalidConfigurationException("mail.transports.{$transport}", "Expected transport configuration array, found " . (null === $config ? "none" : gettype($config)));
        }

        $transport = $this->createTransport($transport, $config);
        $app->bindService(TransportContract::class, $transport);
    }
}
