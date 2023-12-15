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

class Mailgun implements Transport
{
    private const MailgunService = "Mailgun\\Mailgun";

    public static function isAvailable(): bool
    {
        $app = Application::instance();

        if ("" === (string) $app->config("mail.transports.mailgun.domain")
            || !class_exists(self::MailgunService)
            || !$app->has(self::MailgunService)) {
            return false;
        }

        $mailgun = $app->get(self::MailgunService);
        return is_object($mailgun) && is_a($mailgun, self::MailgunService);
    }

    public function send(Message $message): void
    {
        if (!self::isAvailable()) {
            throw new TransportException("Mailgun transport is not available");
        }

        $app = Application::instance();

        /** @var Mailgun\Mailgun $client */
        $client = $app->get(self::MailgunService);

        /** @var Mailgun\Api\Message $messages */
        $messageApi = $client->messages();

        $builder = new MimeBuilder();
        $mime = $builder->headers($message) . Mime::Rfc822LineEnd . $builder->body($message);

        try {
            /** @var \Mailgun\Model\Message\SendResponse $response */
            $response = $messageApi->sendMime(
                $app->config("mail.transports.mailgun.domain"),
                array_unique([...$message->to(), ...$message->cc(), ...$message->bcc()]),
                $mime,
                []
            );

            Log::debug("Successfully transported message with subject \"{$message->subject()}\" using Mailgun: {$response->getMessage()}");
        } catch (HttpClientException $err) {
            throw new TransportException("Failed to transport message with subject \"{$message->subject()}\" using Mailgun: \"{$err->getResponse()->getReasonPhrase()}\"");
        }
    }
}
