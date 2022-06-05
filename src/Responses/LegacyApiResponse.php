<?php

namespace Equit\Responses;

use Equit\Contracts\Response;

/**
 * Response class for legacy API responses.
 *
 * Legacy API responses are plain-text with the first line of text indicating a status code and message, and subsequent
 * lines containing the response payload. For example:
 *
 * ```
 * 0 Request was fulfilled successfully. Data follows
 * This is the data.
 * This is nore of the data.
 * This is also data.
 * ```
 */
class LegacyApiResponse implements Response
{
    use CanSetStatusCode;
    use DoesntHaveHeaders;
    use NaivelySendsContent;

    /** @var int The API response code. 0 for success, non-0 for failure. */
    private int $m_code;

    /** @var string The single-line message for the response. */
    private string $m_message;

    /** @var string The response payload. */
    private string $m_payload;

    /**
     * Initialise a new legacy API response.
     *
     * @param int $code The response code.
     * @param string $message The response message.
     * @param string $payload The response payload. Defaults to an empty string.
     */
    public function __construct(int $code, string $message, string $payload = "")
    {
        $this->m_statusCode = 200;
        $this->m_code = $code;
        $this->m_message = $message;
        $this->m_payload = $payload;
    }

    /**
     * Fetch the API response code.
     *
     * @return int The code.
     */
    public function apiResponseCode(): int
    {
        return $this->m_code;
    }

    /**
     * Fetch the API response message.
     *
     * @return string The message.
     */
    public function apiResponseMessage(): string
    {
        return $this->m_message;
    }

    /**
     * Fetch the API response payload.
     *
     * @return string The payload.
     */
    public function apiResponsePayload(): string
    {
        return $this->m_payload;
    }

    /**
     * Fetch the response content type.
     *
     * API responses are text/plain.
     *
     * @return string "text/plain"
     */
    public function contentType(): string
    {
        return "text/plain";
    }

    /**
     * Fetch the body content for the HTTP response.
     *
     * @return string The response HTTP body.
     */
    public function content(): string
    {
        return "{$this->apiResponseCode()}" . (empty($this->apiResponseMessage()) ? "" : " {$this->apiResponseMessage()}") . "\n{$this->apiResponsePayload()}";
    }
}