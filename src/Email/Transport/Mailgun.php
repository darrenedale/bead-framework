<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Core\Application;
use Bead\Contracts\Email\Message;
use Bead\Contracts\Email\Transport;
use Bead\Email\Mime;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\TransportException;
use Bead\Facades\Log;
use Mailgun\Exception\HttpClientException;
use RuntimeException;

class Mailgun implements Transport
{
    private const MailgunService = "Mailgun\\Mailgun";

    private \Mailgun\Mailgun $client;

    private string $domain;

    public function __construct(\Mailgun\Mailgun $client, string $domain)
    {
        $this->client = $client;
        $this->domain = $domain;
    }

    public static function isAvailable(): bool
    {
        return class_exists(self::MailgunService);
    }

    public static function create(string $key, string $domain, string $endpoint = 'https://api.mailgun.net'): self
    {
        if (!self::isAvailable()) {
            throw new RuntimeException("Mailgun is not installed.");
        }

        $client = [self::MailgunService, "create"]($key, $endpoint);
        return new self($client, $domain);
    }

    public function send(Message $message): void
    {
        $app = Application::instance();

        /** @var Mailgun\Api\Message $messages */
        $messageApi = $this->client->messages();

        $builder = new MimeBuilder();
        $mime = $builder->headers($message) . Mime::Rfc822LineEnd . $builder->body($message);

        try {
            /** @var \Mailgun\Model\Message\SendResponse $response */
            $response = $messageApi->sendMime(
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
