<?php

declare(strict_types=1);

namespace Bead\Queues;

use Bead\Contracts\Azure\Authorisation as AzureAuthorisationContract;
use Bead\Contracts\Azure\ClientApplicationCredentials as AzureClientApplicationCredentialsContract;
use Bead\Contracts\Azure\OAuth2Authoriser as AzureOAuth2AuthenticatorContract;
use Bead\Contracts\Queues\Message as MessageContract;
use Bead\Contracts\Queues\Queue;
use Bead\Encryption\ScrubsStrings;
use Bead\Exceptions\Azure\AuthorizationException;
use Bead\Exceptions\QueueException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use JsonException;
use LogicException;
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

    private ClientInterface $httpClient;

    public function __construct(string $namespace, string $queueName, AzureClientApplicationCredentialsContract $credentials, ?ClientInterface $httpClient = null)
    {
        $this->credentials = $credentials;
        $this->namespace = $namespace;
        $this->name = $queueName;
        $this->httpClient = $httpClient ?? new Client();
    }

    final protected function authorise(): void
    {
        try {
            // TODO persist (encrypted) token in session?
            $this->authorisation = $this->credentials->authorise(self::Resource, self::GrantType);
        } catch (AuthorizationException $err) {
            throw new QueueException("Failed to obtain OAuth2 token for Azure service bus: {$err->getMessage()}", previous: $err);
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

    final protected function baseUri(): string
    {
        return "https://{$this->namespace()}.servicebus.windows.net/{$this->name()}/messages";
    }

    final protected function sendRequest(string $method, string $uri, string $body = null): ResponseInterface
    {
        if (null === $this->authorisation) {
            $this->authorise();
        }

        $retry = false;

        do {
            try {
                $response = $this->httpClient->sendRequest(new Request($method, $uri, $this->authorisation->headers(), $body));
            } catch (ClientExceptionInterface $err) {
                throw new QueueException("Network error communicating with Azure service bus queue {$this->name()} in namespace {$this->namespace()}: {$err->getMessage()}", previous: $err);
            }

            // if authorization fails, try refreshing the token once and retrying
            if (401 === $response->getStatusCode() && !$retry) {
                $this->authorise();
                $retry = true;
            } else {
                $retry = false;
            }
        } while ($retry);

        return $response;
    }

    protected function hydrateMessage(ResponseInterface $response): AzureServiceBusMessage
    {
        assert(200 <= $response->getStatusCode() && 300 > $response->getStatusCode());

        if ($response->hasHeader("BrokerProperties")) {
            $header = $response->getHeader("BrokerProperties")[0];

            try {
                $properties = json_decode($header, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $err) {
                if (50 < strlen($header)) {
                    $header = substr($header, 0, 47) . "...";
                }

                throw new QueueException("Expected valid JSON BrokerProperties header, found {$header}: {$err->getMessage()}", previous: $err);
            }

            print_r($properties);
            $id = $properties["MessageId"] ?? null;

            if (null === $id) {
                throw new QueueException("Expected MessageId in BrokerProperties header, none found");
            }

            $lockToken = $properties["LockToken"] ?? "";
        } else {
            $id = "";
            $lockToken = "";
        }

        return new AzureServiceBusMessage($id, $lockToken, (string) $response->getBody());
    }

    final protected function fetch(int $n, bool $remove = true): array
    {
        assert(0 < $n, new LogicException("Expected positive number of messages to fetch, found {$n}"));
        $messages = [];

        while (0 < $n) {
            $response = $this->sendRequest(($remove ? "DELETE" : "POST"), "{$this->baseUri()}/head");

            switch ($response->getStatusCode()) {
                case 201:       // when POST
                case 200:       // when DELETE
                    --$n;
                    $messages[] = $this->hydrateMessage($response);
                    break;

                case 204:
                    // no more messages available
                    break 2;

                default:
                    throw new QueueException("Failed fetching messages from service bus queue {$this->name()} in namespace {$this->namespace()}: {$response->getReasonPhrase()}");
            }
        }

        return $messages;
    }

    public function peek(int $n = 1): array
    {
        return $this->fetch($n, false);
    }

    public function get(int $n = 1): array
    {
        return $this->fetch($n);
    }

    public function put(MessageContract $message): void
    {
        $this->sendRequest("POST", $this->baseUri(), $message->payload());
    }

    public function delete(MessageContract $message): void
    {
        if (!$message instanceof AzureServiceBusMessage) {
            throw new QueueException("Expected message from AzureServiceBus queue, found " . $message::class);
        }

        if ("" === $message->id() || "" === $message->lockToken()) {
            throw new QueueException("Expected message peeked from AzureServiceBus queue - message may have been taken or may not have originated on a queue");
        }

        $this->sendRequest("DELETE", "{$this->baseUri()}/{$message->id()}/{$message->lockToken()}");
    }
}