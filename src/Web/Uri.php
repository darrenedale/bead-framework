<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\Uri as UriContract;
use Bead\Contracts\Web\UriAuthority as UriAuthorityContract;
use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

class Uri implements UriContract
{

    private string $m_scheme;

    private UriAuthorityContract $m_authority;

    private string $m_path;

    private ?string $m_query;

    private ?string $m_fragment;

    /** By default constructs the URI https://localhost/. */
    public function __construct(string $scheme = "https", string $host = "localhost", string $path = "/")
    {
        $this->m_scheme = $scheme;
        $this->m_authority = new UriAuthority($host);
        $this->m_path = $path;
        $this->m_query = null;
        $this->m_fragment = null;
    }

    /** @inheritDoc */
    public function scheme(): string
    {
        return $this->m_scheme;
    }

    /** @inheritDoc */
    public function authority(): UriAuthorityContract
    {
        return $this->m_authority;
    }

    /** @inheritDoc */
    public function userInfo(): ?UriUserInfo
    {
        return $this->m_authority->userInfo();
    }

    /** @inheritDoc */
    public function username(): ?string
    {
        return $this->userInfo()?->username();
    }

    /** @inheritDoc */
    public function password(): ?string
    {
        return $this->userInfo()?->password();
    }

    /** @inheritDoc */
    public function host(): string
    {
        return $this->authority()->host();
    }

    /** @inheritDoc */
    public function port(): ?int
    {
        return $this->authority()->port();
    }

    /** @inheritDoc */
    public function path(): string
    {
        return $this->m_path;
    }

    /** @inheritDoc */
    public function query(): ?string
    {
        return $this->m_query;
    }

    /** @inheritDoc */
    public function fragment(): ?string
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

    public function withUserInfo(UriUserInfoContract $userInfo): static
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

    /** The provided username must not be escaped. */
    public function withUsername(string $username): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUsername($username);
        return $clone;
    }

    /** The provided password must not be escaped. */
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

    /** The provided username and password must not be escaped. */
    public function withUsernameAndPassword(string $username, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUsernameAndPassword($username, $password);
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

    /** The provied path must be properly escaped. */
    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->m_path = $path;
        return $clone;
    }

    /** The provided query string must be properly escaped. */
    public function withQuery(string $query): static
    {
        $clone = clone $this;
        $clone->m_query = $query;
        return $clone;
    }

    public function withoutQuery(): static
    {
        $clone = clone $this;
        $clone->m_query = null;
        return $clone;
    }

    /** The provided fragment must not be escaped. */
    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->m_fragment = $fragment;
        return $clone;
    }

    public function withoutFragment(): static
    {
        $clone = clone $this;
        $clone->m_fragment = null;
        return $clone;
    }

    public function __toString(): string
    {
        $uri = "{$this->scheme()}://{$this->authority()}{$this->path()}";

        if (null !== $this->query()) {
            $uri .= "?{$this->query()}";
        }

        if (null !== $this->fragment()) {
            $uri .= "#" . rawurlencode($this->fragment());
        }

        return $uri;
    }
}
