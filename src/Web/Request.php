<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\Header as HeaderContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Contracts\Web\UploadedFile as UploadedFileContract;
use Bead\Contracts\Web\Uri as UriContract;
use Bead\Exceptions\Web\RequestException;
use LogicException;

use function Bead\Helpers\Iterable\all;
use function Bead\Helpers\Iterable\flatten;
use function Bead\Helpers\Iterable\some;

use const ARRAY_FILTER_USE_KEY;

/** Default implementation of the Request contract. */
class Request implements RequestContract
{
    /** @var Request|null Lazy-initialised request captured from PHP superglobals. */
    private static ?Request $capturedRequest = null;

    /** @var HttpMethod The HTTP requst method. */
    private HttpMethod $m_method;

    /** @var UriContract The request URI. */
    private UriContract $m_uri;

    /** @var array<string,Header[]>  */
    private array $m_headers;

    /** @var array<string,string|string[]> */
    private array $m_queryParameters;

    /** @var array<string,string|string[]> */
    private array $m_formFields;

    /** @var array<string,UploadedFileContract[]> $m_uploadedFiles */
    private array $m_uploadedFiles;

    /** @var array<string,string> */
    private array $m_cookies;

    /** @var string|null Lazy-initialised string containing the full (raw) body of the request. */
    private ?string $m_body;

    /** Forbid external construction. */
    private function __construct()
    {
        $this->m_body = null;
    }

    /**
     * Capture the incoming request.
     *
     * @return Request The request.
     */
    public static function capture(): Request
    {
        if (null === self::$capturedRequest) {
            self::$capturedRequest = new Request();
            self::$capturedRequest->captureMethod();
            self::$capturedRequest->captureUri();
            self::$capturedRequest->captureHeaders();
            self::$capturedRequest->captureQueryParameters();
            self::$capturedRequest->captureFormFields();
            self::$capturedRequest->captureUploadedFiles();
            self::$capturedRequest->captureCookies();
        }

        return self::$capturedRequest;
    }

    /**
     * @param HttpMethod $method
     * @param UriContract $uri
     * @param array<string,string> $queryParameters
     * @param array<string,string> $formFields
     * @param array<string,string> $cookies
     * @param HeaderContract[] $headers
     * @param UploadedFileContract[] $uploadedFiles
     * @param string $body
     * @return Request
     */
    public static function create(
        HttpMethod $method,
        UriContract $uri,
        array $queryParameters = [],
        array $formFields = [],
        array $cookies = [],
        array $headers = [],
        array $uploadedFiles = [],
        string $body = ""
    ): Request
    {
        assert(all($queryParameters, static fn(mixed $value): bool => self::isValidValue($value)), new LogicException("Request::create(): invalid query parameters"));
        assert(all(array_keys($queryParameters), static fn(mixed $key): bool => is_string($key)), new LogicException("Request::create(): invalid query parameters"));
        assert(all($formFields, static fn(mixed $value): bool => self::isValidValue($value)), new LogicException("Request::create(): invalid form fields"));
        assert(all(array_keys($formFields), static fn(mixed $key): bool => is_string($key)), new LogicException("Request::create(): invalid form fields"));
        assert(all($cookies, static fn(mixed $value): bool => is_string($value)), new LogicException("Request::create(): invalid cookies"));
        assert(all(array_keys($cookies), static fn(mixed $key): bool => is_string($key)), new LogicException("Request::create(): invalid cookies"));
        assert(all($headers, static fn(mixed $header): bool => $header instanceof HeaderContract), new LogicException("Request::create(): invalid headers"));
        assert(all($uploadedFiles, static fn(mixed $uploadedFile): bool => $uploadedFile instanceof UploadedFileContract), new LogicException("Request::create(): invalid uploaded files"));

        $request = new Request();
        $request->m_method = $method;
        $request->m_uri = $uri;
        $request->m_queryParameters = $queryParameters;
        $request->m_formFields = $formFields;
        $request->m_cookies = $cookies;
        $request->m_headers = $headers;
        $request->m_uploadedFiles = $uploadedFiles;
        $request->m_body = $body;
        return $request;
    }

    /** Helper to check a value is a valid query argument or form field. */
    private static function isValidValue(mixed $value): bool
    {
        return is_string($value) ||
            (is_array($value) && all($value, static fn (mixed $value): bool => is_string($value)));
    }

    /** Helper to capture the request method. */
    protected function captureMethod(): void
    {
        $this->m_method = strtoupper($_SERVER["REQUEST_METHOD"]);
    }

    /** Helper to capture the request URI. */
    protected function captureUri(): void
    {
        $hostAndPort = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "";

        if (str_contains($hostAndPort, ":")) {
            $pos = strpos($hostAndPort, ":");
            $host = substr($hostAndPort, 0, $pos);
            $port = filter_var(substr($hostAndPort, $pos + 1), FILTER_VALIDATE_INT) ?: null;
        } else {
            $host = $hostAndPort;
            $port = null;
        }

        $this->m_uri = (new Uri(
            "" !== ($_SERVER["HTTPS"] ?? "") ? UriContract::SchemeHttps : UriContract::SchemeHttp,
            $host,
            parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH),
        ))
            ->withQuery($_SERVER["QUERY_STRING"]);

        if (null !== $port) {
            $this->m_uri = $this->m_uri->withPort($port);
        }
    }

    /** Helper to add a header. */
    private function addHeader(string $name, string $value): void
    {
        $key = strtolower($name);

        if (!array_key_exists($key, $this->m_headers)) {
            $this->m_headers[$key] = [];
        }

        $this->m_headers[$key][] = new Header($name, $value);
    }

    /** Helper to capture the request headers. */
    protected function captureHeaders(): void
    {
        $this->m_headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, "HTTP_")) {
                $this->addHeader(str_replace("_", "-", substr($key, 5)), $value);
            } else if (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5",])) {
                // if we also have the header prefixed with HTTP_, prefer that one
                if (!array_key_exists("HTTP_{$key}", $_SERVER)) {
                    $this->addHeader(str_replace("_", "-", $key), $value);
                }

            }
        }
    }

    /** Helper to capture the request query parameters. */
    protected function captureQueryParameters(): void
    {
        $this->m_queryParameters = $_GET;
    }

    /** Helper to capture the request form data. */
    protected function captureFormFields(): void
    {
        $this->m_formFields = $_POST;
    }

    /** Helper to capture the uploaded files. */
    protected function captureUploadedFiles(): void
    {
        $this->m_uploadedFiles = [];

        foreach ($_FILES as $name => $files) {
            if (is_array($files["name"])) {
                $this->m_uploadedFiles[$name] = [];

                for ($idx = 0; $idx < count($files["name"]); $idx++) {
                    $this->m_uploadedFiles[$name][] = UploadedFile::create(
                        $files["name"][$idx],
                        $files["type"][$idx],
                        $files["tmp_name"][$idx],
                        $files["size"][$idx],
                        $files["error"][$idx],
                    );
                }
            } else {
                $this->m_uploadedFiles[$name] = [UploadedFile::fromFilesArray($files)];
            }
        }
    }

    /** Helper to capture the request cookies. */
    protected function captureCookies(): void
    {
        $this->m_cookies = $_COOKIE;
    }

    /** @inheritDoc */
    public function method(): \Bead\Web\HttpMethod
    {
        return $this->m_method;
    }

    /** @inheritDoc */
    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->m_headers);
    }

    /** @inheritDoc */
    public function headers(): array
    {
        return flatten($this->m_headers);
    }

    /** @inheritDoc */
    public function header(string $name): array
    {
        return $this->m_headers[strtolower($name)] ?? [];
    }

    /** @inheritDoc */
    public function isAjax(): bool
    {
        // FE frameworks need to set this header. many popular frameworks do so
        return some(
            $this->header("x-requested-with"),
            static fn (Header $header): bool => $header->value() === "XMLHttpRequest",
        );
    }

    /** @inheritDoc */
    public function scheme(): string
    {
        return $this->m_uri->scheme();
    }

    /** @inheritDoc */
    public function host(): string
    {
        return $this->m_uri->host();
    }

    /** @inheritDoc */
    public function port(): ?int
    {
        return $this->m_uri->port();
    }

    /** @inheritDoc */
    public function path(): string
    {
        return $this->m_uri->path();
    }

    /** @inheritDoc */
    public function query(): string
    {
        return $this->m_uri->query();
    }

    /** @inheritDoc */
    public function uri(): UriContract
    {
        return $this->m_uri;
    }

    /** @inheritDoc */
    public function hasQueryParameter(string $name): bool
    {
        return array_key_exists($name, $this->m_queryParameters);
    }

    /** @inheritDoc */
    public function allQueryParameters(): array
    {
        return $this->m_queryParameters;
    }

    /** @inheritDoc */
    public function queryParameter(string $name): string|array|null
    {
        return $this->m_queryParameters[$name] ?? null;
    }

    /** @inheritDoc */
    public function queryParameters(array $names): array
    {
        return array_filter(
            $this->m_queryParameters,
            static fn (string $parameterName): bool => in_array($parameterName, $names, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @inheritDoc */
    public function hasFormField(string $name): bool
    {
        return array_key_exists($name, $this->m_formFields);
    }

    /** @inheritDoc */
    public function allFormFields(): array
    {
        return $this->m_formFields;
    }

    /** @inheritDoc */
    public function formField(string $name): string|array|null
    {
        return $this->m_formFields[$name] ?? null;
    }

    /** @inheritDoc */
    public function formFields(array $names): array
    {
        return array_filter(
            $this->m_formFields,
            static fn (string $fieldName): bool => in_array($fieldName, $names, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @inheritDoc */
    public function has(string $name): bool
    {
        return $this->hasFormField($name) || $this->hasQueryParameter($name);
    }

    /** @inheritDoc */
    public function data(array|string $names): string|array|null
    {
        if (is_string($names)) {
            return $this->m_formFields[$names] ?? $this->m_queryParameters[$names] ?? null;
        }

        $data = [];

        foreach ($names as $name) {
            $value = $this->m_formFields[$name] ?? $this->m_queryParameters[$name] ?? null;

            if (null === $value) {
                continue;
            }

            $data[$name] = $value;
        }

        return $data;
    }

    /** @inheritDoc */
    public function hasUploadedFile(string $name): bool
    {
        return array_key_exists($name, $this->m_uploadedFiles);
    }

    /** @inheritDoc */
    public function uploadedFiles(): array
    {
        return flatten($this->m_uploadedFiles);
    }

    /** @inheritDoc */
    public function uploadedFile(string $name): array
    {
        return $this->m_uploadedFiles[$name] ?? [];
    }

    /** @inheritDoc */
    public function hasCookie(string $name): bool
    {
        return array_key_exists($name, $this->m_cookies);
    }

    /** @inheritDoc */
    public function cookies(): array
    {
        return $this->m_cookies;
    }

    /** @inheritDoc */
    public function cookie(string $name): ?string
    {
        return $this->m_cookies[$name] ?? null;
    }

    /** @inheritDoc */
    public function body(): string
    {
        if (null === $this->m_body) {
            $body = @file_get_contents("php://input");

            if (false === $body) {
                throw new RequestException($this, "Unable to read request body");
            }
        }

        return $this->m_body;
    }

    /** @inheritDoc */
    public function isJson(): bool
    {
        $contentType = $this->header("Content-Type");

        if (0 === count($contentType)) {
            return false;
        }

        $contentType = $contentType[0]->value();
        return "application/json" === $contentType || preg_match("/^application\/json( *;.*)?$/", $contentType);
    }

    /** @inheritDoc */
    public function json(): array
    {
        if (!$this->isJson()) {
            throw new RequestException($this, "Request body is not JSON");
        }

        // TODO reject if charset option in content-type header is not UTF-8 (json_decode() only accepts UTF-8)
        return json_decode($this->body(), true);
    }
}
