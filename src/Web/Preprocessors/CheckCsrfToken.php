<?php

namespace Bead\Web\Preprocessors;

use Bead\Contracts\RequestPreprocessor;
use Bead\Contracts\Response;
use Bead\Exceptions\CsrfTokenVerificationException;
use Bead\Facades\WebApplication as WebApp;
use Bead\Web\Request;

class CheckCsrfToken implements RequestPreprocessor
{

    /**
     * Determine whether the incoming request must pass CSRF verification.
     *
     * The default behaviour is to require verification for all requests that don't use the GET, HEAD or OPTIONS HTTP
     * methods. Use this method as a customisation point in your WebApplication subclass to implement more detailed
     * logic.
     *
     * @param Request $request The incoming request.
     *
     * @return bool `true` if the request requires CSRF validation, `false` if not.
     */
    protected function requestRequiresCsrf(Request $request): bool
    {
        return match ($request->method()) {
            "GET", "HEAD", "OPTIONS" => false,
            default => true,
        };
    }


    /**
     * Extract the CSRF token submitted with a request.
     *
     * Use this as a customisation point in your WebApplication subclass if you need custom logic to obtain the token
     * from Requests. The default behaviour is to look for a `_token` POST field, or an X-CSRF-TOKEN header if the
     * field is not present (the latter case is primarily for AJAX requests).
     *
     * @param Request $request The request from which to extract the CSRF token.
     *
     * @return string|null The token, or `null` if no CSRF token is found in the request.
     */
    protected function csrfTokenFromRequest(Request $request): ?string
    {
        return $request->postData("_token") ?? $request->header("X-CSRF-TOKEN");
    }

    /**
     * Helper to verify the CSRF token in an incoming request is correct, if necessary.
     *
     * Not all requests require CSRF verification. requestRequiresCsrf() is used to determine whether the request
     * requires it. The CSRF token is extracted from the request by csrfTokenFromRequest().
     *
     * @param Request $request The incoming request.
     *
     * @throws CsrfTokenVerificationException if the CSRF token in the request is not verified.
     */
    protected function verifyCsrf(Request $request): void
    {
        $requestCsrf = $this->csrfTokenFromRequest($request);

        if (!isset($requestCsrf) || !hash_equals(WebApp::csrf(), $requestCsrf)) {
            throw new CsrfTokenVerificationException($request, "The CSRF token is missing from the request or is invalid.");
        }
    }

    /**
     * Verify the request's CSRF token.
     *
     * Checks whether the request requires CSRF verification. If it does and the verification fails, a
     * CsrfTokenVerificationException is thrown.
     *
     * @throws CsrfTokenVerificationException
     */
    public function preprocessRequest(Request $request): ?Response
    {
        if ($this->requestRequiresCsrf($request)) {
            $this->verifyCsrf($request);
        }

        return null;
    }
}
