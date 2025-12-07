<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\Request as RequestContract;
use Bead\Contracts\Web\UploadedFile as UploadedFileContract;
use LogicException;

use const ARRAY_FILTER_USE_KEY;

/** Default implementation of the Request contract. */
class Request implements RequestContract
{
    private static ?Request $capturedRequest = null;

    private string $m_method;

    private string $m_scheme;

    private string $m_host;

    private ?int $m_port;

    private string $m_path;

    /** @var Header[]  */
    private array $m_headers;

    private string $m_query;

    private string $m_fragment;

    /** @var array<string,string|string[]> */
    private array $m_queryParameters;

    /** @var array<string,string|string[]> */
    private array $m_formFields;

    /** @var UploadedFile[] $m_uploadedFiles */
    private array $m_uploadedFiles;

    /** @var array<string,string> */
    private array $m_cookies;

    // TODO temporary private constructor to forbid external instantiation
    private function __construct()
    {}

    /**
     * Capture the incoming request.
     *
     * @return Request The request.
     */
    public static function capture(): Request
    {
        if (null === static::$capturedRequest) {
            self::$capturedRequest = new Request();
            self::$capturedRequest->captureMethod();
            self::$capturedRequest->captureScheme();
            self::$capturedRequest->captureHost();
            self::$capturedRequest->capturePort();
            self::$capturedRequest->capturePath();
            self::$capturedRequest->captureHeaders();
            self::$capturedRequest->captureQueryString();
            self::$capturedRequest->captureFragment();
            self::$capturedRequest->captureQueryParameters();
            self::$capturedRequest->captureFormFields();
            self::$capturedRequest->captureUploadedFiles();
            self::$capturedRequest->captureCookies();
        }

        return self::$capturedRequest;
    }

    protected function captureMethod(): void
    {
        $this->m_method = strtoupper($_SERVER["REQUEST_METHOD"]);
    }

    protected function captureScheme(): void
    {
        $this->m_scheme = (!empty($_SERVER["HTTPS"]) ? RequestContract::SchemeHttps : RequestContract::SchemeHttp);
    }

    protected function captureHost(): void
    {
        $this->m_host = $_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "";
    }

    protected function capturePort(): void
    {
        $this->m_port = filter_var($_SERVER["SERVER_PORT"] ?? "", FILTER_VALIDATE_INT) ?: null;
    }

    protected function capturePath(): void
    {
        $this->m_path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    }

    protected function captureQueryString(): void
    {
        $this->m_query = $_SERVER["QUERY_STRING"];
    }

    protected function captureFragment(): void
    {
        $this->m_fragment = parse_url($_SERVER["REQUEST_URI"], PHP_URL_FRAGMENT) ?? "";
    }

    protected function captureHeaders(): void
    {
        $this->m_headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, "HTTP_")) {
                $this->m_headers[] = new Header(str_replace("_", "-", substr($key, 5)), $value);
            } else if (in_array($key, ["CONTENT_TYPE", "CONTENT_LENGTH", "CONTENT_MD5",])) {
                $this->m_headers[] = new Header(str_replace("_", "-", $key), $value);
            }
        }
    }

    protected function captureQueryParameters(): void
    {
        $this->m_queryParameters = $_GET;
    }

    protected function captureFormFields(): void
    {
        $this->m_formFields = $_POST;
    }

    protected function captureUploadedFiles(): void
    {
        // TODO handle peculiarities around arrays of uploaded files with the same name in $_FILES (see
        //  https://www.php-fig.org/psr/psr-7/#16-uploaded-files)
        // array_map preserves keys when just one array is mapped
        $this->m_uploadedFiles = array_map(
            static fn (array $uploadedFile): UploadedFile => new UploadedFile($uploadedFile),
            $_FILES,
        );
    }

    protected function captureCookies(): void
    {
        $this->m_cookies = $_COOKIE;
    }

    /** @inheritDoc */
    public function method(): string
    {
        return $this->m_method;
    }

    /** @inheritDoc */
    public function headers(): array
    {
        return $this->m_headers;
    }

    /** @inheritDoc */
    public function header(string $name): array
    {
        return array_filter(
            $this->m_headers,
            static fn (Header $header): bool => $header->name() === mb_strtolower($name, "UTF-8"),
        );
    }

    /** @inheritDoc */
    public function scheme(): string
    {
        return $this->m_scheme;
    }

    /** @inheritDoc */
    public function host(): string
    {
        return $this->m_host;
    }

    /** @inheritDoc */
    public function port(): ?int
    {
        return $this->m_port;
    }

    /** @inheritDoc */
    public function path(): string
    {
        return $this->m_path;
    }

    /** @inheritDoc */
    public function query(): string
    {
        return $this->m_query;
    }

    /** @inheritDoc */
    public function fragment(): string
    {
        return $this->m_fragment;
    }

    /** @inheritDoc */
    public function hasQueryParameter(string $name): bool
    {
        return array_key_exists($name, $this->m_queryParameters);
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
    public function allQueryParameters(): array
    {
        return $this->m_queryParameters;
    }

    /** @inheritDoc */
    public function hasFormField(string $name): bool
    {
        return array_key_exists($name, $this->m_formFields);
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
    public function allFormFields(): array
    {
        return $this->m_formFields;
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
    public function uploadedFile(string $name): UploadedFileContract
    {
        if (!$this->hasUploadedFile($name)) {
            throw new LogicException("Uploaded file \"{$name}\" does not exist");
        }

        return $this->m_uploadedFiles[$name];
    }

    /** @inheritDoc */
    public function hasCookie(string $name): bool
    {
        return array_key_exists($name, $this->m_cookies);
    }

    /** @inheritDoc */
    public function cookie(string $name): ?string
    {
        return $this->m_cookies[$name] ?? null;
    }

    /** @inheritDoc */
    public function cookies(): array
    {
        return $this->m_cookies;
    }
}