<?php

namespace Equit;

use Equit\Contracts\ErrorHandler as ErrorHandlerContract;
use Equit\Html\Details;
use Equit\Html\Division;
use Equit\Html\HtmlLiteral;
use Equit\Html\Page;
use Equit\Html\PageElement;
use Equit\Html\Section;
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
     * @param \Throwable $err The exception.
     *
     * @return bool true if it should be displayed, false otherwise.
     */
    protected function shouldDisplay(Throwable $err): bool
    {
        $app = Application::instance();
        return $app && $app->isInDebugMode();
    }

    /**
     * Display a given exception.
     *
     * Display will be to the WebApplication's page (if it has one, a default page if it does not); or to standard
     * output if the application is not a WebApplication.
     *
     * @param \Throwable $err The exception to display.
     */
    protected function display(Throwable $err): void
    {
        $app = Application::instance();

        if ($app instanceof WebApplication) {
            $page = $app->page() ?? new Page();
            $this->displayInPage($err, $page);
            $page->output();
        } else {
            $this->outputToStream($err, STDERR);
        }
    }

    /**
     * Create a DOM element to display an exception on the page.
     *
     * @param \Throwable $err The exception being displayed.
     *
     * @return \Equit\Html\PageElement The element displaying the exception details.
     */
    protected function createExceptionElement(Throwable $err): PageElement
    {
        $section = new Section();
        $section->addClassName("equit-exception");

        $div = new Division();
        $div->addClassName("equit-exception-type");
        $div->addChildElement(new HtmlLiteral(get_class($err)));
        $section->addChildElement($div);

        $div = new Division();
        $div->addClassName("equit-exception-message");
        $div->addChildElement(new HtmlLiteral($err->getMessage()));
        $section->addChildElement($div);

        $div = new Division();
        $div->addClassName("equit-exception-file");
        $div->addChildElement(new HtmlLiteral($err->getFile()));
        $section->addChildElement($div);

        $div = new Division();
        $div->addClassName("equit-exception-line");
        $div->addChildElement(new HtmlLiteral("{$err->getLine()}"));
        $section->addChildElement($div);

        return $section;
    }

    /**
     * Create a DOM element to display a backtrace stack frame.
     *
     * @param array $frame The stack frame details.
     *
     * @return \Equit\Html\PageElement The element displaying the frame details.
     */
    protected function createStackFrameElement(array $frame): PageElement
    {
        $details = new Details();
        $details->addClassName("equit-stack-frame");
        $details->setSummary("{$frame["file"]} @ {$frame["line"]}");
        $div = new Division();

        if (isset($frame["function"])) {
            if (isset($frame["type"])) {
                $fn = "{$frame["class"]}{$frame["type"]}{$frame["function"]}()";
            } else {
                $fn = "{$frame["function"]}()";
            }

            $div->setClassNames(["equit-stack-frame-function", "equit-stack-frame-context",]);
            $div->addChildElement(new HtmlLiteral(html("from {$fn}")));
        } else {
            $div->setClassName("equit-stack-frame-context");
            $div->addChildElement(new HtmlLiteral(html("from global scope")));
        }

        $details->addChildElement($div);
        return $details;
    }

    /**
     * Display an exception in a web page.
     *
     * @param \Throwable $err The exception to display.
     * @param \Equit\Html\Page $page The page to display it on.
     */
    protected function displayInPage(Throwable $err, Page $page): void
    {
        $currentErr = $err;

        while ($currentErr) {
            $page->addMainElement($this->createExceptionElement($err));
            $currentErr = $err->getPrevious();
        }

        $backtraceContainer = new Section();
        $backtraceContainer->addClassName("equit-backtrace");

        foreach ($err->getTrace() as $frame) {
            $backtraceContainer->addChildElement($this->createStackFrameElement($frame));
        }

        $page->addMainElement($backtraceContainer);
    }

    /**
     * Output an exception to a stream.
     *
     * @param \Throwable $err The exception to output.
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
     * @param \Throwable $err The exception to report.
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
