<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Contracts\Email\Message;
use Bead\Contracts\Email\Transport;
use Bead\Email\Mime;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\TransportException;
use Bead\Facades\Log;
use Mailgun\Exception\HttpClientException;
use Mailgun\Mailgun as MailgunClient;
use Mailgun\Model\Message\SendResponse;
use RuntimeException;

class Mailgun implements Transport
{
    /** @var string The mailgun client class FQN. */
    private const MailgunService = "Mailgun\\Mailgun";

    private MailgunClient $client;

    private string $domain;

    /**
     * Initialise a new instance of the transport with a client and domain.
     *
     * @param MailgunClient $client The client to use to send messages.
     * @param string $domain The domain from which messages will be sent.
     */
    public function __construct(MailgunClient $client, string $domain)
    {
        $this->client = $client;
        $this->domain = $domain;
    }

    /** Check whether Mailgun is installed. */
    public static function isAvailable(): bool
    {
        return class_exists(self::MailgunService);
    }

    /**
     * Create a Mailgun HTTP API transport using a key, domain and optionally endpoint.
     *
     * @param string $key The mailgun API key.
     * @param string $domain The mailgun sending domain.
     * @param ?string $endpoint The endpoint to use, or null to use the default endpoint.
     * @return self
     */
    public static function create(string $key, string $domain, ?string $endpoint = null): self
    {
        if (!self::isAvailable()) {
            throw new RuntimeException("Mailgun is not installed");
        }

        if (is_string($endpoint)) {
            $client = [self::MailgunService, "create"]($key, $endpoint);
        } else {
            $client = [self::MailgunService, "create"]($key);
        }

        return new self($client, $domain);
    }

    /**
     * Fetch the Mailgun client the transport will use to send messages.
     *
     * @return MailgunClient The client.
     */
    public function client(): MailgunClient
    {
        return $this->client;
    }

    /**
     * Fetch the domain that will be used to send messages.
     *
     * @return string The domain.
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * Send a message.
     *
     * @param Message $message The message to send.
     *
     * @throws TransportException if the message can't be submitted for delivery.
     */
    public function send(Message $message): void
    {
        $builder = new MimeBuilder();
        $mime = $builder->headers($message) . Mime::Rfc822LineEnd . $builder->body($message);

        try {
            /** @var SendResponse $response */
            $response = $this->client->messages()->sendMime(
                $this->domain,
                array_unique([...$message->to(), ...$message->cc(), ...$message->bcc()]),
                $mime,
                ["from" => $message->from(),]
            );

            Log::debug("Successfully transported message with subject \"{$message->subject()}\" using Mailgun: {$response->getMessage()}");
        } catch (HttpClientException $err) {
            throw new TransportException("Failed to transport message with subject \"{$message->subject()}\" using Mailgun: \"{$err->getResponse()->getReasonPhrase()}\"");
        }
    }
}
