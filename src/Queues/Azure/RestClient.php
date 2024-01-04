<?php

declare(strict_types=1);

namespace Bead\Queues\Azure;

use Bead\Contracts\Azure\Authorisation as AzureAuthorisationContract;
use Bead\Contracts\Azure\RestClient as AzureRestClientContract;
use Bead\Contracts\Azure\RestCommand as AzureRestCommandContract;
use Bead\Exceptions\Azure\AuthorisationException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class RestClient implements AzureRestClientContract
{
    private ClientInterface $httpClient;

    public function __construct(?ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? new HttpClient();
    }

    public function httpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public function withHttpClient(ClientInterface $httpClient): self
    {
        $clone = clone $this;
        $clone->httpClient = $httpClient;
        return $clone;
    }

    public function send(AzureRestCommandContract $command, ?AzureAuthorisationContract $authorisation = null): mixed
    {
        try {
            $headers = array_merge($command->headers(), $authorisation?->headers() ?? []);
            $response = $this->httpClient()->sendRequest(new Request($command->method(), $command->uri(), $headers, $command->body()));
        } catch (ClientExceptionInterface $err) {
            // TODO use the correct exception type
            throw new \RuntimeException("Network error communicating with Azure REST API endpoint {$command->uri()}: {$err->getMessage()}", previous: $err);
        }

        if (401 === $response->getStatusCode()) {
            throw new AuthorisationException("Azure REST API returned a 401 Not Authorised response: {$response->getReasonPhrase()}");
        }

        return $command->parseResponse($response);
    }
}
