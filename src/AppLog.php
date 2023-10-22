<?php

namespace Bead;

/**
 * Debug logging class.
 *
 * @deprecated AppLog will be removed in Bead V1. Use the Log facade instead.
 */
class AppLog
{
    public const MessagePrefix = "MSG";

    public const WarningPrefix = "WRN";

    public const ErrorPrefix = "ERR";

	/** @var null|string The name for the log file. */
	private ?string $m_fileName = null;

    /** @var null|resource The  handle for the log file  */
	private $m_fileHandle = null;

    /** @var AppLog|null The message log. */
    private static ?AppLog $s_messageLog = null;

    /** @var AppLog|null The warning log. */
    private static ?AppLog $s_warningLog = null;

    /** @var AppLog|null The error log. */
    private static ?AppLog $s_errorLog = null;

    /** Create a new AppLog.
     *
     * The provided file will have any log messages appended to it. It will not be truncated.
     *
     * @param $fileName string The name of the file to log to.
     */
    public function __construct(string $fileName)
    {
        $this->m_fileName = $fileName;
    }

    /** Set the AppLog object to use as the default message log.
     *
     * The provided log object will be used for all messages logged using AppLog::message().
     *
     * @param $log AppLog|null The log to use.
     */
    public static function setMessageLog(?AppLog $log): void
    {
        self::$s_messageLog = $log;
    }

    /** Fetch the default AppLog object being used to log messages.
     *
     * The log returned is the one that is currently being used to log messages logged using AppLog::message().
     *
     * @return AppLog|null The message log, or _null_ if no message log is set.
     */
    public static function messageLog(): ?AppLog
    {
        return self::$s_messageLog;
    }

    /** Set the AppLog object to use as the default warning log.
     *
     * The provided log object will be used for all messages logged using AppLog::warning().
     *
     * @param $log AppLog|null The log to use.
     */
    public static function setWarningLog(?AppLog $log): void
    {
        self::$s_warningLog = $log;
    }

    /** Fetch the default AppLog object being used to log warnings.
     *
     * The log returned is the one that is currently being used to log warnings logged using AppLog::warning().
     *
     * @return AppLog|null The warning log, or _null_ if no warning log is set.
     */
    public static function warningLog(): ?AppLog
    {
        return self::$s_warningLog;
    }

    /**
     * Set the AppLog object to use as the default error log.
     *
     * The provided log object will be used for all messages logged using AppLog::error().
     *
     * @param $log AppLog|null The log to use.
     */
    public static function setErrorLog(?AppLog $log): void
    {
        self::$s_errorLog = $log;
    }

    /** Fetch the default AppLog object being used to log errors.
     *
     * The log returned is the one that is currently being used to log errors logged using AppLog::error().
     *
     * @return AppLog|null The error log, or _null_ if no error log is set.
     */
    public static function errorLog(): ?AppLog
    {
        return self::$s_errorLog;
    }

    /** Check whether the log is open.
     *
     * @return bool _true_ if the log is open, _false_ otherwise.
     */
    public function isOpen(): bool
    {
        return (bool)$this->m_fileHandle;
    }

    /** Open the log for writing.
     *
     * The log will attempt to open the provided file for writing. Existing content in the file is preserved, log
     * messages are appended.
     *
     * @return bool _true_ if the log file was opened successfully, _false_ otherwise.
     */
    public function open(): bool
    {
        if ($this->isOpen()) {
            return true;
        }

        if (!is_string($this->m_fileName) || "" == $this->m_fileName) {
            AppLog::error("invalid log file name", __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        $this->m_fileHandle = fopen($this->m_fileName, "a");

        if (!$this->m_fileHandle) {
            $this->m_fileHandle = null;
            AppLog::error("failed to open log file \"{$this->m_fileName}\"", __FILE__, __LINE__, __FUNCTION__);
            return false;
        }

        return true;
    }

    /** Close the log.
     *
     * The log file will be closed. Subsequent writes will fail until the log is reopened.
     */
    public function close(): void
    {
        if (!$this->isOpen()) {
            return;
        }

        if (!fclose($this->m_fileHandle)) {
            AppLog::error("failed to cleanly close log file \"{$this->m_fileName}\"", __FILE__, __LINE__, __FUNCTION__);
        }

        $this->m_fileHandle = null;
    }

    /** Write a message to the log file.
     *
     * The message will be written verbatim.
     *
     * @param $msg string The message to write to the log.
     *
     * @return bool _true_ if the message was completely written to the log file, _false_ if the message could not be
     * written or could only be partially written.
     */
    public function write(string $msg): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        /* MUST NOT log an error or warning - to do so would risk recursively calling this method */
        return strlen($msg) == fwrite($this->m_fileHandle, $msg);
    }

    /** Internal helper function to build a message.
     *
     * This method is used internally by the default logging methods message(), warning() and error() to format the
     * message for output to the log. The provided message has the current date/time and the provided source code
     * location details added to it according to this template:
     *
     * ~~~{.php}
     * YYYY-MM-DD HH:MM:SS function() in file.php (110) This is my message.
     * ~~~
     *
     * The file path will have the path part stripped so that just the actual file name is inserted into the final
     * message. If the originating function is not provided, that part of the message is omitted; if the originating
     * file or line are omitted, they are replaced with "??".
     *
     * @param $msg string The base message to write.
     * @param $file string The path to the file from which the message originates.
     * @param $line int The line in the file from which the message originates.
     * @param $fn string The name of the function from which the message originates.
     *
     * @return string The formatted message.
     */
    private static function buildMessage(string $msg, ?string $file, ?int $line, ?string $fn): string
    {
        return date("Y-m-d H:i:s ")
            . (isset($fn) ? "{$fn}() in " : "")
            . (isset($file) ? basename($file) : "??")
            . (isset($line) ? "({$line})" : "(??)") . " {$msg}\n";
    }

    /**
     * Write a message to the default message log.
     *
     * The message will be formatted and output to the message log. Messages should not contain a trailing linefeed
     * unless you want blank lines to appear in the log file.
     *
     * @param $msg string The message to write.
     * @param $file string|null The file from which the message originates.
     * @param $line int|null The line in the file from which the message originates.
     * @param $fn string|null The function from which the message originates.
     *
     * @return bool _true_ if the message was written in its entirety, _false_ if it was not written or was only
     * partially written.
     */
    public static function message(string $msg, ?string $file = null, ?int $line = null, ?string $fn = null): bool
    {
        if (!isset(self::$s_messageLog)) {
            return false;
        }

        return self::$s_messageLog->write(self::buildMessage(self::MessagePrefix . " {$msg}", $file, $line, $fn));
    }

    /**
     * Write a message to the default warning log.
     *
     * The message will be formatted and output to the warning log. Messages should not contain a trailing linefeed
     * unless you want blank lines to appear in the log file.
     *
     * @param $msg string The message to write.
     * @param $file string|null The file from which the message originates.
     * @param $line int|null The line in the file from which the message originates.
     * @param $fn string|null The function from which the message originates.
     *
     * @return bool _true_ if the message was written in its entirety, _false_ if it was not written or was only
     * partially written.
     */
    public static function warning(string $msg, ?string $file = null, ?int $line = null, ?string $fn = null): bool
    {
        if (!isset(self::$s_warningLog)) {
            return false;
        }

        return self::$s_warningLog->write(self::buildMessage(self::WarningPrefix . " " . $msg, $file, $line, $fn));
    }

    /** Write a message to the default error log.
     *
     * The message will be formatted and output to the error log. Messages should not contain a trailing linefeed unless
     * you want blank lines to appear in the log file.
     *
     * @param $msg string The message to write.
     * @param $file string|null The file from which the message originates.
     * @param $line int|null The line in the file from which the message originates.
     * @param $fn string|null The function from which the message originates.
     *
     * @return bool _true_ if the message was written in its entirety, _false_ if it was not written or was only
     * partially written.
     */
    public static function error(string $msg, ?string $file = null, ?int $line = null, ?string $fn = null): bool
    {
        if (!isset(self::$s_errorLog)) {
            return false;
        }

        return self::$s_errorLog->write(self::buildMessage(self::ErrorPrefix . " {$msg}", $file, $line, $fn));
    }
}
