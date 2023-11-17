<?php

declare(strict_types=1);

namespace Bead\Email\Transport;

use Bead\Application;
use Bead\Contracts\Email\Message;
use Bead\Contracts\Email\Transport;
use Bead\Email\Mime;
use Bead\Email\MimeBuilder;
use Bead\Exceptions\Email\TransportException;
use Psr\Http\Message\ResponseInterface;

class Mailgun implements Transport
{
    private const MailgunService = "Mailgun\\Mailgun";

    public static function isAvailable(): bool
    {
        $app = Application::instance();

        return
            "" !== (string) $app->config("mail.transport.mailgun.domain")
            && class_exists(self::MailgunService)
            && $app->has(self::MailgunService)
            && $app->get(self::MailgunService) instanceof self::MailgunService;
    }

    public function send(Message $message): void
    {
        if (!self::isAvailable()) {
            throw new TransportException("Mailgun transport is not available.");
        }

        $app = Application::instance();

        /** @var Mailgun\Mailgun $client */
        $client = $app->get(self::MailgunService);

        /** @var Mailgun\Api\Message $messages */
        $messageApi = $client->messages();

        $builder = new MimeBuilder();
        $mime = $builder->headers($message) . Mime::Rfc822LineEnd . $builder->body($message);

        /** @var ResponseInterface $response */
        $response = $messageApi->sendMime(
            $app->config("mail.transport.mailgun.domain"),
            array_unique([...$message->to(), ...$message->cc(), ...$message->bcc()]),
            $mime
        );

        if (200 !== $response->getStatusCode()) {
            throw new TransportException("Failed to transport message with subject {$message->subject()}: \"{$response->getReasonPhrase()}\"");
        }
    }
}
