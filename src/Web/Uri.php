<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\Uri as UriContract;
use Bead\Contracts\Web\UriAuthority as UriAuthorityContract;
use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

/** Default implementation of the Uri contract. */
class Uri implements UriContract
{

    private string $m_scheme;

    private UriAuthorityContract $m_authority;

    private string $m_path;

    /** @var string|null The URI's query string, if it has one. */
    private ?string $m_query;

    /** @var string|null The URI's fragment, if it has one. */
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

    /**
     * Obtain a replica of the URI with a potentially different scheme.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI with a potentially different scheme.
     */
    public function withScheme(string $scheme): static
    {
        $clone = clone $this;
        $clone->m_scheme = $scheme;
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different authority.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI with a potentially different authority.
     */
    public function withAuthority(UriAuthorityContract $authority): static
    {
        $clone = clone $this;
        $clone->m_authority = $authority;
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different user info section.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI with a potentially different user info section.
     */
    public function withUserInfo(UriUserInfoContract $userInfo): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUserInfo($userInfo);
        return $clone;
    }

    /**
     * Obtain a replica of the URI with no user info section.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI with no user info section.
     */
    public function withoutUserInfo(): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withoutUserInfo();
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different username.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $username The new username. It must not be escaped.
     *
     * @return static A replica of the URI with a potentially different username.
     */
    public function withUsername(string $username): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUsername($username);
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different password.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $password The new password. It must not be escaped.
     *
     * @return static A replica of the URI with a potentially different password.
     */
    public function withPassword(string $password): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withPassword($password);
        return $clone;
    }

    /**
     * Obtain a replica of the URI without a password.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI without a password.
     */
    public function withoutPassword(): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withoutPassword();
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different username and password.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $username The new username. It must not be escaped.
     * @param string|null $password The new password. It must not be escaped. The default of null will result in the
     * password being removed from the replica URI.
     *
     * @return static A replica of the URI with a potentially different username and password.
     */
    public function withUsernameAndPassword(string $username, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withUsernameAndPassword($username, $password);
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different host.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $host The new host.
     *
     * @return static A replica of the URI with a potentially different host.
     */
    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withHost($host);
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different port.
     *
     * The immutability of the original object is preserved.
     *
     * @param int $port The new port.
     *
     * @return static A replica of the URI with a potentially different port.
     */
    public function withPort(int $port): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withPort($port);
        return $clone;
    }

    /**
     * Obtain a replica of the URI without a port.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI without a port.
     */
    public function withoutPort(): static
    {
        $clone = clone $this;
        $clone->m_authority = $clone->m_authority->withoutPort();
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different path.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $path The new path. It must be properly escaped.
     *
     * @return static A replica of the URI with a potentially different path.
     */
    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->m_path = $path;
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different query string.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $query The new query string. It must be properly escaped.
     *
     * @return static A replica of the URI with a potentially different query string.
     */
    public function withQuery(string $query): static
    {
        $clone = clone $this;
        $clone->m_query = $query;
        return $clone;
    }

    /**
     * Obtain a replica of the URI without a query string.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI without a query string.
     */
    public function withoutQuery(): static
    {
        $clone = clone $this;
        $clone->m_query = null;
        return $clone;
    }

    /**
     * Obtain a replica of the URI with a potentially different fragment.
     *
     * The immutability of the original object is preserved.
     *
     * @param string $fragment The new fragment. It must not be escaped.
     *
     * @return static A replica of the URI with a potentially different fragment.
     */
    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->m_fragment = $fragment;
        return $clone;
    }

    /**
     * Obtain a replica of the URI without a fragment.
     *
     * The immutability of the original object is preserved.
     *
     * @return static A replica of the URI without a fragment.
     */
    public function withoutFragment(): static
    {
        $clone = clone $this;
        $clone->m_fragment = null;
        return $clone;
    }

    /** Obtain a string representation of the URI. */
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
