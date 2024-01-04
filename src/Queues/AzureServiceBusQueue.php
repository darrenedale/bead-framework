<?php

declare(strict_types=1);

namespace Bead\Queues;

use Bead\Contracts\Azure\Authorisation as AzureAuthorisationContract;
use Bead\Contracts\Azure\ClientApplicationCredentials as AzureClientApplicationCredentialsContract;
use Bead\Contracts\Azure\OAuth2Authoriser as AzureOAuth2AuthenticatorContract;
use Bead\Contracts\Azure\RestCommand;
use Bead\Contracts\Queues\Message as MessageContract;
use Bead\Contracts\Queues\Queue;
use Bead\Encryption\ScrubsStrings;
use Bead\Exceptions\Azure\AuthorizationException;
use Bead\Exceptions\QueueException;
use Bead\Queues\Azure\RestClient as AzureRestClientInterface;
use Bead\Queues\Azure\RestCommands\Delete;
use Bead\Queues\Azure\RestCommands\Get;
use Bead\Queues\Azure\RestCommands\Peek;
use Bead\Queues\Azure\RestCommands\Put;
use Bead\Queues\Azure\RestCommands\Release;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/* TODO support timeout URI parameter */
class AzureServiceBusQueue implements Queue
{
    use ScrubsStrings;

    protected const GrantType = AzureOAuth2AuthenticatorContract::ClientCredentialsGrantType;

    protected const Resource = AzureOAuth2AuthenticatorContract::ServiceBusResource;

    private ?AzureAuthorisationContract $authorisation = null;

    private AzureClientApplicationCredentialsContract $credentials;

    private string $name;

    private string $namespace;

    private AzureRestClientInterface $restClient;

    public function __construct(string $namespace, string $queueName, AzureClientApplicationCredentialsContract $credentials, ?AzureRestClientInterface $client = null)
    {
        $this->credentials = $credentials;
        $this->namespace = $namespace;
        $this->name = $queueName;
        $this->restClient = $client ?? new RestClient();
    }

    final protected function checkAuthorisation(): void
    {
        if (null === $this->authorisation || $this->authorisation->hasExpired()) {
            try {
                $this->authorisation = $this->credentials->authorise(self::Resource, self::GrantType);
            } catch (AuthorizationException $err) {
                throw new QueueException("Failed to obtain OAuth2 token for Azure service bus: {$err->getMessage()}", previous: $err);
            }
        }
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function name(): string
    {
        return $this->name;
    }

    final protected function sendCommand(RestCommand $command): mixed
    {
        $this->checkAuthorisation();

        try {
            return $this->restClient->send($command, $this->authorisation);
        } catch (AuthorizationException $err) {
            // just in case we were right on the cusp of expiry when we checked above
            $this->checkAuthorisation();
            return $this->restClient->send($command, $this->authorisation);
        }
    }

    /**
     * The returned message will be locked on the queue unless deleted or released.
     */
    public function peek(): ?AzureServiceBusMessage
    {
        return $this->sendCommand(new Peek($this->namespace(), $this->name()));
    }

    public function get(): ?AzureServiceBusMessage
    {
        return $this->sendCommand(new Get($this->namespace(), $this->name()));
    }

    public function put(MessageContract $message): void
    {
        $this->sendCommand(new Put($this->namespace(), $this->name(), $message->payload()));
    }

    public function release(MessageContract $message): void
    {
        if (!$message instanceof AzureServiceBusMessage) {
            throw new QueueException("Expected message from AzureServiceBus queue, found " . $message::class);
        }

        if ("" === $message->id() || "" === $message->lockToken()) {
            throw new QueueException("Expected message peeked from AzureServiceBus queue - message may have been taken or may not have originated on a queue");
        }

        $this->sendCommand(new Release($this->namespace(), $this->name(), $message));
    }

    public function delete(MessageContract $message): void
    {
        if (!$message instanceof AzureServiceBusMessage) {
            throw new QueueException("Expected message from AzureServiceBus queue, found " . $message::class);
        }

        if ("" === $message->id() || "" === $message->lockToken()) {
            throw new QueueException("Expected message peeked from AzureServiceBus queue - message may have been taken or may not have originated on a queue");
        }

        $this->sendCommand(new Delete($this->namespace(), $this->name(), $message));
    }
}
