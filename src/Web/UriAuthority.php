<?php

declare(strict_types=1);

namespace Bead\Web;

use Bead\Contracts\Web\UriAuthority as UriAuthorityContract;
use Bead\Contracts\Web\UriUserInfo as UriUserInfoContract;

class UriAuthority implements UriAuthorityContract
{
    private ?UriUserInfoContract $m_userInfo;

    private string $m_host;

    private ?int $m_port;

    public function __construct(string $host, ?int $port = null, ?UriUserInfoContract $userInfo = null)
    {
        $this->m_userInfo = $userInfo;
        $this->m_host = $host;
        $this->m_port = $port;
    }

    public function __clone(): void
    {
        if (null !== $this->m_userInfo) {
            $this->m_userInfo = clone $this->m_userInfo;
        }
    }

    /** @inheritDoc */
    public function userInfo(): ?UriUserInfo
    {
        return $this->m_userInfo;
    }

    public function withUserInfo(UriUserInfoContract $userInfo): static
    {
        $clone = clone $this;
        $clone->m_userInfo = $userInfo;
        return $clone;
    }

    /** Fetch the username, if the authority has one. */
    public function username(): ?string
    {
        return $this->m_userInfo?->username();
    }

    /** Fetch the password, if the authority has one. */
    public function password(): ?string
    {
        return $this->m_userInfo?->password();
    }

    public function withoutUserInfo(): static
    {
        $clone = clone $this;
        $clone->m_userInfo = null;
        return $clone;
    }

    public function withUsername(string $username): static
    {
        $clone = clone $this;

        if (null === $clone->m_userInfo) {
            $clone->m_userInfo = new UriUserInfo($username);
        } else {
            $clone->m_userInfo = $clone->m_userInfo->withUsername($username);
        }

        return $clone;
    }

    public function withUsernameAndPassword(string $username, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->m_userInfo = new UriUserInfo($username, $password);
        return $clone;
    }

    /**
     * Set the password on the user info part of the authority.
     *
     * If the authority doesn't yet have a user info part one will be created with an empty username.
     *
     * @param string $password The password to set.
     * @return static a new UriAuthority that's the same as the current one, but with the given password.
     */
    public function withPassword(string $password): static
    {
        $clone = clone $this;

        if (null === $clone->m_userInfo) {
            $clone->m_userInfo = new UriUserInfo("", $password);
        } else {
            $clone->m_userInfo = $clone->m_userInfo->withPassword($password);
        }

        return $clone;
    }

    public function withoutPassword(): static
    {
        $clone = clone $this;

        if (null !== $clone->m_userInfo) {
            $clone->m_userInfo = $clone->m_userInfo->withoutPassword();
        }

        return $clone;
    }

    /** @inheritDoc */
    public function host(): string
    {
        return $this->m_host;
    }

    public function withHost(string $host): static
    {
        $clone = clone $this;
        $clone->m_host = $host;
        return $clone;
    }

    /** @inheritDoc */
    public function port(): ?int
    {
        return $this->m_port;
    }

    public function withPort(int $port): static
    {
        $clone = clone $this;
        $clone->m_port = $port;
        return $clone;
    }

    public function withoutPort(): static
    {
        $clone = clone $this;
        $clone->m_port = null;
        return $clone;
    }

    /** @inheritDoc */
    public function __toString(): string
    {
        $ui = "";

        if (null !== $this->userInfo()) {
            $ui = "{$this->userInfo()}@";
        }

        $ui .= $this->host();

        if (null !== $this->port()) {
            $ui .= ":{$this->port()}";
        }

        return $ui;
    }
}
