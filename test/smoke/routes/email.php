<?php

declare(strict_types=1);

use Bead\Contracts\Router;
use Bead\Email\Message;
use Bead\Email\Mime;
use Bead\Email\Part;
use Bead\Email\Transport\Log as LogTransport;
use Bead\Email\Transport\Php;
use Bead\Exceptions\Email\TransportException;
use Bead\Request;
use Bead\Validation\Validator;
use Bead\View;

use function Bead\Helpers\Iterable\flatten;
use function Bead\Helpers\Str\html;

/** @var Router $router */

$router->registerGet("/email", function(): View {
    return new View("email");
});

$router->registerPost("/email/send", function(Request $request): View {
    $validator = new Validator($request->allPostData(), [
        "to" => "email",
        "cc" => ["optional", "email",],
        "bcc" => ["optional", "email",],
        "from" => ["optional", "email",],
        "subject" => ["filled",],
        "content" => ["filled",],
    ]);

    if ($validator->fails()) {
        $dispatchMessages = flatten($validator->errors());
        $data = $validator->data();
    } else {
        $data = $validator->validated();
        ["to" => $to, "from" => $from, "subject" => $subject, "content" => $content, "cc" => $cc, "bcc" => $bcc] = $data;
        $message = new Message($to, $subject);

        if ("" !== (string) $cc) {
            $message = $message->withCc($cc);
        }

        if ("" !== (string) $bcc) {
            $message = $message->withBcc($bcc);
        }

        if ("" !== (string) $from) {
            $message = $message->withFrom($from);
        }

        $message = $message
            ->withContentType("multipart/alternative", ["boundary" => Mime::generateMultipartBoundary()])
            ->withPart($content, "text/plain")
            ->withPart(
                "<html><head><title>" . html($subject) . "</title></head><body><div>" . html($content) . "</div></body></html>",
                "text/html"
            );

        $transport = new Php();

        try {
            $transport->send($message);
            $dispatchMessages = ["Message was successfully dispatched for delivery to {$to}.",];
        } catch (TransportException $err) {
            $dispatchMessages = ["Exception transporting message: {$err->getMessage()}.",];
        }
    }

    return new View("email", ["dispatchMessages" => $dispatchMessages, ...$data,]);
});
