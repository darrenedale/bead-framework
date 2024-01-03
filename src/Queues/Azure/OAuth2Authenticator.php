<?php

declare(strict_types=1);

namespace Bead\Queues\Azure;

use Bead\Contracts\Azure\AccessToken as AzureAccessTokenContract;
use Bead\Contracts\Azure\Credentials;
use Bead\Contracts\Azure\OAuth2Authenticator as OAuth2AuthenticatorContract;
use Bead\Exceptions\Azure\AuthenticationException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

class OAuth2Authenticator implements OAuth2AuthenticatorContract
{
    private string $grantType;

    private string $resource;

    private ClientInterface $httpClient;

    public function __construct(string $grantType, string $resource, ClientInterface $httpClient = null)
    {
        $this->grantType = $grantType;
        $this->resource = $resource;
        $this->httpClient = $httpClient ?? new Client();
    }

    public function grantType(): string
    {
        return $this->grantType;
    }

    public function withGrantType(string $grantType): self
    {
        $clone = clone $this;
        $clone->grantType = $grantType;
        return $clone;
    }

    public function resource(): string
    {
        return $this->resource;
    }

    public function withResource(string $resource): self
    {
        $clone = clone $this;
        $clone->resource = $resource;
        return $clone;
    }

    protected function createRequest(Credentials $credentials): RequestInterface
    {
        return new Request(
            "get",
            "https://login.microsoftonline.com/{$credentials->tenantId()}/oauth2/token",
            [
                "content-type" => "application/x-www-form-urlencoded",
            ],
            http_build_query([
                "grant_type" => $this->grantType(),
                "resource" => $this->resource(),
                "client_id" => $credentials->clientId(),
                "client_secret" => $credentials->secret(),
            ])
        );
    }

    protected function processResponse(ResponseInterface $response): AzureAccessTokenContract
    {
        if (200 !== $response->getStatusCode()) {
            throw new AuthenticationException("Authentication failed: {$response->getReasonPhrase()}");
        }

        try {
            return AccessToken::fromJson((string) $response->getBody());
        } catch (Throwable $err) {
            throw new AuthenticationException("Invalid JSON response from authentication server: {$err->getMessage()}", previous: $err);
        }
    }

    public function authenticateUsing(Credentials $credentials): AzureAccessTokenContract
    {

        try {
            $response = $this->httpClient->sendRequest($this->createRequest($credentials));
        } catch (\Throwable $err) {
            throw new AuthenticationException("Network authenticating with Azure: {$err->getMessage()}", previous: $err);
        }

        return $this->processResponse($response);
    }
}