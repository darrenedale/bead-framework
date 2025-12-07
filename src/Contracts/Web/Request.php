<?php

declare(strict_types=1);

namespace Bead\Contracts\Web;

/**
 * TODO add body access, incl. JSON
 */
interface Request
{
    /** @var string The unencrypted HTTP scheme. */
    public const SchemeHttp = "http";

    /** @var string The encrypted HTTP scheme. */
    public const SchemeHttps = "https";

    /** @var string The GET HTTP method. */
    public const MethodGet = "GET";

    /** @var string The POST HTTP method. */
    public const MethodPost = "POST";

    /** @var string The PUT HTTP method. */
    public const MethodPut = "PUT";

    /** @var string The DELETE HTTP method. */
    public const MethodDelete = "DELETE";

    /** @var string The PATCH HTTP method. */
    public const MethodPatch = "PATCH";

    /** @var string The HEAD HTTP method. */
    public const MethodHead = "HEAD";

    /** @var string The OPTIONS HTTP method. */
    public const MethodOptions = "OPTIONS";

    /** @var string The TRACE HTTP method. */
    public const MethodTrace = "TRACE";

    /**
     * The request HTTP method.
     *
     * Must be one of the class Method... constants.
     */
    public function method(): string;

    /** @return Header[] The request headers. */
    public function headers(): array;

    /**
     * @param string $name The name of the header(s) to retrieve.
     *
     * @return Header[] All the request headers with the given name.
     */
    public function header(string $name): array;

    /**
     * The HTTP scheme used for the request.
     *
     * Typically http or https, but may be others.
     *
     * @return string The scheme.
     */
    public function scheme(): string;

    /**
     * The host part of the Request URL.
     *
     * @return string The host.
     */
    public function host(): string;

    /**
     * The port part of the Request URL, if set.
     *
     * @return int|null The port if set, null if not.
     */
    public function port(): ?int;

    /**
     * The path part of the Request URL.
     *
     * @return string The path.
     */
    public function path(): string;

    /**
     * The query string of the Request URL.
     *
     * The query string will be in its encoded form, and will not include the ? delimiter at the start.
     *
     * @return string The query string.
     */
    public function query(): string;

    /**
     * The fragment part of the Request URL.
     *
     * The fragment will be decoded, and will include the leading # delimiter.
     *
     * @return string The fragment.
     */
    public function fragment(): string;

    /** Check whether the request contains a named query parameter. */
    public function hasQueryParameter(string $name): bool;

    /**
     * @param string $name The name of the query parameter to fetch.
     *
     * The values will have been decoded from the query string.
     *
     * @return string|string[]|null The string value of the parameter, or an array of string values if it's an array
     * parameter, or null if there's no such query parameter.
     */
    public function queryParameter(string $name): string | array | null;

    /**
     * Fetch a number of query parameters at once.
     *
     * The returned array must contain all of the query parameters specified that exist in the Request object. It must
     * not contain any other query parameters. Any names provided that don't exist in the Request object should be
     * absent from the returned array.
     *
     * The values will have been decoded from the query string.
     *
     * @param string[] $names The query parameter names to fetch.
     *
     * @return array<string,string|string[]> The values of the requested parameters.
     */
    public function queryParameters(array $names): array;

    /**
     * Fetch all query parameters.
     *
     * The values will have been decoded from the query string.
     *
     * @return array<string,string|string[]> The values of all the query parameters.
     */
    public function allQueryParameters(): array;

    /** Check whether the request contains a named form field. */
    public function hasFormField(string $name): bool;

    /**
     * @param string $name The name of the form data to fetch.
     *
     * The values will have been decoded from whatever encoding was used in the request body.
     *
     * @return string|string[]|null The string value of the form data, or an array of string values if it's an array
     * parameter, or null if there's no such form data.
     */
    public function formField(string $name): string | array | null;

    /**
     * Fetch a number of form fields at once.
     *
     * The returned array must contain all of the form fields specified that exist in the Request object. It must not
     * contain any other form fields. Any names provided that don't exist in the Request object should be absent from
     * the returned array.
     *
     * The values will have been decoded from whatever encoding was used in the request body.
     *
     * @param string[] $names The form field names to fetch.
     *
     * @return array<string,string|string[]> The values of the requested form fields.
     */
    public function formFields(array $names): array;

    /**
     * Fetch all form fields.
     *
     * The values will have been decoded from whatever encoding was used in the request body.
     *
     * @return array<string,string|string[]> The values of all the form fields.
     */
    public function allFormFields(): array;

    /** Check whether the request contains a named form field or query parameter. */
    public function has(string $name): bool;

    /**
     * Fetch some data from the query parameters or form fields, or a combination of the two.
     *
     * This method looks up the provided name(s) in the form fields. Any name that isn't found there is looked
     * up in the query parameters. If a single name is provided, the return value is a single string, or an array of
     * strings if the name identifies an array value. If an array of names is provided, the returned value is a map of
     * names to values (the values being strings or arrays of strings). If a single name is provided and it doesn't
     * exist, null is returned.
     *
     * The values will have been decoded from the URL query string or whatever encoding was used in the request body.
     *
     * @param string|array $names The names of the items to fetch.
     *
     * @return string|string[]|array<string,string|string[]>|null The requested data.
     */
    public function data(string | array $names): string | array | null;

    /** Check whether an uploaded file with a given name exists in the Request. */
    public function hasUploadedFile(string $name): bool;

    /** Fetch a named uploaded file. */
    public function uploadedFile(string $name): UploadedFile;

    /** Check whether the request contains a named cookie. */
    public function hasCookie(string $name): bool;

    /**
     * Fetch the value of a single cookie from the request.
     *
     * @return string|null The value of the cookie, or null if the cookie is not set.
     */
    public function cookie(string $name): ?string;

    /**
     * Fetch all the request's cookies.
     *
     * @return array<string,string> All the cookies that are set.
     */
    public function cookies(): array;
}
