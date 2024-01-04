<?php

declare(strict_types=1);

namespace Bead\Queues\Azure;

use Bead\Contracts\Azure\Authorisation as AuthorizationContract;
use Bead\Contracts\Azure\ClientApplicationCredentials;
use Bead\Contracts\Azure\OAuth2Authoriser as OAuth2AuthoriserContract;
use Bead\Exceptions\Azure\AuthorisationException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class OAuth2Authoriser implements OAuth2AuthoriserContract
{
    private ClientInterface $httpClient;

    public function __construct(ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new Client();
    }

    protected function createRequest(string $resource, string $grantType, ClientApplicationCredentials $credentials): RequestInterface
    {
        return new Request(
            "get",
            "https://login.microsoftonline.com/{$credentials->tenantId()}/oauth2/token",
            ["content-type" => "application/x-www-form-urlencoded",],
            http_build_query([
                "grant_type" => $grantType,
                "resource" => $resource,
                "client_id" => $credentials->clientId(),
                "client_secret" => $credentials->secret(),
            ])
        );
    }

    protected function processResponse(ResponseInterface $response): AuthorizationContract
    {
        if (200 !== $response->getStatusCode()) {
            throw new AuthorisationException("Authentication failed: {$response->getReasonPhrase()}");
        }

        try {
            return AccessToken::fromJson((string) $response->getBody());
        } catch (Throwable $err) {
            throw new AuthorisationException("Invalid JSON response from authentication server: {$err->getMessage()}", previous: $err);
        }
    }

    public function authorise(string $resource, string $grantType, ClientApplicationCredentials $credentials): AuthorizationContract
    {
        try {
            $response = $this->httpClient->sendRequest($this->createRequest($resource, $grantType, $credentials));
        } catch (\Throwable $err) {
            throw new AuthorisationException("Network error authorising Azure access: {$err->getMessage()}", previous: $err);
        }

        return $this->processResponse($response);
    }
}