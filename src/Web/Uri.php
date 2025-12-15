<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\Uri as UriContract;
use Bead\Contracts\Web\UriAuthority as UriAuthorityContract;

class Uri implements UriContract
{
    public const string SchemeHttp = "http";

    public const string SchemeHttps = "https";

    private string $m_scheme;

    private UriAuthorityContract $m_authority;

    private string $m_path;

    private string $m_query;

    private string $m_fragment;

    /** By default constructs the URI https://localhost/. */
    public function __construct(string $scheme = "https", string $host = "localhost", string $path = "/")
    {
        $this->m_scheme = $scheme;
        $this->m_authority = new UriAuthority($host);
        $this->m_path = $path;
        $this->m_query = "";
        $this->m_fragment = "";
    }

    public function scheme(): string
    {
        return $this->m_scheme;
    }

    public function authority(): UriAuthorityContract
    {
        return $this->m_authority;
    }

    public function userInfo(): ?UriUserInfo
    {
        return $this->m_authority->userInfo();
    }

    public function username(): ?string
    {
        return $this->userInfo()?->username();
    }

    public function password(): ?string
    {
        return $this->userInfo()?->password();
    }

    public function host(): string
    {
        return $this->authority()->host();
    }

    public function port(): ?int
    {
        return $this->authority()->port();
    }

    public function path(): string
    {
        return $this->m_path;
    }

    public function query(): string
    {
        return $this->m_query;
    }

    public function fragment(): string
    {
        return $this->m_fragment;
    }

    public function withScheme(string $scheme): static
    {
        $clone = clone $this;
        $clone->m_scheme = $scheme;
        return $clone;
    }

    public function withAuthority(UriAuthorityContract $authority): static
    {
        $clone = clone $this;
        $clone->m_authority = $authority;
        return $clone;
    }

    public function withUserInfo(string $username, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUsernameAndPassword($username, $password);
        return $clone;
    }

    public function withUserInfoObject(\Bead\Contracts\Web\UriUserInfo $userInfo): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUserInfo($userInfo);
        return $clone;
    }

    public function withoutUserInfo(): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withoutUserInfo();
        return $clone;
    }

    public function withUsername(string $username): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUsername($username);
        return $clone;
    }

    public function withPassword(string $password): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withPassword($password);
        return $clone;
    }

    public function withoutPassword(): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withoutPassword();
        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withHost($host);
        return $clone;
    }

    public function withPort(?int $port): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withPort($port);
        return $clone;
    }

    public function withoutPort(): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withoutPort();
        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->m_path = $path;
        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone = clone $this;
        $clone->m_query = $query;
        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->m_fragment = $fragment;
        return $clone;
    }

    public function withoutQuery(): static
    {
        $clone = clone $this;
        $clone->m_query = "";
        return $clone;
    }

    public function withoutFragment(): static
    {
        $clone = clone $this;
        $clone->m_fragment = "";
        return $clone;
    }

    public function getScheme(): string
    {
        return $this->scheme();
    }

    public function getAuthority(): string
    {
        return (string) $this->authority();
    }

    public function getUserInfo(): string
    {
        return (string) $this->userInfo();
    }

    public function getHost(): string
    {
        return $this->host();
    }

    public function getPort(): ?int
    {
        return $this->port();
    }

    public function getPath(): string
    {
        return $this->path();
    }

    public function getQuery(): string
    {
        return $this->query();
    }

    public function getFragment(): string
    {
        return $this->fragment();
    }

    public function __toString()
    {
        $uri = "{$this->scheme()}://{$this->authority()}{$this->m_path}";

        if ("" !== $this->query()) {
            $uri .= "?{$this->query()}";
        }

        if ("" !== $this->fragment()) {
            $uri .= "#{$this->fragment()}";
        }

        return $uri;
    }
}
