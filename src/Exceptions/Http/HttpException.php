<?php

namespace Bead\Exceptions\Http;

use Bead\Contracts\Response;
use Bead\Exceptions\ViewNotFoundException;
use Bead\Exceptions\ViewRenderingException;
use Bead\Facades\Application as App;
use Bead\Responses\DoesntHaveHeaders;
use Bead\Responses\HasDefaultReasonPhrase;
use Bead\Responses\NaivelySendsContent;
use Bead\View;
use Bead\Web\Application;
use Bead\Web\Request;
use Exception;
use Throwable;

use function Bead\Helpers\Str\html;

/**
 * Base class for HTTP exceptions that can also act as responses.
 *
 * By default, when rendered as a response the exception will look for a view named after the HTTP status code in the
 * view path configured in `app.http.error.view.path`. If the view does not exist, the response body will be empty.
 */
abstract class HttpException extends Exception implements Response
{
    use HasDefaultReasonPhrase;
    use DoesntHaveHeaders;
    use NaivelySendsContent;

    /** @var Request The request that triggered the HTTP exception. */
    private Request $m_request;

    /**
     * @param Request $request The incoming request that triggered the exception.
     * @param string $message The message. This may be displayed in the response.
     * @param int $code The exception code. This is NOT the HTTP response code.
     * @param Throwable|null $previous The previous exception that occurred before this.
     */
    public function __construct(Request $request, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->m_request = $request;
    }

    /**
     * Fetch the request that triggered the exception.
     *
     * @return Request The request.
     */
    public function getRequest(): Request
    {
        return $this->m_request;
    }

    public function contentType(): string
    {
        return "text/html";
    }

    public function content(): string
    {
        $viewPath = Application::instance()->config("app.http.error.view.path");

        if (isset($viewPath)) {
            try {
                return (new View("{$viewPath}.{$this->statusCode()}", ["message" => $this->getMessage()]))->render();
            } catch (ViewNotFoundException | ViewRenderingException) {
                // we only want to catch these - any others should be handled by some other means
            }
        }

        if (App::isInDebugMode() && "" !== $this->getMessage()) {
            $message = "<p>" . html($this->getMessage()) . "</p>";
        } else {
            $message = "";
        }

        // NOTE uses default reasonPhrase() so no need to escape for HTML as it's known to be safe
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>HTTP Error {$this->statusCode()}</title>
</head>
<body>
<h2>HTTP Error {$this->statusCode()} <em>{$this->reasonPhrase()}</em></h2>
{$message}
</body>
</html>
HTML;
    }
}
