<?php

namespace Bead\Core;

use Bead\Contracts\ErrorHandler as ErrorHandlerContract;
use Bead\Contracts\Response;
use Bead\Exceptions\ViewNotFoundException;
use Bead\Facades\Log;
use Bead\Responses\AbstractResponse;
use Bead\View;
use Bead\Web\Application as WebApplication;
use Error;
use Throwable;

/**
 * The default error handler for Applications.
 */
class ErrorHandler implements ErrorHandlerContract
{
    /**
     * Determine whether an exception should be reported.
     *
     * @param Throwable $error
     * @return bool
     */
    protected function shouldReport(Throwable $error): bool
    {
        return true;
    }

    /**
     * Determine whether the provided exception should be displayed.
     *
     * The default implementation will display the error if the Application is in debug mode.
     *
     * @param Throwable $error The exception.
     *
     * @return bool true if it should be displayed, false otherwise.
     */
    protected function shouldDisplay(Throwable $error): bool
    {
        $app = Application::instance();
        return $app && $app->isInDebugMode();
    }

    /**
     * Display a given exception.
     *
     * Display will be to the configured view (or a plain default one if no view is configured) for WebApplications; or
     * to standard error if the application is not a WebApplication.
     *
     * @param Throwable $error The exception to display.
     */
    protected function display(Throwable $error): void
    {
        $app = Application::instance();

        if ($app instanceof WebApplication) {
            $this->displayExceptionInView($error);
        } else {
            $this->outputToStream($error, STDERR);
        }
    }

    /**
     * Fetch the name of the view to use to render exceptions.
     *
     * This is used to display exception details when the web app is in debug mode.
     *
     * @return string The view name.
     */
    protected function exceptionDisplayViewName(): string
    {
        return "errors.exception";
    }

    /**
     * Display an exception in a web page.
     *
     * @param Throwable $error The exception to display.
     */
    protected function displayExceptionInView(Throwable $error): void
    {
        try {
            try {
                WebApplication::instance()->sendResponse(new View($this->exceptionDisplayViewName(), compact("error")));
            } catch (Throwable) {
                // extremely basic fallback display
                WebApplication::instance()->sendResponse(new class ($error) extends AbstractResponse {
                    private Throwable $m_error;

                    public function __construct(Throwable $error)
                    {
                        $this->m_error = $error;
                    }

                    public function statusCode(): int
                    {
                        return 500;
                    }

                    public function contentType(): string
                    {
                        return "text/plain";
                    }

                    public function content(): string
                    {
                        return $this->m_error->getMessage();
                    }
                });
            }
        } catch (Throwable $err) {
            // display of last resort - we're displaying the requested error rather than the one we've just caught
            echo $error->getMessage();
        }
    }

    /**
     * Fetch the name of the view to use when exceptions are not being shown.
     *
     * This means the user is not greeted with an entirely blank page when an error occurs and the web app is not in
     * debug mode (i.e. in live environments).
     *
     * @return string The view name.
     */
    protected function errorPageViewName(): string
    {
        return "errors.error";
    }

    /**
     * Display the generic error page.
     *
     * This is used in a web app when the error handler indicates error details should *not* be displayed.
     */
    protected function showErrorPage(): void
    {
        try {
            try {
                WebApplication::instance()->sendResponse(new View($this->errorPageViewName()));
            } catch (ViewNotFoundException) {
                // extremely basic fallback display
                WebApplication::instance()->sendResponse(new class extends AbstractResponse {
                    public function statusCode(): int
                    {
                        return 500;
                    }

                    public function contentType(): string
                    {
                        return "text/html";
                    }

                    public function content(): string
                    {
                        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<title>Error Page</title>
<style>
h1 {
	margin: 0;
	padding: 10px 20%;
	font-family: sans-serif;
	font-size: 40px;
	font-weight: bold;
	background: #ff4040;
	color: #fff;
	border-bottom: 5px solid #660000;
}

body {
	margin: 0;
	padding: 0;
}

p {
	margin: 20px 10px;
	padding: 10px 20%;
	font-size: 22px;
}
</style>
</head>
<body>
<h1>Error</h1>
<p>
An application error has occurred. It has been reported and should be investigated and fixed in due course.
</p>
</body>
</html>
HTML;
                    }
                });
            }
        } catch (Throwable) {
            // error "page" of last resort - if we can't even empty the output buffer, just echo to it
            echo "An application error has occurred. It has been reported and should be investigated and fixed in due course.";
        }
    }

    /**
     * Output an exception to a stream.
     *
     * @param Throwable $error The exception to output.
     * @param resource $stream The stream resource to which it should be output.
     */
    protected function outputToStream(Throwable $error, $stream): void
    {
        fputs($stream, "Exception `" . get_class($error) . "` in '{$error->getFile()}' @ {$error->getLine()}: [{$error->getCode()}] {$error->getMessage()}\n");

        foreach ($error->getTrace() as $frame) {
            fputs($stream, "... from '{$frame["file"]}' @ {$frame["line"]}");

            if (isset($frame["function"])) {
                if (isset($frame["type"])) {
                    fputs($stream, ", {$frame["class"]}{$frame["type"]}{$frame["function"]}()");
                } else {
                    fputs($stream, ", {$frame["function"]}()");
                }
            }

            fputs($stream, "\n");
        }
    }

    /**
     * Report an exception
     *
     * The default implementation just logs it to the current error log. Subclass this error handler to do something
     * more detailed.
     *
     * @param Throwable $error The exception to report.
     */
    protected function report(Throwable $error): void
    {
        Log::critical("Exception in %1[%2]: {$error->getMessage()}", [$error->getFile(), $error->getLine(),]);
    }

    /**
     * Handle an error.
     *
     * The error is converted to an `Error` exception.
     *
     * @param int $type The error type.
     * @param string $message The error message.
     * @param string $file The file where the error occurred.
     * @param int $line The line on which the error occurred.
     *
     * @throws Error An Error exception representing the PHP error that was triggered.
     */
    public function handleError(int $type, string $message, string $file, int $line): void
    {
        throw new Error("PHP error in {$file}@{$line}: {$message}", $type);
    }

    /**
     * Handle an exception.
     *
     * The exception is reported, displayed if necessary, and the script exits. The exception's error code is used as
     * the script exit code.
     *
     * @param Throwable $error The exception that was thrown.
     */
    public function handleException(Throwable $error): void
    {
        if ($this->shouldReport($error)) {
            $this->report($error);
        }

        $displayed = false;

        if (Application::instance() instanceof WebApplication && $error instanceof Response) {
            // if the exception is itself a response, send it
            try {
                WebApplication::instance()->sendResponse($error);
                $displayed = true;
            } catch (Throwable) {
                // nothing to do here - the block below will take over sending something
            }
        }

        if (!$displayed) {
            if ($this->shouldDisplay($error)) {
                // display the error information
                $this->display($error);
            } elseif (Application::instance() instanceof WebApplication) {
                // in live environments, just show the generic error page
                $this->showErrorPage();
            }
        }

        exit($error->getCode());
    }
}
