<?php

namespace Bead\Web\Responses;

use Bead\Contracts\Web\Response;

/**
 * A response to redirect the user agent to a different URL.
 */
class RedirectResponse implements Response
{
    use HasDefaultReasonPhrase;
    use DoesntHaveContent;
    use SendsHeaders;

    /** @var int HTTP status code for permanent redirects. */
    public const PermanentRedirect = 308;

    /**
     * @var int HTTP status code for temporary redirects using the same HTTP method as the original request.
     *
     * This is typically used when a request hasn't been processed because it needs to be processed by some other
     * resource. The user agent should repeat the original request, only with a different URI.
     */
    public const TemporaryRepeatRedirect = 307;

    /**
     * @var int HTTP status code for temporary redirects using the GET HTTP method regardless of the method of the
     * original request.
     *
     * This is typically used when a request has succeeded and you want to redirect the user agent to a different
     * resource as a result.
     */
    public const TemporaryGetRedirect = 303;

    /** @var int The default HTTP status code to use. */
    public const DefaultRedirectCode = self::TemporaryGetRedirect;

    /** @var int The HTTP status code. */
    private int $m_code;

    /** @var string The redirect location. */
    private string $m_url;

    /**
     * Initialise a new redirect response.
     *
     * You can provide any HTTP status code, but in almost all cases you should use the class HTTP status code constants
     * for consistency.
     *
     * @param string $url The URL to redirect to.
     * @param int $code The HTTP status code.
     */
    public function __construct(string $url, int $code = self::DefaultRedirectCode)
    {
        $this->m_url  = $url;
        $this->m_code = $code;
    }

    /**
     * @inheritDoc
     */
    public function statusCode(): int
    {
        return $this->m_code;
    }

    /**
     * The redirect headers.
     *
     * @return array
     */
    public function headers(): array
    {
        return ["location" => $this->url(),];
    }

    /**
     * Fetch the redirect URL.
     *
     * @return string The URL.
     */
    public function url(): string
    {
        return $this->m_url;
    }

    /**
     * Set the redirect URL.
     *
     * @param string $url The URL.
     */
    public function setUrl(string $url): void
    {
        $this->m_url = $url;
    }

    /**
     * Send the redirect response.
     */
    public function send(): void
    {
        http_response_code($this->statusCode());
        $this->sendHeaders();
    }
}
