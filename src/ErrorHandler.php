<?php

namespace Equit;

use Equit\Contracts\ErrorHandler as ErrorHandlerContract;
use Equit\Responses\AbstractResponse;
use Error;
use Throwable;

/**
 * The default error handler for Applications.
 */
class ErrorHandler implements ErrorHandlerContract
{
    /**
     * Determine whether the provided exception should be displayed.
     *
     * The default implementation will display the error if the Application is in debug mode.
     *
     * @param Throwable $err The exception.
     *
     * @return bool true if it should be displayed, false otherwise.
     */
    protected function shouldDisplay(Throwable $err): bool
    {
        $app = Application::instance();
        return $app && $app->isInDebugMode();
    }

	/**
	 * Fetch the name of the view to use to render exceptions.
	 *
	 * @return string The view name.
	 */
	protected function viewName(): string
	{
		return "errors.exception";
	}

    /**
     * Display a given exception.
     *
     * Display will be to the WebApplication's page (if it has one, a default page if it does not); or to standard
     * output if the application is not a WebApplication.
     *
     * @param Throwable $err The exception to display.
     */
    protected function display(Throwable $err): void
    {
        $app = Application::instance();

        if ($app instanceof WebApplication) {
            $this->displayInView($err);
        } else {
            $this->outputToStream($err, STDERR);
        }
    }

    /**
     * Display an exception in a web page.
     *
     * @param Throwable $error The exception to display.
     */
    protected function displayInView(Throwable $error): void
    {
		try {
			WebApplication::instance()->sendResponse(new View($this->viewName(), compact("error")));
		} catch (Throwable $err) {
			// extremely basic fallback display
			WebApplication::instance()->sendResponse(new class($error) extends AbstractResponse {
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
    }

    /**
     * Output an exception to a stream.
     *
     * @param Throwable $err The exception to output.
     * @param resource $stream The stream resource to which it should be output.
     */
    protected function outputToStream(Throwable $err, $stream): void
    {
        fputs($stream, "Exception `" . get_class($err) . "` in '{$err->getFile()}' @ {$err->getLine()}: [{$err->getCode()}] {$err->getMessage()}\n");

        foreach ($err->getTrace() as $frame) {
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
     * @param Throwable $err The exception to report.
     */
    protected function report(Throwable $err): void
    {
        AppLog::error($err->getMessage(), $err->getFile(), $err->getLine());
    }

    /**
     * Handle an error.
     *
     * The error is converted to an `Error` exception and the script exits.
     *
     * @param int $type The error type.
     * @param string $message The error message.
     * @param string $file The file where the error occurred.
     * @param int $line The line on which the error occurred.
     */
    public function handleError(int $type, string $message, string $file, int $line): void
    {
        $this->handleException(new Error("PHP error {$file}@{$line}: {$message}", $type));
        exit($type);
    }

    /**
     * Handle an exception.
     *
     * The exception is reported, displayed if necessary, and the script exits. The exception's error code is used as
     * the script exit code.
     *
     * @param Throwable $err The exception that was thrown.
     */
    public function handleException(Throwable $err): void
    {
        $this->report($err);

        if ($this->shouldDisplay($err)) {
            $this->display($err);
        }

        exit($err->getCode());
    }
}
